<?php

namespace App\Services\Auth;

class AuthService
{
    /**
     * Почати сесію (якщо ще не почата)
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Зберегти ID користувача в сесії
     */
    public static function login(int $userId, string $email, string $firstName, string $lastName = null): void
    {
        self::startSession();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_first_name'] = $firstName;
        $_SESSION['user_last_name'] = $lastName;
        $_SESSION['user_logged_in'] = true;
    }

    /**
     * Вийти з системи
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    /**
     * Перевірити, чи користувач залогінений
     */
    public static function isLoggedIn(): bool
    {
        self::startSession();
        return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    }

    /**
     * Отримати ID поточного користувача
     */
    public static function getUserId(): ?int
    {
        self::startSession();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Отримати email поточного користувача
     */
    public static function getUserEmail(): ?string
    {
        self::startSession();
        return $_SESSION['user_email'] ?? null;
    }

    /**
     * Отримати ім'я поточного користувача
     */
    public static function getUserName(): ?string
    {
        self::startSession();
        $firstName = $_SESSION['user_first_name'] ?? '';
        $lastName = $_SESSION['user_last_name'] ?? '';
        return trim($firstName . ' ' . $lastName) ?: null;
    }
}

