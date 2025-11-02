<?php

namespace App\Services;

use PDO;

class IotService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Заглушка - требуется реализация
}

