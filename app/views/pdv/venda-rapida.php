<?php
/**
 * @var array $caixa
 * @var array $formasPagamento
 * @var string $csrfToken
 */
$baseUrl = tenantCleanUrl('pdv/venda');
$searchUrl = tenantCleanUrl('pdv/venda') . '?action=buscar-itens';
$finalizeUrl = tenantCleanUrl('pdv/venda') . '?action=finalizar';
$caixaUrl = tenantCleanUrl('pdv/caixa/' . (int)$caixa['id']);
$fluxoUrl = tenantCleanUrl('pdv/fluxo');

$labelPorTipo = [
    'D' => 'Dinheiro', 'PIX' => 'PIX', 'CC' => 'Crédito', 'CD' => 'Débito',
    'BOL' => 'Boleto', 'TB' => 'Transferência', 'AF' => 'A Faturar',
];
?>
<style>
/* Alinha ao design system: usa tokens CSS, Bootstrap cards, respeita dark mode */
.vr-layout { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(340px, 1fr); gap: 1rem; }
@media (max-width: 991.98px) { .vr-layout { grid-template-columns: 1fr; } }

.vr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .5rem; max-height: 58vh; overflow-y: auto; padding: .25rem; }
.vr-item {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: .75rem;
    text-align: left;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    gap: .25rem;
    color: var(--text-primary);
}
.vr-item:hover { border-color: var(--accent); transform: translateY(-1px); box-shadow: var(--shadow-md); }
.vr-item .tipo { font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); }
.vr-item .tipo.srv { color: var(--success); }
.vr-item .nome { font-size: .875rem; color: var(--text-primary); line-height: 1.3; }
.vr-item .valor { font-size: 1rem; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }

.vr-cart-list { max-height: 42vh; overflow-y: auto; }
.vr-cart-item {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: .25rem .75rem;
    padding: .625rem .875rem;
    border-bottom: 1px solid var(--border-color);
    align-items: center;
}
.vr-cart-item:last-child { border-bottom: none; }
.vr-cart-item-nome { font-size: .8125rem; color: var(--text-primary); grid-column: 1; }
.vr-cart-item-qty { display: flex; align-items: center; gap: .25rem; grid-column: 1; flex-wrap: wrap; }
.vr-qty-input { width: 52px; text-align: center; }
.vr-cart-item-total { font-weight: 700; font-variant-numeric: tabular-nums; grid-column: 2; grid-row: 1; text-align: right; color: var(--text-primary); }
.vr-cart-item-unit { font-size: .7rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }

.vr-grand { font-size: 2rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--accent); letter-spacing: -0.02em; }

.vr-pay { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: .375rem; }
.vr-pay .btn { text-transform: uppercase; font-size: .75rem; letter-spacing: .04em; padding: .625rem .375rem; }
.vr-pay .btn.active { background: var(--accent); color: #fff; border-color: var(--accent); font-weight: 600; }

.vr-modo { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: .25rem; }
.vr-modo .btn { background: transparent; color: var(--text-secondary); border: none; font-size: .8125rem; padding: .5rem .375rem; }
.vr-modo .btn.active { background: var(--bg-primary); color: var(--accent); font-weight: 600; box-shadow: var(--shadow); }

.vr-status-bar { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: .5rem .875rem; font-size: .8125rem; color: var(--text-secondary); display: flex; gap: 1rem; flex-wrap: wrap; }
.vr-status-bar strong { color: var(--text-primary); }
</style>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Venda Rápida</h1>
            <div class="vr-status-bar">
                <span>Caixa <strong>#<?= (int)$caixa['numero_caixa'] ?></strong></span>
                <span>Operador <strong><?= htmlspecialchars((string)($_SESSION['user_name'] ?? '')) ?></strong></span>
                <span>Aberto <strong><?= htmlspecialchars(date('d/m H:i', strtotime((string)$caixa['data_abertura']))) ?></strong></span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= htmlspecialchars($caixaUrl) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Caixa
            </a>
            <a href="<?= htmlspecialchars($fluxoUrl) ?>" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-columns me-1"></i> Fluxo
            </a>
        </div>
    </div>

    <div class="vr-layout">

        <!-- Coluna esquerda: busca + grade -->
        <div class="card shadow">
            <div class="card-body">
                <div class="mb-3">
                    <input type="text" id="vrSearch" class="form-control form-control-lg"
                           placeholder="Buscar produto ou serviço (nome ou código)..." autocomplete="off" autofocus>
                </div>
                <div class="vr-grid" id="vrGrid">
                    <div class="text-muted text-center py-5 w-100" style="grid-column: 1 / -1;">
                        <i class="fas fa-search fa-2x mb-2 opacity-50"></i>
                        <div>Digite para buscar itens</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna direita: carrinho -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Carrinho</h6>
                <span class="badge bg-primary" id="vrCount">0 itens</span>
            </div>
            <div class="vr-cart-list" id="vrCart">
                <div class="text-muted text-center py-5">
                    <i class="fas fa-basket-shopping fa-2x mb-2 opacity-50"></i>
                    <div>Adicione itens à esquerda</div>
                </div>
            </div>

            <div class="card-body border-top">

                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="text-muted small">Subtotal</span>
                    <span class="fw-semibold" id="vrSubtotal">R$ 0,00</span>
                </div>

                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-5">
                        <label class="form-label small text-muted mb-1">Desconto</label>
                        <select id="vrDescTipo" class="form-select form-select-sm">
                            <option value="">Sem desconto</option>
                            <option value="VALOR">R$</option>
                            <option value="PERCENTUAL">%</option>
                        </select>
                    </div>
                    <div class="col-7">
                        <input type="number" id="vrDescValor" class="form-control form-control-sm" min="0" step="0.01" value="0" disabled>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-baseline mb-3 pt-3 border-top">
                    <span class="text-uppercase small fw-bold text-muted">Total</span>
                    <span class="vr-grand" id="vrTotal">R$ 0,00</span>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted mb-1 d-flex justify-content-between align-items-baseline">
                        <span>Cliente <small class="text-muted" id="vrClienteHint">(opcional)</small></span>
                        <small id="vrClienteReq" class="text-danger fw-semibold" style="display:none;">* Obrigatório</small>
                    </label>
                    <select id="vrCliente" name="cliente_id" class="form-select form-select-sm">
                        <option value="">— Consumidor avulso —</option>
                        <?php foreach (($clientes ?? []) as $c): ?>
                            <option value="<?= (int)$c['id'] ?>">
                                <?= htmlspecialchars((string)$c['nome']) ?>
                                <?php if (!empty($c['cpf_cnpj'])): ?> · <?= htmlspecialchars((string)$c['cpf_cnpj']) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Modo de venda</label>
                    <div class="vr-modo" id="vrModo">
                        <button type="button" class="btn btn-sm active" data-modo="pago_agora">
                            <i class="fas fa-bolt me-1"></i>Pago Agora
                        </button>
                        <button type="button" class="btn btn-sm" data-modo="cobrar_depois">
                            <i class="fas fa-clock me-1"></i>Cobrar Depois
                        </button>
                    </div>
                </div>

                <div id="vrPayWrap">
                    <label class="form-label small text-muted mb-1">Forma de pagamento</label>
                    <div class="vr-pay" id="vrPay">
                        <?php foreach ($formasPagamento as $fp):
                            $label = $labelPorTipo[$fp['tipo']] ?? (string)$fp['nome'];
                        ?>
                            <button type="button" class="btn btn-outline-primary"
                                    data-id="<?= (int)$fp['id'] ?>"
                                    data-tipo="<?= htmlspecialchars((string)$fp['tipo']) ?>">
                                <?= htmlspecialchars($label) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="alert alert-warning mt-3 py-2 mb-0" id="vrHint" style="display:none; font-size:.8125rem;">
                    <i class="fas fa-clock me-1"></i>
                    Esta venda irá para o <strong>Fluxo (Kanban)</strong> como <strong>Pendente</strong>.
                    Forma de pagamento será escolhida ao faturar.
                </div>

                <div class="d-grid gap-2 mt-3">
                    <button type="button" id="vrFinish" class="btn btn-primary btn-lg" disabled>
                        <i class="fas fa-check me-1"></i> Finalizar <kbd class="bg-dark text-white small ms-1">F9</kbd>
                    </button>
                    <button type="button" id="vrClear" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-eraser me-1"></i> Limpar Carrinho <kbd class="bg-light text-dark small ms-1">ESC</kbd>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const CSRF = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const URL_SEARCH = <?= json_encode($searchUrl, JSON_UNESCAPED_SLASHES) ?>;
    const URL_FINISH = <?= json_encode($finalizeUrl, JSON_UNESCAPED_SLASHES) ?>;

    const fmt = n => 'R$ ' + (Number(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const $ = id => document.getElementById(id);
    const search = $('vrSearch');
    const grid = $('vrGrid');
    const cartEl = $('vrCart');
    const countEl = $('vrCount');
    const subEl = $('vrSubtotal');
    const totalEl = $('vrTotal');
    const descTipo = $('vrDescTipo');
    const descValor = $('vrDescValor');
    const btnClear = $('vrClear');
    const btnFinish = $('vrFinish');
    const payArea = $('vrPay');
    const payWrap = $('vrPayWrap');
    const hintArea = $('vrHint');
    const modoArea = $('vrModo');

    const cart = new Map();
    let selectedFormaId = null;
    let selectedFormaTipo = null;
    let modoVenda = 'pago_agora';
    const clienteSel = $('vrCliente');
    const clienteHint = $('vrClienteHint');
    const clienteReq = $('vrClienteReq');

    function atualizarStatusCliente() {
        const precisa = modoVenda === 'cobrar_depois' || selectedFormaTipo === 'AF';
        clienteHint.style.display = precisa ? 'none' : '';
        clienteReq.style.display = precisa ? '' : 'none';
        clienteSel.classList.toggle('is-invalid', precisa && !clienteSel.value);
    }
    clienteSel.addEventListener('change', () => { atualizarStatusCliente(); updateFinishState(); });

    function escapeHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch])); }
    function escapeAttr(s) { return escapeHtml(s).replace(/"/g, '&quot;'); }

    // Busca
    let searchTimer = null;
    search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(doSearch, 200); });

    async function doSearch() {
        const q = search.value.trim();
        if (q.length < 1) {
            grid.innerHTML = '<div class="text-muted text-center py-5 w-100" style="grid-column: 1 / -1;"><i class="fas fa-search fa-2x mb-2 opacity-50"></i><div>Digite para buscar itens</div></div>';
            return;
        }
        try {
            const res = await fetch(URL_SEARCH + '&q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const data = await res.json();
            renderGrid(data.itens || []);
        } catch (e) {
            grid.innerHTML = '<div class="text-danger text-center py-5 w-100" style="grid-column: 1 / -1;">Erro na busca</div>';
        }
    }

    function renderGrid(itens) {
        if (!itens.length) {
            grid.innerHTML = '<div class="text-muted text-center py-5 w-100" style="grid-column: 1 / -1;">Nenhum item encontrado</div>';
            return;
        }
        grid.innerHTML = itens.map(it => {
            const isSrv = it.tipo === 'SERVICO';
            return `<button type="button" class="vr-item" data-tipo="${it.tipo}" data-id="${it.id}" data-nome="${escapeAttr(it.nome)}" data-valor="${it.valor}">
                <span class="tipo ${isSrv ? 'srv' : ''}">${isSrv ? 'Serviço' : 'Produto'}${it.codigo ? ' · ' + escapeHtml(it.codigo) : ''}</span>
                <span class="nome">${escapeHtml(it.nome)}</span>
                <span class="valor">${fmt(it.valor)}</span>
            </button>`;
        }).join('');
    }

    grid.addEventListener('click', e => {
        const card = e.target.closest('.vr-item');
        if (!card) return;
        addToCart({ tipo: card.dataset.tipo, id: parseInt(card.dataset.id, 10), nome: card.dataset.nome, valor: parseFloat(card.dataset.valor) });
    });

    function cartKey(it) { return it.tipo + ':' + it.id; }
    function addToCart(it) {
        const k = cartKey(it);
        if (cart.has(k)) cart.get(k).qtd += 1;
        else cart.set(k, { ...it, qtd: 1 });
        renderCart();
    }
    function removeFromCart(k) { cart.delete(k); renderCart(); }
    function setQty(k, qtd) {
        const row = cart.get(k);
        if (!row) return;
        row.qtd = Math.max(1, parseFloat(qtd) || 1);
        renderCart();
    }

    function renderCart() {
        if (cart.size === 0) {
            cartEl.innerHTML = '<div class="text-muted text-center py-5"><i class="fas fa-basket-shopping fa-2x mb-2 opacity-50"></i><div>Adicione itens à esquerda</div></div>';
            countEl.textContent = '0 itens';
            subEl.textContent = fmt(0);
            totalEl.textContent = fmt(0);
            btnFinish.disabled = true;
            return;
        }
        let subtotal = 0;
        const rows = [];
        for (const [k, r] of cart.entries()) {
            const subt = r.qtd * r.valor;
            subtotal += subt;
            rows.push(`
                <div class="vr-cart-item" data-k="${k}">
                    <div class="vr-cart-item-nome">${escapeHtml(r.nome)}</div>
                    <div class="vr-cart-item-qty">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-act="dec">−</button>
                        <input type="number" class="form-control form-control-sm vr-qty-input" value="${r.qtd}" min="1" step="1">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-act="inc">+</button>
                        <span class="vr-cart-item-unit">× ${fmt(r.valor)}</span>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" data-act="rm" style="font-size:.7rem;">Remover</button>
                    </div>
                    <div class="vr-cart-item-total">${fmt(subt)}</div>
                </div>
            `);
        }
        cartEl.innerHTML = rows.join('');
        countEl.textContent = cart.size + (cart.size === 1 ? ' item' : ' itens');
        subEl.textContent = fmt(subtotal);
        totalEl.textContent = fmt(computeTotal(subtotal));
        updateFinishState();
    }

    function computeTotal(subtotal) {
        const tipo = descTipo.value;
        const val = parseFloat(descValor.value) || 0;
        if (!tipo || val <= 0) return subtotal;
        if (tipo === 'PERCENTUAL') return Math.max(0, subtotal * (1 - Math.min(100, val) / 100));
        return Math.max(0, subtotal - val);
    }

    cartEl.addEventListener('click', e => {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const row = btn.closest('.vr-cart-item');
        const k = row.dataset.k;
        const entry = cart.get(k);
        if (!entry) return;
        const act = btn.dataset.act;
        if (act === 'inc') setQty(k, entry.qtd + 1);
        else if (act === 'dec') setQty(k, entry.qtd - 1);
        else if (act === 'rm') removeFromCart(k);
    });
    cartEl.addEventListener('change', e => {
        if (!e.target.classList.contains('vr-qty-input')) return;
        const row = e.target.closest('.vr-cart-item');
        setQty(row.dataset.k, e.target.value);
    });

    descTipo.addEventListener('change', () => {
        descValor.disabled = descTipo.value === '';
        if (descTipo.value === '') descValor.value = 0;
        renderCart();
    });
    descValor.addEventListener('input', renderCart);

    payArea.addEventListener('click', e => {
        const b = e.target.closest('button[data-id]');
        if (!b) return;
        selectedFormaId = parseInt(b.dataset.id, 10);
        selectedFormaTipo = b.dataset.tipo || null;
        payArea.querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
        atualizarStatusCliente();
        updateFinishState();
    });

    modoArea.addEventListener('click', e => {
        const b = e.target.closest('button[data-modo]');
        if (!b) return;
        modoVenda = b.dataset.modo;
        modoArea.querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
        if (modoVenda === 'cobrar_depois') {
            payWrap.style.display = 'none';
            hintArea.style.display = '';
            selectedFormaId = null;
            selectedFormaTipo = null;
            payArea.querySelectorAll('button').forEach(x => x.classList.remove('active'));
        } else {
            payWrap.style.display = '';
            hintArea.style.display = 'none';
        }
        atualizarStatusCliente();
        updateFinishState();
    });

    function updateFinishState() {
        if (cart.size === 0) { btnFinish.disabled = true; return; }
        const precisaCliente = modoVenda === 'cobrar_depois' || selectedFormaTipo === 'AF';
        if (precisaCliente && !clienteSel.value) { btnFinish.disabled = true; return; }
        if (modoVenda === 'pago_agora') { btnFinish.disabled = !selectedFormaId; return; }
        btnFinish.disabled = false;
    }

    btnClear.addEventListener('click', () => {
        if (cart.size === 0) return;
        if (typeof Swal === 'undefined') {
            if (!confirm('Limpar o carrinho?')) return;
            cart.clear();
            renderCart();
            return;
        }
        Swal.fire({
            title: 'Limpar carrinho?',
            text: cart.size + ' item(ns) serão removidos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Limpar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: { confirmButton: 'btn btn-warning mx-1', cancelButton: 'btn btn-outline-secondary mx-1' }
        }).then(r => { if (r.isConfirmed) { cart.clear(); renderCart(); } });
    });

    btnFinish.addEventListener('click', finalizar);
    async function finalizar() {
        if (btnFinish.disabled) return;
        btnFinish.disabled = true;

        const itens = [];
        for (const r of cart.values()) {
            itens.push({
                tipo_item: r.tipo,
                produto_id: r.tipo === 'PRODUTO' ? r.id : null,
                servico_id: r.tipo === 'SERVICO' ? r.id : null,
                nome_item: r.nome,
                quantidade: r.qtd,
                valor_unitario: r.valor,
            });
        }
        const body = {
            modo: modoVenda,
            cliente_id: clienteSel.value ? parseInt(clienteSel.value, 10) : null,
            forma_pagamento_id: modoVenda === 'pago_agora' ? selectedFormaId : null,
            desconto_tipo: descTipo.value || null,
            desconto_valor: parseFloat(descValor.value) || 0,
            itens,
            _token: CSRF,
        };
        try {
            const res = await fetch(URL_FINISH, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!data.ok) {
                swalOrAlert('Erro', (data.erros || ['Falha']).join(' · '), 'error');
                updateFinishState();
                return;
            }
            const msg = modoVenda === 'cobrar_depois'
                ? 'Venda #' + data.numero + ' enviada ao Fluxo'
                : 'Venda #' + data.numero + ' finalizada';
            swalOrAlert('Sucesso', msg, 'success');
            cart.clear();
            selectedFormaId = null;
            selectedFormaTipo = null;
            descTipo.value = '';
            descValor.value = 0;
            descValor.disabled = true;
            payArea.querySelectorAll('button').forEach(x => x.classList.remove('active'));
            modoVenda = 'pago_agora';
            modoArea.querySelectorAll('button').forEach(b => b.classList.toggle('active', b.dataset.modo === 'pago_agora'));
            payWrap.style.display = '';
            hintArea.style.display = 'none';
            clienteSel.value = '';
            atualizarStatusCliente();
            renderCart();
            search.focus();
        } catch (e) {
            swalOrAlert('Erro', 'Erro de rede', 'error');
            updateFinishState();
        }
    }

    function swalOrAlert(titulo, msg, tipo) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ title: titulo, text: msg, icon: tipo, timer: tipo === 'success' ? 1800 : undefined, showConfirmButton: tipo !== 'success' });
        } else {
            alert(titulo + ': ' + msg);
        }
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.activeElement !== descValor && document.activeElement !== search) {
            btnClear.click();
        } else if (e.key === 'F9') {
            e.preventDefault();
            if (!btnFinish.disabled) btnFinish.click();
        }
    });
})();
</script>
