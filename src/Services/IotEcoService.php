<?php

namespace App\Services;

use PDO;

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
}

