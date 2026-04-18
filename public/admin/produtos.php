<?php
require_once __DIR__ . '/../../config/database.php';

use App\Modules\Produtos\ProdutosController;
use App\Security\PdvPermissao;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
$allowPdvSharedAccess = !empty($GLOBALS['__dm_allow_pdv_shared_access']) && !empty($GLOBALS['__dm_pdv_context']);
if (!$allowPdvSharedAccess) {
    \App\Support\PermissionGate::require('produtos');
} elseif (!(new PdvPermissao($db))->usuarioAtualPodeOperarPdv()) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$controller = new ProdutosController($db);
$controller->handleRequest();
