<?php
/**
 * Ponto de entrada da aplicacao - redireciona direto para login.
 */
require_once __DIR__ . '/../config/tenant.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Support/PermissionManager.php';

date_default_timezone_set('America/Sao_Paulo');
tenantBootstrap();
tenantEnsureSession();

$role = (int)($_SESSION['user_role'] ?? 0);

if (isset($_SESSION['user_id']) && $role > 0) {
    if (isset($db) && $db instanceof PDO) {
        $permissionManager = new \App\Support\PermissionManager($db, (int)$_SESSION['user_id'], $role);
        header('Location: ' . $permissionManager->getRedirectUrl());
    } else {
        header('Location: ' . tenantUrl('admin/dashboard.php'));
    }
} else {
    if (isset($_SESSION['user_id']) && $role <= 0) {
        // Remove sessao inconsistente para evitar loop de redirecionamento.
        $_SESSION = [];
    }
    header('Location: ' . tenantUrl('login.php'));
}
exit();
