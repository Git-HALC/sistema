<?php
/**
 * @var array $caixa
 * @var array $formas
 * @var array<int, float> $totaisSistema
 * @var array<int, float> $valoresInformados
 * @var string $csrfToken
 */
$step1Url = tenantCleanUrl('pdv/conferencia');
$confirmUrl = tenantCleanUrl('pdv/conferencia') . '?action=confirmar';
$caixaUrl = tenantCleanUrl('pdv/caixa/' . (int)$caixa['id']);

$labelPorTipo = [
    'D' => 'Dinheiro', 'PIX' => 'PIX', 'CC' => 'Cartão de Crédito', 'CD' => 'Cartão de Débito',
    'BOL' => 'Boleto', 'TB' => 'Transferência Bancária', 'AF' => 'A Faturar',
];

$brl = static fn ($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');

$totalSistemaGeral = 0.0;
$totalInformadoGeral = 0.0;
$totalDiferencaGeral = 0.0;
foreach ($formas as $fp) {
    $vs = (float)($totaisSistema[$fp['id']] ?? 0);
    $vi = (float)($valoresInformados[$fp['id']] ?? 0);
    $totalSistemaGeral += $vs;
    $totalInformadoGeral += $vi;
    $totalDiferencaGeral += ($vi - $vs);
}
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Conferência de Caixa — Comparativo</h1>
            <p class="text-muted mb-0 small">
                Caixa <strong>#<?= (int)$caixa['numero_caixa'] ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= htmlspecialchars($step1Url) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Voltar ao passo 1
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

    <!-- Stepper -->
    <div class="card shadow mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3 small">
                <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check me-1"></i>1 · Informar</span>
                <i class="fas fa-chevron-right text-muted"></i>
                <span class="badge bg-primary rounded-pill px-3 py-2">2 · Comparativo</span>
                <i class="fas fa-chevron-right text-muted"></i>
                <span class="badge bg-light text-muted rounded-pill px-3 py-2 border">3 · Confirmar</span>
            </div>
        </div>
    </div>

    <div class="card shadow mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Forma de pagamento</th>
                            <th class="text-end">Sistema</th>
                            <th class="text-end">Informado</th>
                            <th class="text-end">Diferença</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formas as $fp):
                            $vs = (float)($totaisSistema[$fp['id']] ?? 0);
                            $vi = (float)($valoresInformados[$fp['id']] ?? 0);
                            $dif = $vi - $vs;
                            $classeDif = $dif > 0.009 ? 'text-success' : ($dif < -0.009 ? 'text-danger' : 'text-muted');
                        ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($labelPorTipo[$fp['tipo']] ?? $fp['nome']) ?>
                                    <span class="badge bg-light text-muted border ms-1"><?= htmlspecialchars($fp['tipo']) ?></span>
                                </td>
                                <td class="text-end font-monospace"><?= $brl($vs) ?></td>
                                <td class="text-end font-monospace fw-semibold"><?= $brl($vi) ?></td>
                                <td class="text-end font-monospace fw-bold <?= $classeDif ?>">
                                    <?= ($dif > 0.009 ? '+' : '') . $brl($dif) ?>
                                </td>
                                <td class="text-center">
                                    <?php if (abs($dif) < 0.01): ?>
                                        <span class="badge bg-success">Confere</span>
                                    <?php elseif ($dif > 0): ?>
                                        <span class="badge bg-warning text-dark">Sobra</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Falta</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>Total</th>
                            <th class="text-end font-monospace"><?= $brl($totalSistemaGeral) ?></th>
                            <th class="text-end font-monospace"><?= $brl($totalInformadoGeral) ?></th>
                            <th class="text-end font-monospace <?= $totalDiferencaGeral > 0.009 ? 'text-success' : ($totalDiferencaGeral < -0.009 ? 'text-danger' : 'text-muted') ?>">
                                <?= ($totalDiferencaGeral > 0.009 ? '+' : '') . $brl($totalDiferencaGeral) ?>
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <?php if (abs($totalDiferencaGeral) >= 0.01): ?>
        <div class="alert alert-warning d-flex align-items-start">
            <i class="fas fa-triangle-exclamation me-2 mt-1"></i>
            <div>
                <strong>Há diferenças no caixa.</strong>
                Você pode voltar ao passo 1 para recontar ou prosseguir e confirmar mesmo assim.
                As diferenças ficarão registradas em <code>pdv_conferencia_itens</code>.
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <h6 class="mb-3"><i class="fas fa-bolt me-2 text-warning"></i>O que acontece ao confirmar?</h6>
            <ul class="small text-secondary mb-3">
                <li>Vendas em <strong>D/PIX/TB</strong>: geram <strong>movimentação de entrada</strong> (protegida) na conta vinculada à forma.</li>
                <li>Vendas em <strong>CC/CD/BOL/AF</strong>: geram <strong>conta a receber</strong> (protegida) com vencimento = hoje + prazo da forma.</li>
                <li>Todas as vendas do caixa são marcadas como <strong>conferidas</strong>.</li>
                <li>O caixa é <strong>fechado</strong> automaticamente.</li>
                <li>Tudo ocorre em <strong>uma única transação</strong>: se algo falhar, nada é gravado.</li>
            </ul>
            <form method="POST" action="<?= htmlspecialchars($confirmUrl) ?>" class="d-flex justify-content-between gap-2" id="formConfirmar">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <a href="<?= htmlspecialchars($step1Url) ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
                <button type="submit" class="btn btn-success btn-lg" id="btnConfirmar">
                    <i class="fas fa-check-circle me-1"></i> Confirmar e fechar caixa
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('formConfirmar').addEventListener('submit', function(e) {
    if (typeof Swal !== 'undefined') {
        e.preventDefault();
        Swal.fire({
            title: 'Confirmar conferência?',
            text: 'Esta ação irá fechar o caixa e gerar os lançamentos financeiros. Não pode ser desfeita.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, confirmar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#22c55e',
        }).then(r => {
            if (r.isConfirmed) {
                document.getElementById('btnConfirmar').disabled = true;
                document.getElementById('btnConfirmar').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
                e.target.submit();
            }
        });
    }
});
</script>
