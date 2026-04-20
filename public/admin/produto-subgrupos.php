<?php
require_once __DIR__ . '/../../config/database.php';

use App\Modules\Produtos\ProdutoSubgrupoController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('produtos');

$controller = new ProdutoSubgrupoController($db);
$controller->handleRequest();
