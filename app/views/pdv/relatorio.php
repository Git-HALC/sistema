<?php
$relatorio = is_array($relatorio ?? null) ? $relatorio : [];
$impressaoAutomatica = (bool) ($impressaoAutomatica ?? false);
$caixa = is_array($relatorio['caixa'] ?? null) ? $relatorio['caixa'] : [];
$lancamentos = is_array($relatorio['lancamentos'] ?? null) ? $relatorio['lancamentos'] : [];
$sistema = is_array($relatorio['sistema'] ?? null) ? $relatorio['sistema'] : [];
$operador = is_array($relatorio['operador'] ?? null) ? $relatorio['operador'] : [];
$diferencas = is_array($relatorio['diferencas'] ?? null) ? $relatorio['diferencas'] : [];
$formas = [
    'dinheiro' => 'Dinheiro',
    'cartao' => 'Cartao',
    'pix' => 'PIX',
    'a_faturar' => 'A faturar',
];
$caixaId = (int) ($caixa['id'] ?? 0);

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

$formatarMoeda = static fn ($valor): string => 'R$ ' . number_format((float) $valor, 2, ',', '.');
?>
<style>
@media print {
    .no-print {
        display: none !important;
    }

    .card,
    .table-responsive {
        box-shadow: none !important;
    }

    body {
        background: #fff !important;
    }
}
</style>

<div class="container-fluid py-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <h1 class="h3 mb-1">Relatorio de fechamento</h1>
            <p class="text-muted mb-0">Caixa #<?= (int) ($caixa['numero_caixa'] ?? 0) ?> finalizado por <?= htmlspecialchars((string) ($caixa['operador_nome'] ?? '')) ?>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . $caixaId . '/pdf')) ?>" class="btn btn-outline-primary">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . $caixaId . '/termica')) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-print me-1"></i> Termica
            </a>
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . $caixaId)) ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Voltar ao caixa
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Operador</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars((string) ($caixa['operador_nome'] ?? 'Nao informado')) ?></div>
                </div>
            </div>
        </div>
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
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Diferenca total</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarMoeda($relatorio['diferenca_total'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
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

    <?php if (trim((string) ($caixa['observacao'] ?? '')) !== ''): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <span class="fw-semibold">Observacao</span>
            </div>
            <div class="card-body">
                <p class="mb-0"><?= nl2br(htmlspecialchars((string) $caixa['observacao'])) ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($impressaoAutomatica): ?>
    <script>
    window.addEventListener('load', function () {
        window.print();
    });
    </script>
<?php endif; ?>
