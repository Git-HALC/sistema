(function () {
    const config = window.dmDashboardConfig || {};
    if (!config.api) {
        return;
    }

    const state = {
        chart: null,
        period: '30D',
        activityPage: 1,
        activityPerPage: 8,
        kpis: null
    };

    const kpiDefs = {
        clientes_ativos: { label: 'Clientes ativos', icon: 'fa-users', href: config.links.clientes, valueType: 'integer' },
        pedidos_mes: { label: 'Pedidos do mes', icon: 'fa-cart-shopping', href: config.links.pedidos, valueType: 'integer' },
        servicos_em_andamento: { label: 'Servicos em andamento', icon: 'fa-screwdriver-wrench', href: config.links.servicos, valueType: 'integer' },
        faturamento_mes: { label: 'Faturamento do mes', icon: 'fa-chart-line', href: config.links.financeiro, valueType: 'currency' }
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        renderKpiShells();
        renderInsightsSkeleton();
        renderActivitySkeleton();

        document.getElementById('dashboardRefreshBtn').addEventListener('click', refreshDashboard);
        document.getElementById('activityPrevBtn').addEventListener('click', function () {
            if (state.activityPage > 1) {
                state.activityPage -= 1;
                loadActivity();
            }
        });
        document.getElementById('activityNextBtn').addEventListener('click', function () {
            state.activityPage += 1;
            loadActivity();
        });

        document.querySelectorAll('[data-period]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (state.period === button.dataset.period) {
                    return;
                }
                state.period = button.dataset.period;
                syncPeriodButtons();
                loadChart();
            });
        });

        syncPeriodButtons();
        refreshDashboard();
    }

    async function refreshDashboard() {
        setRefreshing(true);
        clearFeedback('chartFeedback');
        clearFeedback('activityFeedback');

        try {
            await Promise.all([loadKpis(), loadChart(), loadActivity()]);
            document.getElementById('dashboardLastUpdate').textContent = 'Atualizado ' + formatTimestamp(new Date());
        } finally {
            setRefreshing(false);
        }
    }

    async function loadKpis() {
        try {
            const payload = await fetchJson(config.api.kpis);
            state.kpis = payload.kpis || {};
            Object.keys(kpiDefs).forEach(function (key) {
                renderKpi(key, state.kpis[key] || {});
            });
            renderInsights(state.kpis);
        } catch (error) {
            renderKpiError(error.message || 'Falha ao carregar os KPIs.');
            renderInsightsError(error.message || 'Falha ao carregar o radar.');
        }
    }

    async function loadChart() {
        try {
            const payload = await fetchJson(config.api.faturamento + '?period=' + encodeURIComponent(state.period));
            renderChart(payload);
            clearFeedback('chartFeedback');
        } catch (error) {
            showFeedback('chartFeedback', error.message || 'Falha ao carregar o grafico.', loadChart);
        }
    }

    async function loadActivity() {
        try {
            const qs = new URLSearchParams({
                page: String(state.activityPage),
                per_page: String(state.activityPerPage)
            });
            const payload = await fetchJson(config.api.atividade + '?' + qs.toString());
            renderActivity(payload.items || [], payload.pagination || {});
            clearFeedback('activityFeedback');
        } catch (error) {
            showFeedback('activityFeedback', error.message || 'Falha ao carregar a atividade recente.', loadActivity);
        }
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const payload = await response.json().catch(function () { return null; });

        if (!response.ok) {
            throw new Error((payload && payload.erro) || 'Requisicao falhou.');
        }

        return payload || {};
    }

    function renderKpiShells() {
        const container = document.getElementById('dashboardKpiGrid');
        container.innerHTML = Object.keys(kpiDefs).map(function (key) {
            const def = kpiDefs[key];
            return [
                '<a class="dash-card dash-card--interactive text-decoration-none text-reset" href="', escapeHtml(def.href || '#'), '" data-kpi-card="', key, '">',
                    '<div class="dash-kpi">',
                        '<span class="dash-icon"><i class="fas ', escapeHtml(def.icon), '"></i></span>',
                        '<div class="dash-kpi__content">',
                            '<p class="dash-kpi__label">', escapeHtml(def.label), '</p>',
                            '<p class="dash-kpi__value dash-live-value" data-kpi-value="', key, '">--</p>',
                            '<div class="dash-kpi__support">',
                                '<span class="dash-change dash-change--neutral" data-kpi-change="', key, '">--</span>',
                                '<span class="dash-meta" data-kpi-meta="', key, '">Carregando...</span>',
                            '</div>',
                        '</div>',
                    '</div>',
                    '<div class="dash-sparkline" data-kpi-spark="', key, '"><div class="dash-skeleton-block" style="min-height:3.5rem;"></div></div>',
                '</a>'
            ].join('');
        }).join('');
    }

    function renderKpi(key, item) {
        const def = kpiDefs[key];
        if (!def) {
            return;
        }

        setText('[data-kpi-value="' + key + '"]', def.valueType === 'currency' ? formatCurrency(item.valor || 0) : formatInteger(item.valor || 0));
        renderChange(document.querySelector('[data-kpi-change="' + key + '"]'), Number(item.variacao_percentual || 0));
        setText('[data-kpi-meta="' + key + '"]', kpiMeta(def, item));
        renderSparkline(document.querySelector('[data-kpi-spark="' + key + '"]'), item.sparkline || []);
    }

    function renderKpiError(message) {
        Object.keys(kpiDefs).forEach(function (key) {
            setText('[data-kpi-value="' + key + '"]', '--');
            const badge = document.querySelector('[data-kpi-change="' + key + '"]');
            if (badge) {
                badge.className = 'dash-change dash-change--down';
                badge.textContent = 'Erro';
            }
            setText('[data-kpi-meta="' + key + '"]', message);
            const spark = document.querySelector('[data-kpi-spark="' + key + '"]');
            if (spark) {
                spark.innerHTML = '<div class="dash-inline-error">Falha ao carregar</div>';
            }
        });
    }

    function renderInsights(kpis) {
        const faturamento = kpis.faturamento_mes || {};
        const total = Number(faturamento.valor || 0);
        const pedidos = Number(faturamento.pedidos || 0);
        const servicos = Number(faturamento.servicos || 0);
        const pedidosShare = total > 0 ? Math.round((pedidos / total) * 100) : 0;
        const servicosShare = total > 0 ? Math.round((servicos / total) * 100) : 0;

        document.getElementById('insightsPanel').innerHTML = [
            progressRow('Pedidos faturados', formatCurrency(pedidos), pedidosShare, 'success'),
            progressRow('Servicos faturados', formatCurrency(servicos), servicosShare, 'warning'),
            '<div class="dash-list">',
                trendRow('Clientes ativos', kpis.clientes_ativos),
                trendRow('Pedidos do mes', kpis.pedidos_mes),
                trendRow('Servicos em andamento', kpis.servicos_em_andamento),
                trendRow('Faturamento do mes', kpis.faturamento_mes),
            '</div>'
        ].join('');
    }

    function renderInsightsSkeleton() {
        document.getElementById('insightsPanel').innerHTML = [
            '<div class="dashboard-stack">',
                '<div class="dash-skeleton-line dash-skeleton-line--lg"></div>',
                '<div class="dash-skeleton-line"></div>',
                '<div class="dash-skeleton-line"></div>',
                '<div class="dash-skeleton-block"></div>',
            '</div>'
        ].join('');
    }

    function renderInsightsError(message) {
        document.getElementById('insightsPanel').innerHTML = '<div class="dash-inline-error">' + escapeHtml(message) + '</div>';
    }

    function renderChart(payload) {
        const labels = payload.labels || [];
        const series = payload.series || {};
        document.getElementById('chartSubtitle').textContent = 'Pedidos e servicos faturados em ' + String(payload.periodo || state.period) + '.';

        if (state.chart) {
            state.chart.destroy();
        }

        state.chart = new Chart(document.getElementById('dashboardFaturamentoChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Pedidos', data: series.pedidos || [], backgroundColor: 'rgba(79,70,229,0.72)', borderRadius: 10, maxBarThickness: 24 },
                    { label: 'Servicos', data: series.servicos || [], backgroundColor: 'rgba(16,185,129,0.68)', borderRadius: 10, maxBarThickness: 24 },
                    { type: 'line', label: 'Total', data: series.total || [], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.18)', fill: true, tension: 0.28, pointRadius: 3, pointHoverRadius: 5 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + formatCurrency(context.parsed.y || 0);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(148,163,184,0.14)' },
                        ticks: {
                            callback: function (value) { return formatCompactCurrency(value); }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderActivity(items, pagination) {
        const body = document.getElementById('activityBody');
        const page = Number(pagination.page || 1);
        const totalPages = Number(pagination.total_pages || 1);

        state.activityPage = page;

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="6"><div class="dash-empty-state">Nenhuma atividade recente encontrada.</div></td></tr>';
        } else {
            body.innerHTML = items.map(function (item) {
                return [
                    '<tr>',
                        '<td><span class="dash-badge">', escapeHtml(item.tipo === 'pedido' ? 'Pedido' : 'Servico'), '</span></td>',
                        '<td><strong>', escapeHtml(item.cliente_nome || 'Sem cliente'), '</strong></td>',
                        '<td><span class="', statusClass(item.status), '">', escapeHtml(normalizeStatus(item.status)), '</span></td>',
                        '<td>', formatCurrency(item.valor_total || 0), '</td>',
                        '<td>', escapeHtml(formatDate(item.data_evento)), '</td>',
                        '<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="', escapeHtml(item.href || '#'), '">Abrir</a></td>',
                    '</tr>'
                ].join('');
            }).join('');
        }

        document.getElementById('activityPageInfo').textContent = 'Pagina ' + page + ' de ' + totalPages;
        document.getElementById('activityPrevBtn').disabled = page <= 1;
        document.getElementById('activityNextBtn').disabled = page >= totalPages;
    }

    function renderActivitySkeleton() {
        document.getElementById('activityBody').innerHTML = [
            '<tr><td colspan="6">',
                '<div class="dashboard-stack">',
                    '<div class="dash-skeleton-line"></div>',
                    '<div class="dash-skeleton-line"></div>',
                    '<div class="dash-skeleton-line"></div>',
                '</div>',
            '</td></tr>'
        ].join('');
    }

    function renderSparkline(container, values) {
        if (!container) {
            return;
        }

        const points = Array.isArray(values) ? values.map(Number) : [];
        if (!points.length) {
            container.innerHTML = '<div class="dash-empty-state">Sem serie</div>';
            return;
        }

        const width = 220;
        const height = 56;
        const min = Math.min.apply(null, points);
        const max = Math.max.apply(null, points);
        const range = max - min || 1;
        const coordinates = points.map(function (value, index) {
            const x = (index / Math.max(points.length - 1, 1)) * (width - 8) + 4;
            const y = height - (((value - min) / range) * (height - 10) + 5);
            return x.toFixed(2) + ',' + y.toFixed(2);
        }).join(' ');

        container.innerHTML = [
            '<svg viewBox="0 0 ', width, ' ', height, '" width="100%" height="', height, '" aria-hidden="true">',
                '<polyline fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" points="', coordinates, '"></polyline>',
            '</svg>'
        ].join('');
    }

    function renderChange(element, value) {
        if (!element) {
            return;
        }
        element.className = 'dash-change ' + changeVariant(value);
        element.textContent = (value > 0 ? '+' : '') + value.toFixed(2) + '%';
    }

    function progressRow(label, value, percent, tone) {
        const barClass = tone === 'success'
            ? 'dash-progress__bar dash-progress__bar--success'
            : 'dash-progress__bar dash-progress__bar--warning';

        return [
            '<div class="dashboard-stack" style="gap:0.45rem;">',
                '<div class="dash-card__header">',
                    '<span class="dash-meta">', escapeHtml(label), '</span>',
                    '<strong>', escapeHtml(value), '</strong>',
                '</div>',
                '<div class="dash-progress">',
                    '<div class="', barClass, '" style="width:', Math.max(0, Math.min(percent, 100)), '%"></div>',
                '</div>',
            '</div>'
        ].join('');
    }

    function trendRow(label, item) {
        const value = Number(item && item.variacao_percentual || 0);
        return [
            '<div class="dash-list-item">',
                '<span class="dash-avatar">', escapeHtml(initials(label)), '</span>',
                '<div>',
                    '<p class="dash-list-item__title">', escapeHtml(label), '</p>',
                    '<p class="dash-list-item__meta">', escapeHtml(item && item.referencia ? item.referencia : 'Comparativo do painel'), '</p>',
                '</div>',
                '<span class="dash-change ', changeVariant(value), '">', escapeHtml((value > 0 ? '+' : '') + value.toFixed(2) + '%'), '</span>',
            '</div>'
        ].join('');
    }

    function kpiMeta(def, item) {
        if (def.valueType === 'currency') {
            return 'Pedidos ' + formatCurrency(item.pedidos || 0) + ' + Servicos ' + formatCurrency(item.servicos || 0);
        }
        if (def.label === 'Pedidos do mes') {
            return formatCurrency(item.valor_financeiro || 0) + ' no periodo';
        }
        return item.referencia || 'Atualizado em tempo real';
    }

    function setText(selector, value) {
        const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (element) {
            element.textContent = value;
        }
    }

    function showFeedback(id, message, retryHandler) {
        const container = document.getElementById(id);
        if (!container) {
            return;
        }
        container.innerHTML = [
            '<div class="dash-inline-error">',
                '<span>', escapeHtml(message), '</span>',
                '<button type="button" class="dash-retry-btn" data-feedback-retry>Tentar novamente</button>',
            '</div>'
        ].join('');
        const button = container.querySelector('[data-feedback-retry]');
        if (button) {
            button.addEventListener('click', retryHandler);
        }
    }

    function clearFeedback(id) {
        const container = document.getElementById(id);
        if (container) {
            container.innerHTML = '';
        }
    }

    function setRefreshing(isRefreshing) {
        document.querySelectorAll('.dash-live-value').forEach(function (element) {
            element.classList.toggle('is-refreshing', isRefreshing);
        });
        document.getElementById('dashboardRefreshBtn').disabled = isRefreshing;
    }

    function syncPeriodButtons() {
        document.querySelectorAll('[data-period]').forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.period === state.period);
        });
    }

    function changeVariant(value) {
        if (value > 0.01) return 'dash-change--up';
        if (value < -0.01) return 'dash-change--down';
        return 'dash-change--neutral';
    }

    function statusClass(status) {
        const normalized = String(status || '').toUpperCase();
        if (['FATURADO', 'PAGO', 'CONCLUIDO', 'APROVADO'].includes(normalized)) return 'dash-status-badge dash-status-badge--success';
        if (['PENDENTE', 'RASCUNHO'].includes(normalized)) return 'dash-status-badge dash-status-badge--warning';
        if (['EM_PROCESSO'].includes(normalized)) return 'dash-status-badge dash-status-badge--info';
        return 'dash-status-badge dash-status-badge--danger';
    }

    function normalizeStatus(status) {
        const map = {
            RASCUNHO: 'Rascunho',
            PENDENTE: 'Pendente',
            EM_PROCESSO: 'Em processo',
            APROVADO: 'Aprovado',
            FATURADO: 'Faturado',
            CONCLUIDO: 'Concluido',
            CANCELADO: 'Cancelado',
            PAGO: 'Pago'
        };
        const normalized = String(status || '').toUpperCase();
        return map[normalized] || status || '--';
    }

    function formatInteger(value) {
        return Number(value || 0).toLocaleString('pt-BR');
    }

    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatCompactCurrency(value) {
        return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', notation: 'compact', maximumFractionDigits: 1 });
    }

    function formatTimestamp(date) {
        return date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value || '--';
        return date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function initials(label) {
        return String(label || '').split(' ').filter(Boolean).slice(0, 2).map(function (part) {
            return part.charAt(0).toUpperCase();
        }).join('');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
