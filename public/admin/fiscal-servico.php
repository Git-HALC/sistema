<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/tenant.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/Modules/FiscalServico/EmpresaFiscalServico.php';
require_once __DIR__ . '/../../app/Modules/FiscalServico/EmpresaFiscalServicoRepository.php';
require_once __DIR__ . '/../../app/Modules/FiscalServico/NotaFiscalServico.php';
require_once __DIR__ . '/../../app/Modules/FiscalServico/NotaFiscalServicoException.php';
require_once __DIR__ . '/../../app/Modules/FiscalServico/NotaFiscalServicoRepository.php';
require_once __DIR__ . '/../../app/Modules/FiscalServico/NotaFiscalServicoService.php';
require_once __DIR__ . '/../../app/Modules/FiscalServico/NotaFiscalServicoController.php';

use App\Modules\FiscalServico\NotaFiscalServicoController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

tenantBootstrap();
\App\Support\PermissionGate::init($db);

$controller = new NotaFiscalServicoController($db);
$controller->handleRequest();
