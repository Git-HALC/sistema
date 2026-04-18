<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';

use App\Modules\Financeiro\FluxoCaixaProjetadoController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('financeiro');

$controller = new FluxoCaixaProjetadoController($db);
$controller->handleRequest();
