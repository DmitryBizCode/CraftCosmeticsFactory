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
            $itemsPerPage = 12; // Товарів на сторінку
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
                "itemsPerPage" => 12,
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
            
            // Отримуємо замовлення з рецептами
            $orders = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT o.*, r.name as recipe_name, r.price as recipe_price, r.photo as recipe_photo
                    FROM orders o
                    LEFT JOIN recipes r ON o.recipe_id = r.id
                    WHERE o.client_id = :client_id
                    ORDER BY o.id DESC
                    LIMIT 5
                ");
                $stmt->execute([':client_id' => $userId]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                error_log("Error fetching orders: " . $e->getMessage());
                $orders = [];
            }
            
            // Форматуємо замовлення для відображення
            $formattedOrders = [];
            foreach ($orders as $order) {
                $orderTotal = 0.00;
                if (!empty($order['recipe_price']) && !empty($order['quantity'])) {
                    $orderTotal = (float)$order['recipe_price'] * (int)$order['quantity'];
                }
                
                // Форматуємо дату (якщо немає created_at, використовуємо id для приблизної дати)
                $orderDate = 'N/A';
                if (!empty($order['created_at'])) {
                    $orderDate = date('d M Y', strtotime($order['created_at']));
                } elseif (!empty($order['id'])) {
                    // Якщо немає created_at, використовуємо поточну дату мінус кілька днів на основі id
                    $daysAgo = (int)$order['id'] % 30; // Приблизно 0-29 днів тому
                    $orderDate = date('d M Y', strtotime("-{$daysAgo} days"));
                }
                
                $formattedOrders[] = [
                    'id' => $order['id'],
                    'order_id' => strtoupper(substr(md5($order['id']), 0, 4)),
                    'date' => $orderDate,
                    'quantity' => (int)$order['quantity'],
                    'total' => $orderTotal,
                    'status' => 'Completed', // За замовчуванням
                    'description' => !empty($order['recipe_name']) ? $order['recipe_name'] : 'N/A',
                    'recipe_id' => $order['recipe_id'] ?? null
                ];
            }
            
            // Розраховуємо середній кошик
            $averageBasket = 0.00;
            if (!empty($formattedOrders)) {
                $total = 0;
                $count = 0;
                foreach ($formattedOrders as $order) {
                    if ($order['total'] > 0) {
                        $total += $order['total'];
                        $count++;
                    }
                }
                if ($count > 0) {
                    $averageBasket = round($total / $count, 2);
                }
            }
            
            // Отримуємо останнє замовлення
            $lastOrder = !empty($formattedOrders) ? $formattedOrders[0] : null;
            
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
                "careReminders" => $careReminders
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
        return $this->templateService->render('pages/profileOrders', ["homeNavBar" => 1, "footer" => null]);
    }
    public function getCart(): string
    {
        return $this->templateService->render('pages/cartPage', ["homeNavBar" => 1, "footer" => null]);
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