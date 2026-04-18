<?php
require_once __DIR__ . '/../../../../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

use App\Modules\Relatorios\Reports\RelatorioEntradas;

if (!isset($_SESSION['user_id']) || !in_array((int)($_SESSION['user_role'] ?? 0), [1, 2, 3], true)) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$busca = trim((string)($_GET['busca'] ?? ''));
$somenteAtivos = isset($_GET['somente_ativos']) ? (int)$_GET['somente_ativos'] === 1 : true;
$dataInicio = trim((string)($_GET['data_inicio'] ?? ''));
$dataFim = trim((string)($_GET['data_fim'] ?? ''));
$selfUrl = tenantUrl('admin/relatorios/produto/relatorio_entradas.php');

$filtros = [
    'busca' => $busca,
    'somente_ativos' => $somenteAtivos,
    'data_inicio' => $dataInicio,
    'data_fim' => $dataFim,
];

$relatorio = new RelatorioEntradas($db);
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados = $relatorio->getData($filtros);
$resumo = $dados['resumo'] ?? [];
$entradas = $dados['entradas'] ?? [];

$page_title = 'Relatório de Entradas';
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
    @media (max-width: 768px) {
        .container-fluid { padding: 0.5rem; }
        .resumo-card { padding: 1rem; margin-bottom: 1rem; }
        .resumo-item { flex-direction: column; align-items: flex-start; margin: 0.5rem 0; padding: 0.75rem; }
        .resumo-value { font-size: 1.1rem; margin-top: 0.25rem; }
        .filter-section { padding: 1rem; }
        .filter-section .col-md-2, .filter-section .col-md-3,
        .filter-section .col-md-4, .filter-section .col-md-5 { flex: 1 1 100%; max-width: 100%; }
        .btn-lg { padding: 0.5rem 1rem; font-size: 0.875rem; }
        .h3 { font-size: 1.25rem; }
    }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-arrow-down me-2"></i>Relatório de Entradas de Estoque</h1>
        <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars(tenantUrl('admin/produtos.php?action=inventario')); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-boxes me-1"></i>Inventário
            </a>
            <a href="<?php echo htmlspecialchars($selfUrl . '?' . http_build_query(array_filter($filtros + ['export' => 'pdf']))); ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-file-pdf me-2"></i>Exportar PDF
            </a>
        </div>
    </div>

    <form method="GET" class="filter-section">
        <div class="row">
            <div class="col-md-4">
                <label for="busca" class="form-label">
                    <i class="fas fa-search me-1"></i>Busca produto
                </label>
                <input type="text" name="busca" id="busca" class="form-control" value="<?= htmlspecialchars($busca) ?>" placeholder="Nome ou código">
            </div>
            <div class="col-md-2">
                <label for="data_inicio" class="form-label">
                    <i class="fas fa-calendar-alt me-1"></i>Entradas de
                </label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?= htmlspecialchars($dataInicio) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-2">
                <label for="data_fim" class="form-label">
                    <i class="fas fa-calendar-alt me-1"></i>até
                </label>
                <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?= htmlspecialchars($dataFim) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-2 d-flex align-items-end pb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="somente_ativos" id="somente_ativos" value="1" <?= $somenteAtivos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="somente_ativos">Somente ativos</label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="fas fa-search me-1"></i>Filtrar
                </button>
                <a href="<?= htmlspecialchars($selfUrl) ?>" class="btn btn-outline-secondary flex-fill">
                    <i class="fas fa-times me-1"></i>Limpar
                </a>
            </div>
        </div>
    </form>

    <div class="resumo-card">
        <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Resumo de Entradas</h4>
        <div class="row">
            <div class="col-md-4">
                <div class="resumo-item">
                    <span>Movimentações</span>
                    <span class="resumo-value"><?= (int)($resumo['movimentacoes'] ?? 0) ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="resumo-item">
                    <span>Produtos</span>
                    <span class="resumo-value"><?= (int)($resumo['produtos'] ?? 0) ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="resumo-item">
                    <span>Quantidade total</span>
                    <span class="resumo-value positive"><?= rtrim(rtrim(number_format((float)($resumo['qtd_total'] ?? 0), 4, ',', '.'), '0'), ',') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>Movimentações de Entrada</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Produto</th>
                            <th class="text-end">Qtd</th>
                            <th class="text-end">Antes</th>
                            <th class="text-end">Depois</th>
                            <th>Usuário</th>
                            <th>Origem</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entradas)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Sem movimentações de entrada para o filtro atual.</td></tr>
                        <?php else: ?>
                            <?php foreach ($entradas as $entrada): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$entrada['created_at']))) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars((string)$entrada['nome']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string)($entrada['codigo'] ?: 'SEM COD')) ?></div>
                                    </td>
                                    <td class="text-end"><?= number_format((float)$entrada['quantidade'], 4, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format((float)$entrada['estoque_anterior'], 4, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format((float)$entrada['estoque_posterior'], 4, ',', '.') ?></td>
                                    <td><?= htmlspecialchars((string)$entrada['usuario_nome']) ?></td>
                                    <td><?= htmlspecialchars((string)$entrada['origem']) ?></td>
                                    <td><?= htmlspecialchars((string)($entrada['observacao'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>
