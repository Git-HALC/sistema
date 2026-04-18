<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Modules\Relatorios\Reports\RelatorioVendasPdv;

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
$status     = $_GET['status']      ?? '';
$selfUrl    = tenantUrl('admin/relatorios/financeiros/relatorio_vendas_pdv.php');

$filtros = [
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'status'      => $status,
];

$relatorio = new RelatorioVendasPdv($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados = $relatorio->getData($filtros);

$page_title = 'Vendas PDV por Período';
include __DIR__ . '/../../../../public/includes/header.php';
\App\Support\PermissionGate::require('rel_financeiro');

$brl = static fn ($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">Vendas PDV por Período</h1>
        <div class="d-flex gap-2 d-print-none">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-print me-1"></i> Imprimir
            </button>
            <a href="<?= htmlspecialchars($selfUrl . '?' . http_build_query(['data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'status' => $status, 'export' => 'pdf'])) ?>"
               class="btn btn-outline-danger btn-sm">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
        </div>
    </div>

    <form method="GET" action="<?= htmlspecialchars($selfUrl) ?>" class="card shadow mb-3 d-print-none">
        <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">Até</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="faturado" <?= $status === 'faturado' ? 'selected' : '' ?>>Faturado</option>
                    <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-filter me-1"></i> Filtrar
            </button>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total Faturado</div>
                    <div class="h3 mb-0 text-success"><?= $brl($dados['total_geral']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Qtd. Vendas</div>
                    <div class="h3 mb-0"><?= (int)$dados['qtd_geral'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Ticket Médio</div>
                    <div class="h3 mb-0"><?= $brl($dados['ticket_medio']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-3">
        <div class="card-header">Totais por forma de pagamento</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Forma</th><th class="text-end">Qtd</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($dados['por_forma'] as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$p['nome']) ?></td>
                            <td class="text-end"><?= (int)$p['qtd'] ?></td>
                            <td class="text-end fw-semibold"><?= $brl($p['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header">Vendas do período (<?= count($dados['vendas']) ?>)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Data/Hora</th>
                            <th>Cliente</th>
                            <th>Pagamento</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados['vendas'] as $v): ?>
                            <tr>
                                <td class="fw-bold text-primary">#<?= (int)$v['numero'] ?></td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime((string)$v['created_at'])) ?></td>
                                <td><?= htmlspecialchars((string)($v['cliente_nome'] ?? 'Avulso')) ?></td>
                                <td><?= htmlspecialchars((string)($v['forma_pagamento_nome'] ?? '—')) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $v['status'] === 'faturado' ? 'success' : ($v['status'] === 'cancelado' ? 'danger' : 'warning text-dark') ?>">
                                        <?= htmlspecialchars((string)$v['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold"><?= $brl($v['valor_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dados['vendas'])): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma venda no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>
