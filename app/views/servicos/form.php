<?php
$editando = (bool)($editando ?? false);
$servico = $servico ?? null;
$servicoId = $editando && $servico ? (string)$servico->id : '';
$clienteSelecionado = $servico?->clienteId ?? '';
$nomeClienteAtual = $servico?->nomeCliente ?? '';
$telefoneClienteAtual = $servico?->telefoneCliente ?? '';
$servicoCatalogoSelecionado = $servico?->servicoCatalogoId ?? '';
$servicoNomeAtual = $servico?->servicoNome ?? '';
$servicoValorAtual = (float)($servico?->servicoValor ?? 0);
$descontoTipoAtual = (string)($servico?->descontoTipo ?? '');
$descontoValorAtual = (float)($servico?->descontoValor ?? 0);
$placaAtual = $servico?->placa ?? '';
$modeloVeiculoAtual = $servico?->modeloVeiculo ?? '';
$observacoesAtual = $servico?->observacoes ?? '';
$itensServico = ($editando && $servico && !empty($servico->itens)) ? $servico->itens : [];
$produtosCliente = is_array($produtosCliente ?? null) ? $produtosCliente : [];
$servicoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_servico_base_url', 'admin/servicos.php')
    : tenantUrl('admin/servicos.php');
$servicoKanbanUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=kanban')
    : $servicoBaseUrl . '?action=kanban';
$salvarServicoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=salvar')
    : $servicoBaseUrl . '?action=salvar';
$buscarProdutosClienteUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=buscar-produtos-cliente')
    : $servicoBaseUrl . '?action=buscar-produtos-cliente';
$cadastrarClienteServicoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=cadastrar-cliente')
    : $servicoBaseUrl . '?action=cadastrar-cliente';
$isPdvContext = function_exists('dmIsPdvContext') ? dmIsPdvContext() : false;
$pdvCaixaId = function_exists('dmPdvCaixaId') ? dmPdvCaixaId() : (int)($_SESSION['pdv_caixa_id'] ?? 0);
$pdvFormaPagamento = strtolower((string)($_POST['pdv_forma_pagamento'] ?? 'dinheiro'));
$pdvDataFaturamento = (string)($_POST['pdv_data_faturamento'] ?? date('Y-m-d'));
$pdvDataVencimento = (string)($_POST['pdv_data_vencimento'] ?? date('Y-m-d'));
$totalProdutosAtual = 0.0;
foreach ($itensServico as $itemServico) {
    $totalProdutosAtual += (float)($itemServico->valorTotalItem ?? 0);
}
$descontoAplicadoAtual = 0.0;
$totalPersistidoAtual = (float)($servico?->valorTotal ?? 0);
$tipoDescontoAtualUpper = strtoupper($descontoTipoAtual);
if ($servico && $servicoValorAtual <= 0 && ($totalPersistidoAtual > 0 || $totalProdutosAtual > 0)) {
    $subtotalReconstruido = $totalPersistidoAtual;
    if ($tipoDescontoAtualUpper === 'PERCENTUAL' && $descontoValorAtual > 0 && $descontoValorAtual < 100) {
        $subtotalReconstruido = $totalPersistidoAtual / (1 - ($descontoValorAtual / 100));
    } elseif ($tipoDescontoAtualUpper === 'VALOR' && $descontoValorAtual > 0) {
        $subtotalReconstruido = $totalPersistidoAtual + $descontoValorAtual;
    }
    $servicoValorAtual = max(0, round($subtotalReconstruido - $totalProdutosAtual, 4));
}
$subtotalAtual = round($servicoValorAtual + $totalProdutosAtual, 4);
if ($tipoDescontoAtualUpper === 'PERCENTUAL') {
    $descontoAplicadoAtual = round($subtotalAtual * ($descontoValorAtual / 100), 4);
} elseif ($tipoDescontoAtualUpper === 'VALOR') {
    $descontoAplicadoAtual = round($descontoValorAtual, 4);
}
$valorTotalAtual = max(0, round($subtotalAtual - $descontoAplicadoAtual, 4));
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars((string)($page_title ?? ($editando ? 'Editar Servico' : 'Iniciar Novo Servico'))) ?></h1>
        <a href="<?= htmlspecialchars($servicoKanbanUrl) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($salvarServicoUrl) ?>" id="servicoForm">
        <?php if ($editando && $servicoId !== ''): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($servicoId) ?>">
        <?php endif; ?>
        <input type="hidden" name="valor_total" id="valorTotal" value="<?= htmlspecialchars(number_format($valorTotalAtual, 4, '.', '')) ?>">
        <?php if ($isPdvContext): ?>
            <input type="hidden" name="caixa_id" value="<?= (int)$pdvCaixaId ?>">
            <input type="hidden" name="pdv_data_faturamento" value="<?= htmlspecialchars($pdvDataFaturamento) ?>">
        <?php endif; ?>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="clienteId">Cliente</label>
                        <div class="d-flex gap-2 align-items-start">
                            <div class="flex-grow-1">
                                <select name="cliente_id" id="clienteId" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach (($clientes ?? []) as $cliente): ?>
                                        <option value="<?= (int)$cliente['id'] ?>" data-nome="<?= htmlspecialchars((string)$cliente['nome'], ENT_QUOTES, 'UTF-8') ?>" data-telefone="<?= htmlspecialchars((string)($cliente['telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= (string)$clienteSelecionado === (string)$cliente['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$cliente['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoCliente"><i class="fas fa-plus"></i> Novo</button>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="nomeCliente">Nome do Cliente</label>
                        <input type="text" name="nome_cliente" id="nomeCliente" class="form-control" readonly required value="<?= htmlspecialchars((string)$nomeClienteAtual) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="telefoneCliente">Telefone</label>
                        <input type="text" name="telefone_cliente" id="telefoneCliente" class="form-control" readonly value="<?= htmlspecialchars((string)$telefoneClienteAtual) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="servicoCatalogoId">Servico</label>
                        <select name="servico_catalogo_id" id="servicoCatalogoId" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach (($catalogo ?? []) as $item): ?>
                                <option value="<?= (int)$item->id ?>" data-nome="<?= htmlspecialchars((string)$item->nome, ENT_QUOTES, 'UTF-8') ?>" data-valor="<?= htmlspecialchars(number_format((float)$item->valorBase, 4, '.', ''), ENT_QUOTES, 'UTF-8') ?>" <?= (string)$servicoCatalogoSelecionado === (string)$item->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$item->nome) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="servico_nome" id="servicoNome" value="<?= htmlspecialchars((string)$servicoNomeAtual) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="servicoValor">Valor do Servico</label>
                        <input type="number" name="servico_valor" id="servicoValor" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars(number_format($servicoValorAtual, 2, '.', '')) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="totalServicosDisplay">Total Servicos</label>
                        <input type="text" id="totalServicosDisplay" class="form-control" value="<?= htmlspecialchars('R$ ' . number_format($servicoValorAtual, 2, ',', '.')) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="placa">Placa</label>
                        <input type="text" name="placa" id="placa" class="form-control text-uppercase" maxlength="8" placeholder="ABC1D23" value="<?= htmlspecialchars((string)$placaAtual) ?>">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="modeloVeiculo">Modelo do carro</label>
                        <input type="text" name="modelo_veiculo" id="modeloVeiculo" class="form-control" maxlength="120" placeholder="Ex: Onix 1.0 LT" value="<?= htmlspecialchars((string)$modeloVeiculoAtual) ?>">
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
                    <div class="form-text mt-2">Ao salvar, o servico sera faturado e lancado automaticamente no caixa selecionado.</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Produtos do Servico</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn"><i class="fas fa-plus"></i> Produto</button>
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
                            <?php foreach ($itensServico as $index => $item): ?>
                                <tr class="servico-item-row">
                                    <td>
                                        <select name="itens[<?= (int)$index ?>][produto_id]" class="form-select form-select-sm produto-select" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($produtosCliente as $produto): ?>
                                                <option value="<?= (int)$produto['id'] ?>" data-nome="<?= htmlspecialchars((string)$produto['nome'], ENT_QUOTES, 'UTF-8') ?>" data-preco="<?= htmlspecialchars(number_format((float)$produto['preco_venda'], 4, '.', ''), ENT_QUOTES, 'UTF-8') ?>" data-estoque="<?= htmlspecialchars(number_format((float)($produto['estoque_atual'] ?? 0), 4, '.', ''), ENT_QUOTES, 'UTF-8') ?>" <?= (string)($item->produtoId ?? '') === (string)$produto['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string)$produto['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="itens[<?= (int)$index ?>][nome_produto]" class="nome-produto-input" value="<?= htmlspecialchars((string)($item->nomeProduto ?? '')) ?>">
                                    </td>
                                    <td><input type="number" step="0.0001" min="0.0001" name="itens[<?= (int)$index ?>][quantidade]" class="form-control form-control-sm quantidade-input" value="<?= htmlspecialchars(number_format((float)($item->quantidade ?? 1), 4, '.', '')) ?>" required></td>
                                    <td><input type="number" step="0.0001" min="0" name="itens[<?= (int)$index ?>][valor_unitario]" class="form-control form-control-sm valor-input" value="<?= htmlspecialchars(number_format((float)($item->valorUnitario ?? 0), 4, '.', '')) ?>" required></td>
                                    <td class="text-end align-middle total-item-cell">R$ <?= number_format((float)($item->valorTotalItem ?? 0), 2, ',', '.') ?></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="descontoTipo">Tipo de desconto</label>
                                <select name="desconto_tipo" id="descontoTipo" class="form-select">
                                    <option value="">Sem desconto</option>
                                    <option value="VALOR" <?= strtoupper($descontoTipoAtual) === 'VALOR' ? 'selected' : '' ?>>Valor (R$)</option>
                                    <option value="PERCENTUAL" <?= strtoupper($descontoTipoAtual) === 'PERCENTUAL' ? 'selected' : '' ?>>Percentual (%)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="descontoValor">Valor do desconto</label>
                                <input type="number" name="desconto_valor" id="descontoValor" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars(number_format($descontoValorAtual, 2, '.', '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="descontoAplicadoInfo">Desconto aplicado</label>
                                <input type="text" id="descontoAplicadoInfo" class="form-control bg-light" value="<?= htmlspecialchars('R$ ' . number_format($descontoAplicadoAtual, 2, ',', '.')) ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Servicos</div>
                        <div class="mb-2" id="subtotalServicosResumo">R$ <?= number_format($servicoValorAtual, 2, ',', '.') ?></div>
                        <div class="text-muted small">Total Produtos</div>
                        <div class="mb-2" id="totalProdutosResumo">R$ <?= number_format($totalProdutosAtual, 2, ',', '.') ?></div>
                        <div class="text-muted small">Total do Servico</div>
                        <div class="h4 mb-0 text-primary" id="totalResumo">R$ <?= number_format($valorTotalAtual, 2, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <label class="form-label" for="observacoes">Observacoes</label>
                <textarea name="observacoes" id="observacoes" class="form-control" rows="3"><?= htmlspecialchars((string)$observacoesAtual) ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= htmlspecialchars($servicoKanbanUrl) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $editando ? 'Salvar Alteracoes' : 'Salvar Servico' ?></button>
        </div>
    </form>
</div>

<template id="itemRowTemplate">
    <tr class="servico-item-row">
        <td>
            <select name="itens[0][produto_id]" class="form-select form-select-sm produto-select" required><option value="">Selecione...</option></select>
            <input type="hidden" name="itens[0][nome_produto]" class="nome-produto-input" value="">
        </td>
        <td><input type="number" step="0.0001" min="0.0001" name="itens[0][quantidade]" class="form-control form-control-sm quantidade-input" value="1.0000" required></td>
        <td><input type="number" step="0.0001" min="0" name="itens[0][valor_unitario]" class="form-control form-control-sm valor-input" value="" required></td>
        <td class="text-end align-middle total-item-cell">R$ 0,00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-trash"></i></button></td>
    </tr>
</template>

<div class="modal fade" id="modalNovoCliente" tabindex="-1" aria-labelledby="modalNovoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoClienteLabel">Cadastrar Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formNovoCliente">
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="erroNovoCliente"></div>
                    <div class="row g-3">
                        <div class="col-md-12"><label class="form-label" for="novoClienteNome">Nome *</label><input type="text" class="form-control" id="novoClienteNome" name="nome" required></div>
                        <div class="col-md-6"><label class="form-label" for="novoClienteEmail">E-mail *</label><input type="email" class="form-control" id="novoClienteEmail" name="email" required></div>
                        <div class="col-md-6"><label class="form-label" for="novoClienteTelefone">Telefone</label><input type="text" class="form-control" id="novoClienteTelefone" name="telefone"></div>
                        <div class="col-md-6"><label class="form-label" for="novoClienteCpfCnpj">CPF/CNPJ *</label><input type="text" class="form-control" id="novoClienteCpfCnpj" name="cpf_cnpj" required></div>
                        <div class="col-md-4"><label class="form-label" for="novoClienteCidade">Cidade *</label><input type="text" class="form-control" id="novoClienteCidade" name="cidade" required></div>
                        <div class="col-md-2"><label class="form-label" for="novoClienteEstado">UF *</label><input type="text" class="form-control" id="novoClienteEstado" name="estado" maxlength="2" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){const $=id=>document.getElementById(id),clienteId=$('clienteId'),nomeCliente=$('nomeCliente'),telefoneCliente=$('telefoneCliente'),servicoCatalogoId=$('servicoCatalogoId'),servicoNome=$('servicoNome'),servicoValor=$('servicoValor'),valorTotal=$('valorTotal'),totalServicosDisplay=$('totalServicosDisplay'),subtotalServicosResumo=$('subtotalServicosResumo'),totalProdutosResumo=$('totalProdutosResumo'),totalResumo=$('totalResumo'),descontoTipo=$('descontoTipo'),descontoValor=$('descontoValor'),descontoAplicadoInfo=$('descontoAplicadoInfo'),tbody=document.querySelector('#itensTable tbody'),addItemBtn=$('addItemBtn'),itemRowTemplate=$('itemRowTemplate'),buscarProdutosUrl=<?= json_encode($buscarProdutosClienteUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;let produtos=<?= json_encode($produtosCliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>||[];const n=v=>{v=parseFloat(String(v||'0').replace(',','.'));return Number.isFinite(v)?v:0},m=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(v||0),h=t=>String(t||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');function infoCliente(){const o=clienteId.options[clienteId.selectedIndex];nomeCliente.value=o?(o.getAttribute('data-nome')||o.textContent.trim()):'';telefoneCliente.value=o?(o.getAttribute('data-telefone')||''):''}function infoServico(force){const o=servicoCatalogoId.options[servicoCatalogoId.selectedIndex],nome=o?(o.getAttribute('data-nome')||o.textContent.trim()):'',valor=o?o.getAttribute('data-valor'):'';servicoNome.value=nome||'';if(valor&&(force||!servicoValor.value||n(servicoValor.value)===0))servicoValor.value=n(valor).toFixed(2)}function optionsProduto(select,selected){const current=selected!==undefined?String(selected):String(select.value||'');let html='<option value="">Selecione...</option>';produtos.forEach(p=>{const id=String(p.id||''),sel=current===id?' selected':'',preco=n(p.preco_venda).toFixed(4),estoque=n(p.estoque_atual).toFixed(4);html+='<option value="'+id+'" data-nome="'+h(p.nome||'')+'" data-preco="'+preco+'" data-estoque="'+estoque+'"'+sel+'>'+h(p.nome||'')+'</option>'});select.innerHTML=html}function reindex(){tbody.querySelectorAll('.servico-item-row').forEach((row,i)=>row.querySelectorAll('select,input').forEach(el=>{el.name=el.name.replace(/itens\[[^\]]+\]/,'itens['+i+']')}))}function updateRow(row,force){const select=row.querySelector('.produto-select'),q=row.querySelector('.quantidade-input'),v=row.querySelector('.valor-input'),nome=row.querySelector('.nome-produto-input'),total=row.querySelector('.total-item-cell'),o=select.options[select.selectedIndex],preco=o?o.getAttribute('data-preco'):'',nomeProduto=o?(o.getAttribute('data-nome')||o.textContent.trim()):'';nome.value=nomeProduto||'';if(preco&&(force||!v.value||n(v.value)===0))v.value=n(preco).toFixed(4);total.textContent=m(n(q.value)*n(v.value))}function totals(){const serv=n(servicoValor.value);let prod=0;tbody.querySelectorAll('.servico-item-row').forEach(row=>{updateRow(row,false);prod+=n(row.querySelector('.quantidade-input').value)*n(row.querySelector('.valor-input').value)});const sub=serv+prod;let desc=0;if(descontoTipo.value==='PERCENTUAL')desc=sub*(n(descontoValor.value)/100);else if(descontoTipo.value==='VALOR')desc=n(descontoValor.value);if(desc>sub)desc=sub;const total=sub-desc;totalServicosDisplay.value=m(serv);subtotalServicosResumo.textContent=m(serv);totalProdutosResumo.textContent=m(prod);descontoAplicadoInfo.value=m(desc);totalResumo.textContent=m(total);valorTotal.value=total.toFixed(4)}function bind(row){const select=row.querySelector('.produto-select');optionsProduto(select);select.addEventListener('change',()=>{updateRow(row,true);totals()});row.querySelectorAll('.quantidade-input,.valor-input').forEach(el=>{el.addEventListener('input',totals);el.addEventListener('change',totals)});row.querySelector('.remove-item').addEventListener('click',()=>{row.remove();reindex();totals()});updateRow(row,false)}function refreshSelects(){tbody.querySelectorAll('.produto-select').forEach(select=>optionsProduto(select,select.value))}function fetchProdutos(id){if(!id){produtos=[];refreshSelects();totals();return}fetch(buscarProdutosUrl+'&cliente_id='+encodeURIComponent(id)).then(r=>r.json()).then(data=>{produtos=Array.isArray(data.data)?data.data:[];refreshSelects();totals()}).catch(()=>{produtos=[];refreshSelects();totals()})}addItemBtn.addEventListener('click',()=>{const row=itemRowTemplate.content.firstElementChild.cloneNode(true);tbody.appendChild(row);reindex();bind(row);totals()});clienteId.addEventListener('change',()=>{infoCliente();fetchProdutos(clienteId.value)});servicoCatalogoId.addEventListener('change',()=>{infoServico(true);totals()});[servicoValor,descontoTipo,descontoValor].forEach(el=>{el.addEventListener('input',totals);el.addEventListener('change',totals)});tbody.querySelectorAll('.servico-item-row').forEach(row=>{bind(row);optionsProduto(row.querySelector('.produto-select'),row.querySelector('.produto-select').value)});infoCliente();infoServico(false);totals()})();
(function(){const form=document.getElementById('formNovoCliente'),erro=document.getElementById('erroNovoCliente'),clienteId=document.getElementById('clienteId'),modalEl=document.getElementById('modalNovoCliente'),modal=modalEl?bootstrap.Modal.getOrCreateInstance(modalEl):null,cadastrarClienteUrl=<?= json_encode($cadastrarClienteServicoUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;if(!form)return;form.addEventListener('submit',function(e){e.preventDefault();erro.classList.add('d-none');erro.textContent='';fetch(cadastrarClienteUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:new URLSearchParams(new FormData(form)).toString()}).then(r=>r.json()).then(data=>{if(!data.success||!data.cliente)throw new Error(data.message||'Erro ao cadastrar cliente.');const c=data.cliente,o=document.createElement('option');o.value=c.id;o.textContent=c.nome||'Cliente';o.setAttribute('data-nome',c.nome||'');o.setAttribute('data-telefone',c.telefone||'');o.selected=true;clienteId.appendChild(o);clienteId.dispatchEvent(new Event('change'));form.reset();if(modal)modal.hide()}).catch(err=>{erro.textContent=err.message;erro.classList.remove('d-none')})})})();
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
