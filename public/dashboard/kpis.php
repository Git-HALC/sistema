<?php

declare(strict_types=1);

use App\Modules\Dashboard\DashboardApiService;
use App\Support\DashboardApiResponder;

require_once __DIR__ . '/../../config/database.php';

DashboardApiResponder::run(
    $db,
    'dashboard',
    'GET',
    static function (\PDO $pdo): array {
        return (new DashboardApiService($pdo))->getKpis();
    }
);
