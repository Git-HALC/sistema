<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$grafico = is_array($grafico ?? null) ? $grafico : [];
$caixas = is_array($caixas ?? null) ? $caixas : [];
$operadores = is_array($operadores ?? null) ? $operadores : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$csrfToken = (string) ($csrfToken ?? '');
$isAdmin = (bool) ($isAdmin ?? false);
unset($csrfToken);

$formatarMoeda = static fn ($valor): string => 'R$ ' . number_format((float) $valor, 2, ',', '.');
$formatarDataHora = static function (?string $valor): string {
    if (empty($valor)) {
        return '--';
    }

    $timestamp = strtotime($valor);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '--';
};

$statusBadge = static function (?string $status): string {
    return match (strtolower((string) $status)) {
        'aberto' => 'success',
        'fechado' => 'secondary',
        default => 'warning',
    };
};

/** Classe e label do badge de conferencia (considera status do caixa + flag). */
$conferenciaBadge = static function (array $caixa): array {
    $status = strtolower((string) ($caixa['status'] ?? ''));
    $concluida = !empty($caixa['conferencia_concluida']);
    if ($status === 'aberto') {
        return ['cls' => 'warning', 'label' => 'Caixa aberto', 'icon' => 'fa-door-open'];
    }
    if ($concluida) {
        return ['cls' => 'success', 'label' => 'Conferido', 'icon' => 'fa-circle-check'];
    }
    return ['cls' => 'danger', 'label' => 'Aguardando conferencia', 'icon' => 'fa-triangle-exclamation'];
};

/** Destaca linha de caixa fechado ainda nao conferido. */
$rowClass = static function (array $caixa): string {
    $status = strtolower((string) ($caixa['status'] ?? ''));
    $concluida = !empty($caixa['conferencia_concluida']);
    if ($status === 'fechado' && !$concluida) {
        return 'table-warning-subtle';
    }
    return '';
};

/* Highlight da linha nao conferida: amarelo suave compativel com dark mode */
?>
<style>
    .table > tbody > tr.table-warning-subtle > td {
        background-color: rgba(245, 158, 11, 0.08);
    }
    [data-bs-theme="dark"] .table > tbody > tr.table-warning-subtle > td {
        background-color: rgba(251, 191, 36, 0.10);
    }
</style>
<?php

$valorMaximo = 0.0;
foreach ($grafico as $item) {
    $valorMaximo = max($valorMaximo, (float) ($item['total_faturado'] ?? 0));
}

$temFiltros = array_filter($filtros, static fn ($valor): bool => trim((string) $valor) !== '') !== [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">Conferencia de caixas</h1>
                <?php if ($isAdmin): ?>
                    <span class="badge text-bg-primary">Admin</span>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-0">Acompanhe aberturas, fechamentos e divergencias do PDV.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv')) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar ao PDV
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Caixas hoje</div>
                    <div class="display-6 fw-semibold"><?= (int) ($dashboard['total_caixas_hoje'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Abertos agora</div>
                    <div class="display-6 fw-semibold"><?= (int) ($dashboard['caixas_abertos_agora'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Faturado hoje</div>
                    <div class="fs-3 fw-semibold"><?= htmlspecialchars($formatarMoeda($dashboard['total_faturado_hoje'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Divergencias hoje</div>
                    <div class="fs-3 fw-semibold"><?= htmlspecialchars($formatarMoeda($dashboard['total_divergencias_hoje'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <span class="fw-semibold">Filtros</span>
        </div>
        <div class="card-body">
            <form method="get" action="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas')) ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label" for="filtro_data">Data</label>
                        <input type="date" class="form-control" id="filtro_data" name="data" value="<?= htmlspecialchars((string) ($filtros['data'] ?? '')) ?>">
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label" for="filtro_operador">Operador</label>
                        <select class="form-select" id="filtro_operador" name="operador">
                            <option value="">Todos</option>
                            <?php foreach ($operadores as $operador): ?>
                                <?php $operadorId = (string) ($operador['id'] ?? ''); ?>
                                <option value="<?= htmlspecialchars($operadorId) ?>" <?= ((string) ($filtros['operador'] ?? '') === $operadorId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($operador['nome'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label" for="filtro_status">Status</label>
                        <select class="form-select" id="filtro_status" name="status">
                            <option value="">Todos</option>
                            <option value="aberto" <?= ((string) ($filtros['status'] ?? '') === 'aberto') ? 'selected' : '' ?>>Aberto</option>
                            <option value="fechado" <?= ((string) ($filtros['status'] ?? '') === 'fechado') ? 'selected' : '' ?>>Fechado</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label" for="filtro_diferenca">Divergencia</label>
                        <select class="form-select" id="filtro_diferenca" name="diferenca">
                            <option value="">Todos</option>
                            <option value="ok" <?= ((string) ($filtros['diferenca'] ?? '') === 'ok') ? 'selected' : '' ?>>Sem diferenca</option>
                            <option value="divergente" <?= ((string) ($filtros['diferenca'] ?? '') === 'divergente') ? 'selected' : '' ?>>Com diferenca</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label" for="filtro_conferencia">Conferencia</label>
                        <select class="form-select" id="filtro_conferencia" name="conferencia">
                            <option value="">Todas</option>
                            <option value="pendente" <?= ((string) ($filtros['conferencia'] ?? '') === 'pendente') ? 'selected' : '' ?>>Aguardando</option>
                            <option value="concluida" <?= ((string) ($filtros['conferencia'] ?? '') === 'concluida') ? 'selected' : '' ?>>Concluida</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                            <?php if ($temFiltros): ?>
                                <a href="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas')) ?>" class="btn btn-outline-secondary">Limpar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php
    $totalGraficoHoje = 0.0;
    foreach ($grafico as $it) $totalGraficoHoje += (float)($it['total_faturado'] ?? 0);
    ?>
    <?php if ($totalGraficoHoje > 0): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-2">
            <span class="fw-semibold"><i class="fas fa-chart-bar me-1 text-muted"></i> Faturamento por caixa hoje</span>
        </div>
        <div class="card-body">
            <div class="d-flex flex-column gap-3">
                <?php foreach ($grafico as $item): ?>
                    <?php
                    $valor = (float) ($item['total_faturado'] ?? 0);
                    if ($valor <= 0) continue;
                    $percentual = $valorMaximo > 0 ? (int) round(($valor / $valorMaximo) * 100) : 0;
                    ?>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="small">
                                <strong>Caixa #<?= (int) ($item['numero_caixa'] ?? 0) ?></strong>
                                <span class="text-muted">· <?= htmlspecialchars((string) ($item['operador_nome'] ?? '')) ?></span>
                            </div>
                            <div class="fw-semibold text-success"><?= htmlspecialchars($formatarMoeda($valor)) ?></div>
                        </div>
                        <div class="progress" role="progressbar" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= $percentual ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <span class="fw-semibold">Caixas encontrados</span>
        </div>
        <div class="card-body p-0">
            <?php if ($caixas === []): ?>
                <div class="p-4 text-center text-muted">Nenhum caixa localizado com os filtros informados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Caixa</th>
                                <th>Operador</th>
                                <th>Abertura</th>
                                <th>Fechamento</th>
                                <th>Status</th>
                                <th>Conferencia</th>
                                <th class="text-end">Lancado</th>
                                <th class="text-end">Digitado</th>
                                <th class="text-end">Diferenca</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($caixas as $caixa): ?>
                                <?php
                                    $caixaId = (int) ($caixa['id'] ?? 0);
                                    $conf = $conferenciaBadge($caixa);
                                    $rowCls = $rowClass($caixa);
                                    $diferenca = (float) ($caixa['diferenca_total'] ?? 0);
                                ?>
                                <tr class="<?= htmlspecialchars($rowCls) ?>">
                                    <td class="fw-semibold">#<?= (int) ($caixa['numero_caixa'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($caixa['operador_nome'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($formatarDataHora($caixa['data_abertura'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars($formatarDataHora($caixa['data_fechamento'] ?? null)) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= htmlspecialchars($statusBadge($caixa['status'] ?? '')) ?>">
                                            <?= htmlspecialchars(ucfirst((string) ($caixa['status'] ?? ''))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?= htmlspecialchars($conf['cls']) ?> d-inline-flex align-items-center gap-1">
                                            <i class="fas <?= htmlspecialchars($conf['icon']) ?>"></i>
                                            <?= htmlspecialchars($conf['label']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= htmlspecialchars($formatarMoeda($caixa['total_lancado'] ?? 0)) ?></td>
                                    <td class="text-end"><?= htmlspecialchars($formatarMoeda($caixa['total_digitado'] ?? 0)) ?></td>
                                    <td class="text-end <?= abs($diferenca) > 0.009 ? 'text-danger fw-semibold' : '' ?>">
                                        <?= htmlspecialchars($formatarMoeda($diferenca)) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas/' . $caixaId)) ?>" class="btn btn-outline-primary">
                                                <?php if (strtolower((string) ($caixa['status'] ?? '')) === 'fechado' && empty($caixa['conferencia_concluida'])): ?>
                                                    <i class="fas fa-gavel me-1"></i>Conferir
                                                <?php else: ?>
                                                    Detalhe
                                                <?php endif; ?>
                                            </a>
                                            <a href="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas/' . $caixaId . '/pdf')) ?>" class="btn btn-outline-secondary">PDF</a>
                                            <a href="<?= htmlspecialchars(tenantCleanUrl('gerencial/caixas/' . $caixaId . '/termica')) ?>" class="btn btn-outline-secondary">Termica</a>
                                        </div>
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
