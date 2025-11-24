<?php

namespace App\Services\Admin;

use App\Services\Product\ProductService;
use App\Services\Recipe\RecipeService;
use App\Services\Iot\IotService;
use App\Services\Iot\IotEcoService;
use App\Services\Iot\IotDataGenerator;
use App\Services\User\ClientService;
use App\Services\User\StaffService;
use App\Services\Order\OrderService;
use App\Services\Database\SQLService;
use Exception;
use PDO;

/**
 * Сервіс для обробки всіх бізнес-операцій адмін-панелі
 */
class AdminActionService
{
    private ProductService $productService;
    private IotService $iotService;
    private RecipeService $recipeService;
    private IotEcoService $iotEcoService;
    private ClientService $clientService;
    private StaffService $staffService;
    private OrderService $orderService;

    public function __construct()
    {
        $sqlService = new SQLService();
        $pdo = $sqlService->getPdo();
        $this->productService = new ProductService($pdo);
        $this->iotService = new IotService($pdo);
        $this->recipeService = new RecipeService($pdo);
        $this->iotEcoService = new IotEcoService($pdo);
        $this->clientService = new ClientService($pdo);
        $this->staffService = new StaffService($pdo);
        $this->orderService = new OrderService($pdo);
    }

    // ============ Products ============
    
    public function getAllProducts(): array
    {
        return $this->productService->getAll();
    }

    public function createProduct(array $data): void
    {
        $this->productService->create($data);
    }

    public function updateProduct(int $id, array $data): void
    {
        $this->productService->update($id, $data);
    }

    public function deleteProduct(int $id): void
    {
        $this->productService->delete($id);
    }

    // ============ Recipes ============
    
    /**
     * Отримати всі рецепти з обробкою фото та додатковими даними
     */
    public function getAllRecipesWithDetails(): array
    {
        $recipes = $this->recipeService->getAll();
        $products = $this->productService->getAll();
        
        // Завантажуємо продукти та IoT сенсори для кожного рецепту
        foreach ($recipes as &$recipe) {
            // Перевіряємо наявність фото
            if (empty($recipe['photo']) || $recipe['photo'] === null) {
                $recipe['photo'] = 'default.png';
            } else {
                // Перевіряємо, чи існує файл фото
                $photoPath = __DIR__ . '/../../../image/recipes/' . $recipe['photo'];
                if (!file_exists($photoPath)) {
                    // Якщо файл не існує, оновлюємо БД - встановлюємо photo в NULL
                    $this->recipeService->update($recipe['id'], [
                        'name' => $recipe['name'],
                        'time_to_complete' => $recipe['time_to_complete'],
                        'description' => $recipe['description'],
                        'photo' => null,
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
        
        return [
            'recipes' => $recipes,
            'products' => $products,
            'iotSensors' => $this->iotService->getAll(),
        ];
    }

    public function getAllRecipes(): array
    {
        return $this->recipeService->getAll();
    }

    /**
     * Створити рецепт з обробкою фото та додатків
     */
    public function createRecipeWithDetails(array $data, array $image = []): void
    {
        $photo = null;
        if (isset($image['photo']) && $image['photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $this->uploadRecipePhoto($image['photo']);
        }

        // Конвертуємо час з окремих компонентів (години, хвилини, секунди)
        $hours = (float)($data['time_hours'] ?? 0);
        $minutes = (float)($data['time_minutes'] ?? 0);
        $seconds = (float)($data['time_seconds'] ?? 0);
        $timeToComplete = round($hours + ($minutes / 60.0) + ($seconds / 3600.0), 4);

        $recipeData = [
            'name' => $data['name'],
            'time_to_complete' => $timeToComplete,
            'description' => $data['description'] ?? null,
            'price' => isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : 0.00,
            'photo' => $photo,
        ];

        $recipeId = $this->recipeService->create($recipeData);

        // Створюємо eco_system для рецепту
        $ecoSystemId = $this->recipeService->createEcoSystem($recipeId);

        // Додаємо продукти до рецепту
        if (isset($data['products']) && !empty($data['products'])) {
            $products = is_string($data['products']) ? json_decode($data['products'], true) : $data['products'];
            
            if (is_array($products)) {
                foreach ($products as $productData) {
                    $productInfo = is_string($productData) ? json_decode($productData, true) : $productData;
                    
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
            $iotSensors = is_string($data['iot_sensors']) ? json_decode($data['iot_sensors'], true) : $data['iot_sensors'];
            
            error_log("DEBUG: iot_sensors raw: " . print_r($data['iot_sensors'], true));
            error_log("DEBUG: iot_sensors parsed: " . print_r($iotSensors, true));
            error_log("DEBUG: ecoSystemId: " . $ecoSystemId);
            
            if (is_array($iotSensors) && !empty($iotSensors)) {
                foreach ($iotSensors as $iotId) {
                    if (!empty($iotId)) {
                        $result = $this->recipeService->addIotSensorToEcoSystem($ecoSystemId, (int)$iotId);
                        error_log("DEBUG: Added IoT sensor " . (int)$iotId . " to eco system " . $ecoSystemId . " - result: " . ($result ? 'success' : 'failed'));
                    }
                }
            } else {
                error_log("DEBUG: iot_sensors is not array or empty");
            }
        } else {
            error_log("DEBUG: iot_sensors not set or empty in data");
        }
    }

    /**
     * Оновити рецепт з обробкою фото та додатків
     */
    public function updateRecipeWithDetails(int $id, array $data, array $image = []): void
    {
        $recipe = $this->recipeService->getById($id);
        if (!$recipe) {
            throw new Exception('Recipe not found');
        }

        $photo = $recipe['photo'];
        if (isset($image['photo']) && $image['photo']['error'] === UPLOAD_ERR_OK) {
            // Видаляємо старе фото
            if ($photo) {
                $this->deleteRecipePhotoFile($photo);
            }
            $photo = $this->uploadRecipePhoto($image['photo']);
        }

        // Конвертуємо час з окремих компонентів
        $hours = (float)($data['time_hours'] ?? 0);
        $minutes = (float)($data['time_minutes'] ?? 0);
        $seconds = (float)($data['time_seconds'] ?? 0);
        $timeToComplete = round($hours + ($minutes / 60.0) + ($seconds / 3600.0), 4);

        $recipeData = [
            'name' => $data['name'],
            'time_to_complete' => $timeToComplete,
            'description' => $data['description'] ?? null,
            'price' => isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : 0.00,
            'photo' => $photo,
        ];

        $this->recipeService->update($id, $recipeData);

        // Оновлюємо продукти
        $this->recipeService->clearRecipeProducts($id);
        if (isset($data['products']) && !empty($data['products'])) {
            $products = is_string($data['products']) ? json_decode($data['products'], true) : $data['products'];
            
            if (is_array($products)) {
                foreach ($products as $productData) {
                    $productInfo = is_string($productData) ? json_decode($productData, true) : $productData;
                    
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
            $newIotIds = [];
            if (isset($data['iot_sensors']) && !empty($data['iot_sensors'])) {
                $iotSensors = is_string($data['iot_sensors']) ? json_decode($data['iot_sensors'], true) : $data['iot_sensors'];
                
                if (is_array($iotSensors) && !empty($iotSensors)) {
                    foreach ($iotSensors as $iotId) {
                        if (!empty($iotId)) {
                            $newIotIds[] = (int)$iotId;
                        }
                    }
                }
            }
            
            $this->recipeService->updateEcoSystemIotSensors($ecoSystem['id'], $newIotIds);
        }
    }

    public function deleteRecipe(int $id): void
    {
        $recipe = $this->recipeService->getById($id);
        if ($recipe && !empty($recipe['photo']) && $recipe['photo'] !== 'default.png') {
            $this->deleteRecipePhotoFile($recipe['photo']);
        }
        $this->recipeService->delete($id);
    }

    public function deleteRecipePhoto(int $id): void
    {
        $recipe = $this->recipeService->getById($id);
        if ($recipe && !empty($recipe['photo']) && $recipe['photo'] !== 'default.png') {
            $this->deleteRecipePhotoFile($recipe['photo']);
            $this->recipeService->update($id, [
                'name' => $recipe['name'],
                'time_to_complete' => $recipe['time_to_complete'],
                'description' => $recipe['description'],
                'photo' => null,
            ]);
        }
    }

    /**
     * Завантажити фото рецепту
     */
    private function uploadRecipePhoto(array $file): string
    {
        $hashName = $this->recipeService->generateImageHash($file['name']);
        $targetDir = __DIR__ . '/../../../image/recipes/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $targetPath = $targetDir . $hashName;
        
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to upload image');
        }
        
        return $hashName;
    }

    /**
     * Видалити файл фото рецепту
     */
    private function deleteRecipePhotoFile(string $photoName): void
    {
        $photoPath = __DIR__ . '/../../../image/recipes/' . $photoName;
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    /**
     * Форматувати час в читабельний формат
     */
    public function formatTime(float $hours): string
    {
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

    // ============ IoT ============
    
    public function getAllIotSensors(): array
    {
        $sensors = $this->iotService->getAll();
        foreach ($sensors as &$sensor) {
            $sensor['recipe_count'] = $this->iotService->getRecipeCountForIot($sensor['id']);
        }
        return $sensors;
    }

    /**
     * Отримати дані для сторінки IoT управління
     */
    public function getIotManagementPageData(): array
    {
        $iotSensors = $this->getAllIotSensors();
        $ecoSystems = $this->iotService->getEcoSystems();
        
        return [
            'iotSensors' => $iotSensors,
            'ecoSystems' => $ecoSystems,
        ];
    }

    public function createIotSensor(array $data): void
    {
        $this->iotService->create($data);
    }

    public function updateIotSensor(int $id, array $data): void
    {
        $this->iotService->update($id, $data);
    }

    public function deleteIotSensor(int $id): void
    {
        $this->iotService->delete($id);
    }

    // ============ IoT Eco System ============
    
    /**
     * Отримати дані для сторінки контролю eco system
     */
    public function getEcoSystemPageData(int $recipeId): array
    {
        $recipe = $this->recipeService->getById($recipeId);
        if (!$recipe) {
            throw new Exception('Recipe not found');
        }

        $ecoSystem = $this->recipeService->getEcoSystemByRecipeId($recipeId);
        if (!$ecoSystem) {
            throw new Exception('Eco system not found');
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
        $staffMembers = $this->staffService->getAllForSelect();

        return [
            'recipe' => $recipe,
            'ecoSystem' => $ecoSystem,
            'iotSensors' => $iotSensors,
            'timeToComplete' => $timeToComplete,
            'recipeProducts' => $recipeProducts,
            'staffMembers' => $staffMembers,
        ];
    }

    public function getEcoSystemForRecipe(int $recipeId): array
    {
        return $this->iotEcoService->getEcoSystemForRecipe($recipeId);
    }

    /**
     * Генерація нового значення для датчика
     */
    public function generateIotValue(int $iotEcoId): array
    {
        // Отримуємо інформацію про датчик
        $sensor = $this->iotEcoService->getSensorInfo($iotEcoId);
        
        if (!$sensor) {
            throw new Exception('Sensor not found');
        }

        // Перевіряємо, чи активна eco_system
        if (!$this->recipeService->isEcoSystemActive($sensor['eco_system_id'])) {
            throw new Exception('Eco system is not active');
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

        // Використовуємо генератор з історією та залежностями
        $generator = new IotDataGenerator();
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

        // Якщо значення критичне, збільшуємо лічильник
        if ($result['is_critical']) {
            $this->iotEcoService->incrementCriticalWarnings($iotEcoId);
        }

        return [
            'success' => true,
            'value' => $result['value'],
            'is_critical' => $result['is_critical'],
            'warning_type' => $result['warning_type'],
            'distance_to_min' => $result['distance_to_min'],
            'distance_to_max' => $result['distance_to_max'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public function setIotValue(int $iotEcoId, float $value): array
    {
        // Отримуємо інформацію про датчик
        $sensor = $this->iotEcoService->getSensorInfo($iotEcoId);
        
        if (!$sensor) {
            throw new Exception('Sensor not found');
        }

        $startValue = $sensor['start_value'] ?? $sensor['iot_start_value'] ?? 0;
        $endValue = $sensor['end_value'] ?? $sensor['iot_end_value'] ?? 100;
        $range = $endValue - $startValue;
        
        // Захист від ділення на 0
        if ($range <= 0) {
            $range = 1; // Якщо range = 0, встановлюємо 1 для уникнення ділення на 0
        }
        
        // Отримуємо попереднє значення для розрахунку зміни
        $previousValue = $this->iotEcoService->getLastValue($iotEcoId);
        if ($previousValue === null) {
            $previousValue = ($startValue + $endValue) / 2;
        }
        
        // Розраховуємо зміну в процентах від range
        $change = $value - $previousValue;
        $changePercent = ($change / $range) * 100;
        
        // Зберігаємо нове значення
        $setResult = $this->iotEcoService->setValue($iotEcoId, $value);
        if (!$setResult) {
            throw new Exception('Failed to save value to database');
        }
        
        // Отримуємо всі інші датчики в eco_system
        $allSensors = $this->iotEcoService->getIotSensorsByEcoSystemId($sensor['eco_system_id']);
        $updatedSensors = [];
        
        // Змінюємо всі інші датчики на 25% від зміни цього датчика
        foreach ($allSensors as $otherSensor) {
            if ($otherSensor['id'] != $iotEcoId) {
                $otherCurrentValue = $this->iotEcoService->getLastValue($otherSensor['id']);
                if ($otherCurrentValue !== null) {
                    $otherStartValue = $otherSensor['start_value'] ?? $otherSensor['iot_start_value'] ?? 0;
                    $otherEndValue = $otherSensor['end_value'] ?? $otherSensor['iot_end_value'] ?? 100;
                    $otherRange = $otherEndValue - $otherStartValue;
                    
                    // Захист від ділення на 0
                    if ($otherRange <= 0) {
                        $otherRange = 1;
                    }
                    
                    // 25% від зміни (випадково вибираємо напрямок в + або -)
                    $influencePercent = 0.25;
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
        $criticalThreshold = max(1, $range * 0.05); // Мінімум 1, щоб уникнути проблем з дуже малими range
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
        
        // Якщо значення критичне, збільшуємо лічильник
        if ($isCritical) {
            $this->iotEcoService->incrementCriticalWarnings($iotEcoId);
        }
        
        return [
            'success' => true,
            'iot_eco_id' => $iotEcoId,
            'value' => $value,
            'previous_value' => $previousValue,
            'change' => $change,
            'updated_sensors' => $updatedSensors,
            'is_critical' => $isCritical,
            'warning_type' => $warningType,
            'distance_to_min' => $distanceToMin,
            'distance_to_max' => $distanceToMax,
            'critical_warnings' => $isCritical ? $this->iotEcoService->getCriticalWarnings($iotEcoId) : null
        ];
    }

    // ============ Clients ============
    
    public function getAllClients(): array
    {
        return $this->clientService->getAll();
    }

    public function createClient(array $data): void
    {
        $this->clientService->create($data);
    }

    public function updateClient(int $id, array $data): void
    {
        $this->clientService->update($id, $data);
    }

    public function deleteClient(int $id): void
    {
        $this->clientService->delete($id);
    }

    // ============ Staff ============
    
    public function getAllStaff(): array
    {
        return $this->staffService->getAll();
    }

    public function createStaff(array $data): void
    {
        $this->staffService->create($data);
    }

    public function updateStaff(int $id, array $data): void
    {
        $this->staffService->update($id, $data);
    }

    public function deleteStaff(int $id): void
    {
        $this->staffService->delete($id);
    }

    // ============ IoT Data & Stats ============
    
    /**
     * Отримати дані для графіків IoT датчика
     */
    public function getIotDataForGraph(int $iotEcoId): array
    {
        $sensor = $this->iotEcoService->getSensorInfo($iotEcoId);
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
        
        return [
            'success' => true,
            'labels' => $labels,
            'values' => $values,
            'current_value' => $currentValue,
            'is_critical' => $isCritical,
            'warning_type' => $warningType
        ];
    }

    /**
     * Отримати статистику IoT датчиків для рецепту
     */
    public function getIotStatsForRecipe(int $recipeId, string $period = '7'): array
    {
        $recipe = $this->recipeService->getById($recipeId);
        if (!$recipe) {
            throw new Exception('Recipe not found');
        }

        $ecoSystem = $this->recipeService->getEcoSystemByRecipeId($recipeId);
        if (!$ecoSystem) {
            throw new Exception('Eco system not found');
        }

        // Отримуємо IoT сенсори для цієї eco_system
        $iotSensors = $this->iotEcoService->getIotSensorsByEcoSystemId($ecoSystem['id']);
        
        // Визначаємо dateFrom для статистики
        if ($period === 'hour') {
            $dateFrom = date('Y-m-d H:i:s', strtotime('-1 hour'));
        } elseif ($period === 'day') {
            $dateFrom = date('Y-m-d H:i:s', strtotime('-1 day'));
        } else {
            $days = (int)$period;
            if ($days <= 0) {
                $days = 7;
            }
            $dateFrom = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        }
        
        $sensorsStats = [];
        foreach ($iotSensors as $sensor) {
            $stats = $this->iotEcoService->getStatisticsForSensor($sensor['id'], $dateFrom);
            $histogram = $this->iotEcoService->getHistogramData($sensor['id'], $period);
            
            $sensorsStats[] = [
                'id' => $sensor['id'],
                'name' => $sensor['iot_name'],
                'start_value' => $sensor['iot_start_value'] ?? 0,
                'end_value' => $sensor['iot_end_value'] ?? 100,
                'critical_warnings_count' => (int)($sensor['critical_warnings_count'] ?? 0),
                'avg' => $stats['avg'],
                'min' => $stats['min'],
                'max' => $stats['max'],
                'count' => $stats['count'],
                'histogram' => $histogram,
            ];
        }

        return [
            'recipe' => $recipe,
            'sensorsStats' => $sensorsStats,
            'period' => $period,
        ];
    }

    /**
     * Оновити статус eco system
     */
    public function updateEcoSystemStatus(int $ecoSystemId, bool $isActive): array
    {
        $this->recipeService->updateEcoSystemStatus($ecoSystemId, $isActive);
        
        // Отримуємо оновлені дані eco_system
        $ecoSystem = $this->recipeService->getEcoSystemByEcoSystemId($ecoSystemId);
        
        if (!$ecoSystem) {
            throw new Exception('Eco system not found');
        }
        
        return [
            'success' => true,
            'is_active' => $isActive,
            'date_start' => $ecoSystem['date_start'] ?? null,
            'date_end' => $ecoSystem['date_end'] ?? null,
            'message' => $isActive ? 'Eco system started' : 'Eco system stopped'
        ];
    }

    /**
     * Оновити опис eco system
     */
    public function updateEcoSystemDescription(int $ecoSystemId, string $description): array
    {
        $this->recipeService->updateEcoSystemDescription($ecoSystemId, $description);
        return [
            'success' => true,
            'description' => $description
        ];
    }

    /**
     * Оновити staff для eco system
     */
    public function updateEcoSystemStaff(int $ecoSystemId, ?int $staffId): array
    {
        $this->recipeService->updateEcoSystemStaff($ecoSystemId, $staffId);
        return [
            'success' => true,
            'staff_id' => $staffId
        ];
    }

    // ============ Orders ============

    /**
     * Отримати всі замовлення з фільтрацією
     */
    public function getAllOrders(?string $statusFilter = null, ?string $searchQuery = null): array
    {
        return $this->orderService->getAllOrders($statusFilter, $searchQuery);
    }

    /**
     * Отримати замовлення по ID
     */
    public function getOrderById(int $orderId): ?array
    {
        return $this->orderService->getOrderById($orderId);
    }

    /**
     * Оновити статус транзакції (тільки для бухгалтера)
     */
    public function updateTransactionStatus(int $transactionId, string $newStatus, ?int $staffId = null): array
    {
        $transaction = $this->orderService->getTransactionById($transactionId);
        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction not found'
            ];
        }

        // Перевіряємо, чи статус дозволений
        $allowedStatuses = ['Pending', 'Complete', 'Reject', 'Cancel'];
        if (!in_array($newStatus, $allowedStatuses)) {
            return [
                'success' => false,
                'message' => 'Invalid status'
            ];
        }

        // Оновлюємо статус та staff_id
        $success = $this->orderService->updateTransactionStatus($transactionId, $newStatus, $staffId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Status updated successfully' : 'Failed to update status'
        ];
    }

    /**
     * Отримати всіх staff для вибору
     */
    public function getAllStaffForSelect(): array
    {
        return $this->staffService->getAllForSelect();
    }

    /**
     * Оновити тільки staff_id транзакції (без зміни статусу)
     */
    public function updateTransactionStaff(int $transactionId, int $staffId): array
    {
        $transaction = $this->orderService->getTransactionById($transactionId);
        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction not found'
            ];
        }

        // Оновлюємо тільки staff_id
        $success = $this->orderService->updateTransactionStaff($transactionId, $staffId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Staff updated successfully' : 'Failed to update staff'
        ];
    }

    // ============ Statistics ============

    public function getTodayStats(): array
    {
        return $this->orderService->getTodayStats();
    }

    public function getPendingStats(): array
    {
        return $this->orderService->getPendingStats();
    }

    public function getApprovedLast7Days(): array
    {
        return $this->orderService->getApprovedLast7Days();
    }

    public function getSalesByDay(int $days = 30): array
    {
        return $this->orderService->getSalesByDay($days);
    }

    public function getSalesByMonth(int $months = 12): array
    {
        return $this->orderService->getSalesByMonth($months);
    }

    public function getAllTransactionsForStats(int $limit = 50, int $offset = 0): array
    {
        return $this->orderService->getAllTransactionsForStats($limit, $offset);
    }

    public function getFinancialStatsByPeriod(string $period = 'day'): array
    {
        return $this->orderService->getFinancialStatsByPeriod($period);
    }

    public function getStatusStats(): array
    {
        return $this->orderService->getStatusStats();
    }

    public function getClientStats(): array
    {
        return $this->orderService->getClientStats();
    }

    public function getRecipeStats(): array
    {
        return $this->orderService->getRecipeStats();
    }

    public function getSalesByWeek(int $weeks = 12): array
    {
        return $this->orderService->getSalesByWeek($weeks);
    }

    public function getCompanyOverview(): array
    {
        return $this->orderService->getCompanyOverview();
    }

    public function getTotalOrders(): int
    {
        return $this->orderService->getTotalOrders();
    }

    public function getStatusTrendsByDay(int $days = 30): array
    {
        return $this->orderService->getStatusTrendsByDay($days);
    }

    public function getTopRecipesChart(int $limit = 5): array
    {
        return $this->orderService->getTopRecipesChart($limit);
    }

    public function getTopStaffChart(int $limit = 5): array
    {
        return $this->orderService->getTopStaffChart($limit);
    }

    public function getTransactionsByWeekday(): array
    {
        return $this->orderService->getTransactionsByWeekday();
    }
}

