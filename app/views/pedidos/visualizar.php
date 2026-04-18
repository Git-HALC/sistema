<?php /** @var \App\Modules\Gestao_Pedidos\Pedido $pedido */ ?>

<?php
$statusPedido = strtoupper((string)$pedido->status);
$statusLabel = \App\Modules\Gestao_Pedidos\Pedido::STATUS_LABELS[$statusPedido] ?? $statusPedido;
$statusBadge = \App\Modules\Gestao_Pedidos\Pedido::STATUS_BADGES[$statusPedido] ?? 'bg-secondary';

$subtotalItensPedido = 0.0;
foreach (($pedido->itens ?? []) as $itemCalc) {
    $subtotalItensPedido += (float)($itemCalc->valor_total_item ?? 0);
}
$valorDescontoPedido = max(0, round($subtotalItensPedido - (float)$pedido->valor_total, 2));
$percentualDescontoPedido = $subtotalItensPedido > 0
    ? round(($valorDescontoPedido / $subtotalItensPedido) * 100, 2)
    : 0.0;

$descontoOrigemPercentual = isset($pedido->orcamento['desconto_percentual'])
    ? (float)$pedido->orcamento['desconto_percentual']
    : null;
$pedidoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_pedido_base_url', 'admin/pedidos.php')
    : tenantUrl('admin/pedidos.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Pedido #<?= htmlspecialchars((string)($pedido->numero ?? $pedido->id)) ?></h1>
        <div class="d-flex gap-2">
            <?php if (!in_array($pedido->status, ['FATURADO', 'CANCELADO'], true)): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=editar&id=' . urlencode((string)$pedido->id)) : $pedidoBaseUrl . '?action=editar&id=' . urlencode((string)$pedido->id)) ?>"
                   class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i> Editar
                </a>
            <?php endif; ?>

            <?php if ($pedido->status === 'FATURADO'): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=uninvoice&id=' . urlencode((string)$pedido->id)) : $pedidoBaseUrl . '?action=uninvoice&id=' . urlencode((string)$pedido->id)) ?>"
                   class="btn btn-sm btn-warning"
                   data-confirm-action="estornar-faturamento">
                    <i class="fas fa-undo"></i> Estornar Faturamento
                </a>
            <?php endif; ?>

            <?php if ($pedido->status !== 'FATURADO' && $pedido->status !== 'CANCELADO'): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=invoice&id=' . urlencode((string)$pedido->id)) : $pedidoBaseUrl . '?action=invoice&id=' . urlencode((string)$pedido->id)) ?>"
                   class="btn btn-sm btn-success"
                   data-confirm-action="faturar">
                    <i class="fas fa-file-invoice-dollar"></i> Faturar
                </a>
            <?php endif; ?>

            <?php if ($pedido->status !== 'CANCELADO' && $pedido->status !== 'FATURADO'): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=cancel&id=' . urlencode((string)$pedido->id)) : $pedidoBaseUrl . '?action=cancel&id=' . urlencode((string)$pedido->id)) ?>"
                   class="btn btn-sm btn-danger"
                   data-confirm-action="cancelar">
                    <i class="fas fa-ban"></i> Cancelar
                </a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($pedidoBaseUrl) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Cliente</div>
                            <div class="fw-semibold"><?= htmlspecialchars((string)($pedido->cliente['nome'] ?? '—')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Status</div>
                            <span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars((string)$statusLabel) ?></span>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Data do pedido</div>
                            <div><?= htmlspecialchars((string)$pedido->data_pedido) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Entrega prevista</div>
                            <div><?= htmlspecialchars((string)($pedido->data_entrega_prevista ?? '—')) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Faturamento</div>
                            <div><?= htmlspecialchars((string)($pedido->data_faturamento ?? '—')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Criado por</div>
                            <div><?= htmlspecialchars((string)($usuarioCriador ?? 'Não identificado')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Observações</div>
                            <div><?= nl2br(htmlspecialchars((string)($pedido->observacoes ?? '—'))) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($subtotalItensPedido > 0): ?>
                        <div class="text-muted small">Subtotal dos itens</div>
                        <div class="mb-1">R$ <?= number_format($subtotalItensPedido, 2, ',', '.') ?></div>
                    <?php endif; ?>

                    <?php if ($valorDescontoPedido > 0): ?>
                        <div class="text-muted small">Desconto aplicado</div>
                        <div class="mb-1 text-danger">- R$ <?= number_format($valorDescontoPedido, 2, ',', '.') ?> (<?= number_format($percentualDescontoPedido, 2, ',', '.') ?>%)</div>
                    <?php endif; ?>

                    <div class="text-muted small">Total do pedido</div>
                    <div class="h4 mb-0 text-primary">R$ <?= number_format((float)$pedido->valor_total, 2, ',', '.') ?></div>
                    <?php if (!empty($pedido->orcamento_id)): ?>
                        <div class="mt-2 small text-muted">Origem orçamento: #<?= htmlspecialchars((string)$pedido->orcamento_id) ?></div>
                        <?php if ($descontoOrigemPercentual !== null): ?>
                            <div class="small text-muted">Desconto do orçamento: <?= number_format($descontoOrigemPercentual, 2, ',', '.') ?>%</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-2 fw-semibold">Itens do Pedido</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Produto</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-end">Valor Unit.</th>
                            <th class="text-end">Total Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedido->itens)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Nenhum item.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedido->itens as $idx => $item): ?>
                                <tr>
                                    <td><?= (int)$idx + 1 ?></td>
                                    <td><?= htmlspecialchars((string)($item->nome_produto !== '' ? $item->nome_produto : ('Produto #' . ($item->produto_id ?? '—')))) ?></td>
                                    <td class="text-end"><?= number_format((float)$item->quantidade, 4, ',', '.') ?></td>
                                    <td class="text-end">R$ <?= number_format((float)$item->valor_unitario, 4, ',', '.') ?></td>
                                    <td class="text-end">R$ <?= number_format((float)$item->valor_total_item, 2, ',', '.') ?></td>
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
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal === 'undefined') return;

    const botoes = document.querySelectorAll('[data-confirm-action]');
    botoes.forEach(function (botao) {
        botao.addEventListener('click', function (event) {
            event.preventDefault();

            const acao = botao.getAttribute('data-confirm-action');
            const isFaturar = acao === 'faturar';
            const isEstornoFaturamento = acao === 'estornar-faturamento';

            Swal.fire({
                title: isFaturar
                    ? 'Confirmar faturamento?'
                    : (isEstornoFaturamento ? 'Estornar faturamento?' : 'Confirmar cancelamento?'),
                text: isFaturar
                    ? 'Confirmar faturamento deste pedido?'
                    : (isEstornoFaturamento
                        ? 'Essa ação removerá as contas a receber geradas por este pedido. Deseja continuar?'
                        : 'Confirma cancelamento do pedido?'),
                icon: isFaturar ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: isFaturar
                    ? 'Faturar'
                    : (isEstornoFaturamento ? 'Estornar faturamento' : 'Cancelar pedido'),
                cancelButtonText: 'Voltar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: isFaturar ? 'btn btn-success mx-1' : 'btn btn-warning mx-1',
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
