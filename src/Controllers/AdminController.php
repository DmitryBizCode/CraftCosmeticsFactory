<?php

namespace App\Controllers;

use App\Services\TemplateService;
use App\Services\SQLService;
use App\Services\ProductService;
use App\Services\IotService;
use App\Services\RecipeService;

class AdminController
{
    private TemplateService $templateService;
    private ProductService $productService;
    private IotService $iotService;
    private RecipeService $recipeService;

    public function __construct()
    {
        $this->templateService = new TemplateService();
        $sqlService = new SQLService();
        $this->productService = new ProductService($sqlService->getPdo());
        $this->iotService = new IotService($sqlService->getPdo());
        $this->recipeService = new RecipeService($sqlService->getPdo());
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
            $timeToComplete = $hours + ($minutes / 60) + ($seconds / 3600);

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
                } else {
                    $iotSensors = $data['iot_sensors'];
                }
                
                if (is_array($iotSensors)) {
                    foreach ($iotSensors as $iotId) {
                        $this->recipeService->addIotSensorToEcoSystem($ecoSystemId, (int)$iotId);
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
            $timeToComplete = $hours + ($minutes / 60) + ($seconds / 3600);

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
                    } else {
                        $iotSensors = $data['iot_sensors'];
                    }
                    
                    if (is_array($iotSensors)) {
                        foreach ($iotSensors as $iotId) {
                            $this->recipeService->addIotSensorToEcoSystem($ecoSystem['id'], (int)$iotId);
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
            if ($recipe && $recipe['photo']) {
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
            if ($recipe && $recipe['photo']) {
                $this->deleteRecipePhoto($recipe['photo']);
                $this->recipeService->update($id, [
                    'name' => $recipe['name'],
                    'time_to_complete' => $recipe['time_to_complete'],
                    'description' => $recipe['description'],
                    'photo' => null,
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
        if ($hours < 0.0167) { // Менше 1 хвилини
            return round($hours * 3600) . ' sec';
        } elseif ($hours < 1) { // Менше 1 години
            return round($hours * 60, 1) . ' min';
        } elseif ($hours < 24) { // Менше 1 дня
            return round($hours, 1) . ' hours';
        } else {
            return round($hours / 24, 1) . ' days';
        }
    }

    /** Сторінка контролю еко-системи рецепту */
    public function getAdminEcoSystem(int $recipeId): string
    {
        return $this->templateService->render('pages/adminEcoSystem', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'recipes',
            'recipeId' => $recipeId,
        ]);
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


