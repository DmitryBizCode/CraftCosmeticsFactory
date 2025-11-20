<?php

namespace App\Services\Product;

use PDO;
use App\Services\Database\SqlSupportServiceTemplate;

class ProductService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        new SqlSupportServiceTemplate($pdo); // Ініціалізуємо static PDO
    }

    public function getAll(): array
    {
        return SqlSupportServiceTemplate::getAll('products', 'id DESC');
    }

    public function getById(int $id): ?array
    {
        return SqlSupportServiceTemplate::getById('products', 'id', $id);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO products (name, quantity, unit, temperature, lightening, description, allergen) 
                VALUES (:name, :quantity, :unit, :temperature, :lightening, :description, :allergen)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':quantity' => $data['quantity'],
            ':unit' => $data['unit'],
            ':temperature' => (!empty($data['temperature']) && $data['temperature'] !== '') ? (float)$data['temperature'] : null,
            ':lightening' => (!empty($data['lightening']) && $data['lightening'] !== '') ? (float)$data['lightening'] : null,
            ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
            ':allergen' => isset($data['allergen']) ? 1 : 0
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE products 
                SET name = :name, quantity = :quantity, unit = :unit, 
                    temperature = :temperature, lightening = :lightening, 
                    description = :description, allergen = :allergen 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':quantity' => $data['quantity'],
            ':unit' => $data['unit'],
            ':temperature' => (!empty($data['temperature']) && $data['temperature'] !== '') ? (float)$data['temperature'] : null,
            ':lightening' => (!empty($data['lightening']) && $data['lightening'] !== '') ? (float)$data['lightening'] : null,
            ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
            ':allergen' => isset($data['allergen']) ? 1 : 0
        ]);
    }

    public function delete(int $id): bool
    {
        try {
            SqlSupportServiceTemplate::delete('products', 'id', $id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

