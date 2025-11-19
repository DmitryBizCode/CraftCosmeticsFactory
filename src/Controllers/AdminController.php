<?php

namespace App\Controllers;

use App\Services\TemplateService;

class AdminController
{
    private TemplateService $templateService;

    public function __construct()
    {
        $this->templateService = new TemplateService();
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
        return $this->templateService->render('pages/adminProducts', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'products',
        ]);
    }

    /** Сторінка управління рецептами */
    public function getAdminRecipes(): string
    {
        return $this->templateService->render('pages/adminRecipes', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'recipes',
        ]);
    }

    /** Сторінка контролю еко-системи рецепту */
    public function getAdminEcoSystem(int $recipeId): string
    {
        return $this->templateService->render('pages/adminEcoSystem', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'recipes',
            'recipeId' => $recipeId,
        ]);
    }

    /** Сторінка статистики IoT датчиків */
    public function getAdminIotStats(int $recipeId): string
    {
        return $this->templateService->render('pages/adminIotStats', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'recipes',
            'recipeId' => $recipeId,
        ]);
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
        return $this->templateService->render('pages/adminUsers', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'users',
        ]);
    }

    /** Сторінка управління IoT / Eco system */
    public function getAdminIot(): string
    {
        return $this->templateService->render('pages/adminIot', [
            'homeNavBar' => null,
            'footer' => null,
            'currentPage' => 'iot',
        ]);
    }
}


