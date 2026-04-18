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
            'forma_pagamento_id' => DashboardApiResponder::positiveInt($request, 'forma_pagamento_id', 'Forma de pagamento'),
            'categoria_dre_id' => DashboardApiResponder::positiveInt($request, 'categoria_dre_id', 'Categoria DRE'),
            'valor_pago' => DashboardApiResponder::optionalPositiveFloat($request, 'valor_pago'),
            'desconto_recebimento' => DashboardApiResponder::nonNegativeFloat($request, 'desconto_recebimento', 0.0),
            'data_pagamento' => DashboardApiResponder::ymdDate($request, 'data_pagamento', date('Y-m-d')),
        ];

        return (new FinanceiroDashboardApiService($pdo))->markContaReceberAsPaid(
            $id,
            $data,
            (int)($_SESSION['user_id'] ?? 0)
        );
    },
    true
);
