<?php
use App\Support\ShareLinkSigner;

$telefoneClienteRaw = (string)($orcamento->clienteTelefone ?? '');
$telefoneClienteNum = preg_replace('/\D+/', '', $telefoneClienteRaw);
if ($telefoneClienteNum !== '' && strlen($telefoneClienteNum) >= 10 && strlen($telefoneClienteNum) <= 11) {
    $telefoneClienteNum = '55' . $telefoneClienteNum;
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$tokenPdf = ShareLinkSigner::tokenOrcamento((int)$orcamento->id, (string)$orcamento->dataEmissao, (float)$orcamento->valorTotal);
$pdfUrl = $scheme . '://' . $host . '/sistema_dm/public/orcamento_pdf_publico.php?id=' . (int)$orcamento->id . '&token=' . urlencode($tokenPdf);

$codigoOrc = (string)($orcamento->codigo ?? ('ORC-' . $orcamento->id));
$clienteOrc = (string)($orcamento->clienteNome ?? 'Cliente');
$nomesItensWhatsapp = [];
foreach ($itens as $itemWhatsapp) {
    $nomeItemWhatsapp = trim((string)$itemWhatsapp->nomeItem());
    if ($nomeItemWhatsapp !== '') {
        $nomesItensWhatsapp[] = $nomeItemWhatsapp;
    }
}
$nomesItensWhatsapp = array_values(array_unique($nomesItensWhatsapp));
$resumoItensWhatsapp = '';
if (!empty($nomesItensWhatsapp)) {
    $resumoItensWhatsapp = "\nItens: " . implode(', ', $nomesItensWhatsapp);
}
$mensagemWhatsapp = "OlÃ¡, {$clienteOrc}! Segue o orÃ§amento {$codigoOrc}.{$resumoItensWhatsapp}\nPDF: {$pdfUrl}";
$whatsLink = $telefoneClienteNum !== ''
    ? 'https://wa.me/' . $telefoneClienteNum . '?text=' . rawurlencode($mensagemWhatsapp)
    : '';
$subtotalItens = 0.0;
foreach ($itens as $itemCalc) {
    $subtotalItens += (float)($itemCalc->subtotal ?? 0);
}
$descontoPercentual = max(0, min(100, (float)($orcamento->descontoPercentual ?? 0)));
$valorDesconto = round($subtotalItens * ($descontoPercentual / 100), 2);
$servico = $servico ?? null;
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <div class="d-flex align-items-center gap-2">
            <h1 class="h3 mb-0"><?= htmlspecialchars($orcamento->codigo ?? "ORC-{$orcamento->id}") ?></h1>
            <span class="badge <?= $orcamento->statusBadge() ?> fs-6"><?= $orcamento->statusLabel() ?></span>
        </div>
        <div class="d-flex gap-2">
            <?php if ($whatsLink !== ''): ?>
                <a href="<?= htmlspecialchars($whatsLink) ?>"
                   target="_blank"
                   rel="noopener"
                   class="btn btn-sm btn-success"
                   title="Enviar orÃ§amento por WhatsApp">
                    <i class="fab fa-whatsapp me-1"></i> <span class="d-none d-sm-inline">WhatsApp</span>
                </a>
            <?php else: ?>
                <button type="button"
                        class="btn btn-sm btn-outline-success"
                        title="Cliente sem telefone cadastrado"
                        disabled>
                    <i class="fab fa-whatsapp me-1"></i> <span class="d-none d-sm-inline">WhatsApp</span>
                </button>
            <?php endif; ?>
            <a href="/sistema_dm/public/admin/orcamentos.php?action=pdf&id=<?= $orcamento->id ?>"
               target="_blank"
               class="btn btn-sm btn-outline-secondary"
               title="Imprimir PDF">
                <i class="fas fa-print me-1"></i> <span class="d-none d-sm-inline">Imprimir</span>
            </a>
            <?php if ($orcamento->estaAberto()): ?>
                <a href="/sistema_dm/public/admin/orcamentos.php?action=editar&id=<?= $orcamento->id ?>"
                   class="btn btn-sm btn-outline-primary" title="Editar">
                    <i class="fas fa-edit me-1"></i> <span class="d-none d-sm-inline">Editar</span>
                </a>
            <?php endif; ?>
            <a href="/sistema_dm/public/admin/orcamentos.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> <span class="d-none d-sm-inline">Voltar</span>
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card shadow mb-3">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold">Dados do Orçamento</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Cliente</small>
                            <span class="fw-semibold"><?= htmlspecialchars($orcamento->clienteNome ?? '- Sem cliente -') ?></span>
                        </div>
                        <div class="col-sm-3">
                            <small class="text-muted d-block">Data de emissão</small>
                            <span><?= $orcamento->dataEmissaoFormatada() ?></span>
                        </div>
                        <div class="col-sm-3">
                            <small class="text-muted d-block">Validade</small>
                            <span><?= $orcamento->dataValidadeFormatada() ?: '-' ?></span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Criado por</small>
                            <span><?= htmlspecialchars((string)($orcamento->usuarioNome ?? ('Usuário #' . ($orcamento->usuarioId ?? '-')))) ?></span>
                        </div>
                        <?php if ($orcamento->observacoes): ?>
                            <div class="col-12">
                                <small class="text-muted d-block">Observações</small>
                                <p class="mb-0 fst-italic text-secondary"><?= nl2br(htmlspecialchars($orcamento->observacoes)) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold">Itens do Orçamento</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($itens)): ?>
                        <div class="p-4 text-center text-muted">Nenhum item cadastrado neste orçamento.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Item</th>
                                        <th class="text-center">Qtd</th>
                                        <th class="text-end">Valor unit.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $i => $item): ?>
                                        <tr>
                                            <td class="text-muted"><?= $i + 1 ?></td>
                                            <td><span class="badge bg-secondary me-2"><?= htmlspecialchars((string)($item->tipoItem ?? 'ITEM')) ?></span><?= htmlspecialchars((string)$item->nomeItem()) ?></td>
                                            <td class="text-center"><?= number_format((float)$item->quantidade, 2, ',', '.') ?></td>
                                            <td class="text-end">R$ <?= number_format((float)$item->precoUnitario, 2, ',', '.') ?></td>
                                            <td class="text-end fw-semibold">R$ <?= number_format((float)$item->subtotal, 2, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end">Subtotal dos itens</td>
                                        <td class="text-end">R$ <?= number_format($subtotalItens, 2, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end text-danger">Desconto (<?= number_format($descontoPercentual, 2, ',', '.') ?>%)</td>
                                        <td class="text-end text-danger">- R$ <?= number_format($valorDesconto, 2, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Total</td>
                                        <td class="text-end fw-bold fs-6"><?= $orcamento->valorTotalFormatado() ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card shadow mb-3">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold">Resumo</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-6 text-muted">Código</dt>
                        <dd class="col-6 text-end fw-semibold"><?= htmlspecialchars($orcamento->codigo ?? "ORC-{$orcamento->id}") ?></dd>

                        <dt class="col-6 text-muted">Status</dt>
                        <dd class="col-6 text-end">
                            <span class="badge <?= $orcamento->statusBadge() ?>"><?= $orcamento->statusLabel() ?></span>
                        </dd>

                        <dt class="col-6 text-muted">Itens</dt>
                        <dd class="col-6 text-end"><?= count($itens) ?></dd>

                        <dt class="col-6 text-muted">Subtotal</dt>
                        <dd class="col-6 text-end">R$ <?= number_format($subtotalItens, 2, ',', '.') ?></dd>

                        <dt class="col-6 text-muted">Desconto</dt>
                        <dd class="col-6 text-end text-danger">
                            <?= number_format($descontoPercentual, 2, ',', '.') ?>% (- R$ <?= number_format($valorDesconto, 2, ',', '.') ?>)
                        </dd>

                        <dt class="col-6 text-muted">Emissão</dt>
                        <dd class="col-6 text-end"><?= $orcamento->dataEmissaoFormatada() ?></dd>

                        <?php if ($orcamento->dataValidade): ?>
                            <dt class="col-6 text-muted">Validade</dt>
                            <dd class="col-6 text-end"><?= $orcamento->dataValidadeFormatada() ?></dd>
                        <?php endif; ?>

                        <dt class="col-6 text-muted border-top pt-2 mt-1">Total</dt>
                        <dd class="col-6 text-end border-top pt-2 mt-1 fw-bold fs-5"><?= $orcamento->valorTotalFormatado() ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($servico !== null): ?>
                <div class="card shadow mb-3 border-start border-primary border-3">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold">Serviço gerado</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-3 small">
                            <dt class="col-5 text-muted">Número</dt>
                            <dd class="col-7 text-end fw-semibold">#<?= htmlspecialchars((string)($servico->numero ?? 0)) ?></dd>

                            <dt class="col-5 text-muted">Status</dt>
                            <dd class="col-7 text-end">
                                <span class="badge <?= htmlspecialchars((string)$servico->statusBadge()) ?>"><?= htmlspecialchars((string)$servico->statusLabel()) ?></span>
                            </dd>

                            <dt class="col-5 text-muted">Serviço</dt>
                            <dd class="col-7 text-end"><?= htmlspecialchars((string)($servico->servicoNome ?? '---')) ?></dd>

                            <?php if (!empty($servico->placa)): ?>
                                <dt class="col-5 text-muted">Placa</dt>
                                <dd class="col-7 text-end"><?= htmlspecialchars((string)$servico->placa) ?></dd>
                            <?php endif; ?>

                            <dt class="col-5 text-muted">Valor</dt>
                            <dd class="col-7 text-end fw-semibold">R$ <?= number_format((float)($servico->valorTotal ?? 0), 2, ',', '.') ?></dd>
                        </dl>
                        <a href="/sistema_dm/public/admin/servicos.php?action=visualizar&id=<?= urlencode((string)($servico->id ?? '')) ?>"
                           class="btn btn-sm btn-outline-primary w-100">
                            <i class="fas fa-wrench me-1"></i> Ver serviço
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($orcamento->contaReceberId): ?>
                <div class="card shadow border-start border-info border-3">
                    <div class="card-body py-3">
                        <small class="text-muted d-block mb-1"><i class="fas fa-link me-1"></i> Conta a receber vinculada</small>
                        <a href="/sistema_dm/public/admin/financeiro/contas-receber.php"
                           class="btn btn-sm btn-outline-info w-100">
                            <i class="fas fa-arrow-circle-down me-1"></i> Ver contas a receber
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow mt-3">
                <div class="card-body d-grid gap-2">
                    <?php if ($orcamento->estaAberto()): ?>
                        <a href="/sistema_dm/public/admin/orcamentos.php?action=editar&id=<?= $orcamento->id ?>"
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit me-1"></i> Editar orçamento
                        </a>
                        <a href="/sistema_dm/public/admin/orcamentos.php?action=aprovar&id=<?= $orcamento->id ?>"
                           class="btn btn-outline-success btn-sm">
                            <i class="fas fa-check me-1"></i> Aprovar
                        </a>
                        <button type="button" class="btn btn-outline-warning btn-sm"
                                onclick="confirmarAcao('Cancelar este orçamento?', '/sistema_dm/public/admin/orcamentos.php?action=cancelar&id=<?= $orcamento->id ?>')">
                            <i class="fas fa-ban me-1"></i> Cancelar orçamento
                        </button>
                    <?php elseif ($orcamento->estaAprovado()): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm"
                                onclick="confirmarAcao('Estornar este orçamento? A conta a receber vinculada também será estornada automaticamente.', '/sistema_dm/public/admin/orcamentos.php?action=estornar&id=<?= $orcamento->id ?>')">
                            <i class="fas fa-undo me-1"></i> Estornar orçamento
                        </button>
                    <?php endif; ?>
                    <a href="/sistema_dm/public/admin/orcamentos.php?action=pdf&id=<?= $orcamento->id ?>"
                       target="_blank"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-print me-1"></i> Imprimir
                    </a>
                    <a href="/sistema_dm/public/admin/orcamentos.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Voltar à lista
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function confirmarAcao(mensagem, url) {
    Swal.fire({
        title: 'Confirmar',
        text: mensagem,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-warning mx-1',
            cancelButton: 'btn btn-secondary mx-1'
        }
    }).then(result => {
        if (result.isConfirmed) window.location.href = url;
    });
}
</script>

<style>
@media print {
    #sidebar, .top-header, .app-footer, .btn, .card-header { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .page-body { padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
}
</style>
