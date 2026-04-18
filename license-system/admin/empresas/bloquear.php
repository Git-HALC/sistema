<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAuth();

$pdo = MasterDatabase::getInstance()->getConnection();
$empresaId = (int)($_GET['id'] ?? 0);
if ($empresaId <= 0) {
    header('Location: ' . adminUrl('admin/empresas/listar.php') . '?msg=Empresa%20invalida');
    exit;
}

$empresaStmt = $pdo->prepare('SELECT * FROM empresas WHERE id = :id LIMIT 1');
$empresaStmt->execute([':id' => $empresaId]);
$empresa = $empresaStmt->fetch();
if (!is_array($empresa)) {
    header('Location: ' . adminUrl('admin/empresas/listar.php') . '?msg=Empresa%20nao%20encontrada');
    exit;
}

$erro = '';

function syncStatusCliente(array $empresa, string $novoStatus): void
{
    $clientePdo = MasterDatabase::createPdo((string)$empresa['banco_dados']);
    $update = $clientePdo->prepare(
        'UPDATE licenca
            SET status = :status
          WHERE id = (SELECT id FROM licenca ORDER BY id ASC LIMIT 1)'
    );
    $update->execute([':status' => $novoStatus]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $erro = 'Token CSRF invalido.';
    } else {
        $acao = (string)($_POST['acao'] ?? '');
        if (!in_array($acao, ['bloquear', 'desbloquear'], true)) {
            $erro = 'Acao invalida.';
        } else {
            $novoStatus = $acao === 'bloquear'
                ? 'bloqueada'
                : (((string)$empresa['licenca_tipo'] === 'trial') ? 'trial' : 'ativa');

            try {
                $update = $pdo->prepare('UPDATE empresas SET status = :status WHERE id = :id');
                $update->execute([
                    ':status' => $novoStatus,
                    ':id' => $empresaId,
                ]);

                syncStatusCliente($empresa, $novoStatus);
                header('Location: ' . adminUrl('admin/empresas/ver.php') . '?id=' . $empresaId);
                exit;
            } catch (Throwable $e) {
                error_log('Erro ao bloquear/desbloquear empresa: ' . $e->getMessage());
                $erro = 'Falha ao alterar status da empresa.';
            }
        }
    }
}

function statusBadgeClassBlock(string $status): string
{
    return match (strtolower($status)) {
        'ativa' => 'status-ativa',
        'bloqueada' => 'status-bloqueada',
        'trial' => 'status-trial',
        'cancelada' => 'status-cancelada',
        default => 'status-cancelada',
    };
}

$adminNome = (string)($_SESSION['admin_nome'] ?? 'Administrador');
$adminInicial = strtoupper(substr($adminNome, 0, 1) ?: 'A');
$isBloqueada = ((string)$empresa['status'] === 'bloqueada');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bloquear ou Desbloquear Empresa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(adminUrl('admin/assets/css/style.css')); ?>">
</head>
<body class="admin-shell">
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-head">
            <div class="logo-mark"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="logo-text">
                <span class="logo-title">LicenseSystem</span>
                <span class="logo-sub">Master Panel</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a class="sidebar-link is-active" href="<?php echo e(adminUrl('admin/empresas/listar.php')); ?>"><i class="fa-solid fa-building"></i> Empresas</a>
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>#ultimas-acoes"><i class="fa-solid fa-clock-rotate-left"></i> Historico</a>
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>#configuracoes"><i class="fa-solid fa-gear"></i> Configuracoes</a>
        </nav>
        <div class="sidebar-foot">
            <div class="admin-id">
                <div class="admin-avatar"><?php echo e($adminInicial); ?></div>
                <div class="admin-meta">
                    <span class="admin-name"><?php echo e($adminNome); ?></span>
                    <span class="admin-role">Administrador</span>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-wrap">
        <header class="topbar-fixed">
            <div class="topbar-left">
                <button type="button" class="mobile-nav-toggle" data-nav-toggle aria-label="Abrir menu"><i class="fa-solid fa-bars"></i></button>
                <span>Painel</span>
                <i class="fa-solid fa-angle-right"></i>
                <span class="crumb-current">Bloqueio</span>
            </div>
            <div class="topbar-right">
                <span class="badge-notify"><i class="fa-solid fa-user-shield"></i> <?php echo e((string)$empresa['nome']); ?></span>
                <a class="btn btn-sm btn-ghost" href="<?php echo e(adminUrl('admin/logout.php')); ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <main class="content-area">
            <section class="panel" style="max-width:620px; margin:0 auto;">
                <h1 class="page-title">Bloqueio de Empresa</h1>
                <p class="page-subtitle">Controle o estado de acesso da licenca desta empresa.</p>

                <div style="margin-top:12px;" class="info-item">
                    <span>Status atual</span>
                    <strong><span class="badge <?php echo e(statusBadgeClassBlock((string)$empresa['status'])); ?>"><?php echo e((string)$empresa['status']); ?></span></strong>
                </div>

                <?php if ($erro !== ''): ?>
                    <div class="alert alert-error" style="margin-top:12px"><?php echo e($erro); ?></div>
                <?php endif; ?>

                <form method="post" class="form-actions" data-loading-submit style="margin-top:14px;">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                    <?php if ($isBloqueada): ?>
                        <input type="hidden" name="acao" value="desbloquear">
                        <button type="submit" class="btn btn-success"><span class="btn-text"><i class="fa-solid fa-lock-open"></i> Desbloquear empresa</span><span class="spinner"></span></button>
                    <?php else: ?>
                        <input type="hidden" name="acao" value="bloquear">
                        <button type="submit" class="btn btn-danger"><span class="btn-text"><i class="fa-solid fa-lock"></i> Bloquear empresa</span><span class="spinner"></span></button>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="<?php echo e(adminUrl('admin/empresas/ver.php')); ?>?id=<?php echo $empresaId; ?>"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
                </form>
            </section>
        </main>
    </div>
</div>
<script src="<?php echo e(adminUrl('admin/assets/js/admin.js')); ?>"></script>
</body>
</html>
