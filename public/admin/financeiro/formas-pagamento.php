<?php
use App\Modules\Financeiro\FormaPagamentoController;

/**
 * Dispatcher: Formas de Pagamento
 *
 * Ponto de entrada HTTP para o módulo de Formas de Pagamento.
 * Responsabilidades: carregar configurações, instanciar Controller, delegar.
 * Sem lógica de negócio, sem SQL, sem validações.
 */

require_once __DIR__ . '/../../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('financeiro');

$controller = new FormaPagamentoController($db);
$controller->handleRequest();
