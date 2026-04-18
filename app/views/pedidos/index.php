<?php
$pedidoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_pedido_base_url', 'admin/pedidos.php')
    : tenantUrl('admin/pedidos.php');
$pedidoNovoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($pedidoBaseUrl, 'action=novo')
    : $pedidoBaseUrl . '?action=novo';
$pedidoListarUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($pedidoBaseUrl, 'action=listar')
    : $pedidoBaseUrl . '?action=listar';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-list me-2"></i>Lista de Pedidos
        </h1>
        <a href="<?= htmlspecialchars($pedidoNovoUrl) ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Novo Pedido
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?= htmlspecialchars($pedidoBaseUrl) ?>" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="listar">

                <div class="col-12 col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($filtros['busca'] ?? '') ?>"
                           class="form-control form-control-sm" placeholder="ID do pedido, observações...">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (($statusOpcoes ?? []) as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= (($filtros['status'] ?? '') === $status) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Data de</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio'] ?? '') ?>"
                           class="form-control form-control-sm" data-skip-datepicker="1">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Data até</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($filtros['data_fim'] ?? '') ?>"
                           class="form-control form-control-sm" data-skip-datepicker="1">
                </div>

                <div class="col-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="<?= htmlspecialchars($pedidoListarUrl) ?>" class="btn btn-outline-danger btn-sm" title="Limpar filtros">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <?php if (empty($pedidos)): ?>
                <div class="p-4 text-center text-muted">Nenhum pedido encontrado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <?php
                                    $statusPedido = strtoupper((string)($pedido['status'] ?? ''));
                                    $statusLabel = \App\Modules\Gestao_Pedidos\Pedido::STATUS_LABELS[$statusPedido] ?? $statusPedido;
                                    $statusBadge = \App\Modules\Gestao_Pedidos\Pedido::STATUS_BADGES[$statusPedido] ?? 'bg-secondary';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($pedido['numero'] ?? ($pedido['id'] ?? ''))) ?></td>
                                    <td><?= htmlspecialchars((string)($pedido['cliente_nome'] ?? '—')) ?></td>
                                    <td><?= htmlspecialchars((string)($pedido['data_pedido'] ?? '')) ?></td>
                                    <td class="text-end">R$ <?= number_format((float)($pedido['valor_total'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars((string)$statusLabel) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                                          <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=show&id=' . urlencode((string)($pedido['id'] ?? ''))) : $pedidoBaseUrl . '?action=show&id=' . urlencode((string)($pedido['id'] ?? ''))) ?>"
                                                              class="btn btn-outline-secondary" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <?php if (($pedido['status'] ?? '') !== 'FATURADO' && ($pedido['status'] ?? '') !== 'CANCELADO'): ?>
                                                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=invoice&id=' . urlencode((string)($pedido['id'] ?? ''))) : $pedidoBaseUrl . '?action=invoice&id=' . urlencode((string)($pedido['id'] ?? ''))) ?>"
                                                   class="btn btn-outline-success" title="Faturar">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (($pedido['status'] ?? '') === 'FATURADO'): ?>
                                                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=uninvoice&id=' . urlencode((string)($pedido['id'] ?? ''))) : $pedidoBaseUrl . '?action=uninvoice&id=' . urlencode((string)($pedido['id'] ?? ''))) ?>"
                                                   class="btn btn-outline-warning" title="Estornar faturamento"
                                                   data-confirm-action="estornar-faturamento-lista">
                                                    <i class="fas fa-undo"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (($pedido['status'] ?? '') !== 'CANCELADO' && ($pedido['status'] ?? '') !== 'FATURADO'): ?>
                                                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=cancel&id=' . urlencode((string)($pedido['id'] ?? ''))) : $pedidoBaseUrl . '?action=cancel&id=' . urlencode((string)($pedido['id'] ?? ''))) ?>"
                                                   class="btn btn-outline-danger" title="Cancelar"
                                                   data-confirm-action="cancelar-pedido-lista">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (($totalPaginas ?? 1) > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center py-2">
                <small class="text-muted"><?= (int)($total ?? 0) ?> pedido(s)</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if (($paginaAtual ?? 1) > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?action=listar&pagina=<?= (int)$paginaAtual - 1 ?>&<?= http_build_query(array_filter($filtros ?? [])) ?>">‹</a>
                            </li>
                        <?php endif; ?>
                        <li class="page-item active"><span class="page-link"><?= (int)($paginaAtual ?? 1) ?></span></li>
                        <?php if (($paginaAtual ?? 1) < ($totalPaginas ?? 1)): ?>
                            <li class="page-item">
                                <a class="page-link" href="?action=listar&pagina=<?= (int)$paginaAtual + 1 ?>&<?= http_build_query(array_filter($filtros ?? [])) ?>">›</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal === 'undefined') return;

    document.querySelectorAll('[data-confirm-action="estornar-faturamento-lista"], [data-confirm-action="cancelar-pedido-lista"]').forEach(function (botao) {
        botao.addEventListener('click', function (event) {
            event.preventDefault();

            const acao = botao.getAttribute('data-confirm-action');
            const isEstorno = acao === 'estornar-faturamento-lista';

            Swal.fire({
                title: isEstorno ? 'Estornar faturamento?' : 'Confirmar cancelamento?',
                text: isEstorno
                    ? 'Essa ação removerá as contas a receber geradas por este pedido. Deseja continuar?'
                    : 'Confirma cancelamento do pedido?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: isEstorno ? 'Estornar faturamento' : 'Cancelar pedido',
                cancelButtonText: 'Voltar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-warning mx-1',
                    cancelButton: 'btn btn-secondary mx-1'
                },
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    window.location.href = botao.getAttribute('href');
                }
            });
        });
    });
});
</script>
