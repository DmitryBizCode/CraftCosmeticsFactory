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
                    address = :address, phone_number = :phone_number";
        
        $params = [
            ':id' => $id,
            ':first_name' => $data['first_name'],
            ':last_name' => (!empty($data['last_name']) && $data['last_name'] !== '') ? $data['last_name'] : null,
            ':email' => $data['email'],
            ':address' => (!empty($data['address']) && $data['address'] !== '') ? $data['address'] : null,
            ':phone_number' => (!empty($data['phone_number']) && $data['phone_number'] !== '') ? $data['phone_number'] : null,
        ];
        
        // Додаємо фото, якщо воно вказано в даних (навіть якщо null - для видалення)
        if (array_key_exists('photo', $data)) {
            $sql .= ", photo = :photo";
            if ($data['photo'] === null || $data['photo'] === '') {
                $params[':photo'] = null;
                error_log("ClientService::update - Photo will be set to NULL (removing photo)");
            } else {
                $params[':photo'] = $data['photo'];
                error_log("ClientService::update - Photo will be set to: " . $data['photo']);
            }
        } else {
            error_log("ClientService::update - Photo NOT in data array (array_key_exists returned false)");
        }
        
        // Якщо пароль вказано, оновлюємо його
        if (!empty($data['password']) && $data['password'] !== '') {
            $sql .= ", password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        error_log("ClientService::update - SQL: " . $sql);
        error_log("ClientService::update - Params: " . json_encode($params));
        
        try {
            $result = $stmt->execute($params);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("ERROR updating client: " . implode(", ", $errorInfo));
                return false;
            } else {
                $rowsAffected = $stmt->rowCount();
                error_log("ClientService::update - Success. Rows affected: " . $rowsAffected);
                
                // Якщо жоден рядок не оновлено, це може бути проблемою
                if ($rowsAffected === 0) {
                    error_log("WARNING: No rows were updated! User ID might not exist or data is the same.");
                }
                
                return true;
            }
        } catch (\PDOException $e) {
            error_log("PDO Exception in ClientService::update: " . $e->getMessage());
            return false;
        }
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

    /**
     * Отримати користувача по email
     */
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Перевірити пароль користувача
     */
    public function verifyPassword(string $email, string $password): ?array
    {
        $user = $this->getByEmail($email);
        if (!$user) {
            return null;
        }

        // Перевіряємо пароль
        if (password_verify($password, $user['password'])) {
            // Видаляємо пароль з результату перед поверненням
            unset($user['password']);
            return $user;
        }

        return null;
    }
}

