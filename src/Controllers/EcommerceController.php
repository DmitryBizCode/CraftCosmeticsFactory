<?php

namespace App\Controllers;

use App\Services\Legacy\CalculateShipments;
use App\Services\Legacy\ContainerService;
use App\Services\Iot\IotService;
use App\Services\Common\Mail;
use App\Services\User\PeopleService;
use App\Services\Legacy\PlaceService;
use App\Services\Legacy\RentService;
use App\Services\Database\SQLService;
use App\Services\Common\TemplateService;
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
        return $this->templateService->render('pages/indexPage', ["homeNavBar" => 1,"footer" => 1]);
    }
    public function getProfile(): string
    {
        return $this->templateService->render('pages/indexProfile', ["homeNavBar" => 1, "footer" => null]);
    }
    public function getProfileSetting(): string
    {
        return $this->templateService->render('pages/profileSetting', ["homeNavBar" => 1, "footer" => null]);
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
        return $this->templateService->render('pages/signPage', ["homeNavBar" => null, "footer" => null]);
    }
}