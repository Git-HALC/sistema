<?php
require_once __DIR__ . '/../../../../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

use App\Modules\Relatorios\Reports\RelatorioAuditoria;

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

if ((int)($_SESSION['user_role'] ?? 0) !== 1) {
    header('Location: ' . tenantUrl('admin/dashboard.php?erro=sem_permissao'));
    exit();
}

$filtros = [
    'usuario_id'  => isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int)$_GET['usuario_id'] : null,
    'modulo'      => trim((string)($_GET['modulo']      ?? '')),
    'acao'        => trim((string)($_GET['acao']        ?? '')),
    'data_inicio' => trim((string)($_GET['data_inicio'] ?? date('Y-m-01'))),
    'data_fim'    => trim((string)($_GET['data_fim']    ?? date('Y-m-d'))),
    'busca'       => trim((string)($_GET['busca']       ?? '')),
];

$relatorio = new RelatorioAuditoria($db);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $relatorio->exportarPdf($filtros);
}

$dados    = $relatorio->getData($filtros);
$logs     = $dados['logs']     ?? [];
$usuarios = $dados['usuarios'] ?? [];
$totais   = $dados['totais']   ?? ['registros' => 0, 'usuarios' => 0, 'modulos' => 0, 'acoes' => 0];

$page_title = 'Relatorio de Atividade de Usuarios';
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
    .mobile-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-weight: 600; }
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
            <i class="fas fa-history me-2"></i>Relatorio de Usuarios (Auditoria)
        </h1>
        <button class="btn btn-primary btn-lg" onclick="exportPDF()">
            <i class="fas fa-file-pdf me-2"></i>Exportar PDF
        </button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filter-section">
        <div class="row">
            <div class="col-md-2">
                <label for="usuario_id" class="form-label">
                    <i class="fas fa-user me-1"></i>Usuario
                </label>
                <select name="usuario_id" id="usuario_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= ((int)($filtros['usuario_id'] ?? 0) === (int)$u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$u['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="modulo" class="form-label">
                    <i class="fas fa-cube me-1"></i>Modulo
                </label>
                <input type="text" name="modulo" id="modulo" class="form-control"
                       value="<?= htmlspecialchars((string)$filtros['modulo']) ?>" placeholder="Ex.: orcamentos">
            </div>
            <div class="col-md-2">
                <label for="acao" class="form-label">
                    <i class="fas fa-bolt me-1"></i>Acao
                </label>
                <input type="text" name="acao" id="acao" class="form-control"
                       value="<?= htmlspecialchars((string)$filtros['acao']) ?>" placeholder="Ex.: CRIAR">
            </div>
            <div class="col-md-2">
                <label for="data_inicio" class="form-label">
                    <i class="fas fa-calendar-alt me-1"></i>Data inicial
                </label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control"
                       value="<?= htmlspecialchars((string)$filtros['data_inicio']) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-2">
                <label for="data_fim" class="form-label">
                    <i class="fas fa-calendar-alt me-1"></i>Data final
                </label>
                <input type="date" name="data_fim" id="data_fim" class="form-control"
                       value="<?= htmlspecialchars((string)$filtros['data_fim']) ?>" data-skip-datepicker="1">
            </div>
            <div class="col-md-2">
                <label for="busca" class="form-label">
                    <i class="fas fa-search me-1"></i>Busca livre
                </label>
                <input type="text" name="busca" id="busca" class="form-control"
                       value="<?= htmlspecialchars((string)$filtros['busca']) ?>" placeholder="descricao, entidade...">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Filtrar
                </button>
                <a href="<?php echo htmlspecialchars(tenantUrl('admin/relatorios/usuarios/atividade.php')); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Limpar
                </a>
            </div>
        </div>
    </form>

    <!-- Resumo -->
    <div class="resumo-card">
        <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Resumo</h4>
        <div class="row">
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Registros</span>
                    <span class="resumo-value"><?= $totais['registros'] ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Usuarios</span>
                    <span class="resumo-value"><?= $totais['usuarios'] ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Modulos</span>
                    <span class="resumo-value"><?= $totais['modulos'] ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="resumo-item">
                    <span>Tipos de Acao</span>
                    <span class="resumo-value"><?= $totais['acoes'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Log de Atividades</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover custom-table mb-0">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Usuario</th>
                            <th>Modulo</th>
                            <th>Acao</th>
                            <th>Entidade</th>
                            <th>Descricao</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Nenhuma acao encontrada para os filtros selecionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= !empty($log['created_at']) ? date('d/m/Y H:i:s', strtotime((string)$log['created_at'])) : '—' ?></td>
                                    <td><?= htmlspecialchars((string)($log['usuario_nome'] ?? '—')) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars((string)($log['modulo'] ?? '')) ?></span></td>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars((string)($log['acao'] ?? '')) ?></span></td>
                                    <td>
                                        <?= htmlspecialchars((string)($log['entidade'] ?? '')) ?>
                                        <?= !empty($log['entidade_id']) ? '<small class="text-muted">#' . (int)$log['entidade_id'] . '</small>' : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($log['descricao'] ?? '')) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars((string)($log['ip'] ?? '')) ?></small></td>
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
        <?php foreach ($logs as $log): ?>
            <div class="mobile-card">
                <div class="mobile-card-header">
                    <div>
                        <strong><?= !empty($log['created_at']) ? date('d/m/Y H:i', strtotime((string)$log['created_at'])) : '—' ?></strong>
                        <span class="mobile-badge badge bg-dark ms-2"><?= htmlspecialchars((string)($log['acao'] ?? '')) ?></span>
                    </div>
                    <small class="text-muted"><?= htmlspecialchars((string)($log['ip'] ?? '')) ?></small>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Usuario</span>
                        <span class="mobile-card-value"><?= htmlspecialchars((string)($log['usuario_nome'] ?? '—')) ?></span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Modulo</span>
                        <span class="mobile-card-value"><?= htmlspecialchars((string)($log['modulo'] ?? '')) ?></span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Entidade</span>
                        <span class="mobile-card-value">
                            <?= htmlspecialchars((string)($log['entidade'] ?? '')) ?>
                            <?= !empty($log['entidade_id']) ? '#' . (int)$log['entidade_id'] : '' ?>
                        </span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Descricao</span>
                        <span class="mobile-card-value"><?= htmlspecialchars((string)($log['descricao'] ?? '')) ?></span>
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



