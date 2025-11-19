<?php

namespace App\Services;

use App\Controllers\ContainerController;
use App\Controllers\EcommerceController;
use App\Controllers\AdminController;

class Router
{
    private ?ContainerController $containerController = null;
    private EcommerceController $ecommerceController;
    private AdminController $adminController;
    public function __construct()
    {
        $this->ecommerceController = new EcommerceController();
        $this->adminController = new AdminController();
    }
    
    private function getContainerController(): ContainerController
    {
        if ($this->containerController === null) {
            $this->containerController = new ContainerController();
        }
        return $this->containerController;
    }

    public function route(string $action = null, string $id = null, string $method = 'GET', array $data = [], array $image = [], bool $sign = null, int $id_containers = null): void
    {
        switch ($action) {
            case 'autorization':
                $content = $this->ecommerceController->autorization();
                break;
            case 'admin':
                // Вхід в адмінку тільки по URL: index.php?action=admin (сторінка логіну)
                $content = $this->adminController->getAdminLogin();
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
            case 'adminIotStats':
                $recipeId = isset($_GET['recipe_id']) ? (int)$_GET['recipe_id'] : 0;
                $content = $this->adminController->getAdminIotStats($recipeId);
                break;
            case 'adminOrders':
                $content = $this->adminController->getAdminOrders();
                break;
            case 'adminStats':
                $content = $this->adminController->getAdminStats();
                break;
            case 'adminUsers':
                $content = $this->adminController->getAdminUsers();
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
                    $this->getContainerController()->сontactMail($data['email'], $data['name'], $data['message']);
                    break;
                } else {
                    $content = $this->getContainerController()->getContact();
                    break;
                }
            // no break
            case 'services':
                if ($method == 'POST') {
                    //$this->getContainerController()->delete($id);
                    break;
                } else {
                    $content = $this->getContainerController()->getServices();
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
                    $content = $this->getContainerController()->getOrders($id,$data);
                    break;
                }
            // no break
            case 'setting':
                $content = $this->ecommerceController->getProfileSetting();
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
                $content = $this->ecommerceController->getCart();
                break;

            case 'rental':
                if ($method == 'POST') {
                    $content = $this->getContainerController()->rental($id,$data);
                    break;
                } else {
                    $content = $this->getContainerController()->getRental($id,$data);
                    break;
                }
            case 'detail':
                if ($method == 'POST') {
                    $content = $this->getContainerController()->detail($id,$data,$id_containers);
                    break;
                } else {
                    $content = $this->getContainerController()->getDetail($id,$data,$id_containers);
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
        echo $content;
    }
}