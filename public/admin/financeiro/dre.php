<?php
use App\Modules\Financeiro\DreController;

/**
 * Dispatcher: DRE (Demonstração de Resultado do Exercício)
 *
 * Ponto de entrada HTTP para o módulo DRE.
 * Responsabilidades: carregar configurações, instanciar Controller, delegar.
 * Sem lógica de negócio, sem SQL, sem validações.
 */

require_once __DIR__ . '/../../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('financeiro');

$controller = new DreController($db);
$controller->handleRequest();
