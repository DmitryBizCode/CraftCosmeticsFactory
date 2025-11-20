<?php

namespace App\Services\User;

use PDO;
use App\Services\Database\SqlSupportServiceTemplate;

class ClientService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        new SqlSupportServiceTemplate($pdo); // Ініціалізуємо static PDO
    }

    public function getAll(): array
    {
        return SqlSupportServiceTemplate::getAll('clients', 'id DESC');
    }

    public function getById(int $id): ?array
    {
        return SqlSupportServiceTemplate::getById('clients', 'id', $id);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO clients (first_name, last_name, email, password, address, phone_number, photo) 
                VALUES (:first_name, :last_name, :email, :password, :address, :phone_number, :photo)";
        
        // Хешуємо пароль (обов'язковий при створенні)
        if (empty($data['password'])) {
            throw new \Exception('Password is required when creating a client');
        }
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => (!empty($data['last_name']) && $data['last_name'] !== '') ? $data['last_name'] : null,
            ':email' => $data['email'],
            ':password' => $hashedPassword,
            ':address' => (!empty($data['address']) && $data['address'] !== '') ? $data['address'] : null,
            ':phone_number' => (!empty($data['phone_number']) && $data['phone_number'] !== '') ? $data['phone_number'] : null,
            ':photo' => (!empty($data['photo']) && $data['photo'] !== '') ? $data['photo'] : null,
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        // Якщо пароль не вказано, залишаємо старий
        $sql = "UPDATE clients 
                SET first_name = :first_name, last_name = :last_name, email = :email, 
                    address = :address, phone_number = :phone_number, photo = :photo";
        
        $params = [
            ':id' => $id,
            ':first_name' => $data['first_name'],
            ':last_name' => (!empty($data['last_name']) && $data['last_name'] !== '') ? $data['last_name'] : null,
            ':email' => $data['email'],
            ':address' => (!empty($data['address']) && $data['address'] !== '') ? $data['address'] : null,
            ':phone_number' => (!empty($data['phone_number']) && $data['phone_number'] !== '') ? $data['phone_number'] : null,
            ':photo' => (!empty($data['photo']) && $data['photo'] !== '') ? $data['photo'] : null,
        ];
        
        // Якщо пароль вказано, оновлюємо його
        if (!empty($data['password']) && $data['password'] !== '') {
            $sql .= ", password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        try {
            SqlSupportServiceTemplate::delete('clients', 'id', $id);
            return true;
        } catch (\Exception $e) {
            error_log("Error deleting client: " . $e->getMessage());
            return false;
        }
    }
}

