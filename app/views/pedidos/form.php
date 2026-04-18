<?php
$editando = (bool)($editando ?? false);
$pedido = $pedido ?? null;
$clientes = $clientes ?? [];
$produtos = $produtos ?? [];
$orcamentosAprovados = $orcamentosAprovados ?? [];

$pedidoId = $editando && $pedido ? (string)$pedido->id : '';
$tipoCriacaoInicial = $editando ? 'novo' : 'novo';
$clienteSelecionado = $pedido?->cliente_id ?? '';
$observacoesPedido = $pedido?->observacoes ?? '';
$dataEntregaPrevista = $pedido?->data_entrega_prevista ?? '';
$itensPedido = ($editando && $pedido && !empty($pedido->itens)) ? $pedido->itens : [null];
$subtotalAtual = 0.0;
$pedidoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_pedido_base_url', 'admin/pedidos.php')
    : tenantUrl('admin/pedidos.php');
$pedidoShowUrl = $editando && $pedidoId !== ''
    ? (function_exists('dmBuildUrl')
        ? dmBuildUrl($pedidoBaseUrl, 'action=show&id=' . urlencode($pedidoId))
        : $pedidoBaseUrl . '?action=show&id=' . urlencode($pedidoId))
    : $pedidoBaseUrl;
$buscarClientesUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($pedidoBaseUrl, 'action=buscar-clientes')
    : $pedidoBaseUrl . '?action=buscar-clientes';
$cadastrarClienteUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($pedidoBaseUrl, 'action=cadastrar-cliente')
    : $pedidoBaseUrl . '?action=cadastrar-cliente';
$isPdvContext = function_exists('dmIsPdvContext') ? dmIsPdvContext() : false;
$pdvCaixaId = function_exists('dmPdvCaixaId') ? dmPdvCaixaId() : (int)($_SESSION['pdv_caixa_id'] ?? 0);
$pdvFormaPagamento = strtolower((string)($_POST['pdv_forma_pagamento'] ?? 'dinheiro'));
$pdvDataFaturamento = (string)($_POST['pdv_data_faturamento'] ?? date('Y-m-d'));
$pdvDataVencimento = (string)($_POST['pdv_data_vencimento'] ?? date('Y-m-d'));
if ($editando && $pedido) {
    foreach ($pedido->itens as $itemExistente) {
        $subtotalAtual += (float)($itemExistente->valor_total_item ?? 0);
    }
}

$descontoTipoInicial = $pedido?->desconto_tipo ?? null;
$descontoValorInicial = (float)($pedido?->desconto_valor ?? 0);
if ($editando && $pedido && $descontoTipoInicial === null && $subtotalAtual > (float)$pedido->valor_total) {
    $descontoTipoInicial = 'VALOR';
    $descontoValorInicial = round($subtotalAtual - (float)$pedido->valor_total, 2);
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars((string)($page_title ?? ($editando ? 'Editar Pedido' : 'Novo Pedido'))) ?></h1>
        <a href="<?= htmlspecialchars($pedidoShowUrl) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars((function_exists('dmBuildUrl') ? dmBuildUrl($pedidoBaseUrl, 'action=salvar') : $pedidoBaseUrl . '?action=salvar')) ?>" id="pedidoForm">
        <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($pedidoId) ?>">
        <?php endif; ?>
        <input type="hidden" name="tipo_criacao" id="tipoCriacaoHidden" value="<?= htmlspecialchars($tipoCriacaoInicial) ?>">
        <?php if ($isPdvContext): ?>
            <input type="hidden" name="caixa_id" value="<?= (int)$pdvCaixaId ?>">
            <input type="hidden" name="pdv_data_faturamento" value="<?= htmlspecialchars($pdvDataFaturamento) ?>">
        <?php endif; ?>


        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <div class="d-flex gap-2 align-items-start">
                            <div class="flex-grow-1">
                                <select name="cliente_id" id="clienteId" class="form-select" data-placeholder="Selecione um cliente">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= (int)$cliente['id'] ?>" <?= (string)$clienteSelecionado === (string)$cliente['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$cliente['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoCliente" title="Cadastrar novo cliente">
                                <i class="fas fa-plus"></i> Novo
                            </button>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Data entrega prevista</label>
                        <input type="date" name="data_entrega_prevista" class="form-control" data-skip-datepicker="1" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars((string)$dataEntregaPrevista) ?>">
                    </div>

                    <?php if ($editando && $pedido !== null): ?>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (\App\Modules\Gestao_Pedidos\Pedido::STATUS_VALIDOS as $status): ?>
                                    <?php if (in_array($status, [\App\Modules\Gestao_Pedidos\Pedido::STATUS_FATURADO, \App\Modules\Gestao_Pedidos\Pedido::STATUS_CANCELADO], true)) { continue; } ?>
                                    <option value="<?= htmlspecialchars($status) ?>" <?= $pedido->status === $status ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(\App\Modules\Gestao_Pedidos\Pedido::STATUS_LABELS[$status] ?? $status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label">Observacoes</label>
                        <input type="text" name="observacoes" class="form-control" placeholder="Opcional" value="<?= htmlspecialchars((string)$observacoesPedido) ?>">
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isPdvContext): ?>
            <div class="card shadow-sm mb-3 border-success-subtle">
                <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Lancamento no Caixa</span>
                    <span class="small text-muted">Caixa #<?= (int)$pdvCaixaId ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="pdvFormaPagamento">Forma de pagamento</label>
                            <select name="pdv_forma_pagamento" id="pdvFormaPagamento" class="form-select" required>
                                <option value="dinheiro" <?= $pdvFormaPagamento === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                                <option value="cartao" <?= $pdvFormaPagamento === 'cartao' ? 'selected' : '' ?>>Cartao</option>
                                <option value="pix" <?= $pdvFormaPagamento === 'pix' ? 'selected' : '' ?>>PIX</option>
                                <option value="a_faturar" <?= $pdvFormaPagamento === 'a_faturar' ? 'selected' : '' ?>>A faturar</option>
                            </select>
                        </div>
                        <div class="col-md-6<?= $pdvFormaPagamento === 'a_faturar' ? '' : ' d-none' ?>" id="pdvVencimentoWrap">
                            <label class="form-label" for="pdvDataVencimento">Data de vencimento</label>
                            <input type="date" name="pdv_data_vencimento" id="pdvDataVencimento" class="form-control" value="<?= htmlspecialchars($pdvDataVencimento) ?>" data-skip-datepicker="1" <?= $pdvFormaPagamento === 'a_faturar' ? 'required' : '' ?>>
                        </div>
                    </div>
                    <div class="form-text mt-2">Ao salvar, o pedido sera faturado e lancado automaticamente no caixa selecionado.</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-3" id="itensWrap">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Itens do Pedido</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
                    <i class="fas fa-plus"></i> Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="itensTable">
                        <thead class="table-light">
                            <tr>
                                <th>Produto</th>
                                <th style="width: 120px;">Qtd</th>
                                <th style="width: 160px;">Valor Unit.</th>
                                <th style="width: 160px;">Total</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itensPedido as $index => $item): ?>
                                <?php
                                $produtoId = $item?->produto_id ?? '';
                                $quantidade = $item ? number_format((float)$item->quantidade, 4, '.', '') : '1.0000';
                                $valorUnitario = $item ? number_format((float)$item->valor_unitario, 4, '.', '') : '';
                                $valorTotalItem = $item ? (float)$item->valor_total_item : 0.0;
                                $nomeProdutoItem = $item?->nome_produto ?? '';
                                ?>
                                <tr class="pedido-item-row">
                                    <td>
                                        <select name="itens[<?= (int)$index ?>][produto_id]" class="form-select form-select-sm produto-select" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($produtos as $produto): ?>
                                                <option
                                                    value="<?= (int)$produto['id'] ?>"
                                                    data-preco="<?= htmlspecialchars(number_format((float)$produto['preco_venda'], 4, '.', '')) ?>"
                                                    data-nome="<?= htmlspecialchars((string)$produto['nome']) ?>"
                                                    <?= (string)$produtoId === (string)$produto['id'] ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars((string)$produto['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="itens[<?= (int)$index ?>][nome_produto]" class="nome-produto-input" value="<?= htmlspecialchars((string)$nomeProdutoItem) ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" name="itens[<?= (int)$index ?>][quantidade]" class="form-control form-control-sm quantidade-input" value="<?= htmlspecialchars($quantidade) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" min="0" name="itens[<?= (int)$index ?>][valor_unitario]" class="form-control form-control-sm valor-input" value="<?= htmlspecialchars($valorUnitario) ?>" required>
                                    </td>
                                    <td class="text-end align-middle total-item-cell">R$ <?= number_format($valorTotalItem, 2, ',', '.') ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4" id="descontoWrap">
            <div class="col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo de desconto</label>
                                <select name="desconto_tipo" id="descontoTipo" class="form-select">
                                    <option value="">Sem desconto</option>
                                    <option value="VALOR" <?= $descontoTipoInicial === 'VALOR' ? 'selected' : '' ?>>Valor (R$)</option>
                                    <option value="PERCENTUAL" <?= $descontoTipoInicial === 'PERCENTUAL' ? 'selected' : '' ?>>Percentual (%)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valor do desconto</label>
                                <input type="number" step="0.01" min="0" name="desconto_valor" id="descontoValor" class="form-control" value="<?= htmlspecialchars(number_format($descontoValorInicial, 2, '.', '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Desconto aplicado</label>
                                <div id="descontoAplicadoInfo" class="form-control bg-light">R$ 0,00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Subtotal dos itens</div>
                        <div class="mb-2" id="subtotalPedido">R$ 0,00</div>

                        <div class="text-muted small">Total do pedido</div>
                        <div class="h4 mb-0 text-primary" id="totalPedido">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= htmlspecialchars($pedidoShowUrl) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $editando ? 'Salvar Alteracoes' : 'Salvar Pedido' ?>
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="modalNovoCliente" tabindex="-1" aria-labelledby="modalNovoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoClienteLabel">Cadastrar Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formNovoClientePedido">
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="erroNovoClientePedido"></div>
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="novoClienteNomePedido">Nome *</label>
                            <input type="text" class="form-control" id="novoClienteNomePedido" name="nome" required autocomplete="name">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="form-group col-md-6">
                            <label for="novoClienteEmailPedido">E-mail *</label>
                            <input type="email" class="form-control" id="novoClienteEmailPedido" name="email" required autocomplete="email">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="novoClienteTelefonePedido">Telefone</label>
                            <input type="text" class="form-control" id="novoClienteTelefonePedido" name="telefone" autocomplete="tel">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="form-group col-md-6">
                            <label for="novoClienteCpfCnpjPedido">CPF/CNPJ *</label>
                            <input type="text" class="form-control" id="novoClienteCpfCnpjPedido" name="cpf_cnpj" required autocomplete="off" placeholder="Ex: 12345678000195">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="form-group col-md-4">
                            <label for="novoClienteCidadePedido">Cidade *</label>
                            <input type="text" class="form-control" id="novoClienteCidadePedido" name="cidade" autocomplete="address-level2" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="novoClienteEstadoPedido">Estado (UF) *</label>
                            <input type="text" class="form-control" id="novoClienteEstadoPedido" name="estado" autocomplete="address-level1" maxlength="2" placeholder="Ex: SP" required>
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Salvar Cliente
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="itemRowTemplate">
    <tr class="pedido-item-row">
        <td>
            <select name="itens[__IDX__][produto_id]" class="form-select form-select-sm produto-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($produtos as $produto): ?>
                    <option value="<?= (int)$produto['id'] ?>" data-preco="<?= htmlspecialchars(number_format((float)$produto['preco_venda'], 4, '.', '')) ?>" data-nome="<?= htmlspecialchars((string)$produto['nome']) ?>">
                        <?= htmlspecialchars((string)$produto['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="itens[__IDX__][nome_produto]" class="nome-produto-input" value="">
        </td>
        <td><input type="number" step="0.0001" min="0.0001" name="itens[__IDX__][quantidade]" class="form-control form-control-sm quantidade-input" value="1.0000" required></td>
        <td><input type="number" step="0.0001" min="0" name="itens[__IDX__][valor_unitario]" class="form-control form-control-sm valor-input" value="" required></td>
        <td class="text-end align-middle total-item-cell">R$ 0,00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-trash"></i></button></td>
    </tr>
</template>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<script>
(function () {
    const editando = <?= $editando ? 'true' : 'false' ?>;
    const tipoCriacao = document.getElementById('tipoCriacao');
    const tipoCriacaoHidden = document.getElementById('tipoCriacaoHidden');
    const orcamentoWrap = document.getElementById('orcamentoWrap');
    const orcamentoId = document.getElementById('orcamentoId');
    const clienteId = document.getElementById('clienteId');
    const itensWrap = document.getElementById('itensWrap');
    const descontoWrap = document.getElementById('descontoWrap');
    const addItemBtn = document.getElementById('addItemBtn');
    const tbody = document.querySelector('#itensTable tbody');
    const itemRowTemplate = document.getElementById('itemRowTemplate');
    const descontoTipo = document.getElementById('descontoTipo');
    const descontoValor = document.getElementById('descontoValor');
    const subtotalPedido = document.getElementById('subtotalPedido');
    const descontoAplicadoInfo = document.getElementById('descontoAplicadoInfo');
    const totalPedido = document.getElementById('totalPedido');

    function formatMoney(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value || 0);
    }

    function toggleModo() {
        if (editando) {
            return;
        }

        const modo = tipoCriacao ? tipoCriacao.value : 'novo';
        tipoCriacaoHidden.value = modo;
        const deOrcamento = modo === 'deOrcamento';

        if (orcamentoWrap) {
            orcamentoWrap.classList.toggle('d-none', !deOrcamento);
        }
        itensWrap.classList.toggle('d-none', deOrcamento);
        descontoWrap.classList.toggle('d-none', deOrcamento);

        if (orcamentoId) {
            if (deOrcamento) {
                orcamentoId.setAttribute('required', 'required');
            } else {
                orcamentoId.removeAttribute('required');
            }
        }

        tbody.querySelectorAll('select, input').forEach(function (el) {
            if (el.name.includes('[produto_id]') || el.name.includes('[quantidade]') || el.name.includes('[valor_unitario]')) {
                if (deOrcamento) {
                    el.removeAttribute('required');
                } else {
                    el.setAttribute('required', 'required');
                }
            }
        });
    }

    function reindexRows() {
        tbody.querySelectorAll('.pedido-item-row').forEach(function (row, idx) {
            row.querySelectorAll('select, input').forEach(function (el) {
                el.name = el.name.replace(/itens\[[^\]]+\]/, 'itens[' + idx + ']');
            });
        });
    }

    function updateRow(row, options = {}) {
        const produtoSelect = row.querySelector('.produto-select');
        const quantidadeInput = row.querySelector('.quantidade-input');
        const valorInput = row.querySelector('.valor-input');
        const nomeProdutoInput = row.querySelector('.nome-produto-input');
        const totalCell = row.querySelector('.total-item-cell');
        const forcePriceSync = options.forcePriceSync === true;

        if (produtoSelect) {
            const option = produtoSelect.options[produtoSelect.selectedIndex];
            const nome = option ? option.getAttribute('data-nome') || option.textContent.trim() : '';
            const preco = option ? option.getAttribute('data-preco') : '';

            if (nomeProdutoInput) {
                nomeProdutoInput.value = nome || '';
            }

            if (preco && (forcePriceSync || !valorInput.value || parseFloat(valorInput.value) === 0)) {
                valorInput.value = parseFloat(preco).toFixed(4);
            }
        }

        const quantidade = parseFloat((quantidadeInput.value || '0').replace(',', '.')) || 0;
        const valorUnitario = parseFloat((valorInput.value || '0').replace(',', '.')) || 0;
        const total = quantidade * valorUnitario;
        totalCell.textContent = formatMoney(total);
    }

    function recalculateTotals() {
        let subtotal = 0;
        tbody.querySelectorAll('.pedido-item-row').forEach(function (row) {
            updateRow(row);
            const quantidade = parseFloat((row.querySelector('.quantidade-input').value || '0').replace(',', '.')) || 0;
            const valorUnitario = parseFloat((row.querySelector('.valor-input').value || '0').replace(',', '.')) || 0;
            subtotal += quantidade * valorUnitario;
        });

        let descontoAplicado = 0;
        const tipo = descontoTipo ? descontoTipo.value : '';
        const valor = descontoValor ? (parseFloat((descontoValor.value || '0').replace(',', '.')) || 0) : 0;

        if (tipo === 'PERCENTUAL') {
            descontoAplicado = subtotal * (valor / 100);
        } else if (tipo === 'VALOR') {
            descontoAplicado = valor;
        }

        if (descontoAplicado > subtotal) {
            descontoAplicado = subtotal;
        }

        subtotalPedido.textContent = formatMoney(subtotal);
        descontoAplicadoInfo.textContent = formatMoney(descontoAplicado);
        totalPedido.textContent = formatMoney(subtotal - descontoAplicado);
    }

    function bindRow(row) {
        const produtoSelect = row.querySelector('.produto-select');
        const quantidadeInput = row.querySelector('.quantidade-input');
        const valorInput = row.querySelector('.valor-input');

        if (produtoSelect) {
            produtoSelect.addEventListener('change', function () {
                updateRow(row, { forcePriceSync: true });
                recalculateTotals();
            });
        }

        [quantidadeInput, valorInput].forEach(function (el) {
            if (!el) {
                return;
            }

            el.addEventListener('change', function () {
                updateRow(row);
                recalculateTotals();
            });

            el.addEventListener('input', function () {
                updateRow(row);
                recalculateTotals();
            });
        });

        const removeButton = row.querySelector('.remove-item');
        if (removeButton) {
            removeButton.addEventListener('click', function () {
                if (tbody.querySelectorAll('.pedido-item-row').length <= 1) {
                    return;
                }
                row.remove();
                reindexRows();
                recalculateTotals();
            });
        }

        updateRow(row);
    }

    if (addItemBtn) {
        addItemBtn.addEventListener('click', function () {
            const clone = itemRowTemplate.content.firstElementChild.cloneNode(true);
            tbody.appendChild(clone);
            reindexRows();
            bindRow(clone);
            recalculateTotals();
        });
    }

    if (tipoCriacao) {
        tipoCriacao.addEventListener('change', toggleModo);
    }

    if (orcamentoId) {
        orcamentoId.addEventListener('change', function () {
            const option = orcamentoId.options[orcamentoId.selectedIndex];
            const cliente = option ? option.getAttribute('data-cliente') : '';
            if (cliente) {
                clienteId.value = cliente;
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    window.jQuery(clienteId).trigger('change');
                }
            }
        });
    }

    if (descontoTipo) {
        descontoTipo.addEventListener('change', recalculateTotals);
    }
    if (descontoValor) {
        descontoValor.addEventListener('input', recalculateTotals);
        descontoValor.addEventListener('change', recalculateTotals);
    }

    tbody.querySelectorAll('.pedido-item-row').forEach(bindRow);
    toggleModo();
    recalculateTotals();
})();

$(document).ready(function() {
    const clienteSelectEl = $('#clienteId');

    function formatCpfCnpj(valor) {
        if (!valor) return '';
        const digits = valor.replace(/\D+/g, '').slice(0, 14);
        if (digits.length <= 11) {
            return digits.replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1-$2')
                .replace(/(-\d{2})\d+?$/, '$1');
        }
        return digits.replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    function formatTelefone(valor) {
        if (!valor) return '';
        const digits = valor.replace(/\D+/g, '').slice(0, 11);
        if (digits.length <= 10) {
            return digits.replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{4})(\d)/, '$1-$2')
                .replace(/(-\d{4})\d+?$/, '$1');
        }
        return digits.replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d)/, '$1-$2')
            .replace(/(-\d{4})\d+?$/, '$1');
    }

    function resetModalNovoClientePedido() {
        $('#formNovoClientePedido')[0].reset();
        $('#erroNovoClientePedido').addClass('d-none').text('');
    }

    clienteSelectEl.select2({
        theme: 'bootstrap-5',
        placeholder: clienteSelectEl.data('placeholder') || 'Selecione um cliente',
        allowClear: true,
        width: '100%',
        ajax: {
            url: <?= json_encode($buscarClientesUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    term: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function(data) {
                return {
                    results: data.data || [],
                    pagination: {
                        more: data.pagination && data.pagination.more
                    }
                };
            },
            cache: true
        },
        minimumInputLength: 0
    });

    $('#novoClienteTelefonePedido').on('input', function() {
        this.value = formatTelefone(this.value);
    });

    $('#novoClienteCpfCnpjPedido').on('input', function() {
        this.value = formatCpfCnpj(this.value);
    });

    $('#novoClienteEstadoPedido').on('input', function() {
        this.value = this.value.toUpperCase().substring(0, 2);
    });

    $('#formNovoClientePedido').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const erroEl = $('#erroNovoClientePedido');
        erroEl.addClass('d-none').text('');

        $.ajax({
            url: <?= json_encode($cadastrarClienteUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            method: 'POST',
            data: form.serialize(),
            dataType: 'json'
        }).done(function(resp) {
            if (resp.success && resp.cliente) {
                const clienteData = resp.cliente;
                const option = new Option(clienteData.nome || 'Cliente', clienteData.id, true, true);
                clienteSelectEl.append(option).trigger('change');

                const modalEl = document.getElementById('modalNovoCliente');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                resetModalNovoClientePedido();
            } else {
                erroEl.removeClass('d-none').text(resp.message || 'Erro ao cadastrar cliente.');
            }
        }).fail(function() {
            erroEl.removeClass('d-none').text('Erro ao comunicar com o servidor. Tente novamente.');
        });
    });

    $('#modalNovoCliente').on('hidden.bs.modal', function() {
        resetModalNovoClientePedido();
    });
});
</script>
<?php if ($isPdvContext): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const formaPagamento = document.getElementById('pdvFormaPagamento');
    const vencimentoWrap = document.getElementById('pdvVencimentoWrap');
    const vencimentoInput = document.getElementById('pdvDataVencimento');

    if (!formaPagamento || !vencimentoWrap || !vencimentoInput) {
        return;
    }

    function syncPdvPagamento() {
        const exigeVencimento = formaPagamento.value === 'a_faturar';
        vencimentoWrap.classList.toggle('d-none', !exigeVencimento);
        vencimentoInput.required = exigeVencimento;
    }

    formaPagamento.addEventListener('change', syncPdvPagamento);
    syncPdvPagamento();
});
</script>
<?php endif; ?>
