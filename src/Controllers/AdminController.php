<?php

namespace App\Controllers;

use App\Services\Common\TemplateService;
use App\Services\Common\ResponseService;
use App\Services\Admin\AdminActionService;
use Exception;

class AdminController
{
    private TemplateService $templateService;
    private AdminActionService $adminActionService;

    public function __construct()
    {
        $this->templateService = new TemplateService();
        $this->adminActionService = new AdminActionService();
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
        $products = $this->adminActionService->getAllProducts();
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
            $this->adminActionService->createProduct($data);
            ResponseService::redirectWithSuccess('index.php?action=adminProducts', 'created');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminProducts', 'Error creating product: ' . $e->getMessage());
            return '';
        }
    }

    /** Оновлення продукту */
    public function updateProduct(int $id, array $data): string
    {
        try {
            $this->adminActionService->updateProduct($id, $data);
            ResponseService::redirectWithSuccess('index.php?action=adminProducts', 'updated');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminProducts', 'Error updating product: ' . $e->getMessage());
            return '';
        }
    }

    /** Видалення продукту */
    public function deleteProduct(int $id): string
    {
        try {
            $this->adminActionService->deleteProduct($id);
            ResponseService::redirectWithSuccess('index.php?action=adminProducts', 'deleted');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminProducts', 'Error deleting product: ' . $e->getMessage());
            return '';
        }
    }

    /** Сторінка управління рецептами */
    public function getAdminRecipes(): string
    {
        try {
            $data = $this->adminActionService->getAllRecipesWithDetails();
            $success = isset($_GET['success']) && !empty($_GET['success']) ? $_GET['success'] : null;
            
            return $this->templateService->render('pages/adminRecipes', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'recipes',
                'recipes' => $data['recipes'],
                'products' => $data['products'],
                'iotSensors' => $data['iotSensors'],
                'success' => $success,
            ]);
        } catch (Exception $e) {
            $data = $this->adminActionService->getAllRecipesWithDetails();
            return $this->templateService->render('pages/adminRecipes', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'recipes',
                'recipes' => $data['recipes'],
                'products' => $data['products'],
                'iotSensors' => $data['iotSensors'],
                'error' => 'Error loading recipes: ' . $e->getMessage(),
            ]);
        }
    }

    /** Створення нового рецепту */
    public function createRecipe(array $data, array $image): string
    {
        try {
            $this->adminActionService->createRecipeWithDetails($data, $image);
            ResponseService::redirectWithSuccess('index.php?action=adminRecipes', 'created');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminRecipes', 'Error creating recipe: ' . $e->getMessage());
            return '';
        }
    }

    /** Оновлення рецепту */
    public function updateRecipe(int $id, array $data, array $image): string
    {
        try {
            $this->adminActionService->updateRecipeWithDetails($id, $data, $image);
            ResponseService::redirectWithSuccess('index.php?action=adminRecipes', 'updated');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminRecipes', 'Error updating recipe: ' . $e->getMessage());
            return '';
        }
    }

    /** Видалення рецепту */
    public function deleteRecipe(int $id): string
    {
        try {
            $this->adminActionService->deleteRecipe($id);
            ResponseService::redirectWithSuccess('index.php?action=adminRecipes', 'deleted');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminRecipes', 'Error deleting recipe: ' . $e->getMessage());
            return '';
        }
    }

    /** Видалення фото рецепту */
    public function deleteRecipePhotoAction(int $id): string
    {
        try {
            $this->adminActionService->deleteRecipePhoto($id);
            ResponseService::redirectWithSuccess('index.php?action=adminRecipes', 'photo_deleted');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminRecipes', $e->getMessage());
            return '';
        }
    }

    /** Сторінка контролю еко-системи рецепту */
    public function getAdminEcoSystem(int $recipeId): string
    {
        try {
            $data = $this->adminActionService->getEcoSystemPageData($recipeId);
            
            return $this->templateService->render('pages/adminEcoSystem', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'recipes',
                'recipeId' => $recipeId,
                'recipe' => $data['recipe'],
                'ecoSystem' => $data['ecoSystem'],
                'iotSensors' => $data['iotSensors'],
                'timeToComplete' => $data['timeToComplete'],
                'recipeProducts' => $data['recipeProducts'],
                'staffMembers' => $data['staffMembers'],
            ]);
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminRecipes', $e->getMessage());
            return '';
        }
    }

    /** API: Генерація нового значення для датчика */
    public function generateIotValue(int $iotEcoId): string
    {
        try {
            $result = $this->adminActionService->generateIotValue($iotEcoId);
            return ResponseService::jsonSuccess($result);
        } catch (Exception $e) {
            return ResponseService::jsonError($e->getMessage(), 400);
        }
    }

    /** API: Встановлення значення вручну */
    public function setIotValue(int $iotEcoId, float $value): string
    {
        try {
            $result = $this->adminActionService->setIotValue($iotEcoId, $value);
            return ResponseService::jsonSuccess($result);
        } catch (Exception $e) {
            return ResponseService::jsonError($e->getMessage(), 400);
        }
    }

    /** API: Отримання даних для графіків */
    public function getIotData(int $iotEcoId): string
    {
        try {
            $result = $this->adminActionService->getIotDataForGraph($iotEcoId);
            return ResponseService::jsonSuccess($result);
        } catch (Exception $e) {
            return ResponseService::jsonError($e->getMessage(), 400);
        }
    }

    /** API: Запуск/зупинка eco_system */
    public function toggleEcoSystem(int $ecoSystemId, bool $isActive): string
    {
        try {
            $result = $this->adminActionService->updateEcoSystemStatus($ecoSystemId, $isActive);
            return ResponseService::jsonSuccess($result);
        } catch (Exception $e) {
            return ResponseService::jsonError($e->getMessage(), 400);
        }
    }

    /** API: Оновлення опису eco_system */
    public function updateEcoSystemDescription(int $ecoSystemId, string $description): string
    {
        try {
            $result = $this->adminActionService->updateEcoSystemDescription($ecoSystemId, $description);
            return ResponseService::jsonSuccess($result);
        } catch (Exception $e) {
            return ResponseService::jsonError($e->getMessage(), 400);
        }
    }

    /** API: Оновлення staff для eco_system */
    public function updateEcoSystemStaff(int $ecoSystemId, ?int $staffId): string
    {
        try {
            $result = $this->adminActionService->updateEcoSystemStaff($ecoSystemId, $staffId);
            return ResponseService::jsonSuccess($result);
        } catch (Exception $e) {
            return ResponseService::jsonError($e->getMessage(), 400);
        }
    }

    /** Сторінка статистики IoT датчиків */
    public function getAdminIotStats(int $recipeId): string
    {
        try {
            $period = $_GET['period'] ?? $_GET['days'] ?? '7';
            $data = $this->adminActionService->getIotStatsForRecipe($recipeId, $period);
            
            return $this->templateService->render('pages/adminIotStats', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'recipes',
                'recipeId' => $recipeId,
                'recipe' => $data['recipe'],
                'sensorsStats' => $data['sensorsStats'],
                'period' => $data['period'],
            ]);
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminRecipes', $e->getMessage());
            return '';
        }
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
        $clients = $this->adminActionService->getAllClients();
        $staff = $this->adminActionService->getAllStaff();
        $success = isset($_GET['success']) && !empty($_GET['success']) ? $_GET['success'] : null;
        $error = isset($_GET['error']) && !empty($_GET['error']) ? $_GET['error'] : null;
        
        return $this->templateService->render('pages/adminUsers', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'users',
            'clients' => $clients,
            'staff' => $staff,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /** Створення нового клієнта */
    public function createClient(array $data): string
    {
        try {
            $this->adminActionService->createClient($data);
            ResponseService::redirectWithSuccess('index.php?action=adminUsers', 'client_created');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminUsers', 'Error creating client: ' . $e->getMessage());
            return '';
        }
    }

    /** Оновлення клієнта */
    public function updateClient(int $id, array $data): string
    {
        try {
            $this->adminActionService->updateClient($id, $data);
            ResponseService::redirectWithSuccess('index.php?action=adminUsers', 'client_updated');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminUsers', 'Error updating client: ' . $e->getMessage());
            return '';
        }
    }

    /** Видалення клієнта */
    public function deleteClient(int $id): string
    {
        try {
            $this->adminActionService->deleteClient($id);
            ResponseService::redirectWithSuccess('index.php?action=adminUsers', 'client_deleted');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminUsers', 'Error deleting client: ' . $e->getMessage());
            return '';
        }
    }

    /** Створення нового персоналу */
    public function createStaff(array $data): string
    {
        try {
            $this->adminActionService->createStaff($data);
            ResponseService::redirectWithSuccess('index.php?action=adminUsers', 'staff_created');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminUsers', 'Error creating staff: ' . $e->getMessage());
            return '';
        }
    }

    /** Оновлення персоналу */
    public function updateStaff(int $id, array $data): string
    {
        try {
            $this->adminActionService->updateStaff($id, $data);
            ResponseService::redirectWithSuccess('index.php?action=adminUsers', 'staff_updated');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminUsers', 'Error updating staff: ' . $e->getMessage());
            return '';
        }
    }

    /** Видалення персоналу */
    public function deleteStaff(int $id): string
    {
        try {
            $this->adminActionService->deleteStaff($id);
            ResponseService::redirectWithSuccess('index.php?action=adminUsers', 'staff_deleted');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminUsers', 'Error deleting staff: ' . $e->getMessage());
            return '';
        }
    }

    /** Сторінка управління IoT / Eco system */
    public function getAdminIot(): string
    {
        try {
            $data = $this->adminActionService->getIotManagementPageData();
            $success = isset($_GET['success']) && !empty($_GET['success']) ? $_GET['success'] : null;
            
            return $this->templateService->render('pages/adminIot', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'iot',
                'iotSensors' => $data['iotSensors'],
                'ecoSystems' => $data['ecoSystems'],
                'success' => $success,
            ]);
        } catch (Exception $e) {
            $data = $this->adminActionService->getIotManagementPageData();
            return $this->templateService->render('pages/adminIot', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'iot',
                'iotSensors' => $data['iotSensors'],
                'ecoSystems' => $data['ecoSystems'],
                'error' => 'Error loading IoT sensors: ' . $e->getMessage(),
            ]);
        }
    }

    /** Створення нового IoT датчика */
    public function createIotSensor(array $data): string
    {
        try {
            $this->adminActionService->createIotSensor($data);
            ResponseService::redirectWithSuccess('index.php?action=adminIot', 'created');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminIot', 'Error creating IoT sensor: ' . $e->getMessage());
            return '';
        }
    }

    /** Оновлення IoT датчика */
    public function updateIotSensor(int $id, array $data): string
    {
        try {
            $this->adminActionService->updateIotSensor($id, $data);
            ResponseService::redirectWithSuccess('index.php?action=adminIot', 'updated');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminIot', 'Error updating IoT sensor: ' . $e->getMessage());
            return '';
        }
    }

    /** Видалення IoT датчика */
    public function deleteIotSensor(int $id): string
    {
        try {
            $this->adminActionService->deleteIotSensor($id);
            ResponseService::redirectWithSuccess('index.php?action=adminIot', 'deleted');
            return '';
        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=adminIot', 'Error deleting IoT sensor: ' . $e->getMessage());
            return '';
        }
    }
}


