<?php

namespace App\Services;

use PDO;

class IotService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        new SqlSupportServiceTemplate($pdo); // Ініціалізуємо static PDO
    }

    public function getAll(): array
    {
        return SqlSupportServiceTemplate::getAll('iot', 'id DESC');
    }

    public function getById(int $id): ?array
    {
        return SqlSupportServiceTemplate::getById('iot', 'id', $id);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO iot (name, start_value, end_value, description) 
                VALUES (:name, :start_value, :end_value, :description)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':start_value' => (float)$data['start_value'],
            ':end_value' => (float)$data['end_value'],
            ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE iot 
                SET name = :name, start_value = :start_value, end_value = :end_value, 
                    description = :description 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':start_value' => (float)$data['start_value'],
            ':end_value' => (float)$data['end_value'],
            ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
        ]);
    }

    public function delete(int $id): bool
    {
        try {
            SqlSupportServiceTemplate::delete('iot', 'id', $id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getEcoSystems(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM eco_system ORDER BY date_start DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
