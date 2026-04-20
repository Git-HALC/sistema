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
        $tipo = DashboardApiResponder::enum($request, 'tipo', ['receita', 'despesa'], 'receita');
        $inicio = isset($request['inicio']) ? (string)$request['inicio'] : null;
        $fim = isset($request['fim']) ? (string)$request['fim'] : null;
        return (new FinanceiroDashboardApiService($pdo))->getPorCategoria($tipo, $inicio, $fim);
    }
);
