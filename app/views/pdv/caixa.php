<?php
$caixa         = is_array($caixa ?? null) ? $caixa : [];
$caixasAbertos = is_array($caixasAbertos ?? null) ? $caixasAbertos : [];
$podeFechar    = (bool) ($podeFechar ?? false);
$caixaId       = (int) ($caixa['id'] ?? 0);
$outrosCaixas  = array_values(array_filter(
    $caixasAbertos,
    static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $caixaId
));

$brl = static fn ($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
$fmtDH = static function (?string $v): string {
    if (!$v) return '--';
    $t = strtotime($v);
    return $t ? date('d/m/Y H:i', $t) : '--';
};

$status = strtolower((string) ($caixa['status'] ?? ''));
$statusLabel = match ($status) {
    'aberto'  => 'Aberto',
    'fechado' => 'Fechado',
    default   => 'Indefinido',
};
$statusCor = match ($status) {
    'aberto'  => 'success',
    'fechado' => 'secondary',
    default   => 'warning',
};

// KPIs ao vivo — vendas e totais do caixa
$db = $GLOBALS['db'] ?? null;
$kpis = [
    'qtd_vendas'   => 0,
    'total_faturado' => 0.0,
    'ticket_medio' => 0.0,
    'qtd_fluxo'    => 0,
    'total_fluxo'  => 0.0,
    'qtd_canceladas' => 0,
];
if ($db instanceof PDO && $caixaId > 0) {
    $stmt = $db->prepare(
        "SELECT
            COUNT(*) FILTER (WHERE status='faturado') AS qtd_fat,
            COALESCE(SUM(valor_total) FILTER (WHERE status='faturado'), 0) AS total_fat,
            COUNT(*) FILTER (WHERE status IN ('pendente','em_processo','concluido')) AS qtd_flx,
            COALESCE(SUM(valor_total) FILTER (WHERE status IN ('pendente','em_processo','concluido')), 0) AS total_flx,
            COUNT(*) FILTER (WHERE status='cancelado') AS qtd_can
           FROM pdv_vendas WHERE caixa_id = :id"
    );
    $stmt->execute([':id' => $caixaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $kpis['qtd_vendas']      = (int)($row['qtd_fat'] ?? 0);
    $kpis['total_faturado']  = (float)($row['total_fat'] ?? 0);
    $kpis['ticket_medio']    = $kpis['qtd_vendas'] > 0 ? $kpis['total_faturado'] / $kpis['qtd_vendas'] : 0.0;
    $kpis['qtd_fluxo']       = (int)($row['qtd_flx'] ?? 0);
    $kpis['total_fluxo']     = (float)($row['total_flx'] ?? 0);
    $kpis['qtd_canceladas']  = (int)($row['qtd_can'] ?? 0);
}
?>
<div class="container-fluid py-3">

    <!-- Header compacto: identificação + status -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary"
                 style="width:56px; height:56px;">
                <i class="fas fa-cash-register fa-lg"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h3 mb-0">Caixa #<?= (int)($caixa['numero_caixa'] ?? 0) ?></h1>
                    <span class="badge text-bg-<?= $statusCor ?> fs-6"><?= htmlspecialchars($statusLabel) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    <i class="fas fa-user me-1"></i><?= htmlspecialchars((string)($caixa['operador_nome'] ?? 'Operador')) ?>
                    · <i class="fas fa-clock me-1"></i>Aberto em <?= htmlspecialchars($fmtDH($caixa['data_abertura'] ?? null)) ?>
                    <?php if (($caixa['data_fechamento'] ?? null)): ?>
                        · <i class="fas fa-lock me-1"></i>Fechado em <?= htmlspecialchars($fmtDH($caixa['data_fechamento'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . $caixaId . '/relatorio')) ?>"
           class="btn btn-outline-primary btn-sm">
            <i class="fas fa-file-alt me-1"></i> Relatório
        </a>
    </div>

    <!-- Ações primárias (apenas quando aberto) -->
    <?php if ($status === 'aberto'): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/venda')) ?>"
                   class="card border-0 shadow-sm text-decoration-none text-body h-100 action-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-success bg-opacity-10 text-success"
                             style="width:56px; height:56px;">
                            <i class="fas fa-bolt fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold fs-5 text-success">Venda Rápida</div>
                            <div class="small text-muted">Registrar venda no PDV agora</div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/fluxo')) ?>"
                   class="card border-0 shadow-sm text-decoration-none text-body h-100 action-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-warning bg-opacity-10 text-warning"
                             style="width:56px; height:56px;">
                            <i class="fas fa-columns fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold fs-5 text-warning">
                                Fluxo de Vendas
                                <?php if ($kpis['qtd_fluxo'] > 0): ?>
                                    <span class="badge bg-warning text-dark ms-1"><?= $kpis['qtd_fluxo'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted">Kanban das vendas a faturar</div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/historico')) ?>"
                   class="card border-0 shadow-sm text-decoration-none text-body h-100 action-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-info bg-opacity-10 text-info"
                             style="width:56px; height:56px;">
                            <i class="fas fa-list fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold fs-5 text-info">Histórico</div>
                            <div class="small text-muted">Ver todas as vendas deste caixa</div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPIs do caixa (ao vivo) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total Faturado</div>
                    <div class="h4 mb-0 text-success"><?= $brl($kpis['total_faturado']) ?></div>
                    <div class="small text-muted mt-1"><?= $kpis['qtd_vendas'] ?> venda(s)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Ticket Médio</div>
                    <div class="h4 mb-0"><?= $brl($kpis['ticket_medio']) ?></div>
                    <div class="small text-muted mt-1">Total ÷ Qtd</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm border-start border-4 border-warning h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">A Faturar (Fluxo)</div>
                    <div class="h4 mb-0 text-warning"><?= $brl($kpis['total_fluxo']) ?></div>
                    <div class="small text-muted mt-1"><?= $kpis['qtd_fluxo'] ?> pendente(s)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm border-start border-4 border-secondary h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Suprimento</div>
                    <div class="h4 mb-0"><?= $brl($caixa['valor_suprimento'] ?? 0) ?></div>
                    <?php if ($kpis['qtd_canceladas'] > 0): ?>
                        <div class="small text-danger mt-1"><?= $kpis['qtd_canceladas'] ?> cancelada(s)</div>
                    <?php else: ?>
                        <div class="small text-muted mt-1">Troco inicial</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Ação de fechamento (destaque separado) -->
    <?php if ($status === 'aberto' && $podeFechar): ?>
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-bold mb-1">
                        <i class="fas fa-check-double me-1 text-primary"></i> Pronto para fechar o caixa?
                    </div>
                    <div class="small text-muted">
                        Você irá informar os valores contados às cegas. A conferência final é feita pelo responsável no Gerencial.
                    </div>
                </div>
                <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/conferencia')) ?>" class="btn btn-primary">
                    <i class="fas fa-lock me-1"></i> Fechar caixa
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Outros caixas abertos -->
    <?php if ($outrosCaixas !== []): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold">
                    <i class="fas fa-cash-register me-1 text-muted"></i>
                    Outros caixas abertos
                </span>
                <span class="badge bg-light text-dark border"><?= count($outrosCaixas) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Caixa</th>
                                <th>Operador</th>
                                <th>Abertura</th>
                                <th class="text-end">Suprimento</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outrosCaixas as $o): ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int)($o['numero_caixa'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string)($o['operador_nome'] ?? '')) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($fmtDH($o['data_abertura'] ?? null)) ?></td>
                                    <td class="text-end"><?= $brl($o['valor_suprimento'] ?? 0) ?></td>
                                    <td class="text-end">
                                        <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . (int)($o['id'] ?? 0))) ?>"
                                           class="btn btn-sm btn-outline-primary">Acessar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.action-card { transition: transform .15s, box-shadow .15s; cursor: pointer; }
.action-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important; }
</style>
