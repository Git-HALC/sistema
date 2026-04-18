<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Modules\Relatorios\Reports\RelatorioMovimentacoesUsuario;

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
$usuarioId  = $_GET['usuario_id']  ?? '';
$selfUrl    = tenantUrl('admin/relatorios/financeiros/relatorio_movimentacoes_usuario.php');

$filtros = [
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'usuario_id'  => $usuarioId,
];

$relatorio = new RelatorioMovimentacoesUsuario($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados         = $relatorio->getData($filtros);
$totais        = $dados['totais']        ?? [];
$movimentacoes = $dados['movimentacoes'] ?? [];
$usuarios      = $relatorio->buscarUsuarios();

$page_title = 'Mov. por Usuário';
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
    .filter-section{background:var(--bg-secondary);padding:1.5rem;border-radius:10px;margin-bottom:2rem;border-left:4px solid #17a2b8;}
    .table-responsive{border-radius:10px;overflow:hidden;background:var(--bg-card);}
    .custom-table{background:var(--bg-card);color:var(--text-primary);margin-bottom:0;}
    .custom-table th{background:#343a40;color:white;}
    .custom-table tbody tr:nth-child(even){background:var(--bg-secondary);}
    .custom-table tbody tr:hover{background:rgba(23,162,184,0.1);}
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-user-check me-2"></i>Movimentações por Usuário</h1>
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
                <label class="form-label">Usuário</label>
                <select name="usuario_id" class="form-select">
                    <option value="">Todos os usuários</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $usuarioId == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-info flex-fill text-white"><i class="fas fa-search me-1"></i>Filtrar</button>
                <a href="<?= htmlspecialchars($selfUrl) ?>" class="btn btn-outline-secondary flex-fill"><i class="fas fa-times me-1"></i>Limpar</a>
            </div>
        </div>
    </form>

    <div class="resumo-card">
        <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Resumo do Período</h4>
        <div class="row">
            <div class="col-md-3">
                <div class="resumo-item"><span>Total de Títulos</span><span class="resumo-value"><?= (int)($totais['total_titulos'] ?? 0) ?></span></div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item"><span>Contas a Receber</span><span class="resumo-value positive">R$ <?= number_format((float)($totais['total_receber'] ?? 0), 2, ',', '.') ?></span></div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item"><span>Contas a Pagar</span><span class="resumo-value negative">R$ <?= number_format((float)($totais['total_pagar'] ?? 0), 2, ',', '.') ?></span></div>
            </div>
            <div class="col-md-3">
                <?php $saldo = (float)($totais['saldo'] ?? 0); ?>
                <div class="resumo-item"><span>Saldo Líquido</span><span class="resumo-value <?= $saldo >= 0 ? 'positive' : 'negative' ?>">R$ <?= number_format($saldo, 2, ',', '.') ?></span></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Movimentações</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover custom-table mb-0">
                    <thead>
                        <tr>
                            <th>Data da Baixa</th>
                            <th>Usuário</th>
                            <th class="text-center">Tipo</th>
                            <th>Identificador</th>
                            <th>Descrição</th>
                            <th class="text-center">Vencimento</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimentacoes)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Nenhuma movimentação encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($movimentacoes as $m): ?>
                                <tr>
                                    <td><?= !empty($m['data_baixa']) ? date('d/m/Y H:i', strtotime($m['data_baixa'])) : '—' ?></td>
                                    <td><?= htmlspecialchars((string)($m['usuario_nome'] ?? '—')) ?></td>
                                    <td class="text-center">
                                        <?php if ($m['tipo'] === 'receber'): ?>
                                            <span class="badge bg-success">CR</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">CP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($m['identificador'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($m['descricao'] ?? '')) ?></td>
                                    <td class="text-center"><?= !empty($m['data_vencimento']) ? date('d/m/Y', strtotime($m['data_vencimento'])) : '—' ?></td>
                                    <td class="text-end <?= $m['tipo'] === 'receber' ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
                                        R$ <?= number_format((float)($m['valor'] ?? 0), 2, ',', '.') ?>
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



