<?php
defined('APP_PATH') || die('Acesso negado');

$notas = is_array($notas ?? null) ? $notas : [];
$totalNotas = (int)($totalNotas ?? $total ?? 0);
$paginaAtual = max(1, (int)($paginaAtual ?? 1));
$totalPaginas = max(1, (int)($totalPaginas ?? 1));
$filtros = is_array($filtros ?? null) ? $filtros : [];
$haFiltrosAtivos = !empty($filtros['data_inicio']) || !empty($filtros['data_fim']) || !empty($filtros['status']);
$statusBadgeMap = [
    'AUTORIZADA' => 'bg-success',
    'CANCELADA' => 'bg-secondary',
    'REJEITADA' => 'bg-danger',
    'PENDENTE' => 'bg-warning text-dark',
    'EMITIDA' => 'bg-info text-dark',
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0 text-gray-800">Notas Fiscais</h1>
        <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=faturaveis')); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Nova NF-e
            </a>
            <a href="<?php echo htmlspecialchars(tenantUrl('admin/empresa-dados.php')); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-cog me-1"></i> Configurar Empresa
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo ($_SESSION['mensagem']['tipo'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars((string)($_SESSION['mensagem']['texto'] ?? '')); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-body py-2">
            <form method="GET" action="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php')); ?>">
                <input type="hidden" name="action" value="listar">
                <div class="d-flex gap-2 flex-wrap align-items-end">
                    <div>
                        <label class="form-label small mb-1" for="data_inicio">Data início</label>
                        <input type="date" id="data_inicio" name="data_inicio" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)($filtros['data_inicio'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="data_fim">Data fim</label>
                        <input type="date" id="data_fim" name="data_fim" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)($filtros['data_fim'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="status">Status</label>
                        <select id="status" name="status" class="form-select form-select-sm" style="min-width:150px">
                            <option value="">Todos</option>
                            <?php foreach (['AUTORIZADA', 'CANCELADA', 'REJEITADA', 'PENDENTE', 'EMITIDA'] as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (($filtros['status'] ?? '') === $status) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="align-self-end d-flex gap-1">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-search me-1"></i> Filtrar
                        </button>
                        <?php if ($haFiltrosAtivos): ?>
                            <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=listar')); ?>" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px;">
                    <thead class="table-light">
                        <tr>
                            <th>Nº NF-E</th>
                            <th>ORIGEM</th>
                            <th>CLIENTE</th>
                            <th>DATA EMISSÃO</th>
                            <th>VALOR</th>
                            <th>STATUS</th>
                            <th>CHAVE</th>
                            <th class="text-center">AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($notas === []): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Nenhuma nota fiscal emitida.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notas as $nota): ?>
                                <?php
                                $status = strtoupper((string)($nota['status'] ?? ''));
                                $badgeClass = $statusBadgeMap[$status] ?? 'bg-secondary';
                                $chave = (string)($nota['chave_acesso'] ?? '');
                                $chaveCurta = $chave !== '' ? substr($chave, 0, 9) . '...' : '?';
                                $valor = (float)($nota['valor_total'] ?? 0);
                                $origemTipo = strtoupper((string)($nota['origem_tipo'] ?? 'PEDIDO'));
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)($nota['numero_nfe'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge <?php echo $origemTipo === 'SERVICO' ? 'bg-info text-dark' : 'bg-primary'; ?>">
                                            <?php echo htmlspecialchars($origemTipo); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($nota['cliente_nome'] ?? '')); ?></td>
                                    <td><?php echo !empty($nota['data_emissao']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$nota['data_emissao']))) : '?'; ?></td>
                                    <td><?php echo 'R$ ' . number_format($valor, 2, ',', '.'); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                    <td>
                                        <span class="text-muted font-monospace" style="font-size:11px;" title="<?php echo htmlspecialchars($chave); ?>">
                                            <?php echo htmlspecialchars($chaveCurta); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=visualizar&id=' . urlencode((string)($nota['id'] ?? '')))); ?>" class="btn btn-outline-primary" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=xml&id=' . urlencode((string)($nota['id'] ?? '')))); ?>" class="btn btn-outline-dark" title="Download XML" target="_blank">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($status === 'AUTORIZADA'): ?>
                                                <button type="button" class="btn btn-outline-danger btn-cancelar" title="Cancelar" data-id="<?php echo htmlspecialchars((string)($nota['id'] ?? '')); ?>" data-numero="<?php echo htmlspecialchars((string)($nota['numero_nfe'] ?? '')); ?>">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between py-2" style="font-size:12px;">
            <span class="text-muted"><?php echo $totalNotas; ?> nota(s) encontrada(s)</span>
            <?php if ($totalPaginas > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($paginaAtual > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filtros, ['action' => 'listar', 'pagina' => $paginaAtual - 1]))); ?>">&laquo;</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filtros, ['action' => 'listar', 'pagina' => $i]))); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($paginaAtual < $totalPaginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filtros, ['action' => 'listar', 'pagina' => $paginaAtual + 1]))); ?>">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const fiscalUrl = '<?= htmlspecialchars(
    function_exists('tenantUrl')
        ? tenantUrl('admin/fiscal.php')
        : (isset($tenant_slug)
            ? '/sistema_dm/public/' . $tenant_slug . '/admin/fiscal.php'
            : '/admin/fiscal.php')
) ?>';
</script>

<?php include __DIR__ . '/_modal_cancelar.php'; ?>
