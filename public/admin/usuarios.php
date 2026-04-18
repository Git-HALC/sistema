<?php
/**
 * Dispatcher: Usuários
 *
 * Ponto de entrada HTTP para o módulo de Usuários.
 * Responsabilidades: carregar configurações, instanciar Controller, delegar.
 * Sem lógica de negócio, sem SQL, sem validações.
 */

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Modules\Usuarios\UsuariosController;

$controller = new UsuariosController($db);
$controller->handleRequest();
