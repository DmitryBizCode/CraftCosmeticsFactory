<?php

namespace App\Services;

use App\Controllers\ContainerController;
use App\Controllers\EcommerceController;
class Router
{
    private ?ContainerController $containerController = null;
    private EcommerceController $ecommerceController;
    public function __construct()
    {
        $this->ecommerceController = new EcommerceController();
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
            case 'sign':
                if ($method == 'POST') {
                    //$this->getContainerController()->create($data, $image);
                    break;
                } else {
                    $content = $this->getContainerController()->getSign();
                    break;
                }
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
                // if ($method == 'POST') {
                //     $content = $this->getContainerController()->setting($id,$data);
                //     break;
                // } else {
                //     $content = $this->getContainerController()->getSetting($id,$data);
                //     break;
                // }
            // no break
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
            // no break
            default:
                $content = $this->ecommerceController->getHome();
                break;
        }
        echo $content;
    }
}