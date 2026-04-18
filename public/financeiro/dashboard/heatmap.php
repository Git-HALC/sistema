<?php

declare(strict_types=1);

use App\Modules\Financeiro\FinanceiroDashboardApiService;
use App\Support\DashboardApiResponder;

require_once __DIR__ . '/../../../config/database.php';

DashboardApiResponder::run(
    $db,
    'financeiro',
    'GET',
    static function (\PDO $pdo, array $request): array {
        $year = max(2020, min(2100, DashboardApiResponder::intFrom($request, 'year', (int)date('Y'))));
        return (new FinanceiroDashboardApiService($pdo))->getHeatmap($year);
    }
);
