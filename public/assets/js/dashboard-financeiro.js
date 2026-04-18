(function () {
    const config = window.dmFinanceiroDashboardConfig || {};
    if (!config.api) {
        return;
    }

    const state = {
        chart: null,
        chartYear: Number(config.defaultChartYear || config.currentYear || new Date().getFullYear()),
        chartMonth: config.defaultChartMonth == null ? '' : String(config.defaultChartMonth),
        heatmapYear: Number(config.currentYear || new Date().getFullYear()),
        limit: Number(config.defaultLimit || 8),
        kpis: null,
        fluxo: null,
        heatmap: null,
        queues: null,
        modal: null,
        activeAction: null,
        modalBusy: false
    };

    const kpiDefs = {
        receitas: { label: 'Receitas do mes', icon: 'fa-arrow-trend-up', href: config.links.contas_receber, invertChange: false },
        despesas: { label: 'Despesas do mes', icon: 'fa-arrow-trend-down', href: config.links.contas_pagar, invertChange: true },
        lucro: { label: 'Lucro do mes', icon: 'fa-scale-balanced', href: config.links.dre, invertChange: false },
        inadimplencia: { label: 'Inadimplencia', icon: 'fa-triangle-exclamation', href: config.links.contas_receber, invertChange: true }
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        renderKpiShells();
        renderSummarySkeleton();
        renderAccountsSkeleton();
        renderHeatmapSkeleton();
        renderQueueSkeleton('financeReceberList');
        renderQueueSkeleton('financePagarList');
        setupModal();

        const refreshButton = document.getElementById('financeRefreshBtn');
        if (refreshButton) {
            refreshButton.addEventListener('click', refreshDashboard);
        }

        const chartMonthSelect = document.getElementById('financeChartMonth');
        if (chartMonthSelect) {
            chartMonthSelect.value = state.chartMonth;
            chartMonthSelect.addEventListener('change', function () {
                state.chartMonth = String(chartMonthSelect.value || '');
                loadFluxo();
            });
        }

        const chartYearSelect = document.getElementById('financeChartYear');
        if (chartYearSelect) {
            chartYearSelect.value = String(state.chartYear);
            chartYearSelect.addEventListener('change', function () {
                const nextYear = Number(chartYearSelect.value || 0);
                if (!nextYear) {
                    return;
                }
                state.chartYear = nextYear;
                loadFluxo();
            });
        }

        const yearSelect = document.getElementById('financeHeatmapYear');
        if (yearSelect) {
            yearSelect.addEventListener('change', function () {
                const nextYear = Number(yearSelect.value || 0);
                if (!nextYear || nextYear === state.heatmapYear) {
                    return;
                }
                state.heatmapYear = nextYear;
                loadHeatmap();
            });
        }

        document.addEventListener('click', function (event) {
            const actionButton = event.target.closest('[data-queue-action]');
            if (!actionButton) {
                return;
            }

            event.preventDefault();
            openQuickAction(
                String(actionButton.dataset.queueAction || ''),
                Number(actionButton.dataset.queueId || 0)
            );
        });

        refreshDashboard();
    }

    async function refreshDashboard() {
        setRefreshing(true);
        clearFeedback('financeChartFeedback');
        clearFeedback('financeHeatmapFeedback');
        clearFeedback('financeReceberFeedback');
        clearFeedback('financePagarFeedback');

        await Promise.all([loadKpis(), loadFluxo(), loadHeatmap(), loadQueues()]);
        setRefreshing(false);

        const stamp = document.getElementById('financeLastUpdate');
        if (stamp) {
            stamp.textContent = 'Atualizado ' + formatTimestamp(new Date());
        }
    }

    async function loadKpis() {
        try {
            const payload = await fetchJson(config.api.kpis);
            state.kpis = payload.kpis || {};
            Object.keys(kpiDefs).forEach(function (key) {
                renderKpi(key, state.kpis[key] || {});
            });
            renderSummaryPanel();
        } catch (error) {
            state.kpis = null;
            renderKpiError(error.message || 'Falha ao carregar os KPIs financeiros.');
            renderSummaryError(error.message || 'Falha ao carregar o resumo do periodo.');
        }
    }

    async function loadFluxo() {
        try {
            const qs = new URLSearchParams({
                year: String(state.chartYear)
            });
            if (state.chartMonth !== '') {
                qs.set('month', String(state.chartMonth));
            }
            const payload = await fetchJson(config.api.fluxo + '?' + qs.toString());
            state.fluxo = payload || {};
            renderFluxoChart(state.fluxo);
            clearFeedback('financeChartFeedback');
        } catch (error) {
            showFeedback('financeChartFeedback', error.message || 'Falha ao carregar o grafico financeiro.', loadFluxo);
        }
    }

    async function loadHeatmap() {
        try {
            const payload = await fetchJson(config.api.heatmap + '?year=' + encodeURIComponent(String(state.heatmapYear)));
            state.heatmap = payload || {};
            renderHeatmap(state.heatmap);
            clearFeedback('financeHeatmapFeedback');
        } catch (error) {
            renderHeatmapError(error.message || 'Falha ao carregar o heatmap.');
            showFeedback('financeHeatmapFeedback', error.message || 'Falha ao carregar o heatmap.', loadHeatmap);
        }
    }

    async function loadQueues() {
        try {
            const payload = await fetchJson(config.api.filas + '?limit=' + encodeURIComponent(String(state.limit)));
            state.queues = payload || {};
            renderQueueList('receber', payload.contas_receber || [], 'financeReceberList');
            renderQueueList('pagar', payload.contas_pagar || [], 'financePagarList');
            renderAccountsPanel();
            renderSummaryPanel();
            clearFeedback('financeReceberFeedback');
            clearFeedback('financePagarFeedback');
        } catch (error) {
            state.queues = null;
            renderQueueError('financeReceberList', error.message || 'Falha ao carregar contas a receber.');
            renderQueueError('financePagarList', error.message || 'Falha ao carregar contas a pagar.');
            renderAccountsError(error.message || 'Falha ao carregar as configuracoes financeiras.');
            showFeedback('financeReceberFeedback', error.message || 'Falha ao carregar contas a receber.', loadQueues);
            showFeedback('financePagarFeedback', error.message || 'Falha ao carregar contas a pagar.', loadQueues);
            renderSummaryPanel();
        }
    }

    async function fetchJson(url, options) {
        const requestOptions = Object.assign({}, options || {});
        requestOptions.headers = Object.assign(
            { 'X-Requested-With': 'XMLHttpRequest' },
            (options && options.headers) || {}
        );

        const response = await fetch(url, requestOptions);
        const payload = await response.json().catch(function () {
            return null;
        });

        if (!response.ok) {
            throw new Error((payload && payload.erro) || 'Requisicao falhou.');
        }

        return payload || {};
    }

    function renderKpiShells() {
        const container = document.getElementById('financeKpiGrid');
        if (!container) {
            return;
        }

        container.innerHTML = Object.keys(kpiDefs).map(function (key) {
            const def = kpiDefs[key];
            return [
                '<a class="dash-card dash-card--interactive text-decoration-none text-reset" href="', escapeHtml(def.href || '#'), '" data-fin-kpi-card="', key, '">',
                    '<div class="dash-kpi">',
                        '<span class="dash-icon"><i class="fas ', escapeHtml(def.icon), '"></i></span>',
                        '<div class="dash-kpi__content">',
                            '<p class="dash-kpi__label">', escapeHtml(def.label), '</p>',
                            '<p class="dash-kpi__value" data-fin-kpi-value="', key, '">--</p>',
                            '<div class="dash-kpi__support">',
                                '<span class="dash-change dash-change--neutral" data-fin-kpi-change="', key, '">--</span>',
                                '<span class="dash-meta" data-fin-kpi-meta="', key, '">Carregando...</span>',
                            '</div>',
                        '</div>',
                    '</div>',
                    '<div class="dash-sparkline" data-fin-kpi-spark="', key, '"><div class="dash-skeleton-block" style="min-height:3.5rem;"></div></div>',
                '</a>'
            ].join('');
        }).join('');
    }

    function renderKpi(key, item) {
        const def = kpiDefs[key];
        if (!def) {
            return;
        }

        setText('[data-fin-kpi-value="' + key + '"]', formatCurrency(item.valor || 0));
        renderChange(document.querySelector('[data-fin-kpi-change="' + key + '"]'), Number(item.variacao_percentual || 0), !!def.invertChange);
        setText('[data-fin-kpi-meta="' + key + '"]', kpiMeta(item));
        renderSparkline(document.querySelector('[data-fin-kpi-spark="' + key + '"]'), item.sparkline || [], sparklineAccent(key));
    }

    function renderKpiError(message) {
        Object.keys(kpiDefs).forEach(function (key) {
            setText('[data-fin-kpi-value="' + key + '"]', '--');
            const badge = document.querySelector('[data-fin-kpi-change="' + key + '"]');
            if (badge) {
                badge.className = 'dash-change dash-change--down';
                badge.textContent = 'Erro';
            }
            setText('[data-fin-kpi-meta="' + key + '"]', message);
            const spark = document.querySelector('[data-fin-kpi-spark="' + key + '"]');
            if (spark) {
                spark.innerHTML = '<div class="dash-inline-error">Falha ao carregar</div>';
            }
        });
    }

    function renderFluxoChart(payload) {
        const labels = payload.labels || [];
        const series = payload.series || {};
        const subtitle = document.getElementById('financeChartSubtitle');
        if (subtitle) {
            subtitle.textContent = payload.periodo_label
                ? 'Movimentacao financeira em ' + String(payload.periodo_label) + '.'
                : 'Movimentacao financeira por mes e ano.';
        }

        if (state.chart) {
            state.chart.destroy();
        }

        state.chart = new Chart(document.getElementById('financeFluxoChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Receitas',
                        data: series.receitas || [],
                        backgroundColor: 'rgba(16,185,129,0.72)',
                        borderRadius: 10,
                        maxBarThickness: payload.granularidade === 'dia' ? 14 : 22
                    },
                    {
                        label: 'Despesas',
                        data: series.despesas || [],
                        backgroundColor: 'rgba(239,68,68,0.68)',
                        borderRadius: 10,
                        maxBarThickness: payload.granularidade === 'dia' ? 14 : 22
                    },
                    {
                        type: 'line',
                        label: 'Lucro',
                        data: series.lucro || [],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.14)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
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
                        grid: { color: 'rgba(148,163,184,0.16)' },
                        ticks: {
                            callback: function (value) {
                                return formatCompactCurrency(value);
                            }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderSummaryPanel() {
        if (!state.kpis) {
            renderSummarySkeleton();
            return;
        }

        const panel = document.getElementById('financeSummaryPanel');
        if (!panel) {
            return;
        }

        const receitas = Number((state.kpis.receitas && state.kpis.receitas.valor) || 0);
        const despesas = Number((state.kpis.despesas && state.kpis.despesas.valor) || 0);
        const lucro = Number((state.kpis.lucro && state.kpis.lucro.valor) || 0);
        const totalFluxo = receitas + despesas;
        const receitaShare = totalFluxo > 0 ? Math.round((receitas / totalFluxo) * 100) : 0;
        const despesaShare = totalFluxo > 0 ? Math.round((despesas / totalFluxo) * 100) : 0;
        const margem = receitas > 0 ? Math.round((lucro / receitas) * 100) : 0;
        const receberCount = state.queues && state.queues.contas_receber ? state.queues.contas_receber.length : 0;
        const pagarCount = state.queues && state.queues.contas_pagar ? state.queues.contas_pagar.length : 0;

        panel.innerHTML = [
            progressRow('Receitas do mes', formatCurrency(receitas), receitaShare, 'success'),
            progressRow('Despesas do mes', formatCurrency(despesas), despesaShare, 'danger'),
            progressRow('Margem estimada', (margem > 0 ? '+' : '') + margem + '%', clamp(Math.abs(margem), 0, 100), lucro >= 0 ? 'info' : 'danger'),
            '<div class="dash-list">',
                metricListItem('Lucro do mes', state.kpis.lucro || {}, false),
                metricListItem('Inadimplencia', state.kpis.inadimplencia || {}, true),
                queueMetricItem('Fila de recebimento', receberCount, config.links.contas_receber),
                queueMetricItem('Fila de pagamento', pagarCount, config.links.contas_pagar),
            '</div>'
        ].join('');
    }

    function renderSummarySkeleton() {
        const panel = document.getElementById('financeSummaryPanel');
        if (!panel) {
            return;
        }

        panel.innerHTML = [
            '<div class="dashboard-stack">',
                '<div class="dash-skeleton-line dash-skeleton-line--lg"></div>',
                '<div class="dash-skeleton-line"></div>',
                '<div class="dash-skeleton-line"></div>',
                '<div class="dash-skeleton-block"></div>',
            '</div>'
        ].join('');
    }

    function renderSummaryError(message) {
        const panel = document.getElementById('financeSummaryPanel');
        if (panel) {
            panel.innerHTML = '<div class="dash-inline-error">' + escapeHtml(message) + '</div>';
        }
    }

    function renderAccountsPanel() {
        if (!state.queues) {
            renderAccountsSkeleton();
            return;
        }

        const panel = document.getElementById('financeAccountsPanel');
        if (!panel) {
            return;
        }

        const opcoes = state.queues.opcoes || {};
        const contas = opcoes.contas_bancarias || [];
        const formas = opcoes.formas_pagamento || [];
        const categoriasReceita = opcoes.categorias_receita || [];
        const categoriasDespesa = opcoes.categorias_despesa || [];
        const warnings = [];

        if (!formas.length) {
            warnings.push('Cadastre ao menos uma forma de pagamento para baixar contas a receber.');
        }
        if (!categoriasReceita.length) {
            warnings.push('Cadastre categorias de receita para habilitar os recebimentos rapidos.');
        }
        if (!categoriasDespesa.length) {
            warnings.push('Cadastre categorias de despesa para habilitar os pagamentos rapidos.');
        }

        panel.innerHTML = [
            contas.length ? '<div class="dash-list">' + contas.map(renderAccountItem).join('') + '</div>' : '<div class="dash-empty-state">Nenhuma conta bancaria ativa cadastrada.</div>',
            '<div class="dash-stat-grid">',
                statChip('Contas ativas', String(contas.length)),
                statChip('Formas ativas', String(formas.length)),
                statChip('Categorias receita', String(categoriasReceita.length)),
                statChip('Categorias despesa', String(categoriasDespesa.length)),
            '</div>',
            warnings.length ? '<div class="dash-inline-error">' + escapeHtml(warnings.join(' ')) + '</div>' : '',
            '<div class="dash-config-links">',
                '<a class="btn btn-outline-secondary btn-sm" href="', escapeHtml(config.links.contas), '"><i class="fas fa-building-columns me-1"></i>Contas</a>',
                '<a class="btn btn-outline-secondary btn-sm" href="', escapeHtml(config.links.formas_pagamento), '"><i class="fas fa-credit-card me-1"></i>Formas</a>',
                '<a class="btn btn-outline-secondary btn-sm" href="', escapeHtml(config.links.categorias), '"><i class="fas fa-tags me-1"></i>Categorias</a>',
            '</div>'
        ].join('');
    }

    function renderAccountsSkeleton() {
        const panel = document.getElementById('financeAccountsPanel');
        if (!panel) {
            return;
        }

        panel.innerHTML = [
            '<div class="dashboard-stack">',
                '<div class="dash-skeleton-block"></div>',
                '<div class="dash-skeleton-block" style="min-height:5rem;"></div>',
            '</div>'
        ].join('');
    }

    function renderAccountsError(message) {
        const panel = document.getElementById('financeAccountsPanel');
        if (panel) {
            panel.innerHTML = '<div class="dash-inline-error">' + escapeHtml(message) + '</div>';
        }
    }

    function renderHeatmap(payload) {
        const panel = document.getElementById('financeHeatmapPanel');
        if (!panel) {
            return;
        }

        const items = payload.items || [];
        if (!items.length) {
            panel.innerHTML = '<div class="dash-empty-state">Nenhum dado diario encontrado para o ano selecionado.</div>';
            return;
        }

        const weeks = [];
        let maxValue = 0;
        let activeDays = 0;
        let peakItem = null;

        items.forEach(function (item) {
            const weekIndex = Number(item.semana_indice || 0);
            const dayIndex = Number(item.dia_semana || 0);
            if (!weeks[weekIndex]) {
                weeks[weekIndex] = [null, null, null, null, null, null, null];
            }
            weeks[weekIndex][dayIndex] = item;

            const total = Number(item.valor_total || 0);
            if (total > 0) {
                activeDays += 1;
            }
            if (total >= maxValue) {
                maxValue = total;
                peakItem = item;
            }
        });

        const validWeeks = weeks.filter(function (week) {
            return Array.isArray(week);
        });

        panel.innerHTML = [
            '<div class="dash-heatmap">',
                '<div class="dash-heatmap__labels">',
                    '<span>Seg</span>',
                    '<span>Ter</span>',
                    '<span>Qua</span>',
                    '<span>Qui</span>',
                    '<span>Sex</span>',
                    '<span>Sab</span>',
                    '<span>Dom</span>',
                '</div>',
                '<div class="dash-heatmap__grid">',
                    validWeeks.map(function (week) {
                        return renderHeatmapWeek(week, maxValue);
                    }).join(''),
                '</div>',
                '<div class="dashboard-toolbar__group">',
                    '<span class="dash-meta">Dias com movimento: ', escapeHtml(String(activeDays)), '</span>',
                    peakItem ? '<span class="dash-meta">Maior dia: ' + escapeHtml(formatDate(peakItem.data)) + ' (' + escapeHtml(formatCurrency(peakItem.valor_total)) + ')</span>' : '<span class="dash-meta">Sem pico registrado no periodo.</span>',
                '</div>',
                '<div class="dash-legend">',
                    legendItem(0, 'Sem fluxo'),
                    legendItem(1, 'Leve'),
                    legendItem(2, 'Medio'),
                    legendItem(3, 'Forte'),
                    legendItem(4, 'Pico'),
                '</div>',
            '</div>'
        ].join('');
    }

    function renderHeatmapWeek(week, maxValue) {
        return [
            '<div class="dash-heatmap__week">',
                week.map(function (item) {
                    if (!item) {
                        return '<span class="dash-heatmap__cell dash-heatmap__cell--0" title="Sem registro"></span>';
                    }

                    const total = Number(item.valor_total || 0);
                    const level = computeHeatLevel(total, maxValue);
                    const title = [
                        formatDate(item.data),
                        'Receitas: ' + formatCurrency(item.receitas || 0),
                        'Despesas: ' + formatCurrency(item.despesas || 0),
                        'Volume: ' + formatCurrency(total)
                    ].join(' | ');

                    return '<span class="dash-heatmap__cell dash-heatmap__cell--' + level + '" title="' + escapeHtml(title) + '"></span>';
                }).join(''),
            '</div>'
        ].join('');
    }

    function renderHeatmapSkeleton() {
        const panel = document.getElementById('financeHeatmapPanel');
        if (!panel) {
            return;
        }

        panel.innerHTML = '<div class="dash-skeleton-block" style="min-height:12rem;"></div>';
    }

    function renderHeatmapError(message) {
        const panel = document.getElementById('financeHeatmapPanel');
        if (panel) {
            panel.innerHTML = '<div class="dash-inline-error">' + escapeHtml(message) + '</div>';
        }
    }

    function renderQueueList(type, items, containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        if (!items.length) {
            container.innerHTML = '<div class="dash-empty-state">Nenhuma conta ' + escapeHtml(type === 'receber' ? 'a receber' : 'a pagar') + ' em aberto agora.</div>';
            return;
        }

        container.innerHTML = items.map(function (item) {
            return renderQueueItem(type, item);
        }).join('');
    }

    function renderQueueItem(type, item) {
        const name = type === 'receber' ? item.cliente_nome : item.fornecedor_nome;
        const statusTone = item.atrasada ? 'danger' : statusClass(item.status);
        const duePrefix = item.atrasada ? 'Vencida em ' : 'Vencimento ';

        return [
            '<div class="dash-list-item">',
                '<span class="dash-avatar">', escapeHtml(type === 'receber' ? 'CR' : 'CP'), '</span>',
                '<div class="dash-list-item__content">',
                    '<p class="dash-list-item__title">', escapeHtml(name || 'Sem cadastro'), '</p>',
                    '<p class="dash-list-item__meta">', escapeHtml(item.descricao || 'Sem descricao'), ' • ', escapeHtml(duePrefix + formatDate(item.data_vencimento)), '</p>',
                    '<div class="dash-list-item__badges">',
                        '<span class="dash-status-badge dash-status-badge--', escapeHtml(statusTone), '">', escapeHtml(item.atrasada ? 'Atrasada' : normalizeStatus(item.status)), '</span>',
                        item.valor_pago > 0 ? '<span class="dash-badge">Parcial: ' + escapeHtml(formatCurrency(item.valor_pago)) + '</span>' : '',
                    '</div>',
                '</div>',
                '<div class="text-end">',
                    '<div class="dash-list-item__value">', escapeHtml(formatCurrency(item.saldo_aberto || 0)), '</div>',
                    '<p class="dash-list-item__meta">Saldo aberto</p>',
                    '<div class="dash-list-item__actions">',
                        '<a class="btn btn-outline-secondary btn-sm" href="', escapeHtml(item.href || '#'), '">Abrir</a>',
                        queueActionMarkup(type, item),
                    '</div>',
                '</div>',
            '</div>'
        ].join('');
    }

    function renderQueueSkeleton(containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        container.innerHTML = [
            queueSkeletonItem(),
            queueSkeletonItem(),
            queueSkeletonItem()
        ].join('');
    }

    function queueSkeletonItem() {
        return [
            '<div class="dash-list-item">',
                '<div class="dash-skeleton-circle"></div>',
                '<div class="dashboard-stack" style="gap:0.55rem;">',
                    '<div class="dash-skeleton-line"></div>',
                    '<div class="dash-skeleton-line dash-skeleton-line--sm"></div>',
                '</div>',
                '<div class="dashboard-stack" style="gap:0.55rem; min-width:8rem;">',
                    '<div class="dash-skeleton-line"></div>',
                    '<div class="dash-skeleton-line dash-skeleton-line--sm"></div>',
                '</div>',
            '</div>'
        ].join('');
    }

    function renderQueueError(containerId, message) {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = '<div class="dash-inline-error">' + escapeHtml(message) + '</div>';
        }
    }

    function queueActionMarkup(type, item) {
        const readiness = actionReadiness(type);
        if (!readiness.ready) {
            return '<a class="btn btn-outline-warning btn-sm" href="' + escapeHtml(readiness.href || '#') + '">' + escapeHtml(readiness.label || 'Configurar') + '</a>';
        }

        const buttonClass = type === 'receber' ? 'btn-success' : 'btn-primary';
        const buttonLabel = type === 'receber' ? 'Receber' : 'Pagar';

        return '<button type="button" class="btn ' + buttonClass + ' btn-sm" data-queue-action="' + escapeHtml(type) + '" data-queue-id="' + escapeHtml(String(item.id || 0)) + '">' + escapeHtml(buttonLabel) + '</button>';
    }

    function actionReadiness(type) {
        const opcoes = (state.queues && state.queues.opcoes) || {};
        if (type === 'receber') {
            if (!(opcoes.formas_pagamento || []).length) {
                return { ready: false, href: config.links.formas_pagamento, label: 'Configurar forma' };
            }
            if (!(opcoes.categorias_receita || []).length) {
                return { ready: false, href: config.links.categorias, label: 'Configurar categoria' };
            }
            return { ready: true };
        }

        if (!(opcoes.contas_bancarias || []).length) {
            return { ready: false, href: config.links.contas, label: 'Configurar conta' };
        }
        if (!(opcoes.categorias_despesa || []).length) {
            return { ready: false, href: config.links.categorias, label: 'Configurar categoria' };
        }

        return { ready: true };
    }

    function setupModal() {
        const modalEl = document.getElementById('financeQuickActionModal');
        const formEl = document.getElementById('financeQuickActionForm');

        if (!modalEl || !formEl || typeof window.bootstrap === 'undefined') {
            return;
        }

        state.modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modalEl.addEventListener('hidden.bs.modal', resetQuickActionModal);
        formEl.addEventListener('submit', submitQuickAction);
    }

    function openQuickAction(type, id) {
        if (!state.modal) {
            return;
        }

        const readiness = actionReadiness(type);
        if (!readiness.ready) {
            window.location.href = readiness.href || '#';
            return;
        }

        const item = findQueueItem(type, id);
        if (!item) {
            return;
        }

        state.activeAction = { type: type, item: item };

        const title = document.getElementById('financeQuickActionTitle');
        const eyebrow = document.getElementById('financeQuickActionEyebrow');
        const submit = document.getElementById('financeQuickActionSubmit');
        if (title) {
            title.textContent = type === 'receber' ? 'Receber conta' : 'Pagar conta';
        }
        if (eyebrow) {
            eyebrow.textContent = type === 'receber' ? 'Baixa de recebimento' : 'Baixa de pagamento';
        }
        if (submit) {
            submit.textContent = type === 'receber' ? 'Confirmar recebimento' : 'Confirmar pagamento';
        }

        renderQuickActionSummary(type, item);
        renderQuickActionFields(type, item);
        showQuickActionNotice(
            type === 'receber'
                ? 'Ajuste o valor pago apenas se for uma baixa parcial. O saldo aberto ja vem preenchido.'
                : 'Use desconto apenas quando a baixa for menor que o valor original da conta.',
            'info'
        );

        state.modal.show();
    }

    function renderQuickActionSummary(type, item) {
        const summary = document.getElementById('financeQuickActionSummary');
        if (!summary) {
            return;
        }

        summary.innerHTML = [
            '<div class="dash-modal-summary__title">', escapeHtml(type === 'receber' ? (item.cliente_nome || 'Sem cliente') : (item.fornecedor_nome || 'Sem fornecedor')), '</div>',
            '<div class="dash-modal-summary__meta">', escapeHtml(item.descricao || 'Sem descricao'), ' • ', escapeHtml((item.atrasada ? 'Vencida em ' : 'Vencimento ') + formatDate(item.data_vencimento)), '</div>',
            '<div class="dash-stat-grid mt-3">',
                statChip('Valor original', formatCurrency(item.valor || 0)),
                statChip('Ja baixado', formatCurrency(item.valor_pago || 0)),
                statChip('Saldo aberto', formatCurrency(item.saldo_aberto || 0)),
                statChip('Desconto atual', formatCurrency(item.desconto || 0)),
            '</div>'
        ].join('');
    }

    function renderQuickActionFields(type, item) {
        const fields = document.getElementById('financeQuickActionFields');
        if (!fields || !state.queues) {
            return;
        }

        const opcoes = state.queues.opcoes || {};
        const today = todayString();
        const openValue = Number(item.saldo_aberto || 0).toFixed(2);

        if (type === 'receber') {
            fields.innerHTML = [
                selectField('finance_forma_pagamento_id', 'forma_pagamento_id', 'Forma de pagamento', opcoes.formas_pagamento || [], opcoes.formas_pagamento && opcoes.formas_pagamento[0] ? opcoes.formas_pagamento[0].id : '', 'nome'),
                selectField('finance_categoria_receita_id', 'categoria_dre_id', 'Categoria de receita', opcoes.categorias_receita || [], opcoes.categorias_receita && opcoes.categorias_receita[0] ? opcoes.categorias_receita[0].id : '', 'nome'),
                inputField('finance_data_pagamento', 'data_pagamento', 'Data do recebimento', 'date', today, ''),
                inputField('finance_valor_pago', 'valor_pago', 'Valor recebido', 'number', openValue, '0.01'),
                inputField('finance_desconto_recebimento', 'desconto_recebimento', 'Desconto aplicado', 'number', '0.00', '0.01'),
            ].join('');
            return;
        }

        fields.innerHTML = [
            selectField('finance_conta_id', 'conta_id', 'Conta bancaria', opcoes.contas_bancarias || [], opcoes.contas_bancarias && opcoes.contas_bancarias[0] ? opcoes.contas_bancarias[0].id : '', 'nome'),
            selectField('finance_categoria_despesa_id', 'categoria_dre_id', 'Categoria de despesa', opcoes.categorias_despesa || [], opcoes.categorias_despesa && opcoes.categorias_despesa[0] ? opcoes.categorias_despesa[0].id : '', 'nome'),
            inputField('finance_data_pagamento', 'data_pagamento', 'Data do pagamento', 'date', today, ''),
            inputField('finance_valor_pago', 'valor_pago', 'Valor pago', 'number', openValue, '0.01'),
            inputField('finance_desconto_pagamento', 'desconto_pagamento', 'Desconto aplicado', 'number', '0.00', '0.01'),
        ].join('');
    }

    async function submitQuickAction(event) {
        event.preventDefault();

        if (!state.activeAction || state.modalBusy) {
            return;
        }

        const form = event.currentTarget;
        const formData = new FormData(form);
        formData.append('csrf_token', String(config.csrfToken || ''));

        setQuickActionBusy(true);

        try {
            const payload = await fetchJson(state.activeAction.item.acao_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams(formData).toString()
            });

            if (state.modal) {
                state.modal.hide();
            }

            showSuccess(payload.mensagem || 'Conta atualizada com sucesso.');
            await refreshDashboard();
        } catch (error) {
            showQuickActionNotice(error.message || 'Nao foi possivel concluir a baixa.', 'error');
        } finally {
            setQuickActionBusy(false);
        }
    }

    function resetQuickActionModal() {
        state.activeAction = null;

        const summary = document.getElementById('financeQuickActionSummary');
        const fields = document.getElementById('financeQuickActionFields');
        const notice = document.getElementById('financeQuickActionNotice');
        const form = document.getElementById('financeQuickActionForm');
        const submit = document.getElementById('financeQuickActionSubmit');

        if (summary) {
            summary.innerHTML = '';
        }
        if (fields) {
            fields.innerHTML = '';
        }
        if (notice) {
            notice.innerHTML = '';
        }
        if (form) {
            form.reset();
        }
        if (submit) {
            submit.disabled = false;
            submit.textContent = 'Confirmar';
        }
    }

    function setQuickActionBusy(busy) {
        state.modalBusy = busy;
        const submit = document.getElementById('financeQuickActionSubmit');
        if (submit) {
            submit.disabled = busy;
            submit.textContent = busy
                ? 'Processando...'
                : (state.activeAction
                    ? (state.activeAction.type === 'receber' ? 'Confirmar recebimento' : 'Confirmar pagamento')
                    : 'Confirmar');
        }
    }

    function showQuickActionNotice(message, tone) {
        const notice = document.getElementById('financeQuickActionNotice');
        if (!notice) {
            return;
        }

        const cssClass = tone === 'error' ? 'dash-inline-error' : 'dash-empty-state';
        notice.innerHTML = '<div class="' + cssClass + '">' + escapeHtml(message) + '</div>';
    }

    function findQueueItem(type, id) {
        if (!state.queues) {
            return null;
        }

        const collection = type === 'receber' ? (state.queues.contas_receber || []) : (state.queues.contas_pagar || []);
        return collection.find(function (item) {
            return Number(item.id || 0) === Number(id || 0);
        }) || null;
    }

    function selectField(id, name, label, options, selectedValue, textKey) {
        return [
            '<div class="col-md-6">',
                '<label class="form-label" for="', escapeHtml(id), '">', escapeHtml(label), ' *</label>',
                '<select class="form-select" id="', escapeHtml(id), '" name="', escapeHtml(name), '" required>',
                    options.map(function (option) {
                        const value = option.id != null ? option.id : '';
                        const selected = String(value) === String(selectedValue) ? ' selected' : '';
                        return '<option value="' + escapeHtml(String(value)) + '"' + selected + '>' + escapeHtml(String(option[textKey] || 'Opcao')) + '</option>';
                    }).join(''),
                '</select>',
            '</div>'
        ].join('');
    }

    function inputField(id, name, label, type, value, step) {
        const stepAttr = step ? ' step="' + escapeHtml(step) + '"' : '';
        return [
            '<div class="col-md-6">',
                '<label class="form-label" for="', escapeHtml(id), '">', escapeHtml(label), ' *</label>',
                '<input class="form-control" id="', escapeHtml(id), '" name="', escapeHtml(name), '" type="', escapeHtml(type), '" value="', escapeHtml(String(value || '')), '"', stepAttr, ' required>',
            '</div>'
        ].join('');
    }

    function metricListItem(title, item, invertChange) {
        return [
            '<div class="dash-list-item">',
                '<span class="dash-avatar">', escapeHtml(initials(title)), '</span>',
                '<div class="dash-list-item__content">',
                    '<p class="dash-list-item__title">', escapeHtml(title), '</p>',
                    '<p class="dash-list-item__meta">', escapeHtml(String(item.referencia || 'Comparacao com o periodo anterior')), '</p>',
                '</div>',
                '<div class="text-end">',
                    '<div class="dash-list-item__value">', escapeHtml(formatCurrency(item.valor || 0)), '</div>',
                    changeMarkup(Number(item.variacao_percentual || 0), invertChange),
                '</div>',
            '</div>'
        ].join('');
    }

    function queueMetricItem(title, count, href) {
        return [
            '<a class="dash-list-item text-decoration-none text-reset" href="', escapeHtml(href || '#'), '">',
                '<span class="dash-avatar">', escapeHtml(initials(title)), '</span>',
                '<div class="dash-list-item__content">',
                    '<p class="dash-list-item__title">', escapeHtml(title), '</p>',
                    '<p class="dash-list-item__meta">Abrir fila completa e historico do modulo.</p>',
                '</div>',
                '<div class="text-end">',
                    '<div class="dash-list-item__value">', escapeHtml(String(count)), '</div>',
                    '<span class="dash-meta">item(ns)</span>',
                '</div>',
            '</a>'
        ].join('');
    }

    function renderAccountItem(account) {
        const title = account.nome || 'Conta';
        const meta = account.banco ? account.banco : 'Conta bancaria ativa';

        return [
            '<div class="dash-list-item">',
                '<span class="dash-avatar">', escapeHtml(initials(title)), '</span>',
                '<div class="dash-list-item__content">',
                    '<p class="dash-list-item__title">', escapeHtml(title), '</p>',
                    '<p class="dash-list-item__meta">', escapeHtml(meta), '</p>',
                '</div>',
                '<div class="dash-list-item__value">', escapeHtml(formatCurrency(account.saldo_atual || 0)), '</div>',
            '</div>'
        ].join('');
    }

    function statChip(label, value) {
        return [
            '<div class="dash-stat-chip">',
                '<p class="dash-stat-chip__label">', escapeHtml(label), '</p>',
                '<p class="dash-stat-chip__value">', escapeHtml(String(value)), '</p>',
            '</div>'
        ].join('');
    }

    function progressRow(label, value, percent, tone) {
        const toneClass = tone ? 'dash-progress__bar--' + escapeHtml(tone) : '';
        return [
            '<div class="dashboard-stack" style="gap:0.45rem;">',
                '<div class="dash-card__header">',
                    '<span class="dash-list-item__title">', escapeHtml(label), '</span>',
                    '<span class="dash-list-item__value">', escapeHtml(value), '</span>',
                '</div>',
                '<div class="dash-progress"><div class="dash-progress__bar ', toneClass, '" style="width:', escapeHtml(String(clamp(percent, 0, 100))), '%;"></div></div>',
            '</div>'
        ].join('');
    }

    function changeMarkup(value, invert) {
        const numeric = Number(value || 0);
        const cssClass = changeClass(numeric, invert);
        const icon = numeric > 0 ? 'fa-arrow-trend-up' : (numeric < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
        const sign = numeric > 0 ? '+' : '';

        return [
            '<div class="mt-2">',
                '<span class="dash-change ', cssClass, '">',
                    '<i class="fas ', escapeHtml(icon), '"></i>',
                    escapeHtml(sign + formatPercent(numeric)),
                '</span>',
            '</div>'
        ].join('');
    }

    function renderChange(element, value, invert) {
        if (!element) {
            return;
        }

        const numeric = Number(value || 0);
        const icon = numeric > 0 ? 'fa-arrow-trend-up' : (numeric < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
        const sign = numeric > 0 ? '+' : '';
        element.className = 'dash-change ' + changeClass(numeric, invert);
        element.innerHTML = '<i class="fas ' + escapeHtml(icon) + '"></i>' + escapeHtml(sign + formatPercent(numeric));
    }

    function changeClass(value, invert) {
        if (value > 0) {
            return invert ? 'dash-change--down' : 'dash-change--up';
        }
        if (value < 0) {
            return invert ? 'dash-change--up' : 'dash-change--down';
        }
        return 'dash-change--neutral';
    }

    function renderSparkline(container, values, accent) {
        if (!container) {
            return;
        }

        const series = Array.isArray(values) ? values.map(function (value) { return Number(value || 0); }) : [];
        if (!series.length) {
            container.innerHTML = '<div class="dash-inline-error">Sem serie recente</div>';
            return;
        }

        const width = 220;
        const height = 62;
        const padding = 6;
        const min = Math.min.apply(Math, series);
        const max = Math.max.apply(Math, series);
        const range = max - min || 1;

        const points = series.map(function (value, index) {
            const x = padding + (index * ((width - (padding * 2)) / Math.max(series.length - 1, 1)));
            const y = height - padding - (((value - min) / range) * (height - (padding * 2)));
            return x.toFixed(2) + ',' + y.toFixed(2);
        }).join(' ');

        const areaPoints = [padding + ',' + (height - padding), points, (width - padding) + ',' + (height - padding)].join(' ');

        container.innerHTML = [
            '<svg viewBox="0 0 ', width, ' ', height, '" width="100%" height="100%" preserveAspectRatio="none" aria-hidden="true">',
                '<polyline fill="rgba(255,255,255,0)" stroke="none" points="', areaPoints, '"></polyline>',
                '<polygon fill="', escapeHtml(hexToRgba(accent, 0.12)), '" points="', areaPoints, '"></polygon>',
                '<polyline fill="none" stroke="', escapeHtml(accent), '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="', points, '"></polyline>',
            '</svg>'
        ].join('');
    }

    function showFeedback(containerId, message, retryHandler) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        container.innerHTML = [
            '<div class="dash-inline-error">',
                '<span>', escapeHtml(message), '</span>',
                '<button type="button" class="btn btn-outline-danger btn-sm">Tentar de novo</button>',
            '</div>'
        ].join('');

        const button = container.querySelector('button');
        if (button && typeof retryHandler === 'function') {
            button.addEventListener('click', retryHandler);
        }
    }

    function clearFeedback(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = '';
        }
    }

    function showSuccess(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: message,
                confirmButtonText: 'OK'
            });
            return;
        }

        window.alert(message);
    }

    function setRefreshing(refreshing) {
        const button = document.getElementById('financeRefreshBtn');
        if (!button) {
            return;
        }

        button.disabled = refreshing;
        button.innerHTML = refreshing
            ? '<i class="fas fa-spinner fa-spin me-1"></i>Atualizando...'
            : '<i class="fas fa-rotate-right me-1"></i>Atualizar';
    }

    function kpiMeta(item) {
        const absolute = Number(item.variacao_absoluta || 0);
        const reference = String(item.referencia || 'vs periodo anterior');
        const sign = absolute > 0 ? '+' : (absolute < 0 ? '-' : '');
        return reference + ' • ' + sign + formatCurrency(Math.abs(absolute));
    }

    function statusClass(status) {
        const normalized = String(status || '').toUpperCase();
        if (normalized === 'PAGO' || normalized === 'RECEBIDO') {
            return 'success';
        }
        if (normalized === 'VENCIDO' || normalized === 'ATRASADO') {
            return 'danger';
        }
        if (normalized === 'PENDENTE') {
            return 'warning';
        }
        return 'info';
    }

    function normalizeStatus(status) {
        const normalized = String(status || '').toUpperCase();
        if (normalized === 'PENDENTE') {
            return 'Pendente';
        }
        if (normalized === 'VENCIDO') {
            return 'Vencido';
        }
        if (normalized === 'PAGO') {
            return 'Pago';
        }
        if (normalized === 'CANCELADO') {
            return 'Cancelado';
        }
        return status || 'Sem status';
    }

    function sparklineAccent(key) {
        if (key === 'receitas') {
            return '#10b981';
        }
        if (key === 'despesas') {
            return '#ef4444';
        }
        if (key === 'lucro') {
            return '#2563eb';
        }
        return '#f59e0b';
    }

    function computeHeatLevel(value, maxValue) {
        const numeric = Number(value || 0);
        if (numeric <= 0 || maxValue <= 0) {
            return 0;
        }

        const ratio = numeric / maxValue;
        if (ratio >= 0.75) {
            return 4;
        }
        if (ratio >= 0.5) {
            return 3;
        }
        if (ratio >= 0.25) {
            return 2;
        }
        return 1;
    }

    function legendItem(level, label) {
        return [
            '<span class="dash-legend__item">',
                '<span class="dash-legend__swatch dash-legend__swatch--', escapeHtml(String(level)), '"></span>',
                escapeHtml(label),
            '</span>'
        ].join('');
    }

    function setText(selector, value) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = value;
        }
    }

    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function formatCompactCurrency(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            notation: 'compact',
            maximumFractionDigits: 1
        });
    }

    function formatPercent(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        }) + '%';
    }

    function formatDate(value) {
        if (!value) {
            return '--';
        }

        const date = new Date(value + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('pt-BR');
    }

    function formatTimestamp(value) {
        return value.toLocaleDateString('pt-BR') + ' ' + value.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function todayString() {
        const today = new Date();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        return today.getFullYear() + '-' + month + '-' + day;
    }

    function initials(value) {
        return String(value || '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(function (part) { return part.charAt(0).toUpperCase(); })
            .join('') || 'DM';
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(Number(value || 0), min), max);
    }

    function hexToRgba(hex, alpha) {
        const normalized = String(hex || '').replace('#', '');
        if (normalized.length !== 6) {
            return 'rgba(37,99,235,' + alpha + ')';
        }

        const r = parseInt(normalized.slice(0, 2), 16);
        const g = parseInt(normalized.slice(2, 4), 16);
        const b = parseInt(normalized.slice(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
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
