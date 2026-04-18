<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/Modules/EmpresaDados/EmpresaDadosController.php';

use App\Modules\EmpresaDados\EmpresaDadosController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$controller = new EmpresaDadosController($db);
$controller->handleRequest();