<?php

declare(strict_types=1);

use App\Modules\Financeiro\FinanceiroDashboardApiService;
use App\Support\DashboardApiResponder;

require_once __DIR__ . '/../../../config/database.php';

DashboardApiResponder::run(
    $db,
    'financeiro',
    'POST',
    static function (\PDO $pdo, array $request): array {
        $id = DashboardApiResponder::positiveInt($_GET, 'id', 'ID da conta');
        $data = [
            'conta_id' => DashboardApiResponder::positiveInt($request, 'conta_id', 'Conta bancaria'),
            'categoria_dre_id' => DashboardApiResponder::positiveInt($request, 'categoria_dre_id', 'Categoria DRE'),
            'valor_pago' => DashboardApiResponder::optionalPositiveFloat($request, 'valor_pago'),
            'desconto_pagamento' => DashboardApiResponder::nonNegativeFloat($request, 'desconto_pagamento', 0.0),
            'data_pagamento' => DashboardApiResponder::ymdDate($request, 'data_pagamento', date('Y-m-d')),
        ];

        return (new FinanceiroDashboardApiService($pdo))->markContaPagarAsPaid(
            $id,
            $data,
            (int)($_SESSION['user_id'] ?? 0)
        );
    },
    true
);
