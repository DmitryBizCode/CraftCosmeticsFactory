<?php

namespace App\Services;
use PDO;
use PDOException;

class SQLService
{
    private PDO $pdo;

    public function __construct(string $host = null, string $user = 'user', string $pass = '12345', string $db = 'container', int $port = 3306)
    {
        // Автоматическое определение хоста: в Docker используется имя сервиса 'mysql', локально 'localhost'
        if ($host === null) {
            $host = getenv('MYSQL_HOST') ?: (file_exists('/.dockerenv') ? 'mysql' : 'localhost');
        }
        try {
            $this->pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
        } catch (PDOException $e) {
            echo "Error access to database: " . $e->getMessage();
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

}