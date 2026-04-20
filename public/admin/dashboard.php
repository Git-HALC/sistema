<?php

declare(strict_types=1);

use App\Support\PermissionGate;

require_once '../../config/database.php';
require_once '../../config/tenant.php';

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
PermissionGate::require('dashboard');

$page_title = 'Dashboard Geral';
include '../../public/includes/header.php';

$config = [
    'api' => [
        'kpis' => dashboardPublicApiUrl('dashboard/kpis'),
        'faturamento' => dashboardPublicApiUrl('dashboard/faturamento'),
        'atividade' => dashboardPublicApiUrl('dashboard/atividade-recente'),
        'topItens' => dashboardPublicApiUrl('dashboard/top-itens'),
        'formasPagamento' => dashboardPublicApiUrl('dashboard/formas-pagamento'),
        'vendasHora' => dashboardPublicApiUrl('dashboard/vendas-hora'),
    ],
    'links' => [
        'clientes' => tenantUrl('admin/clientes.php'),
        'vendas' => tenantUrl('admin/relatorios/financeiros/relatorio_vendas_pdv.php'),
        'produtos' => tenantUrl('admin/produtos.php'),
        'financeiro' => tenantUrl('admin/financeiro/dashboard.php'),
        'pdv' => tenantUrl('pdv'),
    ],
];

$dashboardJsVersion = @filemtime(__DIR__ . '/../../assets/js/dashboard-geral.js') ?: time();
?>

<div class="dashboard-page" id="dashboardGeralPage">
    <div class="dashboard-toolbar">
        <div class="dashboard-toolbar__group">
            <div>
                <p class="dash-overline">Dashboard Geral</p>
                <h1 class="dash-title">Operacao, vendas e estoque</h1>
                <p class="dash-subtitle">Dados em tempo real do PDV, faturamento consolidado do mes e alertas de estoque.</p>
            </div>
            <span class="dash-badge"><i class="fas fa-wave-square"></i>Live</span>
        </div>

        <div class="dashboard-toolbar__group">
            <span class="dash-meta" id="dashboardLastUpdate">Atualizando...</span>
            <button type="button" class="dash-retry-btn" id="dashboardRefreshBtn">
                <i class="fas fa-rotate-right me-1"></i>Atualizar
            </button>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($config['links']['financeiro']); ?>">
                <i class="fas fa-chart-pie me-1"></i>Dashboard financeiro
            </a>
        </div>
    </div>

    <section class="dashboard-grid dashboard-grid--kpi" id="dashboardKpiGrid"></section>

    <section class="dashboard-grid dashboard-grid--aside">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Serie Temporal</p>
                    <h2 class="dash-title">Vendas por periodo</h2>
                    <p class="dash-subtitle" id="chartSubtitle">Total de vendas PDV no intervalo selecionado.</p>
                </div>

                <div class="dash-filter-group" id="chartFilterGroup">
                    <button type="button" class="dash-filter-btn" data-period="7D">7D</button>
                    <button type="button" class="dash-filter-btn is-active" data-period="30D">30D</button>
                    <button type="button" class="dash-filter-btn" data-period="90D">90D</button>
                </div>
            </div>
            <div id="chartFeedback"></div>
            <div class="dash-chart-frame">
                <canvas id="dashboardVendasChart" aria-label="Grafico de vendas por dia"></canvas>
            </div>
        </article>

        <aside class="dashboard-stack">
            <article class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <p class="dash-overline">Mix</p>
                        <h2 class="dash-title">Formas de pagamento</h2>
                    </div>
                </div>
                <div id="formasPagamentoFeedback"></div>
                <div class="dash-chart-frame dash-chart-frame--sm">
                    <canvas id="dashboardFormasChart" aria-label="Grafico de formas de pagamento"></canvas>
                </div>
            </article>
        </aside>
    </section>

    <section class="dashboard-grid dashboard-grid--two">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Ranking</p>
                    <h2 class="dash-title">Top 5 itens vendidos</h2>
                    <p class="dash-subtitle">Produtos e servicos com maior quantidade no periodo.</p>
                </div>
            </div>
            <div id="topItensFeedback"></div>
            <div class="dash-chart-frame dash-chart-frame--sm">
                <canvas id="dashboardTopItensChart" aria-label="Grafico top itens"></canvas>
            </div>
        </article>

        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Padrao</p>
                    <h2 class="dash-title">Vendas por hora</h2>
                    <p class="dash-subtitle">Distribuicao das vendas PDV ao longo do dia.</p>
                </div>
            </div>
            <div id="vendasHoraFeedback"></div>
            <div class="dash-chart-frame dash-chart-frame--sm">
                <canvas id="dashboardHoraChart" aria-label="Grafico de vendas por hora"></canvas>
            </div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Timeline</p>
                    <h2 class="dash-title">Ultimas vendas PDV</h2>
                    <p class="dash-subtitle">Fila operacional com cliente, forma e status.</p>
                </div>

                <div class="dashboard-toolbar__group">
                    <span class="dash-meta" id="activityPageInfo">Pagina 1</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="activityPrevBtn" disabled><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="activityNextBtn" disabled><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <div id="activityFeedback"></div>
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Numero</th>
                            <th>Cliente</th>
                            <th>Forma</th>
                            <th>Status</th>
                            <th>Valor</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody id="activityBody"></tbody>
                </table>
            </div>
        </article>
    </section>
</div>

<script>
window.dmDashboardConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?php echo htmlspecialchars(tenantUrl('assets/js/dashboard-geral.js')); ?>?v=<?php echo $dashboardJsVersion; ?>"></script>

<?php include '../../public/includes/footer.php'; ?>
