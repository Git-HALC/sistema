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
        $limit = max(1, min(50, DashboardApiResponder::intFrom($request, 'limit', 10)));
        return (new FinanceiroDashboardApiService($pdo))->getContasPagarReceber($limit);
    }
);
