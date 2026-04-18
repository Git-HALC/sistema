<?php

declare(strict_types=1);

use App\Modules\Dashboard\DashboardApiService;
use App\Support\DashboardApiResponder;

require_once __DIR__ . '/../../config/database.php';

DashboardApiResponder::run(
    $db,
    'dashboard',
    'GET',
    static function (\PDO $pdo, array $request): array {
        $period = DashboardApiResponder::enum($request, 'period', ['7D', '30D', '90D', '12M'], '30D');
        return (new DashboardApiService($pdo))->getFaturamento($period);
    }
);
