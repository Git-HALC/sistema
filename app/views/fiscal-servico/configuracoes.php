<?php

declare(strict_types=1);

$config = $config ?? null;
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Configuracoes da NFS-e</h1>
            <p class="text-muted mb-0">Dados do emitente e credenciais do webservice municipal.</p>
        </div>
        <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=index')); ?>" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=salvar-configuracoes')); ?>" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Support\CsrfProtection::token()); ?>">

                <div class="col-md-4">
                    <label for="cnpj" class="form-label">CNPJ</label>
                    <input type="text" id="cnpj" name="cnpj" class="form-control" value="<?php echo htmlspecialchars((string)($config?->cnpj ?? '')); ?>" required>
                </div>
                <div class="col-md-8">
                    <label for="razao_social" class="form-label">Razao social</label>
                    <input type="text" id="razao_social" name="razao_social" class="form-control" value="<?php echo htmlspecialchars((string)($config?->razao_social ?? '')); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="inscricao_municipal" class="form-label">Inscricao municipal</label>
                    <input type="text" id="inscricao_municipal" name="inscricao_municipal" class="form-control" value="<?php echo htmlspecialchars((string)($config?->inscricao_municipal ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label for="codigo_municipio_ibge" class="form-label">Codigo municipio IBGE</label>
                    <input type="text" id="codigo_municipio_ibge" name="codigo_municipio_ibge" class="form-control" value="<?php echo htmlspecialchars((string)($config?->codigo_municipio_ibge ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label for="aliquota_iss_padrao" class="form-label">Aliquota ISS padrao</label>
                    <input type="number" step="0.0001" id="aliquota_iss_padrao" name="aliquota_iss_padrao" class="form-control" value="<?php echo htmlspecialchars((string)($config?->aliquota_iss_padrao ?? '0')); ?>">
                </div>
                <div class="col-md-6">
                    <label for="url_webservice_homologacao" class="form-label">URL homologacao</label>
                    <input type="url" id="url_webservice_homologacao" name="url_webservice_homologacao" class="form-control" value="<?php echo htmlspecialchars((string)($config?->url_webservice_homologacao ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label for="url_webservice_producao" class="form-label">URL producao</label>
                    <input type="url" id="url_webservice_producao" name="url_webservice_producao" class="form-control" value="<?php echo htmlspecialchars((string)($config?->url_webservice_producao ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label for="usuario_webservice" class="form-label">Usuario webservice</label>
                    <input type="text" id="usuario_webservice" name="usuario_webservice" class="form-control" value="<?php echo htmlspecialchars((string)($config?->usuario_webservice ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label for="senha_webservice" class="form-label">Senha webservice</label>
                    <input type="password" id="senha_webservice" name="senha_webservice" class="form-control" value="">
                </div>
                <div class="col-md-4">
                    <label for="ambiente" class="form-label">Ambiente</label>
                    <select id="ambiente" name="ambiente" class="form-select">
                        <option value="homologacao" <?php echo (($config?->ambiente ?? 'homologacao') === 'homologacao') ? 'selected' : ''; ?>>Homologacao</option>
                        <option value="producao" <?php echo (($config?->ambiente ?? '') === 'producao') ? 'selected' : ''; ?>>Producao</option>
                    </select>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Salvar configuracoes</button>
                </div>
            </form>
        </div>
    </div>
</div>
