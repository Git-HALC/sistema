<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Modules\Relatorios\Reports\RelatorioVendasPdvPorItem;

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
$tipo       = $_GET['tipo']        ?? '';
$selfUrl    = tenantUrl('admin/relatorios/financeiros/relatorio_vendas_pdv_por_item.php');

$filtros = [
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'tipo'        => $tipo,
];

$relatorio = new RelatorioVendasPdvPorItem($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados = $relatorio->getData($filtros);

$page_title = 'Vendas PDV por Item';
include __DIR__ . '/../../../../public/includes/header.php';
\App\Support\PermissionGate::require('rel_financeiro');

$brl = static fn ($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">Vendas PDV por Item</h1>
        <div class="d-flex gap-2 d-print-none">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-print me-1"></i> Imprimir
            </button>
            <a href="<?= htmlspecialchars($selfUrl . '?' . http_build_query(['data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'tipo' => $tipo, 'export' => 'pdf'])) ?>"
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
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="PRODUTO" <?= $tipo === 'PRODUTO' ? 'selected' : '' ?>>Produtos</option>
                    <option value="SERVICO" <?= $tipo === 'SERVICO' ? 'selected' : '' ?>>Serviços</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-filter me-1"></i> Filtrar
            </button>
        </div>
    </form>

    <div class="card shadow mb-3">
        <div class="card-body d-flex justify-content-between">
            <div><span class="text-muted small">Total faturado</span><div class="h4 mb-0 text-success"><?= $brl($dados['total_geral']) ?></div></div>
            <div><span class="text-muted small">Itens distintos</span><div class="h4 mb-0"><?= (int)$dados['qtd_distintos'] ?></div></div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header">Ranking por volume faturado</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tipo</th>
                            <th>Item</th>
                            <th class="text-end">Qtd</th>
                            <th class="text-end">Vendas</th>
                            <th class="text-end">Unit. Médio</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados['itens'] as $it): ?>
                            <tr>
                                <td><span class="badge bg-<?= $it['tipo_item'] === 'SERVICO' ? 'info' : 'secondary' ?>"><?= htmlspecialchars((string)$it['tipo_item']) ?></span></td>
                                <td><?= htmlspecialchars((string)$it['nome_item']) ?></td>
                                <td class="text-end"><?= (int)$it['qtd_total'] ?></td>
                                <td class="text-end"><?= (int)$it['qtd_vendas'] ?></td>
                                <td class="text-end text-muted small"><?= $brl($it['valor_unitario_medio']) ?></td>
                                <td class="text-end fw-bold"><?= $brl($it['valor_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dados['itens'])): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum item vendido no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>
