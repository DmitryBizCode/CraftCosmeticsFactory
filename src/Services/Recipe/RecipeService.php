<?php

namespace App\Services\Recipe;

use PDO;
use App\Services\Database\SqlSupportServiceTemplate;

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
        $hasPrice = isset($data['price']) && $data['price'] !== null && $data['price'] !== '';
        
        // Генеруємо рандомні значення для rating та review_count
        // rating: від 3.0 до 5.0 з одним десятковим знаком
        $rating = round(3.0 + (rand(0, 20) / 10), 1);
        // review_count: від 100 до 12000
        $reviewCount = rand(100, 12000);
        
        $fields = ['name', 'time_to_complete', 'description', 'rating', 'review_count'];
        $values = [':name', ':time_to_complete', ':description', ':rating', ':review_count'];
        $params = [
            ':name' => $data['name'],
            ':time_to_complete' => (float)$data['time_to_complete'],
            ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
            ':rating' => $rating,
            ':review_count' => $reviewCount,
        ];
        
        if ($hasPhoto) {
            $fields[] = 'photo';
            $values[] = ':photo';
            $params[':photo'] = $data['photo'];
        }
        
        if ($hasPrice) {
            $fields[] = 'price';
            $values[] = ':price';
            $params[':price'] = (float)$data['price'];
        } else {
            $fields[] = 'price';
            $values[] = ':price';
            $params[':price'] = 0.00;
        }
        
        $sql = "INSERT INTO recipes (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        // Перевіряємо, чи є поле photo в даних
        $hasPhoto = isset($data['photo']) && $data['photo'] !== null && $data['photo'] !== '';
        $photoIsNull = isset($data['photo']) && $data['photo'] === null;
        $hasPrice = isset($data['price']) && $data['price'] !== null && $data['price'] !== '';
        
        $setParts = [
            'name = :name',
            'time_to_complete = :time_to_complete',
            'description = :description'
        ];
        $params = [
            ':id' => $id,
            ':name' => $data['name'],
            ':time_to_complete' => (float)$data['time_to_complete'],
            ':description' => (!empty($data['description']) && $data['description'] !== '') ? $data['description'] : null,
        ];
        
        if ($hasPhoto) {
            $setParts[] = 'photo = :photo';
            $params[':photo'] = $data['photo'];
        } elseif ($photoIsNull) {
            $setParts[] = 'photo = NULL';
        }
        
        // Price завжди додаємо (навіть якщо не вказано, встановлюємо 0.00)
        if ($hasPrice) {
            $setParts[] = 'price = :price';
            $params[':price'] = (float)$data['price'];
        } else {
            $setParts[] = 'price = :price';
            $params[':price'] = 0.00;
        }
        
        $sql = "UPDATE recipes SET " . implode(', ', $setParts) . " WHERE id = :id";
        
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
            SELECT rp.*, p.name as product_name, p.unit, p.quantity as product_quantity, p.allergen
            FROM recipe_product rp 
            JOIN products p ON rp.product_id = p.id 
            WHERE rp.recipe_id = :recipe_id
        ");
        $stmt->execute([':recipe_id' => $recipeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Отримати всі рецепти для сторінки продажів з усіма деталями
     */
    public function getAllForSalesPage(?string $searchQuery = null): array
    {
        // Якщо є пошуковий запит, фільтруємо рецепти
        if (!empty($searchQuery)) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM recipes 
                WHERE name LIKE :search 
                ORDER BY id DESC
            ");
            $searchPattern = '%' . $searchQuery . '%';
            $stmt->execute([':search' => $searchPattern]);
            $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $recipes = $this->getAll();
        }
        
        foreach ($recipes as &$recipe) {
            // Встановлюємо дефолтне фото, якщо немає
            if (empty($recipe['photo']) || $recipe['photo'] === null) {
                $recipe['photo'] = 'default.png';
            } else {
                // Перевіряємо, чи існує файл фото
                $photoPath = __DIR__ . '/../../../image/recipes/' . $recipe['photo'];
                if (!file_exists($photoPath)) {
                    $recipe['photo'] = 'default.png';
                }
            }
            
            // Встановлюємо дефолтні значення для rating та review_count, якщо їх немає
            if (!isset($recipe['rating']) || $recipe['rating'] === null) {
                $recipe['rating'] = 5.0;
            }
            if (!isset($recipe['review_count']) || $recipe['review_count'] === null) {
                $recipe['review_count'] = 0;
            }
            
            // Отримуємо продукти з алергенами
            $recipe['products'] = $this->getRecipeProducts($recipe['id']);
            
            // Визначаємо, чи є алергени в рецепті
            $recipe['has_allergens'] = false;
            foreach ($recipe['products'] as $product) {
                if (!empty($product['allergen']) && (bool)$product['allergen']) {
                    $recipe['has_allergens'] = true;
                    break;
                }
            }
        }
        
        return $recipes;
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

    /**
     * Перевірити, чи активна eco_system
     */
    public function isEcoSystemActive(int $ecoSystemId): bool
    {
        $stmt = $this->pdo->prepare("SELECT is_active FROM eco_system WHERE id = :id");
        $stmt->execute([':id' => $ecoSystemId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && (bool)$result['is_active'];
    }

    /**
     * Отримати eco_system по ID
     */
    public function getEcoSystemByEcoSystemId(int $ecoSystemId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM eco_system WHERE id = :id");
        $stmt->execute([':id' => $ecoSystemId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function createEcoSystem(int $recipeId, ?int $staffId = null, ?string $description = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO eco_system (recipe_id, staff_id, description) 
            VALUES (:recipe_id, :staff_id, :description)
        ");
        $stmt->execute([
            ':recipe_id' => $recipeId,
            ':staff_id' => $staffId,
            ':description' => $description,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateEcoSystemStatus(int $ecoSystemId, bool $isActive): bool
    {
        if ($isActive) {
            // При запуску: встановлюємо date_start, очищаємо date_end
            $stmt = $this->pdo->prepare("
                UPDATE eco_system 
                SET is_active = 1, 
                    date_start = NOW(6),
                    date_end = NULL
                WHERE id = :id
            ");
        } else {
            // При зупинці: встановлюємо date_end
            $stmt = $this->pdo->prepare("
                UPDATE eco_system 
                SET is_active = 0, 
                    date_end = NOW(6)
                WHERE id = :id
            ");
        }
        
        return $stmt->execute([
            ':id' => $ecoSystemId,
        ]);
    }

    public function updateEcoSystemDescription(int $ecoSystemId, ?string $description): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE eco_system 
            SET description = :description 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $ecoSystemId,
            ':description' => $description,
        ]);
    }

    public function updateEcoSystemStaff(int $ecoSystemId, ?int $staffId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE eco_system 
            SET staff_id = :staff_id 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $ecoSystemId,
            ':staff_id' => $staffId,
        ]);
    }

    public function addIotSensorToEcoSystem(int $ecoSystemId, int $iotId, ?float $startValue = null, ?float $endValue = null): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO iot_eco (eco_system_id, iot_id, start_value, end_value) 
            VALUES (:eco_system_id, :iot_id, :start_value, :end_value)
        ");
        return $stmt->execute([
            ':eco_system_id' => $ecoSystemId,
            ':iot_id' => $iotId,
            ':start_value' => $startValue,
            ':end_value' => $endValue,
        ]);
    }

    public function clearEcoSystemIotSensors(int $ecoSystemId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM iot_eco WHERE eco_system_id = :eco_system_id");
        return $stmt->execute([':eco_system_id' => $ecoSystemId]);
    }

    /**
     * Оновити IoT датчики в eco_system (видалити тільки видалені, додати тільки нові)
     */
    public function updateEcoSystemIotSensors(int $ecoSystemId, array $newIotIds): bool
    {
        try {
            // Отримуємо поточні датчики
            $stmt = $this->pdo->prepare("SELECT iot_id FROM iot_eco WHERE eco_system_id = :eco_system_id");
            $stmt->execute([':eco_system_id' => $ecoSystemId]);
            $currentIotIds = array_map(function($row) {
                return (int)$row['iot_id'];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
            
            $newIotIds = array_map('intval', $newIotIds);
            
            // Знаходимо датчики для видалення (є в поточних, але немає в нових)
            $toDelete = array_diff($currentIotIds, $newIotIds);
            
            // Знаходимо датчики для додавання (є в нових, але немає в поточних)
            $toAdd = array_diff($newIotIds, $currentIotIds);
            
            // Видаляємо тільки ті, які потрібно видалити
            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $stmt = $this->pdo->prepare("DELETE FROM iot_eco WHERE eco_system_id = ? AND iot_id IN ($placeholders)");
                $stmt->execute(array_merge([$ecoSystemId], $toDelete));
            }
            
            // Додаємо тільки нові датчики
            foreach ($toAdd as $iotId) {
                $this->addIotSensorToEcoSystem($ecoSystemId, $iotId);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error updating eco system IoT sensors: " . $e->getMessage());
            return false;
        }
    }

    public function generateImageHash(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $hash = hash('sha256', $originalName . time() . uniqid());
        return $hash . '.' . $extension;
    }
}

