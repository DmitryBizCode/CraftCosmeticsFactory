<?php

namespace App\Services;

use PDO;

class RecipeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        new SqlSupportServiceTemplate($pdo); // Ініціалізуємо static PDO
    }

    public function getAll(): array
    {
        return SqlSupportServiceTemplate::getAll('recipes', 'id DESC');
    }

    public function getById(int $id): ?array
    {
        return SqlSupportServiceTemplate::getById('recipes', 'id', $id);
    }

    public function create(array $data): int
    {
        // Перевіряємо, чи є поле photo в даних
        $hasPhoto = isset($data['photo']) && $data['photo'] !== null && $data['photo'] !== '';
        
        if ($hasPhoto) {
            $sql = "INSERT INTO recipes (name, time_to_complete, description, photo) 
                    VALUES (:name, :time_to_complete, :description, :photo)";
            $params = [
                ':name' => $data['name'],
                ':time_to_complete' => (float)$data['time_to_complete'],
                ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
                ':photo' => $data['photo'],
            ];
        } else {
            $sql = "INSERT INTO recipes (name, time_to_complete, description) 
                    VALUES (:name, :time_to_complete, :description)";
            $params = [
                ':name' => $data['name'],
                ':time_to_complete' => (float)$data['time_to_complete'],
                ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
            ];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        // Перевіряємо, чи є поле photo в даних
        $hasPhoto = isset($data['photo']) && $data['photo'] !== null && $data['photo'] !== '';
        
        if ($hasPhoto) {
            $sql = "UPDATE recipes 
                    SET name = :name, time_to_complete = :time_to_complete, 
                        description = :description, photo = :photo 
                    WHERE id = :id";
            $params = [
                ':id' => $id,
                ':name' => $data['name'],
                ':time_to_complete' => (float)$data['time_to_complete'],
                ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
                ':photo' => $data['photo'],
            ];
        } else {
            $sql = "UPDATE recipes 
                    SET name = :name, time_to_complete = :time_to_complete, 
                        description = :description 
                    WHERE id = :id";
            $params = [
                ':id' => $id,
                ':name' => $data['name'],
                ':time_to_complete' => (float)$data['time_to_complete'],
                ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
            ];
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        try {
            SqlSupportServiceTemplate::delete('recipes', 'id', $id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRecipeProducts(int $recipeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT rp.*, p.name as product_name, p.unit 
            FROM recipe_product rp 
            JOIN products p ON rp.product_id = p.id 
            WHERE rp.recipe_id = :recipe_id
        ");
        $stmt->execute([':recipe_id' => $recipeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecipeIotSensors(int $recipeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ie.*, i.name as iot_name 
            FROM iot_eco ie 
            JOIN eco_system es ON ie.eco_system_id = es.id 
            JOIN iot i ON ie.iot_id = i.id 
            WHERE es.recipe_id = :recipe_id
        ");
        $stmt->execute([':recipe_id' => $recipeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addProductToRecipe(int $recipeId, int $productId, int $quantity): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO recipe_product (recipe_id, product_id, quantity) 
            VALUES (:recipe_id, :product_id, :quantity)
        ");
        return $stmt->execute([
            ':recipe_id' => $recipeId,
            ':product_id' => $productId,
            ':quantity' => $quantity,
        ]);
    }

    public function removeProductFromRecipe(int $recipeId, int $productId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM recipe_product 
            WHERE recipe_id = :recipe_id AND product_id = :product_id
        ");
        return $stmt->execute([
            ':recipe_id' => $recipeId,
            ':product_id' => $productId,
        ]);
    }

    public function clearRecipeProducts(int $recipeId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM recipe_product WHERE recipe_id = :recipe_id");
        return $stmt->execute([':recipe_id' => $recipeId]);
    }

    public function getEcoSystemByRecipeId(int $recipeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM eco_system WHERE recipe_id = :recipe_id");
        $stmt->execute([':recipe_id' => $recipeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function createEcoSystem(int $recipeId, ?int $staffId = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO eco_system (recipe_id, staff_id) 
            VALUES (:recipe_id, :staff_id)
        ");
        $stmt->execute([
            ':recipe_id' => $recipeId,
            ':staff_id' => $staffId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function addIotSensorToEcoSystem(int $ecoSystemId, int $iotId, ?float $startValue = null, ?float $endValue = null, ?string $description = null): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO iot_eco (eco_system_id, iot_id, start_value, end_value, description) 
            VALUES (:eco_system_id, :iot_id, :start_value, :end_value, :description)
        ");
        return $stmt->execute([
            ':eco_system_id' => $ecoSystemId,
            ':iot_id' => $iotId,
            ':start_value' => $startValue,
            ':end_value' => $endValue,
            ':description' => $description,
        ]);
    }

    public function clearEcoSystemIotSensors(int $ecoSystemId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM iot_eco WHERE eco_system_id = :eco_system_id");
        return $stmt->execute([':eco_system_id' => $ecoSystemId]);
    }

    public function generateImageHash(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $hash = hash('sha256', $originalName . time() . uniqid());
        return $hash . '.' . $extension;
    }
}

