<?php
$relatorio = is_array($relatorio ?? null) ? $relatorio : [];
$csrfToken = (string) ($csrfToken ?? '');
$isAdmin = (bool) ($isAdmin ?? false);
$vendasDetalhadas = is_array($vendasDetalhadas ?? null) ? $vendasDetalhadas : [];
$formasAtivas = is_array($formasAtivas ?? null) ? $formasAtivas : [];
$caixa = is_array($relatorio['caixa'] ?? null) ? $relatorio['caixa'] : [];
$lancamentos = is_array($relatorio['lancamentos'] ?? null) ? $relatorio['lancamentos'] : [];
$sistema = is_array($relatorio['sistema'] ?? null) ? $relatorio['sistema'] : [];
$operador = is_array($relatorio['operador'] ?? null) ? $relatorio['operador'] : [];
$diferencas = is_array($relatorio['diferencas'] ?? null) ? $relatorio['diferencas'] : [];
$caixaId = (int) ($caixa['id'] ?? 0);
$status = strtolower((string) ($caixa['status'] ?? ''));
$formas = [
    'dinheiro' => 'Dinheiro',
    'cartao' => 'Cartao',
    'pix' => 'PIX',
    'a_faturar' => 'A faturar',
];

$formatarMoeda = static fn ($valor): string => 'R$ ' . number_format((float) $valor, 2, ',', '.');
$formatarDataHora = static function (?string $valor): string {
    if (empty($valor)) {
        return '--';
    }

    $timestamp = strtotime($valor);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '--';
};

$formatarHora = static function (?string $valor): string {
    if (empty($valor)) {
        return '--';
    }

    $timestamp = strtotime($valor);
    return $timestamp !== false ? date('H:i', $timestamp) : '--';
};

$statusClasse = match ($status) {
    'aberto' => 'success',
    'fechado' => 'secondary',
    default => 'warning',
};
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">Caixa #<?= (int) ($caixa['numero_caixa'] ?? 0) ?></h1>
                <span class="badge text-bg-<?= htmlspecialchars($statusClasse) ?>"><?= htmlspecialchars(ucfirst($status !== '' ? $status : 'indefinido')) ?></span>
                <?php $conferidoBadge = !empty($caixa['conferencia_concluida']); ?>
                <?php if ($conferidoBadge): ?>
                    <span class="badge text-bg-success"><i class="fas fa-check-circle me-1"></i>Conferido</span>
                <?php elseif ($status === 'fechado'): ?>
                    <span class="badge text-bg-warning text-dark"><i class="fas fa-clock me-1"></i>Aguardando conferência</span>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-0">Conferencia detalhada do fechamento do operador <?= htmlspecialchars((string) ($caixa['operador_nome'] ?? '')) ?>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas')) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
            <a href="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas/' . $caixaId . '/pdf')) ?>" class="btn btn-outline-primary">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
            <a href="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas/' . $caixaId . '/termica')) ?>" class="btn btn-primary">
                <i class="fas fa-print me-1"></i> Termica
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Abertura</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarDataHora($caixa['data_abertura'] ?? null)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Fechamento</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarDataHora($caixa['data_fechamento'] ?? null)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Sistema</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarMoeda($relatorio['total_sistema'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Operador</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarMoeda($relatorio['total_operador'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <?php
        // Diferença só tem sentido quando o operador já fechou às cegas.
        // Antes disso, mostramos "—" ao invés de 0, para não induzir erro.
        $jaFechouCegas = (float)($relatorio['total_operador'] ?? 0) > 0 || $status === 'fechado';
        ?>
        <div class="col-xl-2 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Diferença</div>
                    <div class="fw-semibold fs-5 <?= $jaFechouCegas ? (abs((float)($relatorio['diferenca_total'] ?? 0)) < 0.01 ? 'text-success' : 'text-danger') : 'text-muted' ?>">
                        <?= $jaFechouCegas ? htmlspecialchars($formatarMoeda($relatorio['diferenca_total'] ?? 0)) : '—' ?>
                    </div>
                    <?php if (!$jaFechouCegas): ?>
                        <div class="small text-muted mt-1">Aguardando fechamento às cegas</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <span class="fw-semibold">Conferência</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Forma</th>
                            <th class="text-end">Sistema</th>
                            <th class="text-end">Operador</th>
                            <th class="text-end">Diferença</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formas as $chave => $label): ?>
                            <?php $dif = (float)($diferencas[$chave] ?? 0); ?>
                            <tr>
                                <td><?= htmlspecialchars($label) ?></td>
                                <td class="text-end"><?= htmlspecialchars($formatarMoeda($sistema[$chave] ?? 0)) ?></td>
                                <td class="text-end"><?= htmlspecialchars($formatarMoeda($operador[$chave] ?? 0)) ?></td>
                                <td class="text-end <?= $jaFechouCegas ? (abs($dif) < 0.01 ? 'text-muted' : ($dif > 0 ? 'text-success fw-semibold' : 'text-danger fw-semibold')) : 'text-muted' ?>">
                                    <?= $jaFechouCegas ? htmlspecialchars($formatarMoeda($dif)) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>Total</th>
                            <th class="text-end"><?= htmlspecialchars($formatarMoeda($relatorio['total_sistema'] ?? 0)) ?></th>
                            <th class="text-end"><?= htmlspecialchars($formatarMoeda($relatorio['total_operador'] ?? 0)) ?></th>
                            <th class="text-end"><?= $jaFechouCegas ? htmlspecialchars($formatarMoeda($relatorio['diferenca_total'] ?? 0)) : '—' ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <span class="fw-semibold">Observacao gerencial</span>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas')) ?>">
                        <input type="hidden" name="action" value="salvar-observacao">
                        <input type="hidden" name="id" value="<?= $caixaId ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="observacao">Observacoes internas</label>
                            <textarea class="form-control" id="observacao" name="observacao" rows="6" placeholder="Registre divergencias, justificativas ou observacoes de conferencia."><?= htmlspecialchars((string) ($caixa['observacao'] ?? '')) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar observacao</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <span class="fw-semibold">Resumo do caixa</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Operador</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars((string) ($caixa['operador_nome'] ?? '')) ?></dd>

                        <dt class="col-sm-5">Numero</dt>
                        <dd class="col-sm-7">#<?= (int) ($caixa['numero_caixa'] ?? 0) ?></dd>

                        <dt class="col-sm-5">Suprimento</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($formatarMoeda($caixa['valor_suprimento'] ?? 0)) ?></dd>

                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars(ucfirst($status !== '' ? $status : 'indefinido')) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <?php $conferido = !empty($caixa['conferencia_concluida']); ?>
    <?php if ($status === 'fechado' && !$conferido): ?>
        <div class="card border-0 shadow-sm mb-4 border-success">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-success-emphasis">
                    <i class="fas fa-check-double me-1"></i> Conferência pendente
                </span>
                <span class="badge bg-warning text-dark">Aguardando conferência</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    O operador fechou o caixa às cegas. Confira os valores informados versus o sistema e
                    aprove a conferência para gerar as <strong>movimentações financeiras</strong> e
                    <strong>contas a receber</strong> automaticamente.
                </p>
                <form method="post" action="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas')) ?>"
                      class="js-confirm-form"
                      data-confirm-title="Confirmar conferência?"
                      data-confirm-text="Isto gerará os lançamentos financeiros e não pode ser desfeito."
                      data-confirm-icon="warning"
                      data-confirm-btn="Conferir">
                    <input type="hidden" name="action" value="conferir">
                    <input type="hidden" name="id" value="<?= $caixaId ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle me-1"></i> Conferir e gerar lançamentos
                    </button>
                </form>
            </div>
        </div>
    <?php elseif ($conferido): ?>
        <div class="alert alert-success d-flex align-items-center mb-4">
            <i class="fas fa-check-circle me-2"></i>
            <div>
                <strong>Caixa conferido.</strong>
                Lançamentos financeiros gerados
                <?php if (!empty($caixa['conferencia_em'])): ?>
                    em <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$caixa['conferencia_em']))) ?>.
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin && $status === 'aberto'): ?>
        <div class="card border-0 shadow-sm mb-4 border-warning">
            <div class="card-header bg-white">
                <span class="fw-semibold text-warning-emphasis">Fechamento forcado</span>
            </div>
            <div class="card-body">
                <p class="text-muted">Use apenas quando o operador nao puder concluir o fechamento pelo fluxo normal.</p>
                <form method="post" action="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas')) ?>" id="form-forcar-fechamento">
                    <input type="hidden" name="action" value="forcar-fechamento">
                    <input type="hidden" name="id" value="<?= $caixaId ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="observacao_forcada">Justificativa</label>
                        <textarea class="form-control" id="observacao_forcada" name="observacao" rows="4" placeholder="Explique por que o fechamento esta sendo forcado." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-warning">Forcar fechamento</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Vendas detalhadas do caixa (para conferência venda-a-venda) -->
    <?php if ($vendasDetalhadas !== []):
        $labelTipo = [
            'D' => 'Dinheiro', 'PIX' => 'PIX', 'CC' => 'Cartão de Crédito', 'CD' => 'Cartão de Débito',
            'BOL' => 'Boleto', 'TB' => 'Transferência', 'AF' => 'A Faturar',
        ];
        // Agrupa vendas por tipo de forma
        $vendasPorTipo = [];
        foreach ($vendasDetalhadas as $v) {
            $t = (string)($v['forma_tipo'] ?? 'SEM');
            $vendasPorTipo[$t][] = $v;
        }
        $totalGeral = array_sum(array_column($vendasDetalhadas, 'valor_total'));
        $podeEditar = !$conferido;
    ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold">Vendas do caixa</span>
                    <span class="badge bg-light text-dark ms-2"><?= count($vendasDetalhadas) ?> venda(s)</span>
                </div>
                <div class="small text-muted">
                    Total <strong class="text-success"><?= $formatarMoeda($totalGeral) ?></strong>
                    <?php if (!$podeEditar): ?>
                        <span class="badge bg-success ms-2"><i class="fas fa-lock"></i> Conferido — somente leitura</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark ms-2"><i class="fas fa-pen"></i> Editável até a conferência</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <ul class="nav nav-tabs px-3 pt-3" role="tablist">
                    <?php $idx = 0; foreach ($vendasPorTipo as $tipo => $vendasGrupo):
                        $totalGrupo = array_sum(array_column($vendasGrupo, 'valor_total'));
                        $tabId = 'tab-grp-' . htmlspecialchars($tipo);
                    ?>
                        <li class="nav-item">
                            <button class="nav-link <?= $idx === 0 ? 'active' : '' ?>"
                                    data-bs-toggle="tab" data-bs-target="#<?= $tabId ?>" type="button">
                                <?= htmlspecialchars($labelTipo[$tipo] ?? $tipo) ?>
                                <span class="badge bg-secondary ms-1"><?= count($vendasGrupo) ?></span>
                                <span class="small text-muted ms-1">(<?= $formatarMoeda($totalGrupo) ?>)</span>
                            </button>
                        </li>
                    <?php $idx++; endforeach; ?>
                </ul>

                <div class="tab-content p-0">
                    <?php $idx = 0; foreach ($vendasPorTipo as $tipo => $vendasGrupo): ?>
                        <div class="tab-pane fade <?= $idx === 0 ? 'show active' : '' ?>" id="tab-grp-<?= htmlspecialchars($tipo) ?>">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Data/Hora</th>
                                            <th>Cliente</th>
                                            <th>Resumo</th>
                                            <th class="text-end">Valor</th>
                                            <th style="min-width: 240px;">Forma de pagamento</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vendasGrupo as $v): ?>
                                            <tr>
                                                <td class="fw-bold text-primary">#<?= (int)$v['numero'] ?></td>
                                                <td class="small text-muted"><?= htmlspecialchars(date('d/m H:i', strtotime((string)$v['created_at']))) ?></td>
                                                <td><?= htmlspecialchars((string)($v['cliente_nome'] ?? 'Avulso')) ?></td>
                                                <td class="small text-muted"><?= htmlspecialchars((string)($v['resumo_itens'] ?? '')) ?></td>
                                                <td class="text-end fw-semibold"><?= $formatarMoeda($v['valor_total']) ?></td>
                                                <td>
                                                    <?php if ($podeEditar): ?>
                                                        <?php $temCliente = !empty($v['cliente_id']); ?>
                                                        <form method="post" action="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas')) ?>"
                                                              class="js-form-trocar-forma d-flex flex-wrap gap-1 align-items-center"
                                                              data-tem-cliente="<?= $temCliente ? '1' : '0' ?>">
                                                            <input type="hidden" name="action" value="trocar-forma">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                            <input type="hidden" name="venda_id" value="<?= (int)$v['id'] ?>">
                                                            <input type="hidden" name="caixa_id" value="<?= $caixaId ?>">
                                                            <select name="forma_pagamento_id" class="form-select form-select-sm js-forma-select"
                                                                    data-formas-af='<?= htmlspecialchars(json_encode(array_values(array_map(fn($fp) => (int)$fp['id'], array_filter($formasAtivas, fn($fp) => $fp['tipo'] === 'AF'))))) ?>'>
                                                                <?php foreach ($formasAtivas as $fp): ?>
                                                                    <option value="<?= (int)$fp['id'] ?>"
                                                                        data-tipo="<?= htmlspecialchars($fp['tipo']) ?>"
                                                                        <?= ((int)$fp['id'] === (int)$v['forma_pagamento_id']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($labelTipo[$fp['tipo']] ?? $fp['nome']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php if (!$temCliente): ?>
                                                                <select name="cliente_id_novo" class="form-select form-select-sm js-cliente-select" style="display:none; min-width: 180px;">
                                                                    <option value="">Selecione cliente...</option>
                                                                    <?php foreach (($clientesAtivos ?? []) as $cli): ?>
                                                                        <option value="<?= (int)$cli['id'] ?>"><?= htmlspecialchars($cli['nome']) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            <?php endif; ?>
                                                            <button type="submit" class="btn btn-sm btn-outline-primary js-confirm-trigger"
                                                                    data-confirm-title="Alterar forma de pagamento?"
                                                                    data-confirm-text="Venda #<?= (int)$v['numero'] ?>"
                                                                    data-confirm-icon="question"
                                                                    data-confirm-btn="Alterar"
                                                                    title="Salvar alteração">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <?= htmlspecialchars($labelTipo[$tipo] ?? (string)$v['forma_nome']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php $idx++; endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <span class="fw-semibold">Lancamentos do caixa</span>
        </div>
        <div class="card-body p-0">
            <?php if ($lancamentos === []): ?>
                <div class="p-4 text-center text-muted">Nenhum lancamento registrado neste caixa.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Numero</th>
                                <th>Cliente</th>
                                <th>Pagamento</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lancamentos as $lancamento): ?>
                                <?php $forma = (string) ($lancamento['forma_pagamento'] ?? ''); ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($lancamento['tipo_label'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($lancamento['numero_referencia'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($lancamento['cliente_nome'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($formas[$forma] ?? $forma) ?></td>
                                    <td class="text-end"><?= htmlspecialchars($formatarMoeda($lancamento['valor_total'] ?? 0)) ?></td>
                                    <td class="text-end"><?= htmlspecialchars($formatarHora($lancamento['created_at'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isAdmin && $status === 'aberto'): ?>
    <script>
    (function () {
        const form = document.getElementById('form-forcar-fechamento');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const continuar = function () {
                form.submit();
            };

            if (typeof window.Swal !== 'undefined') {
                window.Swal.fire({
                    title: 'Forcar fechamento?',
                    text: 'Essa acao encerra o caixa sem os valores digitados pelo operador.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, fechar',
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        continuar();
                    }
                });
                return;
            }

            if (window.confirm('Deseja realmente forcar o fechamento deste caixa?')) {
                continuar();
            }
        });
    }());
    </script>
<?php endif; ?>

<script>
(function () {
    function confirmarSwal(opts, onConfirm) {
        if (typeof Swal === 'undefined') { onConfirm(); return; }
        var icon = opts.icon || 'question';
        var confirmClass = icon === 'warning' ? 'btn btn-warning mx-1'
                          : (icon === 'error' ? 'btn btn-danger mx-1' : 'btn btn-primary mx-1');
        Swal.fire({
            title: opts.title || 'Confirmar?',
            text: opts.text || '',
            icon: icon, showCancelButton: true,
            confirmButtonText: opts.btn || 'Confirmar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: { confirmButton: confirmClass, cancelButton: 'btn btn-outline-secondary mx-1' }
        }).then(function (r) { if (r.isConfirmed) onConfirm(); });
    }

    document.querySelectorAll('.js-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            confirmarSwal({
                title: form.dataset.confirmTitle, text: form.dataset.confirmText,
                icon: form.dataset.confirmIcon, btn: form.dataset.confirmBtn
            }, function () { form.dataset.confirmed = '1'; form.submit(); });
        });
    });

    document.querySelectorAll('button.js-confirm-trigger').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (btn.dataset.confirmed === '1') return;
            // Em formulários de troca de forma: validar cliente quando AF sem cliente
            const form = btn.form;
            if (form && form.classList.contains('js-form-trocar-forma') && form.dataset.temCliente === '0') {
                const select = form.querySelector('.js-forma-select');
                const clienteSelect = form.querySelector('.js-cliente-select');
                if (select && clienteSelect) {
                    const selectedOption = select.options[select.selectedIndex];
                    const tipo = selectedOption ? selectedOption.dataset.tipo : '';
                    if (tipo === 'AF' && !clienteSelect.value) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cliente obrigatório',
                            text: 'Selecione o cliente antes de trocar para "A Faturar".',
                            buttonsStyling: false,
                            customClass: { confirmButton: 'btn btn-warning' }
                        });
                        return;
                    }
                }
            }
            if (btn.dataset.confirmed === '1') return;
            e.preventDefault();
            confirmarSwal({
                title: btn.dataset.confirmTitle, text: btn.dataset.confirmText,
                icon: btn.dataset.confirmIcon, btn: btn.dataset.confirmBtn
            }, function () {
                btn.dataset.confirmed = '1';
                if (btn.form) btn.form.submit();
            });
        });
    });

    // Mostrar/ocultar select de cliente conforme tipo da forma selecionada
    document.querySelectorAll('form.js-form-trocar-forma').forEach(function (form) {
        const select = form.querySelector('.js-forma-select');
        const clienteSelect = form.querySelector('.js-cliente-select');
        if (!select || !clienteSelect) return;
        const toggle = function () {
            const selectedOption = select.options[select.selectedIndex];
            const tipo = selectedOption ? selectedOption.dataset.tipo : '';
            clienteSelect.style.display = (tipo === 'AF') ? '' : 'none';
            if (tipo !== 'AF') clienteSelect.value = '';
        };
        select.addEventListener('change', toggle);
        toggle();
    });
}());
</script>
