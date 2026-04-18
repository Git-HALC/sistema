<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0"><?= htmlspecialchars($page_title) ?></h1>
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

    <form method="POST" action="/sistema_dm/public/admin/orcamentos.php?action=salvar" id="formOrcamento">
        <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= $orcamento->id ?>">
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary">Dados do Orcamento</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <div class="d-flex gap-2 align-items-start">
                            <div class="flex-grow-1">
                                <select id="cliente_id" name="cliente_id" class="form-select form-select-sm" data-placeholder="Selecione um cliente">
                                    <option value=""><?= (int)($orcamento->clienteId ?? 0) > 0 ? '' : 'Sem cliente' ?></option>
                                    <?php foreach (($clientes ?? []) as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)($orcamento->clienteId ?? 0) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)($c['nome'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoCliente" title="Cadastrar novo cliente">
                                <i class="fas fa-plus"></i> Novo Cliente
                            </button>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Data de Emissao <span class="text-danger">*</span></label>
                        <input type="date" name="data_emissao"
                               value="<?= $orcamento->dataEmissao ?: date('Y-m-d') ?>"
                               class="form-control form-control-sm" data-skip-datepicker="1" required <?= $editando ? 'readonly' : '' ?>>
                        <?php if ($editando): ?>
                            <small class="text-muted">A data de emissao nao pode ser alterada apos a criacao do orcamento.</small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Validade</label>
                        <input type="date" name="data_validade"
                               value="<?= $orcamento->dataValidade ?? '' ?>"
                               class="form-control form-control-sm" data-skip-datepicker="1">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Desconto Geral (%)</label>
                        <input type="number" name="desconto_percentual" id="desconto_percentual"
                               value="<?= number_format((float)($orcamento->descontoPercentual ?? 0), 2, '.', '') ?>"
                               min="0" max="100" step="0.01"
                               class="form-control form-control-sm text-end">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observacoes</label>
                        <textarea name="observacoes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($orcamento->observacoes ?? '') ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">Itens do Orcamento</h6>
                <button type="button" class="btn btn-success btn-sm" onclick="adicionarItem()">
                    <i class="fas fa-plus me-1"></i> Adicionar Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="tabelaItens">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:260px">Produto ou Servico <span class="text-danger">*</span></th>
                                <th style="width:120px" class="text-end">Qtd <span class="text-danger">*</span></th>
                                <th style="width:160px" class="text-end">Preco Unit.</th>
                                <th style="width:120px" class="text-end">Subtotal</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="itensBody">
                            <?php foreach ($itens as $idx => $item): /** @var OrcamentoItem $item */ ?>
                                <?php include __DIR__ . '/_item_row.php'; ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="3" class="text-end pe-3">Subtotal dos Itens:</td>
                                <td class="text-end" id="subtotalItens">R$ 0,00</td>
                                <td></td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="3" class="text-end pe-3">Desconto Geral:</td>
                                <td class="text-end text-danger" id="valorDesconto">- R$ 0,00</td>
                                <td></td>
                            </tr>
                            <tr class="table-light fw-bold">
                                <td colspan="3" class="text-end pe-3">Total do Orcamento:</td>
                                <td class="text-end" id="totalGeral">
                                    R$ <?= number_format($orcamento->valorTotal, 2, ',', '.') ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mb-4">
            <a href="/sistema_dm/public/admin/orcamentos.php" class="btn btn-secondary">
                Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>
                <?= $editando ? 'Salvar Alteracoes' : 'Criar Orcamento' ?>
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
            <form id="formNovoCliente">
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="erroNovoCliente"></div>
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="novoClienteNome">Nome *</label>
                            <input type="text" class="form-control" id="novoClienteNome" name="nome" required autocomplete="name">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="novoClienteEmail">E-mail *</label>
                            <input type="email" class="form-control" id="novoClienteEmail" name="email" required autocomplete="email">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="novoClienteTelefone">Telefone</label>
                            <input type="text" class="form-control" id="novoClienteTelefone" name="telefone" autocomplete="tel">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="novoClienteCpfCnpj">CPF/CNPJ *</label>
                            <input type="text" class="form-control" id="novoClienteCpfCnpj" name="cpf_cnpj" required autocomplete="off" placeholder="Ex: 12345678000195">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="novoClienteCidade">Cidade *</label>
                            <input type="text" class="form-control" id="novoClienteCidade" name="cidade" autocomplete="address-level2" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="novoClienteEstado">Estado (UF) *</label>
                            <input type="text" class="form-control" id="novoClienteEstado" name="estado" autocomplete="address-level1" maxlength="2" placeholder="Ex: SP" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="novoClienteObservacoes">Observacoes</label>
                        <textarea class="form-control" id="novoClienteObservacoes" name="observacoes" rows="3" autocomplete="off"></textarea>
                    </div>
                    <div class="form-group d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Salvar Cliente
                        </button>
                    </div>
                </div>
            </form>
            <div id="clientesDuplicadosWrapper" class="d-none">
                <hr>
                <div class="modal-body">
                    <h6 class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Possiveis clientes existentes</h6>
                    <p class="small text-muted">Encontramos clientes com dados semelhantes. Se for o mesmo, selecione para reutilizar:</p>
                    <div id="listaClientesDuplicados" class="list-group"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="itemRowTemplate">
    <tr class="item-row">
        <td>
            <select name="itens[__IDX__][tipo_item]" class="form-select form-select-sm tipo-item-select mb-2">
                <option value="PRODUTO" selected>Produto</option>
                <option value="SERVICO">Servico</option>
            </select>
            <select name="itens[__IDX__][produto_id]" class="form-select form-select-sm produto-select mb-2" required>
                <option value="">Selecione um produto...</option>
                <?php foreach (($produtos ?? []) as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"
                            data-preco="<?= htmlspecialchars((string)$p['preco_venda']) ?>"
                            data-nome="<?= htmlspecialchars((string)$p['nome']) ?>">
                        <?= htmlspecialchars((string)$p['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="itens[__IDX__][servico_id]" class="form-select form-select-sm servico-select mb-2" style="display:none;">
                <option value="">Selecione um servico...</option>
                <?php foreach (($servicosCatalogo ?? []) as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"
                            data-preco="<?= htmlspecialchars((string)$s['valor_base']) ?>"
                            data-nome="<?= htmlspecialchars((string)$s['nome']) ?>">
                        <?= htmlspecialchars((string)$s['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="itens[__IDX__][nome_produto]" value="" class="campo-nome-produto">
            <input type="hidden" name="itens[__IDX__][nome_servico]" value="" class="campo-nome-servico">
            <input type="hidden" name="itens[__IDX__][descricao_item]" value="" class="campo-descricao">
        </td>
        <td>
            <input type="number" name="itens[__IDX__][quantidade]" value="1" min="0.0001" step="0.0001"
                   class="form-control form-control-sm text-end campo-calc" required>
        </td>
        <td>
            <input type="number" name="itens[__IDX__][preco_unitario]" value="0" min="0" step="0.01"
                   class="form-control form-control-sm text-end campo-calc">
        </td>
        <td class="text-end align-middle subtotal-cell fw-semibold">R$ 0,00</td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerItem(this)" title="Remover">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<script>
$(document).ready(function() {
    const clienteSelectEl = $('#cliente_id');
    const modalNovoClienteEl = document.getElementById('modalNovoCliente');
    let modalNovoClienteInstance = null;

    if (modalNovoClienteEl) {
        modalNovoClienteEl.setAttribute('aria-hidden', 'true');
        modalNovoClienteEl.setAttribute('inert', '');
    }

    function formatClienteLabel(cliente) {
        if (!cliente) return 'Cliente';
        return cliente.nome || 'Cliente';
    }

    function formatCpfCnpj(valor) {
        if (!valor) return '';
        const digits = valor.replace(/\D+/g, '');
        if (digits.length <= 11) {
            return digits.replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1-$2')
                .replace(/(-\d{2})\d+?$/, '$1');
        }
        return digits.replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2')
            .replace(/(-\d{4})\d+?$/, '$1');
    }

    function formatTelefone(valor) {
        if (!valor) return '';
        const digits = valor.replace(/\D+/g, '');
        if (digits.length === 11) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
        }
        if (digits.length === 10) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
        }
        return valor;
    }

    function adicionarClienteNoSelect(clienteData) {
        const option = new Option(formatClienteLabel(clienteData), clienteData.id, true, true);
        clienteSelectEl.append(option).trigger('change');
    }

    function resetModalNovoCliente() {
        $('#formNovoCliente')[0].reset();
        $('#erroNovoCliente').addClass('d-none').text('');
        $('#clientesDuplicadosWrapper').addClass('d-none');
        $('#listaClientesDuplicados').empty();
    }

    function limparBackdropEBody() {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    function hideModal() {
        if (!modalNovoClienteEl) return;

        document.activeElement?.blur();

        if (modalNovoClienteInstance) {
            modalNovoClienteInstance.hide();
        } else {
            const bsModal = bootstrap.Modal.getInstance(modalNovoClienteEl);
            if (bsModal) {
                bsModal.hide();
            } else {
                modalNovoClienteEl.classList.remove('show');
                modalNovoClienteEl.style.display = 'none';
                modalNovoClienteEl.setAttribute('aria-hidden', 'true');
                modalNovoClienteEl.setAttribute('inert', '');
                limparBackdropEBody();
            }
        }
    }

    function initModalNovoCliente() {
        if (!modalNovoClienteEl) return;

        modalNovoClienteEl.addEventListener('hidden.bs.modal', function() {
            resetModalNovoCliente();
            limparBackdropEBody();
        });

        modalNovoClienteEl.addEventListener('show.bs.modal', function() {
            modalNovoClienteEl.removeAttribute('inert');
            modalNovoClienteEl.setAttribute('aria-hidden', 'false');
        });
    }

    clienteSelectEl.select2({
        theme: 'bootstrap-5',
        placeholder: clienteSelectEl.data('placeholder') || 'Selecione um cliente',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '/sistema_dm/public/admin/orcamentos.php?action=buscar-clientes',
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
        minimumInputLength: 0,
        language: {
            errorLoading: function() {
                return 'Os resultados nao puderam ser carregados.';
            },
            inputTooShort: function() {
                return 'Digite pelo menos 1 caractere';
            },
            noResults: function() {
                return 'Nenhum resultado encontrado';
            },
            searching: function() {
                return 'Buscando...';
            }
        }
    });

    $('#novoClienteTelefone').on('input', function() {
        this.value = formatTelefone(this.value);
    });

    $('#novoClienteCpfCnpj').on('input', function() {
        this.value = formatCpfCnpj(this.value);
    });

    $('#novoClienteEstado').on('input', function() {
        this.value = this.value.toUpperCase().substring(0, 2);
    });

    $('#formNovoCliente').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const erroEl = $('#erroNovoCliente');
        erroEl.addClass('d-none').text('');

        $.ajax({
            url: '/sistema_dm/public/admin/orcamentos.php?action=cadastrar-cliente',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json'
        }).done(function(resp) {
            if (resp.success && resp.cliente) {
                const clienteData = resp.cliente;
                clienteData.text = formatClienteLabel(clienteData);
                adicionarClienteNoSelect(clienteData);
                resetModalNovoCliente();
                setTimeout(() => {
                    hideModal();
                }, 150);
            } else if (resp.duplicates && resp.duplicates.length > 0) {
                const wrapper = $('#clientesDuplicadosWrapper');
                const lista = $('#listaClientesDuplicados');
                lista.empty();

                resp.duplicates.forEach(function(dup) {
                    const item = $('<button type="button" class="list-group-item list-group-item-action btn-usar-cliente">')
                        .html(`<strong>${dup.nome || 'Cliente'}</strong><br><small class="text-muted">${dup.email || ''} ${dup.telefone ? '| ' + dup.telefone : ''}</small>`)
                        .data('cliente', dup);
                    lista.append(item);
                });

                wrapper.removeClass('d-none');

                $('.btn-usar-cliente').on('click', function() {
                    const cliente = $(this).data('cliente');
                    cliente.text = formatClienteLabel(cliente);
                    adicionarClienteNoSelect(cliente);
                    setTimeout(() => {
                        hideModal();
                    }, 100);
                });
            } else {
                erroEl.removeClass('d-none').text(resp.message || 'Erro ao cadastrar cliente.');
            }
        }).fail(function() {
            erroEl.removeClass('d-none').text('Erro ao comunicar com o servidor. Tente novamente.');
        });
    });

    initModalNovoCliente();
});

let itemIdx = <?= count($itens) ?>;

function configurarTipoItem(row) {
    const tipoSelect = row.querySelector('.tipo-item-select');
    const produtoSelect = row.querySelector('.produto-select');
    const servicoSelect = row.querySelector('.servico-select');
    const nomeProduto = row.querySelector('.campo-nome-produto');
    const nomeServico = row.querySelector('.campo-nome-servico');

    if (!tipoSelect || !produtoSelect || !servicoSelect) {
        return;
    }

    const tipo = tipoSelect.value;
    const isProduto = tipo === 'PRODUTO';

    produtoSelect.style.display = isProduto ? 'block' : 'none';
    servicoSelect.style.display = isProduto ? 'none' : 'block';
    produtoSelect.required = isProduto;
    servicoSelect.required = !isProduto;

    if (isProduto) {
        servicoSelect.value = '';
        if (nomeServico) nomeServico.value = '';
    } else {
        produtoSelect.value = '';
        if (nomeProduto) nomeProduto.value = '';
    }

    preencherItem(row);
}

function adicionarItem() {
    const template = document.getElementById('itemRowTemplate');
    const clone = template.content.cloneNode(true);
    const html = new XMLSerializer().serializeToString(clone).replace(/__IDX__/g, itemIdx);
    const temp = document.createElement('tbody');
    temp.innerHTML = html;
    const row = temp.querySelector('tr');

    bindItemRow(row);
    document.getElementById('itensBody').appendChild(row);
    itemIdx++;
    atualizarTotal();
}

function removerItem(btn) {
    const row = btn.closest('tr');
    row.remove();
    reindexarItens();
    atualizarTotal();
}

function preencherItem(row) {
    const tipo = row.querySelector('.tipo-item-select')?.value || 'PRODUTO';
    const produtoSelect = row.querySelector('.produto-select');
    const servicoSelect = row.querySelector('.servico-select');
    const precoInput = row.querySelector('input[name*="preco_unitario"]');
    const nomeProduto = row.querySelector('.campo-nome-produto');
    const nomeServico = row.querySelector('.campo-nome-servico');

    if (tipo === 'PRODUTO') {
        const opt = produtoSelect ? produtoSelect.options[produtoSelect.selectedIndex] : null;
        if (!opt || !opt.value) {
            if (nomeProduto) nomeProduto.value = '';
            calcularSubtotalRow(row);
            return;
        }
        if (nomeProduto) nomeProduto.value = opt.getAttribute('data-nome') || opt.textContent.trim();
        if (precoInput && (precoInput.value === '' || parseFloat(precoInput.value) === 0)) {
            precoInput.value = (parseFloat(opt.getAttribute('data-preco') || '0') || 0).toFixed(2);
        }
    } else {
        const opt = servicoSelect ? servicoSelect.options[servicoSelect.selectedIndex] : null;
        if (!opt || !opt.value) {
            if (nomeServico) nomeServico.value = '';
            calcularSubtotalRow(row);
            return;
        }
        if (nomeServico) nomeServico.value = opt.getAttribute('data-nome') || opt.textContent.trim();
        if (precoInput && (precoInput.value === '' || parseFloat(precoInput.value) === 0)) {
            precoInput.value = (parseFloat(opt.getAttribute('data-preco') || '0') || 0).toFixed(2);
        }
    }

    calcularSubtotalRow(row);
}

function calcularSubtotalRow(row) {
    const qtd = parseFloat(row.querySelector('[name*="quantidade"]').value) || 0;
    const preco = parseFloat(row.querySelector('[name*="preco_unitario"]').value) || 0;
    const subtotal = qtd * preco;
    row.querySelector('.subtotal-cell').textContent = 'R$ ' + subtotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    atualizarTotal();
}

function atualizarTotal() {
    let subtotal = 0;
    document.querySelectorAll('.subtotal-cell').forEach(cell => {
        const val = (cell.textContent || '')
            .replace('R$', '')
            .replace(/\./g, '')
            .replace(',', '.')
            .trim();
        subtotal += parseFloat(val) || 0;
    });

    const descontoPercentual = parseFloat(document.getElementById('desconto_percentual')?.value || 0) || 0;
    const descontoValor = subtotal * (Math.max(0, Math.min(100, descontoPercentual)) / 100);
    const total = Math.max(0, subtotal - descontoValor);

    document.getElementById('subtotalItens').textContent = 'R$ ' + subtotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('valorDesconto').textContent = '- R$ ' + descontoValor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('totalGeral').textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function reindexarItens() {
    document.querySelectorAll('#itensBody .item-row').forEach((row, idx) => {
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/itens\[\d+\]/, `itens[${idx}]`);
        });
    });
    itemIdx = document.querySelectorAll('#itensBody .item-row').length;
}

function bindItemRow(row) {
    row.querySelector('.tipo-item-select')?.addEventListener('change', function() {
        configurarTipoItem(row);
    });
    row.querySelector('.produto-select')?.addEventListener('change', function() {
        preencherItem(row);
    });
    row.querySelector('.servico-select')?.addEventListener('change', function() {
        preencherItem(row);
    });
    row.querySelectorAll('.campo-calc').forEach(el => {
        el.addEventListener('input', () => calcularSubtotalRow(row));
    });
    configurarTipoItem(row);
}

document.getElementById('formOrcamento').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('#itensBody .item-row');
    if (rows.length === 0) {
        e.preventDefault();
        alert('Adicione ao menos um item ao orcamento antes de salvar.');
        return;
    }

    let temProdutoOuServico = false;
    for (const row of rows) {
        const tipo = row.querySelector('.tipo-item-select')?.value || 'PRODUTO';
        const produto = row.querySelector('.produto-select');
        const servico = row.querySelector('.servico-select');

        if (tipo === 'PRODUTO' && produto && produto.value) {
            temProdutoOuServico = true;
            continue;
        }

        if (tipo === 'SERVICO' && servico && servico.value) {
            temProdutoOuServico = true;
            continue;
        }

        e.preventDefault();
        alert('Cada item deve ter um produto ou servico selecionado, conforme o tipo da linha.');
        return;
    }

    if (!temProdutoOuServico) {
        e.preventDefault();
        alert('Inclua ao menos um produto ou servico no orcamento.');
    }
});

document.querySelectorAll('#itensBody .item-row').forEach(row => bindItemRow(row));
document.getElementById('desconto_percentual')?.addEventListener('input', atualizarTotal);
atualizarTotal();
</script>
