<?php

namespace App\Services\Database;
use PDO;
use PDOException;

class SQLService
{
    private PDO $pdo;

    public function __construct(?string $host = null, string $user = 'user', string $pass = '12345qwerty', string $db = 'cosmetics', int $port = 3306)
    {
        // Автоматическое определение хоста: в Docker используется имя сервиса 'mysql', локально 'localhost'
        if ($host === null) {
            $host = getenv('MYSQL_HOST') ?: (file_exists('/.dockerenv') ? 'mysql' : 'localhost');
        }
        try {
            $this->pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new \Exception("Error access to database: " . $e->getMessage());
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

}