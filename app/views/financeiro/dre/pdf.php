<?php
// $dreData e $periodo vem do controller (exportarPdf)
$blocos = $dreData['blocos'] ?? [];
$brl = static fn($v) => ($v < 0 ? '(' : '') . 'R$ ' . number_format(abs((float)$v), 2, ',', '.') . ($v < 0 ? ')' : '');
$pct = static fn($v) => number_format((float)$v, 1, ',', '.') . '%';
$receitaBruta = (float)($blocos['01_receita_bruta']['valor'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DRE - <?= htmlspecialchars($periodo ?? '') ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans', Arial, sans-serif; font-size:10pt; color:#333; padding:20px; }
        .header { text-align:center; margin-bottom:20px; padding-bottom:15px; border-bottom:3px solid #4f46e5; }
        .header h1 { font-size:18pt; color:#4f46e5; margin-bottom:4px; }
        .header p { font-size:10pt; color:#6b7280; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:#f3f4f6; text-align:left; padding:8px 12px; font-size:8pt; text-transform:uppercase; color:#6b7280; border-bottom:2px solid #e5e7eb; }
        th.right, td.right { text-align:right; }
        td { padding:6px 12px; border-bottom:1px solid #f3f4f6; font-variant-numeric:tabular-nums; }
        .row-group td { font-weight:bold; text-transform:uppercase; font-size:9pt; color:#6b7280; padding-top:12px; }
        .row-sub td:first-child { padding-left:28px; color:#374151; }
        .row-total td { font-weight:bold; background:#f9fafb; border-top:1px solid #d1d5db; }
        .row-result td { font-weight:bold; background:rgba(79,70,229,.08); color:#111; border-top:2px solid #4f46e5; border-bottom:2px solid #4f46e5; }
        .row-final td { font-weight:bold; font-size:11pt; background:rgba(16,185,129,.1); border-top:3px solid #10b981; border-bottom:3px solid #10b981; }
        .negativo { color:#ef4444; }
        .footer { margin-top:30px; padding-top:15px; border-top:1px solid #e5e7eb; font-size:8pt; color:#9ca3af; text-align:center; }
        .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:8pt; font-weight:bold; margin-left:6px; }
        .badge.pos { background:rgba(16,185,129,.15); color:#065f46; }
        .badge.neg { background:rgba(239,68,68,.15); color:#991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Demonstrativo de Resultado — DRE</h1>
        <p>Periodo: <?= htmlspecialchars($periodo ?? '') ?> · Regime de competencia</p>
    </div>

    <table>
        <thead>
            <tr><th>Descricao</th><th class="right">Valor</th><th class="right" style="width:90px;">Margem</th></tr>
        </thead>
        <tbody>
        <?php
        $linha = static function (string $cls, string $desc, float $valor, bool $margem = false) use ($brl, $pct, $receitaBruta): void {
            $negCls = $valor < 0 ? 'negativo' : '';
            echo '<tr class="' . $cls . '">';
            echo '<td>' . htmlspecialchars($desc) . '</td>';
            echo '<td class="right ' . $negCls . '">' . $brl($valor) . '</td>';
            echo '<td class="right">';
            if ($margem && $receitaBruta > 0) {
                $m = ($valor / $receitaBruta) * 100;
                $badge = $m >= 0 ? 'pos' : 'neg';
                echo '<span class="badge ' . $badge . '">' . $pct($m) . '</span>';
            }
            echo '</td></tr>';
        };

        $blocosOrdem = [
            '01_receita_bruta' => 'row-group',
            '02_deducoes' => 'row-group',
            '03_receita_liquida' => 'row-total',
            '04_cpv' => 'row-group',
            '05_lucro_bruto' => 'row-total',
            '06_despesas_operacionais' => 'row-group',
            '07_resultado_operacional' => 'row-result',
            '08_resultado_financeiro' => 'row-group',
            '09_lair' => 'row-total',
            '10_tributos' => 'row-group',
            '11_lucro_liquido' => 'row-final',
        ];

        foreach ($blocosOrdem as $chave => $cls) {
            $b = $blocos[$chave] ?? null;
            if (!$b) continue;
            $valor = (float)($b['valor'] ?? 0);
            $label = (string)($b['label'] ?? $chave);
            $mostrarMargem = in_array($cls, ['row-total','row-result','row-final'], true);

            $valorExib = $valor;
            if (in_array($chave, ['02_deducoes','04_cpv','06_despesas_operacionais','10_tributos'], true)) {
                $valorExib = -abs($valor);
            }
            $linha($cls, $label, $valorExib, $mostrarMargem);

            foreach ($b['detalhes'] ?? [] as $d) {
                $v = (float)($d['valor'] ?? 0);
                if (in_array($chave, ['02_deducoes','04_cpv','06_despesas_operacionais','10_tributos'], true)) {
                    $v = -abs($v);
                }
                $linha('row-sub', (string)$d['nome'], $v, false);
            }
            if ($chave === '08_resultado_financeiro') {
                foreach ($b['detalhes_despesa'] ?? [] as $d) {
                    $linha('row-sub', (string)$d['nome'], -abs((float)$d['valor']), false);
                }
            }
        }
        ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Sistema DM · DRE por regime de competencia · Gerado em <?= date('d/m/Y H:i') ?></p>
    </div>
</body>
</html>
