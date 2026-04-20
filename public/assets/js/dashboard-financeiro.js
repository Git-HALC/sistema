(function () {
    const config = window.dmFinanceiroDashboardConfig || {};
    if (!config.api) return;

    const CHART_ANIMATION = { duration: 800, easing: 'easeInOutQuart' };
    const PALETTE = {
        primary: '#4f46e5',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#0ea5e9',
        purple: '#a855f7',
        teal: '#14b8a6',
        pink: '#ec4899',
        gray: '#64748b'
    };
    const PALETTE_ARRAY = [PALETTE.primary, PALETTE.success, PALETTE.warning, PALETTE.danger, PALETTE.info, PALETTE.purple, PALETTE.teal, PALETTE.pink];

    const state = {
        fluxoChart: null,
        crChart: null,
        cpChart: null,
        receitasCatChart: null,
        despesasCatChart: null
    };

    const kpiDefs = {
        receitas: { label: 'Receitas do mes', icon: 'fa-arrow-up', color: PALETTE.success, valueType: 'currency' },
        despesas: { label: 'Despesas do mes', icon: 'fa-arrow-down', color: PALETTE.danger, valueType: 'currency' },
        lucro: { label: 'Saldo consolidado', icon: 'fa-wallet', color: PALETTE.primary, valueType: 'currency' },
        inadimplencia: { label: 'Titulos vencidos', icon: 'fa-triangle-exclamation', color: PALETTE.warning, valueType: 'currency' }
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        renderKpiShells();
        document.getElementById('financeRefreshBtn').addEventListener('click', refresh);
        refresh();
    }

    async function refresh() {
        setRefreshing(true);
        try {
            await Promise.all([
                loadKpis(),
                loadFluxoSemanal(),
                loadCrVencimento(),
                loadPorCategoria('receita'),
                loadPorCategoria('despesa'),
                loadSaldoContas(),
                loadCpVencimento(),
                loadUltimosLancamentos(),
                loadRecebimentosForma()
            ]);
            document.getElementById('financeLastUpdate').textContent = 'Atualizado ' + formatTimestamp(new Date());
        } finally {
            setRefreshing(false);
        }
    }

    async function loadKpis() {
        try {
            const payload = await fetchJson(config.api.kpis);
            const kpis = payload.kpis || {};
            Object.keys(kpiDefs).forEach(function (key) {
                renderKpi(key, kpis[key] || {});
            });
        } catch (error) {
            Object.keys(kpiDefs).forEach(function (key) {
                setText('[data-kpi-value="' + key + '"]', '--');
                setText('[data-kpi-meta="' + key + '"]', error.message || 'Falha ao carregar.');
            });
        }
    }

    async function loadDre() {
        clearFeedback('dreFeedback');
        try {
            const payload = await fetchJson(config.api.dre);
            renderDre(payload.valores || {});
        } catch (error) {
            showFeedback('dreFeedback', error.message || 'Falha ao carregar DRE.', loadDre);
        }
    }

    async function loadFluxoSemanal() {
        clearFeedback('fluxoSemanalFeedback');
        try {
            const payload = await fetchJson(config.api.fluxoSemanal);
            renderFluxoChart(payload);
        } catch (error) {
            showFeedback('fluxoSemanalFeedback', error.message || 'Falha ao carregar fluxo.', loadFluxoSemanal);
        }
    }

    async function loadCrVencimento() {
        clearFeedback('crVencimentoFeedback');
        try {
            const payload = await fetchJson(config.api.crVencimento);
            renderCrChart(payload);
        } catch (error) {
            showFeedback('crVencimentoFeedback', error.message || 'Falha ao carregar CR.', loadCrVencimento);
        }
    }

    async function loadPorCategoria(tipo) {
        const feedbackId = tipo === 'receita' ? 'receitasCatFeedback' : 'despesasCatFeedback';
        clearFeedback(feedbackId);
        try {
            const payload = await fetchJson(config.api.porCategoria + '?tipo=' + encodeURIComponent(tipo));
            renderCategoriaChart(tipo, payload);
        } catch (error) {
            showFeedback(feedbackId, error.message || 'Falha ao carregar categorias.', function () { return loadPorCategoria(tipo); });
        }
    }

    async function loadRecebimentosForma() {
        clearFeedback('recebimentosFormaFeedback');
        try {
            const payload = await fetchJson(config.api.recebimentosForma);
            renderRecebimentosForma(payload);
        } catch (error) {
            showFeedback('recebimentosFormaFeedback', error.message || 'Falha ao carregar recebimentos.', loadRecebimentosForma);
        }
    }

    function renderRecebimentosForma(payload) {
        const panel = document.getElementById('recebimentosFormaPanel');
        const labels = payload.labels || [];
        const totais = payload.totais || [];
        const tipos = payload.tipos || [];
        const qtds = payload.quantidades || [];
        const totalGeral = Number(payload.total_geral || 0);

        if (!labels.length) {
            panel.innerHTML = '<div class="dash-empty-state">Nenhum recebimento registrado no periodo.</div>';
            return;
        }

        const tipoColor = function (tipo) {
            return ({ 'D':PALETTE.success,'PIX':PALETTE.info,'CC':PALETTE.primary,'CD':PALETTE.purple,'TB':PALETTE.teal,'BOL':PALETTE.warning,'AF':PALETTE.gray }[tipo]) || PALETTE.gray;
        };
        const tipoLabel = { 'D':'Dinheiro','PIX':'PIX','CC':'Cartao Credito','CD':'Cartao Debito','TB':'Transferencia','BOL':'Boleto','AF':'A Faturar' };

        const rows = labels.map(function (lab, i) {
            const valor = Number(totais[i] || 0);
            const pct = totalGeral > 0 ? (valor / totalGeral) * 100 : 0;
            const cor = tipoColor(tipos[i] || '');
            return [
                '<div style="padding:0.5rem 0;border-bottom:1px solid var(--bs-border-color, #e5e7eb)">',
                    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.3rem">',
                        '<div>',
                            '<strong>', escapeHtml(lab), '</strong>',
                            ' <span class="dash-meta">(', (qtds[i] || 0), ' recebimento', (qtds[i] === 1 ? '' : 's'), ')</span>',
                        '</div>',
                        '<span style="color:', cor, ';font-weight:600;font-variant-numeric:tabular-nums">', formatCurrency(valor), '</span>',
                    '</div>',
                    '<div style="height:4px;background:rgba(148,163,184,0.18);border-radius:4px;overflow:hidden">',
                        '<div style="height:100%;width:', pct.toFixed(1), '%;background:', cor, ';border-radius:4px"></div>',
                    '</div>',
                    '<div class="dash-meta" style="font-size:0.72rem;margin-top:0.2rem">', (tipoLabel[tipos[i]] || tipos[i] || '—'), ' · ', pct.toFixed(1), '%</div>',
                '</div>'
            ].join('');
        }).join('');

        panel.innerHTML = [
            rows,
            '<div style="display:flex;justify-content:space-between;padding-top:0.65rem;margin-top:0.25rem;font-weight:700">',
                '<span>Total recebido no periodo</span>',
                '<span style="color:', PALETTE.primary, '">', formatCurrency(totalGeral), '</span>',
            '</div>'
        ].join('');
    }

    async function loadSaldoContas() {
        clearFeedback('saldoContasFeedback');
        try {
            const payload = await fetchJson(config.api.saldoContas);
            renderSaldoContas(payload);
        } catch (error) {
            showFeedback('saldoContasFeedback', error.message || 'Falha ao carregar contas.', loadSaldoContas);
        }
    }

    async function loadProjecao() {
        clearFeedback('projecaoFeedback');
        try {
            const payload = await fetchJson(config.api.projecaoMes);
            renderProjecao(payload);
        } catch (error) {
            showFeedback('projecaoFeedback', error.message || 'Falha ao carregar projecao.', loadProjecao);
        }
    }

    async function loadCpVencimento() {
        clearFeedback('cpVencimentoFeedback');
        try {
            const payload = await fetchJson(config.api.cpVencimento);
            renderCpChart(payload);
        } catch (error) {
            showFeedback('cpVencimentoFeedback', error.message || 'Falha ao carregar CP.', loadCpVencimento);
        }
    }

    async function loadUltimosLancamentos() {
        clearFeedback('ultimosLancamentosFeedback');
        try {
            const payload = await fetchJson(config.api.ultimosLancamentos + '?limite=8');
            renderUltimosLancamentos(payload.items || []);
        } catch (error) {
            showFeedback('ultimosLancamentosFeedback', error.message || 'Falha ao carregar lancamentos.', loadUltimosLancamentos);
        }
    }

    function renderSaldoContas(payload) {
        const panel = document.getElementById('saldoContasPanel');
        const contas = payload.contas || [];
        const total = Number(payload.total || 0);
        if (!contas.length) {
            panel.innerHTML = '<div class="dash-empty-state">Nenhuma conta ativa cadastrada.</div>';
            return;
        }
        const maxAbs = contas.reduce(function (acc, c) { return Math.max(acc, Math.abs(c.saldo_atual || 0)); }, 0) || 1;
        const rows = contas.map(function (c) {
            const saldo = Number(c.saldo_atual || 0);
            const pct = Math.min(100, Math.abs(saldo) / maxAbs * 100);
            const color = saldo >= 0 ? PALETTE.success : PALETTE.danger;
            return [
                '<div style="padding:0.55rem 0;border-bottom:1px solid var(--bs-border-color, #e5e7eb)">',
                    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem">',
                        '<div>',
                            '<strong>', escapeHtml(c.nome), '</strong>',
                            c.banco ? ' <span class="dash-meta">(' + escapeHtml(c.banco) + ')</span>' : '',
                        '</div>',
                        '<span style="color:', color, ';font-variant-numeric:tabular-nums;font-weight:600">', formatCurrency(saldo), '</span>',
                    '</div>',
                    '<div style="height:4px;background:rgba(148,163,184,0.18);border-radius:4px;overflow:hidden">',
                        '<div style="height:100%;width:', pct, '%;background:', color, ';border-radius:4px"></div>',
                    '</div>',
                '</div>'
            ].join('');
        }).join('');
        panel.innerHTML = [
            rows,
            '<div style="display:flex;justify-content:space-between;align-items:center;padding-top:0.75rem;margin-top:0.25rem;font-weight:700">',
                '<span>Total consolidado</span>',
                '<span style="color:', total >= 0 ? PALETTE.success : PALETTE.danger, '">', formatCurrency(total), '</span>',
            '</div>'
        ].join('');
    }

    function renderProjecao(p) {
        const realizado = Number(p.realizado || 0);
        const projecao = Number(p.projecao || 0);
        const pct = projecao > 0 ? Math.min(100, (realizado / projecao) * 100) : 0;
        const diasP = p.dias_passados || 0;
        const diasM = p.dias_mes || 30;

        document.getElementById('projecaoPanel').innerHTML = [
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">',
                '<div>',
                    '<p class="dash-meta" style="margin:0">Realizado</p>',
                    '<p style="font-size:1.35rem;font-weight:700;margin:0;color:', PALETTE.success, '">', formatCurrency(realizado), '</p>',
                '</div>',
                '<div>',
                    '<p class="dash-meta" style="margin:0">Projecao (fim do mes)</p>',
                    '<p style="font-size:1.35rem;font-weight:700;margin:0;color:', PALETTE.primary, '">', formatCurrency(projecao), '</p>',
                '</div>',
            '</div>',
            '<div style="height:10px;background:rgba(148,163,184,0.18);border-radius:6px;overflow:hidden;margin-bottom:0.5rem">',
                '<div style="height:100%;width:', pct.toFixed(1), '%;background:linear-gradient(90deg,', PALETTE.success, ',', PALETTE.primary, ');transition:width 0.8s ease-out"></div>',
            '</div>',
            '<div style="display:flex;justify-content:space-between" class="dash-meta">',
                '<span>Dia ', diasP, ' de ', diasM, '</span>',
                '<span>', pct.toFixed(1), '% da projecao</span>',
            '</div>'
        ].join('');
    }

    function renderCpChart(payload) {
        const labels = payload.labels || [];
        const totais = payload.totais || [];

        if (state.cpChart) state.cpChart.destroy();

        if (!labels.length || totais.every(function (v) { return v === 0; })) {
            document.getElementById('cpVencimentoFeedback').innerHTML = '<div class="dash-empty-state">Sem CP em aberto.</div>';
            return;
        }

        const colors = labels.map(function (label) {
            if (label === 'Vencidas') return PALETTE.danger;
            if (label === 'Hoje') return PALETTE.warning;
            if (label === '7 dias') return PALETTE.info;
            if (label === '15 dias') return PALETTE.primary;
            return PALETTE.teal;
        });

        state.cpChart = new Chart(document.getElementById('financeCpChart'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: totais, backgroundColor: colors, borderWidth: 2, borderColor: getComputedStyle(document.body).getPropertyValue('--bs-body-bg') || '#fff' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: Object.assign({}, CHART_ANIMATION, { animateRotate: true, animateScale: true }),
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: { callbacks: { label: function (ctx) { return ctx.label + ': ' + formatCurrency(ctx.parsed || 0); } } }
                }
            }
        });
    }

    function renderUltimosLancamentos(items) {
        const body = document.getElementById('ultimosLancamentosBody');
        if (!items.length) {
            body.innerHTML = '<tr><td colspan="6"><div class="dash-empty-state">Nenhum lancamento recente.</div></td></tr>';
            return;
        }
        body.innerHTML = items.map(function (item) {
            const isEntrada = item.tipo === 'Entrada';
            const color = isEntrada ? PALETTE.success : PALETTE.danger;
            const icon = isEntrada ? 'fa-arrow-up' : 'fa-arrow-down';
            return [
                '<tr>',
                    '<td><span style="color:', color, '"><i class="fas ', icon, ' me-1"></i>', escapeHtml(item.tipo), '</span>',
                        item.protegido ? ' <i class="fas fa-lock dash-meta" title="Protegido"></i>' : '',
                    '</td>',
                    '<td>', escapeHtml(item.descricao || '--'), '</td>',
                    '<td>', escapeHtml(item.conta || '--'), '</td>',
                    '<td>', escapeHtml(item.categoria || '--'), '</td>',
                    '<td style="color:', color, ';font-variant-numeric:tabular-nums">', formatCurrency(item.valor || 0), '</td>',
                    '<td>', escapeHtml(formatDate(item.data)), '</td>',
                '</tr>'
            ].join('');
        }).join('');
    }

    function formatDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value || '--';
        return date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    async function fetchJson(url) {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await response.json().catch(function () { return null; });
        if (!response.ok) throw new Error((payload && payload.erro) || 'Requisicao falhou.');
        return payload || {};
    }

    function renderKpiShells() {
        const container = document.getElementById('financeKpiGrid');
        container.innerHTML = Object.keys(kpiDefs).map(function (key) {
            const def = kpiDefs[key];
            return [
                '<div class="dash-card">',
                    '<div class="dash-kpi">',
                        '<span class="dash-icon" style="background: ', def.color, '15; color: ', def.color, '"><i class="fas ', def.icon, '"></i></span>',
                        '<div class="dash-kpi__content">',
                            '<p class="dash-kpi__label">', def.label, '</p>',
                            '<p class="dash-kpi__value dash-live-value" data-kpi-value="', key, '">--</p>',
                            '<div class="dash-kpi__support">',
                                '<span class="dash-change dash-change--neutral" data-kpi-change="', key, '">--</span>',
                                '<span class="dash-meta" data-kpi-meta="', key, '">Carregando...</span>',
                            '</div>',
                        '</div>',
                    '</div>',
                '</div>'
            ].join('');
        }).join('');
    }

    function renderKpi(key, item) {
        const def = kpiDefs[key];
        if (!def) return;
        setText('[data-kpi-value="' + key + '"]', formatCurrency(item.valor || 0));
        renderChange(document.querySelector('[data-kpi-change="' + key + '"]'), Number(item.variacao_percentual || 0));
        setText('[data-kpi-meta="' + key + '"]', item.referencia || 'vs mes anterior');
    }

    function renderDre(v) {
        const rows = [
            { label: 'Receita Bruta', valor: v.receitaBruta, tone: 'positive', bold: true },
            { label: '(-) Deducoes', valor: -Math.abs(v.deducoes || 0), tone: 'negative' },
            { label: 'Receita Liquida', valor: v.receitaLiquida, tone: 'positive', bold: true, divider: true },
            { label: '(-) CPV', valor: -Math.abs(v.cpv || 0), tone: 'negative' },
            { label: 'Lucro Bruto', valor: v.lucroBruto, tone: (v.lucroBruto || 0) >= 0 ? 'positive' : 'negative', bold: true, divider: true },
            { label: '(-) Despesa Op.', valor: -Math.abs(v.despesaOp || 0), tone: 'negative' },
            { label: 'EBITDA', valor: v.ebitda, tone: (v.ebitda || 0) >= 0 ? 'positive' : 'negative', bold: true, divider: true },
            { label: '(-) Despesa Fin.', valor: -Math.abs(v.despesaFin || 0), tone: 'negative' },
            { label: '(-) Tributos', valor: -Math.abs(v.tributos || 0), tone: 'negative' },
            { label: 'Lucro Liquido', valor: v.lucroLiquido, tone: (v.lucroLiquido || 0) >= 0 ? 'positive' : 'negative', bold: true, highlight: true }
        ];

        const panel = document.getElementById('dreResumidoPanel');
        panel.innerHTML = rows.map(function (row) {
            const toneColor = row.tone === 'positive' ? PALETTE.success : PALETTE.danger;
            const styles = [
                row.bold ? 'font-weight:600' : '',
                row.highlight ? 'background:' + toneColor + '12;border-radius:8px;padding:0.6rem 0.75rem' : 'padding:0.35rem 0',
                row.divider ? 'border-top:1px solid var(--bs-border-color, #e5e7eb);margin-top:0.25rem;padding-top:0.55rem' : ''
            ].filter(Boolean).join(';');
            return [
                '<div style="display:flex;justify-content:space-between;align-items:center;', styles, '">',
                    '<span style="font-size:0.875rem">', escapeHtml(row.label), '</span>',
                    '<span style="color:', toneColor, ';font-variant-numeric:tabular-nums">', formatCurrency(row.valor || 0), '</span>',
                '</div>'
            ].join('');
        }).join('');
    }

    function renderFluxoChart(payload) {
        const labels = payload.labels || [];
        const receitas = (payload.series && payload.series.receitas) || [];
        const despesas = (payload.series && payload.series.despesas) || [];

        if (state.fluxoChart) state.fluxoChart.destroy();

        if (!labels.length) {
            document.getElementById('fluxoSemanalFeedback').innerHTML = '<div class="dash-empty-state">Sem movimentacoes no periodo.</div>';
            return;
        }

        state.fluxoChart = new Chart(document.getElementById('financeFluxoChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Receitas', data: receitas, backgroundColor: PALETTE.success + 'cc', borderColor: PALETTE.success, borderWidth: 1, borderRadius: 6 },
                    { label: 'Despesas', data: despesas, backgroundColor: PALETTE.danger + 'cc', borderColor: PALETTE.danger, borderWidth: 1, borderRadius: 6 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: CHART_ANIMATION,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + formatCurrency(ctx.parsed.y || 0); } } }
                },
                scales: {
                    y: { grid: { color: 'rgba(148,163,184,0.14)' }, ticks: { callback: function (v) { return formatCompactCurrency(v); } } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderCrChart(payload) {
        const labels = payload.labels || [];
        const totais = payload.totais || [];

        if (state.crChart) state.crChart.destroy();

        if (!labels.length || totais.every(function (v) { return v === 0; })) {
            document.getElementById('crVencimentoFeedback').innerHTML = '<div class="dash-empty-state">Sem CR em aberto.</div>';
            return;
        }

        const colors = labels.map(function (label) {
            if (label === 'Vencidas') return PALETTE.danger;
            if (label === 'Hoje') return PALETTE.warning;
            if (label === '7 dias') return PALETTE.info;
            if (label === '15 dias') return PALETTE.primary;
            return PALETTE.success;
        });

        state.crChart = new Chart(document.getElementById('financeCrChart'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: totais, backgroundColor: colors, borderWidth: 2, borderColor: getComputedStyle(document.body).getPropertyValue('--bs-body-bg') || '#fff' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: Object.assign({}, CHART_ANIMATION, { animateRotate: true, animateScale: true }),
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: { callbacks: { label: function (ctx) { return ctx.label + ': ' + formatCurrency(ctx.parsed || 0); } } }
                }
            }
        });
    }

    function renderCategoriaChart(tipo, payload) {
        const labels = payload.labels || [];
        const totais = payload.totais || [];
        const canvasId = tipo === 'receita' ? 'financeReceitasCatChart' : 'financeDespesasCatChart';
        const feedbackId = tipo === 'receita' ? 'receitasCatFeedback' : 'despesasCatFeedback';
        const chartKey = tipo === 'receita' ? 'receitasCatChart' : 'despesasCatChart';
        const baseColor = tipo === 'receita' ? PALETTE.success : PALETTE.danger;

        if (state[chartKey]) state[chartKey].destroy();

        if (!labels.length) {
            document.getElementById(feedbackId).innerHTML = '<div class="dash-empty-state">Sem lancamentos no periodo.</div>';
            return;
        }

        state[chartKey] = new Chart(document.getElementById(canvasId), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: tipo === 'receita' ? 'Receitas' : 'Despesas',
                    data: totais,
                    backgroundColor: baseColor + 'cc',
                    borderColor: baseColor,
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: CHART_ANIMATION,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (ctx) { return formatCurrency(ctx.parsed.x || 0); } } }
                },
                scales: {
                    x: { grid: { color: 'rgba(148,163,184,0.14)' }, ticks: { callback: function (v) { return formatCompactCurrency(v); } } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    function renderChange(element, value) {
        if (!element) return;
        element.className = 'dash-change ' + changeVariant(value);
        element.textContent = (value > 0 ? '+' : '') + value.toFixed(2) + '%';
    }

    function changeVariant(value) {
        if (value > 0.01) return 'dash-change--up';
        if (value < -0.01) return 'dash-change--down';
        return 'dash-change--neutral';
    }

    function setText(selector, value) {
        const element = document.querySelector(selector);
        if (element) element.textContent = value;
    }

    function showFeedback(id, message, retry) {
        const container = document.getElementById(id);
        if (!container) return;
        container.innerHTML = '<div class="dash-inline-error"><span>' + escapeHtml(message) + '</span><button type="button" class="dash-retry-btn" data-feedback-retry>Tentar novamente</button></div>';
        const button = container.querySelector('[data-feedback-retry]');
        if (button) button.addEventListener('click', retry);
    }

    function clearFeedback(id) {
        const container = document.getElementById(id);
        if (container) container.innerHTML = '';
    }

    function setRefreshing(isRefreshing) {
        document.querySelectorAll('.dash-live-value').forEach(function (el) {
            el.classList.toggle('is-refreshing', isRefreshing);
        });
        document.getElementById('financeRefreshBtn').disabled = isRefreshing;
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

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
