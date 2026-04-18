<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $titulo; ?></h1>
        <a href="/sistema_dm/public/admin/financeiro/contas-receber.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Voltar
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Dados da Conta a Receber</h6>
        </div>
        <div class="card-body">
            <form method="post" action="/sistema_dm/public/admin/financeiro/contas-receber.php">
                <input type="hidden" name="action" value="<?php echo isset($contaReceber) ? 'atualizar' : 'salvar'; ?>">
                <?php if (isset($contaReceber)): ?>
                    <input type="hidden" name="id" value="<?php echo $contaReceber['id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="cliente_id" class="form-label d-flex justify-content-between align-items-center">
                            <span>Cliente/Fornecedor</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCliente">
                                <i class="fas fa-user-plus me-1"></i> Novo Cliente
                            </button>
                        </label>
                        <select class="form-control" id="cliente_id" name="cliente_id" data-placeholder="Digite para buscar por nome, e-mail ou documento">
                            <option value=""></option>
                            <?php if (isset($contaReceber) && !empty($contaReceber['cliente_id'])): 
                                $clienteSel = null;
                                foreach ($clientes as $c) {
                                    if ($c['id'] == $contaReceber['cliente_id']) {
                                        $clienteSel = $c;
                                        break;
                                    }
                                }
                                if ($clienteSel):
                                    $nomeSel = !empty($clienteSel['nome']) ? $clienteSel['nome'] : 'Cliente sem nome';
                                    $empresaSel = !empty($clienteSel['empresa']) ? ' - ' . $clienteSel['empresa'] : '';
                                    $textoSel = trim($nomeSel . $empresaSel);
                            ?>
                                <option value="<?php echo $clienteSel['id']; ?>" selected>
                                    <?php echo htmlspecialchars($textoSel); ?>
                                </option>
                            <?php endif; endif; ?>
                        </select>
                        <small class="text-muted d-block mt-1">Pesquise clientes existentes ou cadastre um novo sem sair desta tela.</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="forma_pagamento_id" class="form-label">Forma de Pagamento *</label>
                        <select class="form-select" id="forma_pagamento_id" name="forma_pagamento_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($formasPagamento as $fp): ?>
                                <option value="<?php echo $fp['id']; ?>" 
                                    <?php echo (isset($contaReceber) && (($contaReceber['forma_pagamento_id'] ?? null) == $fp['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fp['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="valor_original" class="form-label">Valor Original *</label>
                        <input type="number" step="0.01" class="form-control" id="valor_original" name="valor_original" 
                               value="<?php echo isset($contaReceber) ? ($contaReceber['valor'] ?? ($contaReceber['valor_original'] ?? '')) : ''; ?>" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="data_vencimento" class="form-label">Data de Vencimento *</label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" 
                               value="<?php echo isset($contaReceber) ? $contaReceber['data_vencimento'] : ''; ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição/Histórico</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo isset($contaReceber) ? htmlspecialchars($contaReceber['descricao']) : ''; ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="2"><?php echo isset($contaReceber) ? htmlspecialchars($contaReceber['observacoes']) : ''; ?></textarea>
                </div>
                
                <div class="d-flex justify-content-end">
                    <a href="/sistema_dm/public/admin/financeiro/contas-receber.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Novo Cliente -->
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
                        <label for="novoClienteObservacoes">Observações</label>
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
                    <h6 class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Possíveis clientes existentes</h6>
                    <p class="small text-muted">Encontramos clientes com dados semelhantes. Se for o mesmo, selecione para reutilizar:</p>
                    <div id="listaClientesDuplicados" class="list-group"></div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Scripts necessários -->
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
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2')
            .replace(/(-\d{2})\d+?$/, '$1');
    }

    function formatTelefone(valor) {
        if (!valor) return '';
        const digits = valor.replace(/\D+/g, '');
        if (digits.length <= 10) {
            return digits.replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{4})(\d)/, '$1-$2')
                .replace(/(-\d{4})\d+?$/, '$1');
        }
        return digits.replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d)/, '$1-$2')
            .replace(/(-\d{4})\d+?$/, '$1');
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
            url: '/sistema_dm/public/admin/financeiro/contas-receber.php?action=buscar-clientes',
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
                return 'Os resultados não puderam ser carregados.';
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
            url: '/sistema_dm/public/admin/financeiro/contas-receber.php?action=cadastrar-cliente',
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
                        .html(`<strong>${dup.nome || 'Cliente'}</strong>${dup.empresa ? ' - ' + dup.empresa : ''}<br><small class="text-muted">${dup.email || ''} ${dup.telefone ? '| ' + dup.telefone : ''}</small>`)
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

    // Formatação de CNPJ para nova empresa
    $('#novaEmpresaCnpj').on('input', function() {
        this.value = formatCpfCnpj(this.value);
    });

    // Formatação de telefone para nova empresa
    $('#novaEmpresaTelefone').on('input', function() {
        this.value = formatTelefone(this.value);
    });

    // Formatação de CEP para nova empresa
    $('#novaEmpresaCep').on('input', function() {
        this.value = this.value.replace(/\D/g, '').replace(/^(\d{5})(\d)/, '$1-$2');
    });

    initModalNovoCliente();
});
</script>
