<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Modules\Relatorios\Reports\RelatorioCrStatus;

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim    = $_GET['data_fim']    ?? '';
$status     = $_GET['status']      ?? 'todos';
$selfUrl    = tenantUrl('admin/relatorios/financeiros/relatorio_contas_receber_status.php');

$filtros = [
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'status'      => $status,
];

$relatorio = new RelatorioCrStatus($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados  = $relatorio->getData($filtros);
$contas = $dados['contas']  ?? [];
$totais = $dados['totais']  ?? [];

$statusOpcoes = [
    'todos'              => 'Todos',
    'PENDENTE'           => 'Pendente',
    'PAGO'               => 'Pago',
    'PARCIALMENTE_PAGO'  => 'Parcialmente Pago',
    'VENCIDO'            => 'Vencido',
    'CANCELADO'          => 'Cancelado',
];

$page_title = 'CR por Status';
include __DIR__ . '/../../../../public/includes/header.php';
\App\Support\PermissionGate::require('rel_financeiro');
?>

<div class="container-fluid">
    <style>
    :root {
        --bg-primary:#ffffff;--bg-secondary:#f8f9fa;--bg-card:#ffffff;
        --text-primary:#212529;--text-secondary:#6c757d;--border-color:#dee2e6;
        --shadow-sm:0 0.125rem 0.25rem rgba(0,0,0,0.075);
        --shadow-md:0 0.5rem 1rem rgba(0,0,0,0.15);
    }
    [data-theme="dark"]{--bg-primary:#1a1a1a;--bg-secondary:#2d2d2d;--bg-card:#2d2d2d;--text-primary:#ffffff;--text-secondary:#b0b0b0;--border-color:#404040;}
    body{background-color:var(--bg-primary);color:var(--text-primary);}
    .resumo-card{background:var(--bg-card);color:var(--text-primary);border-radius:15px;padding:2rem;margin-bottom:2rem;box-shadow:var(--shadow-md);border:1px solid var(--border-color);}
    .resumo-item{display:flex;justify-content:space-between;align-items:center;margin:1rem 0;padding:1rem;background:var(--bg-secondary);border-radius:10px;border:1px solid var(--border-color);transition:all 0.3s ease;}
    .resumo-item:hover{transform:translateY(-2px);}
    .resumo-value{font-size:1.5rem;font-weight:bold;}
    .positive{color:#28a745!important;}.negative{color:#dc3545!important;}
    .filter-section{background:var(--bg-secondary);padding:1.5rem;border-radius:10px;margin-bottom:2rem;border-left:4px solid #28a745;}
    .table-responsive{border-radius:10px;overflow:hidden;background:var(--bg-card);}
    .custom-table{background:var(--bg-card);color:var(--text-primary);margin-bottom:0;}
    .custom-table th{background:#343a40;color:white;}
    .custom-table tbody tr:nth-child(even){background:var(--bg-secondary);}
    .custom-table tbody tr:hover{background:rgba(40,167,69,0.1);}
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Contas a Receber por Status</h1>
        <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars(tenantUrl('admin/financeiro/contas-receber.php')); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-circle-down me-1"></i>Contas a Receber
            </a>
            <button class="btn btn-danger btn-lg" onclick="exportPDF()">
                <i class="fas fa-file-pdf me-2"></i>Exportar PDF
            </button>
        </div>
    </div>

    <form method="GET" class="filter-section">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Data Início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($dataInicio) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($dataFim) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach ($statusOpcoes as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-success flex-fill"><i class="fas fa-search me-1"></i>Filtrar</button>
                <a href="<?= htmlspecialchars($selfUrl) ?>" class="btn btn-outline-secondary flex-fill"><i class="fas fa-times me-1"></i>Limpar</a>
            </div>
        </div>
    </form>

    <div class="resumo-card">
        <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Resumo</h4>
        <div class="row">
            <div class="col-md-3">
                <div class="resumo-item"><span>Total de Títulos</span><span class="resumo-value"><?= (int)($totais['total_titulos'] ?? 0) ?></span></div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item"><span>Valor Original</span><span class="resumo-value">R$ <?= number_format((float)($totais['valor_original'] ?? 0), 2, ',', '.') ?></span></div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item"><span>Valor Recebido</span><span class="resumo-value positive">R$ <?= number_format((float)($totais['valor_recebido'] ?? 0), 2, ',', '.') ?></span></div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item"><span>Valor em Aberto</span><span class="resumo-value negative">R$ <?= number_format((float)($totais['valor_aberto'] ?? 0), 2, ',', '.') ?></span></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Títulos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover custom-table mb-0">
                    <thead>
                        <tr>
                            <th>Doc.</th>
                            <th>Cliente</th>
                            <th>Descrição</th>
                            <th class="text-center">Emissão</th>
                            <th class="text-center">Vencimento</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Valor Original</th>
                            <th class="text-end">Valor Aberto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contas)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-3">Nenhum título encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($contas as $c): ?>
                                <tr>
                                    <td><?= (int)$c['id'] ?></td>
                                    <td><?= htmlspecialchars((string)($c['cliente_nome'] ?? '—')) ?></td>
                                    <td><?= htmlspecialchars((string)($c['descricao'] ?? '')) ?></td>
                                    <td class="text-center"><?= !empty($c['data_emissao']) ? date('d/m/Y', strtotime($c['data_emissao'])) : '—' ?></td>
                                    <td class="text-center"><?= !empty($c['data_vencimento']) ? date('d/m/Y', strtotime($c['data_vencimento'])) : '—' ?></td>
                                    <td class="text-center">
                                        <?php
                                        $badgeMap = ['PAGO' => 'success', 'PENDENTE' => 'warning', 'VENCIDO' => 'danger', 'PARCIALMENTE_PAGO' => 'info', 'CANCELADO' => 'secondary'];
                                        $badge = $badgeMap[$c['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars((string)$c['status']) ?></span>
                                    </td>
                                    <td class="text-end">R$ <?= number_format((float)$c['valor_original'], 2, ',', '.') ?></td>
                                    <td class="text-end <?= (float)$c['valor_aberto'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                                        R$ <?= number_format((float)$c['valor_aberto'], 2, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'pdf');
    window.open(window.location.pathname + '?' + params.toString(), '_blank');
}
</script>

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>



