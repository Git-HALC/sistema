<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DRE - <?php echo $periodo; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4f46e5;
        }
        
        .header h1 {
            font-size: 20pt;
            color: #4f46e5;
            margin-bottom: 8px;
        }
        
        .header .periodo {
            font-size: 11pt;
            color: #666;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section-header {
            background: #4f46e5;
            color: white;
            padding: 8px 12px;
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .section-header.receita {
            background: #10b981;
        }
        
        .section-header.deducao {
            background: #f59e0b;
        }
        
        .section-header.cpv {
            background: #6b7280;
        }
        
        .section-header.despesa {
            background: #ef4444;
        }
        
        .section-header.tributo {
            background: #374151;
        }
        
        .section-header.lucro {
            background: #06b6d4;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        table td {
            padding: 6px 12px;
        }
        
        table td.label {
            width: 70%;
        }
        
        table td.value {
            width: 30%;
            text-align: right;
            font-weight: 600;
        }
        
        .item {
            padding-left: 20px;
        }
        
        .total {
            background: #f9fafb;
            font-weight: bold;
            font-size: 11pt;
        }
        
        .total td {
            padding: 10px 12px;
        }
        
        .highlight {
            background: #dbeafe;
            font-weight: bold;
            font-size: 12pt;
        }
        
        .highlight td {
            padding: 12px 12px;
        }
        
        .positive {
            color: #10b981;
        }
        
        .negative {
            color: #ef4444;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
        }
        
        .summary-box {
            background: #f9fafb;
            border: 1px solid #d1d5db;
            padding: 15px;
            margin-top: 20px;
        }
        
        .summary-box h3 {
            font-size: 11pt;
            margin-bottom: 10px;
            color: #4f46e5;
        }
        
        .summary-grid {
            display: table;
            width: 100%;
        }
        
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
        }
        
        .summary-item .label {
            font-size: 8pt;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .summary-item .value {
            font-size: 11pt;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Demonstração de Resultado do Exercício</h1>
        <div class="periodo"><?php echo $periodo; ?></div>
    </div>
    
    <?php 
    $fmt = function($valor) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    };
    ?>
    
    <!-- 1. RECEITA BRUTA -->
    <div class="section">
        <div class="section-header receita">1. RECEITA BRUTA</div>
        <table>
            <?php foreach (($dreData['receita_bruta']['detalhes'] ?? []) as $item): ?>
                <?php if ($item['valor'] > 0): ?>
                <tr>
                    <td class="label item"><?php echo htmlspecialchars($item['categoria']); ?></td>
                    <td class="value"><?php echo $fmt($item['valor']); ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="total">
                <td class="label">RECEITA BRUTA TOTAL</td>
                <td class="value"><?php echo $fmt($dreData['receita_bruta']['total']); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- 2. DEDUÇÕES -->
    <div class="section">
        <div class="section-header deducao">2. DEDUÇÕES DA RECEITA BRUTA</div>
        <table>
            <?php foreach (($dreData['deducoes']['detalhes'] ?? []) as $item): ?>
                <?php if ($item['valor'] > 0): ?>
                <tr>
                    <td class="label item"><?php echo htmlspecialchars($item['categoria']); ?></td>
                    <td class="value">(<?php echo $fmt($item['valor']); ?>)</td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="total">
                <td class="label">TOTAL DEDUÇÕES</td>
                <td class="value negative">(<?php echo $fmt($dreData['deducoes']['total']); ?>)</td>
            </tr>
        </table>
    </div>
    
    <!-- 3. RECEITA LÍQUIDA -->
    <div class="section">
        <table>
            <tr class="highlight">
                <td class="label">3. RECEITA LÍQUIDA</td>
                <td class="value positive"><?php echo $fmt($dreData['receita_liquida']); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- 4. CPV/CMV -->
    <div class="section">
        <div class="section-header cpv">4. CUSTO DOS PRODUTOS/MERCADORIAS VENDIDAS (CPV/CMV)</div>
        <table>
            <?php foreach (($dreData['cpv_cmv']['detalhes'] ?? []) as $item): ?>
                <?php if ($item['valor'] > 0): ?>
                <tr>
                    <td class="label item"><?php echo htmlspecialchars($item['categoria']); ?></td>
                    <td class="value">(<?php echo $fmt($item['valor']); ?>)</td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="total">
                <td class="label">TOTAL CPV/CMV</td>
                <td class="value negative">(<?php echo $fmt($dreData['cpv_cmv']['total']); ?>)</td>
            </tr>
        </table>
    </div>
    
    <!-- 5. LUCRO BRUTO -->
    <div class="section">
        <table>
            <tr class="highlight">
                <td class="label">5. LUCRO BRUTO</td>
                <td class="value positive"><?php echo $fmt($dreData['lucro_bruto']); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- 6. DESPESAS OPERACIONAIS -->
    <div class="section">
        <div class="section-header despesa">6. DESPESAS OPERACIONAIS</div>
        <table>
            <?php foreach (($dreData['despesas_operacionais']['detalhes'] ?? []) as $item): ?>
                <?php if ($item['valor'] > 0): ?>
                <tr>
                    <td class="label item"><?php echo htmlspecialchars($item['categoria']); ?></td>
                    <td class="value">(<?php echo $fmt($item['valor']); ?>)</td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="total">
                <td class="label">TOTAL DESPESAS OPERACIONAIS</td>
                <td class="value negative">(<?php echo $fmt($dreData['despesas_operacionais']['total']); ?>)</td>
            </tr>
        </table>
    </div>
    
    <!-- 7. LAIR -->
    <div class="section">
        <table>
            <tr class="highlight">
                <td class="label">7. LUCRO ANTES DO IR E CSLL (LAIR)</td>
                <td class="value <?php echo $dreData['lair'] >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $fmt($dreData['lair']); ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- 8. TRIBUTOS -->
    <div class="section">
        <div class="section-header tributo">8. TRIBUTOS SOBRE O LUCRO</div>
        <table>
            <?php if (($dreData['tributos_sobre_lucro']['irpj'] ?? 0) > 0): ?>
            <tr>
                <td class="label item">IRPJ (15%)</td>
                <td class="value">(<?php echo $fmt($dreData['tributos_sobre_lucro']['irpj']); ?>)</td>
            </tr>
            <?php endif; ?>
            <?php if (($dreData['tributos_sobre_lucro']['csll'] ?? 0) > 0): ?>
            <tr>
                <td class="label item">CSLL (9%)</td>
                <td class="value">(<?php echo $fmt($dreData['tributos_sobre_lucro']['csll']); ?>)</td>
            </tr>
            <?php endif; ?>
            <?php foreach (($dreData['tributos_sobre_lucro']['detalhes'] ?? []) as $item): ?>
                <?php if ($item['valor'] > 0): ?>
                <tr>
                    <td class="label item"><?php echo htmlspecialchars($item['categoria']); ?></td>
                    <td class="value">(<?php echo $fmt($item['valor']); ?>)</td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="total">
                <td class="label">TOTAL TRIBUTOS</td>
                <td class="value negative">(<?php echo $fmt($dreData['tributos_sobre_lucro']['total']); ?>)</td>
            </tr>
        </table>
    </div>
    
    <!-- 9. LUCRO LÍQUIDO -->
    <div class="section">
        <table>
            <tr class="highlight">
                <td class="label">9. LUCRO LÍQUIDO DO EXERCÍCIO</td>
                <td class="value <?php echo $dreData['lucro_liquido'] >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $fmt($dreData['lucro_liquido']); ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- RESUMO PERCENTUAL -->
    <div class="summary-box">
        <h3>RESUMO DE INDICADORES</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="label">Margem Bruta</div>
                <div class="value">
                    <?php 
                    $rb = $dreData['receita_bruta']['total'];
                    echo $rb > 0 ? number_format(($dreData['lucro_bruto'] / $rb) * 100, 1, ',', '.') . '%' : '0,0%';
                    ?>
                </div>
            </div>
            <div class="summary-item">
                <div class="label">Margem LAIR</div>
                <div class="value">
                    <?php 
                    echo $rb > 0 ? number_format(($dreData['lair'] / $rb) * 100, 1, ',', '.') . '%' : '0,0%';
                    ?>
                </div>
            </div>
            <div class="summary-item">
                <div class="label">Margem Líquida</div>
                <div class="value">
                    <?php 
                    echo $rb > 0 ? number_format(($dreData['lucro_liquido'] / $rb) * 100, 1, ',', '.') . '%' : '0,0%';
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>Relatório gerado em <?php echo date('d/m/Y \à\s H:i'); ?></p>
        <p>Sistema DM - DRE</p>
    </div>
</body>
</html>
