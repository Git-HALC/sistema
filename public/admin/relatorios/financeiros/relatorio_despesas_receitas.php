<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Modules\Relatorios\Reports\RelatorioDespesasReceitas;

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$dataInicio    = $_GET['data_inicio']    ?? date('Y-m-01');
$dataFim       = $_GET['data_fim']       ?? date('Y-m-t');
$detalhamento  = $_GET['detalhamento']   ?? 'mensal';
$selfUrl       = tenantUrl('admin/relatorios/financeiros/relatorio_despesas_receitas.php');

$filtros = [
    'data_inicio'   => $dataInicio,
    'data_fim'      => $dataFim,
    'detalhamento'  => $detalhamento,
];

$relatorio = new RelatorioDespesasReceitas($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados    = $relatorio->getData($filtros);
$totais   = $dados['totais']   ?? [];
$detalhes = $dados['detalhes'] ?? [];
$det      = $dados['detalhamento'] ?? 'mensal';

$page_title = 'Despesas vs Receitas';
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
    .filter-section{background:var(--bg-secondary);padding:1.5rem;border-radius:10px;margin-bottom:2rem;border-left:4px solid #667eea;}
    .table-responsive{border-radius:10px;overflow:hidden;background:var(--bg-card);}
    .custom-table{background:var(--bg-card);color:var(--text-primary);margin-bottom:0;}
    .custom-table th{background:#343a40;color:white;}
    .custom-table tbody tr:nth-child(even){background:var(--bg-secondary);}
    .custom-table tbody tr:hover{background:rgba(102,126,234,0.1);}
    .text-receita{color:#28a745!important;font-weight:600;}
    .text-despesa{color:#dc3545!important;font-weight:600;}
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-balance-scale me-2"></i>Demonstrativo de Despesas e Receitas</h1>
        <button class="btn btn-danger btn-lg" onclick="exportPDF()">
            <i class="fas fa-file-pdf me-2"></i>Exportar PDF
        </button>
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
                <label class="form-label">Detalhamento</label>
                <select name="detalhamento" class="form-select">
                    <option value="mensal" <?= $detalhamento === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                    <option value="trimestral" <?= $detalhamento === 'trimestral' ? 'selected' : '' ?>>Trimestral</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-search me-1"></i>Filtrar</button>
                <a href="<?= htmlspecialchars($selfUrl) ?>" class="btn btn-outline-secondary flex-fill"><i class="fas fa-times me-1"></i>Limpar</a>
            </div>
        </div>
    </form>

    <div class="resumo-card">
        <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Resumo do Período</h4>
        <div class="row">
            <div class="col-md-4">
                <div class="resumo-item"><span>Total de Receitas</span><span class="resumo-value positive">R$ <?= number_format((float)($totais['total_receitas'] ?? 0), 2, ',', '.') ?></span></div>
            </div>
            <div class="col-md-4">
                <div class="resumo-item"><span>Total de Despesas</span><span class="resumo-value negative">R$ <?= number_format((float)($totais['total_despesas'] ?? 0), 2, ',', '.') ?></span></div>
            </div>
            <div class="col-md-4">
                <?php $saldo = (float)($totais['saldo_liquido'] ?? 0); ?>
                <div class="resumo-item"><span>Saldo Líquido</span><span class="resumo-value <?= $saldo >= 0 ? 'positive' : 'negative' ?>">R$ <?= number_format($saldo, 2, ',', '.') ?></span></div>
            </div>
        </div>
    </div>

    <?php if (!empty($detalhes)): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Detalhamento <?= $det === 'mensal' ? 'Mensal' : 'Trimestral' ?></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover custom-table mb-0">
                    <thead>
                        <tr>
                            <th><?= $det === 'mensal' ? 'Mês/Ano' : 'Período' ?></th>
                            <th class="text-end">Receitas</th>
                            <th class="text-end">Despesas</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalhes as $d): ?>
                            <?php if ($det === 'mensal'): ?>
                                <?php $saldoM = (float)($d['saldo_mes'] ?? 0); ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['nome_mes'] ?? '') ?></td>
                                    <td class="text-end text-receita">R$ <?= number_format((float)($d['receitas_mes'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-end text-despesa">R$ <?= number_format((float)($d['despesas_mes'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-end <?= $saldoM >= 0 ? 'text-receita' : 'text-despesa' ?>">R$ <?= number_format($saldoM, 2, ',', '.') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php $saldoT = (float)($d['saldo_trimestre'] ?? 0); ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['periodo'] ?? '') ?></td>
                                    <td class="text-end text-receita">R$ <?= number_format((float)($d['receitas_trimestre'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-end text-despesa">R$ <?= number_format((float)($d['despesas_trimestre'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-end <?= $saldoT >= 0 ? 'text-receita' : 'text-despesa' ?>">R$ <?= number_format($saldoT, 2, ',', '.') ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function exportPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'pdf');
    window.open(window.location.pathname + '?' + params.toString(), '_blank');
}
</script>

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>



