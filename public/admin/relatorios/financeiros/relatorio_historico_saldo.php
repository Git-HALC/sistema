<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Modules\Relatorios\Reports\RelatorioHistoricoSaldo;

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim    = $_GET['data_fim']    ?? date('Y-m-d');
$contaId    = $_GET['conta_id']    ?? '';
$selfUrl    = tenantUrl('admin/relatorios/financeiros/relatorio_historico_saldo.php');

$filtros = [
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'conta_id'    => $contaId,
];

$relatorio = new RelatorioHistoricoSaldo($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados         = $relatorio->getData($filtros);
$contaInfo     = $dados['conta_info']    ?? [];
$saldos        = $dados['saldos']        ?? [];
$movimentacoes = $dados['movimentacoes'] ?? [];
$contas        = $relatorio->buscarContas();

$page_title = 'Histórico de Saldo';
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
    .filter-section{background:var(--bg-secondary);padding:1.5rem;border-radius:10px;margin-bottom:2rem;border-left:4px solid #fd7e14;}
    .table-responsive{border-radius:10px;overflow:hidden;background:var(--bg-card);}
    .custom-table{background:var(--bg-card);color:var(--text-primary);margin-bottom:0;}
    .custom-table th{background:#343a40;color:white;}
    .custom-table tbody tr:nth-child(even){background:var(--bg-secondary);}
    .custom-table tbody tr:hover{background:rgba(253,126,20,0.1);}
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-history me-2"></i>Extrato / Histórico de Saldo</h1>
        <button class="btn btn-danger btn-lg" onclick="exportPDF()" <?= empty($contaId) ? 'disabled' : '' ?>>
            <i class="fas fa-file-pdf me-2"></i>Exportar PDF
        </button>
    </div>

    <form method="GET" class="filter-section">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Conta <span class="text-danger">*</span></label>
                <select name="conta_id" class="form-select" required>
                    <option value="">Selecione uma conta...</option>
                    <?php foreach ($contas as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $contaId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['tipo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($dataInicio) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($dataFim) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-warning flex-fill text-dark"><i class="fas fa-search me-1"></i>Filtrar</button>
                <a href="<?= htmlspecialchars($selfUrl) ?>" class="btn btn-outline-secondary flex-fill"><i class="fas fa-times me-1"></i>Limpar</a>
            </div>
        </div>
    </form>

    <?php if (empty($contaId)): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Selecione uma conta para visualizar o extrato.</div>
    <?php else: ?>
        <div class="resumo-card">
            <h4 class="mb-4"><i class="fas fa-university me-2"></i><?= htmlspecialchars($contaInfo['nome'] ?? '') ?></h4>
            <div class="row">
                <div class="col-md-3">
                    <div class="resumo-item"><span>Saldo Inicial</span><span class="resumo-value">R$ <?= number_format((float)($saldos['saldo_inicial'] ?? 0), 2, ',', '.') ?></span></div>
                </div>
                <div class="col-md-3">
                    <div class="resumo-item"><span>Total Entradas</span><span class="resumo-value positive">R$ <?= number_format((float)($saldos['total_entradas'] ?? 0), 2, ',', '.') ?></span></div>
                </div>
                <div class="col-md-3">
                    <div class="resumo-item"><span>Total Saídas</span><span class="resumo-value negative">R$ <?= number_format((float)($saldos['total_saidas'] ?? 0), 2, ',', '.') ?></span></div>
                </div>
                <div class="col-md-3">
                    <div class="resumo-item"><span>Saldo Final</span><span class="resumo-value">R$ <?= number_format((float)($saldos['saldo_final'] ?? 0), 2, ',', '.') ?></span></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Extrato de Movimentações</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover custom-table mb-0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-end">Entrada (Crédito)</th>
                                <th class="text-end">Saída (Débito)</th>
                                <th class="text-end">Saldo Acumulado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movimentacoes)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Nenhuma movimentação no período.</td></tr>
                            <?php else: ?>
                                <?php foreach ($movimentacoes as $m): ?>
                                    <?php $isEntrada = $m['tipo'] === 'Entrada'; ?>
                                    <tr>
                                        <td><?= !empty($m['data_movimentacao']) ? date('d/m/Y', strtotime($m['data_movimentacao'])) : '—' ?></td>
                                        <td><?= htmlspecialchars((string)($m['descricao'] ?? '')) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $isEntrada ? 'success' : 'danger' ?>"><?= $isEntrada ? 'Entrada' : 'Saída' ?></span>
                                        </td>
                                        <td class="text-end text-success fw-bold">
                                            <?= $isEntrada ? 'R$ ' . number_format((float)$m['valor'], 2, ',', '.') : '' ?>
                                        </td>
                                        <td class="text-end text-danger fw-bold">
                                            <?= !$isEntrada ? 'R$ ' . number_format((float)$m['valor'], 2, ',', '.') : '' ?>
                                        </td>
                                        <td class="text-end fw-bold">R$ <?= number_format((float)($m['saldo_acumulado'] ?? 0), 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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



