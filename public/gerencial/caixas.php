<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

use App\Modules\PDV\GerencialCaixaController;
use App\Modules\PDV\PdvRelatorioController;
use App\Modules\PDV\PdvRepository;
use App\Modules\PDV\PdvService;
use App\Security\PdvPermissao;
use App\Support\AuditLogger;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$repository = new PdvRepository($db);
$permissao = new PdvPermissao($db);
$service = new PdvService($db, $repository, $permissao, new AuditLogger($db));
$relatorioController = new PdvRelatorioController($db, $service, $permissao);

$controller = new GerencialCaixaController($db, $service, $permissao, $relatorioController);
$controller->handleRequest();
