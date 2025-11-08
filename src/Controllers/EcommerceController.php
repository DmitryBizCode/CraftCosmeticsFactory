<?php

namespace App\Controllers;

use App\Services\CalculateShipments;
use App\Services\ContainerService;
use App\Services\IotService;
use App\Services\Mail;
use App\Services\PeopleService;
use App\Services\PlaceService;
use App\Services\RentService;
use App\Services\SQLService;
use App\Services\TemplateService;
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
    public function getHome(): string
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
}