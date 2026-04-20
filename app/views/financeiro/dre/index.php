<?php
/**
 * DRE — Demonstrativo de Resultado (regime de competencia, 11 blocos).
 * Consome: $dre = ['blocos'=>[...], 'periodo'=>[...]] do DreRepository::gerarCentralizado().
 */
use App\Support\CsrfProtection;

$blocos = $dre['blocos'] ?? [];
$periodo = $dre['periodo'] ?? ['inicio' => date('Y-m-01'), 'fim' => date('Y-m-t')];

// KPIs de topo
$receitaBruta = (float)($blocos['01_receita_bruta']['valor'] ?? 0);
$receitaLiquida = (float)($blocos['03_receita_liquida']['valor'] ?? 0);
$cpv = (float)($blocos['04_cpv']['valor'] ?? 0);
$despesaOp = (float)($blocos['06_despesas_operacionais']['valor'] ?? 0);
$despesaFin = (float)($blocos['08_resultado_financeiro']['despesa_financeira'] ?? 0);
$deducoes = (float)($blocos['02_deducoes']['valor'] ?? 0);
$tributos = (float)($blocos['10_tributos']['valor'] ?? 0);
$lucroLiquido = (float)($blocos['11_lucro_liquido']['valor'] ?? 0);
$resultadoOp = (float)($blocos['07_resultado_operacional']['valor'] ?? 0);
$lucroBruto = (float)($blocos['05_lucro_bruto']['valor'] ?? 0);

$despesasTotais = $cpv + $despesaOp + $despesaFin + $deducoes + $tributos;
$margemLiquida = $receitaBruta > 0 ? ($lucroLiquido / $receitaBruta) * 100 : 0.0;
$margemBruta = $receitaBruta > 0 ? ($lucroBruto / $receitaBruta) * 100 : 0.0;
$margemOp = $receitaBruta > 0 ? ($resultadoOp / $receitaBruta) * 100 : 0.0;

$brl = static fn(float $v) => ($v < 0 ? '(' : '') . 'R$ ' . number_format(abs($v), 2, ',', '.') . ($v < 0 ? ')' : '');
$pct = static fn(float $v) => number_format($v, 1, ',', '.') . '%';

// Filtros de periodo
$periodoAtalho = $_GET['periodo_atalho'] ?? '';
$mesAtual = (int)($_GET['mes'] ?? date('m'));
$anoAtual = (int)($_GET['ano'] ?? date('Y'));
$dataInicioStr = $_GET['data_inicio'] ?? $periodo['inicio'];
$dataFimStr = $_GET['data_fim'] ?? $periodo['fim'];

$baseUrl = '/sistema_dm/public/admin/financeiro/dre.php';

// Serializacao para JS
$chartData = [
    'labels' => ['Rec. Bruta','(-) Deducoes','Rec. Liquida','(-) CPV','Lucro Bruto','(-) Desp. Op.','Result. Op.','Resultado Fin.','LAIR','(-) Tributos','Lucro Liquido'],
    'valores' => [
        $receitaBruta, -$deducoes, $receitaLiquida, -$cpv, $lucroBruto,
        -$despesaOp, $resultadoOp, (float)($blocos['08_resultado_financeiro']['valor'] ?? 0),
        (float)($blocos['09_lair']['valor'] ?? 0), -$tributos, $lucroLiquido,
    ],
];
?>

<style>
    .dre-toolbar { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
    .dre-filter-group { display:flex; gap:.5rem; flex-wrap:wrap; align-items:end; }
    .dre-filter-group .form-control, .dre-filter-group .form-select { min-width:120px; }
    .dre-filter-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .75rem; border:1px solid var(--border-color, #e5e7eb); border-radius:999px; font-size:.78rem; cursor:pointer; background:transparent; color:var(--text-primary, #374151); transition:all .15s; }
    .dre-filter-chip:hover { background:var(--bg-secondary, rgba(0,0,0,.04)); }
    .dre-filter-chip.active { background:#4f46e5; color:#fff; border-color:#4f46e5; }

    .dre-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:1.5rem; }
    .dre-kpi { background:var(--bg-primary, #fff); border:1px solid var(--border-color, #e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; position:relative; overflow:hidden; }
    .dre-kpi::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--kpi-color, #4f46e5); }
    .dre-kpi__label { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted, #9ca3af); font-weight:600; }
    .dre-kpi__value { font-size:1.55rem; font-weight:700; color:var(--text-primary, #111827); line-height:1.15; margin:.3rem 0; font-variant-numeric:tabular-nums; }
    .dre-kpi__meta { font-size:.78rem; color:var(--text-muted, #6b7280); }
    .dre-kpi--rec  { --kpi-color:#10b981; }
    .dre-kpi--desp { --kpi-color:#ef4444; }
    .dre-kpi--luc  { --kpi-color:#4f46e5; }
    .dre-kpi--mar  { --kpi-color:#f59e0b; }

    .dre-layout { display:grid; grid-template-columns:minmax(0, 1.5fr) minmax(0, 1fr); gap:1.25rem; align-items:start; }
    @media (max-width: 992px) { .dre-layout { grid-template-columns:1fr; } }

    .dre-card { background:var(--bg-primary, #fff); border:1px solid var(--border-color, #e5e7eb); border-radius:12px; overflow:hidden; }
    .dre-card__header { padding:1rem 1.25rem; border-bottom:1px solid var(--border-color, #e5e7eb); display:flex; justify-content:space-between; align-items:center; }
    .dre-card__title { margin:0; font-size:1rem; font-weight:600; color:var(--text-primary, #111827); }
    .dre-card__subtitle { font-size:.78rem; color:var(--text-muted, #6b7280); }

    .dre-table { width:100%; border-collapse:collapse; font-variant-numeric:tabular-nums; }
    .dre-table th { text-align:left; padding:.7rem 1.25rem; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted, #9ca3af); font-weight:600; background:var(--bg-secondary, rgba(0,0,0,.02)); }
    .dre-table th.text-end, .dre-table td.text-end { text-align:right; }
    .dre-row td { padding:.55rem 1.25rem; border-bottom:1px solid rgba(0,0,0,.04); transition:background .15s; }
    .dre-row:hover td { background:var(--bg-hover, rgba(0,0,0,.025)); }

    .dre-row--group td { font-weight:600; font-size:.82rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted, #6b7280); padding-top:1rem; }
    .dre-row--sub td:first-child { padding-left:2.75rem; color:var(--text-primary, #374151); font-size:.88rem; }
    .dre-row--total td { font-weight:700; border-top:2px solid rgba(0,0,0,.1); background:var(--bg-secondary, rgba(0,0,0,.015)); font-size:.95rem; }
    .dre-row--result td { font-weight:700; font-size:1rem; border-top:2px solid #4f46e5; border-bottom:2px solid #4f46e5; background:rgba(79,70,229,.06); color:var(--text-primary, #111827); }
    .dre-row--final td { font-weight:800; font-size:1.05rem; border-top:3px solid var(--border-color, #10b981); background:rgba(16,185,129,.08); }
    .dre-row--final.negativo td { background:rgba(239,68,68,.08); border-top-color:#ef4444; }

    .dre-valor-neg { color:#ef4444; }
    .dre-valor-pos { color:#10b981; }

    .dre-margem-badge { display:inline-block; padding:.18rem .55rem; border-radius:999px; font-size:.74rem; font-weight:600; margin-left:.5rem; }
    .dre-margem-badge.pos { background:rgba(16,185,129,.12); color:#065f46; }
    .dre-margem-badge.neg { background:rgba(239,68,68,.12); color:#991b1b; }

    [data-bs-theme="dark"] .dre-card,
    [data-bs-theme="dark"] .dre-kpi { background:#1f2937; border-color:#374151; }
    [data-bs-theme="dark"] .dre-row--total td,
    [data-bs-theme="dark"] .dre-table th { background:rgba(255,255,255,.04); }
    [data-bs-theme="dark"] .dre-row:hover td { background:rgba(255,255,255,.04); }

    @media print {
        body * { visibility:hidden; }
        .dre-print, .dre-print * { visibility:visible; }
        .dre-print { position:absolute; left:0; top:0; width:100%; }
        .dre-toolbar, .dre-actions, .dre-card--chart, .sidebar { display:none !important; }
        .dre-card { border:1px solid #e5e7eb; box-shadow:none; page-break-inside:avoid; }
        .dre-row:hover td { background:transparent; }
    }

    .dre-chart-frame { height:320px; padding:1rem 1.25rem; }

    .dre-expand-btn { color:#4f46e5; text-decoration:none; border:none; background:transparent; }
    .dre-expand-btn:hover { color:#3730a3; }
    tr.dre-drill td { border-bottom:1px solid var(--border-color, #e5e7eb); }
</style>

<div class="container-fluid py-3 dre-print" style="color: var(--text-primary);">

    <div class="dre-toolbar">
        <div>
            <p class="text-muted mb-1 small text-uppercase"><i class="fas fa-chart-pie me-1"></i>Financeiro</p>
            <h1 class="h4 mb-1">Demonstrativo de Resultado — DRE</h1>
            <p class="text-muted mb-0 small">
                Regime de competencia · Periodo:
                <strong><?= htmlspecialchars(date('d/m/Y', strtotime($periodo['inicio']))) ?></strong>
                a
                <strong><?= htmlspecialchars(date('d/m/Y', strtotime($periodo['fim']))) ?></strong>
            </p>
        </div>
        <div class="dre-actions d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Imprimir
            </button>
            <a class="btn btn-outline-secondary btn-sm"
               href="<?= htmlspecialchars($baseUrl . '?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">
                <i class="fas fa-file-csv me-1"></i>Excel / CSV
            </a>
            <form method="post" action="<?= htmlspecialchars($baseUrl) ?>" style="display:inline">
                <input type="hidden" name="action" value="export_pdf">
                <input type="hidden" name="dre_data" value='<?= htmlspecialchars(json_encode($dre), ENT_QUOTES) ?>'>
                <input type="hidden" name="periodo" value="<?= htmlspecialchars(date('d/m/Y', strtotime($periodo['inicio'])) . ' a ' . date('d/m/Y', strtotime($periodo['fim']))) ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                </button>
            </form>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="dre-card mb-3 dre-actions">
        <div style="padding:1rem 1.25rem; display:flex; flex-wrap:wrap; gap:.75rem; align-items:end;">
            <div>
                <label class="form-label small mb-1">Inicio</label>
                <input type="date" name="data_inicio" value="<?= htmlspecialchars(date('Y-m-d', strtotime($dataInicioStr))) ?>" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">Fim</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars(date('Y-m-d', strtotime($dataFimStr))) ?>" class="form-control form-control-sm">
            </div>
            <div class="d-flex gap-1 flex-wrap">
                <?php
                $atalhos = [
                    ['mes',     'Mes atual',    date('Y-m-01'),                                date('Y-m-t')],
                    ['mes_ant', 'Mes anterior', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
                    ['tri',     'Trimestre',    date('Y-m-01', strtotime('first day of -2 month')), date('Y-m-t')],
                    ['sem',     'Semestre',     date('Y-m-01', strtotime('first day of -5 month')), date('Y-m-t')],
                    ['ano',     'Ano',          date('Y-01-01'),                                date('Y-12-31')],
                ];
                foreach ($atalhos as [$k, $lab, $ini, $fim]): ?>
                    <a class="dre-filter-chip <?= ($dataInicioStr === $ini && $dataFimStr === $fim) ? 'active' : '' ?>"
                       href="<?= htmlspecialchars($baseUrl . '?' . http_build_query(['data_inicio' => $ini, 'data_fim' => $fim])) ?>">
                        <?= htmlspecialchars($lab) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="fas fa-filter me-1"></i>Aplicar</button>
        </div>
    </form>

    <!-- KPIs -->
    <section class="dre-kpi-grid">
        <div class="dre-kpi dre-kpi--rec">
            <div class="dre-kpi__label">Receita Bruta</div>
            <div class="dre-kpi__value"><?= $brl($receitaBruta) ?></div>
            <div class="dre-kpi__meta">Liquida: <?= $brl($receitaLiquida) ?></div>
        </div>
        <div class="dre-kpi dre-kpi--desp">
            <div class="dre-kpi__label">Despesas Totais</div>
            <div class="dre-kpi__value"><?= $brl($despesasTotais) ?></div>
            <div class="dre-kpi__meta">CPV + Oper + Fin + Ded + Trib</div>
        </div>
        <div class="dre-kpi dre-kpi--luc">
            <div class="dre-kpi__label">Lucro Liquido</div>
            <div class="dre-kpi__value" style="color: <?= $lucroLiquido >= 0 ? '#10b981' : '#ef4444' ?>">
                <?= $brl($lucroLiquido) ?>
            </div>
            <div class="dre-kpi__meta">LAIR: <?= $brl((float)($blocos['09_lair']['valor'] ?? 0)) ?></div>
        </div>
        <div class="dre-kpi dre-kpi--mar">
            <div class="dre-kpi__label">Margem Liquida</div>
            <div class="dre-kpi__value" style="color: <?= $margemLiquida >= 0 ? '#10b981' : '#ef4444' ?>">
                <?= $pct($margemLiquida) ?>
            </div>
            <div class="dre-kpi__meta">Bruta <?= $pct($margemBruta) ?> · Op <?= $pct($margemOp) ?></div>
        </div>
    </section>

    <div class="dre-layout">
        <!-- DRE detalhada -->
        <article class="dre-card">
            <div class="dre-card__header">
                <div>
                    <h2 class="dre-card__title">Demonstrativo Detalhado</h2>
                    <p class="dre-card__subtitle mb-0">11 blocos · regime de competencia</p>
                </div>
                <span class="badge bg-secondary">R$</span>
            </div>
            <table class="dre-table">
                <thead>
                    <tr>
                        <th>Descricao</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end" style="width:110px;">Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Linhas da DRE — ordem contabil R6
                    $renderLinha = static function (string $tipo, string $desc, float $valor, float $receita = 0, bool $mostrarMargem = false, bool $neg = false, ?int $categoriaId = null) use ($brl, $pct): void {
                        $classMap = [
                            'grupo'  => 'dre-row dre-row--group',
                            'sub'    => 'dre-row dre-row--sub',
                            'total'  => 'dre-row dre-row--total',
                            'result' => 'dre-row dre-row--result',
                            'final'  => 'dre-row dre-row--final' . ($valor < 0 ? ' negativo' : ''),
                        ];
                        $cls = $classMap[$tipo] ?? 'dre-row';
                        $valorStr = $brl($neg ? -abs($valor) : $valor);
                        $valorCls = $neg ? 'dre-valor-neg' : ($valor >= 0 ? '' : 'dre-valor-neg');

                        echo '<tr class="' . $cls . '">';
                        echo '<td>';
                        if ($categoriaId !== null) {
                            echo '<button type="button" class="btn btn-sm btn-link p-0 me-2 dre-expand-btn" data-categoria="' . (int)$categoriaId . '" data-nome="' . htmlspecialchars($desc) . '" title="Ver movimentacoes desta categoria">';
                            echo '<i class="fas fa-plus-circle"></i>';
                            echo '</button>';
                        }
                        echo htmlspecialchars($desc) . '</td>';
                        echo '<td class="text-end ' . $valorCls . '">' . $valorStr . '</td>';
                        echo '<td class="text-end">';
                        if ($mostrarMargem && $receita > 0) {
                            $margem = ($valor / $receita) * 100;
                            $badge = $margem >= 0 ? 'pos' : 'neg';
                            echo '<span class="dre-margem-badge ' . $badge . '">' . $pct($margem) . '</span>';
                        }
                        echo '</td></tr>';
                    };

                    // (1) RECEITA BRUTA
                    $renderLinha('grupo', '(+) RECEITA BRUTA', $receitaBruta);
                    foreach ($blocos['01_receita_bruta']['detalhes'] ?? [] as $d) {
                        $renderLinha('sub', (string)$d['nome'], (float)$d['valor'], 0, false, false, (int)$d['categoria_id']);
                    }

                    // (2) DEDUCOES
                    if ($deducoes > 0 || !empty($blocos['02_deducoes']['detalhes'])) {
                        $renderLinha('grupo', '(-) DEDUCOES DA RECEITA', -$deducoes);
                        foreach ($blocos['02_deducoes']['detalhes'] ?? [] as $d) {
                            $renderLinha('sub', (string)$d['nome'], -abs((float)$d['valor']), 0, false, false, (int)$d['categoria_id']);
                        }
                    }

                    // (3) RECEITA LIQUIDA
                    $renderLinha('total', '(=) RECEITA LIQUIDA', $receitaLiquida, $receitaBruta, true);

                    // (4) CPV
                    if ($cpv > 0 || !empty($blocos['04_cpv']['detalhes'])) {
                        $renderLinha('grupo', '(-) CUSTOS (CPV / CMV)', -$cpv);
                        foreach ($blocos['04_cpv']['detalhes'] ?? [] as $d) {
                            $renderLinha('sub', (string)$d['nome'], -abs((float)$d['valor']), 0, false, false, (int)$d['categoria_id']);
                        }
                    }

                    // (5) LUCRO BRUTO
                    $renderLinha('total', '(=) LUCRO BRUTO', $lucroBruto, $receitaBruta, true);

                    // (6) DESPESAS OPERACIONAIS
                    if ($despesaOp > 0 || !empty($blocos['06_despesas_operacionais']['detalhes'])) {
                        $renderLinha('grupo', '(-) DESPESAS OPERACIONAIS', -$despesaOp);
                        foreach ($blocos['06_despesas_operacionais']['detalhes'] ?? [] as $d) {
                            $renderLinha('sub', (string)$d['nome'], -abs((float)$d['valor']), 0, false, false, (int)$d['categoria_id']);
                        }
                    }

                    // (7) RESULTADO OPERACIONAL
                    $renderLinha('total', '(=) RESULTADO OPERACIONAL', $resultadoOp, $receitaBruta, true);

                    // (8) RESULTADO FINANCEIRO
                    $resFin = (float)($blocos['08_resultado_financeiro']['valor'] ?? 0);
                    $recFin = (float)($blocos['08_resultado_financeiro']['receita_financeira'] ?? 0);
                    if (abs($resFin) > 0.001 || abs($recFin) > 0.001 || abs($despesaFin) > 0.001) {
                        $renderLinha('grupo', '(+/-) RESULTADO FINANCEIRO', $resFin);
                        if ($recFin > 0) $renderLinha('sub', 'Receitas Financeiras', $recFin);
                        foreach ($blocos['08_resultado_financeiro']['detalhes_despesa'] ?? [] as $d) {
                            $renderLinha('sub', (string)$d['nome'], -abs((float)$d['valor']), 0, false, false, (int)$d['categoria_id']);
                        }
                    }

                    // (9) LAIR
                    $lair = (float)($blocos['09_lair']['valor'] ?? 0);
                    $renderLinha('total', '(=) RESULTADO ANTES DOS TRIBUTOS', $lair, $receitaBruta, true);

                    // (10) TRIBUTOS
                    if ($tributos > 0 || !empty($blocos['10_tributos']['detalhes'])) {
                        $renderLinha('grupo', '(-) IRPJ / CSLL', -$tributos);
                        foreach ($blocos['10_tributos']['detalhes'] ?? [] as $d) {
                            $renderLinha('sub', (string)$d['nome'], -abs((float)$d['valor']), 0, false, false, (int)$d['categoria_id']);
                        }
                    }

                    // (11) LUCRO LIQUIDO
                    $renderLinha('final', '(=) LUCRO LIQUIDO DO PERIODO', $lucroLiquido, $receitaBruta, true);
                    ?>
                </tbody>
            </table>
        </article>

        <!-- Grafico waterfall + composicao -->
        <aside class="dre-card dre-card--chart">
            <div class="dre-card__header">
                <div>
                    <h2 class="dre-card__title">Composicao do Resultado</h2>
                    <p class="dre-card__subtitle mb-0">Cada bloco do DRE visualizado</p>
                </div>
                <i class="fas fa-chart-bar text-muted"></i>
            </div>
            <div class="dre-chart-frame">
                <canvas id="dreWaterfallChart" aria-label="Grafico waterfall do DRE"></canvas>
            </div>
            <?php if ($receitaBruta <= 0): ?>
                <div class="p-3">
                    <div class="alert alert-info mb-0 small">
                        <i class="fas fa-info-circle me-1"></i>
                        Nenhuma movimentacao DRE no periodo selecionado. Verifique se as vendas ja foram conferidas no gerencial.
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
    const ctx = document.getElementById('dreWaterfallChart');
    if (!ctx) return;
    const labels = <?= json_encode($chartData['labels'], JSON_UNESCAPED_UNICODE) ?>;
    const valores = <?= json_encode($chartData['valores'], JSON_NUMERIC_CHECK) ?>;
    const cores = valores.map(v => v >= 0 ? 'rgba(16,185,129,.85)' : 'rgba(239,68,68,.85)');
    const bordas = valores.map(v => v >= 0 ? '#10b981' : '#ef4444');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                data: valores,
                backgroundColor: cores,
                borderColor: bordas,
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 38,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 800, easing: 'easeInOutQuart' },
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' R$ ' + Number(ctx.parsed.x || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(148,163,184,.14)' },
                    ticks: {
                        callback: v => {
                            const n = Number(v);
                            if (Math.abs(n) >= 1000) return 'R$ ' + (n / 1000).toFixed(0) + 'k';
                            return 'R$ ' + n.toFixed(0);
                        },
                        font: { size: 11 }
                    }
                },
                y: { grid: { display: false }, ticks: { font: { size: 11 } } }
            }
        }
    });
})();

// Drill-down por CATEGORIA (+ ao lado de cada subitem: Vendas de Produtos, etc.)
(function(){
    const DETALHES_URL = '<?= htmlspecialchars($baseUrl) ?>';
    const INICIO = '<?= htmlspecialchars(date('Y-m-d', strtotime($periodo['inicio']))) ?>';
    const FIM    = '<?= htmlspecialchars(date('Y-m-d', strtotime($periodo['fim']))) ?>';

    function brl(v) { return (Number(v)||0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); }
    function formatDate(v) {
        const d = new Date(v);
        if (isNaN(d.getTime())) return v || '--';
        return d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }
    function escape(s) { return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]); }

    document.querySelectorAll('.dre-expand-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            const tr = btn.closest('tr');
            const catId = btn.dataset.categoria;
            const nome = btn.dataset.nome;
            const next = tr.nextElementSibling;

            if (next && next.dataset.drillCat === catId) {
                next.remove();
                btn.innerHTML = '<i class="fas fa-plus-circle"></i>';
                return;
            }

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const url = DETALHES_URL + '?action=detalhes&categoria_id=' + encodeURIComponent(catId)
                      + '&inicio=' + INICIO + '&fim=' + FIM;

            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r){
                    const ct = (r.headers.get('content-type') || '').toLowerCase();
                    if (!ct.includes('json')) {
                        return r.text().then(function(t){ throw new Error('Resposta inesperada (HTTP ' + r.status + '). Sessao pode ter expirado.'); });
                    }
                    return r.json();
                })
                .then(function(data){
                    btn.innerHTML = '<i class="fas fa-minus-circle"></i>';
                    const container = document.createElement('tr');
                    container.dataset.drillCat = catId;
                    container.className = 'dre-drill';
                    const td = document.createElement('td');
                    td.colSpan = 3;
                    td.style.padding = '0';
                    td.style.background = 'rgba(79,70,229,0.04)';

                    if (!data.ok) {
                        td.innerHTML = '<div class="p-3 text-center text-danger small">' + escape(data.erro || 'Falha ao carregar.') + '</div>';
                    } else if (!data.movimentacoes || data.movimentacoes.length === 0) {
                        td.innerHTML = '<div class="p-3 text-center text-muted small">Nenhuma movimentacao em ' + escape(nome) + ' no periodo.</div>';
                    } else {
                        const total = data.movimentacoes.reduce(function(a,m){ return a + Number(m.valor||0); }, 0);
                        let html = '<div style="padding:0.5rem 1.5rem;">';
                        html += '<div style="display:flex;justify-content:space-between;padding:0.4rem 0.6rem;background:rgba(79,70,229,0.08);border-radius:6px;font-weight:600;font-size:0.82rem;margin-bottom:0.35rem">';
                        html += '<span><i class="fas fa-folder-open me-1"></i>' + escape(nome) + ' <span class="text-muted">[' + data.movimentacoes.length + ' lancamento' + (data.movimentacoes.length===1?'':'s') + ']</span></span>';
                        html += '<span>' + brl(total) + '</span>';
                        html += '</div>';
                        html += '<table class="table table-sm mb-0" style="font-size:0.82rem">';
                        html += '<thead><tr class="text-muted"><th>Data</th><th>Descricao</th><th>Cliente</th><th>Forma</th><th>Venda</th><th class="text-end">Valor</th></tr></thead>';
                        html += '<tbody>';
                        data.movimentacoes.forEach(function(m){
                            html += '<tr>';
                            html += '<td>' + escape(formatDate(m.data)) + '</td>';
                            html += '<td>' + escape(m.descricao) + '</td>';
                            html += '<td>' + escape(m.cliente_nome || '—') + '</td>';
                            html += '<td>' + escape(m.forma_pagamento || '—') + '</td>';
                            html += '<td>' + (m.venda_numero ? '#' + m.venda_numero : '—') + '</td>';
                            html += '<td class="text-end" style="font-variant-numeric:tabular-nums">' + brl(m.valor) + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                        html += '</div>';
                        td.innerHTML = html;
                    }
                    container.appendChild(td);
                    tr.parentNode.insertBefore(container, tr.nextSibling);
                })
                .catch(function(err){
                    btn.innerHTML = '<i class="fas fa-plus-circle"></i>';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon:'error',
                            title:'Falha ao carregar detalhes',
                            text: err.message || 'Erro desconhecido.',
                            buttonsStyling:false,
                            customClass:{confirmButton:'btn btn-danger'}
                        });
                    }
                });
        });
    });
})();
</script>
