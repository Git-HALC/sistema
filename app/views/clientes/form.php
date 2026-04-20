<?php
// Incluir o cabeçalho
$clienteBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_cliente_base_url', 'admin/clientes.php')
    : tenantUrl('admin/clientes.php');
$salvarClienteUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($clienteBaseUrl, 'action=salvar')
    : $clienteBaseUrl . '?action=salvar';
$titulo_pagina = $titulo ?? 'Clientes';
include __DIR__ . '/../../../public/includes/header.php';
?>

<!-- Conteúdo da Página -->
<div class="container-fluid">
    <!-- Título da Página -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $titulo_pagina; ?></h1>
        <a href="<?php echo htmlspecialchars($clienteBaseUrl); ?>" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Voltar para a lista
        </a>
    </div>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Dados do Cliente</h6>
        </div>
        <div class="card-body">
            <div id="formAlert" class="alert d-none" role="alert"></div>
            <form id="clienteForm" method="post" action="<?php echo htmlspecialchars($salvarClienteUrl); ?>">
                <?php if (isset($cliente['id'])): ?>
                    <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="nome">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" autocomplete="name"
                               value="<?php echo htmlspecialchars($cliente['nome'] ?? ($_SESSION['form_data']['nome'] ?? '')); ?>" 
                               required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cpf_cnpj">CPF/CNPJ *</label>
                        <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" placeholder="000.000.000-00 ou 00.000.000/0000-00"
                               inputmode="numeric" maxlength="18"
                               value="<?php echo htmlspecialchars($cliente['cpf_cnpj'] ?? ($_SESSION['form_data']['cpf_cnpj'] ?? '')); ?>" 
                               required>
                        <small class="form-text text-muted">Informe CPF (11 dígitos) ou CNPJ (14 dígitos)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="email">E-mail *</label>
                           <input type="email" class="form-control" id="email" name="email" autocomplete="email"
                               value="<?php echo htmlspecialchars($cliente['email'] ?? ($_SESSION['form_data']['email'] ?? '')); ?>" 
                               required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="telefone">Telefone *</label>
                           <input type="tel" class="form-control" id="telefone" name="telefone" autocomplete="tel"
                               value="<?php echo htmlspecialchars($cliente['telefone'] ?? ($_SESSION['form_data']['telefone'] ?? '')); ?>" required>
                    </div>
                </div>

                <?php
                $ehCliente = (bool)($cliente['eh_cliente'] ?? ($_SESSION['form_data']['eh_cliente'] ?? true));
                $ehFornecedor = (bool)($cliente['eh_fornecedor'] ?? ($_SESSION['form_data']['eh_fornecedor'] ?? false));
                ?>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label class="d-block">Perfil de cadastro *</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="eh_cliente" name="eh_cliente" value="1" <?php echo $ehCliente ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="eh_cliente">Cliente</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="eh_fornecedor" name="eh_fornecedor" value="1" <?php echo $ehFornecedor ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="eh_fornecedor">Fornecedor</label>
                        </div>
                        <small class="form-text text-muted">Pode marcar um ou os dois perfis.</small>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="prazo_faturamento_dias">
                            Prazo de faturamento <small class="text-muted">(dias)</small>
                        </label>
                        <?php $prazoFat = (int)($cliente['prazo_faturamento_dias'] ?? ($_SESSION['form_data']['prazo_faturamento_dias'] ?? 0)); ?>
                        <input type="number" min="0" max="365" step="1"
                               class="form-control" id="prazo_faturamento_dias" name="prazo_faturamento_dias"
                               value="<?php echo $prazoFat; ?>">
                        <small class="form-text text-muted">
                            Usado em vendas <strong>A Faturar</strong>. Deixe <strong>0</strong> para 30 dias automáticos.
                        </small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="logradouro">Logradouro</label>
                        <input type="text" class="form-control" id="logradouro" name="logradouro" autocomplete="address-line1"
                               value="<?php echo htmlspecialchars($cliente['logradouro'] ?? ($_SESSION['form_data']['logradouro'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="numero_endereco">Numero</label>
                        <input type="text" class="form-control" id="numero_endereco" name="numero_endereco" autocomplete="address-line2"
                               value="<?php echo htmlspecialchars($cliente['numero_endereco'] ?? ($_SESSION['form_data']['numero_endereco'] ?? '')); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="complemento">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento"
                               value="<?php echo htmlspecialchars($cliente['complemento'] ?? ($_SESSION['form_data']['complemento'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="bairro">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro"
                               value="<?php echo htmlspecialchars($cliente['bairro'] ?? ($_SESSION['form_data']['bairro'] ?? '')); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cidade">Cidade *</label>
                           <input type="text" class="form-control" id="cidade" name="cidade" autocomplete="address-level2"
                               list="listaMunicipios"
                               value="<?php echo htmlspecialchars($cliente['cidade'] ?? ($_SESSION['form_data']['cidade'] ?? '')); ?>" required>
                        <datalist id="listaMunicipios"></datalist>
                        <small class="form-text text-muted">Escolha a cidade para preencher o código do município automaticamente.</small>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="estado">Estado (UF) *</label>
                           <input type="text" class="form-control" id="estado" name="estado" maxlength="2" autocomplete="address-level1"
                               value="<?php echo htmlspecialchars($cliente['estado'] ?? ($_SESSION['form_data']['estado'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="cep">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" inputmode="numeric" maxlength="9" autocomplete="postal-code"
                               value="<?php echo htmlspecialchars($cliente['cep'] ?? ($_SESSION['form_data']['cep'] ?? '')); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="codigo_municipio">Codigo do municipio *</label>
                        <input type="text" class="form-control" id="codigo_municipio" name="codigo_municipio" inputmode="numeric" maxlength="7" readonly
                               value="<?php echo htmlspecialchars($cliente['codigo_municipio'] ?? ($_SESSION['form_data']['codigo_municipio'] ?? '')); ?>" required>
                        <small class="form-text text-muted">Codigo IBGE com 7 digitos.</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="ind_ie_dest">Indicador IE</label>
                        <?php $indIeDest = (string)($cliente['ind_ie_dest'] ?? ($_SESSION['form_data']['ind_ie_dest'] ?? '9')); ?>
                        <select class="form-control" id="ind_ie_dest" name="ind_ie_dest">
                            <option value="9" <?php echo $indIeDest === '9' ? 'selected' : ''; ?>>9 - Nao contribuinte</option>
                            <option value="1" <?php echo $indIeDest === '1' ? 'selected' : ''; ?>>1 - Contribuinte ICMS</option>
                            <option value="2" <?php echo $indIeDest === '2' ? 'selected' : ''; ?>>2 - Isento</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="ie">Inscricao estadual</label>
                        <input type="text" class="form-control" id="ie" name="ie"
                               value="<?php echo htmlspecialchars($cliente['ie'] ?? ($_SESSION['form_data']['ie'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" id="btnSalvar" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <a href="<?php echo htmlspecialchars($clienteBaseUrl); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Limpar dados do formulário da sess?o
if (isset($_SESSION['form_data'])) {
    unset($_SESSION['form_data']);
}
?>

<!-- Incluir o rodapé -->
<?php include __DIR__ . '/../../../public/includes/footer.php'; ?>

<!-- jQuery Mask Plugin -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script>
$(document).ready(function() {
    const municipioCache = {};
    const cidadeInput = document.getElementById('cidade');
    const estadoInput = document.getElementById('estado');
    const codigoMunicipioInput = document.getElementById('codigo_municipio');
    const listaMunicipios = document.getElementById('listaMunicipios');

    function normalizarTexto(valor) {
        return (valor || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toUpperCase();
    }

    function preencherCodigoMunicipio() {
        const uf = normalizarTexto(estadoInput?.value || '');
        const cidade = normalizarTexto(cidadeInput?.value || '');
        const municipios = municipioCache[uf] || [];
        const municipio = municipios.find(function(item) {
            return item.nomeNormalizado === cidade;
        });

        codigoMunicipioInput.value = municipio ? municipio.codigo : '';
    }

    function renderizarMunicipios(uf) {
        const municipios = municipioCache[uf] || [];
        listaMunicipios.innerHTML = municipios.map(function(item) {
            return '<option value="' + item.nome + '"></option>';
        }).join('');
        preencherCodigoMunicipio();
    }

    function carregarMunicipios(uf) {
        const ufNormalizada = normalizarTexto(uf);
        if (ufNormalizada.length !== 2) {
            listaMunicipios.innerHTML = '';
            codigoMunicipioInput.value = '';
            return;
        }

        if (municipioCache[ufNormalizada]) {
            renderizarMunicipios(ufNormalizada);
            return;
        }

        listaMunicipios.innerHTML = '';
        codigoMunicipioInput.value = '';

        fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + encodeURIComponent(ufNormalizada) + '/municipios')
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Falha ao buscar municipios');
                }
                return response.json();
            })
            .then(function(municipios) {
                municipioCache[ufNormalizada] = Array.isArray(municipios)
                    ? municipios.map(function(item) {
                        const codigo = String(item.id || '');
                        const nome = String(item.nome || '');
                        return {
                            codigo: codigo,
                            nome: nome,
                            nomeNormalizado: normalizarTexto(nome)
                        };
                    })
                    : [];
                renderizarMunicipios(ufNormalizada);
            })
            .catch(function() {
                listaMunicipios.innerHTML = '';
                codigoMunicipioInput.value = '';
            });
    }

    if (typeof $.fn.mask === 'function') {
        $('#telefone').mask('(00) 00000-0000');
        $('#cep').mask('00000-000');
        
        // Mask dinâmico para CPF/CNPJ
        $('#cpf_cnpj').on('input', function() {
            let valor = this.value.replace(/\D/g, '').slice(0, 14);
            
            if (valor.length <= 11) {
                valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            } else {
                valor = valor.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            }
            
            this.value = valor;

            const digitos = this.value.replace(/\D/g, '');
            this.setCustomValidity(digitos.length >= 11 && digitos.length <= 14 ? '' : 'CPF/CNPJ deve ter entre 11 e 14 dígitos.');
        });
    } else {
        console.error('jQuery Mask não foi carregado corretamente!');
    }
    
    // Fechar apenas alertas globais (não o #formAlert) após 5s
    setTimeout(function() {
        $('.alert').not('#formAlert').alert('close');
    }, 5000);
    
    $('#estado').on('input', function(){
        this.value = this.value.toUpperCase().slice(0,2);
        carregarMunicipios(this.value);
    });
    $('#cep').on('input', function(){ this.value = this.value.replace(/[^\d-]/g, '').slice(0,9); });
    $('#ind_ie_dest').on('change', function() {
        const exigeIe = this.value === '1';
        $('#ie').prop('required', exigeIe);
        if (!exigeIe) {
            $('#ie').get(0)?.setCustomValidity('');
        }
    }).trigger('change');
    $('#cidade').on('input change blur', preencherCodigoMunicipio);
    carregarMunicipios(estadoInput?.value || '');

    $('#clienteForm').on('submit', function(e){
        e.preventDefault();
        const $btn = $('#btnSalvar');
        const $alert = $('#formAlert');
        const cpfCnpjInput = document.getElementById('cpf_cnpj');
        const cpfCnpjDigitos = (cpfCnpjInput?.value || '').replace(/\D/g, '');

        if (cpfCnpjDigitos.length < 11 || cpfCnpjDigitos.length > 14) {
            $alert.removeClass('d-none alert-success').addClass('alert alert-danger')
                .text('CPF/CNPJ deve ter entre 11 e 14 dígitos.');
            $btn.prop('disabled', false);
            return;
        }

        const codigoMunicipio = (document.getElementById('codigo_municipio')?.value || '').replace(/\D/g, '');
        if (codigoMunicipio.length !== 7) {
            $alert.removeClass('d-none alert-success').addClass('alert alert-danger')
                .text('Código do município deve ter 7 dígitos.');
            $btn.prop('disabled', false);
            return;
        }

        if ($('#ind_ie_dest').val() === '1' && !$('#ie').val().trim()) {
            $alert.removeClass('d-none alert-success').addClass('alert alert-danger')
                .text('Informe a inscrição estadual para contribuintes de ICMS.');
            $btn.prop('disabled', false);
            return;
        }

        $btn.prop('disabled', true);
        $alert.removeClass('alert-danger alert-success').addClass('d-none').empty();

        const formData = new FormData(this);
        fetch(this.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(async (r) => {
            const ct = (r.headers.get('content-type') || '').toLowerCase();
            if (!ct.includes('application/json')) {
                // Se não vier JSON, pode ser redirect para login ou erro HTML
                const txt = await r.text();
                console.warn('Resposta não-JSON recebida no salvar de clientes.', {status: r.status, body: txt});
                if (r.status === 401 || r.status === 403 || (txt && txt.includes('<form') && txt.toLowerCase().includes('login'))) {
                    window.location.reload();
                    return;
                }
                throw new Error('Resposta não-JSON do servidor');
            }
            return r.json();
        }).then(json => {
            if (!json.success) {
                const errors = Array.isArray(json.errors) ? json.errors : [json.message || 'Não foi possível salvar.'];
                $alert.removeClass('d-none').addClass('alert alert-danger');
                $alert.html(errors.map(e => `<div>${e}</div>`).join(''));
                $btn.prop('disabled', false);
            } else {
                if (json.redirect) { window.location.href = json.redirect; return; }
                $alert.removeClass('d-none').addClass('alert alert-success').text(json.message || 'Salvo com sucesso.');
                $btn.prop('disabled', false);
            }
        }).catch((err) => {
            console.error('Falha ao salvar cliente via AJAX:', err);
            $alert.removeClass('d-none').addClass('alert alert-danger').text('Erro inesperado ao salvar.');
            $btn.prop('disabled', false);
        });
    });
});
</script>
