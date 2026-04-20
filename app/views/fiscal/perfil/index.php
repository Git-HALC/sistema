<?php
use App\Support\CsrfProtection;

$perfil = $perfil ?? [];
$empresa = $empresa ?? [];
$tributacao = $tributacao ?? [];
$servicos = $servicos ?? [];
$csrfToken = $csrfToken ?? CsrfProtection::token();

$ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
$trByUf = [];
foreach ($tributacao as $t) { $trByUf[strtoupper($t['uf_destino'])] = $t; }

$action = '/sistema_dm/public/admin/fiscal/perfil.php';
?>
<div class="container-fluid py-3" style="color: var(--text-primary);">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-receipt me-2"></i>Perfil Tributário</h1>
            <p class="text-muted mb-0 small">Configure os dados fiscais da empresa, NFC-e, NFS-e Nacional, tributação por UF e serviços ISS.</p>
        </div>
        <div>
            <span class="badge bg-<?= ((int)($perfil['ambiente_nfce'] ?? 2) === 1) ? 'danger' : 'warning' ?>">
                NFC-e: <?= ((int)($perfil['ambiente_nfce'] ?? 2) === 1) ? 'Producao' : 'Homologacao' ?>
            </span>
            <span class="badge bg-<?= (($perfil['ambiente_nfse'] ?? '') === 'producao') ? 'danger' : 'warning' ?>">
                NFS-e: <?= (($perfil['ambiente_nfse'] ?? '') === 'producao') ? 'Producao' : 'Homologacao' ?>
            </span>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2 py-2 small">
        <i class="fas fa-info-circle mt-1"></i>
        <div>
            Os campos gerais (CNPJ, IE, IM, código do município, regime, ambiente NF-e, série, certificado)
            ficam em <a href="<?= htmlspecialchars('/sistema_dm/public/admin/dados-empresa.php') ?>"><strong>Configurações → Dados da Empresa</strong></a>.
            Aqui ajuste apenas o que é específico do perfil tributário: CSC da NFC-e, credenciais NFS-e, tributação por UF e configuração ISS dos serviços.
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-nfce"><i class="fas fa-file-invoice me-1"></i>NFC-e (CSC)</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-nfse"><i class="fas fa-file-contract me-1"></i>NFS-e Nacional</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-tributacao"><i class="fas fa-map me-1"></i>Tributação por UF</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-servicos"><i class="fas fa-concierge-bell me-1"></i>Serviços / ISS</a></li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-body">

        <!-- NFC-e (apenas CSC; série/numero/ambiente/cert ficam em Dados da Empresa) -->
        <div class="tab-pane fade show active" id="tab-nfce">
            <form method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="salvar-nfce">
                <p class="small text-muted mb-3">
                    Ambiente, série e número são compartilhados com os campos NF-e em
                    <a href="<?= htmlspecialchars('/sistema_dm/public/admin/dados-empresa.php') ?>">Dados da Empresa</a>.
                    Aqui você configura apenas o CSC (Código de Segurança do Contribuinte) emitido pela SEFAZ.
                </p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <h6 class="small text-muted mb-2">HOMOLOGAÇÃO</h6>
                        <label class="form-label small">CSC ID</label>
                        <input type="text" name="csc_id_homologacao" class="form-control mb-2" value="<?= htmlspecialchars((string)($perfil['csc_id_homologacao'] ?? '')) ?>">
                        <label class="form-label small">CSC Token</label>
                        <input type="text" name="csc_token_homologacao" class="form-control" value="<?= htmlspecialchars((string)($perfil['csc_token_homologacao'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <h6 class="small text-muted mb-2">PRODUÇÃO</h6>
                        <label class="form-label small">CSC ID</label>
                        <input type="text" name="csc_id_producao" class="form-control mb-2" value="<?= htmlspecialchars((string)($perfil['csc_id_producao'] ?? '')) ?>">
                        <label class="form-label small">CSC Token</label>
                        <input type="text" name="csc_token_producao" class="form-control" value="<?= htmlspecialchars((string)($perfil['csc_token_producao'] ?? '')) ?>">
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-primary" id="btnTestarSefaz">
                        <i class="fas fa-satellite-dish me-1"></i>Testar conexão SEFAZ
                    </button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar NFC-e</button>
                </div>
            </form>
        </div>

        <!-- NFS-e Nacional -->
        <div class="tab-pane fade" id="tab-nfse">
            <form method="post" action="<?= $action ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="salvar-nfse">
                <div class="alert alert-info small"><i class="fas fa-info-circle me-1"></i>Emissor Nacional (<a href="https://www.nfse.gov.br" target="_blank">nfse.gov.br</a>) — 3 modos de autenticação: usuário/senha, certificado A1/A3 e gov.br (OAuth).</div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small">Ambiente *</label>
                        <select name="ambiente_nfse" class="form-select">
                            <option value="homologacao" <?= ($perfil['ambiente_nfse'] ?? '') === 'homologacao' ? 'selected' : '' ?>>Homologação</option>
                            <option value="producao" <?= ($perfil['ambiente_nfse'] ?? '') === 'producao' ? 'selected' : '' ?>>Produção</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Modo de autenticação *</label>
                        <select name="nfse_modo_auth" class="form-select" id="modoAuthSelect">
                            <option value="usuario_senha" <?= ($perfil['nfse_modo_auth'] ?? '') === 'usuario_senha' ? 'selected' : '' ?>>Usuário e senha</option>
                            <option value="certificado" <?= ($perfil['nfse_modo_auth'] ?? '') === 'certificado' ? 'selected' : '' ?>>Certificado Digital (A1/A3)</option>
                            <option value="govbr" <?= ($perfil['nfse_modo_auth'] ?? '') === 'govbr' ? 'selected' : '' ?>>gov.br (OAuth)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Série RPS</label>
                        <input type="text" name="serie_rps" class="form-control" value="<?= htmlspecialchars((string)($perfil['serie_rps'] ?? 'RPS')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Número RPS atual</label>
                        <input type="number" name="numero_rps_atual" class="form-control" value="<?= (int)($perfil['numero_rps_atual'] ?? 0) ?>" min="0">
                    </div>

                    <!-- Modo usuário/senha -->
                    <div class="col-md-6 modo-group" data-modo="usuario_senha">
                        <label class="form-label small">Usuário</label>
                        <input type="text" name="nfse_usuario" class="form-control" autocomplete="off" value="<?= htmlspecialchars((string)($perfil['nfse_usuario'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6 modo-group" data-modo="usuario_senha">
                        <label class="form-label small">Senha <?php if (!empty($perfil['nfse_senha_cifrada'])): ?><span class="text-muted">(já cadastrada, deixe em branco para manter)</span><?php endif; ?></label>
                        <div class="input-group">
                            <input type="password" name="nfse_senha" class="form-control" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword(this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <?php if (!empty($perfil['nfse_senha_cifrada'])): ?>
                            <input type="hidden" name="manter_senha" value="1">
                        <?php endif; ?>
                    </div>

                    <!-- Modo certificado -->
                    <div class="col-md-6 modo-group" data-modo="certificado">
                        <label class="form-label small">Arquivo do certificado (.pfx)</label>
                        <input type="file" name="nfse_certificado_file" class="form-control" accept=".pfx,application/x-pkcs12">
                        <?php if (!empty($perfil['nfse_certificado_path'])): ?>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-check text-success me-1"></i>
                                Atual: <code><?= htmlspecialchars(basename((string)$perfil['nfse_certificado_path'])) ?></code>
                                <span class="ms-2">(deixe em branco para manter)</span>
                            </small>
                            <input type="hidden" name="nfse_certificado_path_atual" value="<?= htmlspecialchars((string)$perfil['nfse_certificado_path']) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 modo-group" data-modo="certificado">
                        <label class="form-label small">Senha do certificado <?php if (!empty($perfil['nfse_certificado_senha_cifrada'])): ?><span class="text-muted">(já cadastrada, em branco = manter)</span><?php endif; ?></label>
                        <input type="password" name="nfse_certificado_senha" class="form-control" autocomplete="off">
                        <?php if (!empty($perfil['nfse_certificado_senha_cifrada'])): ?>
                            <input type="hidden" name="manter_cert_senha" value="1">
                        <?php endif; ?>
                    </div>

                    <!-- Modo gov.br -->
                    <div class="col-12 modo-group" data-modo="govbr">
                        <div class="alert alert-secondary small mb-0">
                            Clique em <strong>Autenticar</strong> para iniciar o fluxo OAuth da conta gov.br.
                            O token será armazenado cifrado com validação automática a cada emissão.
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-primary" id="btnAutenticarNfse">
                        <i class="fas fa-key me-1"></i>Autenticar / Testar login
                    </button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar NFS-e</button>
                </div>
            </form>
        </div>

        <!-- TRIBUTAÇÃO POR UF -->
        <div class="tab-pane fade" id="tab-tributacao">
            <form method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="salvar-tributacao">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>UF destino</th>
                                <th class="text-end">ICMS interno %</th>
                                <th class="text-end">ICMS interest. %</th>
                                <th class="text-end">FCP %</th>
                                <th class="text-end">Simples anexo</th>
                                <th class="text-end">Simples alíq. %</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ufs as $uf): $t = $trByUf[$uf] ?? []; ?>
                            <tr>
                                <td><strong><?= $uf ?></strong><input type="hidden" name="uf[]" value="<?= $uf ?>"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="icms_aliquota[]" value="<?= number_format((float)($t['icms_aliquota'] ?? 0), 2, '.', '') ?>"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="icms_aliquota_inter[]" value="<?= number_format((float)($t['icms_aliquota_inter'] ?? 0), 2, '.', '') ?>"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="fcp_aliquota[]" value="<?= number_format((float)($t['fcp_aliquota'] ?? 0), 2, '.', '') ?>"></td>
                                <td><input type="number" min="0" max="5" class="form-control form-control-sm text-end" name="simples_anexo[]" value="<?= (int)($t['simples_anexo'] ?? 0) ?: '' ?>" placeholder="—"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="simples_aliquota[]" value="<?= number_format((float)($t['simples_aliquota'] ?? 0), 2, '.', '') ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar tributação (27 UFs)</button>
                </div>
            </form>
        </div>

        <!-- SERVIÇOS -->
        <div class="tab-pane fade" id="tab-servicos">
            <form method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="salvar-servicos">
                <?php if (!$servicos): ?>
                    <div class="alert alert-warning">Nenhum serviço ativo no catálogo.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Serviço</th>
                                <th>Cód. LC 116 *</th>
                                <th>CNAE</th>
                                <th>Código municipal</th>
                                <th class="text-end">ISS %</th>
                                <th class="text-center">ISS retido</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($servicos as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['nome']) ?></strong>
                                    <input type="hidden" name="servico_id[]" value="<?= (int)$s['id'] ?>">
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="codigo_lc116[]" value="<?= htmlspecialchars((string)($s['codigo_lc116'] ?? '')) ?>" placeholder="14.01"></td>
                                <td><input type="text" class="form-control form-control-sm" name="cnae[]" value="<?= htmlspecialchars((string)($s['cnae'] ?? '')) ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="codigo_municipio[]" value="<?= htmlspecialchars((string)($s['codigo_municipio'] ?? '')) ?>"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="iss_aliquota[]" value="<?= number_format((float)($s['iss_aliquota'] ?? 0), 2, '.', '') ?>"></td>
                                <td class="text-center">
                                    <input type="checkbox" name="iss_retido[<?= (int)$s['id'] ?>]" value="1" <?= !empty($s['iss_retido']) ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar configuração ISS</button>
                </div>
                <?php endif; ?>
            </form>
        </div>

    </div>
</div>

<script>
(function(){
    // Ativa aba pela hash
    const hash = window.location.hash;
    if (hash) {
        const trigger = document.querySelector('[data-bs-toggle="tab"][href="'+hash+'"]');
        if (trigger) new bootstrap.Tab(trigger).show();
    }
    // Toggle dos grupos por modo de autenticação NFS-e
    function atualizarModo() {
        const modo = document.getElementById('modoAuthSelect')?.value;
        document.querySelectorAll('.modo-group').forEach(el => {
            el.style.display = (el.dataset.modo === modo) ? '' : 'none';
        });
    }
    document.getElementById('modoAuthSelect')?.addEventListener('change', atualizarModo);
    atualizarModo();

    // Testar SEFAZ
    document.getElementById('btnTestarSefaz')?.addEventListener('click', function(){
        const btn = this; btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testando...';
        fetch('<?= $action ?>?action=testar-sefaz', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false; btn.innerHTML = orig;
                Swal.fire({
                    icon: data.ok ? 'success' : 'error',
                    title: data.ok ? 'SEFAZ respondeu' : 'Falha SEFAZ',
                    html: '<pre class="text-start small mb-0">' + JSON.stringify(data, null, 2) + '</pre>',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-' + (data.ok ? 'success' : 'danger') }
                });
            })
            .catch(() => { btn.disabled = false; btn.innerHTML = orig; Swal.fire('Erro','Falha de rede','error'); });
    });

    // Autenticar NFS-e
    document.getElementById('btnAutenticarNfse')?.addEventListener('click', function(){
        const btn = this; btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Autenticando...';
        const fd = new FormData();
        fd.append('action','autenticar-nfse');
        fd.append('csrf_token','<?= htmlspecialchars($csrfToken) ?>');
        fetch('<?= $action ?>', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false; btn.innerHTML = orig;
                Swal.fire({
                    icon: data.ok ? 'success' : 'error',
                    title: data.ok ? 'Autenticado' : 'Falha na autenticação',
                    html: '<pre class="text-start small mb-0">' + JSON.stringify(data, null, 2) + '</pre>',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-' + (data.ok ? 'success' : 'danger') }
                });
            })
            .catch(() => { btn.disabled = false; btn.innerHTML = orig; Swal.fire('Erro','Falha de rede','error'); });
    });
})();

function togglePassword(btn){
    const inp = btn.previousElementSibling;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.innerHTML = inp.type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}
</script>
