<?php

namespace App\Services;

use App\Controllers\ContainerController;

class Router
{
    private ContainerController $containerController;

    public function __construct()
    {
        $this->containerController = new ContainerController();
    }

    public function route(string $action = null, string $id = null, string $method = 'GET', array $data = [], array $image = [], bool $sign = null, int $id_containers = null): void
    {
        switch ($action) {
            case 'sign':
                if ($method == 'POST') {
                    //$this->containerController->create($data, $image);
                    break;
                } else {
                    $content = $this->containerController->getSign();
                    break;
                }
            // no break
            case 'contact':
                if ($method == 'POST') {
                    $this->containerController->сontactMail($data['email'], $data['name'], $data['message']);
                    break;
                } else {
                    $content = $this->containerController->getContact();
                    break;
                }
            // no break
            case 'services':
                if ($method == 'POST') {
                    //$this->containerController->delete($id);
                    break;
                } else {
                    $content = $this->containerController->getServices();
                    break;
                }
            // no break
            case 'profile':
                if ($method == 'POST') {
                    $content = $this->containerController->getProfileRequest($sign,$data);
                    break;
                } else {
                    $content = $this->containerController->getProfile($id);
                    break;
                }
            // no break
            case 'orders':
                if ($method == 'POST') {
                    //$content = $this->containerController->getProfileRequest($sign,$data);
                    break;
                } else {
                    $content = $this->containerController->getOrders($id,$data);
                    break;
                }
            // no break
            case 'setting':
                if ($method == 'POST') {
                    $content = $this->containerController->setting($id,$data);
                    break;
                } else {
                    $content = $this->containerController->getSetting($id,$data);
                    break;
                }
            // no break
            case 'rental':
                if ($method == 'POST') {
                    $content = $this->containerController->rental($id,$data);
                    break;
                } else {
                    $content = $this->containerController->getRental($id,$data);
                    break;
                }
            case 'detail':
                if ($method == 'POST') {
                    $content = $this->containerController->detail($id,$data,$id_containers);
                    break;
                } else {
                    $content = $this->containerController->getDetail($id,$data,$id_containers);
                    break;
                }
            // no break
            default:
                $content = $this->containerController->getHome();
                break;
        }
        echo $content;
    }
}