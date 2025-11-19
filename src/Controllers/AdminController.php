<?php

namespace App\Controllers;

use App\Services\TemplateService;
use App\Services\SQLService;
use App\Services\ProductService;
use App\Services\IotService;
use App\Services\RecipeService;
use App\Services\IotEcoService;
use PDO;

class AdminController
{
    private TemplateService $templateService;
    private ProductService $productService;
    private IotService $iotService;
    private RecipeService $recipeService;
    private IotEcoService $iotEcoService;

    public function __construct()
    {
        $this->templateService = new TemplateService();
        $sqlService = new SQLService();
        $this->productService = new ProductService($sqlService->getPdo());
        $this->iotService = new IotService($sqlService->getPdo());
        $this->recipeService = new RecipeService($sqlService->getPdo());
        $this->iotEcoService = new IotEcoService($sqlService->getPdo());
    }

    /** Сторінка логіну в адмінку */
    public function getAdminLogin(): string
    {
        return $this->templateService->render('pages/adminPage', [
            'homeNavBar' => null,
            'footer' => null,
        ]);
    }

    /** Сторінка управління продуктами (інгредієнти) */
    public function getAdminProducts(): string
    {
        $products = $this->productService->getAll();
        $success = isset($_GET['success']) && !empty($_GET['success']) ? $_GET['success'] : null;
        return $this->templateService->render('pages/adminProducts', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'products',
            'products' => $products,
            'success' => $success,
        ]);
    }

    /** Створення нового продукту */
    public function createProduct(array $data): string
    {
        try {
            $this->productService->create($data);
            header('Location: index.php?action=adminProducts&success=created');
            exit;
        } catch (\Exception $e) {
            $products = $this->productService->getAll();
            return $this->templateService->render('pages/adminProducts', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'products',
                'products' => $products,
                'error' => 'Error creating product: ' . $e->getMessage(),
            ]);
        }
    }

    /** Оновлення продукту */
    public function updateProduct(int $id, array $data): string
    {
        try {
            $this->productService->update($id, $data);
            header('Location: index.php?action=adminProducts&success=updated');
            exit;
        } catch (\Exception $e) {
            $products = $this->productService->getAll();
            return $this->templateService->render('pages/adminProducts', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'products',
                'products' => $products,
                'error' => 'Error updating product: ' . $e->getMessage(),
            ]);
        }
    }

    /** Видалення продукту */
    public function deleteProduct(int $id): string
    {
        try {
            $this->productService->delete($id);
            header('Location: index.php?action=adminProducts&success=deleted');
            exit;
        } catch (\Exception $e) {
            $products = $this->productService->getAll();
            return $this->templateService->render('pages/adminProducts', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'products',
                'products' => $products,
                'error' => 'Error deleting product: ' . $e->getMessage(),
            ]);
        }
    }

    /** Сторінка управління рецептами */
    public function getAdminRecipes(): string
    {
        $recipes = $this->recipeService->getAll();
        $products = $this->productService->getAll();
        $iotSensors = $this->iotService->getAll();
        $success = isset($_GET['success']) && !empty($_GET['success']) ? $_GET['success'] : null;
        
        // Завантажуємо продукти та IoT сенсори для кожного рецепту
        foreach ($recipes as &$recipe) {
            // Перевіряємо наявність фото
            if (empty($recipe['photo']) || $recipe['photo'] === null) {
                $recipe['photo'] = 'default.png';
            } else {
                // Перевіряємо, чи існує файл фото
                $photoPath = __DIR__ . '/../../image/recipes/' . $recipe['photo'];
                if (!file_exists($photoPath)) {
                    // Якщо файл не існує, оновлюємо БД - встановлюємо photo в NULL
                    $this->recipeService->update($recipe['id'], [
                        'name' => $recipe['name'],
                        'time_to_complete' => $recipe['time_to_complete'],
                        'description' => $recipe['description'],
                        'photo' => null, // Видаляємо посилання на неіснуючий файл
                    ]);
                    $recipe['photo'] = 'default.png';
                }
            }
            
            $recipe['products'] = $this->recipeService->getRecipeProducts($recipe['id']);
            $recipe['iot_sensors'] = $this->recipeService->getRecipeIotSensors($recipe['id']);
            
            // Форматуємо продукти для JSON
            $recipe['products_json'] = json_encode(array_map(function($p) {
                return [
                    'id' => $p['product_id'],
                    'name' => $p['product_name'],
                    'quantity' => $p['quantity'],
                    'unit' => $p['unit']
                ];
            }, $recipe['products']));
            
            // Форматуємо IoT сенсори для JSON
            $recipe['iot_sensors_json'] = json_encode(array_map(function($s) {
                return $s['iot_id'];
            }, $recipe['iot_sensors']));
        }
        
        return $this->templateService->render('pages/adminRecipes', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'recipes',
            'recipes' => $recipes,
            'products' => $products,
            'iotSensors' => $iotSensors,
            'success' => $success,
        ]);
    }

    /** Створення нового рецепту */
    public function createRecipe(array $data, array $image): string
    {
        try {
            $photo = null;
            if (isset($image['photo']) && $image['photo']['error'] === UPLOAD_ERR_OK) {
                $photo = $this->uploadRecipePhoto($image['photo']);
            }

            // Конвертуємо час з окремих компонентів (години, хвилини, секунди)
            $hours = (float)($data['time_hours'] ?? 0);
            $minutes = (float)($data['time_minutes'] ?? 0);
            $seconds = (float)($data['time_seconds'] ?? 0);
            // Точний розрахунок з максимальною точністю
            $timeToComplete = round($hours + ($minutes / 60.0) + ($seconds / 3600.0), 4);

            $recipeData = [
                'name' => $data['name'],
                'time_to_complete' => $timeToComplete,
                'description' => $data['description'] ?? null,
                'photo' => $photo,
            ];

            $recipeId = $this->recipeService->create($recipeData);

            // Створюємо eco_system для рецепту
            $ecoSystemId = $this->recipeService->createEcoSystem($recipeId);

            // Додаємо продукти до рецепту
            if (isset($data['products']) && !empty($data['products'])) {
                // Перевіряємо, чи це JSON рядок або масив
                if (is_string($data['products'])) {
                    $products = json_decode($data['products'], true);
                } else {
                    $products = $data['products'];
                }
                
                if (is_array($products)) {
                    foreach ($products as $productData) {
                        // Якщо це рядок JSON, декодуємо його
                        if (is_string($productData)) {
                            $productInfo = json_decode($productData, true);
                        } else {
                            $productInfo = $productData;
                        }
                        
                        if ($productInfo && isset($productInfo['id']) && isset($productInfo['quantity'])) {
                            $this->recipeService->addProductToRecipe(
                                $recipeId,
                                (int)$productInfo['id'],
                                (int)$productInfo['quantity']
                            );
                        }
                    }
                }
            }

            // Додаємо IoT сенсори до eco_system
            if (isset($data['iot_sensors']) && !empty($data['iot_sensors'])) {
                // Перевіряємо, чи це JSON рядок або масив
                if (is_string($data['iot_sensors'])) {
                    $iotSensors = json_decode($data['iot_sensors'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("JSON decode error for iot_sensors: " . json_last_error_msg() . " | Value: " . $data['iot_sensors']);
                    }
                } else {
                    $iotSensors = $data['iot_sensors'];
                }
                
                if (is_array($iotSensors) && !empty($iotSensors)) {
                    foreach ($iotSensors as $iotId) {
                        if (!empty($iotId)) {
                            $this->recipeService->addIotSensorToEcoSystem($ecoSystemId, (int)$iotId);
                        }
                    }
                }
            }

            header('Location: index.php?action=adminRecipes&success=created');
            exit;
        } catch (\Exception $e) {
            $recipes = $this->recipeService->getAll();
            $products = $this->productService->getAll();
            $iotSensors = $this->iotService->getAll();
            return $this->templateService->render('pages/adminRecipes', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'recipes',
                'recipes' => $recipes,
                'products' => $products,
                'iotSensors' => $iotSensors,
                'error' => 'Error creating recipe: ' . $e->getMessage(),
            ]);
        }
    }

    /** Оновлення рецепту */
    public function updateRecipe(int $id, array $data, array $image): string
    {
        try {
            $recipe = $this->recipeService->getById($id);
            if (!$recipe) {
                throw new \Exception('Recipe not found');
            }

            $photo = $recipe['photo'];
            if (isset($image['photo']) && $image['photo']['error'] === UPLOAD_ERR_OK) {
                // Видаляємо старе фото
                if ($photo) {
                    $this->deleteRecipePhoto($photo);
                }
                $photo = $this->uploadRecipePhoto($image['photo']);
            }

            // Конвертуємо час з окремих компонентів (години, хвилини, секунди)
            $hours = (float)($data['time_hours'] ?? 0);
            $minutes = (float)($data['time_minutes'] ?? 0);
            $seconds = (float)($data['time_seconds'] ?? 0);
            // Точний розрахунок з максимальною точністю
            $timeToComplete = round($hours + ($minutes / 60.0) + ($seconds / 3600.0), 4);

            $recipeData = [
                'name' => $data['name'],
                'time_to_complete' => $timeToComplete,
                'description' => $data['description'] ?? null,
                'photo' => $photo,
            ];

            $this->recipeService->update($id, $recipeData);

            // Оновлюємо продукти
            $this->recipeService->clearRecipeProducts($id);
            if (isset($data['products']) && !empty($data['products'])) {
                // Перевіряємо, чи це JSON рядок або масив
                if (is_string($data['products'])) {
                    $products = json_decode($data['products'], true);
                } else {
                    $products = $data['products'];
                }
                
                if (is_array($products)) {
                    foreach ($products as $productData) {
                        // Якщо це рядок JSON, декодуємо його
                        if (is_string($productData)) {
                            $productInfo = json_decode($productData, true);
                        } else {
                            $productInfo = $productData;
                        }
                        
                        if ($productInfo && isset($productInfo['id']) && isset($productInfo['quantity'])) {
                            $this->recipeService->addProductToRecipe(
                                $id,
                                (int)$productInfo['id'],
                                (int)$productInfo['quantity']
                            );
                        }
                    }
                }
            }

            // Оновлюємо IoT сенсори
            $ecoSystem = $this->recipeService->getEcoSystemByRecipeId($id);
            if ($ecoSystem) {
                $this->recipeService->clearEcoSystemIotSensors($ecoSystem['id']);
                if (isset($data['iot_sensors']) && !empty($data['iot_sensors'])) {
                    // Перевіряємо, чи це JSON рядок або масив
                    if (is_string($data['iot_sensors'])) {
                        $iotSensors = json_decode($data['iot_sensors'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            error_log("JSON decode error for iot_sensors: " . json_last_error_msg() . " | Value: " . $data['iot_sensors']);
                        }
                    } else {
                        $iotSensors = $data['iot_sensors'];
                    }
                    
                    if (is_array($iotSensors) && !empty($iotSensors)) {
                        foreach ($iotSensors as $iotId) {
                            if (!empty($iotId)) {
                                $this->recipeService->addIotSensorToEcoSystem($ecoSystem['id'], (int)$iotId);
                            }
                        }
                    }
                }
            }

            header('Location: index.php?action=adminRecipes&success=updated');
            exit;
        } catch (\Exception $e) {
            $recipes = $this->recipeService->getAll();
            $products = $this->productService->getAll();
            $iotSensors = $this->iotService->getAll();
            return $this->templateService->render('pages/adminRecipes', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'recipes',
                'recipes' => $recipes,
                'products' => $products,
                'iotSensors' => $iotSensors,
                'error' => 'Error updating recipe: ' . $e->getMessage(),
            ]);
        }
    }

    /** Видалення рецепту */
    public function deleteRecipe(int $id): string
    {
        try {
            $recipe = $this->recipeService->getById($id);
            if ($recipe && !empty($recipe['photo']) && $recipe['photo'] !== 'default.png') {
                // Видаляємо файл фото з папки
                $this->deleteRecipePhoto($recipe['photo']);
            }
            $this->recipeService->delete($id);
            header('Location: index.php?action=adminRecipes&success=deleted');
            exit;
        } catch (\Exception $e) {
            $recipes = $this->recipeService->getAll();
            $products = $this->productService->getAll();
            $iotSensors = $this->iotService->getAll();
            return $this->templateService->render('pages/adminRecipes', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'recipes',
                'recipes' => $recipes,
                'products' => $products,
                'iotSensors' => $iotSensors,
                'error' => 'Error deleting recipe: ' . $e->getMessage(),
            ]);
        }
    }

    /** Видалення фото рецепту */
    public function deleteRecipePhotoAction(int $id): string
    {
        try {
            $recipe = $this->recipeService->getById($id);
            if ($recipe && !empty($recipe['photo']) && $recipe['photo'] !== 'default.png') {
                // Видаляємо файл з папки
                $this->deleteRecipePhoto($recipe['photo']);
                
                // Оновлюємо БД - встановлюємо photo в NULL
                $this->recipeService->update($id, [
                    'name' => $recipe['name'],
                    'time_to_complete' => $recipe['time_to_complete'],
                    'description' => $recipe['description'],
                    'photo' => null, // Явно встановлюємо null для видалення
                ]);
            }
            header('Location: index.php?action=adminRecipes&success=photo_deleted');
            exit;
        } catch (\Exception $e) {
            header('Location: index.php?action=adminRecipes&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /** Завантаження фото рецепту */
    private function uploadRecipePhoto(array $file): string
    {
        $hashName = $this->recipeService->generateImageHash($file['name']);
        $targetDir = __DIR__ . '/../../image/recipes/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $targetPath = $targetDir . $hashName;
        
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception('Failed to upload image');
        }
        
        return $hashName;
    }

    /** Видалення фото рецепту */
    private function deleteRecipePhoto(string $photoName): void
    {
        $photoPath = __DIR__ . '/../../image/recipes/' . $photoName;
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    /** Конвертація часу в години */
    private function convertTimeToHours(float $value, string $unit): float
    {
        return match($unit) {
            'seconds' => round($value / 3600, 2),
            'minutes' => round($value / 60, 2),
            'hours' => round($value, 2),
            'days' => round($value * 24, 2),
            default => round($value, 2), // За замовчуванням години
        };
    }

    /** Форматування годин в читабельний формат */
    public function formatTime(float $hours): string
    {
        // Конвертуємо в секунди для точного розрахунку
        $totalSeconds = (int)round($hours * 3600);
        
        $days = floor($totalSeconds / 86400);
        $hours_remain = floor(($totalSeconds % 86400) / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        
        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
        }
        if ($hours_remain > 0) {
            $parts[] = $hours_remain . ' hour' . ($hours_remain > 1 ? 's' : '');
        }
        if ($minutes > 0) {
            $parts[] = $minutes . ' min' . ($minutes > 1 ? 's' : '');
        }
        if ($seconds > 0 || count($parts) === 0) {
            $parts[] = $seconds . ' sec';
        }
        
        return implode(' ', $parts);
    }

    /** Сторінка контролю еко-системи рецепту */
    public function getAdminEcoSystem(int $recipeId): string
    {
        $recipe = $this->recipeService->getById($recipeId);
        if (!$recipe) {
            header('Location: index.php?action=adminRecipes&error=' . urlencode('Recipe not found'));
            exit;
        }

        $ecoSystem = $this->recipeService->getEcoSystemByRecipeId($recipeId);
        if (!$ecoSystem) {
            header('Location: index.php?action=adminRecipes&error=' . urlencode('Eco system not found'));
            exit;
        }

        // Отримуємо IoT сенсори для цієї eco_system
        $iotSensors = $this->iotEcoService->getIotSensorsByEcoSystemId($ecoSystem['id']);
        
        // Для кожного сенсора отримуємо останнє значення та історію
        foreach ($iotSensors as &$sensor) {
            $sensor['current_value'] = $this->iotEcoService->getLastValue($sensor['id']);
            $sensor['history'] = $this->iotEcoService->getValuesHistory($sensor['id'], 20);
            
            // Якщо немає значення, встановлюємо початкове
            if ($sensor['current_value'] === null) {
                $startValue = $sensor['start_value'] ?? $sensor['iot_start_value'] ?? 0;
                $endValue = $sensor['end_value'] ?? $sensor['iot_end_value'] ?? 100;
                $initialValue = ($startValue + $endValue) / 2;
                $this->iotEcoService->addValue($sensor['id'], $initialValue);
                $sensor['current_value'] = $initialValue;
            }
        }

        // Форматуємо час виготовлення рецепту
        $timeToComplete = null;
        if (isset($recipe['time_to_complete']) && $recipe['time_to_complete'] !== null) {
            $timeToComplete = $this->formatTime((float)$recipe['time_to_complete']);
        }

        // Отримуємо продукти рецепту
        $recipeProducts = $this->recipeService->getRecipeProducts($recipeId);

        // Отримуємо список staff для вибору
        $sqlService = new SQLService();
        $pdo = $sqlService->getPdo();
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM staff ORDER BY first_name, last_name");
        $stmt->execute();
        $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->templateService->render('pages/adminEcoSystem', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'recipes',
            'recipeId' => $recipeId,
            'recipe' => $recipe,
            'ecoSystem' => $ecoSystem,
            'iotSensors' => $iotSensors,
            'timeToComplete' => $timeToComplete,
            'recipeProducts' => $recipeProducts,
            'staffMembers' => $staffMembers,
        ]);
    }

    /** API: Генерація нового значення для датчика */
    public function generateIotValue(int $iotEcoId): string
    {
        header('Content-Type: application/json');
        
        try {
            // Отримуємо інформацію про датчик
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $stmt = $pdo->prepare("
                SELECT ie.*, i.start_value as iot_start_value, i.end_value as iot_end_value, ie.eco_system_id
                FROM iot_eco ie 
                JOIN iot i ON ie.iot_id = i.id 
                WHERE ie.id = :id
            ");
            $stmt->execute([':id' => $iotEcoId]);
            $sensor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sensor) {
                echo json_encode(['error' => 'Sensor not found']);
                return '';
            }

            // Перевіряємо, чи активна eco_system
            $stmt = $pdo->prepare("SELECT is_active FROM eco_system WHERE id = :id");
            $stmt->execute([':id' => $sensor['eco_system_id']]);
            $ecoSystem = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ecoSystem || !$ecoSystem['is_active']) {
                echo json_encode(['error' => 'Eco system is not active']);
                return '';
            }

            $startValue = $sensor['start_value'] ?? $sensor['iot_start_value'] ?? 0;
            $endValue = $sensor['end_value'] ?? $sensor['iot_end_value'] ?? 100;
            $currentValue = $this->iotEcoService->getLastValue($iotEcoId);
            
            // Отримуємо історію даних
            $history = $this->iotEcoService->getValuesHistory($iotEcoId, 10);
            $historyValues = array_column($history, 'value');
            
            // Отримуємо дані про інші датчики в цій eco_system
            $allSensors = $this->iotEcoService->getIotSensorsByEcoSystemId($sensor['eco_system_id']);
            $otherSensors = [];
            $previousValues = $this->iotEcoService->getLastValuesForEcoSystem($sensor['eco_system_id']);
            
            foreach ($allSensors as $otherSensor) {
                if ($otherSensor['id'] != $iotEcoId) {
                    $otherCurrentValue = $previousValues[$otherSensor['id']] ?? null;
                    if ($otherCurrentValue !== null) {
                        // Отримуємо попереднє значення для розрахунку зміни
                        $otherHistory = $this->iotEcoService->getValuesHistory($otherSensor['id'], 2);
                        $otherPreviousValue = count($otherHistory) > 1 ? $otherHistory[0]['value'] : $otherCurrentValue;
                        $otherChange = $otherCurrentValue - $otherPreviousValue;
                        
                        $otherStartValue = $otherSensor['start_value'] ?? $otherSensor['iot_start_value'] ?? 0;
                        $otherEndValue = $otherSensor['end_value'] ?? $otherSensor['iot_end_value'] ?? 100;
                        $otherRange = $otherEndValue - $otherStartValue;
                        
                        $otherSensors[] = [
                            'id' => $otherSensor['id'],
                            'value' => $otherCurrentValue,
                            'change' => $otherChange,
                            'range' => $otherRange
                        ];
                    }
                }
            }

            // Використовуємо новий генератор з історією та залежностями
            $generator = new \App\Services\IotDataGenerator();
            $result = $generator->generateNextValue(
                $currentValue,
                $startValue,
                $endValue,
                $iotEcoId,
                time(),
                $historyValues,
                $otherSensors
            );
            
            // Зберігаємо нове значення
            $this->iotEcoService->addValue($iotEcoId, $result['value']);

            echo json_encode([
                'success' => true,
                'value' => $result['value'],
                'is_critical' => $result['is_critical'],
                'warning_type' => $result['warning_type'],
                'distance_to_min' => $result['distance_to_min'],
                'distance_to_max' => $result['distance_to_max'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        return '';
    }

    /** API: Встановлення значення вручну */
    public function setIotValue(int $iotEcoId, float $value): string
    {
        header('Content-Type: application/json');
        
        try {
            // Отримуємо інформацію про датчик
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $stmt = $pdo->prepare("
                SELECT ie.*, i.start_value as iot_start_value, i.end_value as iot_end_value, ie.eco_system_id
                FROM iot_eco ie 
                JOIN iot i ON ie.iot_id = i.id 
                WHERE ie.id = :id
            ");
            $stmt->execute([':id' => $iotEcoId]);
            $sensor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sensor) {
                echo json_encode(['error' => 'Sensor not found']);
                return '';
            }

            $startValue = $sensor['start_value'] ?? $sensor['iot_start_value'] ?? 0;
            $endValue = $sensor['end_value'] ?? $sensor['iot_end_value'] ?? 100;
            $range = $endValue - $startValue;
            
            // Отримуємо попереднє значення для розрахунку зміни
            $previousValue = $this->iotEcoService->getLastValue($iotEcoId);
            if ($previousValue === null) {
                $previousValue = ($startValue + $endValue) / 2;
            }
            
            // Розраховуємо зміну в процентах від range
            $change = $value - $previousValue;
            $changePercent = ($change / $range) * 100; // Зміна в % від range
            
            // Зберігаємо нове значення
            $this->iotEcoService->setValue($iotEcoId, $value);
            
            // Отримуємо всі інші датчики в eco_system
            $allSensors = $this->iotEcoService->getIotSensorsByEcoSystemId($sensor['eco_system_id']);
            $updatedSensors = [];
            
            // Змінюємо всі інші датчики на 10-15% від зміни цього датчика
            foreach ($allSensors as $otherSensor) {
                if ($otherSensor['id'] != $iotEcoId) {
                    $otherCurrentValue = $this->iotEcoService->getLastValue($otherSensor['id']);
                    if ($otherCurrentValue !== null) {
                        $otherStartValue = $otherSensor['start_value'] ?? $otherSensor['iot_start_value'] ?? 0;
                        $otherEndValue = $otherSensor['end_value'] ?? $otherSensor['iot_end_value'] ?? 100;
                        $otherRange = $otherEndValue - $otherStartValue;
                        
                        // 10-15% від зміни (випадково вибираємо напрямок)
                        $influencePercent = mt_rand(100, 150) / 1000; // 10-15%
                        $influence = ($changePercent * $influencePercent / 100) * $otherRange;
                        
                        // Випадково вверх або вниз
                        $direction = mt_rand(0, 1) ? 1 : -1;
                        $newOtherValue = $otherCurrentValue + ($influence * $direction);
                        
                        // Обмежуємо в межах range
                        $newOtherValue = max($otherStartValue, min($otherEndValue, $newOtherValue));
                        
                        // Зберігаємо нове значення
                        $this->iotEcoService->setValue($otherSensor['id'], $newOtherValue);
                        
                        $updatedSensors[] = [
                            'id' => $otherSensor['id'],
                            'old_value' => $otherCurrentValue,
                            'new_value' => $newOtherValue,
                            'change' => $newOtherValue - $otherCurrentValue
                        ];
                    }
                }
            }
            
            // Перевірка на критичні значення
            $criticalThreshold = $range * 0.05;
            $distanceToMin = $value - $startValue;
            $distanceToMax = $endValue - $value;
            $isCritical = false;
            $warningType = null;
            
            if ($distanceToMin <= $criticalThreshold) {
                $isCritical = true;
                $warningType = 'min';
            } elseif ($distanceToMax <= $criticalThreshold) {
                $isCritical = true;
                $warningType = 'max';
            }
            
            echo json_encode([
                'success' => true,
                'value' => $value,
                'change_percent' => round($changePercent, 2),
                'is_critical' => $isCritical,
                'warning_type' => $warningType,
                'updated_sensors' => $updatedSensors,
                'distance_to_min' => round($distanceToMin, 2),
                'distance_to_max' => round($distanceToMax, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        return '';
    }

    /** API: Отримання даних для графіків */
    public function getIotData(int $iotEcoId): string
    {
        header('Content-Type: application/json');
        
        try {
            // Отримуємо інформацію про датчик для перевірки критичних значень
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $stmt = $pdo->prepare("
                SELECT ie.*, i.start_value as iot_start_value, i.end_value as iot_end_value
                FROM iot_eco ie 
                JOIN iot i ON ie.iot_id = i.id 
                WHERE ie.id = :id
            ");
            $stmt->execute([':id' => $iotEcoId]);
            $sensor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $history = $this->iotEcoService->getValuesHistory($iotEcoId, 30);
            $currentValue = $this->iotEcoService->getLastValue($iotEcoId);
            
            // Форматуємо дані для графіка
            $labels = [];
            $values = [];
            
            foreach ($history as $record) {
                $labels[] = date('H:i:s', strtotime($record['created_at']));
                $values[] = (float)$record['value'];
            }
            
            // Перевірка на критичні значення
            $isCritical = false;
            $warningType = null;
            if ($sensor && $currentValue !== null) {
                $startValue = $sensor['start_value'] ?? $sensor['iot_start_value'] ?? 0;
                $endValue = $sensor['end_value'] ?? $sensor['iot_end_value'] ?? 100;
                $range = $endValue - $startValue;
                $criticalThreshold = $range * 0.05;
                $distanceToMin = $currentValue - $startValue;
                $distanceToMax = $endValue - $currentValue;
                
                if ($distanceToMin <= $criticalThreshold) {
                    $isCritical = true;
                    $warningType = 'min';
                } elseif ($distanceToMax <= $criticalThreshold) {
                    $isCritical = true;
                    $warningType = 'max';
                }
            }
            
            echo json_encode([
                'success' => true,
                'labels' => $labels,
                'values' => $values,
                'current_value' => $currentValue,
                'is_critical' => $isCritical,
                'warning_type' => $warningType
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        return '';
    }

    /** API: Запуск/зупинка eco_system */
    public function toggleEcoSystem(int $ecoSystemId, bool $isActive): string
    {
        header('Content-Type: application/json');
        
        try {
            $this->recipeService->updateEcoSystemStatus($ecoSystemId, $isActive);
            
            // Отримуємо оновлені дані eco_system
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM eco_system WHERE id = :id");
            $stmt->execute([':id' => $ecoSystemId]);
            $ecoSystem = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'is_active' => $isActive,
                'date_start' => $ecoSystem['date_start'] ?? null,
                'date_end' => $ecoSystem['date_end'] ?? null,
                'message' => $isActive ? 'Eco system started' : 'Eco system stopped'
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        return '';
    }

    /** API: Оновлення опису eco_system */
    public function updateEcoSystemDescription(int $ecoSystemId, string $description): string
    {
        header('Content-Type: application/json');
        
        try {
            $this->recipeService->updateEcoSystemDescription($ecoSystemId, $description);
            echo json_encode([
                'success' => true,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        return '';
    }

    public function updateEcoSystemStaff(int $ecoSystemId, ?int $staffId): string
    {
        header('Content-Type: application/json');
        
        try {
            $this->recipeService->updateEcoSystemStaff($ecoSystemId, $staffId);
            echo json_encode([
                'success' => true,
                'staff_id' => $staffId
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        return '';
    }

    /** Сторінка статистики IoT датчиків */
    public function getAdminIotStats(int $recipeId): string
    {
        return $this->templateService->render('pages/adminIotStats', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'recipes',
            'recipeId' => $recipeId,
        ]);
    }

    /** Сторінка всіх замовлень */
    public function getAdminOrders(): string
    {
        return $this->templateService->render('pages/adminOrders', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'orders',
        ]);
    }

    /** Сторінка статистики продажів / транзакцій */
    public function getAdminStats(): string
    {
        return $this->templateService->render('pages/adminStats', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'stats',
        ]);
    }

    /** Сторінка користувачів (клієнти та співробітники) */
    public function getAdminUsers(): string
    {
        return $this->templateService->render('pages/adminUsers', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'users',
        ]);
    }

    /** Сторінка управління IoT / Eco system */
    public function getAdminIot(): string
    {
        $iotSensors = $this->iotService->getAll();
        
        // Додаємо кількість рецептів для кожного датчика
        foreach ($iotSensors as &$sensor) {
            $sensor['recipe_count'] = $this->iotService->getRecipeCountForIot($sensor['id']);
        }
        
        $ecoSystems = $this->iotService->getEcoSystems();
        $success = isset($_GET['success']) && !empty($_GET['success']) ? $_GET['success'] : null;
        return $this->templateService->render('pages/adminIot', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'iot',
            'iotSensors' => $iotSensors,
            'ecoSystems' => $ecoSystems,
            'success' => $success,
        ]);
    }

    /** Створення нового IoT датчика */
    public function createIotSensor(array $data): string
    {
        try {
            $this->iotService->create($data);
            header('Location: index.php?action=adminIot&success=created');
            exit;
        } catch (\Exception $e) {
            $iotSensors = $this->iotService->getAll();
            $ecoSystems = $this->iotService->getEcoSystems();
            return $this->templateService->render('pages/adminIot', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'iot',
                'iotSensors' => $iotSensors,
                'ecoSystems' => $ecoSystems,
                'error' => 'Error creating IoT sensor: ' . $e->getMessage(),
            ]);
        }
    }

    /** Оновлення IoT датчика */
    public function updateIotSensor(int $id, array $data): string
    {
        try {
            $this->iotService->update($id, $data);
            header('Location: index.php?action=adminIot&success=updated');
            exit;
        } catch (\Exception $e) {
            $iotSensors = $this->iotService->getAll();
            $ecoSystems = $this->iotService->getEcoSystems();
            return $this->templateService->render('pages/adminIot', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'iot',
                'iotSensors' => $iotSensors,
                'ecoSystems' => $ecoSystems,
                'error' => 'Error updating IoT sensor: ' . $e->getMessage(),
            ]);
        }
    }

    /** Видалення IoT датчика */
    public function deleteIotSensor(int $id): string
    {
        try {
            $this->iotService->delete($id);
            header('Location: index.php?action=adminIot&success=deleted');
            exit;
        } catch (\Exception $e) {
            $iotSensors = $this->iotService->getAll();
            $ecoSystems = $this->iotService->getEcoSystems();
            return $this->templateService->render('pages/adminIot', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'iot',
                'iotSensors' => $iotSensors,
                'ecoSystems' => $ecoSystems,
                'error' => 'Error deleting IoT sensor: ' . $e->getMessage(),
            ]);
        }
    }
}


