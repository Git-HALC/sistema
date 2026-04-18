<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAuth();

$pdo = MasterDatabase::getInstance()->getConnection();
$statusFiltro = strtolower((string)($_GET['status'] ?? 'todos'));
$statusPermitidos = ['todos', 'ativa', 'bloqueada', 'trial', 'cancelada'];
if (!in_array($statusFiltro, $statusPermitidos, true)) {
    $statusFiltro = 'todos';
}
$busca = trim((string)($_GET['q'] ?? ''));

$sql = 'SELECT * FROM vw_licencas_vencendo';
$params = [];
if ($statusFiltro !== 'todos') {
    $sql .= ' WHERE status = :status';
    $params[':status'] = $statusFiltro;
}
$sql .= ' ORDER BY licenca_fim ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empresas = $stmt->fetchAll() ?: [];

function rowAlertClass(string $alerta): string
{
    return match (strtoupper($alerta)) {
        'VENCIDA' => 'row-alert-vencida',
        'URGENTE' => 'row-alert-urgente',
        'CRITICO' => 'row-alert-critico',
        'AVISO' => 'row-alert-aviso',
        default => '',
    };
}

function statusBadgeClassEmpresas(string $status): string
{
    return match (strtolower($status)) {
        'ativa' => 'status-ativa',
        'bloqueada' => 'status-bloqueada',
        'trial' => 'status-trial',
        'cancelada' => 'status-cancelada',
        default => 'status-cancelada',
    };
}

function alertaBadgeClassEmpresas(string $alerta): string
{
    return match (strtoupper($alerta)) {
        'AVISO' => 'alerta-aviso',
        'CRITICO' => 'alerta-critico',
        'URGENTE' => 'alerta-urgente',
        'VENCIDA' => 'alerta-vencida',
        default => 'alerta-ok',
    };
}

function diasVisualClassEmpresas(int $dias): string
{
    if ($dias <= 3) {
        return 'danger';
    }
    if ($dias <= 7) {
        return 'warn';
    }

    return 'ok';
}

$adminNome = (string)($_SESSION['admin_nome'] ?? 'Administrador');
$adminInicial = strtoupper(substr($adminNome, 0, 1) ?: 'A');
$vencendoHoje = 0;
foreach ($empresas as $empresaItem) {
    if ((int)($empresaItem['dias_restantes'] ?? 0) === 0) {
        $vencendoHoje++;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empresas - Painel Master</title>
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
                <span class="crumb-current">Empresas</span>
            </div>
            <div class="topbar-right">
                <span class="badge-notify"><i class="fa-solid fa-bell"></i> Vencendo hoje: <?php echo $vencendoHoje; ?></span>
                <a class="btn btn-sm btn-ghost" href="<?php echo e(adminUrl('admin/logout.php')); ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <main class="content-area">
            <section class="panel">
                <div class="toolbar-inline">
                    <div>
                        <h1 class="page-title">Listagem de Empresas</h1>
                        <p class="page-subtitle">Gestao de clientes e monitoramento de licencas.</p>
                    </div>
                    <a class="btn btn-primary" href="<?php echo e(adminUrl('admin/empresas/criar.php')); ?>"><i class="fa-solid fa-plus"></i> Nova Empresa</a>
                </div>
            </section>

            <section class="panel">
                <form method="get" class="filters" autocomplete="off">
                    <label class="field">
                        <span>Busca por nome/CNPJ</span>
                        <input type="text" name="q" value="<?php echo e($busca); ?>" placeholder="Digite para buscar" data-table-filter>
                    </label>
                    <label class="field" for="status">
                        <span>Status</span>
                        <select id="status" name="status">
                            <?php foreach ($statusPermitidos as $op): ?>
                                <option value="<?php echo e($op); ?>" <?php echo $statusFiltro === $op ? 'selected' : ''; ?>>
                                    <?php echo e(ucfirst($op)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-filter"></i> Filtrar</button>
                </form>

                <?php if (!empty($_GET['msg'])): ?>
                    <div class="alert alert-success" style="margin-top:12px"><?php echo e((string)$_GET['msg']); ?></div>
                <?php endif; ?>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Empresa</th>
                            <th>CNPJ</th>
                            <th>Banco</th>
                            <th>Acesso</th>
                            <th>Status</th>
                            <th>Fim</th>
                            <th>Dias Restantes</th>
                            <th>Alerta</th>
                            <th>Acoes</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($empresas as $empresa): ?>
                            <?php
                            $dias = (int)$empresa['dias_restantes'];
                            $classDias = diasVisualClassEmpresas($dias);
                            $urlAcesso = gerarUrlAcessoEmpresa((string)$empresa['nome']);
                            $searchRow = (string)$empresa['nome'] . ' ' . (string)$empresa['cnpj'];
                            ?>
                            <tr class="<?php echo e(rowAlertClass((string)$empresa['alerta'])); ?>" data-search-row="<?php echo e($searchRow); ?>">
                                <td><?php echo (int)$empresa['id']; ?></td>
                                <td><?php echo e((string)$empresa['nome']); ?></td>
                                <td><?php echo e((string)$empresa['cnpj']); ?></td>
                                <td><?php echo e((string)$empresa['banco_dados']); ?></td>
                                <td><a class="link-btn btn-sm" href="<?php echo e($urlAcesso); ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-up-right-from-square"></i> Abrir</a></td>
                                <td><span class="badge <?php echo e(statusBadgeClassEmpresas((string)$empresa['status'])); ?>"><?php echo e((string)$empresa['status']); ?></span></td>
                                <td><?php echo e((string)$empresa['licenca_fim']); ?></td>
                                <td>
                                    <div class="days-wrap" data-days="<?php echo $dias; ?>">
                                        <span class="days-value <?php echo e($classDias); ?>"><?php echo $dias; ?> dias</span>
                                        <div class="progress"><span class="<?php echo e($classDias); ?>"></span></div>
                                    </div>
                                </td>
                                <td><span class="badge <?php echo e(alertaBadgeClassEmpresas((string)$empresa['alerta'])); ?>"><?php echo e((string)$empresa['alerta']); ?></span></td>
                                <td>
                                    <span class="actions-inline">
                                        <a class="link-btn btn-sm" href="<?php echo e(adminUrl('admin/empresas/ver.php')); ?>?id=<?php echo (int)$empresa['id']; ?>"><i class="fa-solid fa-eye"></i> Ver</a>
                                        <a class="link-btn btn-sm" href="<?php echo e(adminUrl('admin/empresas/renovar.php')); ?>?id=<?php echo (int)$empresa['id']; ?>"><i class="fa-solid fa-rotate"></i> Renovar</a>
                                        <a class="link-btn btn-sm" href="<?php echo e(adminUrl('admin/empresas/bloquear.php')); ?>?id=<?php echo (int)$empresa['id']; ?>"><i class="fa-solid fa-lock"></i> Bloquear</a>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$empresas): ?>
                            <tr><td colspan="10">Nenhuma empresa encontrada.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pager" aria-label="Paginacao">
                    <span class="dot is-active">1</span>
                </div>
            </section>
        </main>
    </div>
</div>
<script src="<?php echo e(adminUrl('admin/assets/js/admin.js')); ?>"></script>
</body>
</html>
