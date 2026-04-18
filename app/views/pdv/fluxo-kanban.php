<?php
/**
 * @var array $caixa
 * @var array $colunas
 * @var array $formasPagamento
 * @var string $csrfToken
 */
$caixaUrl = tenantCleanUrl('pdv/caixa/' . (int)$caixa['id']);
$vendaUrl = tenantCleanUrl('pdv/venda');
$historicoUrl = tenantCleanUrl('pdv/historico');
$moverUrl = tenantCleanUrl('pdv/fluxo') . '?action=mover';

$labelPorTipo = [
    'D' => 'Dinheiro', 'PIX' => 'PIX', 'CC' => 'Crédito', 'CD' => 'Débito',
    'BOL' => 'Boleto', 'TB' => 'Transferência', 'AF' => 'A Faturar',
];

$colunasMeta = [
    'pendente'    => ['label' => 'Pendente',     'color' => 'warning', 'icon' => 'fa-circle-pause'],
    'em_processo' => ['label' => 'Em Processo',  'color' => 'info',    'icon' => 'fa-cog fa-spin'],
    'concluido'   => ['label' => 'Concluído',    'color' => 'primary', 'icon' => 'fa-check-circle'],
    'faturado'    => ['label' => 'Faturado',     'color' => 'success', 'icon' => 'fa-receipt'],
];

$brl = static fn ($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
$fmtDt = static fn (?string $s): string => $s ? date('d/m H:i', strtotime($s)) : '--';
?>
<style>
.kb-board { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
@media (max-width: 991.98px) { .kb-board { grid-template-columns: 1fr 1fr; } }
@media (max-width: 640px) { .kb-board { grid-template-columns: 1fr; } }

.kb-col { display: flex; flex-direction: column; min-height: 400px; }
.kb-col .card-header { border-top: 3px solid var(--bs-col-color); }
.kb-col-body {
    flex: 1;
    padding: .5rem;
    background: var(--bg-secondary);
    display: flex;
    flex-direction: column;
    gap: .5rem;
    min-height: 120px;
    overflow-y: auto;
    max-height: 70vh;
    border-radius: 0 0 var(--radius) var(--radius);
}
.kb-col-body.is-drop-target {
    outline: 2px dashed var(--accent);
    outline-offset: -6px;
    background: var(--accent-light);
}

.kb-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: .75rem;
    cursor: grab;
    display: flex;
    flex-direction: column;
    gap: .35rem;
    transition: var(--transition);
    box-shadow: var(--shadow);
}
.kb-card:hover { border-color: var(--bs-col-color); transform: translateY(-1px); box-shadow: var(--shadow-md); }
.kb-card:active { cursor: grabbing; }
.kb-card.is-dragging { opacity: .4; transform: rotate(-1deg); }
.kb-card-top { display: flex; justify-content: space-between; align-items: baseline; }
.kb-card-num { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--bs-col-color); }
.kb-card-time { font-size: .7rem; color: var(--text-muted); }
.kb-card-cli { font-size: .8125rem; color: var(--text-primary); font-weight: 500; }
.kb-card-itens { font-size: .75rem; color: var(--text-muted); line-height: 1.35; }
.kb-card-val { font-size: 1rem; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; text-align: right; padding-top: .35rem; border-top: 1px dashed var(--border-color); }
.kb-card-actions { display: flex; gap: .25rem; padding-top: .375rem; border-top: 1px solid var(--border-color); }
.kb-empty { text-align: center; color: var(--text-muted); font-size: .75rem; padding: 1.5rem .5rem; }
</style>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Fluxo de Vendas</h1>
            <p class="text-muted mb-0 small">
                Arraste cards entre colunas para avançar o status.
                Caixa <strong>#<?= (int)$caixa['numero_caixa'] ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= htmlspecialchars($caixaUrl) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Caixa
            </a>
            <a href="<?= htmlspecialchars($historicoUrl) ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-list me-1"></i> Histórico
            </a>
            <a href="<?= htmlspecialchars($vendaUrl) ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Nova Venda
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

    <div class="kb-board" id="kbBoard">
        <?php foreach ($colunasMeta as $slug => $meta):
            $qtd = count($colunas[$slug] ?? []);
            $total = 0.0;
            foreach (($colunas[$slug] ?? []) as $v) $total += (float)$v['valor_total'];
        ?>
            <div class="card shadow kb-col" style="--bs-col-color: var(--bs-<?= $meta['color'] ?>);" data-status="<?= $slug ?>">
                <div class="card-header d-flex justify-content-between align-items-center py-2 bg-<?= $meta['color'] ?> bg-opacity-10">
                    <h6 class="mb-0 text-<?= $meta['color'] ?>">
                        <i class="fas <?= $meta['icon'] ?> me-1"></i> <?= $meta['label'] ?>
                    </h6>
                    <div class="text-end">
                        <span class="badge bg-<?= $meta['color'] ?>"><?= $qtd ?></span>
                        <?php if ($qtd > 0): ?>
                            <div class="small text-muted mt-1" style="font-size:.7rem;"><?= $brl($total) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="kb-col-body" data-drop="<?= $slug ?>">
                    <?php if (empty($colunas[$slug])): ?>
                        <div class="kb-empty">—</div>
                    <?php else: ?>
                        <?php foreach ($colunas[$slug] as $v): ?>
                            <div class="kb-card" style="--bs-col-color: var(--bs-<?= $meta['color'] ?>);" draggable="true"
                                 data-id="<?= (int)$v['id'] ?>"
                                 data-numero="<?= (int)$v['numero'] ?>"
                                 data-status="<?= htmlspecialchars((string)$v['status']) ?>">
                                <div class="kb-card-top">
                                    <span class="kb-card-num">#<?= (int)$v['numero'] ?></span>
                                    <span class="kb-card-time"><?= htmlspecialchars($fmtDt($v['created_at'])) ?></span>
                                </div>
                                <div class="kb-card-cli"><?= htmlspecialchars((string)($v['cliente_nome'] ?? 'Cliente avulso')) ?></div>
                                <div class="kb-card-itens">
                                    <?= htmlspecialchars((string)($v['resumo_itens'] ?? '')) ?>
                                    <span class="badge bg-light text-dark ms-1" style="font-size:.65rem;"><?= (int)$v['qtd_itens'] ?> itens</span>
                                </div>
                                <div class="kb-card-val"><?= $brl($v['valor_total']) ?></div>
                                <?php if ($slug !== 'faturado'): ?>
                                <div class="kb-card-actions">
                                    <?php if ($slug === 'pendente'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info w-100" data-act="avancar" data-to="em_processo">
                                            <i class="fas fa-arrow-right"></i> Em processo
                                        </button>
                                    <?php elseif ($slug === 'em_processo'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100" data-act="avancar" data-to="concluido">
                                            <i class="fas fa-arrow-right"></i> Concluído
                                        </button>
                                    <?php elseif ($slug === 'concluido'): ?>
                                        <button type="button" class="btn btn-sm btn-success w-100" data-act="faturar">
                                            <i class="fas fa-dollar-sign"></i> Faturar
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal faturar -->
<div class="modal fade" id="modalFaturar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Faturar venda <span id="faturarNum" class="text-success">#—</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Escolha a forma de pagamento:</p>
                <div class="row g-2" id="faturarPay">
                    <?php foreach ($formasPagamento as $fp):
                        $label = $labelPorTipo[$fp['tipo']] ?? (string)$fp['nome'];
                    ?>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-success w-100" data-id="<?= (int)$fp['id'] ?>">
                                <?= htmlspecialchars($label) ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="faturarConfirm" disabled>
                    <i class="fas fa-check me-1"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const CSRF = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const URL_MOVER = <?= json_encode($moverUrl, JSON_UNESCAPED_SLASHES) ?>;

    const board = document.getElementById('kbBoard');
    const modalFaturar = new bootstrap.Modal(document.getElementById('modalFaturar'));
    const faturarNum = document.getElementById('faturarNum');
    const faturarPay = document.getElementById('faturarPay');
    const faturarConfirm = document.getElementById('faturarConfirm');

    let faturarVendaId = null;
    let faturarFormaId = null;
    let draggedCard = null;

    board.addEventListener('dragstart', e => {
        const card = e.target.closest('.kb-card');
        if (!card) return;
        draggedCard = card;
        card.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    board.addEventListener('dragend', () => {
        if (draggedCard) draggedCard.classList.remove('is-dragging');
        draggedCard = null;
        board.querySelectorAll('.kb-col-body').forEach(c => c.classList.remove('is-drop-target'));
    });
    board.addEventListener('dragover', e => {
        const body = e.target.closest('.kb-col-body');
        if (!body) return;
        e.preventDefault();
        board.querySelectorAll('.kb-col-body').forEach(c => c.classList.toggle('is-drop-target', c === body));
    });
    board.addEventListener('drop', async e => {
        const body = e.target.closest('.kb-col-body');
        if (!body || !draggedCard) return;
        e.preventDefault();
        const destino = body.dataset.drop;
        const origem = draggedCard.dataset.status;
        if (destino === origem) return;

        const ordem = { pendente: 1, em_processo: 2, concluido: 3, faturado: 4 };
        if ((ordem[destino] || 0) < (ordem[origem] || 0)) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Não permitido', text: 'O fluxo só avança.', timer: 1600, showConfirmButton: false });
            }
            return;
        }

        const id = parseInt(draggedCard.dataset.id, 10);
        const numero = draggedCard.dataset.numero;

        if (destino === 'faturado') {
            abrirFaturarModal(id, numero);
            return;
        }
        await moverVenda(id, destino);
    });

    board.addEventListener('click', e => {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const card = btn.closest('.kb-card');
        const id = parseInt(card.dataset.id, 10);
        const act = btn.dataset.act;
        if (act === 'faturar') abrirFaturarModal(id, card.dataset.numero);
        else if (act === 'avancar') moverVenda(id, btn.dataset.to);
    });

    async function moverVenda(id, status, formaId = null) {
        try {
            const res = await fetch(URL_MOVER, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ id, status, forma_pagamento_id: formaId, _token: CSRF }),
            });
            const data = await res.json();
            if (!data.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Erro', text: (data.erros || ['Falha']).join(' · ') });
                }
                return false;
            }
            window.location.reload();
            return true;
        } catch (e) {
            alert('Erro de rede');
            return false;
        }
    }

    function abrirFaturarModal(id, numero) {
        faturarVendaId = id;
        faturarFormaId = null;
        faturarNum.textContent = '#' + numero;
        faturarPay.querySelectorAll('button[data-id]').forEach(b => b.classList.remove('btn-success'));
        faturarPay.querySelectorAll('button[data-id]').forEach(b => b.classList.add('btn-outline-success'));
        faturarConfirm.disabled = true;
        modalFaturar.show();
    }

    faturarPay.addEventListener('click', e => {
        const b = e.target.closest('button[data-id]');
        if (!b) return;
        faturarFormaId = parseInt(b.dataset.id, 10);
        faturarPay.querySelectorAll('button[data-id]').forEach(x => {
            x.classList.toggle('btn-success', x === b);
            x.classList.toggle('btn-outline-success', x !== b);
        });
        faturarConfirm.disabled = false;
    });

    faturarConfirm.addEventListener('click', async () => {
        if (!faturarVendaId || !faturarFormaId) return;
        faturarConfirm.disabled = true;
        const ok = await moverVenda(faturarVendaId, 'faturado', faturarFormaId);
        if (!ok) faturarConfirm.disabled = false;
    });
})();
</script>
