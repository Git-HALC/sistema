<?php include __DIR__ . '/../../../../public/includes/header.php'; ?>

<div class="container-fluid px-3 px-lg-4">
<style>
/* ─── Finance Dashboard ─── */
.hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0d3b6e 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    color: #fff;
    border: none;
}
.hero-card::before {
    content: '';
    position: absolute;
    top: -70px; right: -70px;
    width: 220px; height: 220px;
    background: rgba(255,255,255,.04);
    border-radius: 50%;
    pointer-events: none;
}
.hero-card::after {
    content: '';
    position: absolute;
    bottom: -90px; left: 25%;
    width: 300px; height: 300px;
    background: rgba(16,185,129,.07);
    border-radius: 50%;
    pointer-events: none;
}
[data-theme="dark"] .hero-card {
    background: linear-gradient(135deg, #111 0%, #1a1a1a 50%, #0a2540 100%);
}

/* KPI gradient cards */
.kpi-grad {
    border-radius: 14px;
    color: #fff;
    padding: 1.2rem 1.4rem;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease;
    border: none;
}
.kpi-grad:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0,0,0,.22) !important;
}
.kpi-grad::after {
    content: '';
    position: absolute;
    top: -25px; right: -25px;
    width: 110px; height: 110px;
    background: rgba(255,255,255,.08);
    border-radius: 50%;
    pointer-events: none;
}
.kpi-grad.green  { background: linear-gradient(135deg, #047857 0%, #10b981 100%); }
.kpi-grad.amber  { background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); }
.kpi-grad.blue   { background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); }
.kpi-grad.red    { background: linear-gradient(135deg, #b91c1c 0%, #ef4444 100%); }
.kpi-grad.cyan   { background: linear-gradient(135deg, #0e7490 0%, #06b6d4 100%); }
.kpi-grad.orange { background: linear-gradient(135deg, #c2410c 0%, #f97316 100%); }
.kpi-icon-circle {
    width: 46px; height: 46px;
    border-radius: 11px;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.kpi-label  { font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; opacity: .85; }
.kpi-val-lg { font-size: 1.65rem; font-weight: 800; line-height: 1.15; }
.kpi-sub    { font-size: .78rem; opacity: .82; margin-top: .2rem; }

/* Partial cards */
.partial-card {
    border-radius: 14px;
    border: none;
    overflow: hidden;
    cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease;
}
.partial-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px rgba(0,0,0,.1) !important;
}
.partial-hdr {
    padding: .55rem 1.1rem;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #fff;
}

/* Conta rows */
.conta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .45rem .65rem;
    border-radius: 9px;
    cursor: pointer;
    transition: background .15s;
}
.conta-row:hover { background: rgba(255,255,255,.08); }

/* Section headers */
.section-card  { border-radius: 14px; border: none; overflow: hidden; }
.section-hdr   {
    background: linear-gradient(90deg, #1e293b 0%, #334155 100%);
    color: #fff; padding: .7rem 1.25rem;
}
[data-theme="dark"] .section-hdr {
    background: linear-gradient(90deg, #1a1a1a 0%, #2d2d2d 100%);
}
.chart-hdr {
    background: linear-gradient(90deg, #343a40 0%, #495057 100%);
    color: #fff; padding: .7rem 1.25rem;
}
[data-theme="dark"] .chart-hdr {
    background: linear-gradient(90deg, #1a1a1a 0%, #2d2d2d 100%);
}

/* Insights */
.insight-alert { border-left: 4px solid transparent; border-radius: 10px; }
.insight-alert.alert-success { border-left-color: #22c55e; }
.insight-alert.alert-warning { border-left-color: #f59e0b; }
.insight-alert.alert-danger  { border-left-color: #ef4444; }
.insight-alert.alert-info    { border-left-color: #3b82f6; }

/* Comparison */
.cmp-card { border-radius: 12px; border-top: 3px solid; }

/* Quick-action buttons */
.fin-btn {
    border-radius: 9px;
    font-size: .8rem;
    font-weight: 600;
    padding: .45rem .85rem;
    transition: transform .14s ease;
}
.fin-btn:hover { transform: translateY(-2px); }
</style>

<!-- ── Page Header ── -->
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mt-3 mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">
            <i class="fas fa-chart-line me-2 text-success opacity-75"></i>Dashboard Financeiro
        </h1>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                <i class="fas fa-circle fa-xs me-1"></i>Dados em tempo real
            </span>
            <small class="text-muted">Atualizado: <?= date('d/m/Y H:i') ?></small>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <select id="mesSelect" class="form-select form-select-sm" style="min-width:130px">
            <?php
            $meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                      7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
            for ($m = 1; $m <= 12; $m++):
            ?>
                <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>" <?= $m==(int)$mes ? 'selected' : '' ?>>
                    <?= $meses[$m] ?>
                </option>
            <?php endfor; ?>
        </select>
        <select id="anoSelect" class="form-select form-select-sm" style="min-width:90px">
            <?php for ($a = date('Y'); $a >= date('Y')-5; $a--): ?>
                <option value="<?= $a ?>" <?= $a==$ano ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
        </select>
        <button id="btnFiltrar" class="btn btn-primary btn-sm fin-btn">
            <i class="fas fa-filter me-1"></i>Filtrar
        </button>
    </div>
</div>

<?php
$saldoTotal = floatval($dashboard['saldo_total']['saldo_total'] ?? 0);
$fluxoMes   = $dashboard['fluxo_mes'];
$saldoMes   = floatval($fluxoMes['saldo']);
?>

<!-- ── ROW 1: Hero balance + Chart ── -->
<div class="row g-3 mb-3">

    <!-- Hero: Saldo + Contas -->
    <div class="col-12 col-xl-4">
        <div class="hero-card p-4 shadow h-100" style="min-height:200px">
            <div class="d-flex justify-content-between align-items-start mb-3" style="position:relative;z-index:1">
                <div>
                    <div style="font-size:.68rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;opacity:.7">
                        <i class="fas fa-wallet me-1"></i>Saldo Total Disponível
                    </div>
                    <div style="font-size:2.3rem;font-weight:800;line-height:1.1;color:<?= $saldoTotal >= 0 ? '#6ee7b7' : '#fca5a5' ?>">
                        R$ <span class="kpi-value" data-target="<?= round($saldoTotal,2) ?>" data-format="money">0,00</span>
                    </div>
                </div>
                <div style="background:rgba(255,255,255,.12);border-radius:12px;padding:.6rem .8rem;font-size:1.5rem">
                    <i class="fas fa-landmark"></i>
                </div>
            </div>

            <!-- Receitas / Despesas / Saldo do mês inline -->
            <div class="d-flex gap-3 flex-wrap mb-3" style="position:relative;z-index:1">
                <div>
                    <div style="font-size:.65rem;opacity:.65;text-transform:uppercase;letter-spacing:.04em">Receitas <?= $mes ?>/<?= $ano ?></div>
                    <div style="font-size:.9rem;font-weight:700;color:#6ee7b7">
                        +R$ <span class="kpi-value" data-target="<?= round(floatval($fluxoMes['receitas']),2) ?>" data-format="money">0</span>
                    </div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,.18)"></div>
                <div>
                    <div style="font-size:.65rem;opacity:.65;text-transform:uppercase;letter-spacing:.04em">Despesas</div>
                    <div style="font-size:.9rem;font-weight:700;color:#fca5a5">
                        -R$ <span class="kpi-value" data-target="<?= round(floatval($fluxoMes['despesas']),2) ?>" data-format="money">0</span>
                    </div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,.18)"></div>
                <div>
                    <div style="font-size:.65rem;opacity:.65;text-transform:uppercase;letter-spacing:.04em">Saldo mês</div>
                    <div style="font-size:.9rem;font-weight:700;color:<?= $saldoMes >= 0 ? '#6ee7b7' : '#fca5a5' ?>">
                        R$ <span class="kpi-value" data-target="<?= round($saldoMes,2) ?>" data-format="money">0</span>
                    </div>
                </div>
            </div>

            <!-- Lista de contas -->
            <div style="position:relative;z-index:1;border-top:1px solid rgba(255,255,255,.12);padding-top:.6rem">
                <?php foreach ($dashboard['contas'] as $conta): ?>
                    <div class="conta-row clickable-account" data-conta-id="<?= $conta['id'] ?>">
                        <span style="font-size:.8rem;color:rgba(255,255,255,.8)">
                            <i class="fas fa-university me-2" style="opacity:.5"></i><?= htmlspecialchars($conta['nome']) ?>
                        </span>
                        <span style="font-size:.8rem;font-weight:700;color:<?= $conta['saldo_atual'] >= 0 ? '#6ee7b7' : '#fca5a5' ?>">
                            R$ <?= number_format($conta['saldo_atual'], 2, ',', '.') ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <div class="mt-2">
                    <button class="btn fin-btn w-100"
                            style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2)"
                            onclick="event.stopPropagation(); location.href='/sistema_dm/public/admin/financeiro/contas.php'">
                        <i class="fas fa-cog me-1"></i>Gerenciar Contas
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bar chart: Receitas vs Despesas -->
    <div class="col-12 col-xl-8">
        <div class="card section-card shadow-sm h-100">
            <div class="card-header section-hdr d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-chart-bar me-2"></i>Receitas vs Despesas — Últimos 6 Meses</strong>
                <button class="btn btn-sm btn-outline-light fin-btn" onclick="alternarVisualizacao('fluxo')" title="Alternar barra/linha">
                    <i class="fas fa-exchange-alt"></i>
                </button>
            </div>
            <div class="card-body" style="min-height:230px">
                <canvas id="fluxoMensalChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── ROW 2: 4 KPI Cards ── -->
<div class="row g-3 mb-3">

    <!-- A Receber -->
    <div class="col-6 col-xl-3">
        <div class="kpi-grad green shadow clickable-card" data-type="receber-aberto-mes" data-mes="<?= $mes ?>" data-ano="<?= $ano ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon-circle"><i class="fas fa-arrow-up"></i></div>
                <span class="badge rounded-pill" style="background:rgba(255,255,255,.2);font-size:.68rem">
                    <?= $dashboard['receber_aberto_mes']['total'] ?? 0 ?> tít.
                </span>
            </div>
            <div class="kpi-label mb-1">A Receber <?= $mes ?>/<?= $ano ?></div>
            <div class="kpi-val-lg">
                R$ <span class="kpi-value" data-target="<?= round(floatval($dashboard['receber_aberto_mes']['valor'] ?? 0),2) ?>" data-format="money">0</span>
            </div>
            <div class="mt-2" style="height:3px;background:rgba(255,255,255,.2);border-radius:2px">
                <div style="height:100%;width:<?= ($dashboard['receber_aberto_mes']['total'] ?? 0) > 0 ? '100' : '0' ?>%;background:rgba(255,255,255,.55);border-radius:2px"></div>
            </div>
        </div>
    </div>

    <!-- Receber em Atraso -->
    <div class="col-6 col-xl-3">
        <?php $totAtrasoR = (int)($dashboard['receber_atraso']['total'] ?? 0); ?>
        <div class="kpi-grad amber shadow clickable-card" data-type="receber-atraso">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon-circle"><i class="fas fa-clock"></i></div>
                <span class="badge rounded-pill" style="background:rgba(255,255,255,.2);font-size:.68rem">
                    <?= $totAtrasoR ?> tít.
                </span>
            </div>
            <div class="kpi-label mb-1">Receber em Atraso</div>
            <div class="kpi-val-lg">
                R$ <span class="kpi-value" data-target="<?= round(floatval($dashboard['receber_atraso']['valor'] ?? 0),2) ?>" data-format="money">0</span>
            </div>
            <div class="kpi-sub">
                <?php if ($totAtrasoR > 0): ?>
                    <i class="fas fa-exclamation-triangle me-1"></i>Exige atenção
                <?php else: ?>
                    <i class="fas fa-check me-1"></i>Nenhum em atraso
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- A Pagar -->
    <div class="col-6 col-xl-3">
        <div class="kpi-grad blue shadow clickable-card" data-type="pagar-aberto-mes" data-mes="<?= $mes ?>" data-ano="<?= $ano ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon-circle"><i class="fas fa-arrow-down"></i></div>
                <span class="badge rounded-pill" style="background:rgba(255,255,255,.2);font-size:.68rem">
                    <?= $dashboard['pagar_aberto_mes']['total'] ?? 0 ?> tít.
                </span>
            </div>
            <div class="kpi-label mb-1">A Pagar <?= $mes ?>/<?= $ano ?></div>
            <div class="kpi-val-lg">
                R$ <span class="kpi-value" data-target="<?= round(floatval($dashboard['pagar_aberto_mes']['valor'] ?? 0),2) ?>" data-format="money">0</span>
            </div>
            <div class="mt-2" style="height:3px;background:rgba(255,255,255,.2);border-radius:2px">
                <div style="height:100%;width:<?= ($dashboard['pagar_aberto_mes']['total'] ?? 0) > 0 ? '100' : '0' ?>%;background:rgba(255,255,255,.55);border-radius:2px"></div>
            </div>
        </div>
    </div>

    <!-- Pagar em Atraso -->
    <div class="col-6 col-xl-3">
        <?php $totAtrasoP = (int)($dashboard['pagar_atraso']['total'] ?? 0); ?>
        <div class="kpi-grad red shadow clickable-card" data-type="pagar-atraso">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon-circle"><i class="fas fa-exclamation-circle"></i></div>
                <span class="badge rounded-pill" style="background:rgba(255,255,255,.2);font-size:.68rem">
                    <?= $totAtrasoP ?> tít.
                </span>
            </div>
            <div class="kpi-label mb-1">Pagar em Atraso</div>
            <div class="kpi-val-lg">
                R$ <span class="kpi-value" data-target="<?= round(floatval($dashboard['pagar_atraso']['valor'] ?? 0),2) ?>" data-format="money">0</span>
            </div>
            <div class="kpi-sub">
                <?php if ($totAtrasoP > 0): ?>
                    <i class="fas fa-bolt me-1"></i>Urgente!
                <?php else: ?>
                    <i class="fas fa-check me-1"></i>Tudo em dia
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── ROW 3: Parciais + Top Categorias ── -->
<div class="row g-3 mb-3">

    <!-- Recebimento Parcial -->
    <?php
    $pr       = $dashboard['receber_parcial'];
    $totPR    = (int)($pr['total'] ?? 0);
    $origPR   = floatval($pr['valor_original'] ?? 0);
    $recPR    = floatval($pr['valor_recebido'] ?? 0);
    $pendPR   = floatval($pr['valor_pendente'] ?? 0);
    $pctPR    = $origPR > 0 ? ($recPR / $origPR) * 100 : 0;
    ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card partial-card shadow-sm h-100 clickable-card" data-type="receber-parcial">
            <div class="partial-hdr" style="background:linear-gradient(90deg,#0e7490,#06b6d4)">
                <i class="fas fa-hand-holding-usd me-1"></i>Recebimento Parcial
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="text-center" style="min-width:52px">
                        <div class="fw-bold" style="font-size:2.2rem;line-height:1;color:#06b6d4"><?= $totPR ?></div>
                        <div style="font-size:.65rem;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">títulos</div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="progress mb-1" style="height:8px;border-radius:4px">
                            <div class="progress-bar" style="background:linear-gradient(90deg,#0e7490,#06b6d4);width:<?= $pctPR ?>%;border-radius:4px;transition:width 1s ease"></div>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size:.73rem">
                            <span class="text-muted"><?= number_format($pctPR,1) ?>% recebido</span>
                            <span style="color:#0e7490;font-weight:600"><?= number_format(100-$pctPR,1) ?>% pendente</span>
                        </div>
                    </div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="rounded p-2" style="background:rgba(107,114,128,.07)">
                            <div style="font-size:.65rem;color:#6b7280;text-transform:uppercase;letter-spacing:.03em">Total</div>
                            <div style="font-size:.85rem;font-weight:700">R$ <span class="kpi-value" data-target="<?= round($origPR,2) ?>" data-format="money">0</span></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background:rgba(16,185,129,.08)">
                            <div style="font-size:.65rem;color:#047857;text-transform:uppercase;letter-spacing:.03em">Recebido</div>
                            <div style="font-size:.85rem;font-weight:700;color:#047857">R$ <span class="kpi-value" data-target="<?= round($recPR,2) ?>" data-format="money">0</span></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background:rgba(245,158,11,.08)">
                            <div style="font-size:.65rem;color:#b45309;text-transform:uppercase;letter-spacing:.03em">Pendente</div>
                            <div style="font-size:.85rem;font-weight:700;color:#b45309">R$ <span class="kpi-value" data-target="<?= round($pendPR,2) ?>" data-format="money">0</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagamento Parcial -->
    <?php
    $pp       = $dashboard['pagar_parcial'];
    $totPP    = (int)($pp['total'] ?? 0);
    $origPP   = floatval($pp['valor_original'] ?? 0);
    $pagPP    = floatval($pp['valor_pago'] ?? 0);
    $pendPP   = floatval($pp['valor_pendente'] ?? 0);
    $pctPP    = $origPP > 0 ? ($pagPP / $origPP) * 100 : 0;
    ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card partial-card shadow-sm h-100 clickable-card" data-type="pagar-parcial">
            <div class="partial-hdr" style="background:linear-gradient(90deg,#b45309,#f59e0b)">
                <i class="fas fa-credit-card me-1"></i>Pagamento Parcial
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="text-center" style="min-width:52px">
                        <div class="fw-bold" style="font-size:2.2rem;line-height:1;color:#f59e0b"><?= $totPP ?></div>
                        <div style="font-size:.65rem;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">títulos</div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="progress mb-1" style="height:8px;border-radius:4px">
                            <div class="progress-bar" style="background:linear-gradient(90deg,#b45309,#f59e0b);width:<?= $pctPP ?>%;border-radius:4px;transition:width 1s ease"></div>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size:.73rem">
                            <span class="text-muted"><?= number_format($pctPP,1) ?>% pago</span>
                            <span style="color:#b45309;font-weight:600"><?= number_format(100-$pctPP,1) ?>% pendente</span>
                        </div>
                    </div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="rounded p-2" style="background:rgba(107,114,128,.07)">
                            <div style="font-size:.65rem;color:#6b7280;text-transform:uppercase;letter-spacing:.03em">Total</div>
                            <div style="font-size:.85rem;font-weight:700">R$ <span class="kpi-value" data-target="<?= round($origPP,2) ?>" data-format="money">0</span></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background:rgba(16,185,129,.08)">
                            <div style="font-size:.65rem;color:#047857;text-transform:uppercase;letter-spacing:.03em">Pago</div>
                            <div style="font-size:.85rem;font-weight:700;color:#047857">R$ <span class="kpi-value" data-target="<?= round($pagPP,2) ?>" data-format="money">0</span></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background:rgba(239,68,68,.08)">
                            <div style="font-size:.65rem;color:#b91c1c;text-transform:uppercase;letter-spacing:.03em">Pendente</div>
                            <div style="font-size:.85rem;font-weight:700;color:#b91c1c">R$ <span class="kpi-value" data-target="<?= round($pendPP,2) ?>" data-format="money">0</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Categorias (Doughnut) -->
    <div class="col-12 col-xl-4">
        <div class="card section-card shadow-sm h-100">
            <div class="card-header chart-hdr">
                <strong><i class="fas fa-chart-pie me-2"></i>Top Despesas (<?= $mes ?>/<?= $ano ?>)</strong>
            </div>
            <div class="card-body p-3">
                <div style="max-height:165px">
                    <canvas id="categoriasChart"></canvas>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-1 justify-content-center">
                    <?php
                    $cores_fixas = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#2e59d9','#17a673','#2c9faf'];
                    foreach ($dashboard['top_categorias'] as $idx => $cat):
                    ?>
                        <span class="badge rounded-pill clickable-category"
                              style="background:<?= $cores_fixas[$idx] ?>20;color:<?= $cores_fixas[$idx] ?>;border:1px solid <?= $cores_fixas[$idx] ?>55;cursor:pointer;font-size:.68rem"
                              data-categoria="<?= htmlspecialchars($cat['nome']) ?>">
                            <?= htmlspecialchars($cat['nome']) ?>
                            <small class="ms-1 opacity-75">R$ <?= number_format($cat['total'], 0, ',', '.') ?></small>
                        </span>
                    <?php endforeach; ?>
                    <?php if (empty($dashboard['top_categorias'])): ?>
                        <div class="text-center text-muted w-100 py-2">
                            <i class="fas fa-info-circle mb-1 d-block opacity-40"></i>
                            <small>Nenhuma despesa no período</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── ROW 4: Quick Actions ── -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <button class="btn btn-success fin-btn w-100"
                onclick="location.href='/sistema_dm/public/admin/financeiro/contas-receber.php'">
            <i class="fas fa-plus me-1"></i>Nova Receita
        </button>
    </div>
    <div class="col-6 col-md-3">
        <button class="btn btn-danger fin-btn w-100"
                onclick="location.href='/sistema_dm/public/admin/financeiro/contas-pagar.php'">
            <i class="fas fa-minus me-1"></i>Nova Despesa
        </button>
    </div>
    <div class="col-6 col-md-3">
        <button class="btn btn-outline-primary fin-btn w-100"
                onclick="location.href='/sistema_dm/public/admin/financeiro/movimentacoes.php?mes=<?= $mes ?>&ano=<?= $ano ?>'">
            <i class="fas fa-list me-1"></i>Movimentações
        </button>
    </div>
    <div class="col-6 col-md-3">
        <button class="btn btn-outline-secondary fin-btn w-100"
                onclick="location.href='/sistema_dm/public/admin/financeiro/relatorios.php'">
            <i class="fas fa-file-alt me-1"></i>Relatórios
        </button>
    </div>
</div>

<!-- ── ROW 5: Insights ── -->
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card section-card shadow-sm">
            <div class="card-header section-hdr d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-brain me-2"></i>Insights Financeiros</strong>
                <button class="btn btn-sm btn-outline-light fin-btn" onclick="recarregarInsights()">
                    <i class="fas fa-sync-alt me-1"></i>Atualizar
                </button>
            </div>
            <div class="card-body">
                <div id="insightsContainer">
                    <?php
                    $insights = [];

                    if ($saldoMes < 0) {
                        $insights[] = ['tipo'=>'danger','icone'=>'exclamation-triangle','titulo'=>'Fluxo Negativo',
                            'mensagem'=>'Fluxo negativo em R$ '.number_format(abs($saldoMes),2,',','.').' no mês. Revise as despesas ou aumente receitas.',
                            'acao'=>'Ver despesas','link'=>"/sistema_dm/public/admin/financeiro/contas-pagar.php?status=Pago&mes=$mes&ano=$ano"];
                    } elseif ($saldoMes > 1000) {
                        $insights[] = ['tipo'=>'success','icone'=>'chart-line','titulo'=>'Ótimo Desempenho',
                            'mensagem'=>'Fluxo positivo de R$ '.number_format($saldoMes,2,',','.').' — excelente gestão financeira no período.',
                            'acao'=>'Ver detalhes','link'=>"/sistema_dm/public/admin/financeiro/movimentacoes.php?mes=$mes&ano=$ano"];
                    }

                    $totalAtrasoReceber = floatval($dashboard['receber_atraso']['valor'] ?? 0);
                    $totalAtrasoPagar   = floatval($dashboard['pagar_atraso']['valor'] ?? 0);

                    if ($totalAtrasoReceber > 0) {
                        $insights[] = ['tipo'=>'warning','icone'=>'clock','titulo'=>'Receitas em Atraso',
                            'mensagem'=>'R$ '.number_format($totalAtrasoReceber,2,',','.').' em receitas atrasadas. Ação recomendada: cobrança ativa.',
                            'acao'=>'Ver atrasadas','link'=>'/sistema_dm/public/admin/financeiro/contas-receber.php?status=Vencido'];
                    }
                    if ($totalAtrasoPagar > 0) {
                        $insights[] = ['tipo'=>'danger','icone'=>'exclamation-circle','titulo'=>'Pagamentos Urgentes',
                            'mensagem'=>'R$ '.number_format($totalAtrasoPagar,2,',','.').' em pagamentos atrasados. Risco de multas e juros.',
                            'acao'=>'Regularizar','link'=>'/sistema_dm/public/admin/financeiro/contas-pagar.php?status=Vencido'];
                    }

                    if ($totPR > 0) {
                        $insights[] = ['tipo'=>'info','icone'=>'hand-holding-usd','titulo'=>'Receitas Parciais Pendentes',
                            'mensagem'=>"$totPR títulos com R$ ".number_format($pendPR,2,',','.').' ainda a receber.',
                            'acao'=>'Ver parciais','link'=>'/sistema_dm/public/admin/financeiro/contas-receber.php?status=Parcialmente%20Recebido'];
                    }
                    if ($totPP > 0) {
                        $insights[] = ['tipo'=>'warning','icone'=>'credit-card','titulo'=>'Pagamentos Parciais Pendentes',
                            'mensagem'=>"$totPP títulos com R$ ".number_format($pendPP,2,',','.').' ainda a pagar.',
                            'acao'=>'Ver parciais','link'=>'/sistema_dm/public/admin/financeiro/contas-pagar.php?status=Parcialmente%20Pago'];
                    }

                    if ($saldoTotal < 0) {
                        $insights[] = ['tipo'=>'danger','icone'=>'wallet','titulo'=>'Saldo Negativo',
                            'mensagem'=>'Saldo total negativo em R$ '.number_format(abs($saldoTotal),2,',','.'). '. Atenção urgente!',
                            'acao'=>'Gerenciar contas','link'=>'/sistema_dm/public/admin/financeiro/contas.php'];
                    }

                    if (!empty($dashboard['top_categorias']) && floatval($fluxoMes['despesas']) > 0) {
                        $top    = $dashboard['top_categorias'][0];
                        $pctCat = ($top['total'] / floatval($fluxoMes['despesas'])) * 100;
                        if ($pctCat > 40) {
                            $insights[] = ['tipo'=>'info','icone'=>'chart-pie','titulo'=>'Concentração de Despesas',
                                'mensagem'=>"'{$top['nome']}' representa ".number_format($pctCat,1).'% das despesas. Considere diversificar.',
                                'acao'=>'Ver categorias','link'=>'/sistema_dm/public/admin/financeiro/relatorios.php?tipo=categorias'];
                        }
                    }

                    if (empty($insights)) {
                        $insights[] = ['tipo'=>'success','icone'=>'check-circle','titulo'=>'Tudo em Dia!',
                            'mensagem'=>'Sua saúde financeira está excelente. Continue mantendo o bom controle!',
                            'acao'=>'Ver relatórios','link'=>'/sistema_dm/public/admin/financeiro/relatorios.php'];
                    }

                    foreach ($insights as $ins):
                    ?>
                    <div class="alert alert-<?= $ins['tipo'] ?> alert-dismissible fade show insight-alert mb-2 d-flex align-items-start gap-3">
                        <i class="fas fa-<?= $ins['icone'] ?> fa-lg mt-1 flex-shrink-0"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold mb-1"><?= htmlspecialchars($ins['titulo']) ?></div>
                            <div class="small"><?= htmlspecialchars($ins['mensagem']) ?></div>
                        </div>
                        <a href="<?= htmlspecialchars($ins['link']) ?>"
                           class="btn btn-sm btn-outline-<?= $ins['tipo'] ?> text-nowrap flex-shrink-0 ms-auto fin-btn"
                           onclick="event.stopPropagation()">
                            <?= htmlspecialchars($ins['acao']) ?>
                        </a>
                        <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        <i class="fas fa-robot me-1"></i>Insights gerados automaticamente com base nos seus dados financeiros
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── ROW 6: Análise Comparativa ── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card section-card shadow-sm">
            <div class="card-header section-hdr">
                <strong><i class="fas fa-exchange-alt me-2"></i>Análise Comparativa</strong>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Tipo de Comparação</label>
                        <select id="tipoComparacao" class="form-select form-select-sm">
                            <option value="mes_a_mes">Mês a Mês</option>
                            <option value="dia_a_dia">Dia a Dia</option>
                            <option value="ano_a_ano">Ano a Ano</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Período 1</label>
                        <input type="text" id="periodo1" class="form-control form-control-sm" placeholder="Ex: 11/2024">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Período 2</label>
                        <input type="text" id="periodo2" class="form-control form-control-sm" placeholder="Ex: 11/2025">
                    </div>
                    <div class="col-md-3">
                        <button id="btnComparar" class="btn btn-primary btn-sm fin-btn w-100">
                            <i class="fas fa-search me-1"></i>Comparar
                        </button>
                    </div>
                </div>
                <div id="resultadosComparacao">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm cmp-card" style="border-top-color:#22c55e">
                                <div class="card-body">
                                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size:.7rem">Variação Receitas</div>
                                    <div class="h5 fw-bold text-success mb-0" id="variacaoReceitas"><span class="kpi-comparacao">0%</span></div>
                                    <div class="text-muted small" id="detalhesReceitas">R$ 0 vs R$ 0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm cmp-card" style="border-top-color:#ef4444">
                                <div class="card-body">
                                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size:.7rem">Variação Despesas</div>
                                    <div class="h5 fw-bold text-danger mb-0" id="variacaoDespesas"><span class="kpi-comparacao">0%</span></div>
                                    <div class="text-muted small" id="detalhesDespesas">R$ 0 vs R$ 0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm cmp-card" style="border-top-color:#3b82f6">
                                <div class="card-body">
                                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size:.7rem">Variação Saldo</div>
                                    <div class="h5 fw-bold text-primary mb-0" id="variacaoSaldo"><span class="kpi-comparacao">0%</span></div>
                                    <div class="text-muted small" id="detalhesSaldo">R$ 0 vs R$ 0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-light">
                            <strong class="small">Gráfico Comparativo</strong>
                        </div>
                        <div class="card-body" style="min-height:200px">
                            <canvas id="comparacaoChart" style="max-height:350px"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /container-fluid -->

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function() {
    const dadosUltimosMeses = <?= json_encode($dashboard['ultimos_meses']) ?>;
    const dadosCategorias   = <?= json_encode($dashboard['top_categorias']) ?>;

    // ── KPI counter animation ──
    function animateKpi(el, target, isInt) {
        const startTs = performance.now(), dur = 900;
        const fmt = isInt
            ? v => Math.floor(v).toLocaleString('pt-BR')
            : v => v.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        const frame = now => {
            const p = Math.min((now - startTs) / dur, 1);
            const e = 1 - Math.pow(1 - p, 3);
            el.textContent = fmt(target * e);
            if (p < 1) requestAnimationFrame(frame);
        };
        requestAnimationFrame(frame);
    }
    document.querySelectorAll('.kpi-value[data-target]').forEach((el, i) => {
        const raw     = parseFloat(el.dataset.target || '0');
        const isMoney = el.dataset.format === 'money';
        setTimeout(() => animateKpi(el, raw, !isMoney), i * 40);
    });

    // ── Bar Chart ──
    const mesesLabel   = dadosUltimosMeses.map(d => d.mes.toString().padStart(2,'0') + '/' + d.ano);
    const receitasData = dadosUltimosMeses.map(d => parseFloat(d.receitas || 0));
    const despesasData = dadosUltimosMeses.map(d => parseFloat(d.despesas || 0));

    let myBarChart;
    if (document.getElementById('fluxoMensalChart')) {
        myBarChart = new Chart(document.getElementById('fluxoMensalChart'), {
            type: 'bar',
            data: {
                labels: mesesLabel,
                datasets: [
                    { label: 'Receitas', data: receitasData, backgroundColor: 'rgba(16,185,129,.8)', borderColor: '#10b981', borderWidth: 2, borderRadius: 6 },
                    { label: 'Despesas', data: despesasData, backgroundColor: 'rgba(244,63,94,.8)',  borderColor: '#f43f5e', borderWidth: 2, borderRadius: 6 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                animation: { duration: 1200, easing: 'easeOutCubic' },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => 'R$ ' + Number(v).toLocaleString('pt-BR') }, grid: { color: 'rgba(0,0,0,.05)' } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': R$ ' + ctx.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits:2}) } }
                }
            }
        });
    }

    // ── Doughnut Chart ──
    if (dadosCategorias.length > 0 && document.getElementById('categoriasChart')) {
        const cores = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#2e59d9','#17a673','#2c9faf'];
        new Chart(document.getElementById('categoriasChart'), {
            type: 'doughnut',
            data: {
                labels: dadosCategorias.map(d => d.nome),
                datasets: [{ data: dadosCategorias.map(d => parseFloat(d.total || 0)), backgroundColor: cores.slice(0, dadosCategorias.length), borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                animation: { animateRotate: true, duration: 1000, easing: 'easeOutCubic' },
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => { const t = ctx.dataset.data.reduce((a,b)=>a+b,0); const p = t>0?((ctx.parsed/t)*100).toFixed(1):0; return ctx.label+': R$ '+ctx.parsed.toLocaleString('pt-BR',{minimumFractionDigits:2})+' ('+p+'%)'; } } }
                }
            }
        });
    }

    // ── Filter ──
    $('#btnFiltrar').on('click', function() {
        window.location.href = '?mes=' + $('#mesSelect').val() + '&ano=' + $('#anoSelect').val();
    });
    $('#mesSelect, #anoSelect').on('change', function() { $('#btnFiltrar').click(); });

    // ── Reload insights ──
    window.recarregarInsights = function() {
        const $c = $('#insightsContainer');
        $c.fadeOut(300, function() {
            $c.html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted small">Analisando...</p></div>').fadeIn(300);
            setTimeout(() => location.reload(), 1000);
        });
    };

    // ── Clickable cards ──
    $('.clickable-card').on('click', function(e) {
        if ($(e.target).closest('button,a,.dropdown').length > 0) return;
        const type = $(this).data('type');
        const mes  = $(this).data('mes') || '<?= $mes ?>';
        const ano  = $(this).data('ano') || '<?= $ano ?>';
        const map  = {
            'receber-aberto-mes': `/sistema_dm/public/admin/financeiro/contas-receber.php?status=Aberto&mes=${mes}&ano=${ano}`,
            'receber-atraso':     `/sistema_dm/public/admin/financeiro/contas-receber.php?status=Vencido`,
            'receber-parcial':    `/sistema_dm/public/admin/financeiro/contas-receber.php?status=Parcialmente%20Recebido`,
            'pagar-aberto-mes':   `/sistema_dm/public/admin/financeiro/contas-pagar.php?status=Aberto&mes=${mes}&ano=${ano}`,
            'pagar-atraso':       `/sistema_dm/public/admin/financeiro/contas-pagar.php?status=Vencido`,
            'pagar-parcial':      `/sistema_dm/public/admin/financeiro/contas-pagar.php?status=Parcialmente%20Pago`,
        };
        if (map[type]) window.location.href = map[type];
    });

    $('.clickable-account').on('click', function(e) {
        e.stopPropagation();
        window.location.href = `/sistema_dm/public/admin/financeiro/contas.php?id=${$(this).data('conta-id')}`;
    });

    $('.clickable-category').on('click', function() {
        const cat = $(this).data('categoria');
        window.location.href = `/sistema_dm/public/admin/financeiro/contas-pagar.php?categoria=${encodeURIComponent(cat)}&mes=<?= $mes ?>&ano=<?= $ano ?>`;
    });

    // ── Chart toggle barra/linha ──
    let vizFluxo = 'barra';
    window.alternarVisualizacao = function(tipo) {
        if (tipo === 'fluxo' && myBarChart) {
            myBarChart.config.type = vizFluxo === 'barra' ? 'line' : 'bar';
            vizFluxo = vizFluxo === 'barra' ? 'linha' : 'barra';
            myBarChart.update();
        }
    };

    // ── Comparação AJAX ──
    let comparacaoChart = null;
    $('#tipoComparacao').on('change', function() {
        const tipo = $(this).val();
        const ph1  = tipo==='dia_a_dia' ? 'Ex: 15/11/2024' : tipo==='ano_a_ano' ? 'Ex: 2024' : 'Ex: 11/2024';
        const ph2  = tipo==='dia_a_dia' ? 'Ex: 15/11/2025' : tipo==='ano_a_ano' ? 'Ex: 2025' : 'Ex: 11/2025';
        $('#periodo1').attr('placeholder', ph1);
        $('#periodo2').attr('placeholder', ph2);
    });

    $('#btnComparar').on('click', function() {
        const tipo    = $('#tipoComparacao').val();
        const p1      = $('#periodo1').val().trim();
        const p2      = $('#periodo2').val().trim();
        if (!p1 || !p2) { alert('Preencha ambos os períodos.'); return; }

        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/dashboard_comparacao.php',
            method: 'POST', data: { tipo, periodo1: p1, periodo2: p2 }, dataType: 'json',
            beforeSend: () => $('body').addClass('loading'),
            success: function(r) {
                if (!r.success) { alert('Erro: ' + r.message); return; }
                const d = r.data;
                function calcVar(a, b) {
                    if (a === 0) return { pct: b > 0 ? '+∞' : '0', cls: b > 0 ? 'text-success' : 'text-muted' };
                    const v = ((b - a) / Math.abs(a)) * 100;
                    return { pct: (v >= 0 ? '+' : '') + v.toFixed(1) + '%', cls: v >= 0 ? 'text-success' : 'text-danger' };
                }
                const vR = calcVar(d.periodo1.receitas, d.periodo2.receitas);
                const vD = calcVar(d.periodo1.despesas, d.periodo2.despesas);
                const vS = calcVar(d.periodo1.saldo,    d.periodo2.saldo);
                $('#variacaoReceitas .kpi-comparacao').text(vR.pct).removeClass('text-success text-danger text-muted').addClass(vR.cls);
                $('#detalhesReceitas').text('R$ ' + d.periodo1.receitas.toLocaleString('pt-BR') + ' vs R$ ' + d.periodo2.receitas.toLocaleString('pt-BR'));
                $('#variacaoDespesas .kpi-comparacao').text(vD.pct).removeClass('text-success text-danger text-muted').addClass(vD.cls);
                $('#detalhesDespesas').text('R$ ' + d.periodo1.despesas.toLocaleString('pt-BR') + ' vs R$ ' + d.periodo2.despesas.toLocaleString('pt-BR'));
                $('#variacaoSaldo .kpi-comparacao').text(vS.pct).removeClass('text-success text-danger text-muted').addClass(vS.cls);
                $('#detalhesSaldo').text('R$ ' + d.periodo1.saldo.toLocaleString('pt-BR') + ' vs R$ ' + d.periodo2.saldo.toLocaleString('pt-BR'));
                if (comparacaoChart) comparacaoChart.destroy();
                comparacaoChart = new Chart(document.getElementById('comparacaoChart'), {
                    type: 'bar',
                    data: {
                        labels: ['Receitas','Despesas','Saldo'],
                        datasets: [
                            { label: p1, data: [d.periodo1.receitas, d.periodo1.despesas, d.periodo1.saldo], backgroundColor: 'rgba(99,102,241,.8)', borderRadius: 6 },
                            { label: p2, data: [d.periodo2.receitas, d.periodo2.despesas, d.periodo2.saldo], backgroundColor: 'rgba(16,185,129,.8)',  borderRadius: 6 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { ticks: { callback: v => 'R$ ' + Number(v).toLocaleString('pt-BR') } }, x: { grid: { display: false } } }
                    }
                });
            },
            error: () => alert('Erro de comunicação com o servidor.'),
            complete: () => $('body').removeClass('loading')
        });
    });

    // ── Keyboard shortcuts ──
    $(document).on('keydown', function(e) {
        if (e.ctrlKey) {
            if (e.key === 'r') { e.preventDefault(); recarregarInsights(); }
            if (e.key === 'd') { e.preventDefault(); alternarVisualizacao('fluxo'); }
            if (e.key === '1') { e.preventDefault(); window.location.href = `/sistema_dm/public/admin/financeiro/contas-receber.php?status=Aberto&mes=<?= $mes ?>&ano=<?= $ano ?>`; }
            if (e.key === '2') { e.preventDefault(); window.location.href = `/sistema_dm/public/admin/financeiro/contas-pagar.php?status=Aberto&mes=<?= $mes ?>&ano=<?= $ano ?>`; }
            if (e.key === '3') { e.preventDefault(); window.location.href = `/sistema_dm/public/admin/financeiro/contas.php`; }
        }
    });
});
</script>
