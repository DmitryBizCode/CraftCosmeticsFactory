<?php

namespace App\Services\User;

use PDO;
use App\Services\Database\SqlSupportServiceTemplate;

class StaffService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        new SqlSupportServiceTemplate($pdo); // Ініціалізуємо static PDO
    }

    public function getAll(): array
    {
        return SqlSupportServiceTemplate::getAll('staff', 'id DESC');
    }

    public function getById(int $id): ?array
    {
        return SqlSupportServiceTemplate::getById('staff', 'id', $id);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO staff (first_name, last_name, email, phone_number, address, settlement, access_layer) 
                VALUES (:first_name, :last_name, :email, :phone_number, :address, :settlement, :access_layer)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => (!empty($data['last_name']) && $data['last_name'] !== '') ? $data['last_name'] : null,
            ':email' => $data['email'],
            ':phone_number' => $data['phone_number'],
            ':address' => $data['address'],
            ':settlement' => $data['settlement'],
            ':access_layer' => (int)($data['access_layer'] ?? 1),
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE staff 
                SET first_name = :first_name, last_name = :last_name, email = :email, 
                    phone_number = :phone_number, address = :address, settlement = :settlement, access_layer = :access_layer
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':first_name' => $data['first_name'],
            ':last_name' => (!empty($data['last_name']) && $data['last_name'] !== '') ? $data['last_name'] : null,
            ':email' => $data['email'],
            ':phone_number' => $data['phone_number'],
            ':address' => $data['address'],
            ':settlement' => $data['settlement'],
            ':access_layer' => (int)($data['access_layer'] ?? 1),
        ]);
    }

    public function delete(int $id): bool
    {
        try {
            SqlSupportServiceTemplate::delete('staff', 'id', $id);
            return true;
        } catch (\Exception $e) {
            error_log("Error deleting staff: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати список staff для вибору (тільки id, first_name, last_name, email)
     */
    public function getAllForSelect(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, first_name, last_name, email FROM staff ORDER BY first_name, last_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

