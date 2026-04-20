<?php
/**
 * @var array $caixa
 * @var array $vendas
 * @var array $totais
 * @var array $formasPagamento
 * @var array $filtros
 * @var string $csrfToken
 */
$baseUrl     = tenantCleanUrl('pdv/historico');
$novaUrl     = tenantCleanUrl('pdv/venda');
$caixaUrl    = tenantCleanUrl('pdv/caixa/' . (int)$caixa['id']);
$fluxoUrl    = tenantCleanUrl('pdv/fluxo');
$detalheUrl  = tenantCleanUrl('pdv/historico') . '?action=detalhe';
$cancelarUrl = tenantCleanUrl('pdv/historico') . '?action=cancelar';

$brl = static fn ($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
$fmtDt = static fn (?string $s): string => $s ? date('d/m/Y H:i', strtotime($s)) : '--';
$caixaAberto = ($caixa['status'] ?? '') === 'aberto';

$labelPorTipo = [
    'D' => 'Dinheiro', 'PIX' => 'PIX', 'CC' => 'Crédito', 'CD' => 'Débito',
    'BOL' => 'Boleto', 'TB' => 'Transferência', 'AF' => 'A Faturar',
];

$statusBadge = [
    'faturado' => ['label' => 'Faturado', 'cls' => 'bg-success'],
    'cancelado' => ['label' => 'Cancelado', 'cls' => 'bg-danger'],
    'pendente' => ['label' => 'Pendente', 'cls' => 'bg-warning text-dark'],
    'em_processo' => ['label' => 'Em Processo', 'cls' => 'bg-info text-dark'],
    'concluido' => ['label' => 'Concluído', 'cls' => 'bg-primary'],
];
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Histórico de Vendas</h1>
            <p class="text-muted mb-0 small">
                Caixa <strong>#<?= (int)$caixa['numero_caixa'] ?></strong>
                · Aberto em <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$caixa['data_abertura']))) ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= htmlspecialchars($caixaUrl) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Caixa
            </a>
            <a href="<?= htmlspecialchars($fluxoUrl) ?>" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-columns me-1"></i> Fluxo
            </a>
            <a href="<?= htmlspecialchars($novaUrl) ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-bolt me-1"></i> Nova Venda
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <?php $msg = $_SESSION['mensagem']; unset($_SESSION['mensagem']); ?>
        <div class="alert alert-<?= $msg['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $msg['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Totalizadores -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total Faturado</div>
                    <div class="h3 mb-0 text-success"><?= $brl($totais['total']) ?></div>
                    <div class="small text-muted mt-1">Caixa aberto</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Qtd. Vendas</div>
                    <div class="h3 mb-0"><?= (int)$totais['qtd'] ?></div>
                    <div class="small text-muted mt-1">Status faturado</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Ticket Médio</div>
                    <div class="h3 mb-0"><?= $brl($totais['ticket_medio']) ?></div>
                    <div class="small text-muted mt-1">Total / Qtd</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow h-100 border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-2">Breakdown</div>
                    <?php foreach ($totais['por_forma'] as $fp): ?>
                        <?php if ($fp['qtd'] === 0) continue; ?>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted"><?= htmlspecialchars($labelPorTipo[$fp['tipo']] ?? $fp['nome']) ?></span>
                            <span class="fw-semibold"><?= $brl($fp['total']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($totais['qtd'] === 0): ?>
                        <div class="small text-muted">—</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?= htmlspecialchars($baseUrl) ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Forma de pagamento</label>
                    <select name="forma_pagamento_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($formasPagamento as $fp): ?>
                            <option value="<?= (int)$fp['id'] ?>"
                                <?= ((int)($filtros['forma_pagamento_id'] ?? 0) === (int)$fp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($labelPorTipo[$fp['tipo']] ?? $fp['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="faturado" <?= ($filtros['status'] ?? '') === 'faturado' ? 'selected' : '' ?>>Faturado</option>
                        <option value="cancelado" <?= ($filtros['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Desde</label>
                    <input type="datetime-local" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filtros['desde'] ?? '')) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Até</label>
                    <input type="date" name="ate" class="form-control form-control-sm" value="<?= htmlspecialchars(substr((string)($filtros['ate'] ?? ''), 0, 10)) ?>">
                </div>
                <div class="col-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <?php if (empty($vendas)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-receipt fa-3x opacity-25 mb-3"></i>
                    <div>Nenhuma venda neste caixa.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Data/Hora</th>
                                <th>Cliente / Resumo</th>
                                <th>Pagamento</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendas as $v):
                                $sb = $statusBadge[$v['status']] ?? ['label' => $v['status'], 'cls' => 'bg-secondary'];
                            ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= (int)$v['numero'] ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($fmtDt($v['created_at'])) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars((string)($v['cliente_nome'] ?? 'Cliente avulso')) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string)($v['resumo_itens'] ?? '')) ?> <span class="badge bg-light text-dark"><?= (int)$v['qtd_itens'] ?> itens</span></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($v['forma_pagamento_tipo'])): ?>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($labelPorTipo[$v['forma_pagamento_tipo']] ?? $v['forma_pagamento_nome']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold"><?= $brl($v['valor_total']) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $sb['cls'] ?>" title="<?= htmlspecialchars((string)($v['motivo_cancelamento'] ?? '')) ?>">
                                            <?= $sb['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-primary btn-sm" data-act="detalhe" data-id="<?= (int)$v['id'] ?>" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($v['status'] === 'faturado' && $caixaAberto): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-act="cancelar" data-id="<?= (int)$v['id'] ?>" data-numero="<?= (int)$v['numero'] ?>" title="Cancelar">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($v['status'] === 'faturado'): ?>
                                            <span class="fiscal-actions" data-venda-id="<?= (int)$v['id'] ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detalhe -->
<div class="modal fade" id="modalDetalhe" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Venda <span id="detNum" class="text-primary">#—</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detBody">
                <div class="text-muted text-center py-4">Carregando…</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelar -->
<div class="modal fade" id="modalCancelar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancelar venda <span id="cancelNum" class="text-danger">#—</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Esta ação marca a venda como <strong class="text-danger">cancelada</strong>. O registro é preservado no banco.</p>
                <label class="form-label small">Motivo (opcional)</label>
                <textarea id="cancelMotivo" class="form-control" rows="3" placeholder="Ex.: erro de digitação, cliente desistiu..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="button" class="btn btn-danger" id="cancelConfirm">Confirmar cancelamento</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const CSRF = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const URL_DET = <?= json_encode($detalheUrl, JSON_UNESCAPED_SLASHES) ?>;
    const URL_CANCEL = <?= json_encode($cancelarUrl, JSON_UNESCAPED_SLASHES) ?>;

    const brl = n => 'R$ ' + (Number(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));

    const modalDetalhe = new bootstrap.Modal(document.getElementById('modalDetalhe'));
    const modalCancelar = new bootstrap.Modal(document.getElementById('modalCancelar'));
    const detBody = document.getElementById('detBody');
    const detNum = document.getElementById('detNum');
    const cancelNum = document.getElementById('cancelNum');
    const cancelMotivo = document.getElementById('cancelMotivo');
    const cancelConfirm = document.getElementById('cancelConfirm');
    let cancelVendaId = null;

    document.querySelectorAll('[data-act]').forEach(btn => {
        btn.addEventListener('click', () => {
            const act = btn.dataset.act;
            const id = parseInt(btn.dataset.id, 10);
            if (act === 'detalhe') verDetalhe(id);
            else if (act === 'cancelar') abrirCancelamento(id, btn.dataset.numero);
        });
    });

    async function verDetalhe(id) {
        detNum.textContent = '#…';
        detBody.innerHTML = '<div class="text-muted text-center py-4">Carregando…</div>';
        modalDetalhe.show();
        try {
            const res = await fetch(URL_DET + '&id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!data.ok) {
                detBody.innerHTML = '<div class="alert alert-danger">' + esc((data.erros || ['Erro'])[0]) + '</div>';
                return;
            }
            renderDetalhe(data.venda, data.itens || []);
        } catch (e) {
            detBody.innerHTML = '<div class="alert alert-danger">Erro de rede</div>';
        }
    }

    function renderDetalhe(v, itens) {
        detNum.textContent = '#' + v.numero;
        const desc = v.desconto_tipo === 'PERCENTUAL'
            ? (Number(v.desconto_valor) || 0) + '%'
            : (v.desconto_tipo ? brl(v.desconto_valor) : '—');
        const dt = v.created_at ? new Date(v.created_at).toLocaleString('pt-BR') : '--';
        const itensHtml = itens.map(i => `
            <tr>
                <td>${esc(i.nome_item)} <span class="badge bg-${i.tipo_item === 'SERVICO' ? 'info' : 'secondary'} ms-1">${esc(i.tipo_item)}</span></td>
                <td class="text-end">${Number(i.quantidade).toLocaleString('pt-BR')}</td>
                <td class="text-end">${brl(i.valor_unitario)}</td>
                <td class="text-end fw-bold">${brl(i.valor_total_item)}</td>
            </tr>
        `).join('');
        detBody.innerHTML = `
            <dl class="row small mb-3">
                <dt class="col-sm-3 text-muted">Data</dt><dd class="col-sm-9">${esc(dt)}</dd>
                <dt class="col-sm-3 text-muted">Operador</dt><dd class="col-sm-9">${esc(v.operador_nome)}</dd>
                <dt class="col-sm-3 text-muted">Cliente</dt><dd class="col-sm-9">${esc(v.cliente_nome || 'Avulso')}</dd>
                <dt class="col-sm-3 text-muted">Pagamento</dt><dd class="col-sm-9">${esc(v.forma_nome || '—')}</dd>
                <dt class="col-sm-3 text-muted">Desconto</dt><dd class="col-sm-9">${esc(desc)}</dd>
                <dt class="col-sm-3 text-muted">Status</dt><dd class="col-sm-9"><span class="badge bg-${v.status === 'faturado' ? 'success' : 'danger'}">${esc(v.status)}</span></dd>
                ${v.observacoes ? `<dt class="col-sm-3 text-muted">Observações</dt><dd class="col-sm-9">${esc(v.observacoes)}</dd>` : ''}
            </dl>
            <table class="table table-sm">
                <thead class="table-light">
                    <tr><th>Item</th><th class="text-end">Qtd</th><th class="text-end">Unit.</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>${itensHtml}</tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="3" class="text-end">Total</th>
                        <th class="text-end text-success h5 mb-0">${brl(v.valor_total)}</th>
                    </tr>
                </tfoot>
            </table>
        `;
    }

    function abrirCancelamento(id, numero) {
        cancelVendaId = id;
        cancelNum.textContent = '#' + numero;
        cancelMotivo.value = '';
        modalCancelar.show();
    }

    cancelConfirm.addEventListener('click', async () => {
        if (!cancelVendaId) return;
        cancelConfirm.disabled = true;
        try {
            const res = await fetch(URL_CANCEL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ id: cancelVendaId, motivo: cancelMotivo.value.trim(), _token: CSRF }),
            });
            const data = await res.json();
            if (!data.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ title: 'Erro', text: (data.erros || ['Falha']).join(' · '), icon: 'error' });
                }
                cancelConfirm.disabled = false;
                return;
            }
            window.location.reload();
        } catch (e) {
            alert('Erro de rede');
            cancelConfirm.disabled = false;
        }
    });
})();
</script>

<script>
// Fiscal: popula botoes NFC-e/NFS-e condicionais por venda
(function(){
    const FISCAL_ENDPOINT = '/sistema_dm/public/admin/fiscal/emitir.php';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function brl(v){ return (Number(v)||0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); }

    document.querySelectorAll('.fiscal-actions').forEach(function(wrap){
        const vendaId = wrap.dataset.vendaId;
        fetch(FISCAL_ENDPOINT + '?action=estado-venda&venda_id=' + encodeURIComponent(vendaId))
            .then(r => r.json()).then(function(data){
                if (!data || !data.ok) return;
                const parts = [];
                if (data.tem_produto) {
                    if (data.nfce && data.nfce.status === 'autorizada') {
                        parts.push('<span class="badge bg-success ms-1" title="NFC-e '+data.nfce.numero+'"><i class="fas fa-check me-1"></i>NFC-e</span>');
                    } else {
                        parts.push('<button type="button" class="btn btn-sm btn-primary ms-1 btn-emitir-fiscal" data-tipo="nfce" data-venda="'+vendaId+'" title="Emitir NFC-e ('+brl(data.total_produtos)+')"><i class="fas fa-file-invoice me-1"></i>NFC-e</button>');
                    }
                }
                if (data.tem_servico) {
                    if (data.nfse && data.nfse.status === 'autorizada') {
                        parts.push('<span class="badge bg-success ms-1" title="NFS-e '+(data.nfse.numero_nfse||data.nfse.numero_rps)+'"><i class="fas fa-check me-1"></i>NFS-e</span>');
                    } else {
                        parts.push('<button type="button" class="btn btn-sm btn-info ms-1 btn-emitir-fiscal" data-tipo="nfse" data-venda="'+vendaId+'" title="Emitir NFS-e ('+brl(data.total_servicos)+')"><i class="fas fa-file-contract me-1"></i>NFS-e</button>');
                    }
                }
                wrap.innerHTML = parts.join('');
                wrap.querySelectorAll('.btn-emitir-fiscal').forEach(function(btn){
                    btn.addEventListener('click', function(){
                        const tipo = btn.dataset.tipo;
                        const vid = btn.dataset.venda;
                        Swal.fire({
                            title: 'Emitir ' + (tipo==='nfce' ? 'NFC-e' : 'NFS-e') + '?',
                            text: 'Venda #' + vid,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Sim, emitir',
                            cancelButtonText: 'Cancelar',
                            buttonsStyling: false,
                            customClass: { confirmButton: 'btn btn-success mx-1', cancelButton: 'btn btn-secondary mx-1' }
                        }).then(function(r){
                            if (!r.isConfirmed) return;
                            btn.disabled = true;
                            const fd = new FormData();
                            fd.append('action', 'emitir-' + tipo);
                            fd.append('venda_id', vid);
                            fd.append('csrf_token', CSRF);
                            fetch(FISCAL_ENDPOINT, { method:'POST', body: fd, headers:{ 'X-CSRF-Token': CSRF } })
                                .then(rr => rr.json().catch(() => ({ok:false, erro:'Resposta invalida'})))
                                .then(data => {
                                    Swal.fire({
                                        icon: data.ok ? 'success' : 'error',
                                        title: data.ok ? 'Emitida' : 'Falha',
                                        html: '<pre class="text-start small mb-0">' + JSON.stringify(data, null, 2) + '</pre>',
                                        buttonsStyling: false,
                                        customClass: { confirmButton: 'btn btn-' + (data.ok ? 'success' : 'danger') }
                                    }).then(() => { if (data.ok) location.reload(); else btn.disabled = false; });
                                });
                        });
                    });
                });
            });
    });
})();
</script>
