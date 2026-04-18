<?php
use App\Modules\Orcamento\OrcamentoController;

/**
 * Dispatcher: Orçamentos
 *
 * Ponto de entrada HTTP para o módulo de Orçamentos.
 * Responsabilidades exclusivas:
 *  - Carregar configurações e sessão
 *  - Instanciar o Controller
 *  - Delegar handleRequest()
 *
 * Sem lógica de negócio, sem SQL, sem validações.
 */

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('orcamentos');

$controller = new OrcamentoController($db);
$controller->handleRequest();
