<?php
$caixasAbertos = is_array($caixasAbertos ?? null) ? $caixasAbertos : [];
$destino = (string) ($destino ?? tenantCleanUrl('pdv'));
$menuAtivo = (string) ($menuAtivo ?? 'abertura');
$csrfToken = (string) ($csrfToken ?? '');
unset($menuAtivo, $csrfToken);

$montarDestino = static function (string $base, int $caixaId): string {
    return $base . (str_contains($base, '?') ? '&' : '?') . 'caixa_id=' . $caixaId;
};

$formatarDataHora = static function (?string $valor): string {
    if (empty($valor)) {
        return '--';
    }

    $timestamp = strtotime($valor);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '--';
};
?>
<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-xxl-8 col-xl-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                    <div>
                        <h1 class="h4 mb-1">Selecionar caixa</h1>
                        <p class="text-muted mb-0">Existem varios caixas abertos. Escolha em qual deles o lancamento deve ser feito.</p>
                    </div>
                    <a href="<?= htmlspecialchars(tenantCleanUrl('pdv')) ?>" class="btn btn-outline-secondary">Voltar ao PDV</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($caixasAbertos === []): ?>
                        <div class="p-4 text-center text-muted">Nao ha caixas abertos disponiveis.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Caixa</th>
                                        <th>Operador</th>
                                        <th>Abertura</th>
                                        <th class="text-end">Suprimento</th>
                                        <th class="text-end">Acao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($caixasAbertos as $caixa): ?>
                                        <?php $caixaId = (int) ($caixa['id'] ?? 0); ?>
                                        <tr>
                                            <td class="fw-semibold">#<?= (int) ($caixa['numero_caixa'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars((string) ($caixa['operador_nome'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars($formatarDataHora($caixa['data_abertura'] ?? null)) ?></td>
                                            <td class="text-end">R$ <?= number_format((float) ($caixa['valor_suprimento'] ?? 0), 2, ',', '.') ?></td>
                                            <td class="text-end">
                                                <a href="<?= htmlspecialchars($montarDestino($destino, $caixaId)) ?>" class="btn btn-sm btn-primary">
                                                    Usar este caixa
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
