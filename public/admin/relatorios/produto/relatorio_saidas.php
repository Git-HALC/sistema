<?php
require_once __DIR__ . '/../../../../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

use App\Modules\Relatorios\Reports\RelatorioSaidas;

if (!isset($_SESSION['user_id']) || !in_array((int)($_SESSION['user_role'] ?? 0), [1, 2, 3], true)) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$busca         = trim((string)($_GET['busca'] ?? ''));
$somenteAtivos = isset($_GET['somente_ativos']) ? (int)$_GET['somente_ativos'] === 1 : true;
$dataInicio    = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim       = $_GET['data_fim']    ?? date('Y-m-t');
$selfUrl       = tenantUrl('admin/relatorios/produto/relatorio_saidas.php');

$filtros = [
    'busca'          => $busca,
    'somente_ativos' => $somenteAtivos,
    'data_inicio'    => $dataInicio,
    'data_fim'       => $dataFim,
];

$relatorio = new RelatorioSaidas($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados      = $relatorio->getData($filtros);
$resumo     = $dados['resumo']     ?? [];
$saidas     = $dados['saidas']     ?? [];
$dataInicio = $dados['data_inicio'] ?? $dataInicio;
$dataFim    = $dados['data_fim']    ?? $dataFim;

$page_title = 'Relatório de Saídas';
include __DIR__ . '/../../../../public/includes/header.php';
\App\Support\PermissionGate::require('produtos');
?>

<div class="container-fluid">
    <style>
    :root {
        --bg-primary: #ffffff; --bg-secondary: #f8f9fa; --bg-card: #ffffff;
        --text-primary: #212529; --text-secondary: #6c757d; --border-color: #dee2e6;
        --shadow-sm: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        --shadow-md: 0 0.5rem 1rem rgba(0,0,0,0.15);
    }
    [data-theme="dark"] {
        --bg-primary: #1a1a1a; --bg-secondary: #2d2d2d; --bg-card: #2d2d2d;
        --text-primary: #ffffff; --text-secondary: #b0b0b0; --border-color: #404040;
        --shadow-sm: 0 0.125rem 0.25rem rgba(0,0,0,0.3);
        --shadow-md: 0 0.5rem 1rem rgba(0,0,0,0.4);
    }
    body { background-color: var(--bg-primary); color: var(--text-primary); transition: all 0.3s ease; }
    .resumo-card {
        background: var(--bg-card); color: var(--text-primary);
        border-radius: 15px; padding: 2rem; margin-bottom: 2rem;
        box-shadow: var(--shadow-md); border: 1px solid var(--border-color);
    }
    .resumo-item {
        display: flex; justify-content: space-between; align-items: center;
        margin: 1rem 0; padding: 1rem; background: var(--bg-secondary);
        border-radius: 10px; border: 1px solid var(--border-color); transition: all 0.3s ease;
    }
    .resumo-item:hover { transform: translateY(-2px); }
    .resumo-value { font-size: 1.5rem; font-weight: bold; }
    .positive { color: #28a745 !important; }
    .negative { color: #dc3545 !important; }
    .table-responsive { border-radius: 10px; overflow: hidden; box-shadow: var(--shadow-sm); background: var(--bg-card); }
    .custom-table { background: var(--bg-card); color: var(--text-primary); margin-bottom: 0; }
    .custom-table th { background: #343a40; color: white; border-color: var(--border-color); font-weight: 600; }
    .custom-table td { border-color: var(--border-color); transition: all 0.2s ease; }
    .custom-table tbody tr:nth-child(even) { background: var(--bg-secondary); }
    .custom-table tbody tr:hover { background: rgba(102,126,234,0.1); }
    .filter-section {
        background: var(--bg-secondary); padding: 1.5rem; border-radius: 10px;
        margin-bottom: 2rem; border-left: 4px solid #667eea; box-shadow: var(--shadow-sm);
    }
    .card { background: var(--bg-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
    .card:hover { box-shadow: var(--shadow-md); }
    .card-header { background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
    .form-control, .form-select { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); }
    .form-control:focus, .form-select:focus { background: var(--bg-card); border-color: #667eea; color: var(--text-primary); box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25); }
    .btn { transition: all 0.3s ease; }
    .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }
    .mobile-card {
        display: none; border: 1px solid var(--border-color); border-radius: 10px;
        margin-bottom: 1rem; box-shadow: var(--shadow-sm); background: var(--bg-card); transition: all 0.3s ease;
    }
    .mobile-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .mobile-card-header {
        background: var(--bg-secondary); padding: 1rem; border-bottom: 1px solid var(--border-color);
        border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;
    }
    .mobile-card-body { padding: 1rem; }
    .mobile-card-row {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .mobile-card-row:last-child { border-bottom: none; margin-bottom: 0; }
    .mobile-card-label { font-size: 0.875rem; color: var(--text-secondary); font-weight: 500; }
    .mobile-card-value { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); }
    @media (max-width: 768px) {
        .container-fluid { padding: 0.5rem; }
        .resumo-card { padding: 1rem; margin-bottom: 1rem; }
        .resumo-item { flex-direction: column; align-items: flex-start; margin: 0.5rem 0; padding: 0.75rem; }
        .resumo-value { font-size: 1.1rem; margin-top: 0.25rem; }
        .filter-section { padding: 1rem; }
        .filter-section .col-md-2, .filter-section .col-md-3,
        .filter-section .col-md-4, .filter-section .col-md-5 { flex: 1 1 100%; max-width: 100%; }
        .table-responsive { display: none; }
        .mobile-card { display: block; }
        .btn-lg { padding: 0.5rem 1rem; font-size: 0.875rem; }
        .h3 { font-size: 1.25rem; }
    }
    @media (max-width: 480px) {
        .mobile-card-header { padding: 0.75rem; flex-direction: column; align-items: flex-start; gap: 0.5rem; }
        .mobile-card-body { padding: 0.75rem; }
        .mobile-card-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
    }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-arrow-up me-2"></i>Relatório de Saídas (Pedidos Ativos)
        </h1>
        <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars(tenantUrl('admin/produtos.php')); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-box me-1"></i>Produtos
            </a>
            <button class="btn btn-primary btn-lg" onclick="exportPDF()">
                <i class="fas fa-file-pdf me-2"></i>Exportar PDF
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filter-section">
        <div class="row">
            <div class="col-md-3">
                <label for="busca" class="form-label">
                    <i class="fas fa-search me-1"></i>Busca produto
                </label>
                <input type="text" name="busca" id="busca" class="form-control"
                       value="<?= htmlspecialchars($busca) ?>" placeholder="Nome ou código">
            </div>
            <div class="col-md-2">
                <label for="data_inicio" class="form-label">
                    <i class="fas fa-calendar-alt me-1"></i>Saídas de
                </label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control"
                       value="<?= htmlspecialchars($dataInicio) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-2">
                <label for="data_fim" class="form-label">
                    <i class="fas fa-calendar-alt me-1"></i>até
                </label>
                <input type="date" name="data_fim" id="data_fim" class="form-control"
                       value="<?= htmlspecialchars($dataFim) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-2 d-flex align-items-end pb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="somente_ativos" name="somente_ativos" value="1" <?= $somenteAtivos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="somente_ativos">Somente ativos</label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="fas fa-search me-1"></i>Filtrar
                </button>
                <a href="<?= htmlspecialchars($selfUrl) ?>" class="btn btn-outline-secondary flex-fill">
                    <i class="fas fa-times me-1"></i>Limpar
                </a>
            </div>
        </div>
    </form>

    <!-- Resumo -->
    <div class="resumo-card">
        <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Resumo de Saídas</h4>
        <div class="row">
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Produtos com saída</span>
                    <span class="resumo-value"><?= $resumo['produtos'] ?? 0 ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Pedidos aprovados</span>
                    <span class="resumo-value"><?= $resumo['orcamentos'] ?? 0 ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Qtd saída</span>
                    <span class="resumo-value negative"><?= rtrim(rtrim(number_format((float)($resumo['qtd_saida'] ?? 0), 4, ',', '.'), '0'), ',') ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Valor saída</span>
                    <span class="resumo-value negative">R$ <?= number_format((float)($resumo['valor_saida'] ?? 0), 2, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Saídas (Pedidos Ativos)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover custom-table mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th class="text-center">Un.</th>
                            <th class="text-end">Qtd saída</th>
                            <th class="text-end">Valor saída</th>
                            <th class="text-end">Orçamentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($saidas)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Sem saídas no período.</td></tr>
                        <?php else: ?>
                            <?php foreach ($saidas as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$s['codigo']) ?></td>
                                    <td><?= htmlspecialchars((string)$s['nome']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars((string)$s['unidade']) ?></td>
                                    <td class="text-end"><?= rtrim(rtrim(number_format((float)$s['quantidade_saida'], 4, ',', '.'), '0'), ',') ?></td>
                                    <td class="text-end negative">R$ <?= number_format((float)$s['valor_saida'], 2, ',', '.') ?></td>
                                    <td class="text-end"><?= (int)$s['orcamentos'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobile cards -->
    <div class="mobile-cards-container mt-3">
        <?php foreach ($saidas as $s): ?>
            <div class="mobile-card">
                <div class="mobile-card-header">
                    <div>
                        <strong><?= htmlspecialchars((string)$s['nome']) ?></strong>
                        <small class="text-muted ms-2"><?= htmlspecialchars((string)$s['codigo']) ?></small>
                    </div>
                    <div class="negative fw-bold">R$ <?= number_format((float)$s['valor_saida'], 2, ',', '.') ?></div>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Unidade</span>
                        <span class="mobile-card-value"><?= htmlspecialchars((string)$s['unidade']) ?></span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Qtd saída</span>
                        <span class="mobile-card-value"><?= rtrim(rtrim(number_format((float)$s['quantidade_saida'], 4, ',', '.'), '0'), ',') ?></span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Orçamentos</span>
                        <span class="mobile-card-value"><?= (int)$s['orcamentos'] ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function exportPDF() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    for (let [key, value] of formData.entries()) {
        if (value !== '') params.set(key, value);
    }
    form.querySelectorAll('input[type="checkbox"]:not(:checked)').forEach(cb => {
        params.set(cb.name, '0');
    });
    params.set('export', 'pdf');
    window.open(window.location.pathname + '?' + params.toString(), '_blank');
}
</script>

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>



