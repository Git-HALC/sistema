<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/tenant.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php?error=session_expired'));
    exit;
}

$controller = new \App\Modules\Fiscal\FiscalEmissaoController($db);
$controller->handleRequest();
