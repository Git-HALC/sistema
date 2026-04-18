<?php /** @var \App\Modules\Servicos\Servico $servico */ ?>
<?php
$statusServico = strtoupper((string)$servico->status);
$statusLabel = \App\Modules\Servicos\Servico::STATUS_LABELS[$statusServico] ?? $statusServico;
$statusBadge = \App\Modules\Servicos\Servico::STATUS_BADGES[$statusServico] ?? 'bg-secondary';

$servicoValor = (float)($servico->servicoValor ?? 0);
$itensProdutos = is_array($servico->itens ?? null) ? $servico->itens : [];
$subtotalProdutos = 0.0;
foreach ($itensProdutos as $itemProduto) {
    $subtotalProdutos += (float)($itemProduto->valorTotalItem ?? 0);
}
$descontoTipo = strtoupper((string)($servico->descontoTipo ?? ''));
$descontoValor = (float)($servico->descontoValor ?? 0);
$valorTotalServico = (float)$servico->valorTotal;
if ($servicoValor <= 0 && ($valorTotalServico > 0 || $subtotalProdutos > 0)) {
    $subtotalReconstruido = $valorTotalServico;
    if ($descontoTipo === 'PERCENTUAL' && $descontoValor > 0 && $descontoValor < 100) {
        $subtotalReconstruido = $valorTotalServico / (1 - ($descontoValor / 100));
    } elseif ($descontoTipo === 'VALOR' && $descontoValor > 0) {
        $subtotalReconstruido = $valorTotalServico + $descontoValor;
    }
    $servicoValor = max(0, round($subtotalReconstruido - $subtotalProdutos, 4));
}
$subtotalItensServico = round($servicoValor + $subtotalProdutos, 4);
$descontoAplicado = 0.0;
if ($descontoTipo === 'PERCENTUAL') {
    $descontoAplicado = round($subtotalItensServico * ($descontoValor / 100), 4);
} elseif ($descontoTipo === 'VALOR') {
    $descontoAplicado = round($descontoValor, 4);
}

$veiculoServico = trim((string)(
    ($servico->placa ?? '-')
    . ((($servico->placa ?? '-') !== '-' && ($servico->modeloVeiculo ?? '-') !== '-') ? ' - ' : '')
    . ($servico->modeloVeiculo ?? '-')
));
$faturamento = is_array($faturamento ?? null) ? $faturamento : [];
$notaFiscalProduto = $notaFiscalProduto ?? null;
$notaFiscalServico = $notaFiscalServico ?? null;
$statusNotaProduto = strtoupper((string)($notaFiscalProduto?->status ?? ''));
$statusNotaProdutoBadge = match ($statusNotaProduto) {
    'AUTORIZADA' => 'bg-success',
    'CANCELADA' => 'bg-secondary',
    'REJEITADA' => 'bg-danger',
    'PENDENTE' => 'bg-warning text-dark',
    'EMITIDA' => 'bg-info text-dark',
    default => 'bg-secondary',
};
$statusNotaServico = strtoupper((string)($notaFiscalServico?->status ?? ''));
$statusNotaServicoBadge = match ($statusNotaServico) {
    'ENVIADA' => 'bg-success',
    'CANCELADA' => 'bg-secondary',
    'ERRO' => 'bg-danger',
    'PENDENTE' => 'bg-warning text-dark',
    default => 'bg-secondary',
};
$podeEmitirNfse = $servico->status === \App\Modules\Servicos\Servico::STATUS_FATURADO && $servicoValor > 0;
$csrfToken = \App\Support\CsrfProtection::token();
$servicoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_servico_base_url', 'admin/servicos.php')
    : tenantUrl('admin/servicos.php');

$itensServicoTabela = [[
    'nome' => (string)$servico->servicoNome,
    'quantidade' => 1.0,
    'valor_unitario' => $servicoValor,
    'valor_total' => $servicoValor,
    'tipo' => 'SERVICO',
]];

foreach ($itensProdutos as $itemProduto) {
    $itensServicoTabela[] = [
        'nome' => (string)$itemProduto->nomeProduto,
        'quantidade' => (float)$itemProduto->quantidade,
        'valor_unitario' => (float)$itemProduto->valorUnitario,
        'valor_total' => (float)$itemProduto->valorTotalItem,
        'tipo' => 'PRODUTO',
    ];
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Serviço #<?= htmlspecialchars((string)($servico->numero ?? $servico->id)) ?></h1>
        <div class="d-flex gap-2">
            <?php if ($servico->status === \App\Modules\Servicos\Servico::STATUS_FATURADO): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($servicoBaseUrl, 'action=estornar&id=' . urlencode((string)$servico->id)) : $servicoBaseUrl . '?action=estornar&id=' . urlencode((string)$servico->id)) ?>"
                   class="btn btn-sm btn-warning"
                   data-confirm-action="estornar-servico">
                    <i class="fas fa-undo"></i> Estornar Faturamento
                </a>
            <?php endif; ?>

            <?php if ($servico->status !== \App\Modules\Servicos\Servico::STATUS_FATURADO && $servico->status !== \App\Modules\Servicos\Servico::STATUS_CANCELADO): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($servicoBaseUrl, 'action=faturar&id=' . urlencode((string)$servico->id)) : $servicoBaseUrl . '?action=faturar&id=' . urlencode((string)$servico->id)) ?>"
                   class="btn btn-sm btn-success"
                   data-confirm-action="faturar-servico">
                    <i class="fas fa-file-invoice-dollar"></i> Faturar
                </a>
            <?php endif; ?>

            <?php if ($servico->status !== \App\Modules\Servicos\Servico::STATUS_CANCELADO && $servico->status !== \App\Modules\Servicos\Servico::STATUS_FATURADO): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($servicoBaseUrl, 'action=cancelar&id=' . urlencode((string)$servico->id)) : $servicoBaseUrl . '?action=cancelar&id=' . urlencode((string)$servico->id)) ?>"
                   class="btn btn-sm btn-danger"
                   data-confirm-action="cancelar-servico">
                    <i class="fas fa-ban"></i> Cancelar
                </a>
            <?php endif; ?>

            <?php if ($servico->status !== \App\Modules\Servicos\Servico::STATUS_CANCELADO && $servico->status !== \App\Modules\Servicos\Servico::STATUS_FATURADO): ?>
                <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($servicoBaseUrl, 'action=editar&id=' . urlencode((string)$servico->id)) : $servicoBaseUrl . '?action=editar&id=' . urlencode((string)$servico->id)) ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit"></i> Editar
                </a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($servicoBaseUrl, 'action=listar') : $servicoBaseUrl . '?action=listar') ?>" class="btn btn-sm btn-outline-secondary">
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
                            <div class="fw-semibold"><?= htmlspecialchars((string)$servico->nomeCliente) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Status</div>
                            <span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars((string)$statusLabel) ?></span>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Data do Serviço</div>
                            <div><?= htmlspecialchars((string)$servico->dataServicoFormatada()) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Veículo</div>
                            <div><?= htmlspecialchars($veiculoServico !== '-' ? $veiculoServico : '-') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Faturamento</div>
                            <div><?= htmlspecialchars((string)($servico->dataFaturamento ?? '-')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Criado por</div>
                            <div><?= htmlspecialchars((string)($usuarioCriador ?? 'Não identificado')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Telefone</div>
                            <div><?= htmlspecialchars((string)($servico->telefoneCliente ?? '-')) ?></div>
                        </div>
                        <?php if (!empty($faturamento['forma_conta']) || !empty($faturamento['forma_recebimento'])): ?>
                            <div class="col-md-6">
                                <div class="text-muted small">Forma de PGTO. da conta</div>
                                <div><?= htmlspecialchars((string)($faturamento['forma_conta'] ?? '-')) ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <div class="text-muted small">Observações</div>
                            <div><?= nl2br(htmlspecialchars((string)($servico->observacoes ?? '-'))) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Subtotal do Serviço</div>
                    <div class="mb-1">R$ <?= number_format($subtotalItensServico, 2, ',', '.') ?></div>

                    <?php if ($descontoAplicado > 0): ?>
                        <div class="text-muted small">Desconto</div>
                        <div class="mb-1">
                            - R$ <?= number_format($descontoAplicado, 2, ',', '.') ?>
                            <?php if ($descontoTipo === 'PERCENTUAL'): ?>
                                <span class="small text-muted">(<?= number_format($descontoValor, 2, ',', '.') ?>%)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="text-muted small">Total do Serviço</div>
                    <div class="h4 mb-0 text-primary">R$ <?= number_format((float)$servico->valorTotal, 2, ',', '.') ?></div>

                    <?php if (!empty($servico->orcamentoId)): ?>
                        <div class="mt-2 small text-muted">Origem orçamento: #<?= htmlspecialchars((string)$servico->orcamentoId) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($faturamento['conta_receber_id'])): ?>
                        <div class="mt-2 small text-muted">Conta a receber: #<?= (int)$faturamento['conta_receber_id'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($servico->status === \App\Modules\Servicos\Servico::STATUS_FATURADO): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header py-2 fw-semibold">Documentos Fiscais</div>
            <div class="card-body">
                <?php if ($subtotalProdutos > 0): ?>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="text-muted small">NF-e de Produto</div>
                            <?php if ($notaFiscalProduto !== null): ?>
                                <div class="fw-semibold">
                                    Nota nº <?= htmlspecialchars((string)$notaFiscalProduto->numero_nfe) ?>
                                    <span class="badge <?= htmlspecialchars($statusNotaProdutoBadge) ?> ms-2">
                                        <?= htmlspecialchars((string)$statusNotaProduto) ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="fw-semibold">Ainda não gerada</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($notaFiscalProduto !== null): ?>
                            <a href="<?= htmlspecialchars(tenantUrl('admin/fiscal.php?action=visualizar&id=' . urlencode((string)$notaFiscalProduto->id))) ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-file-invoice"></i> Ver NF-e
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted">Este serviço não possui itens de produto para NF-e de produto.</div>
                <?php endif; ?>

                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="text-muted small">NFS-e do Serviço</div>
                        <?php if ($notaFiscalServico !== null): ?>
                            <div class="fw-semibold">
                                Nota nº <?= htmlspecialchars((string)($notaFiscalServico->numero_nfse ?? 'Pendente')) ?>
                                <span class="badge <?= htmlspecialchars($statusNotaServicoBadge) ?> ms-2">
                                    <?= htmlspecialchars((string)$statusNotaServico) ?>
                                </span>
                            </div>
                            <div class="small text-muted mt-1">RPS <?= htmlspecialchars((string)$notaFiscalServico->numero_rps) ?></div>
                            <?php if ($statusNotaServico === 'ERRO' && (string)$notaFiscalServico->erro_mensagem !== ''): ?>
                                <div class="small text-danger mt-1"><?= htmlspecialchars((string)$notaFiscalServico->erro_mensagem) ?></div>
                            <?php endif; ?>
                        <?php elseif ($podeEmitirNfse): ?>
                            <div class="fw-semibold">Ainda não gerada</div>
                        <?php else: ?>
                            <div class="fw-semibold text-muted">Serviço sem valor tributável para NFS-e</div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($notaFiscalServico !== null): ?>
                            <a href="<?= htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=detalhe&id=' . urlencode((string)$notaFiscalServico->id))) ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-file-signature"></i> Ver NFS-e
                            </a>
                            <a href="<?= htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=pdf&id=' . urlencode((string)$notaFiscalServico->id))) ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                            <?php if ($statusNotaServico === 'ERRO' || $statusNotaServico === 'PENDENTE'): ?>
                                <form method="post" action="<?= htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=reenviar')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$notaFiscalServico->id) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-paper-plane"></i> Reenviar
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($statusNotaServico === 'ENVIADA'): ?>
                                <form method="post" action="<?= htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=enviar-email')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$notaFiscalServico->id) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-envelope"></i> E-mail
                                    </button>
                                </form>
                                <form method="post" action="<?= htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=cancelar')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$notaFiscalServico->id) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-ban"></i> Cancelar
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php elseif ($podeEmitirNfse): ?>
                            <form method="post" action="<?= htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=gerar')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="servico_id" value="<?= htmlspecialchars((string)$servico->id) ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-receipt"></i> Emitir NFS-e
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-2 fw-semibold">Itens do Serviço</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-end">Valor Unit.</th>
                            <th class="text-end">Total Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itensServicoTabela as $idx => $itemTabela): ?>
                            <tr>
                                <td><?= (int)$idx + 1 ?></td>
                                <td>
                                    <?= htmlspecialchars((string)$itemTabela['nome']) ?>
                                    <?php if (($itemTabela['tipo'] ?? '') === 'PRODUTO'): ?>
                                        <div class="small text-muted">Produto vinculado ao serviço</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= $itemTabela['quantidade'] !== null ? number_format((float)$itemTabela['quantidade'], 4, ',', '.') : '-' ?>
                                </td>
                                <td class="text-end">
                                    <?= $itemTabela['valor_unitario'] !== null ? 'R$ ' . number_format((float)$itemTabela['valor_unitario'], 4, ',', '.') : '-' ?>
                                </td>
                                <td class="text-end">
                                    <?= $itemTabela['valor_total'] !== null ? 'R$ ' . number_format((float)$itemTabela['valor_total'], 2, ',', '.') : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal === 'undefined') {
        return;
    }

    const botoes = document.querySelectorAll('[data-confirm-action]');
    botoes.forEach(function (botao) {
        botao.addEventListener('click', function (event) {
            event.preventDefault();

            const acao = botao.getAttribute('data-confirm-action');
            const isFaturar = acao === 'faturar-servico';
            const isEstornar = acao === 'estornar-servico';
            const isExcluir = acao === 'excluir-servico';

            Swal.fire({
                title: isFaturar
                    ? 'Confirmar faturamento?'
                    : (isEstornar ? 'Estornar faturamento?' : (isExcluir ? 'Excluir serviço?' : 'Confirmar cancelamento?')),
                text: isFaturar
                    ? 'Confirmar faturamento deste serviço?'
                    : (isEstornar
                        ? 'Essa ação removerá a conta a receber vinculada, se ela ainda estiver em aberto.'
                        : (isExcluir
                            ? 'Essa ação excluirá o serviço e removerá a conta vinculada, se ela ainda estiver em aberto.'
                            : 'Confirma cancelamento do serviço? A conta vinculada também será removida se estiver em aberto.')),
                icon: isFaturar ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: isFaturar
                    ? 'Faturar'
                    : (isEstornar ? 'Estornar faturamento' : (isExcluir ? 'Excluir serviço' : 'Cancelar serviço')),
                cancelButtonText: 'Voltar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: isFaturar ? 'btn btn-success mx-1' : (isExcluir ? 'btn btn-danger mx-1' : 'btn btn-warning mx-1'),
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
