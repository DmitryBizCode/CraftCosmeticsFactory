<?php

namespace App\Services\Iot;

use PDO;
use App\Services\Database\SqlSupportServiceTemplate;

class IotEcoService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Отримати IoT сенсори для eco_system
     */
    public function getIotSensorsByEcoSystemId(int $ecoSystemId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ie.*, i.name as iot_name, i.start_value as iot_start_value, i.end_value as iot_end_value
            FROM iot_eco ie 
            JOIN iot i ON ie.iot_id = i.id 
            WHERE ie.eco_system_id = :eco_system_id
        ");
        $stmt->execute([':eco_system_id' => $ecoSystemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати останнє значення для датчика
     */
    public function getLastValue(int $iotEcoId): ?float
    {
        $stmt = $this->pdo->prepare("
            SELECT value FROM iot_eco_values 
            WHERE iot_eco_id = :iot_eco_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([':iot_eco_id' => $iotEcoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['value'] : null;
    }

    /**
     * Отримати значення датчика з історії (останні N записів)
     */
    public function getValuesHistory(int $iotEcoId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT value, created_at 
            FROM iot_eco_values 
            WHERE iot_eco_id = :iot_eco_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':iot_eco_id', $iotEcoId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_reverse($results); // Повертаємо в хронологічному порядку
    }

    /**
     * Додати нове значення до історії
     */
    public function addValue(int $iotEcoId, float $value): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO iot_eco_values (iot_eco_id, value) 
            VALUES (:iot_eco_id, :value)
        ");
        $stmt->execute([
            ':iot_eco_id' => $iotEcoId,
            ':value' => $value
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Встановити значення вручну (з генерації далі)
     */
    public function setValue(int $iotEcoId, float $value): bool
    {
        return $this->addValue($iotEcoId, $value);
    }

    /**
     * Отримати останні значення для всіх датчиків в eco_system
     */
    public function getLastValuesForEcoSystem(int $ecoSystemId): array
    {
        $sensors = $this->getIotSensorsByEcoSystemId($ecoSystemId);
        $result = [];
        
        foreach ($sensors as $sensor) {
            $lastValue = $this->getLastValue($sensor['id']);
            if ($lastValue !== null) {
                $result[$sensor['id']] = $lastValue;
            }
        }
        
        return $result;
    }

    /**
     * Отримати статистику для датчика за період (avg, min, max)
     */
    public function getStatisticsForSensor(int $iotEcoId, string $dateFrom = null, string $dateTo = null): array
    {
        $sql = "
            SELECT 
                AVG(value) as avg_value,
                MIN(value) as min_value,
                MAX(value) as max_value,
                COUNT(*) as total_count
            FROM iot_eco_values 
            WHERE iot_eco_id = :iot_eco_id
        ";
        
        $params = [':iot_eco_id' => $iotEcoId];
        
        if ($dateFrom) {
            $sql .= " AND created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND created_at <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'avg' => $result ? (float)$result['avg_value'] : 0,
            'min' => $result ? (float)$result['min_value'] : 0,
            'max' => $result ? (float)$result['max_value'] : 0,
            'count' => $result ? (int)$result['total_count'] : 0,
        ];
    }

    /**
     * Отримати дані для гістограми (групування на основі кількості записів, максимум 7 груп)
     */
    public function getHistogramData(int $iotEcoId, string $period = '7'): array
    {
        // Визначаємо SQL запит залежно від періоду
        $sql = "
            SELECT value, created_at
            FROM iot_eco_values 
            WHERE iot_eco_id = :iot_eco_id 
        ";
        
        if ($period === 'hour') {
            $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        } elseif ($period === 'day') {
            $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        } else {
            $days = (int)$period;
            if ($days <= 0) {
                $days = 7;
            }
            $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        }
        
        $sql .= " ORDER BY created_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':iot_eco_id', $iotEcoId, PDO::PARAM_INT);
        if ($period !== 'hour' && $period !== 'day') {
            $stmt->bindValue(':days', (int)$period, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $allRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($allRecords)) {
            return [];
        }
        
        $totalCount = count($allRecords);
        
        // Визначаємо кількість груп (максимум 7, але не більше ніж загальна кількість записів)
        $numberOfGroups = min(7, $totalCount);
        
        if ($numberOfGroups === 0) {
            return [];
        }
        
        // Розраховуємо кількість записів на групу
        $recordsPerGroup = ceil($totalCount / $numberOfGroups);
        
        $result = [];
        
        // Розділяємо записи на групи
        for ($groupIndex = 0; $groupIndex < $numberOfGroups; $groupIndex++) {
            $startIndex = $groupIndex * $recordsPerGroup;
            $endIndex = min($startIndex + $recordsPerGroup, $totalCount);
            
            if ($startIndex >= $totalCount) {
                break;
            }
            
            // Витягуємо записи для цієї групи
            $groupRecords = array_slice($allRecords, $startIndex, $endIndex - $startIndex);
            
            if (empty($groupRecords)) {
                continue;
            }
            
            // Обчислюємо середнє значення для групи
            $sum = 0;
            foreach ($groupRecords as $record) {
                $sum += (float)$record['value'];
            }
            $avgValue = $sum / count($groupRecords);
            
            // Визначаємо часовий діапазон групи
            $firstRecord = $groupRecords[0];
            $lastRecord = end($groupRecords);
            $firstDate = date('Y-m-d H:i', strtotime($firstRecord['created_at']));
            $lastDate = date('Y-m-d H:i', strtotime($lastRecord['created_at']));
            
            // Якщо перша і остання дата однакові, показуємо тільки одну
            if ($firstDate === $lastDate) {
                $label = $firstDate;
            } else {
                $label = $firstDate . ' - ' . $lastDate;
            }
            
            $result[] = [
                'date' => $label,
                'avg_value' => $avgValue,
                'count' => count($groupRecords)
            ];
        }
        
        return $result;
    }

    /**
     * Отримати інформацію про датчик з повною інформацією
     */
    public function getSensorInfo(int $iotEcoId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ie.*, i.start_value as iot_start_value, i.end_value as iot_end_value, ie.eco_system_id
            FROM iot_eco ie 
            JOIN iot i ON ie.iot_id = i.id 
            WHERE ie.id = :id
        ");
        $stmt->execute([':id' => $iotEcoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Отримати кількість критичних попереджень
     */
    public function getCriticalWarnings(int $iotEcoId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT critical_warnings_count 
            FROM iot_eco 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $iotEcoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['critical_warnings_count'] : 0;
    }

    /**
     * Отримати eco system для рецепту
     */
    public function getEcoSystemForRecipe(int $recipeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT es.*, r.name as recipe_name, r.time_to_complete as recipe_time_to_complete
            FROM eco_system es
            JOIN recipes r ON es.recipe_id = r.id
            WHERE es.recipe_id = :recipe_id
            LIMIT 1
        ");
        $stmt->execute([':recipe_id' => $recipeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Збільшити лічильник критичних попереджень
     */
    public function incrementCriticalWarnings(int $iotEcoId): bool
    {
        try {
            // Спочатку перевіряємо, чи існує колонка
            $checkStmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'iot_eco' 
                AND COLUMN_NAME = 'critical_warnings_count'
            ");
            $checkStmt->execute();
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && (int)$result['count'] === 0) {
                // Колонка не існує, додаємо її
                $alterStmt = $this->pdo->prepare("
                    ALTER TABLE iot_eco 
                    ADD COLUMN critical_warnings_count INT UNSIGNED NOT NULL DEFAULT 0
                ");
                $alterStmt->execute();
            }
            
            // Тепер оновлюємо лічильник
            $stmt = $this->pdo->prepare("
                UPDATE iot_eco 
                SET critical_warnings_count = critical_warnings_count + 1 
                WHERE id = :id
            ");
            return $stmt->execute([':id' => $iotEcoId]);
        } catch (\Exception $e) {
            // Якщо щось пішло не так, просто повертаємо false
            // Це не повинно ламати основний функціонал
            error_log("Error incrementing critical warnings: " . $e->getMessage());
            return false;
        }
    }
}

