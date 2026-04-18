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
        $month = DashboardApiResponder::optionalPositiveInt($request, 'month');
        if ($month !== null) {
            $month = max(1, min(12, $month));
        }
        $categoryId = DashboardApiResponder::optionalPositiveInt($request, 'categoria_dre_id');
        return (new FinanceiroDashboardApiService($pdo))->getReceitasDespesas($year, $month, $categoryId);
    }
);
