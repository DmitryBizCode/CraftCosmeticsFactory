<?php

namespace App\Controllers;

use App\Services\Common\TemplateService;
use App\Services\Common\ResponseService;
use App\Services\Admin\AdminActionService;
use App\Services\Auth\AuthService;
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
        $error = isset($_GET['error']) ? urldecode($_GET['error']) : null;
        $username = isset($_GET['username']) ? urldecode($_GET['username']) : '';
        return $this->templateService->render('pages/adminPage', [
            'homeNavBar' => null,
            'footer' => null,
            'error' => $error,
            'username' => $username,
        ]);
    }

    /** Обробка входу в адмінку */
    public function adminLogin(array $data): string
    {
        try {
            // Валідація обов'язкових полів
            if (empty($data['username']) || empty($data['password'])) {
                $username = isset($data['username']) ? urlencode($data['username']) : '';
                ResponseService::redirectWithError('index.php?action=admin&username=' . $username, 'Username and password are required');
                return '';
            }

            $username = trim($data['username']);
            $password = trim($data['password']);

            // Перевіряємо логін та пароль
            if ($username === 'admin' && $password === 'admin') {
                // Зберігаємо інформацію про адміна в сесії
                AuthService::startSession();
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = 'admin';

                // Редирект на сторінку продуктів
                ResponseService::redirectWithSuccess('index.php?action=adminProducts', 'Welcome to Admin Panel!');
                return '';
            } else {
                // Невірний логін або пароль
                $usernameEncoded = urlencode($username);
                ResponseService::redirectWithError('index.php?action=admin&username=' . $usernameEncoded, 'Invalid username or password');
                return '';
            }

        } catch (Exception $e) {
            error_log("Error during admin login: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=admin', 'Login error. Please try again.');
            return '';
        }
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
        try {
            // Перевіряємо, чи адмін залогінений
            AuthService::startSession();
            if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
                ResponseService::redirectWithError('index.php?action=admin', 'Please log in to access admin panel');
                return '';
            }

            // Отримуємо фільтри з GET параметрів
            $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : null;
            $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;

            // Отримуємо всі замовлення
            $orders = $this->adminActionService->getAllOrders($statusFilter, $searchQuery);

            // Отримуємо список staff для вибору
            $staffList = $this->adminActionService->getAllStaffForSelect();

            // Перевіряємо, чи поточний адмін є бухгалтером (для зміни статусу)
            // Для спрощення, якщо адмін залогінений, дозволяємо зміну статусу
            // В реальному проекті тут була б перевірка через staff_id та access_layer
            $canChangeStatus = true; // Адмін може змінювати статус

            return $this->templateService->render('pages/adminOrders', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'orders',
                'orders' => $orders,
                'statusFilter' => $statusFilter,
                'searchQuery' => $searchQuery,
                'canChangeStatus' => $canChangeStatus,
                'staffList' => $staffList,
            ]);
        } catch (Exception $e) {
            error_log("Error loading orders: " . $e->getMessage());
            return $this->templateService->render('pages/adminOrders', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'orders',
                'orders' => [],
                'statusFilter' => null,
                'searchQuery' => null,
                'canChangeStatus' => false,
            ]);
        }
    }

    /** Оновити статус транзакції */
    public function updateOrderStatus(array $data): string
    {
        try {
            // Перевіряємо, чи адмін залогінений
            AuthService::startSession();
            if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
                ResponseService::redirectWithError('index.php?action=adminOrders', 'Access denied');
                return '';
            }

            $transactionId = isset($data['transaction_id']) ? (int)$data['transaction_id'] : 0;
            $newStatus = isset($data['status']) ? trim($data['status']) : '';
            $staffId = isset($data['staff_id']) && !empty($data['staff_id']) ? (int)$data['staff_id'] : null;

            // Якщо передано тільки staff_id без статусу - оновлюємо тільки staff
            if ($transactionId > 0 && empty($newStatus) && $staffId !== null && $staffId > 0) {
                $result = $this->adminActionService->updateTransactionStaff($transactionId, $staffId);
                if ($result['success']) {
                    ResponseService::redirectWithSuccess('index.php?action=adminOrders', $result['message']);
                } else {
                    ResponseService::redirectWithError('index.php?action=adminOrders', $result['message']);
                }
                return '';
            }

            // Якщо передано статус - оновлюємо статус (staff_id опціональний)
            if ($transactionId <= 0 || empty($newStatus)) {
                ResponseService::redirectWithError('index.php?action=adminOrders', 'Invalid transaction ID or status');
                return '';
            }

            // При зміні статусу staff_id не обов'язковий
            $result = $this->adminActionService->updateTransactionStatus($transactionId, $newStatus, $staffId);

            if ($result['success']) {
                ResponseService::redirectWithSuccess('index.php?action=adminOrders', $result['message']);
            } else {
                ResponseService::redirectWithError('index.php?action=adminOrders', $result['message']);
            }
            return '';
        } catch (Exception $e) {
            error_log("Error updating order status: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=adminOrders', 'Error updating status');
            return '';
        }
    }

    /** Сторінка статистики продажів / транзакцій */
    public function getAdminStats(): string
    {
        try {
            // Фінансова статистика по періодах
            $statsDay = $this->adminActionService->getFinancialStatsByPeriod('day');
            $statsWeek = $this->adminActionService->getFinancialStatsByPeriod('week');
            $statsMonth = $this->adminActionService->getFinancialStatsByPeriod('month');
            $statsYear = $this->adminActionService->getFinancialStatsByPeriod('year');
            $statsAll = $this->adminActionService->getFinancialStatsByPeriod('all');

            // Статистика по статусах
            $statusStats = $this->adminActionService->getStatusStats();

            // Статистика клієнтів
            $clientStats = $this->adminActionService->getClientStats();

            // Статистика рецептів
            $recipeStats = $this->adminActionService->getRecipeStats();

            // Дані для графіків
            $salesByDay = $this->adminActionService->getSalesByDay(30);
            $salesByWeek = $this->adminActionService->getSalesByWeek(12);
            $salesByMonth = $this->adminActionService->getSalesByMonth(12);

            // Загальна статистика компанії
            $companyOverview = $this->adminActionService->getCompanyOverview();
            $totalOrders = $this->adminActionService->getTotalOrders();
            $statusTrends = $this->adminActionService->getStatusTrendsByDay(30);
            $topRecipesChart = $this->adminActionService->getTopRecipesChart(5);
            $topStaffChart = $this->adminActionService->getTopStaffChart(5);
            $transactionsByWeekday = $this->adminActionService->getTransactionsByWeekday();

            return $this->templateService->render('pages/adminStats', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'stats',
                'statsDay' => $statsDay,
                'statsWeek' => $statsWeek,
                'statsMonth' => $statsMonth,
                'statsYear' => $statsYear,
                'statsAll' => $statsAll,
                'statusStats' => $statusStats,
                'clientStats' => $clientStats,
                'recipeStats' => $recipeStats,
                'salesByDay' => $salesByDay,
                'salesByWeek' => $salesByWeek,
                'salesByMonth' => $salesByMonth,
                'companyOverview' => $companyOverview,
                'totalOrders' => $totalOrders,
                'statusTrends' => $statusTrends,
                'topRecipesChart' => $topRecipesChart,
                'topStaffChart' => $topStaffChart,
                'transactionsByWeekday' => $transactionsByWeekday,
            ]);
        } catch (Exception $e) {
            error_log("Error loading admin stats: " . $e->getMessage());
            return $this->templateService->render('pages/adminStats', [
                'homeNavBar' => null,
                'footer' => null,
                'currentPage' => 'stats',
                'statsDay' => [],
                'statsWeek' => [],
                'statsMonth' => [],
                'statsYear' => [],
                'statsAll' => [],
                'statusStats' => [],
                'clientStats' => ['total_clients' => 0, 'active_clients_30d' => 0, 'top_clients' => []],
                'recipeStats' => [],
                'salesByDay' => [],
                'salesByWeek' => [],
                'salesByMonth' => [],
                'companyOverview' => [],
                'totalOrders' => 0,
                'statusTrends' => [],
                'topRecipesChart' => [],
                'topStaffChart' => [],
                'transactionsByWeekday' => [],
            ]);
        }
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


