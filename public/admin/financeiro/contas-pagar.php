<?php
use App\Modules\Financeiro\ContaPagarController;

/**
 * Dispatcher: Contas a Pagar
 *
 * Ponto de entrada HTTP para o módulo de Contas a Pagar.
 * Responsabilidades: carregar configurações, instanciar Controller, delegar.
 * Sem lógica de negócio, sem SQL, sem validações.
 */

require_once __DIR__ . '/../../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('financeiro');

$controller = new ContaPagarController($db);
$controller->handleRequest();
