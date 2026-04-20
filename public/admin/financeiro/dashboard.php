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

$config = [
    'api' => [
        'kpis' => dashboardPublicApiUrl('financeiro/dashboard/kpis'),
        'fluxoSemanal' => dashboardPublicApiUrl('financeiro/dashboard/fluxo-semanal'),
        'crVencimento' => dashboardPublicApiUrl('financeiro/dashboard/cr-vencimento'),
        'porCategoria' => dashboardPublicApiUrl('financeiro/dashboard/por-categoria'),
        'saldoContas' => dashboardPublicApiUrl('financeiro/dashboard/saldo-contas'),
        'cpVencimento' => dashboardPublicApiUrl('financeiro/dashboard/cp-vencimento'),
        'ultimosLancamentos' => dashboardPublicApiUrl('financeiro/dashboard/ultimos-lancamentos'),
        'recebimentosForma' => dashboardPublicApiUrl('financeiro/dashboard/recebimentos-forma'),
        'filas' => dashboardPublicApiUrl('financeiro/contas/pagar-receber'),
    ],
    'links' => [
        'geral' => tenantUrl('admin/dashboard.php'),
        'contas_receber' => tenantUrl('admin/financeiro/contas-receber.php'),
        'contas_pagar' => tenantUrl('admin/financeiro/contas-pagar.php'),
        'dre' => tenantUrl('admin/financeiro/dre.php'),
        'movimentacoes' => tenantUrl('admin/financeiro/movimentacoes.php'),
        'fluxo_projetado' => tenantUrl('admin/financeiro/fluxo-caixa-projetado.php'),
    ],
    'csrfToken' => CsrfProtection::token(),
    'defaultLimit' => 8,
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
                <h1 class="dash-title">Fluxo, DRE resumido e filas operacionais</h1>
                <p class="dash-subtitle">Numeros reais do periodo: movimentacoes, contas e caixas conferidos.</p>
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
                <i class="fas fa-chart-pie me-1"></i>DRE completa
            </a>
        </div>
    </div>

    <section class="dashboard-grid dashboard-grid--kpi" id="financeKpiGrid"></section>

    <section class="dashboard-grid dashboard-grid--aside">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Fluxo do Caixa</p>
                    <h2 class="dash-title">Receitas x Despesas (semanal)</h2>
                    <p class="dash-subtitle">Entradas e saidas efetivas nos bancos do periodo.</p>
                </div>
            </div>
            <div id="fluxoSemanalFeedback"></div>
            <div class="dash-chart-frame">
                <canvas id="financeFluxoChart" aria-label="Grafico de fluxo semanal"></canvas>
            </div>
        </article>

        <aside class="dashboard-stack">
            <article class="dash-card">
                <div class="dash-card__header">
                    <div>
                        <p class="dash-overline">Recebimentos</p>
                        <h2 class="dash-title">Por forma de pagamento</h2>
                        <p class="dash-subtitle">Dinheiro / cartao / PIX / outros. Apenas visibilidade operacional, nao compoe o DRE.</p>
                    </div>
                </div>
                <div id="recebimentosFormaFeedback"></div>
                <div id="recebimentosFormaPanel"></div>
            </article>
        </aside>
    </section>

    <section class="dashboard-grid dashboard-grid--two">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Fila de Recebimento</p>
                    <h2 class="dash-title">CR por vencimento</h2>
                    <p class="dash-subtitle">Distribuicao dos titulos em aberto.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($config['links']['contas_receber']); ?>">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Abrir modulo
                </a>
            </div>
            <div id="crVencimentoFeedback"></div>
            <div class="dash-chart-frame dash-chart-frame--sm">
                <canvas id="financeCrChart" aria-label="Grafico CR por vencimento"></canvas>
            </div>
        </article>

        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Receitas</p>
                    <h2 class="dash-title">Top categorias do mes</h2>
                </div>
            </div>
            <div id="receitasCatFeedback"></div>
            <div class="dash-chart-frame dash-chart-frame--sm">
                <canvas id="financeReceitasCatChart" aria-label="Grafico receitas por categoria"></canvas>
            </div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Despesas</p>
                    <h2 class="dash-title">Top categorias do mes</h2>
                    <p class="dash-subtitle">Inclui CPV, despesa operacional/financeira e tributos.</p>
                </div>
            </div>
            <div id="despesasCatFeedback"></div>
            <div class="dash-chart-frame dash-chart-frame--sm">
                <canvas id="financeDespesasCatChart" aria-label="Grafico despesas por categoria"></canvas>
            </div>
        </article>
    </section>


    <section class="dashboard-grid dashboard-grid--two">
        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Fila de Pagamento</p>
                    <h2 class="dash-title">CP por vencimento</h2>
                    <p class="dash-subtitle">Contas a pagar em aberto ate 30 dias.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($config['links']['contas_pagar']); ?>">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Abrir modulo
                </a>
            </div>
            <div id="cpVencimentoFeedback"></div>
            <div class="dash-chart-frame dash-chart-frame--sm">
                <canvas id="financeCpChart" aria-label="Grafico CP por vencimento"></canvas>
            </div>
        </article>

        <article class="dash-card">
            <div class="dash-card__header">
                <div>
                    <p class="dash-overline">Timeline</p>
                    <h2 class="dash-title">Ultimos lancamentos</h2>
                    <p class="dash-subtitle">Movimentacoes mais recentes do financeiro.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($config['links']['movimentacoes']); ?>">
                    <i class="fas fa-list me-1"></i>Ver todas
                </a>
            </div>
            <div id="ultimosLancamentosFeedback"></div>
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descricao</th>
                            <th>Conta</th>
                            <th>Categoria</th>
                            <th>Valor</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody id="ultimosLancamentosBody"></tbody>
                </table>
            </div>
        </article>
    </section>
</div>

<script>
window.dmFinanceiroDashboardConfig = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?php echo htmlspecialchars(tenantUrl('assets/js/dashboard-financeiro.js')); ?>?v=<?php echo $dashboardJsVersion; ?>"></script>

<?php include '../../includes/footer.php'; ?>
