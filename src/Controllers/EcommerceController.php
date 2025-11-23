<?php

namespace App\Controllers;

use App\Services\Legacy\CalculateShipments;
use App\Services\Legacy\ContainerService;
use App\Services\Iot\IotService;
use App\Services\Common\Mail;
use App\Services\User\PeopleService;
use App\Services\User\ClientService;
use App\Services\User\CareRemindersService;
use App\Services\Auth\AuthService;
use App\Services\Recipe\RecipeService;
use App\Services\Legacy\PlaceService;
use App\Services\Legacy\RentService;
use App\Services\Database\SQLService;
use App\Services\Common\TemplateService;
use App\Services\Common\ResponseService;
use Exception;
use PDO;
use App\Model\PeopleModel;
use TypeError;

class EcommerceController
{
    private TemplateService $templateService;

    public function __construct()
    {
        $this->templateService = new TemplateService();
    }
    private function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit();
    }
    public function getEcommerceHomePage(): string
    {
        try {
            // Отримуємо пошуковий запит
            $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
            
            // Отримуємо рецепти з бази даних
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $recipeService = new RecipeService($pdo);
            $recipes = $recipeService->getAllForSalesPage($searchQuery);
            
            // Отримуємо інформацію про поточного користувача для фото профілю
            $userPhoto = '/image/pfp.png'; // Дефолтне фото
            $userProfileLink = '/index.php?action=profile';
            
            if (AuthService::isLoggedIn()) {
                $userId = AuthService::getUserId();
                if ($userId) {
                    $clientService = new ClientService($pdo);
                    $user = $clientService->getById($userId);
                    if ($user && !empty($user['photo'])) {
                        // Перевіряємо, чи існує файл фото
                        $baseDir = dirname(__DIR__, 2);
                        $photoPath = $baseDir . '/image/profile/' . $user['photo'];
                        if (!file_exists($photoPath)) {
                            $photoPath = $baseDir . '/image/' . $user['photo'];
                        }
                        if (file_exists($photoPath)) {
                            if (strpos($photoPath, '/image/profile/') !== false) {
                                $userPhoto = '/image/profile/' . $user['photo'];
                            } else {
                                $userPhoto = '/image/' . $user['photo'];
                            }
                        }
                    }
                }
            }
            
            // Розраховуємо пагінацію
            $itemsPerPage = 9; // Товарів на сторінку (максимум 9)
            $totalRecipes = count($recipes);
            $totalPages = max(1, ceil($totalRecipes / $itemsPerPage));
            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $currentPage = min($currentPage, $totalPages);
            
            // Обрізаємо масив рецептів для поточної сторінки
            $offset = ($currentPage - 1) * $itemsPerPage;
            $paginatedRecipes = array_slice($recipes, $offset, $itemsPerPage);
            
            // Рандомний вибір 6 товарів для recommendations (незалежно від пошуку)
            $recommendedRecipes = [];
            $allRecipesForRecommendations = $recipeService->getAllForSalesPage(); // Всі рецепти для рекомендацій
            if (count($allRecipesForRecommendations) > 0) {
                $availableRecipes = $allRecipesForRecommendations;
                // Перемішуємо масив
                shuffle($availableRecipes);
                // Беремо максимум 6
                $recommendedRecipes = array_slice($availableRecipes, 0, min(6, count($availableRecipes)));
            }
            
            return $this->templateService->render('pages/indexPage', [
                "homeNavBar" => 1,
                "footer" => 1,
                "recipes" => $paginatedRecipes,
                "allRecipes" => $recipes,
                "recommendedRecipes" => $recommendedRecipes,
                "currentPage" => $currentPage,
                "totalPages" => $totalPages,
                "totalRecipes" => $totalRecipes,
                "itemsPerPage" => $itemsPerPage,
                "searchQuery" => $searchQuery,
                "userPhoto" => $userPhoto,
                "userProfileLink" => $userProfileLink
            ]);
        } catch (Exception $e) {
            error_log("Error loading recipes: " . $e->getMessage());
            // У випадку помилки повертаємо сторінку без рецептів
            $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
            return $this->templateService->render('pages/indexPage', [
                "homeNavBar" => 1,
                "footer" => 1,
                "recipes" => [],
                "allRecipes" => [],
                "recommendedRecipes" => [],
                "currentPage" => 1,
                "totalPages" => 1,
                "totalRecipes" => 0,
                "itemsPerPage" => 9,
                "searchQuery" => $searchQuery,
                "userPhoto" => '/image/pfp.png',
                "userProfileLink" => '/index.php?action=profile'
            ]);
        }
    }
    public function getProfile(): string
    {
        try {
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            
            // Перевіряємо, чи користувач залогінений
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to view your profile');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to view your profile');
                return '';
            }
            
            // Отримуємо дані користувача
            $clientService = new ClientService($pdo);
            $user = $clientService->getById($userId);
            
            if (!$user) {
                ResponseService::redirectWithError('index.php?action=autorization', 'User not found');
                return '';
            }
            
            // Формуємо повне ім'я
            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if (empty($fullName)) {
                $fullName = 'User';
            }
            
            // Отримуємо фото користувача (додаємо timestamp для оновлення кешу)
            $userPhoto = '/image/pfp.png';
            if (!empty($user['photo']) && $user['photo'] !== 'pfp.png' && $user['photo'] !== null) {
                $baseDir = dirname(__DIR__, 2);
                // Перевіряємо спочатку в папці profile, потім в старій папці image
                $photoPath = $baseDir . '/image/profile/' . $user['photo'];
                if (!file_exists($photoPath)) {
                    $photoPath = $baseDir . '/image/' . $user['photo'];
                }
                
                if (file_exists($photoPath)) {
                    // Визначаємо правильний URL в залежності від місця знаходження файлу
                    if (strpos($photoPath, '/image/profile/') !== false) {
                        $userPhoto = '/image/profile/' . $user['photo'] . '?t=' . filemtime($photoPath);
                    } else {
                        $userPhoto = '/image/' . $user['photo'] . '?t=' . filemtime($photoPath);
                    }
                } else {
                    // Якщо файл не існує, логуємо, але НЕ видаляємо з БД автоматично
                    // (це може бути тимчасовий файл або файл на іншому сервері)
                    error_log("Warning: Photo file not found for user ID " . $userId . ", photo: " . $user['photo'] . ", but keeping in DB");
                }
            }
            
            // Отримуємо соціальні канали
            $socialChannels = [];
            $stmt = $pdo->prepare("SELECT * FROM social_channels WHERE client_id = :client_id ORDER BY is_primary DESC, created_at DESC");
            $stmt->execute([':client_id' => $userId]);
            $socialChannels = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Отримуємо останні 5 транзакцій з деталями замовлень
            $formattedOrders = [];
            try {
                // Отримуємо останні 5 транзакцій
                $stmt = $pdo->prepare("
                    SELECT t.*
                    FROM transactions t
                    WHERE t.client_id = :client_id
                    ORDER BY t.id DESC
                    LIMIT 5
                ");
                $stmt->execute([':client_id' => $userId]);
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Отримуємо всі замовлення користувача для формування описів
                $orderStmt = $pdo->prepare("
                    SELECT o.*, r.name as recipe_name, r.price as recipe_price
                    FROM orders o
                    LEFT JOIN recipes r ON o.recipe_id = r.id
                    WHERE o.client_id = :client_id
                    ORDER BY o.id DESC
                ");
                $orderStmt->execute([':client_id' => $userId]);
                $allOrders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Для кожної транзакції формуємо замовлення
                foreach ($transactions as $transaction) {
                    // Отримуємо загальну кількість товарів для цієї транзакції через SQL SUM (з бази даних)
                    $quantityStmt = $pdo->prepare("
                        SELECT COALESCE(SUM(o.quantity), 0) as total_quantity
                        FROM transaction_order to_rel
                        INNER JOIN orders o ON to_rel.order_id = o.id
                        WHERE to_rel.transaction_id = :transaction_id
                    ");
                    $quantityStmt->execute([':transaction_id' => $transaction['id']]);
                    $quantityResult = $quantityStmt->fetch(PDO::FETCH_ASSOC);
                    $totalQuantity = (int)($quantityResult['total_quantity'] ?? 0);
                    
                    // Отримуємо всі замовлення для формування опису
                    $orderStmt = $pdo->prepare("
                        SELECT o.*, r.name as recipe_name, r.price as recipe_price
                        FROM transaction_order to_rel
                        INNER JOIN orders o ON to_rel.order_id = o.id
                        LEFT JOIN recipes r ON o.recipe_id = r.id
                        WHERE to_rel.transaction_id = :transaction_id
                        ORDER BY o.id ASC
                    ");
                    $orderStmt->execute([':transaction_id' => $transaction['id']]);
                    $transactionOrders = $orderStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    
                    // Формуємо список назв рецептів для опису
                    $recipeNames = [];
                    foreach ($transactionOrders as $order) {
                        if (!empty($order['recipe_name'])) {
                            $recipeNames[] = $order['recipe_name'];
                        }
                    }
                    
                    // Форматуємо дату транзакції
                    $orderDate = 'N/A';
                    if (!empty($transaction['date'])) {
                        $orderDate = date('d M Y', strtotime($transaction['date']));
                    }
                    
                    // Формуємо опис
                    $description = !empty($transaction['description']) 
                        ? $transaction['description'] 
                        : (!empty($recipeNames) ? implode(', ', array_slice($recipeNames, 0, 2)) . (count($recipeNames) > 2 ? '...' : '') : 'Purchase');
                    
                    // Визначаємо клас статусу для відображення
                    $status = !empty($transaction['status']) ? $transaction['status'] : 'Pending';
                    $statusClass = 'alert'; // Pending по замовчуванню
                    
                    $statusLower = strtolower($status);
                    if ($statusLower == 'completed' || $statusLower == 'complete') {
                        $statusClass = 'success';
                    } elseif ($statusLower == 'failed') {
                        $statusClass = 'failed';
                    } elseif ($statusLower == 'reject' || $statusLower == 'rejected') {
                        $statusClass = 'reject';
                    } elseif ($statusLower == 'cancel' || $statusLower == 'cancelled') {
                        $statusClass = 'cancel';
                    } elseif ($statusLower == 'pending') {
                        $statusClass = 'alert';
                    }
                    
                    $formattedOrders[] = [
                        'id' => $transaction['id'],
                        'order_id' => '#' . str_pad($transaction['id'], 4, '0', STR_PAD_LEFT),
                        'date' => $orderDate,
                        'quantity' => $totalQuantity, // Загальна кількість товарів (сума всіх quantity)
                        'total' => !empty($transaction['value']) ? (float)$transaction['value'] : 0.00,
                        'status' => $status,
                        'statusClass' => $statusClass,
                        'description' => $description
                    ];
                }
                
            } catch (\Exception $e) {
                error_log("Error fetching orders: " . $e->getMessage());
                $formattedOrders = [];
            }
            
            // Розраховуємо середній кошик з усіх транзакцій
            $averageBasket = 0.00;
            try {
                $stmt = $pdo->prepare("
                    SELECT AVG(value) as avg_value, COUNT(*) as count
                    FROM transactions
                    WHERE client_id = :client_id
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!empty($result) && $result['count'] > 0) {
                    $averageBasket = round((float)$result['avg_value'], 2);
                }
            } catch (\Exception $e) {
                error_log("Error calculating average basket: " . $e->getMessage());
            }
            
            // Отримуємо останнє замовлення (остання транзакція)
            $lastOrder = null;
            try {
                $stmt = $pdo->prepare("
                    SELECT t.*
                    FROM transactions t
                    WHERE t.client_id = :client_id
                    ORDER BY t.id DESC
                    LIMIT 1
                ");
                $stmt->execute([':client_id' => $userId]);
                $lastTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lastTransaction) {
                    // Отримуємо замовлення для останньої транзакції
                    $orderStmt = $pdo->prepare("
                        SELECT o.*, r.name as recipe_name
                        FROM orders o
                        LEFT JOIN recipes r ON o.recipe_id = r.id
                        WHERE o.client_id = :client_id
                        ORDER BY o.id DESC
                        LIMIT 1
                    ");
                    $orderStmt->execute([':client_id' => $userId]);
                    $lastOrderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $orderDate = 'N/A';
                    if (!empty($lastTransaction['date'])) {
                        $orderDate = date('d M Y', strtotime($lastTransaction['date']));
                    }
                    
                    $description = !empty($lastTransaction['description']) 
                        ? $lastTransaction['description'] 
                        : (!empty($lastOrderData['recipe_name']) ? $lastOrderData['recipe_name'] : 'Purchase');
                    
                    $lastOrder = [
                        'date' => $orderDate,
                        'description' => $description
                    ];
                }
            } catch (\Exception $e) {
                error_log("Error fetching last order: " . $e->getMessage());
            }
            
            // 1. Розраховуємо Loyalty points: 5% від суми всіх транзакцій
            $loyaltyPoints = 0;
            try {
                $stmt = $pdo->prepare("SELECT SUM(value) as total FROM transactions WHERE client_id = :client_id");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalPurchases = (float)($result['total'] ?? 0);
                $loyaltyPoints = (int)round($totalPurchases * 0.05); // 5% від покупок
            } catch (\Exception $e) {
                error_log("Error calculating loyalty points: " . $e->getMessage());
            }
            
            // 2. Перевіряємо Allergies: чи були куплені рецепти з алергенами
            $allergiesStatus = 'Possibly has allergies';
            try {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT o.recipe_id 
                    FROM orders o
                    JOIN recipe_product rp ON o.recipe_id = rp.recipe_id
                    JOIN products p ON rp.product_id = p.id
                    WHERE o.client_id = :client_id AND p.allergen = 1
                    LIMIT 1
                ");
                $stmt->execute([':client_id' => $userId]);
                $hasAllergenPurchases = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($hasAllergenPurchases) {
                    $allergiesStatus = 'No known allergies'; // Якщо куплені рецепти з алергенами - немає алергії
                }
            } catch (\Exception $e) {
                error_log("Error checking allergies: " . $e->getMessage());
            }
            
            // 3. Отримуємо Preferred Ingredients (продукти які найчастіше купувались)
            $preferredIngredients = 'None';
            try {
                $stmt = $pdo->prepare("
                    SELECT p.name, COUNT(*) as purchase_count
                    FROM orders o
                    JOIN recipe_product rp ON o.recipe_id = rp.recipe_id
                    JOIN products p ON rp.product_id = p.id
                    WHERE o.client_id = :client_id
                    GROUP BY p.id, p.name
                    ORDER BY purchase_count DESC
                    LIMIT 3
                ");
                $stmt->execute([':client_id' => $userId]);
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($ingredients)) {
                    $ingredientNames = array_map(function($ing) {
                        return $ing['name'];
                    }, $ingredients);
                    $preferredIngredients = implode(', ', $ingredientNames);
                }
            } catch (\Exception $e) {
                error_log("Error fetching preferred ingredients: " . $e->getMessage());
            }
            
            // 4. Skin Type - стандартний текст
            $skinType = 'Normal to combination';
            
            // 5. Top Brands (рецепт який найбільше купувався)
            $topBrand = 'None';
            $topBrandCount = 0;
            try {
                $stmt = $pdo->prepare("
                    SELECT r.name, SUM(o.quantity) as total_quantity, COUNT(*) as purchase_count
                    FROM orders o
                    JOIN recipes r ON o.recipe_id = r.id
                    WHERE o.client_id = :client_id
                    GROUP BY r.id, r.name
                    ORDER BY total_quantity DESC, purchase_count DESC
                    LIMIT 1
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $topBrand = $result['name'];
                    $topBrandCount = (int)$result['total_quantity'];
                }
            } catch (\Exception $e) {
                error_log("Error fetching top brand: " . $e->getMessage());
            }
            
            // 6. Favorite Routine (найпопулярніший рецепт - той що найбільше купувався)
            $favoriteRoutine = 'None';
            try {
                $stmt = $pdo->prepare("
                    SELECT r.name, COUNT(*) as purchase_count
                    FROM orders o
                    JOIN recipes r ON o.recipe_id = r.id
                    WHERE o.client_id = :client_id
                    GROUP BY r.id, r.name
                    ORDER BY purchase_count DESC
                    LIMIT 1
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && !empty($result['name'])) {
                    $favoriteRoutine = $result['name'];
                }
            } catch (\Exception $e) {
                error_log("Error fetching favorite routine: " . $e->getMessage());
            }
            
            // Отримуємо випадкові нагадування
            $careReminders = CareRemindersService::getRandomReminders(3);
            
            return $this->templateService->render('pages/indexProfile', [
                "homeNavBar" => 1,
                "footer" => null,
                "user" => $user,
                "fullName" => $fullName,
                "userPhoto" => $userPhoto,
                "socialChannels" => $socialChannels,
                "orders" => $formattedOrders,
                "averageBasket" => $averageBasket,
                "lastOrder" => $lastOrder,
                "careReminders" => $careReminders,
                "loyaltyPoints" => $loyaltyPoints,
                "allergiesStatus" => $allergiesStatus,
                "preferredIngredients" => $preferredIngredients,
                "skinType" => $skinType,
                "topBrand" => $topBrand,
                "topBrandCount" => $topBrandCount,
                "favoriteRoutine" => $favoriteRoutine
            ]);
        } catch (Exception $e) {
            error_log("Error loading profile: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=autorization', 'Error loading profile. Please try again.');
            return '';
        }
    }
    public function getProfileSetting(): string
    {
        try {
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            
            // Перевіряємо, чи користувач залогінений
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to view your profile settings');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to view your profile settings');
                return '';
            }
            
            // Отримуємо дані користувача
            $clientService = new ClientService($pdo);
            $user = $clientService->getById($userId);
            
            if (!$user) {
                ResponseService::redirectWithError('index.php?action=autorization', 'User not found');
                return '';
            }
            
            // Отримуємо фото користувача (додаємо timestamp для оновлення кешу)
            $userPhoto = '/image/pfp.png';
            if (!empty($user['photo']) && $user['photo'] !== 'pfp.png' && $user['photo'] !== null) {
                $baseDir = dirname(__DIR__, 2);
                // Перевіряємо спочатку в папці profile, потім в старій папці image
                $photoPath = $baseDir . '/image/profile/' . $user['photo'];
                if (!file_exists($photoPath)) {
                    $photoPath = $baseDir . '/image/' . $user['photo'];
                }
                
                if (file_exists($photoPath)) {
                    // Визначаємо правильний URL в залежності від місця знаходження файлу
                    if (strpos($photoPath, '/image/profile/') !== false) {
                        $userPhoto = '/image/profile/' . $user['photo'] . '?t=' . filemtime($photoPath);
                    } else {
                        $userPhoto = '/image/' . $user['photo'] . '?t=' . filemtime($photoPath);
                    }
                } else {
                    // Якщо файл не існує, логуємо, але НЕ видаляємо з БД автоматично
                    // (це може бути тимчасовий файл або файл на іншому сервері)
                    error_log("Warning: Photo file not found for user ID " . $userId . ", photo: " . $user['photo'] . ", but keeping in DB");
                }
            }
            
            // Отримуємо соціальні канали
            $socialChannels = [];
            $stmt = $pdo->prepare("SELECT * FROM social_channels WHERE client_id = :client_id ORDER BY is_primary DESC, created_at DESC");
            $stmt->execute([':client_id' => $userId]);
            $socialChannels = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Групуємо соціальні канали по платформах для зручності
            $socialChannelsByPlatform = [];
            foreach ($socialChannels as $channel) {
                $platform = strtolower($channel['platform']);
                $socialChannelsByPlatform[$platform] = $channel;
            }
            
            $error = isset($_GET['error']) ? $_GET['error'] : null;
            $success = isset($_GET['success']) ? $_GET['success'] : null;
            
            return $this->templateService->render('pages/profileSetting', [
                "homeNavBar" => 1,
                "footer" => null,
                "user" => $user,
                "userPhoto" => $userPhoto,
                "socialChannels" => $socialChannels,
                "socialChannelsByPlatform" => $socialChannelsByPlatform,
                "error" => $error,
                "success" => $success
            ]);
        } catch (Exception $e) {
            error_log("Error loading profile settings: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=setting', 'Error loading profile settings. Please try again.');
            return '';
        }
    }

    /**
     * Оновити профіль користувача
     */
    public function updateProfile(array $data, array $image = []): string
    {
        try {
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to update your profile');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to update your profile');
                return '';
            }
            
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $clientService = new ClientService($pdo);
            
            // Отримуємо поточні дані користувача
            $user = $clientService->getById($userId);
            if (!$user) {
                ResponseService::redirectWithError('index.php?action=setting', 'User not found');
                return '';
            }
            
            // Обробка фото (як в рецептах)
            $photo = $user['photo'] ?? null; // Зберігаємо поточне фото
            
            // Якщо завантажено нове фото
            if (isset($image['photo']) && $image['photo']['error'] === UPLOAD_ERR_OK) {
                // Видаляємо старе фото, якщо воно є
                if ($photo && $photo !== 'pfp.png') {
                    $this->deleteProfilePhotoFile($photo);
                }
                // Завантажуємо нове фото
                try {
                    $photo = $this->uploadProfilePhoto($image['photo']);
                } catch (Exception $e) {
                    error_log("Error uploading photo: " . $e->getMessage());
                    ResponseService::redirectWithError('index.php?action=setting', 'Error uploading photo: ' . $e->getMessage());
                    return '';
                }
            }
            
            // Якщо потрібно видалити фото
            if (isset($data['remove_photo']) && $data['remove_photo'] === '1') {
                if ($photo && $photo !== 'pfp.png') {
                    $this->deleteProfilePhotoFile($photo);
                }
                $photo = null;
            }
            
            // Підготовка даних для оновлення (завжди включаємо photo)
            $updateData = [
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'] ?? '',
                'phone_number' => $data['phone_number'] ?? '',
                'address' => $data['address'] ?? '',
                'photo' => $photo, // Завжди передаємо photo (навіть якщо null)
            ];
            
            // Оновлюємо профіль
            $result = $clientService->update($userId, $updateData);
            
            if (!$result) {
                error_log("ERROR: ClientService::update returned false");
                ResponseService::redirectWithError('index.php?action=setting', 'Error updating profile. Please try again.');
                return '';
            }
            
            // Оновлюємо сесію з новим ім'ям
            if (!empty($updateData['first_name'])) {
                AuthService::login(
                    $userId,
                    $updateData['email'],
                    $updateData['first_name'],
                    $updateData['last_name'] ?? null
                );
            }
            
            ResponseService::redirectWithSuccess('index.php?action=setting', 'Profile updated successfully!');
            return '';
            
        } catch (Exception $e) {
            error_log("Error updating profile: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=setting', 'Error updating profile: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Завантажити фото профілю (як в рецептах)
     */
    private function uploadProfilePhoto(array $file): string
    {
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Перевірка типу файлу
        if (!in_array($file['type'], $allowedTypes)) {
            error_log("ERROR: Invalid file type: " . ($file['type'] ?? 'N/A'));
            throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }
        
        // Перевірка розміру
        if ($file['size'] > $maxSize) {
            error_log("ERROR: File too large: " . ($file['size'] ?? 'N/A'));
            throw new Exception('File is too large. Maximum size is 5MB.');
        }
        
        // Генеруємо hash ім'я файлу
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $hash = hash('sha256', $file['name'] . time() . uniqid());
        $hashName = $hash . '.' . $extension;
        
        // Використовуємо той самий підхід, що і в рецептах
        $targetDir = __DIR__ . '/../../image/profile/';
        
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        $targetPath = $targetDir . $hashName;
        
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to upload image');
        }
        
        // Перевіряємо, чи файл дійсно існує після переміщення
        if (!file_exists($targetPath)) {
            throw new Exception('File was not saved');
        }
        
        return $hashName;
    }
    
    /**
     * Видалити файл фото профілю
     */
    private function deleteProfilePhotoFile(string $photoName): void
    {
        if (empty($photoName) || $photoName === 'pfp.png') {
            return;
        }
        
        // Перевіряємо обидва місця (старий шлях та новий)
        $photoPath1 = __DIR__ . '/../../image/' . $photoName;
        $photoPath2 = __DIR__ . '/../../image/profile/' . $photoName;
        
        if (file_exists($photoPath1)) {
            @unlink($photoPath1);
        }
        if (file_exists($photoPath2)) {
            @unlink($photoPath2);
        }
    }

    /**
     * Оновити пароль
     */
    public function updatePassword(array $data): string
    {
        try {
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to update your password');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to update your password');
                return '';
            }
            
            // Валідація
            if (empty($data['new_password']) || empty($data['confirm_password'])) {
                ResponseService::redirectWithError('index.php?action=setting', 'Both password fields are required');
                return '';
            }
            
            if ($data['new_password'] !== $data['confirm_password']) {
                ResponseService::redirectWithError('index.php?action=setting', 'Passwords do not match');
                return '';
            }
            
            if (strlen($data['new_password']) < 6) {
                ResponseService::redirectWithError('index.php?action=setting', 'Password must be at least 6 characters long');
                return '';
            }
            
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $clientService = new ClientService($pdo);
            
            // Оновлюємо пароль
            $updateData = [
                'first_name' => '', // Потрібно для методу update, але не оновлюємо
                'last_name' => '',
                'email' => '',
                'phone_number' => '',
                'address' => '',
                'password' => $data['new_password']
            ];
            
            // Отримуємо поточні дані для збереження інших полів
            $user = $clientService->getById($userId);
            if ($user) {
                $updateData['first_name'] = $user['first_name'] ?? '';
                $updateData['last_name'] = $user['last_name'] ?? '';
                $updateData['email'] = $user['email'] ?? '';
                $updateData['phone_number'] = $user['phone_number'] ?? '';
                $updateData['address'] = $user['address'] ?? '';
            }
            
            $clientService->update($userId, $updateData);
            
            ResponseService::redirectWithSuccess('index.php?action=setting', 'Password updated successfully!');
            return '';
            
        } catch (Exception $e) {
            error_log("Error updating password: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=setting', 'Error updating password: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Оновити соціальний канал
     */
    public function updateSocialChannel(array $data): string
    {
        try {
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in');
                return '';
            }
            
            if (empty($data['platform']) || empty($data['handle'])) {
                ResponseService::redirectWithError('index.php?action=setting', 'Platform and handle are required');
                return '';
            }
            
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            
            // Перевіряємо, чи існує канал для цієї платформи
            $stmt = $pdo->prepare("SELECT * FROM social_channels WHERE client_id = :client_id AND platform = :platform LIMIT 1");
            $stmt->execute([
                ':client_id' => $userId,
                ':platform' => $data['platform']
            ]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Оновлюємо існуючий канал
                $stmt = $pdo->prepare("
                    UPDATE social_channels 
                    SET handle = :handle, url = :url, is_primary = :is_primary
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $existing['id'],
                    ':handle' => $data['handle'],
                    ':url' => !empty($data['url']) ? substr($data['url'], 0, 255) : null, // Обрізаємо до 255 символів
                    ':is_primary' => isset($data['is_primary']) ? 1 : 0
                ]);
            } else {
                // Створюємо новий канал
                $stmt = $pdo->prepare("
                    INSERT INTO social_channels (client_id, platform, handle, url, is_primary)
                    VALUES (:client_id, :platform, :handle, :url, :is_primary)
                ");
                $stmt->execute([
                    ':client_id' => $userId,
                    ':platform' => $data['platform'],
                    ':handle' => $data['handle'],
                    ':url' => !empty($data['url']) ? substr($data['url'], 0, 255) : null, // Обрізаємо до 255 символів
                    ':is_primary' => isset($data['is_primary']) ? 1 : 0
                ]);
            }
            
            ResponseService::redirectWithSuccess('index.php?action=setting', 'Social channel updated successfully!');
            return '';
            
        } catch (Exception $e) {
            error_log("Error updating social channel: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=setting', 'Error updating social channel: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Видалити соціальний канал
     */
    public function deleteSocialChannel(int $channelId): string
    {
        try {
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in');
                return '';
            }
            
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            
            // Перевіряємо, чи канал належить користувачу
            $stmt = $pdo->prepare("SELECT * FROM social_channels WHERE id = :id AND client_id = :client_id");
            $stmt->execute([':id' => $channelId, ':client_id' => $userId]);
            $channel = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$channel) {
                ResponseService::redirectWithError('index.php?action=setting', 'Social channel not found');
                return '';
            }
            
            // Видаляємо канал
            $stmt = $pdo->prepare("DELETE FROM social_channels WHERE id = :id");
            $stmt->execute([':id' => $channelId]);
            
            ResponseService::redirectWithSuccess('index.php?action=setting', 'Social channel deleted successfully!');
            return '';
            
        } catch (Exception $e) {
            error_log("Error deleting social channel: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=setting', 'Error deleting social channel: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Видалити акаунт користувача
     */
    public function deleteAccount(): string
    {
        try {
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in');
                return '';
            }
            
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $clientService = new ClientService($pdo);
            
            // Видаляємо фото, якщо є
            $user = $clientService->getById($userId);
            if ($user && !empty($user['photo']) && $user['photo'] !== 'pfp.png') {
                $baseDir = dirname(__DIR__, 2);
                $photoPath1 = $baseDir . '/image/profile/' . $user['photo'];
                $photoPath2 = $baseDir . '/image/' . $user['photo'];
                if (file_exists($photoPath1)) {
                    @unlink($photoPath1);
                }
                if (file_exists($photoPath2)) {
                    @unlink($photoPath2);
                }
            }
            
            // Видаляємо акаунт
            $clientService->delete($userId);
            
            // Виходимо з сесії
            AuthService::logout();
            
            ResponseService::redirectWithSuccess('index.php?action=autorization', 'Your account has been deleted successfully. We\'re sad to see you go!');
            return '';
            
        } catch (Exception $e) {
            error_log("Error deleting account: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=setting', 'Error deleting account: ' . $e->getMessage());
            return '';
        }
    }
    public function getProfileOrders(): string
    {
        try {
            // Перевіряємо, чи користувач залогінений
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to view your orders');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to view your orders');
                return '';
            }
            
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            
            // Отримуємо дані користувача та фото
            $clientService = new ClientService($pdo);
            $user = $clientService->getById($userId);
            
            $userPhoto = '/image/pfp.png';
            if (!empty($user['photo']) && $user['photo'] !== 'pfp.png' && $user['photo'] !== null) {
                $baseDir = dirname(__DIR__, 2);
                // Перевіряємо спочатку в папці profile, потім в старій папці image
                $photoPath = $baseDir . '/image/profile/' . $user['photo'];
                if (!file_exists($photoPath)) {
                    $photoPath = $baseDir . '/image/' . $user['photo'];
                }
                
                if (file_exists($photoPath)) {
                    // Визначаємо правильний URL в залежності від місця знаходження файлу
                    if (strpos($photoPath, '/image/profile/') !== false) {
                        $userPhoto = '/image/profile/' . $user['photo'] . '?t=' . filemtime($photoPath);
                    } else {
                        $userPhoto = '/image/' . $user['photo'] . '?t=' . filemtime($photoPath);
                    }
                } else {
                    error_log("Warning: Photo file not found for user ID " . $userId . ", photo: " . $user['photo'] . ", but keeping in DB");
                }
            }
            
            // 1. Monthly spend (поточний місяць)
            $monthlySpend = 0.00;
            try {
                $stmt = $pdo->prepare("
                    SELECT SUM(value) as total
                    FROM transactions
                    WHERE client_id = :client_id
                    AND YEAR(date) = YEAR(CURDATE())
                    AND MONTH(date) = MONTH(CURDATE())
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $monthlySpend = (float)($result['total'] ?? 0);
            } catch (\Exception $e) {
                error_log("Error calculating monthly spend: " . $e->getMessage());
            }
            
            // 2. Mini chart data (останні 30 днів по днях)
            $miniChartData = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT DATE(date) as day, SUM(value) as total
                    FROM transactions
                    WHERE client_id = :client_id
                    AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(date)
                    ORDER BY day ASC
                ");
                $stmt->execute([':client_id' => $userId]);
                $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Нормалізуємо дані для міні-графіку (14 точок)
                $dayCount = count($dailyData);
                if ($dayCount > 0) {
                    $step = max(1, floor($dayCount / 14));
                    for ($i = 0; $i < $dayCount; $i += $step) {
                        if (isset($dailyData[$i])) {
                            $miniChartData[] = (float)$dailyData[$i]['total'];
                        }
                    }
                    // Заповнюємо до 14 точок
                    while (count($miniChartData) < 14 && !empty($dailyData)) {
                        $miniChartData[] = (float)end($dailyData)['total'];
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching mini chart data: " . $e->getMessage());
            }
            
            // 3. Avg order value
            $avgOrderValue = 0.00;
            try {
                $stmt = $pdo->prepare("
                    SELECT AVG(value) as avg_value
                    FROM transactions
                    WHERE client_id = :client_id
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $avgOrderValue = (float)($result['avg_value'] ?? 0);
            } catch (\Exception $e) {
                error_log("Error calculating avg order value: " . $e->getMessage());
            }
            
            // 4. Peak weekday
            $peakWeekday = 'N/A';
            try {
                $stmt = $pdo->prepare("
                    SELECT DAYNAME(date) as weekday, COUNT(*) as count
                    FROM transactions
                    WHERE client_id = :client_id
                    GROUP BY DAYNAME(date)
                    ORDER BY count DESC
                    LIMIT 1
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['weekday'])) {
                    $peakWeekday = $result['weekday'];
                }
            } catch (\Exception $e) {
                error_log("Error finding peak weekday: " . $e->getMessage());
            }
            
            // 5. Total transactions amount (виключаємо Reject та Cancel, бо вони скасовані)
            $totalTransactionsAmount = 0.00;
            try {
                $stmt = $pdo->prepare("
                    SELECT SUM(value) as total
                    FROM transactions
                    WHERE client_id = :client_id
                    AND status NOT IN ('Reject', 'Cancel', 'reject', 'cancel', 'Rejected', 'Cancelled')
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalTransactionsAmount = (float)($result['total'] ?? 0);
            } catch (\Exception $e) {
                error_log("Error calculating total transactions amount: " . $e->getMessage());
            }
            
            // 6. Total orders count (останні 90 днів)
            $totalOrdersCount = 0;
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM orders
                    WHERE client_id = :client_id
                    AND id IN (
                        SELECT DISTINCT o.id
                        FROM orders o
                        JOIN transactions t ON t.client_id = o.client_id
                        WHERE o.client_id = :client_id
                        AND t.date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    )
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalOrdersCount = (int)($result['count'] ?? 0);
                
                // Якщо немає зв'язку, просто рахуємо замовлення
                if ($totalOrdersCount == 0) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count
                        FROM orders
                        WHERE client_id = :client_id
                    ");
                    $stmt->execute([':client_id' => $userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalOrdersCount = (int)($result['count'] ?? 0);
                }
            } catch (\Exception $e) {
                error_log("Error counting orders: " . $e->getMessage());
            }
            
            // 7. Settled invoices (Completed)
            $settledInvoices = 0;
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM transactions
                    WHERE client_id = :client_id
                    AND status = 'Complete'
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $settledInvoices = (int)($result['count'] ?? 0);
            } catch (\Exception $e) {
                error_log("Error counting settled invoices: " . $e->getMessage());
            }
            
            // 8. Spending trend (по місяцях за останні 12 місяців)
            $spendingTrend = [];
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        YEAR(date) as year,
                        MONTH(date) as month,
                        SUM(value) as total
                    FROM transactions
                    WHERE client_id = :client_id
                    AND date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY YEAR(date), MONTH(date)
                    ORDER BY year ASC, month ASC
                ");
                $stmt->execute([':client_id' => $userId]);
                $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Заповнюємо масив для останніх 12 місяців
                for ($i = 11; $i >= 0; $i--) {
                    $date = date('Y-m', strtotime("-$i months"));
                    $year = (int)date('Y', strtotime($date));
                    $month = (int)date('n', strtotime($date));
                    $monthName = $monthNames[$month - 1];
                    
                    $found = false;
                    foreach ($monthlyData as $data) {
                        if ((int)$data['year'] == $year && (int)$data['month'] == $month) {
                            $spendingTrend[] = [
                                'label' => $monthName,
                                'value' => (float)$data['total']
                            ];
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        $spendingTrend[] = [
                            'label' => $monthName,
                            'value' => 0
                        ];
                    }
                }
            } catch (\Exception $e) {
                error_log("Error fetching spending trend: " . $e->getMessage());
                // Заповнюємо пустими даними
                for ($i = 11; $i >= 0; $i--) {
                    $date = date('n', strtotime("-$i months"));
                    $spendingTrend[] = [
                        'label' => $monthNames[$date - 1],
                        'value' => 0
                    ];
                }
            }
            
            // 9. Conversion rate (поточний місяць)
            $conversionRate = 0;
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as completed
                    FROM transactions
                    WHERE client_id = :client_id
                    AND YEAR(date) = YEAR(CURDATE())
                    AND MONTH(date) = MONTH(CURDATE())
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $total = (int)($result['total'] ?? 0);
                $completed = (int)($result['completed'] ?? 0);
                if ($total > 0) {
                    $conversionRate = round(($completed / $total) * 100);
                }
            } catch (\Exception $e) {
                error_log("Error calculating conversion rate: " . $e->getMessage());
            }
            
            // 10. Pending volume
            $pendingVolume = 0.00;
            try {
                $stmt = $pdo->prepare("
                    SELECT SUM(value) as total
                    FROM transactions
                    WHERE client_id = :client_id
                    AND status = 'Pending'
                ");
                $stmt->execute([':client_id' => $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $pendingVolume = (float)($result['total'] ?? 0);
            } catch (\Exception $e) {
                error_log("Error calculating pending volume: " . $e->getMessage());
            }
            
            // 11. Recent transactions (останні 5, або всі якщо show_all=1)
            $showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';
            $limit = $showAll ? 1000 : 5;
            $transactions = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT t.*,
                           COALESCE(SUM(o.quantity), 0) as total_quantity
                    FROM transactions t
                    LEFT JOIN transaction_order to_rel ON to_rel.transaction_id = t.id
                    LEFT JOIN orders o ON to_rel.order_id = o.id
                    WHERE t.client_id = :client_id
                    GROUP BY t.id
                    ORDER BY t.id DESC
                    LIMIT :limit
                ");
                $stmt->bindValue(':client_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $transactionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($transactionData as $tx) {
                    $status = $tx['status'] ?? 'Pending';
                    $statusClass = 'alert'; // Pending по замовчуванню
                    
                    $statusLower = strtolower($status);
                    if ($statusLower == 'completed' || $statusLower == 'complete') {
                        $statusClass = 'success';
                    } elseif ($statusLower == 'failed') {
                        $statusClass = 'failed';
                    } elseif ($statusLower == 'reject' || $statusLower == 'rejected') {
                        $statusClass = 'reject';
                    } elseif ($statusLower == 'cancel' || $statusLower == 'cancelled') {
                        $statusClass = 'reject'; // Cancel також червоний
                    } elseif ($statusLower == 'pending') {
                        $statusClass = 'alert';
                    }
                    
                    $totalQuantity = (int)($tx['total_quantity'] ?? 0);
                    
                    $transactions[] = [
                        'hash' => '#' . str_pad($tx['id'], 4, '0', STR_PAD_LEFT),
                        'date' => date('d M Y', strtotime($tx['date'])),
                        'items' => $totalQuantity . ' item' . ($totalQuantity != 1 ? 's' : ''),
                        'total' => number_format((float)$tx['value'], 2, '.', ','),
                        'status' => $status,
                        'statusClass' => $statusClass,
                        'description' => $tx['description'] ?? 'Purchase'
                    ];
                }
            } catch (\Exception $e) {
                error_log("Error fetching recent transactions: " . $e->getMessage());
            }
            
            return $this->templateService->render('pages/profileOrders', [
                "homeNavBar" => 1,
                "footer" => null,
                "userPhoto" => $userPhoto,
                "monthlySpend" => $monthlySpend,
                "miniChartData" => $miniChartData,
                "avgOrderValue" => $avgOrderValue,
                "peakWeekday" => $peakWeekday,
                "totalTransactionsAmount" => $totalTransactionsAmount,
                "totalOrdersCount" => $totalOrdersCount,
                "settledInvoices" => $settledInvoices,
                "spendingTrend" => $spendingTrend,
                "conversionRate" => $conversionRate,
                "pendingVolume" => $pendingVolume,
                "transactions" => $transactions
            ]);
        } catch (Exception $e) {
            error_log("Error loading profile orders: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=autorization', 'Error loading orders. Please try again.');
            return '';
        }
    }
    public function getCart(): string
    {
        try {
            // Отримуємо всі рецепти з бази даних для відображення інформації про товари в кошику
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $recipeService = new RecipeService($pdo);
            $allRecipes = $recipeService->getAllForSalesPage();
            
            // Створюємо мапу рецептів по ID для швидкого доступу
            $recipesMap = [];
            foreach ($allRecipes as $recipe) {
                // Встановлюємо дефолтне фото, якщо немає
                if (empty($recipe['photo']) || $recipe['photo'] === null) {
                    $recipe['photo'] = 'default.png';
                } else {
                    // Перевіряємо, чи існує файл фото
                    $photoPath = dirname(__DIR__, 2) . '/image/recipes/' . $recipe['photo'];
                    if (!file_exists($photoPath)) {
                        $recipe['photo'] = 'default.png';
                    }
                }
                $recipesMap[$recipe['id']] = $recipe;
            }
            
            // Отримуємо інформацію про користувача (якщо залогінений)
            $userId = null;
            $userPhoto = '/image/pfp.png';
            
            if (AuthService::isLoggedIn()) {
                $userId = AuthService::getUserId();
                if ($userId) {
                    $clientService = new ClientService($pdo);
                    $user = $clientService->getById($userId);
                    
                    if ($user && !empty($user['photo']) && $user['photo'] !== 'pfp.png' && $user['photo'] !== null) {
                        $baseDir = dirname(__DIR__, 2);
                        $photoPath = $baseDir . '/image/profile/' . $user['photo'];
                        if (!file_exists($photoPath)) {
                            $photoPath = $baseDir . '/image/' . $user['photo'];
                        }
                        
                        if (file_exists($photoPath)) {
                            if (strpos($photoPath, '/image/profile/') !== false) {
                                $userPhoto = '/image/profile/' . $user['photo'] . '?t=' . filemtime($photoPath);
                            } else {
                                $userPhoto = '/image/' . $user['photo'] . '?t=' . filemtime($photoPath);
                            }
                        }
                    }
                }
            }
            
            return $this->templateService->render('pages/cartPage', [
                "homeNavBar" => 1, 
                "footer" => null,
                "recipesMap" => $recipesMap,
                "userId" => $userId,
                "userPhoto" => $userPhoto
            ]);
        } catch (Exception $e) {
            error_log("Error loading cart: " . $e->getMessage());
            return $this->templateService->render('pages/cartPage', [
                "homeNavBar" => 1, 
                "footer" => null,
                "recipesMap" => [],
                "userId" => null,
                "userPhoto" => '/image/pfp.png'
            ]);
        }
    }
    public function checkout(array $data): string
    {
        try {
            // Перевіряємо, чи користувач залогінений
            if (!AuthService::isLoggedIn()) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to complete your purchase');
                return '';
            }
            
            $userId = AuthService::getUserId();
            if (!$userId) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Please log in to complete your purchase');
                return '';
            }
            
            // Отримуємо дані з POST запиту
            $cartJson = $data['cart'] ?? null;
            $discountRate = isset($data['discount_rate']) ? (float)$data['discount_rate'] : 0.0;
            
            if (!$cartJson) {
                ResponseService::redirectWithError('index.php?action=cart', 'Cart is empty');
                return '';
            }
            
            $cart = json_decode($cartJson, true);
            if (!is_array($cart) || empty($cart)) {
                ResponseService::redirectWithError('index.php?action=cart', 'Cart is empty');
                return '';
            }
            
            // Підключаємося до бази даних
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            
            // Отримуємо першого staff для транзакції
            $stmt = $pdo->query("SELECT id FROM staff ORDER BY id ASC LIMIT 1");
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            $staffId = $staff ? (int)$staff['id'] : 1; // Якщо немає staff, використовуємо 1
            
            // Отримуємо рецепти та розраховуємо загальну суму
            $recipeService = new RecipeService($pdo);
            $allRecipes = $recipeService->getAllForSalesPage();
            $recipesMap = [];
            foreach ($allRecipes as $recipe) {
                $recipesMap[$recipe['id']] = $recipe;
            }
            
            $subtotal = 0;
            $deliveryFee = 50.00;
            
            // Розраховуємо subtotal
            foreach ($cart as $item) {
                $recipeId = (int)$item['id'];
                $quantity = (int)$item['quantity'];
                
                if (isset($recipesMap[$recipeId])) {
                    $price = (float)$recipesMap[$recipeId]['price'];
                    $subtotal += $price * $quantity;
                }
            }
            
            // Застосовуємо знижку
            $discount = $subtotal * $discountRate;
            $finalTotal = $subtotal - $discount + $deliveryFee;
            
            // Починаємо транзакцію в базі даних
            $pdo->beginTransaction();
            
            try {
                // Створюємо транзакцію (статус 'Pending' - менеджер змінить на 'Completed')
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (value, currency, date, status, description, staff_id, client_id)
                    VALUES (:value, :currency, CURDATE(), 'Pending', :description, :staff_id, :client_id)
                ");
                
                $description = "Purchase of " . count($cart) . " item(s)";
                if ($discountRate > 0) {
                    $discountPercent = round($discountRate * 100);
                    $description .= " with {$discountPercent}% discount";
                }
                
                $stmt->execute([
                    ':value' => $finalTotal,
                    ':currency' => 'USD',
                    ':description' => $description,
                    ':staff_id' => $staffId,
                    ':client_id' => $userId
                ]);
                
                $transactionId = $pdo->lastInsertId();
                
                // Створюємо ордери для кожного товару в кошику та зв'язуємо їх з транзакцією
                $orderStmt = $pdo->prepare("
                    INSERT INTO orders (quantity, client_id, recipe_id)
                    VALUES (:quantity, :client_id, :recipe_id)
                ");
                
                $linkStmt = $pdo->prepare("
                    INSERT INTO transaction_order (transaction_id, order_id)
                    VALUES (:transaction_id, :order_id)
                ");
                
                foreach ($cart as $item) {
                    $recipeId = (int)$item['id'];
                    $quantity = (int)$item['quantity'];
                    
                    if (isset($recipesMap[$recipeId])) {
                        $orderStmt->execute([
                            ':quantity' => $quantity,
                            ':client_id' => $userId,
                            ':recipe_id' => $recipeId
                        ]);
                        
                        $orderId = $pdo->lastInsertId();
                        
                        // Зв'язуємо замовлення з транзакцією
                        $linkStmt->execute([
                            ':transaction_id' => $transactionId,
                            ':order_id' => $orderId
                        ]);
                    }
                }
                
                // Підтверджуємо транзакцію
                $pdo->commit();
                
                // Очищаємо кошик (видаляємо cookie)
                if (isset($_COOKIE['shopping_cart'])) {
                    setcookie('shopping_cart', '', time() - 3600, '/');
                }
                
                // Перенаправляємо на сторінку з товарами з повідомленням про успіх
                ResponseService::redirectWithSuccess('index.php?action=salesPage', 'Order placed successfully! Thank you for your purchase.');
                return '';
                
            } catch (\Exception $e) {
                // Відкатуємо транзакцію при помилці
                $pdo->rollBack();
                error_log("Error during checkout: " . $e->getMessage());
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Checkout error: " . $e->getMessage());
            ResponseService::redirectWithError('index.php?action=cart', 'Error processing your order. Please try again.');
            return '';
        }
    }
    
    public function autorization(): string
    {
        $error = isset($_GET['error']) ? $_GET['error'] : null;
        $success = isset($_GET['success']) ? $_GET['success'] : null;
        return $this->templateService->render('pages/signPage', [
            "homeNavBar" => null, 
            "footer" => null,
            "error" => $error,
            "success" => $success
        ]);
    }

    /**
     * Обробка реєстрації (Sign Up)
     */
    public function signUp(array $data): string
    {
        try {
            // Валідація обов'язкових полів
            if (empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
                ResponseService::redirectWithError('index.php?action=autorization', 'All required fields must be filled');
                return '';
            }

            // Розділяємо full_name на first_name і last_name
            $nameParts = explode('-', $data['full_name'], 2);
            $firstName = trim($nameParts[0]);
            $lastName = isset($nameParts[1]) ? trim($nameParts[1]) : null;

            if (empty($firstName)) {
                ResponseService::redirectWithError('index.php?action=autorization', 'First name is required');
                return '';
            }

            // Перевірка email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Invalid email format');
                return '';
            }

            // Перевірка мінімальної довжини пароля
            if (strlen($data['password']) < 6) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Password must be at least 6 characters long');
                return '';
            }

            // Створюємо сервіс для роботи з клієнтами
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $clientService = new ClientService($pdo);

            // Перевіряємо, чи email вже існує
            $existingUser = $clientService->getByEmail($data['email']);
            if ($existingUser) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Email already registered');
                return '';
            }

            // Створюємо нового користувача
            $clientData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $data['email'],
                'password' => $data['password'], // ClientService сам захешує пароль
                'phone_number' => !empty($data['phone_number']) ? $data['phone_number'] : null,
            ];

            $userId = $clientService->create($clientData);

            // Автоматично логінуємо користувача після реєстрації
            AuthService::login($userId, $data['email'], $firstName, $lastName);

            // Перенаправляємо на головну сторінку
            ResponseService::redirectWithSuccess('index.php?action=salesPage', 'Registration successful! Welcome!');
            return '';

        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=autorization', 'Registration error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Обробка входу (Sign In)
     */
    public function signIn(array $data): string
    {
        try {
            // Валідація обов'язкових полів
            if (empty($data['email']) || empty($data['password'])) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Email and password are required');
                return '';
            }

            // Створюємо сервіс для роботи з клієнтами
            $sqlService = new SQLService();
            $pdo = $sqlService->getPdo();
            $clientService = new ClientService($pdo);

            // Перевіряємо пароль
            $user = $clientService->verifyPassword($data['email'], $data['password']);
            
            if (!$user) {
                ResponseService::redirectWithError('index.php?action=autorization', 'Invalid email or password');
                return '';
            }

            // Логінуємо користувача
            AuthService::login(
                (int)$user['id'],
                $user['email'],
                $user['first_name'],
                $user['last_name'] ?? null
            );

            // Перенаправляємо на головну сторінку
            ResponseService::redirectWithSuccess('index.php?action=salesPage', 'Login successful! Welcome back!');
            return '';

        } catch (Exception $e) {
            ResponseService::redirectWithError('index.php?action=autorization', 'Login error: ' . $e->getMessage());
            return '';
        }
    }
}