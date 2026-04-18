<?php
require_once __DIR__ . '/../../config/tenant.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/Modules/Fiscal/NotaFiscalController.php';

use App\Modules\Fiscal\NotaFiscalController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

tenantBootstrap();
\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('fiscal');

$controller = new NotaFiscalController($db);
$controller->handleRequest();