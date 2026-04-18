<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Modules\Relatorios\Reports\RelatorioPedidos;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

\App\Support\PermissionGate::init($db);
\App\Support\PermissionGate::require('rel_pedidos');

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
$status     = $_GET['status']      ?? 'todos';

$filtros = [
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'status'      => $status,
];

$relatorio = new RelatorioPedidos($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados      = $relatorio->getData($filtros);
$resumo     = $dados['resumo'];
$pedidos    = $dados['pedidos'];
$dataInicio = $dados['data_inicio'];
$dataFim    = $dados['data_fim'];

$statusOpcoes = [
    'todos'       => 'Todos',
    'RASCUNHO'    => 'Rascunho',
    'PENDENTE'    => 'Pendente',
    'EM_PROCESSO' => 'Em Processo',
    'APROVADO'    => 'Aprovado',
    'FATURADO'    => 'Faturado',
    'CONCLUIDO'   => 'Concluído',
    'CANCELADO'   => 'Cancelado',
];

$statusBadge = [
    'RASCUNHO'    => 'secondary',
    'PENDENTE'    => 'warning',
    'EM_PROCESSO' => 'info',
    'APROVADO'    => 'primary',
    'FATURADO'    => 'success',
    'CONCLUIDO'   => 'success',
    'CANCELADO'   => 'danger',
];

$page_title = 'Relatório de Pedidos';
include __DIR__ . '/../../../../public/includes/header.php';
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
        .resumo-card {
            background: var(--bg-card);
            color: var(--text-primary);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        .resumo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1rem;
            background: var(--bg-secondary);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 0.5rem;
        }
        .resumo-value { font-size: 1.05rem; font-weight: 700; }
        .filter-section {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            margin-bottom: 1.5rem;
        }
        .custom-table th {
            background: #343a40;
            color: white;
            font-weight: 600;
            border-color: #454d55;
        }
        .status-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .mobile-card { display: none; }
        @media (max-width: 768px) {
            .table-responsive { display: none; }
            .mobile-card { display: block; }
            .mc-label { font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }
            .mc-value { font-size: 0.95rem; color: var(--text-primary); }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-clipboard-list me-2"></i>Relatório de Pedidos</h1>
        <button class="btn btn-primary btn-lg" onclick="exportPDF()">
            <i class="fas fa-file-pdf me-2"></i>Exportar PDF
        </button>
    </div>

    <form method="GET" class="filter-section">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <?php foreach ($statusOpcoes as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Data início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($dataInicio) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Data fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($dataFim) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="fas fa-filter me-1"></i>Filtrar
                </button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </div>
    </form>

    <div class="resumo-card">
        <h4 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Resumo do Período</h4>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Total de Pedidos</span>
                    <span class="resumo-value"><?= $resumo['total'] ?></span>
                </div>
                <div class="resumo-item">
                    <span>Valor Total</span>
                    <span class="resumo-value">R$ <?= number_format($resumo['valor_total'], 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Valor Médio</span>
                    <span class="resumo-value">R$ <?= number_format($resumo['valor_medio'], 2, ',', '.') ?></span>
                </div>
                <div class="resumo-item">
                    <span>Pendentes</span>
                    <span class="resumo-value"><?= $resumo['pendente'] ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Em Processo</span>
                    <span class="resumo-value"><?= $resumo['em_processo'] ?></span>
                </div>
                <div class="resumo-item">
                    <span>Faturados</span>
                    <span class="resumo-value"><?= $resumo['faturado'] ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Concluídos</span>
                    <span class="resumo-value"><?= $resumo['concluido'] ?></span>
                </div>
                <div class="resumo-item">
                    <span>Cancelados</span>
                    <span class="resumo-value"><?= $resumo['cancelado'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Pedidos</h5>
            <small class="opacity-75"><?= count($pedidos) ?> registro(s) encontrado(s)</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover custom-table mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:55px">#</th>
                            <th>Cliente</th>
                            <th class="text-center" style="width:110px">Status</th>
                            <th class="text-center" style="width:100px">Data Pedido</th>
                            <th class="text-center" style="width:110px">Entrega Prev.</th>
                            <th class="text-center" style="width:110px">Entrega Real.</th>
                            <th class="text-end" style="width:120px">Valor Total</th>
                            <th>Responsável</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x d-block mb-2 opacity-50"></i>
                                Nenhum pedido encontrado para o filtro selecionado.
                            </td></tr>
                        <?php else: ?>
                            <?php
                            $totalValor = 0;
                            foreach ($pedidos as $p):
                                $totalValor += (float)($p['valor_total'] ?? 0);
                                $st = strtoupper($p['status'] ?? '');
                                $badge = $statusBadge[$st] ?? 'secondary';
                                $label = $statusOpcoes[$st] ?? $st;
                            ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= htmlspecialchars((string)$p['numero']) ?></td>
                                    <td><?= htmlspecialchars((string)$p['cliente']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($label) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= $p['data_pedido'] ? date('d/m/Y', strtotime($p['data_pedido'])) : '—' ?>
                                    </td>
                                    <td class="text-center">
                                        <?= $p['data_entrega_prevista'] ? date('d/m/Y', strtotime($p['data_entrega_prevista'])) : '—' ?>
                                    </td>
                                    <td class="text-center">
                                        <?= $p['data_entrega_realizada'] ? date('d/m/Y', strtotime($p['data_entrega_realizada'])) : '—' ?>
                                    </td>
                                    <td class="text-end fw-bold">R$ <?= number_format((float)$p['valor_total'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars((string)$p['usuario']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-dark fw-bold">
                                <td colspan="6" class="text-end">Total</td>
                                <td class="text-end">R$ <?= number_format($totalValor, 2, ',', '.') ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-card p-3">
                <?php foreach ($pedidos as $p):
                    $st = strtoupper($p['status'] ?? '');
                    $badge = $statusBadge[$st] ?? 'secondary';
                    $label = $statusOpcoes[$st] ?? $st;
                ?>
                <div class="card mb-2 shadow-sm">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-bold"><?= htmlspecialchars((string)$p['cliente']) ?>
                                <small class="text-muted">#<?= htmlspecialchars((string)$p['numero']) ?></small>
                            </div>
                            <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($label) ?></span>
                        </div>
                        <div class="row g-1 mt-1">
                            <div class="col-6">
                                <div class="mc-label">Valor</div>
                                <div class="mc-value fw-bold">R$ <?= number_format((float)$p['valor_total'], 2, ',', '.') ?></div>
                            </div>
                            <div class="col-6">
                                <div class="mc-label">Data</div>
                                <div class="mc-value"><?= $p['data_pedido'] ? date('d/m/Y', strtotime($p['data_pedido'])) : '—' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
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
    params.set('export', 'pdf');
    window.open(window.location.pathname + '?' + params.toString(), '_blank');
}
</script>

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>



