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

    <!-- Cabe?alho -->
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
    // Garante que $produto seja um objeto Produto (novo ou reconstitu?do)
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
             SE??O 1 ? Dados Gerais
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
                        <div class="form-text">Opcional. Deve ser ?nico.</div>
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
                        <label class="form-label">Estoque M?nimo</label>
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
             SE??O 2 ? Dados Fiscais (collapsible, opcional)
        ================================================ -->
        <div class="card shadow mb-4">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold">
                    <i class="fas fa-file-invoice me-2 text-info"></i>Dados Fiscais
                    <small class="text-muted fw-normal ms-2">? Opcional (NF-e ready)</small>
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

                    <p class="text-muted small fw-bold mb-2">CLASSIFICA??O FISCAL</p>
                    <div class="row g-3 mb-4">

                        <div class="col-md-2">
                            <label class="form-label">NCM <span class="text-danger">*</span></label>
                            <input type="text" name="ncm" class="form-control" maxlength="8"
                                   value="<?php echo htmlspecialchars($f?->ncm ?? ''); ?>"
                                   placeholder="00000000"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,8)">
                            <div class="form-text">8 d?gitos</div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">CFOP <span class="text-danger">*</span></label>
                            <input type="text" name="cfop" class="form-control" maxlength="4"
                                   value="<?php echo htmlspecialchars($f?->cfop ?? ''); ?>"
                                   placeholder="5102"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                            <div class="form-text">4 d?gitos</div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">CEST <small class="text-muted">(ST)</small></label>
                            <input type="text" name="cest" class="form-control" maxlength="7"
                                   value="<?php echo htmlspecialchars($f?->cest ?? ''); ?>"
                                   placeholder="0000000"
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,7)">
                            <div class="form-text">7 d?gitos</div>
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
                                <option value="">? Não aplic?vel ?</option>
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
                                <option value="">? Selecione ?</option>
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
                                <option value="">? Selecione ?</option>
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
                                <option value="">? Selecione ?</option>
                                <?php foreach (ProdutoFiscal::CST_PIS_COFINS as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>"
                                    <?php echo ($f?->cst_cofins ?? '') === (string)$val ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Al?quotas -->
                    <p class="text-muted small fw-bold mb-2">AL?QUOTAS (%)</p>
                    <div class="row g-3">
                        <?php
                        $camposAliq = [
                            'aliquota_icms'    => ['ICMS',        $f?->aliquota_icms    ?? 0],
                            'aliquota_icms_st' => ['ICMS ST',     $f?->aliquota_icms_st ?? 0],
                            'aliquota_ipi'     => ['IPI',         $f?->aliquota_ipi     ?? 0],
                            'aliquota_pis'     => ['PIS',         $f?->aliquota_pis     ?? 0],
                            'aliquota_cofins'  => ['COFINS',      $f?->aliquota_cofins  ?? 0],
                            'reducao_bc_icms'  => ['Redu??o BC',  $f?->reducao_bc_icms  ?? 0],
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
                            <label class="form-label">C?digo Benef?cio Fiscal</label>
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

        <!-- Bot?es -->
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

// -- Toggle manual da se??o fiscal --------------------------------------
// Não usa data-bs-toggle para evitar que handlers globais (menu.js,
// Bootstrap data-api) fechem o painel ao clicar em qualquer input.
(function () {
    var btn    = document.getElementById('btnToggleFiscal');
    var target = document.getElementById('secaoFiscal');
    var icon   = document.getElementById('iconToggleFiscal');
    if (!btn || !target) return;

    // Cria inst?ncia do Collapse sem auto-toggle (respeita classe `show` j? presente)
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
</script>
