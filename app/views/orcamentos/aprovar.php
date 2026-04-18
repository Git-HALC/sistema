<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Aprovar Orçamento</h1>
        <a href="/sistema_dm/public/admin/orcamentos.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <?= htmlspecialchars($orcamento->codigo ?? "ORC-{$orcamento->id}") ?>
                    </h6>
                    <span class="badge <?= $orcamento->statusBadge() ?>">
                        <?= $orcamento->statusLabel() ?>
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-3">
                        <dt class="col-sm-4 text-muted">Cliente</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($orcamento->clienteNome ?? '-') ?></dd>

                        <dt class="col-sm-4 text-muted">Emissão</dt>
                        <dd class="col-sm-8"><?= $orcamento->dataEmissaoFormatada() ?></dd>

                        <dt class="col-sm-4 text-muted">Validade</dt>
                        <dd class="col-sm-8"><?= $orcamento->dataValidadeFormatada() ?></dd>

                        <?php if ($orcamento->observacoes): ?>
                            <dt class="col-sm-4 text-muted">Observações</dt>
                            <dd class="col-sm-8"><?= nl2br(htmlspecialchars($orcamento->observacoes)) ?></dd>
                        <?php endif; ?>
                    </dl>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Qtd</th>
                                    <th class="text-end">Preço unit.</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): /** @var OrcamentoItem $item */ ?>
                                    <tr>
                                        <td><span class="badge bg-secondary me-2"><?= htmlspecialchars((string)($item->tipoItem ?? 'ITEM')) ?></span><?= htmlspecialchars((string)$item->nomeItem()) ?></td>
                                        <td class="text-end"><?= number_format($item->quantidade, 4) ?></td>
                                        <td class="text-end"><?= $item->precoUnitarioFormatado() ?></td>
                                        <td class="text-end fw-semibold"><?= $item->subtotalFormatado() ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="3" class="text-end">Total:</td>
                                    <td class="text-end text-success fs-6"><?= $orcamento->valorTotalFormatado() ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="fas fa-check-circle me-1"></i> Confirmar aprovação
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Ao aprovar: produtos geram pedido e baixam estoque; se houver serviço, será gerado um serviço. Em orçamento misto, os dois fluxos serão executados.
                    </div>

                    <form method="POST"
                          id="formAprovarOrcamento"
                          action="/sistema_dm/public/admin/orcamentos.php?action=aprovar&id=<?= $orcamento->id ?>">

                        <div class="mb-4 small text-muted">
                            Valor do orçamento aprovado: <strong><?= $orcamento->valorTotalFormatado() ?></strong>
                        </div>
                        <?php if (!empty($temServico)): ?>
                            <div class="border rounded p-3 bg-light mb-3">
                                <div class="fw-semibold text-dark mb-3">Dados do veículo para abrir o serviço</div>
                                <div class="mb-3">
                                    <label for="placa" class="form-label">Placa <span class="text-danger">*</span></label>
                                    <input type="text" name="placa" id="placa" class="form-control text-uppercase" maxlength="8" placeholder="ABC1D23" required>
                                    <div class="form-text">Obrigatório quando o orçamento possuir serviços.</div>
                                </div>
                                <div class="mb-0">
                                    <label for="modelo_veiculo" class="form-label">Modelo do carro <span class="text-danger">*</span></label>
                                    <input type="text" name="modelo_veiculo" id="modelo_veiculo" class="form-control" maxlength="120" placeholder="Ex: Onix 1.0 LT" required>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <a href="/sistema_dm/public/admin/orcamentos.php" class="btn btn-secondary flex-fill">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-success flex-fill fw-semibold">
                                <i class="fas fa-check me-1"></i> Aprovar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formAprovarOrcamento');
    if (!form || typeof Swal === 'undefined') return;

    let confirmado = false;
    const codigo = <?= json_encode($orcamento->codigo ?? ('ORC-' . $orcamento->id)) ?>;
    const temServico = <?= !empty($temServico) ? 'true' : 'false' ?>;
    const placa = document.getElementById('placa');
    const modeloVeiculo = document.getElementById('modelo_veiculo');

    if (placa) {
        placa.addEventListener('input', function () {
            placa.value = placa.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
        });
    }

    form.addEventListener('submit', function (event) {
        if (confirmado) return;

        if (!form.checkValidity()) {
            return;
        }

        if (temServico) {
            const placaNormalizada = (placa?.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            const modeloInformado = (modeloVeiculo?.value || '').trim();

            if (placa && placaNormalizada.length !== 7) {
                event.preventDefault();
                Swal.fire('Atenção', 'Informe uma placa válida com 7 caracteres para gerar o serviço.', 'warning');
                placa.focus();
                return;
            }

            if (modeloVeiculo && modeloInformado === '') {
                event.preventDefault();
                Swal.fire('Atenção', 'Informe o modelo do carro para gerar o serviço.', 'warning');
                modeloVeiculo.focus();
                return;
            }
        }

        event.preventDefault();

        Swal.fire({
            title: 'Confirmar aprovação?',
            text: 'Confirmar aprovação do orçamento ' + codigo + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Aprovar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            allowOutsideClick: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            confirmado = true;
            form.submit();
        });
    });
});
</script>
