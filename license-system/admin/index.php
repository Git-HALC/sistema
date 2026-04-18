<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminAuth();

$pdo = MasterDatabase::getInstance()->getConnection();
$resumo = [
    'total_empresas' => 0,
    'ativas' => 0,
    'bloqueadas' => 0,
    'trial' => 0,
    'canceladas' => 0,
    'vencidas' => 0,
    'vencendo_7_dias' => 0,
    'vencendo_3_dias' => 0,
];
$vencendo = [];

try {
    $resumoQuery = $pdo->query('SELECT * FROM vw_dashboard_resumo LIMIT 1');
    $resumoRow = $resumoQuery ? $resumoQuery->fetch() : false;
    if (is_array($resumoRow)) {
        $resumo = array_merge($resumo, $resumoRow);
    }

    $stmt = $pdo->query('SELECT * FROM vw_licencas_vencendo ORDER BY licenca_fim ASC LIMIT 15');
    $vencendo = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $e) {
    error_log('Dashboard master erro: ' . $e->getMessage());
}

function statusBadgeClass(string $status): string
{
    return match (strtolower($status)) {
        'ativa' => 'status-ativa',
        'bloqueada' => 'status-bloqueada',
        'trial' => 'status-trial',
        'cancelada' => 'status-cancelada',
        default => 'status-cancelada',
    };
}

function alertaBadgeClass(string $alerta): string
{
    return match (strtoupper($alerta)) {
        'AVISO' => 'alerta-aviso',
        'CRITICO' => 'alerta-critico',
        'URGENTE' => 'alerta-urgente',
        'VENCIDA' => 'alerta-vencida',
        default => 'alerta-ok',
    };
}

function diasVisualClass(int $dias): string
{
    if ($dias <= 3) {
        return 'danger';
    }
    if ($dias <= 7) {
        return 'warn';
    }

    return 'ok';
}

function iconeAcaoDashboard(string $alerta): string
{
    return match (strtoupper($alerta)) {
        'VENCIDA' => 'fa-circle-xmark',
        'URGENTE' => 'fa-triangle-exclamation',
        'CRITICO' => 'fa-circle-exclamation',
        'AVISO' => 'fa-bell',
        default => 'fa-circle-check',
    };
}

$adminNome = (string)($_SESSION['admin_nome'] ?? 'Administrador');
$adminInicial = strtoupper(substr($adminNome, 0, 1) ?: 'A');
$vencendoHoje = 0;
foreach ($vencendo as $item) {
    if ((int)($item['dias_restantes'] ?? 0) === 0) {
        $vencendoHoje++;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel Master - Dashboard</title>
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
            <a class="sidebar-link is-active" href="<?php echo e(adminUrl('admin/index.php')); ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/empresas/listar.php')); ?>"><i class="fa-solid fa-building"></i> Empresas</a>
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
                <span class="crumb-current">Dashboard</span>
            </div>
            <div class="topbar-right">
                <span class="badge-notify"><i class="fa-solid fa-bell"></i> Vencendo hoje: <?php echo $vencendoHoje; ?></span>
                <a class="btn btn-sm btn-ghost" href="<?php echo e(adminUrl('admin/logout.php')); ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <main class="content-area">
            <section class="panel">
                <h1 class="page-title">Dashboard de Licencas</h1>
                <p class="page-subtitle">Visao geral de empresas, alertas e proximos vencimentos.</p>
            </section>

            <section class="metric-grid" aria-label="Resumo de licencas">
                <article class="metric-card">
                    <div class="metric-icon ok"><i class="fa-solid fa-circle-check"></i></div>
                    <div><div class="metric-label">Ativas</div><div class="metric-value"><?php echo (int)$resumo['ativas']; ?></div></div>
                </article>
                <article class="metric-card">
                    <div class="metric-icon danger"><i class="fa-solid fa-ban"></i></div>
                    <div><div class="metric-label">Bloqueadas</div><div class="metric-value"><?php echo (int)$resumo['bloqueadas']; ?></div></div>
                </article>
                <article class="metric-card">
                    <div class="metric-icon warn"><i class="fa-solid fa-clock"></i></div>
                    <div><div class="metric-label">Vencendo em 7 dias</div><div class="metric-value"><?php echo (int)$resumo['vencendo_7_dias']; ?></div></div>
                </article>
                <article class="metric-card">
                    <div class="metric-icon info"><i class="fa-solid fa-layer-group"></i></div>
                    <div><div class="metric-label">Total</div><div class="metric-value"><?php echo (int)$resumo['total_empresas']; ?></div></div>
                </article>
            </section>

            <section class="panel">
                <div class="toolbar-inline">
                    <h2 class="panel-title">Licencas em Alerta</h2>
                    <a class="btn btn-sm btn-ghost" href="<?php echo e(adminUrl('admin/empresas/listar.php')); ?>"><i class="fa-solid fa-arrow-right"></i> Ver todas</a>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>CNPJ</th>
                            <th>Status</th>
                            <th>Vencimento</th>
                            <th>Dias Restantes</th>
                            <th>Alerta</th>
                            <th>Acoes</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vencendo as $item): ?>
                            <?php $dias = (int)$item['dias_restantes']; $classDias = diasVisualClass($dias); ?>
                            <tr>
                                <td><?php echo e((string)$item['nome']); ?></td>
                                <td><?php echo e((string)$item['cnpj']); ?></td>
                                <td><span class="badge <?php echo e(statusBadgeClass((string)$item['status'])); ?>"><?php echo e((string)$item['status']); ?></span></td>
                                <td><?php echo e((string)$item['licenca_fim']); ?></td>
                                <td>
                                    <div class="days-wrap" data-days="<?php echo $dias; ?>">
                                        <span class="days-value <?php echo e($classDias); ?>"><?php echo $dias; ?> dias</span>
                                        <div class="progress"><span class="<?php echo e($classDias); ?>"></span></div>
                                    </div>
                                </td>
                                <td><span class="badge <?php echo e(alertaBadgeClass((string)$item['alerta'])); ?>"><?php echo e((string)$item['alerta']); ?></span></td>
                                <td>
                                    <span class="actions-inline">
                                        <a class="link-btn btn-sm" href="<?php echo e(adminUrl('admin/empresas/renovar.php')); ?>?id=<?php echo (int)$item['id']; ?>"><i class="fa-solid fa-rotate"></i> Renovar</a>
                                        <a class="link-btn btn-sm" href="<?php echo e(adminUrl('admin/empresas/ver.php')); ?>?id=<?php echo (int)$item['id']; ?>"><i class="fa-solid fa-eye"></i> Ver</a>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$vencendo): ?>
                            <tr><td colspan="7">Nenhuma empresa encontrada.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" id="ultimas-acoes">
                <h2 class="panel-title">Ultimas Acoes</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Acao</th>
                            <th>Empresa</th>
                            <th>Status</th>
                            <th>Data de vencimento</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vencendo as $item): ?>
                            <tr>
                                <td><i class="fa-solid <?php echo e(iconeAcaoDashboard((string)$item['alerta'])); ?>" style="margin-right:6px"></i> <?php echo e((string)$item['alerta']); ?></td>
                                <td><?php echo e((string)$item['nome']); ?></td>
                                <td><span class="badge <?php echo e(statusBadgeClass((string)$item['status'])); ?>"><?php echo e((string)$item['status']); ?></span></td>
                                <td><?php echo e((string)$item['licenca_fim']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$vencendo): ?>
                            <tr><td colspan="4">Sem acoes recentes.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>
<script src="<?php echo e(adminUrl('admin/assets/js/admin.js')); ?>"></script>
</body>
</html>
