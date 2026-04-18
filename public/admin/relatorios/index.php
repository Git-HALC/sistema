<?php
require_once __DIR__ . '/../../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

\App\Support\PermissionGate::init($db);
$isAdmin = ((int)($_SESSION['user_role'] ?? 0) === 1);

$canRelPedidos = \App\Support\PermissionGate::can('rel_pedidos');
$canRelFinanceiro = \App\Support\PermissionGate::can('rel_financeiro');
$canProdutos = \App\Support\PermissionGate::can('produtos');

if (!$canRelPedidos && !$canRelFinanceiro && !$canProdutos) {
    header('Location: ' . tenantUrl('admin/dashboard.php?erro=sem_permissao'));
    exit();
}

$legacyActionMap = [
    'contas-receber' => tenantUrl('admin/relatorios/financeiros/relatorio_contas_receber_status.php'),
    'contas-pagar' => tenantUrl('admin/relatorios/financeiros/relatorio_contas_pagar_status.php'),
    'movimentacoes' => tenantUrl('admin/relatorios/financeiros/relatorio_movimentacoes_usuario.php'),
    'dre' => tenantUrl('admin/relatorios/financeiros/relatorio_despesas_receitas.php'),
    'produtos' => tenantUrl('admin/relatorios/produto/relatorio_produtos.php'),
];

$action = (string)($_GET['action'] ?? '');
if ($action !== '' && isset($legacyActionMap[$action])) {
    header('Location: ' . $legacyActionMap[$action]);
    exit();
}

$page_title = 'Relatorios';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <style>
        .relatorios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        .relatorio-card {
            background: var(--bg-card, #fff);
            border: 1px solid var(--border-color, #dee2e6);
            border-radius: 14px;
            padding: 1rem;
            box-shadow: var(--shadow-sm, 0 0.125rem 0.25rem rgba(0,0,0,.075));
        }
        .relatorio-card h5 {
            margin: 0 0 .25rem 0;
        }
        .relatorio-card p {
            margin: 0 0 .75rem 0;
            color: var(--text-secondary, #6c757d);
        }
        .grupo-titulo {
            margin: 1.5rem 0 .75rem 0;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: var(--text-secondary, #6c757d);
        }
    </style>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Central de Relatorios</h1>
    </div>

    <?php if ($canRelFinanceiro): ?>
        <div class="grupo-titulo">Relatorios Financeiros</div>
        <div class="relatorios-grid mb-3">
            <div class="relatorio-card">
                <h5>CR por Status</h5>
                <p>Situacao de contas a receber por status.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/financeiros/relatorio_contas_receber_status.php')); ?>">Abrir</a>
            </div>
            <div class="relatorio-card">
                <h5>CP por Status</h5>
                <p>Situacao de contas a pagar por status.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/financeiros/relatorio_contas_pagar_status.php')); ?>">Abrir</a>
            </div>
            <div class="relatorio-card">
                <h5>Movimentacoes por Usuario</h5>
                <p>Analise de movimentacoes registradas por usuario.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/financeiros/relatorio_movimentacoes_usuario.php')); ?>">Abrir</a>
            </div>
            <div class="relatorio-card">
                <h5>Historico de Saldo</h5>
                <p>Evolucao do saldo ao longo do periodo.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/financeiros/relatorio_historico_saldo.php')); ?>">Abrir</a>
            </div>
            <div class="relatorio-card">
                <h5>Despesas vs Receitas</h5>
                <p>Comparativo financeiro consolidado.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/financeiros/relatorio_despesas_receitas.php')); ?>">Abrir</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canRelPedidos): ?>
        <div class="grupo-titulo">Relatorios de Pedidos</div>
        <div class="relatorios-grid mb-3">
            <div class="relatorio-card">
                <h5>Relatorio de Pedidos</h5>
                <p>Resumo e detalhes dos pedidos por periodo.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/pedidos/relatorio_pedidos.php')); ?>">Abrir</a>
            </div>
            <?php if ($isAdmin): ?>
            <div class="relatorio-card">
                <h5>Auditoria de Usuarios</h5>
                <p>Atividades e trilha de operacoes do sistema.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/usuarios/atividade.php')); ?>">Abrir</a>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($canProdutos): ?>
        <div class="grupo-titulo">Relatorios de Produtos</div>
        <div class="relatorios-grid mb-3">
            <div class="relatorio-card">
                <h5>Inventario de Estoque</h5>
                <p>Saldo atual, valor em estoque e produtos com saldo baixo.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/produto/relatorio_produtos.php')); ?>">Abrir</a>
            </div>
            <div class="relatorio-card">
                <h5>Relatorio de Entradas</h5>
                <p>Movimentacoes de entrada em estoque.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/produto/relatorio_entradas.php')); ?>">Abrir</a>
            </div>
            <div class="relatorio-card">
                <h5>Relatorio de Saidas</h5>
                <p>Movimentacoes de saida em estoque.</p>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/produto/relatorio_saidas.php')); ?>">Abrir</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
