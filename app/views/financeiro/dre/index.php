<style>
    :root {
        --dre-primary: #4f46e5;
        --dre-success: #10b981;
        --dre-warning: #f59e0b;
        --dre-danger: #ef4444;
        --dre-info: #06b6d4;
        --dre-dark: #1f2937;
        --dre-light: #f9fafb;
    }

    html[data-bs-theme="dark"] {
        --dre-dark: #1f2937;
        --dre-light: #111827;
        background-color: #111827;
    }

    html[data-bs-theme="dark"] body {
        background-color: #111827;
    }

    html[data-bs-theme="dark"] .container-fluid {
        background-color: #111827;
    }

    .dre-header {
        background: linear-gradient(135deg, var(--dre-primary) 0%, #7c3aed 100%);
        color: white;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(79, 70, 229, 0.2);
    }

    .dre-header h1 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
    }

    .dre-header .periodo {
        font-size: 0.95rem;
        opacity: 0.9;
        margin-top: 0.5rem;
    }

    .dre-filters {
        background: rgb(243, 244, 246);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    html[data-bs-theme="dark"] .dre-filters {
        background: rgb(31, 41, 55);
    }

    .dre-filters .form-select, .dre-filters .form-label {
        border-radius: 8px;
    }

    .dre-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .dre-kpi-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        border-left: 5px solid transparent;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    html[data-bs-theme="dark"] .dre-kpi-card {
        background: rgb(31, 41, 55);
    }

    .dre-kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }

    .dre-kpi-card.receita {
        border-left-color: var(--dre-success);
    }

    .dre-kpi-card.despesa {
        border-left-color: var(--dre-danger);
    }

    .dre-kpi-card.lucro {
        border-left-color: var(--dre-info);
    }

    .dre-kpi-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .dre-kpi-card.receita .dre-kpi-icon {
        background: linear-gradient(135deg, var(--dre-success), #059669);
    }

    .dre-kpi-card.despesa .dre-kpi-icon {
        background: linear-gradient(135deg, var(--dre-danger), #dc2626);
    }

    .dre-kpi-card.lucro .dre-kpi-icon {
        background: linear-gradient(135deg, var(--dre-info), #0891b2);
    }

    .dre-kpi-label {
        font-size: 0.85rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    html[data-bs-theme="dark"] .dre-kpi-label {
        color: #9ca3af;
    }

    .dre-kpi-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--dre-dark);
    }

    html[data-bs-theme="dark"] .dre-kpi-value {
        color: #f3f4f6;
    }

    .dre-kpi-change {
        font-size: 0.8rem;
        margin-top: 0.5rem;
        opacity: 0.7;
    }

    .dre-section {
        background: white;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        overflow: hidden;
    }

    html[data-bs-theme="dark"] .dre-section {
        background: rgb(31, 41, 55);
    }

    .dre-section-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(124, 58, 237, 0.05));
        border-bottom: 2px solid rgba(79, 70, 229, 0.1);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    html[data-bs-theme="dark"] .dre-section-header {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(124, 58, 237, 0.1));
    }

    .dre-section-icon {
        font-size: 1.5rem;
        width: 45px;
        height: 45px;
        border-radius: 10px;
        background: var(--dre-primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .dre-section-title {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--dre-dark);
    }

    html[data-bs-theme="dark"] .dre-section-title {
        color: #f3f4f6;
    }

    .dre-section-body {
        padding: 0;
    }

    .dre-items {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .dre-item {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s ease;
    }

    html[data-bs-theme="dark"] .dre-item {
        border-bottom-color: rgba(255, 255, 255, 0.05);
    }

    .dre-item:hover {
        background: rgba(79, 70, 229, 0.02);
    }

    .dre-item:last-child {
        border-bottom: none;
    }

    .dre-item-label {
        font-weight: 500;
        color: #374151;
        flex: 1;
    }

    html[data-bs-theme="dark"] .dre-item-label {
        color: #d1d5db;
    }

    .dre-item-value {
        font-weight: 600;
        color: var(--dre-dark);
        text-align: right;
        min-width: 150px;
    }

    html[data-bs-theme="dark"] .dre-item-value {
        color: #f3f4f6;
    }

    .dre-item.total {
        background: rgba(79, 70, 229, 0.08);
        font-weight: 700;
        padding: 1.25rem 1.5rem;
    }

    html[data-bs-theme="dark"] .dre-item.total {
        background: rgba(79, 70, 229, 0.2);
    }

    .dre-item.highlight {
        background: rgba(16, 185, 129, 0.08);
    }

    html[data-bs-theme="dark"] .dre-item.highlight {
        background: rgba(16, 185, 129, 0.15);
    }

    .dre-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
        padding: 1.5rem;
        background: rgba(79, 70, 229, 0.05);
        border-radius: 8px;
    }

    html[data-bs-theme="dark"] .dre-summary-grid {
        background: rgba(79, 70, 229, 0.1);
    }

    .dre-summary-item {
        text-align: center;
    }

    .dre-summary-label {
        font-size: 0.8rem;
        color: #6b7280;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    html[data-bs-theme="dark"] .dre-summary-label {
        color: #9ca3af;
    }

    .dre-summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dre-primary);
    }

    .dre-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }

    .dre-actions .btn {
        border-radius: 8px;
        padding: 0.6rem 1.25rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .dre-actions .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
    }
</style>

<div class="container-fluid">
    <!-- Mensagens -->
    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <!-- Cabeçalho -->
    <div class="dre-header d-flex justify-content-between align-items-start">
        <div>
            <h1><?php echo $titulo; ?></h1>
            <?php if ($dre && isset($dre['periodo'])): ?>
                <div class="periodo">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('d/m/Y', strtotime($dre['periodo']['inicio'])); ?> até <?php echo date('d/m/Y', strtotime($dre['periodo']['fim'])); ?>
                </div>
            <?php endif; ?>
        </div>
        <a href="/sistema_dm/public/admin/financeiro/dashboard.php" class="btn btn-light btn-sm">
            <i class="fas fa-arrow-left me-2"></i> Voltar
        </a>
    </div>

    <!-- Filtros -->
    <div class="dre-filters">
        <form method="get" action="" class="row g-3">
            <div class="col-md-3">
                <label for="dreMesSelect" class="form-label fw-bold">Mês</label>
                <select class="form-select" id="dreMesSelect" name="mes">
                    <?php 
                    $meses = [
                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                    ];
                    for ($m = 1; $m <= 12; $m++): 
                    ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>>
                            <?php echo $meses[$m]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="dreAnoSelect" class="form-label fw-bold">Ano</label>
                <select class="form-select" id="dreAnoSelect" name="ano">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $ano ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-6 d-flex align-items-end gap-2 dre-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Gerar DRE
                </button>
                <button type="button" class="btn btn-success" onclick="exportToCSV()">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </form>
    </div>

    <!-- KPIs Principais -->
    <?php if ($dre && is_array($dre)): ?>
    <div class="dre-kpi-grid">
        <!-- Receita Bruta -->
        <div class="dre-kpi-card receita">
            <div class="dre-kpi-icon">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div>
                <div class="dre-kpi-label">Receita Bruta</div>
                <div class="dre-kpi-value">R$ <?php echo number_format($dre['receita_bruta']['total'], 2, ',', '.'); ?></div>
            </div>
        </div>

        <!-- CPV/CMV -->
        <div class="dre-kpi-card despesa">
            <div class="dre-kpi-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <div>
                <div class="dre-kpi-label">CPV/CMV</div>
                <div class="dre-kpi-value">R$ <?php echo number_format($dre['cpv_cmv']['total'], 2, ',', '.'); ?></div>
            </div>
        </div>

        <!-- Lucro Bruto -->
        <div class="dre-kpi-card lucro">
            <div class="dre-kpi-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <div class="dre-kpi-label">Lucro Bruto</div>
                <div class="dre-kpi-value">R$ <?php echo number_format($dre['lucro_bruto'], 2, ',', '.'); ?></div>
            </div>
        </div>

        <!-- Lucro Líquido -->
        <div class="dre-kpi-card <?php echo $dre['lucro_liquido'] >= 0 ? 'lucro' : 'despesa'; ?>">
            <div class="dre-kpi-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div>
                <div class="dre-kpi-label">Lucro Líquido</div>
                <div class="dre-kpi-value">R$ <?php echo number_format($dre['lucro_liquido'], 2, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detalhamento Completo -->
    <?php if ($dre && is_array($dre)): ?>

    <!-- 1. RECEITA BRUTA -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-arrow-up"></i>
            </div>
            <h2 class="dre-section-title">1. Receita Bruta</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <?php if (isset($dre['receita_bruta']['detalhes']) && !empty($dre['receita_bruta']['detalhes'])): ?>
                    <?php foreach ($dre['receita_bruta']['detalhes'] as $receita): ?>
                        <?php if ($receita['valor'] > 0): ?>
                        <li class="dre-item">
                            <span class="dre-item-label"><?php echo htmlspecialchars($receita['categoria']); ?></span>
                            <span class="dre-item-value text-success">R$ <?php echo number_format($receita['valor'], 2, ',', '.'); ?></span>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="dre-item">
                        <span class="dre-item-label text-muted" style="font-style: italic;">Nenhuma receita registrada no período</span>
                        <span class="dre-item-value">R$ 0,00</span>
                    </li>
                <?php endif; ?>
                <li class="dre-item total text-success">
                    <span class="dre-item-label">Total Receita Bruta</span>
                    <span class="dre-item-value">R$ <?php echo number_format($dre['receita_bruta']['total'], 2, ',', '.'); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 2. DEDUÇÕES -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-minus"></i>
            </div>
            <h2 class="dre-section-title">2. Deduções</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <?php if (isset($dre['deducoes']['detalhes']) && !empty($dre['deducoes']['detalhes'])): ?>
                    <?php foreach ($dre['deducoes']['detalhes'] as $deducao): ?>
                        <?php if ($deducao['valor'] > 0): ?>
                        <li class="dre-item">
                            <span class="dre-item-label"><?php echo htmlspecialchars($deducao['categoria']); ?></span>
                            <span class="dre-item-value text-danger">(R$ <?php echo number_format($deducao['valor'], 2, ',', '.'); ?>)</span>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li class="dre-item total text-danger">
                    <span class="dre-item-label">Total Deduções</span>
                    <span class="dre-item-value">(R$ <?php echo number_format($dre['deducoes']['total'], 2, ',', '.'); ?>)</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 3. RECEITA LÍQUIDA -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-balance-scale"></i>
            </div>
            <h2 class="dre-section-title">3. Receita Líquida</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <li class="dre-item highlight total">
                    <span class="dre-item-label">Receita Líquida</span>
                    <span class="dre-item-value text-info" style="font-size: 1.5rem;">R$ <?php echo number_format($dre['receita_liquida'], 2, ',', '.'); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 4. CPV/CMV -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <h2 class="dre-section-title">4. Custo dos Produtos/Mercadorias Vendidas (CPV/CMV)</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <?php if (isset($dre['cpv_cmv']['detalhes']) && !empty($dre['cpv_cmv']['detalhes'])): ?>
                    <?php foreach ($dre['cpv_cmv']['detalhes'] as $cpv): ?>
                        <?php if ($cpv['valor'] > 0): ?>
                        <li class="dre-item">
                            <span class="dre-item-label"><?php echo htmlspecialchars($cpv['categoria']); ?></span>
                            <span class="dre-item-value text-danger">(R$ <?php echo number_format($cpv['valor'], 2, ',', '.'); ?>)</span>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li class="dre-item total text-danger">
                    <span class="dre-item-label">Total CPV/CMV</span>
                    <span class="dre-item-value">(R$ <?php echo number_format($dre['cpv_cmv']['total'], 2, ',', '.'); ?>)</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 5. LUCRO BRUTO -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h2 class="dre-section-title">5. Lucro Bruto</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <li class="dre-item highlight total">
                    <span class="dre-item-label">Lucro Bruto</span>
                    <span class="dre-item-value text-success" style="font-size: 1.5rem;">R$ <?php echo number_format($dre['lucro_bruto'], 2, ',', '.'); ?></span>
                </li>
                <li class="dre-item">
                    <span class="dre-item-label">Margem do Lucro Bruto</span>
                    <span class="dre-item-value"><?php 
                        $percentual = $dre['receita_bruta']['total'] > 0 ? ($dre['lucro_bruto'] / $dre['receita_bruta']['total']) * 100 : 0;
                        echo number_format($percentual, 1, ',', '.') . '%'; 
                    ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 6. DESPESAS OPERACIONAIS -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h2 class="dre-section-title">6. Despesas Operacionais</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <?php if (isset($dre['despesas_operacionais']['detalhes']) && !empty($dre['despesas_operacionais']['detalhes'])): ?>
                    <?php foreach ($dre['despesas_operacionais']['detalhes'] as $despesa): ?>
                        <?php if ($despesa['valor'] > 0): ?>
                        <li class="dre-item">
                            <span class="dre-item-label"><?php echo htmlspecialchars($despesa['categoria']); ?></span>
                            <span class="dre-item-value text-danger">(R$ <?php echo number_format($despesa['valor'], 2, ',', '.'); ?>)</span>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li class="dre-item total text-danger">
                    <span class="dre-item-label">Total Despesas Operacionais</span>
                    <span class="dre-item-value">(R$ <?php echo number_format($dre['despesas_operacionais']['total'], 2, ',', '.'); ?>)</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 7. LAIR -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-coins"></i>
            </div>
            <h2 class="dre-section-title">7. Lucro Antes dos Impostos (LAIR)</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <li class="dre-item highlight total">
                    <span class="dre-item-label">LAIR</span>
                    <span class="dre-item-value text-info" style="font-size: 1.5rem;">R$ <?php echo number_format($dre['lair'], 2, ',', '.'); ?></span>
                </li>
                <li class="dre-item">
                    <span class="dre-item-label">Margem LAIR</span>
                    <span class="dre-item-value"><?php 
                        $percentual = $dre['receita_bruta']['total'] > 0 ? ($dre['lair'] / $dre['receita_bruta']['total']) * 100 : 0;
                        echo number_format($percentual, 1, ',', '.') . '%'; 
                    ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 8. TRIBUTOS -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-university"></i>
            </div>
            <h2 class="dre-section-title">8. Tributos sobre o Lucro</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <?php if (isset($dre['tributos_sobre_lucro']['irpj']) && $dre['tributos_sobre_lucro']['irpj'] > 0): ?>
                <li class="dre-item">
                    <span class="dre-item-label">IRPJ (15%)</span>
                    <span class="dre-item-value text-danger">(R$ <?php echo number_format($dre['tributos_sobre_lucro']['irpj'], 2, ',', '.'); ?>)</span>
                </li>
                <?php endif; ?>
                <?php if (isset($dre['tributos_sobre_lucro']['csll']) && $dre['tributos_sobre_lucro']['csll'] > 0): ?>
                <li class="dre-item">
                    <span class="dre-item-label">CSLL (9%)</span>
                    <span class="dre-item-value text-danger">(R$ <?php echo number_format($dre['tributos_sobre_lucro']['csll'], 2, ',', '.'); ?>)</span>
                </li>
                <?php endif; ?>
                <li class="dre-item total text-danger">
                    <span class="dre-item-label">Total Tributos</span>
                    <span class="dre-item-value">(R$ <?php echo number_format($dre['tributos_sobre_lucro']['total'], 2, ',', '.'); ?>)</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 9. LUCRO LÍQUIDO -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <h2 class="dre-section-title">9. Lucro Líquido do Exercício</h2>
        </div>
        <div class="dre-section-body">
            <ul class="dre-items">
                <li class="dre-item highlight total <?php echo $dre['lucro_liquido'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <span class="dre-item-label">Lucro Líquido</span>
                    <span class="dre-item-value" style="font-size: 1.75rem;">R$ <?php echo number_format($dre['lucro_liquido'], 2, ',', '.'); ?></span>
                </li>
                <li class="dre-item">
                    <span class="dre-item-label">Margem Líquida</span>
                    <span class="dre-item-value"><?php 
                        $percentual = $dre['receita_bruta']['total'] > 0 ? ($dre['lucro_liquido'] / $dre['receita_bruta']['total']) * 100 : 0;
                        echo number_format($percentual, 1, ',', '.') . '%'; 
                    ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- RESUMO ANALÍTICO -->
    <div class="dre-section">
        <div class="dre-section-header">
            <div class="dre-section-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h2 class="dre-section-title">Resumo Analítico</h2>
        </div>
        <div class="dre-section-body">
            <div class="dre-summary-grid">
                <div class="dre-summary-item">
                    <div class="dre-summary-label">Receita Bruta</div>
                    <div class="dre-summary-value">R$ <?php echo number_format($dre['receita_bruta']['total'], 2, ',', '.'); ?></div>
                </div>
                <div class="dre-summary-item">
                    <div class="dre-summary-label">Margem Lucro Bruto</div>
                    <div class="dre-summary-value"><?php 
                        $percentual = $dre['receita_bruta']['total'] > 0 ? ($dre['lucro_bruto'] / $dre['receita_bruta']['total']) * 100 : 0;
                        echo number_format($percentual, 1, ',', '.') . '%'; 
                    ?></div>
                </div>
                <div class="dre-summary-item">
                    <div class="dre-summary-label">Margem LAIR</div>
                    <div class="dre-summary-value"><?php 
                        $percentual = $dre['receita_bruta']['total'] > 0 ? ($dre['lair'] / $dre['receita_bruta']['total']) * 100 : 0;
                        echo number_format($percentual, 1, ',', '.') . '%'; 
                    ?></div>
                </div>
                <div class="dre-summary-item">
                    <div class="dre-summary-label">Margem Líquida</div>
                    <div class="dre-summary-value" style="color: <?php echo $dre['lucro_liquido'] >= 0 ? 'var(--dre-success)' : 'var(--dre-danger)'; ?>;">
                        <?php 
                            $percentual = $dre['receita_bruta']['total'] > 0 ? ($dre['lucro_liquido'] / $dre['receita_bruta']['total']) * 100 : 0;
                            echo number_format($percentual, 1, ',', '.') . '%'; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
        <div class="alert alert-info" style="border-radius: 12px; padding: 2rem;">
            <i class="fas fa-info-circle me-2" style="font-size: 1.2rem;"></i>
            <strong>Nenhuma DRE gerada</strong> - Selecione um período e clique em "Gerar DRE" para visualizar a demonstração.
        </div>
    <?php endif; ?>

</div>


<script>
// Auto-enviar formulário ao mudar os selects
document.addEventListener('DOMContentLoaded', function() {
    const mesSelect = document.getElementById('dreMesSelect');
    const anoSelect = document.getElementById('dreAnoSelect');
    
    if (mesSelect && anoSelect) {
        mesSelect.addEventListener('change', function() {
            this.form.submit();
        });
        
        anoSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }
});

// Função para exportar DRE para PDF
function exportToPDF() {
    const dreData = <?php echo json_encode($dre ?? null); ?>;
    if (!dreData) {
        alert('Selecione um período e gere a DRE antes de exportar.');
        return;
    }
    
    // Criar formulário para envio ao servidor
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/sistema_dm/public/admin/financeiro/dre.php';
    
    // Adicionar ação
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'export_pdf';
    form.appendChild(actionInput);
    
    // Adicionar dados da DRE
    const dreInput = document.createElement('input');
    dreInput.type = 'hidden';
    dreInput.name = 'dre_data';
    dreInput.value = JSON.stringify(dreData);
    form.appendChild(dreInput);
    
    // Adicionar período
    const periodoInput = document.createElement('input');
    periodoInput.type = 'hidden';
    periodoInput.name = 'periodo';
    periodoInput.value = dreData.periodo.inicio + ' a ' + dreData.periodo.fim;
    form.appendChild(periodoInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Função para imprimir apenas a DRE
function printDRE() {
    const dreData = <?php echo json_encode($dre ?? null); ?>;
    if (!dreData) {
        alert('Selecione um período e gere a DRE antes de imprimir.');
        return;
    }
    
    // Criar janela de impressão apenas com a DRE
    const printWindow = window.open('', '_blank');
    
    let html = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>DRE - ${dreData.periodo.inicio} a ${dreData.periodo.fim}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; text-align: center; }
            .periodo { text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-end { text-align: right; }
            .table-success { background-color: #d4edda; }
            .table-warning { background-color: #fff3cd; }
            .table-info { background-color: #d1ecf1; }
            .table-danger { background-color: #f8d7da; }
            .table-secondary { background-color: #e2e3e5; }
            .table-dark { background-color: #343a40; color: white; }
            .table-light { background-color: #f8f9fa; }
            strong { font-weight: bold; }
            @media print {
                body { margin: 10px; }
                table { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <h1>Demonstração de Resultado do Exercício</h1>
        <div class="periodo">Período: ${dreData.periodo.inicio} a ${dreData.periodo.fim}</div>
        <table>
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="text-end">Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    // Adicionar dados da DRE
    html += `
                <tr class="table-success">
                    <td colspan="2"><strong>1. RECEITA BRUTA</strong></td>
                </tr>
    `;
    
    if (dreData.receita_bruta.detalhes && dreData.receita_bruta.detalhes.length > 0) {
        dreData.receita_bruta.detalhes.forEach(item => {
            if (item.valor > 0) {
                html += `<tr><td style="padding-left: 30px;">${item.categoria}</td><td class="text-end">${item.valor.toFixed(2).replace('.', ',')}</td></tr>`;
            }
        });
    }
    
    html += `<tr class="table-success"><td><strong>Receita Bruta Total</strong></td><td class="text-end"><strong>R$ ${dreData.receita_bruta.total.toFixed(2).replace('.', ',')}</strong></td></tr>`;
    
    // Continuar com outras seções...
    html += `
            </tbody>
        </table>
    </body>
    </html>`;
    
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
function exportToCSV() {
    const dreData = <?php echo json_encode($dre ?? null); ?>;
    if (!dreData) {
        alert('Selecione um período e gere a DRE antes de exportar.');
        return;
    }
    
    let csv = 'Demonstração de Resultado do Exercício (DRE)\n';
    csv += 'Período: ' + dreData.periodo.inicio + ' a ' + dreData.periodo.fim + '\n\n';
    csv += 'Descrição;Valor (R$)\n';
    
    // 1. Receita Bruta
    csv += '1. RECEITA BRUTA;\n';
    if (dreData.receita_bruta.detalhes && dreData.receita_bruta.detalhes.length > 0) {
        dreData.receita_bruta.detalhes.forEach(item => {
            if (item.valor > 0) {
                csv += `  ${item.categoria};${item.valor.toFixed(2)}\n`;
            }
        });
    }
    csv += `Receita Bruta Total;${dreData.receita_bruta.total.toFixed(2)}\n\n`;
    
    // 2. Deduções
    csv += '2. DEDUÇÕES;\n';
    if (dreData.deducoes.detalhes && dreData.deducoes.detalhes.length > 0) {
        dreData.deducoes.detalhes.forEach(item => {
            if (item.valor > 0) {
                csv += `  ${item.categoria};-${item.valor.toFixed(2)}\n`;
            }
        });
    }
    csv += `Total Deduções;-${dreData.deducoes.total.toFixed(2)}\n\n`;
    
    // 3. Receita Líquida
    csv += `3. RECEITA LÍQUIDA;${dreData.receita_liquida.toFixed(2)}\n\n`;
    
    // 4. CPV/CMV
    csv += '4. CPV/CMV;\n';
    if (dreData.cpv_cmv.detalhes && dreData.cpv_cmv.detalhes.length > 0) {
        dreData.cpv_cmv.detalhes.forEach(item => {
            if (item.valor > 0) {
                csv += `  ${item.categoria};-${item.valor.toFixed(2)}\n`;
            }
        });
    }
    csv += `Custo dos Produtos/Mercadorias Vendidas;-${dreData.cpv_cmv.total.toFixed(2)}\n\n`;
    
    // 5. Lucro Bruto
    csv += `5. LUCRO BRUTO;${dreData.lucro_bruto.toFixed(2)}\n\n`;
    
    // 6. Despesas Operacionais
    csv += '6. DESPESAS OPERACIONAIS;\n';
    if (dreData.despesas_operacionais.detalhes && dreData.despesas_operacionais.detalhes.length > 0) {
        dreData.despesas_operacionais.detalhes.forEach(item => {
            if (item.valor > 0) {
                csv += `  ${item.categoria};-${item.valor.toFixed(2)}\n`;
            }
        });
    }
    csv += `Total Despesas Operacionais;-${dreData.despesas_operacionais.total.toFixed(2)}\n\n`;
    
    // 7. LAIR
    csv += `7. LUCRO ANTES DOS IMPOSTOS (LAIR);${dreData.lair.toFixed(2)}\n\n`;
    
    // 8. Tributos sobre o Lucro
    csv += '8. TRIBUTOS SOBRE O LUCRO;\n';
    if (dreData.tributos_sobre_lucro.irpj > 0) {
        csv += `  IRPJ (15%);-${dreData.tributos_sobre_lucro.irpj.toFixed(2)}\n`;
    }
    if (dreData.tributos_sobre_lucro.csll > 0) {
        csv += `  CSLL (9%);-${dreData.tributos_sobre_lucro.csll.toFixed(2)}\n`;
    }
    csv += `Total Tributos;-${dreData.tributos_sobre_lucro.total.toFixed(2)}\n\n`;
    
    // 9. Lucro Líquido
    csv += `9. LUCRO LÍQUIDO DO EXERCÍCIO;${dreData.lucro_liquido.toFixed(2)}\n\n`;
    
    // Resumo Percentual
    csv += 'RESUMO PERCENTUAL;\n';
    csv += `Receita Bruta;${dreData.receita_bruta.total.toFixed(2)}\n`;
    
    const rbPercentual = dreData.receita_bruta.total > 0 ? (dreData.lucro_bruto / dreData.receita_bruta.total) * 100 : 0;
    csv += `Lucro Bruto;${rbPercentual.toFixed(1)}% RB\n`;
    
    const lairPercentual = dreData.receita_bruta.total > 0 ? (dreData.lair / dreData.receita_bruta.total) * 100 : 0;
    csv += `LAIR;${lairPercentual.toFixed(1)}% RB\n`;
    
    const llPercentual = dreData.receita_bruta.total > 0 ? (dreData.lucro_liquido / dreData.receita_bruta.total) * 100 : 0;
    csv += `Lucro Líquido;${llPercentual.toFixed(1)}% RB\n`;
    
    // Criar e baixar o arquivo CSV
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const periodo = dreData.periodo.inicio.split('-');
    const mesAno = `${periodo[1]}-${periodo[0]}`;
    
    link.setAttribute('href', url);
    link.setAttribute('download', `DRE_${mesAno}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
