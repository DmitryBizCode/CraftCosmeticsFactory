<?php

namespace App\Services\Common;

class ResponseService
{
    /**
     * Перенаправлення з повідомленням про успіх
     */
    public static function redirectWithSuccess(string $url, string $successMessage): void
    {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        header('Location: ' . $url . $separator . 'success=' . urlencode($successMessage));
        exit;
    }

    /**
     * Перенаправлення з повідомленням про помилку
     */
    public static function redirectWithError(string $url, string $errorMessage): void
    {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        header('Location: ' . $url . $separator . 'error=' . urlencode($errorMessage));
        exit;
    }

    /**
     * Відправка JSON відповіді
     */
    public static function json(array $data, int $statusCode = 200): string
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        return '';
    }

    /**
     * Відправка JSON помилки
     */
    public static function jsonError(string $message, int $statusCode = 400): string
    {
        return self::json(['error' => $message], $statusCode);
    }

    /**
     * Відправка JSON успіху
     */
    public static function jsonSuccess(array $data = []): string
    {
        return self::json(array_merge(['success' => true], $data));
    }
}

