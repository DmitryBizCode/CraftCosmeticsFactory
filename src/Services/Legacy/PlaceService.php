<?php

namespace App\Services\Legacy;

use PDO;
use App\Services\Database\SqlSupportServiceTemplate;

class PlaceService
{
    private PDO $pdo;
    private SqlSupportServiceTemplate $sqlSupportService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sqlSupportService = new SqlSupportServiceTemplate($pdo);
    }

    public function getOneCountryById(int $countryId): array
    {
        return $this->sqlSupportService::getById('countries', 'country_id', $countryId);
    }
}

