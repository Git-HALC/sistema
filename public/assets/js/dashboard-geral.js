(function () {
    const config = window.dmDashboardConfig || {};
    if (!config.api) {
        return;
    }

    const CHART_ANIMATION = { duration: 800, easing: 'easeInOutQuart' };
    const PALETTE = {
        primary: '#4f46e5',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#0ea5e9',
        purple: '#a855f7',
        teal: '#14b8a6',
        pink: '#ec4899'
    };
    const PALETTE_ARRAY = [PALETTE.primary, PALETTE.success, PALETTE.warning, PALETTE.danger, PALETTE.info, PALETTE.purple, PALETTE.teal, PALETTE.pink];

    const state = {
        vendasChart: null,
        formasChart: null,
        topItensChart: null,
        horaChart: null,
        period: '30D',
        activityPage: 1,
        activityPerPage: 8,
        kpis: null
    };

    const kpiDefs = {
        faturamento_mes: { label: 'Faturamento do mes', icon: 'fa-chart-line', href: config.links.financeiro, valueType: 'currency', color: PALETTE.primary },
        vendas_mes: { label: 'Vendas do mes', icon: 'fa-cart-shopping', href: config.links.vendas, valueType: 'integer', color: PALETTE.success },
        ticket_medio: { label: 'Ticket medio', icon: 'fa-receipt', href: config.links.vendas, valueType: 'currency', color: PALETTE.info },
        clientes_novos: { label: 'Clientes novos', icon: 'fa-user-plus', href: config.links.clientes, valueType: 'integer', color: PALETTE.purple },
        estoque_critico: { label: 'Estoque critico', icon: 'fa-triangle-exclamation', href: config.links.produtos, valueType: 'integer', color: PALETTE.danger }
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        renderKpiShells();

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
                if (state.period === button.dataset.period) return;
                state.period = button.dataset.period;
                syncPeriodButtons();
                loadAllPeriod();
            });
        });

        syncPeriodButtons();
        refreshDashboard();
    }

    async function refreshDashboard() {
        setRefreshing(true);
        try {
            await Promise.all([
                loadKpis(),
                loadAllPeriod(),
                loadActivity()
            ]);
            document.getElementById('dashboardLastUpdate').textContent = 'Atualizado ' + formatTimestamp(new Date());
        } finally {
            setRefreshing(false);
        }
    }

    function loadAllPeriod() {
        return Promise.all([
            loadVendasChart(),
            loadFormasChart(),
            loadTopItens(),
            loadVendasHora()
        ]);
    }

    async function loadKpis() {
        try {
            const payload = await fetchJson(config.api.kpis);
            state.kpis = payload.kpis || {};
            Object.keys(kpiDefs).forEach(function (key) {
                renderKpi(key, state.kpis[key] || {});
            });
        } catch (error) {
            renderKpiError(error.message || 'Falha ao carregar os KPIs.');
        }
    }

    async function loadVendasChart() {
        clearFeedback('chartFeedback');
        try {
            const payload = await fetchJson(config.api.faturamento + '?period=' + encodeURIComponent(state.period));
            renderVendasChart(payload);
        } catch (error) {
            showFeedback('chartFeedback', error.message || 'Falha ao carregar vendas.', loadVendasChart);
        }
    }

    async function loadFormasChart() {
        clearFeedback('formasPagamentoFeedback');
        try {
            const payload = await fetchJson(config.api.formasPagamento + '?period=' + encodeURIComponent(state.period));
            renderFormasChart(payload);
        } catch (error) {
            showFeedback('formasPagamentoFeedback', error.message || 'Falha ao carregar formas.', loadFormasChart);
        }
    }

    async function loadTopItens() {
        clearFeedback('topItensFeedback');
        try {
            const payload = await fetchJson(config.api.topItens + '?period=' + encodeURIComponent(state.period));
            renderTopItensChart(payload);
        } catch (error) {
            showFeedback('topItensFeedback', error.message || 'Falha ao carregar top itens.', loadTopItens);
        }
    }

    async function loadVendasHora() {
        clearFeedback('vendasHoraFeedback');
        try {
            const payload = await fetchJson(config.api.vendasHora + '?period=' + encodeURIComponent(state.period));
            renderHoraChart(payload);
        } catch (error) {
            showFeedback('vendasHoraFeedback', error.message || 'Falha ao carregar vendas por hora.', loadVendasHora);
        }
    }

    async function loadActivity() {
        clearFeedback('activityFeedback');
        try {
            const qs = new URLSearchParams({
                page: String(state.activityPage),
                per_page: String(state.activityPerPage)
            });
            const payload = await fetchJson(config.api.atividade + '?' + qs.toString());
            renderActivity(payload.items || [], payload.pagination || {});
        } catch (error) {
            showFeedback('activityFeedback', error.message || 'Falha ao carregar atividade.', loadActivity);
        }
    }

    async function fetchJson(url) {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
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
                        '<span class="dash-icon" style="background: ', def.color, '15; color: ', def.color, '"><i class="fas ', escapeHtml(def.icon), '"></i></span>',
                        '<div class="dash-kpi__content">',
                            '<p class="dash-kpi__label">', escapeHtml(def.label), '</p>',
                            '<p class="dash-kpi__value dash-live-value" data-kpi-value="', key, '">--</p>',
                            '<div class="dash-kpi__support">',
                                '<span class="dash-change dash-change--neutral" data-kpi-change="', key, '">--</span>',
                                '<span class="dash-meta" data-kpi-meta="', key, '">Carregando...</span>',
                            '</div>',
                        '</div>',
                    '</div>',
                '</a>'
            ].join('');
        }).join('');
    }

    function renderKpi(key, item) {
        const def = kpiDefs[key];
        if (!def) return;

        const value = def.valueType === 'currency' ? formatCurrency(item.valor || 0) : formatInteger(item.valor || 0);
        setText('[data-kpi-value="' + key + '"]', value);
        renderChange(document.querySelector('[data-kpi-change="' + key + '"]'), Number(item.variacao_percentual || 0));
        setText('[data-kpi-meta="' + key + '"]', item.referencia || 'Atualizado em tempo real');
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
        });
    }

    function renderVendasChart(payload) {
        const labels = payload.labels || [];
        const vendas = (payload.series && payload.series.vendas) || [];
        document.getElementById('chartSubtitle').textContent = 'Vendas PDV nos ultimos ' + state.period.replace('D', ' dias') + '.';

        if (state.vendasChart) state.vendasChart.destroy();

        const ctx = document.getElementById('dashboardVendasChart');
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, PALETTE.primary + '66');
        gradient.addColorStop(1, PALETTE.primary + '05');

        state.vendasChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Vendas',
                    data: vendas,
                    borderColor: PALETTE.primary,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.32,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: PALETTE.primary,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: CHART_ANIMATION,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return formatCurrency(ctx.parsed.y || 0); }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(148,163,184,0.14)' },
                        ticks: { callback: function (v) { return formatCompactCurrency(v); } }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderFormasChart(payload) {
        const labels = payload.labels || [];
        const totais = payload.totais || [];

        if (state.formasChart) state.formasChart.destroy();

        if (!labels.length) {
            document.getElementById('formasPagamentoFeedback').innerHTML = '<div class="dash-empty-state">Sem vendas no periodo.</div>';
            return;
        }

        state.formasChart = new Chart(document.getElementById('dashboardFormasChart'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: totais,
                    backgroundColor: labels.map(function (_, i) { return PALETTE_ARRAY[i % PALETTE_ARRAY.length]; }),
                    borderWidth: 2,
                    borderColor: getComputedStyle(document.body).getPropertyValue('--bs-body-bg') || '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: Object.assign({}, CHART_ANIMATION, { animateRotate: true, animateScale: true }),
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return ctx.label + ': ' + formatCurrency(ctx.parsed || 0); }
                        }
                    }
                }
            }
        });
    }

    function renderTopItensChart(payload) {
        const labels = payload.labels || [];
        const quantidades = payload.quantidades || [];
        const totais = payload.totais || [];

        if (state.topItensChart) state.topItensChart.destroy();

        if (!labels.length) {
            document.getElementById('topItensFeedback').innerHTML = '<div class="dash-empty-state">Nenhum item vendido no periodo.</div>';
            return;
        }

        state.topItensChart = new Chart(document.getElementById('dashboardTopItensChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Quantidade',
                    data: quantidades,
                    backgroundColor: PALETTE.success + 'cc',
                    borderColor: PALETTE.success,
                    borderWidth: 1,
                    borderRadius: 6,
                    totais: totais
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: CHART_ANIMATION,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const tot = ctx.dataset.totais[ctx.dataIndex] || 0;
                                return ctx.parsed.x + ' un - ' + formatCurrency(tot);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: 'rgba(148,163,184,0.14)' }, ticks: { precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    function renderHoraChart(payload) {
        const labels = payload.labels || [];
        const quantidades = payload.quantidades || [];
        const totais = payload.totais || [];

        if (state.horaChart) state.horaChart.destroy();

        state.horaChart = new Chart(document.getElementById('dashboardHoraChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Vendas',
                    data: quantidades,
                    backgroundColor: labels.map(function (_, i) { return PALETTE.warning + (quantidades[i] > 0 ? 'cc' : '33'); }),
                    borderRadius: 4,
                    maxBarThickness: 18,
                    totais: totais
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: CHART_ANIMATION,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const tot = ctx.dataset.totais[ctx.dataIndex] || 0;
                                return ctx.parsed.y + ' vendas - ' + formatCurrency(tot);
                            }
                        }
                    }
                },
                scales: {
                    y: { grid: { color: 'rgba(148,163,184,0.14)' }, ticks: { precision: 0 } },
                    x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12 } }
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
            body.innerHTML = '<tr><td colspan="6"><div class="dash-empty-state">Nenhuma venda encontrada.</div></td></tr>';
        } else {
            body.innerHTML = items.map(function (item) {
                return [
                    '<tr>',
                        '<td><strong>#', item.numero || '--', '</strong>',
                            item.origem ? ' <span class="dash-meta">(' + escapeHtml(String(item.origem)) + ')</span>' : '',
                        '</td>',
                        '<td>', escapeHtml(item.cliente_nome || 'Consumidor'), '</td>',
                        '<td>', escapeHtml(item.forma || '--'), '</td>',
                        '<td><span class="', statusClass(item.status), '">', escapeHtml(normalizeStatus(item.status)), '</span></td>',
                        '<td>', formatCurrency(item.valor_total || 0), '</td>',
                        '<td>', escapeHtml(formatDate(item.data_evento)), '</td>',
                    '</tr>'
                ].join('');
            }).join('');
        }

        document.getElementById('activityPageInfo').textContent = 'Pagina ' + page + ' de ' + totalPages;
        document.getElementById('activityPrevBtn').disabled = page <= 1;
        document.getElementById('activityNextBtn').disabled = page >= totalPages;
    }

    function renderChange(element, value) {
        if (!element) return;
        element.className = 'dash-change ' + changeVariant(value);
        element.textContent = (value > 0 ? '+' : '') + value.toFixed(2) + '%';
    }

    function setText(selector, value) {
        const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (element) element.textContent = value;
    }

    function showFeedback(id, message, retryHandler) {
        const container = document.getElementById(id);
        if (!container) return;
        container.innerHTML = [
            '<div class="dash-inline-error">',
                '<span>', escapeHtml(message), '</span>',
                '<button type="button" class="dash-retry-btn" data-feedback-retry>Tentar novamente</button>',
            '</div>'
        ].join('');
        const button = container.querySelector('[data-feedback-retry]');
        if (button) button.addEventListener('click', retryHandler);
    }

    function clearFeedback(id) {
        const container = document.getElementById(id);
        if (container) container.innerHTML = '';
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
        if (['FATURADO', 'PAGO', 'CONCLUIDO'].includes(normalized)) return 'dash-status-badge dash-status-badge--success';
        if (['RASCUNHO', 'PENDENTE'].includes(normalized)) return 'dash-status-badge dash-status-badge--warning';
        if (['CANCELADO'].includes(normalized)) return 'dash-status-badge dash-status-badge--danger';
        return 'dash-status-badge dash-status-badge--info';
    }

    function normalizeStatus(status) {
        const map = {
            faturado: 'Faturado',
            rascunho: 'Rascunho',
            pendente: 'Pendente',
            cancelado: 'Cancelado',
            pago: 'Pago'
        };
        return map[String(status || '').toLowerCase()] || status || '--';
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

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
