<?php
use App\Modules\Produtos\Produto;
use App\Modules\Produtos\ProdutoFiscal;

$produtoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_produto_base_url', 'admin/produtos.php')
    : tenantUrl('admin/produtos.php');
$salvarProdutoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($produtoBaseUrl, 'action=salvar')
    : $produtoBaseUrl . '?action=salvar';
?>
<div class="container-fluid">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo htmlspecialchars($produtoBaseUrl); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <?php
    // Garante que $produto seja um objeto Produto (novo ou reconstituído)
    $p = ($produto instanceof Produto) ? $produto : new Produto();

    // $fiscal pode ser ProdutoFiscal ou null (produto sem dados fiscais)
    $f        = ($fiscal instanceof ProdutoFiscal) ? $fiscal : null;
    $temFiscal = $f !== null;
    ?>

    <form method="POST" action="<?php echo htmlspecialchars($salvarProdutoUrl); ?>"
          id="formProduto" novalidate>

        <?php if ($p->id > 0): ?>
            <input type="hidden" name="id" value="<?php echo $p->id; ?>">
        <?php endif; ?>

        <!-- ================================================
             SEÇÃO — Classificação (Grupo / Subgrupo)
        ================================================ -->
        <div class="card shadow mb-3">
            <div class="card-header py-2 fw-bold">
                <i class="fas fa-folder-tree me-2 text-warning"></i>Classificação
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Grupo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="grupo_id" id="grupo_id" class="form-select" required>
                                <option value="">— Selecione —</option>
                                <?php foreach (($grupos ?? []) as $g): ?>
                                    <option value="<?= (int)$g['id'] ?>"
                                        <?= ((int)($p->grupo_id ?? 0) === (int)$g['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$g['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" id="btnNovoGrupo"
                                    title="Criar novo grupo">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Selecione um grupo.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subgrupo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="subgrupo_id" id="subgrupo_id" class="form-select" required>
                                <option value="">
                                    <?= ((int)($p->grupo_id ?? 0) > 0) ? '— Selecione —' : 'Selecione um grupo primeiro' ?>
                                </option>
                                <?php foreach (($subgruposInit ?? []) as $sg): ?>
                                    <option value="<?= (int)$sg['id'] ?>"
                                        <?= ((int)($p->subgrupo_id ?? 0) === (int)$sg['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$sg['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" id="btnNovoSubgrupo"
                                    title="Criar novo subgrupo (exige grupo selecionado)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Selecione um subgrupo.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Novo Grupo -->
        <div class="modal fade" id="modalNovoGrupo" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>Novo Grupo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="grupoNovoNome" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Descrição</label>
                            <textarea id="grupoNovoDesc" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnSalvarGrupo">
                            <i class="fas fa-save me-1"></i> Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Novo Subgrupo -->
        <div class="modal fade" id="modalNovoSubgrupo" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-sitemap me-2"></i>Novo Subgrupo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Grupo pai</label>
                            <input type="text" id="subgrupoNovoGrupoNome" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="subgrupoNovoNome" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Descrição</label>
                            <textarea id="subgrupoNovoDesc" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnSalvarSubgrupo">
                            <i class="fas fa-save me-1"></i> Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================
             SEÇÃO 1 — Dados Gerais
        ================================================ -->
        <div class="card shadow mb-3">
            <div class="card-header py-2 fw-bold">
                <i class="fas fa-box me-2 text-primary"></i>Dados Gerais
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label">Código (SKU)</label>
                        <input type="text" name="codigo" class="form-control"
                               value="<?php echo htmlspecialchars($p->codigo ?? ''); ?>"
                               placeholder="Ex: PROD-001" maxlength="50">
                        <div class="form-text">Opcional. Deve ser único.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control" required
                               value="<?php echo htmlspecialchars($p->nome); ?>"
                               placeholder="Nome do produto" maxlength="255">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Unidade <span class="text-danger">*</span></label>
                        <select name="unidade" class="form-select" required>
                            <?php foreach (Produto::UNIDADES as $u): ?>
                            <option value="<?php echo $u; ?>"
                                <?php echo $p->unidade === $u ? 'selected' : ''; ?>>
                                <?php echo $u; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="2"
                                  placeholder="Descrição detalhada (opcional)"><?php echo htmlspecialchars($p->descricao ?? ''); ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Preço de Custo (R$)</label>
                        <input type="number" name="preco_custo" class="form-control"
                               min="0" step="0.0001"
                               value="<?php echo number_format($p->preco_custo, 4, '.', ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Preço de Venda (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="preco_venda" class="form-control"
                               min="0" step="0.0001" required
                               value="<?php echo number_format($p->preco_venda, 4, '.', ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Estoque Atual</label>
                        <input type="number" name="estoque_atual" class="form-control"
                               min="0" step="0.0001"
                               value="<?php echo number_format($p->estoque_atual, 4, '.', ''); ?>"
                               <?php echo !empty($editando) ? 'readonly' : ''; ?>>
                        <div class="form-text">
                            <?php echo !empty($editando)
                                ? 'Use o Inventário de Estoque para alterar o saldo atual.'
                                : 'Informe aqui apenas o saldo inicial do novo produto.'; ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Estoque Mínimo</label>
                        <input type="number" name="estoque_minimo" class="form-control"
                               min="0" step="0.0001"
                               value="<?php echo number_format($p->estoque_minimo, 4, '.', ''); ?>">
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ativo" id="chkAtivo"
                                   <?php echo $p->ativo ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="chkAtivo">Produto ativo</label>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ================================================
             SEÇÃO 2 — Dados Fiscais (collapsible, opcional)
        ================================================ -->
        <div class="card shadow mb-4">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold">
                    <i class="fas fa-file-invoice me-2 text-info"></i>Dados Fiscais
                    <small class="text-muted fw-normal ms-2">· Opcional (NF-e ready)</small>
                </span>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        id="btnToggleFiscal"
                        aria-expanded="<?php echo $temFiscal ? 'true' : 'false'; ?>"
                        aria-controls="secaoFiscal">
                    <i class="fas fa-chevron-down" id="iconToggleFiscal"
                       style="transition: transform .2s; <?php echo $temFiscal ? '' : 'transform:rotate(-90deg)'; ?>"></i>
                </button>
            </div>

            <div class="collapse <?php echo $temFiscal ? 'show' : ''; ?>" id="secaoFiscal">
                <div class="card-body">

                    <p class="text-muted small fw-bold mb-2">CLASSIFICAÇÃO FISCAL</p>
                    <div class="row g-3 mb-4">

                        <div class="col-md-2">
                            <label class="form-label">NCM <span class="text-danger">*</span></label>
                            <input type="text" name="ncm" class="form-control" maxlength="8"
                                   value="<?php echo htmlspecialchars($f?->ncm ?? ''); ?>"
                                   placeholder="00000000"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,8)">
                            <div class="form-text">8 dígitos</div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">CFOP <span class="text-danger">*</span></label>
                            <input type="text" name="cfop" class="form-control" maxlength="4"
                                   value="<?php echo htmlspecialchars($f?->cfop ?? ''); ?>"
                                   placeholder="5102"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                            <div class="form-text">4 dígitos</div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">CEST <small class="text-muted">(ST)</small></label>
                            <input type="text" name="cest" class="form-control" maxlength="7"
                                   value="<?php echo htmlspecialchars($f?->cest ?? ''); ?>"
                                   placeholder="0000000"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,7)">
                            <div class="form-text">7 dígitos</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Origem</label>
                            <select name="origem" class="form-select">
                                <?php foreach (ProdutoFiscal::ORIGENS as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>"
                                    <?php echo ($f?->origem ?? '0') === (string)$val ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- CSOSN / CST unificado -->
                    <p class="text-muted small fw-bold mb-2">REGIME TRIBUTÁRIO ICMS</p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">
                                CSOSN <small class="text-muted">(Simples)</small>
                                / CST <small class="text-muted">(Lucro Real/Presumido)</small>
                            </label>
                            <select name="csosn_cst" class="form-select">
                                <option value="">— Não aplicável —</option>
                                <?php foreach (ProdutoFiscal::CSOSN_CST as $grupo => $opcoes): ?>
                                <optgroup label="<?php echo $grupo; ?>">
                                    <?php foreach ($opcoes as $val => $lbl): ?>
                                    <option value="<?php echo $val; ?>"
                                        <?php echo ($f?->csosn_cst ?? '') === (string)$val ? 'selected' : ''; ?>>
                                        <?php echo $lbl; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Modalidade BC ICMS <span class="text-danger">*</span></label>
                            <select name="modalidade_bc_icms" class="form-select">
                                <option value="">— Selecione —</option>
                                <?php foreach (ProdutoFiscal::MODALIDADES_BC_ICMS as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>"
                                    <?php echo ($f?->modalidade_bc_icms ?? '') === (string)$val ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Indicador de Escala</label>
                            <select name="ind_escala" class="form-select">
                                <?php foreach (ProdutoFiscal::IND_ESCALA as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>"
                                    <?php echo (($f?->ind_escala ?? 'S') === (string)$val) ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <p class="text-muted small fw-bold mb-2">PIS / COFINS</p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">CST PIS <span class="text-danger">*</span></label>
                            <select name="cst_pis" class="form-select">
                                <option value="">— Selecione —</option>
                                <?php foreach (ProdutoFiscal::CST_PIS_COFINS as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>"
                                    <?php echo ($f?->cst_pis ?? '') === (string)$val ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">CST COFINS <span class="text-danger">*</span></label>
                            <select name="cst_cofins" class="form-select">
                                <option value="">— Selecione —</option>
                                <?php foreach (ProdutoFiscal::CST_PIS_COFINS as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>"
                                    <?php echo ($f?->cst_cofins ?? '') === (string)$val ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Alíquotas -->
                    <p class="text-muted small fw-bold mb-2">ALÍQUOTAS (%)</p>
                    <div class="row g-3">
                        <?php
                        $camposAliq = [
                            'aliquota_icms'    => ['ICMS',        $f?->aliquota_icms    ?? 0],
                            'aliquota_icms_st' => ['ICMS ST',     $f?->aliquota_icms_st ?? 0],
                            'aliquota_ipi'     => ['IPI',         $f?->aliquota_ipi     ?? 0],
                            'aliquota_pis'     => ['PIS',         $f?->aliquota_pis     ?? 0],
                            'aliquota_cofins'  => ['COFINS',      $f?->aliquota_cofins  ?? 0],
                            'reducao_bc_icms'  => ['Redução BC',  $f?->reducao_bc_icms  ?? 0],
                        ];
                        foreach ($camposAliq as $campo => [$rotulo, $valor]):
                        ?>
                        <div class="col-md-2">
                            <label class="form-label"><?php echo $rotulo; ?> (%)</label>
                            <input type="number" name="<?php echo $campo; ?>"
                                   class="form-control" min="0" max="100" step="0.0001"
                                   value="<?php echo number_format((float)$valor, 4, '.', ''); ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="text-muted small fw-bold mt-4 mb-2">COMPLEMENTOS FISCAIS</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Código Benefício Fiscal</label>
                            <input type="text" name="codigo_beneficio_fiscal" class="form-control" maxlength="10"
                                   value="<?php echo htmlspecialchars($f?->codigo_beneficio_fiscal ?? ''); ?>"
                                   placeholder="Ex: SP123456">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">CNPJ do Fabricante</label>
                            <input type="text" name="cnpj_fabricante" class="form-control" maxlength="14"
                                   value="<?php echo htmlspecialchars($f?->cnpj_fabricante ?? ''); ?>"
                                   placeholder="Somente números"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,14)">
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 py-2 small">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>NCM, CFOP, CST PIS, CST COFINS e Modalidade BC ICMS</strong> são obrigátorios ao preencher dados fiscais.
                        Deixe <strong>NCM em branco</strong> para salvar sem informações fiscais.
                    </div>

                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary" id="btnSalvar">
                <i class="fas fa-save me-1"></i>
                <?php echo $editando ? 'Salvar Altera??es' : 'Cadastrar Produto'; ?>
            </button>
            <a href="<?php echo htmlspecialchars($produtoBaseUrl); ?>" class="btn btn-outline-secondary">
                Cancelar
            </a>
        </div>

    </form>
</div>

<script>
document.getElementById('formProduto').addEventListener('submit', function () {
    document.getElementById('btnSalvar').disabled = true;
});

// -- Toggle manual da seção fiscal --------------------------------------
// Não usa data-bs-toggle para evitar que handlers globais (menu.js,
// Bootstrap data-api) fechem o painel ao clicar em qualquer input.
(function () {
    var btn    = document.getElementById('btnToggleFiscal');
    var target = document.getElementById('secaoFiscal');
    var icon   = document.getElementById('iconToggleFiscal');
    if (!btn || !target) return;

    // Cria instância do Collapse sem auto-toggle (respeita classe `show` já presente)
    var bsCollapse = new bootstrap.Collapse(target, { toggle: false });

    btn.addEventListener('click', function (e) {
        // Impede que o clique suba ao document e acione handlers do menu.js
        e.stopPropagation();
        bsCollapse.toggle();
    });

    // Atualiza ?cone e aria-expanded conforme anima??o do Bootstrap
    target.addEventListener('show.bs.collapse', function () {
        btn.setAttribute('aria-expanded', 'true');
        icon.style.transform = 'rotate(0deg)';
    });
    target.addEventListener('hide.bs.collapse', function () {
        btn.setAttribute('aria-expanded', 'false');
        icon.style.transform = 'rotate(-90deg)';
    });
}());

// -- Classificação: AJAX subgrupos + criação inline de grupo/subgrupo ----
(function () {
    var grupoSel = document.getElementById('grupo_id');
    var sgSel    = document.getElementById('subgrupo_id');
    if (!grupoSel || !sgSel) return;

    var CSRF         = <?= json_encode($csrfToken ?? '', JSON_UNESCAPED_SLASHES) ?>;
    var URL_LIST_SG  = <?= json_encode(tenantUrl('admin/ajax/produto-subgrupos.php'), JSON_UNESCAPED_SLASHES) ?>;
    var URL_NEW_GRP  = <?= json_encode(tenantUrl('admin/ajax/criar-produto-grupo.php'), JSON_UNESCAPED_SLASHES) ?>;
    var URL_NEW_SG   = <?= json_encode(tenantUrl('admin/ajax/criar-produto-subgrupo.php'), JSON_UNESCAPED_SLASHES) ?>;

    var selectedSg = sgSel.value;

    function alerta(texto, icon) {
        // Mesmo estilo usado pelo footer.php/flashMessage
        var titulo = icon === 'success' ? 'Sucesso' : (icon === 'error' ? 'Atenção' : 'Aviso');
        var confirmClass = icon === 'error' ? 'btn btn-danger mx-1'
                         : (icon === 'success' ? 'btn btn-success mx-1' : 'btn btn-warning mx-1');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: titulo,
                html: texto,
                icon: icon,
                confirmButtonText: 'OK',
                buttonsStyling: false,
                customClass: { confirmButton: confirmClass },
                timer: icon === 'success' ? 1800 : undefined,
                showConfirmButton: icon !== 'success'
            });
        } else {
            alert(titulo + ': ' + texto);
        }
    }

    // Carrega subgrupos ao trocar grupo
    grupoSel.addEventListener('change', function () { carregarSubgrupos(this.value); });

    function carregarSubgrupos(gid, selecionarId) {
        sgSel.innerHTML = '<option value="">Carregando...</option>';
        if (!gid) {
            sgSel.innerHTML = '<option value="">Selecione um grupo primeiro</option>';
            return;
        }
        fetch(URL_LIST_SG + '?grupo_id=' + encodeURIComponent(gid), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                sgSel.innerHTML = '<option value="">Falha ao carregar</option>';
                return;
            }
            var alvo = selecionarId || selectedSg;
            var html = '<option value="">— Selecione —</option>';
            (data.itens || []).forEach(function (sg) {
                var sel = (String(sg.id) === String(alvo)) ? ' selected' : '';
                html += '<option value="' + sg.id + '"' + sel + '>' +
                        sg.nome.replace(/</g, '&lt;') + '</option>';
            });
            sgSel.innerHTML = html;
            selectedSg = '';
        })
        .catch(function () {
            sgSel.innerHTML = '<option value="">Erro de rede</option>';
        });
    }

    // Botão "+" Novo Grupo
    var modalGrupoEl = document.getElementById('modalNovoGrupo');
    var modalGrupo = modalGrupoEl ? new bootstrap.Modal(modalGrupoEl) : null;
    document.getElementById('btnNovoGrupo').addEventListener('click', function () {
        document.getElementById('grupoNovoNome').value = '';
        document.getElementById('grupoNovoDesc').value = '';
        modalGrupo.show();
        setTimeout(function () { document.getElementById('grupoNovoNome').focus(); }, 200);
    });

    document.getElementById('btnSalvarGrupo').addEventListener('click', function () {
        var btn = this;
        var nome = document.getElementById('grupoNovoNome').value.trim();
        var desc = document.getElementById('grupoNovoDesc').value.trim();
        if (!nome) { alerta('Informe o nome do grupo.', 'warning'); return; }

        btn.disabled = true;
        var fd = new FormData();
        fd.append('nome', nome);
        fd.append('descricao', desc);
        fd.append('csrf_token', CSRF);

        fetch(URL_NEW_GRP, {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': CSRF },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json().then(function (d) { return { status: r.status, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            if (!res.data.ok) {
                alerta(res.data.erro || 'Não foi possível criar o grupo.', 'error');
                return;
            }
            // Inserir novo grupo no select e selecionar
            var opt = document.createElement('option');
            opt.value = res.data.id;
            opt.textContent = res.data.nome;
            opt.selected = true;
            grupoSel.appendChild(opt);
            grupoSel.value = String(res.data.id);
            // Dispara change para carregar subgrupos (vazio)
            sgSel.innerHTML = '<option value="">— Selecione —</option>';
            modalGrupo.hide();
            alerta('Grupo "' + res.data.nome + '" criado.', 'success');
        })
        .catch(function () {
            btn.disabled = false;
            alerta('Erro de rede ao salvar o grupo.', 'error');
        });
    });

    // Botão "+" Novo Subgrupo
    var modalSgEl = document.getElementById('modalNovoSubgrupo');
    var modalSg = modalSgEl ? new bootstrap.Modal(modalSgEl) : null;
    document.getElementById('btnNovoSubgrupo').addEventListener('click', function () {
        if (!grupoSel.value) {
            alerta('Selecione o grupo pai antes de criar um subgrupo.', 'warning');
            return;
        }
        document.getElementById('subgrupoNovoGrupoNome').value = grupoSel.options[grupoSel.selectedIndex].text;
        document.getElementById('subgrupoNovoNome').value = '';
        document.getElementById('subgrupoNovoDesc').value = '';
        modalSg.show();
        setTimeout(function () { document.getElementById('subgrupoNovoNome').focus(); }, 200);
    });

    document.getElementById('btnSalvarSubgrupo').addEventListener('click', function () {
        var btn = this;
        var nome = document.getElementById('subgrupoNovoNome').value.trim();
        var desc = document.getElementById('subgrupoNovoDesc').value.trim();
        var gid  = grupoSel.value;
        if (!gid)  { alerta('Grupo pai não definido. Feche e tente novamente.', 'warning'); return; }
        if (!nome) { alerta('Informe o nome do subgrupo.', 'warning'); return; }

        btn.disabled = true;
        var fd = new FormData();
        fd.append('grupo_id', gid);
        fd.append('nome', nome);
        fd.append('descricao', desc);
        fd.append('csrf_token', CSRF);

        fetch(URL_NEW_SG, {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': CSRF },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json().then(function (d) { return { status: r.status, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            if (!res.data.ok) {
                alerta(res.data.erro || 'Não foi possível criar o subgrupo.', 'error');
                return;
            }
            // Recarrega subgrupos e seleciona o novo
            carregarSubgrupos(gid, res.data.id);
            modalSg.hide();
            alerta('Subgrupo "' + res.data.nome + '" criado.', 'success');
        })
        .catch(function () {
            btn.disabled = false;
            alerta('Erro de rede ao salvar o subgrupo.', 'error');
        });
    });
}());
</script>
