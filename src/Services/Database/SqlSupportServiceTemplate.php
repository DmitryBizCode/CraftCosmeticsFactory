<?php

namespace App\Services\Database;

use PDO;

class SqlSupportServiceTemplate
{
    private static PDO $pdo;

    public function __construct(PDO $pdo)
    {
        self::$pdo = $pdo;
    }
    public static function getAll(string $tableName, string $orderBy = 'id DESC'):array {
        $stmt = self::$pdo->prepare("SELECT * FROM $tableName ORDER BY $orderBy");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public static function getById(string $tableName, string $idTableName, $id): ?array {
        $stmt = self::$pdo->prepare("SELECT * FROM $tableName WHERE $idTableName = :id");
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    public static function delete(string $tableName, string $idTableName, int $id): void
    {
        $stmt = self::$pdo->prepare("DELETE FROM $tableName WHERE $idTableName = :id");
        $stmt->execute([':id' => $id]);
    }

}