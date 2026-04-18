<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

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
$controller = new PdvRelatorioController($db, $service, $permissao);

$action = (string) ($_GET['action'] ?? 'pdf');
$caixaId = (int) ($_GET['id'] ?? 0);

if ($action === 'termica') {
    $controller->termica($caixaId);
    exit();
}

$controller->pdf($caixaId);
