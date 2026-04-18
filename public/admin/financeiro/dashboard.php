<?php

declare(strict_types=1);

use App\Support\CsrfProtection;
use App\Support\PermissionGate;

require_once '../../../config/database.php';
require_once '../../../config/tenant.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . tenantUrl('login.php?error=session_expired'));
    exit();
}

function dashboardPublicApiUrl(string $path): string
{
    $url = tenantUrl(ltrim($path, '/'));
    $rewritten = preg_replace('#/public/#', '/', $url, 1);
    return is_string($rewritten) ? $rewritten : $url;
}

if (isset($db) && $db instanceof PDO) {
    PermissionGate::init($db);
}
PermissionGate::require('financeiro');

$currentYear = (int)date('Y');
$yearOptions = range($currentYear - 2, $currentYear);
rsort($yearOptions);
$meses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Marco',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
];

$config = [
    'api' => [
        'kpis' => dashboardPublicApiUrl('financeiro/dashboard/kpis'),
        'fluxo' => dashboardPublicApiUrl('financeiro/dashboard/receitas-despesas'),
        'heatmap' => dashboardPublicApiUrl('financeiro/dashboard/heatmap'),
        'filas' => dashboardPublicApiUrl('financeiro/contas/pagar-receber'),
    ],
    'links' => [
        'geral' => tenantUrl('admin/dashboard.php'),
        'contas' => tenantUrl('admin/financeiro/contas.php'),
        'contas_receber' => tenantUrl('admin/financeiro/contas-receber.php'),
        'contas_pagar' => tenantUrl('admin/financeiro/contas-pagar.php'),
        'formas_pagamento' => tenantUrl('admin/financeiro/formas-pagamento.php'),
        'categorias' => tenantUrl('admin/financeiro/categorias-dre.php'),
        'dre' => tenantUrl('admin/financeiro/dre.php'),
        'movimentacoes' => tenantUrl('admin/financeiro/movimentacoes.php'),
    ],
    'csrfToken' => CsrfProtection::token(),
    'defaultLimit' => 8,
    'currentYear' => $currentYear,
    'defaultChartYear' => $currentYear,
    'defaultChartMonth' => null,
    'yearOptions' => array_values(array_map('intval', $yearOptions)),
];

$dashboardJsVersion = @filemtime(__DIR__ . '/../../../assets/js/dashboard-financeiro.js') ?: time();

$page_title = 'Dashboard Financeiro';
include '../../includes/header.php';
?>

<div class="dashboard-page" id="dashboardFinanceiroPage">
    <div class="dashboard-toolbar">
        <div class="dashboard-toolbar__group">
            <div>
                <p class="dash-overline">Dashboard Financeiro</p>
                <h1 class="dash-title">Fluxo, inadimplencia e filas operacionais</h1>
            </div>
            <span class="dash-badge"><i class="fas fa-wallet"></i>Financeiro live</span>
        </div>

        <div class="dashboard-toolbar__group">
            <span class="dash-meta" id="financeLastUpdate">Atualizando...</span>
            <button type="button" class="dash-retry-btn" id="financeRefreshBtn">
                <i class="fas fa-rotate-right me-1"></i>Atualizar
            </button>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($config['links']['geral']); ?>">
                <i class="fas fa-gauge-high me-1"></i>Dashboard geral
            </a>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($config['links']['dre']); ?>">
                <i class="fas fa-chart-pie me-1"></i>DRE
            </a>
        </div>
    </div>

    <section class="dashboard-grid dashboard-grid--kpi" id="financeKpiGrid"></section>

    <section class="dashboard-grid dashboard-grid--two">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Radar</p>
                    <h2 class="dash-title">Resumo do periodo</h2>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($config['links']['movimentacoes']); ?>">
                    <i class="fas fa-list me-1"></i>Movimentacoes
                </a>
            </div>
            <div id="financeSummaryPanel"></div>
        </article>

        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Base Operacional</p>
                    <h2 class="dash-title">Contas e configuracoes</h2>
                </div>
            </div>
            <div id="financeAccountsPanel"></div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Heatmap</p>
                    <h2 class="dash-title">Atividade diaria do financeiro</h2>
                    <p class="dash-subtitle">Leitura anual do volume movimentado por dia.</p>
                </div>

                <div class="dashboard-toolbar__group">
                    <label class="dash-meta" for="financeHeatmapYear">Ano</label>
                    <select class="form-select form-select-sm" id="financeHeatmapYear" style="min-width: 7rem;">
                        <?php foreach ($config['yearOptions'] as $yearOption): ?>
                            <option value="<?php echo (int)$yearOption; ?>" <?php echo $yearOption === $config['currentYear'] ? 'selected' : ''; ?>>
                                <?php echo (int)$yearOption; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="financeHeatmapFeedback"></div>
            <div id="financeHeatmapPanel"></div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Fluxo</p>
                    <h2 class="dash-title">Receitas, despesas e lucro</h2>
                    <p class="dash-subtitle" id="financeChartSubtitle">Movimentacao por ano ou por mes dentro do ano selecionado.</p>
                </div>

                <div class="dashboard-toolbar__group">
                    <label class="dash-meta" for="financeChartMonth">Mes</label>
                    <select class="form-select form-select-sm" id="financeChartMonth" style="min-width: 10rem;">
                        <option value="">Ano inteiro</option>
                        <?php foreach ($meses as $mesNumero => $mesLabel): ?>
                            <option value="<?php echo (int)$mesNumero; ?>">
                                <?php echo htmlspecialchars($mesLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="dash-meta" for="financeChartYear">Ano</label>
                    <select class="form-select form-select-sm" id="financeChartYear" style="min-width: 7rem;">
                        <?php foreach ($config['yearOptions'] as $yearOption): ?>
                            <option value="<?php echo (int)$yearOption; ?>" <?php echo $yearOption === $config['defaultChartYear'] ? 'selected' : ''; ?>>
                                <?php echo (int)$yearOption; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="financeChartFeedback"></div>
            <div class="dash-chart-frame dash-chart-frame--sm">
                <canvas id="financeFluxoChart" aria-label="Grafico de fluxo financeiro"></canvas>
            </div>
        </article>
    </section>

    <section class="dashboard-grid dashboard-grid--two">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Fila de Recebimento</p>
                    <h2 class="dash-title">Contas a receber em aberto</h2>
                    <p class="dash-subtitle">Baixa rapida com validacao de forma de pagamento e categoria.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($config['links']['contas_receber']); ?>">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Abrir modulo
                </a>
            </div>
            <div id="financeReceberFeedback"></div>
            <div class="dash-list" id="financeReceberList"></div>
        </article>

        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Fila de Pagamento</p>
                    <h2 class="dash-title">Contas a pagar em aberto</h2>
                    <p class="dash-subtitle">Baixa rapida com conta bancaria e categoria de despesa.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($config['links']['contas_pagar']); ?>">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Abrir modulo
                </a>
            </div>
            <div id="financePagarFeedback"></div>
            <div class="dash-list" id="financePagarList"></div>
        </article>
    </section>
</div>

<div class="modal fade" id="financeQuickActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="dash-overline mb-1" id="financeQuickActionEyebrow">Baixa rapida</p>
                    <h2 class="h5 mb-0" id="financeQuickActionTitle">Atualizar conta</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="financeQuickActionForm">
                <div class="modal-body">
                    <div id="financeQuickActionNotice" class="mb-3"></div>
                    <div class="dash-modal-summary" id="financeQuickActionSummary"></div>
                    <div class="row g-3 mt-1" id="financeQuickActionFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="financeQuickActionSubmit">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.dmFinanceiroDashboardConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?php echo htmlspecialchars(tenantUrl('assets/js/dashboard-financeiro.js')); ?>?v=<?php echo $dashboardJsVersion; ?>"></script>

<?php include '../../includes/footer.php'; ?>
