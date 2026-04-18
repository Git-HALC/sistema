<?php
$relatorio = is_array($relatorio ?? null) ? $relatorio : [];
$csrfToken = (string) ($csrfToken ?? '');
$isAdmin = (bool) ($isAdmin ?? false);
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
                <?php if ($isAdmin): ?>
                    <span class="badge text-bg-primary">Admin</span>
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
        <div class="col-xl-2 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Diferenca</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarMoeda($relatorio['diferenca_total'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <span class="fw-semibold">Conferencia</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Forma</th>
                            <th class="text-end">Sistema</th>
                            <th class="text-end">Operador</th>
                            <th class="text-end">Diferenca</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formas as $chave => $label): ?>
                            <tr>
                                <td><?= htmlspecialchars($label) ?></td>
                                <td class="text-end"><?= htmlspecialchars($formatarMoeda($sistema[$chave] ?? 0)) ?></td>
                                <td class="text-end"><?= htmlspecialchars($formatarMoeda($operador[$chave] ?? 0)) ?></td>
                                <td class="text-end"><?= htmlspecialchars($formatarMoeda($diferencas[$chave] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>Total</th>
                            <th class="text-end"><?= htmlspecialchars($formatarMoeda($relatorio['total_sistema'] ?? 0)) ?></th>
                            <th class="text-end"><?= htmlspecialchars($formatarMoeda($relatorio['total_operador'] ?? 0)) ?></th>
                            <th class="text-end"><?= htmlspecialchars($formatarMoeda($relatorio['diferenca_total'] ?? 0)) ?></th>
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
