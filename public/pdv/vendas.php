<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

use App\Modules\PDV\PdvVendaController;
use App\Security\PdvPermissao;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default action para este router é a listagem
if (!isset($_GET['action']) && !isset($_POST['action'])) {
    $_GET['action'] = 'lista';
}

$controller = new PdvVendaController($db, new PdvPermissao($db));
$controller->handleRequest();
