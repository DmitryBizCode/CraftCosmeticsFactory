<?php

namespace App\Services\Common;

use App\Controllers\EcommerceController;
use App\Controllers\AdminController;

class Router
{
    private EcommerceController $ecommerceController;
    private AdminController $adminController;
    public function __construct()
    {
        $this->ecommerceController = new EcommerceController();
        $this->adminController = new AdminController();
    }

    public function route(?string $action = null, ?string $id = null, string $method = 'GET', array $data = [], array $image = [], ?bool $sign = null, ?int $id_containers = null): void
    {
        $content = '';
        switch ($action) {
            case 'autorization':
                if ($method == 'POST') {
                    // Визначаємо, яка форма була відправлена
                    if (isset($data['full_name'])) {
                        // Sign Up форма
                        $content = $this->ecommerceController->signUp($data);
                    } else {
                        // Sign In форма
                        $content = $this->ecommerceController->signIn($data);
                    }
                } else {
                    $content = $this->ecommerceController->autorization();
                }
                break;
            case 'signUp':
                if ($method == 'POST') {
                    $content = $this->ecommerceController->signUp($data);
                } else {
                    $content = $this->ecommerceController->autorization();
                }
                break;
            case 'signIn':
                if ($method == 'POST') {
                    $content = $this->ecommerceController->signIn($data);
                } else {
                    $content = $this->ecommerceController->autorization();
                }
                break;
            case 'admin':
                if ($method == 'POST') {
                    $content = $this->adminController->adminLogin($data);
                } else {
                    $content = $this->adminController->getAdminLogin();
                }
                break;
            case 'adminProducts':
                if ($method == 'POST') {
                    $productId = isset($data['id']) && !empty($data['id']) ? (int)$data['id'] : 0;
                    if ($productId > 0) {
                        $content = $this->adminController->updateProduct($productId, $data);
                    } else {
                        $content = $this->adminController->createProduct($data);
                    }
                } else {
                    $content = $this->adminController->getAdminProducts();
                }
                break;
            case 'adminProductDelete':
                $productId = isset($id) ? (int)$id : 0;
                if ($productId > 0) {
                    $content = $this->adminController->deleteProduct($productId);
                } else {
                    $content = $this->adminController->getAdminProducts();
                }
                break;
            case 'adminRecipes':
                if ($method == 'POST') {
                    $recipeId = isset($data['id']) && !empty($data['id']) ? (int)$data['id'] : 0;
                    if ($recipeId > 0) {
                        $content = $this->adminController->updateRecipe($recipeId, $data, $image);
                    } else {
                        $content = $this->adminController->createRecipe($data, $image);
                    }
                } else {
                    $content = $this->adminController->getAdminRecipes();
                }
                break;
            case 'adminRecipeDelete':
                $recipeId = isset($id) ? (int)$id : 0;
                if ($recipeId > 0) {
                    $content = $this->adminController->deleteRecipe($recipeId);
                } else {
                    $content = $this->adminController->getAdminRecipes();
                }
                break;
            case 'adminRecipeDeletePhoto':
                $recipeId = isset($id) ? (int)$id : 0;
                if ($recipeId > 0) {
                    $content = $this->adminController->deleteRecipePhotoAction($recipeId);
                } else {
                    $content = $this->adminController->getAdminRecipes();
                }
                break;
            case 'adminEcoSystem':
                $recipeId = isset($_GET['recipe_id']) ? (int)$_GET['recipe_id'] : 0;
                $content = $this->adminController->getAdminEcoSystem($recipeId);
                break;
            case 'adminEcoSystemGenerate':
                // API endpoint для генерації нового значення
                $iotEcoId = isset($_GET['iot_eco_id']) ? (int)$_GET['iot_eco_id'] : 0;
                $content = $this->adminController->generateIotValue($iotEcoId);
                break;
            case 'adminEcoSystemSetValue':
                // API endpoint для встановлення значення вручну
                if ($method === 'POST') {
                    $iotEcoId = isset($data['iot_eco_id']) ? (int)$data['iot_eco_id'] : 0;
                    $value = isset($data['value']) ? (float)$data['value'] : 0;
                    $content = $this->adminController->setIotValue($iotEcoId, $value);
                }
                break;
            case 'adminEcoSystemGetData':
                // API endpoint для отримання даних для графіків
                $iotEcoId = isset($_GET['iot_eco_id']) ? (int)$_GET['iot_eco_id'] : 0;
                $content = $this->adminController->getIotData($iotEcoId);
                break;
            case 'adminEcoSystemToggle':
                // API endpoint для запуску/зупинки eco_system
                if ($method === 'POST') {
                    $ecoSystemId = isset($data['eco_system_id']) ? (int)$data['eco_system_id'] : 0;
                    $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : false;
                    $content = $this->adminController->toggleEcoSystem($ecoSystemId, $isActive);
                }
                break;
            case 'adminEcoSystemUpdateDescription':
                // API endpoint для оновлення опису eco_system
                if ($method === 'POST') {
                    $ecoSystemId = isset($data['eco_system_id']) ? (int)$data['eco_system_id'] : 0;
                    $description = isset($data['description']) ? (string)$data['description'] : '';
                    $content = $this->adminController->updateEcoSystemDescription($ecoSystemId, $description);
                }
                break;
            case 'adminEcoSystemUpdateStaff':
                // API endpoint для оновлення staff_id eco_system
                if ($method === 'POST') {
                    $ecoSystemId = isset($data['eco_system_id']) ? (int)$data['eco_system_id'] : 0;
                    $staffId = isset($data['staff_id']) && !empty($data['staff_id']) ? (int)$data['staff_id'] : null;
                    $content = $this->adminController->updateEcoSystemStaff($ecoSystemId, $staffId);
                }
                break;
            case 'adminIotStats':
                $recipeId = isset($_GET['recipe_id']) ? (int)$_GET['recipe_id'] : 0;
                $content = $this->adminController->getAdminIotStats($recipeId);
                break;
            case 'adminOrders':
                if ($method == 'POST' && isset($data['action']) && $data['action'] === 'update_status') {
                    $content = $this->adminController->updateOrderStatus($data);
                } else {
                    $content = $this->adminController->getAdminOrders();
                }
                break;
            case 'adminStats':
                $content = $this->adminController->getAdminStats();
                break;
            case 'adminUsers':
                if ($method == 'POST') {
                    // Визначаємо тип: client або staff
                    $type = isset($data['type']) ? $data['type'] : 'client';
                    $itemId = isset($data['id']) && !empty($data['id']) ? (int)$data['id'] : 0;
                    
                    if ($type === 'staff') {
                        if ($itemId > 0) {
                            $content = $this->adminController->updateStaff($itemId, $data);
                        } else {
                            $content = $this->adminController->createStaff($data);
                        }
                    } else {
                        if ($itemId > 0) {
                            $content = $this->adminController->updateClient($itemId, $data);
                        } else {
                            $content = $this->adminController->createClient($data);
                        }
                    }
                } else {
                    $content = $this->adminController->getAdminUsers();
                }
                break;
            case 'adminClientDelete':
                $clientId = isset($id) ? (int)$id : 0;
                if ($clientId > 0) {
                    $content = $this->adminController->deleteClient($clientId);
                } else {
                    $content = $this->adminController->getAdminUsers();
                }
                break;
            case 'adminStaffDelete':
                $staffId = isset($id) ? (int)$id : 0;
                if ($staffId > 0) {
                    $content = $this->adminController->deleteStaff($staffId);
                } else {
                    $content = $this->adminController->getAdminUsers();
                }
                break;
            case 'adminIot':
                if ($method == 'POST') {
                    $iotId = isset($data['id']) && !empty($data['id']) ? (int)$data['id'] : 0;
                    if ($iotId > 0) {
                        $content = $this->adminController->updateIotSensor($iotId, $data);
                    } else {
                        $content = $this->adminController->createIotSensor($data);
                    }
                } else {
                    $content = $this->adminController->getAdminIot();
                }
                break;
            case 'adminIotDelete':
                $iotId = isset($id) ? (int)$id : 0;
                if ($iotId > 0) {
                    $content = $this->adminController->deleteIotSensor($iotId);
                } else {
                    $content = $this->adminController->getAdminIot();
                }
                break;
            // case 'signin':
            //     $content = $this->ecommerceController->autorizationSignIn();
            //     break;
            // case 'signup':
            //     $content = $this->ecommerceController->autorizationSignUp();
            //     break;

            // no break
            case 'contact':
                if ($method == 'POST') {
                    // $this->getContainerController()->сontactMail($data['email'], $data['name'], $data['message']);
                    break;
                } else {
                    // $content = $this->getContainerController()->getContact();
                    break;
                }
            // no break
            case 'services':
                if ($method == 'POST') {
                    //$this->getContainerController()->delete($id);
                    break;
                } else {
                    // $content = $this->getContainerController()->getServices();
                    break;
                }
            // no break
            case 'profile':
                $content = $this->ecommerceController->getProfile();
                break;
            case 'orders':
                if ($method == 'POST') {
                    //$content = $this->getContainerController()->getProfileRequest($sign,$data);
                    break;
                } else {
                    // $content = $this->getContainerController()->getOrders($id,$data);
                    break;
                }
            // no break
            case 'setting':
                if ($method == 'POST') {
                    // Визначаємо тип оновлення
                    if (isset($data['update_type'])) {
                        switch ($data['update_type']) {
                            case 'profile':
                                $content = $this->ecommerceController->updateProfile($data, $image);
                                break;
                            case 'password':
                                $content = $this->ecommerceController->updatePassword($data);
                                break;
                            case 'social':
                                $content = $this->ecommerceController->updateSocialChannel($data);
                                break;
                            default:
                                $content = $this->ecommerceController->getProfileSetting();
                                break;
                        }
                    } else {
                        // За замовчуванням оновлюємо профіль
                        $content = $this->ecommerceController->updateProfile($data, $image);
                    }
                } else {
                    $content = $this->ecommerceController->getProfileSetting();
                }
                break;
            case 'deleteSocialChannel':
                if ($method == 'POST' || $method == 'GET') {
                    $channelId = isset($id) ? (int)$id : (isset($data['channel_id']) ? (int)$data['channel_id'] : 0);
                    if ($channelId > 0) {
                        $content = $this->ecommerceController->deleteSocialChannel($channelId);
                    } else {
                        $content = $this->ecommerceController->getProfileSetting();
                    }
                } else {
                    $content = $this->ecommerceController->getProfileSetting();
                }
                break;
            case 'deleteAccount':
                if ($method == 'POST') {
                    $content = $this->ecommerceController->deleteAccount();
                } else {
                    $content = $this->ecommerceController->getProfileSetting();
                }
                break;
            case 'ordersProfile':
                $content = $this->ecommerceController->getProfileOrders();
                break;
                // if ($method == 'POST') {
                //     $content = $this->getContainerController()->setting($id,$data);
                //     break;
                // } else {
                //     $content = $this->getContainerController()->getSetting($id,$data);
                //     break;
                // }
            // no break
            case 'cart':
                if ($method == 'POST') {
                    $content = $this->ecommerceController->checkout($data);
                } else {
                    $content = $this->ecommerceController->getCart();
                }
                break;
            case 'checkout':
                if ($method == 'POST') {
                    $content = $this->ecommerceController->checkout($data);
                } else {
                    ResponseService::redirectWithError('index.php?action=cart', 'Please go to cart page first');
                }
                break;

            case 'rental':
                if ($method == 'POST') {
                    // $content = $this->getContainerController()->rental($id,$data);
                    break;
                } else {
                    // $content = $this->getContainerController()->getRental($id,$data);
                    break;
                }
            case 'detail':
                if ($method == 'POST') {
                    // $content = $this->getContainerController()->detail($id,$data,$id_containers);
                    break;
                } else {
                    // $content = $this->getContainerController()->getDetail($id,$data,$id_containers);
                    break;
                }
            case 'salesPage':
                $content = $this->ecommerceController->getEcommerceHomePage();
                break;
            default:
                $content = $this->ecommerceController->autorization();
                break;
                // $content = $this->ecommerceController->getHome();
                // break;
        }
        
        // Виводимо content тільки якщо він не порожній (не був redirect)
        if (isset($content) && $content !== '') {
            echo $content;
        }
    }
}