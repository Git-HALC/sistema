<?php
require_once __DIR__ . '/../../config/database.php';

use App\Modules\Servicos\ServicoController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('servicos');

$controller = new ServicoController($db);
$controller->handleRequest();
