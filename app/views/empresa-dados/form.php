<?php
$empresa = is_array($empresa ?? null) ? $empresa : [];
$nomeBloqueado = (bool)($nomeBloqueado ?? false);
$formAction = function_exists('tenantUrl') ? tenantUrl('admin/dados-empresa.php') : '/sistema_dm/public/admin/dados-empresa.php';
$certificadoPath = trim((string)($empresa['certificado_path'] ?? ''));
$certificadoNome = $certificadoPath !== '' ? basename(str_replace('\\', '/', $certificadoPath)) : '';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dados da Empresa</h1>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <form id="empresaForm" method="post" action="<?php echo htmlspecialchars($formAction); ?>" enctype="multipart/form-data" novalidate>
                <div class="border rounded p-3 p-md-4 mb-4 bg-light">
                    <div class="mb-3">
                        <h6 class="mb-1 font-weight-bold text-primary">Informacoes principais</h6>
                        
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome da empresa (Razao social) *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars((string)($empresa['nome'] ?? '')); ?>" required>
                            
                        </div>
                        <div class="col-md-3">
                            <label for="cnpj" class="form-label">CNPJ *</label>
                            <input type="text" class="form-control" id="cnpj" name="cnpj" inputmode="numeric" maxlength="18" value="<?php echo htmlspecialchars((string)($empresa['cnpj'] ?? '')); ?>" readonly required>
                        </div>
                        <div class="col-md-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" inputmode="numeric" maxlength="15" value="<?php echo htmlspecialchars((string)($empresa['telefone'] ?? '')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="contato" class="form-label">Contato</label>
                            <input type="text" class="form-control" id="contato" name="contato" value="<?php echo htmlspecialchars((string)($empresa['contato'] ?? '')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars((string)($empresa['email'] ?? '')); ?>">
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 p-md-4 mb-4">
                    <div class="mb-3">
                        <h6 class="mb-1 font-weight-bold text-primary">Endereco</h6>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="logradouro" class="form-label">Logradouro</label>
                            <input type="text" class="form-control" id="logradouro" name="logradouro" value="<?php echo htmlspecialchars((string)($empresa['logradouro'] ?? '')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="numero" class="form-label">Numero</label>
                            <input type="text" class="form-control" id="numero" name="numero" value="<?php echo htmlspecialchars((string)($empresa['numero'] ?? '')); ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="complemento" class="form-label">Complemento</label>
                            <input type="text" class="form-control" id="complemento" name="complemento" value="<?php echo htmlspecialchars((string)($empresa['complemento'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="bairro" class="form-label">Bairro</label>
                            <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo htmlspecialchars((string)($empresa['bairro'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="cidade" class="form-label">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars((string)($empresa['cidade'] ?? '')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="uf" class="form-label">UF</label>
                            <input type="text" class="form-control text-uppercase" id="uf" name="uf" maxlength="2" value="<?php echo htmlspecialchars((string)($empresa['uf'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="cep" class="form-label">CEP</label>
                            <input type="text" class="form-control" id="cep" name="cep" inputmode="numeric" maxlength="9" value="<?php echo htmlspecialchars((string)($empresa['cep'] ?? '')); ?>">
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 p-md-4 mb-4 bg-light">
                    <div class="mb-3">
                        <h6 class="mb-1 font-weight-bold text-primary">Fiscal e NF-e</h6>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="inscricao_estadual" class="form-label">Inscricao estadual</label>
                            <input type="text" class="form-control" id="inscricao_estadual" name="inscricao_estadual" value="<?php echo htmlspecialchars((string)($empresa['inscricao_estadual'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="inscricao_municipal" class="form-label">Inscricao municipal</label>
                            <input type="text" class="form-control" id="inscricao_municipal" name="inscricao_municipal" value="<?php echo htmlspecialchars((string)($empresa['inscricao_municipal'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="codigo_municipio" class="form-label">Codigo do municipio</label>
                            <input type="text" class="form-control" id="codigo_municipio" name="codigo_municipio" inputmode="numeric" maxlength="7" value="<?php echo htmlspecialchars((string)($empresa['codigo_municipio'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="regime_tributario" class="form-label">Regime tributario</label>
                            <select class="form-select" id="regime_tributario" name="regime_tributario">
                                <option value="1" <?php echo ((string)($empresa['regime_tributario'] ?? '1') === '1') ? 'selected' : ''; ?>>1 - Simples Nacional</option>
                                <option value="2" <?php echo ((string)($empresa['regime_tributario'] ?? '') === '2') ? 'selected' : ''; ?>>2 - Simples excesso sublimite</option>
                                <option value="3" <?php echo ((string)($empresa['regime_tributario'] ?? '') === '3') ? 'selected' : ''; ?>>3 - Regime normal</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="ambiente_nfe" class="form-label">Ambiente NF-e</label>
                            <select class="form-select" id="ambiente_nfe" name="ambiente_nfe">
                                <option value="1" <?php echo ((string)($empresa['ambiente_nfe'] ?? '') === '1') ? 'selected' : ''; ?>>1 - Producao</option>
                                <option value="2" <?php echo ((string)($empresa['ambiente_nfe'] ?? '2') === '2') ? 'selected' : ''; ?>>2 - Homologacao</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="serie_nfe" class="form-label">Serie NF-e</label>
                            <input type="text" class="form-control" id="serie_nfe" name="serie_nfe" inputmode="numeric" maxlength="3" value="<?php echo htmlspecialchars((string)($empresa['serie_nfe'] ?? '001')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="proximo_numero_nfe" class="form-label">Proximo numero NF-e</label>
                            <input type="number" class="form-control" id="proximo_numero_nfe" name="proximo_numero_nfe" min="1" value="<?php echo htmlspecialchars((string)($empresa['proximo_numero_nfe'] ?? '1')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="certificado_arquivo" class="form-label">Certificado digital (.pfx ou .p12)</label>
                            <input type="file" class="form-control" id="certificado_arquivo" name="certificado_arquivo" accept=".pfx,.p12,application/x-pkcs12">
                            <input type="hidden" name="certificado_path" value="<?php echo htmlspecialchars($certificadoPath); ?>">
                            <?php if ($certificadoNome !== ''): ?>
                                <div class="form-text">Arquivo atual: <?php echo htmlspecialchars($certificadoNome); ?></div>
                            <?php else: ?>
                                <div class="form-text">Envie aqui o certificado para preencher o caminho automaticamente.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label for="certificado_senha" class="form-label">Senha do certificado</label>
                            <input type="password" class="form-control" id="certificado_senha" name="certificado_senha" value="" autocomplete="new-password">
                            <div class="form-text">Deixe em branco para manter a senha atual.</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4" id="btnSalvarEmpresa">
                        <i class="fas fa-save"></i> Salvar dados
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
(function () {
    const form = document.getElementById('empresaForm');
    const cnpjInput = document.getElementById('cnpj');
    const ufInput = document.getElementById('uf');
    const serieInput = document.getElementById('serie_nfe');

    function onlyDigits(value) {
        return (value || '').replace(/\D/g, '');
    }

    function validarCnpj(cnpj) {
        cnpj = onlyDigits(cnpj);

        if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) {
            return false;
        }

        let tamanho = 12;
        let numeros = cnpj.substring(0, tamanho);
        let digitos = cnpj.substring(tamanho);
        let soma = 0;
        let pos = tamanho - 7;

        for (let i = tamanho; i >= 1; i--) {
            soma += parseInt(numeros.charAt(tamanho - i), 10) * pos--;
            if (pos < 2) pos = 9;
        }

        let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
        if (resultado !== parseInt(digitos.charAt(0), 10)) {
            return false;
        }

        tamanho = 13;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;

        for (let i = tamanho; i >= 1; i--) {
            soma += parseInt(numeros.charAt(tamanho - i), 10) * pos--;
            if (pos < 2) pos = 9;
        }

        resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
        return resultado === parseInt(cnpj.charAt(13), 10);
    }

    if (window.jQuery && typeof window.jQuery.fn.mask === 'function') {
        window.jQuery('#cnpj').mask('00.000.000/0000-00');
        window.jQuery('#cep').mask('00000-000');
        window.jQuery('#telefone').mask('(00) 00000-0000');
    }

    cnpjInput.addEventListener('input', function () {
        this.setCustomValidity(validarCnpj(this.value) ? '' : 'Informe um CNPJ valido.');
    });

    ufInput.addEventListener('input', function () {
        this.value = this.value.toUpperCase().slice(0, 2);
    });

    serieInput.addEventListener('input', function () {
        this.value = onlyDigits(this.value).slice(0, 3);
    });

    form.addEventListener('submit', function (event) {
        if (!validarCnpj(cnpjInput.value)) {
            cnpjInput.setCustomValidity('Informe um CNPJ valido.');
            cnpjInput.reportValidity();
            event.preventDefault();
            return;
        }

        cnpjInput.setCustomValidity('');
    });
})();
</script>
