<?php
/**
 * @var array $caixa
 * @var array $formas
 * @var array<int, float> $valoresInformados
 * @var string $csrfToken
 */
$baseUrl = tenantCleanUrl('pdv/conferencia');
$caixaUrl = tenantCleanUrl('pdv/caixa/' . (int)$caixa['id']);
$step2Url = tenantCleanUrl('pdv/conferencia') . '?action=step2';

$labelPorTipo = [
    'D' => 'Dinheiro', 'PIX' => 'PIX', 'CC' => 'Cartão de Crédito', 'CD' => 'Cartão de Débito',
    'BOL' => 'Boleto', 'TB' => 'Transferência Bancária', 'AF' => 'A Faturar',
];
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Conferência de Caixa</h1>
            <p class="text-muted mb-0 small">
                Caixa <strong>#<?= (int)$caixa['numero_caixa'] ?></strong>
                · Aberto em <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$caixa['data_abertura']))) ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= htmlspecialchars($caixaUrl) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Cancelar
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
                <span class="badge bg-primary rounded-pill px-3 py-2">1 · Informar valores</span>
                <i class="fas fa-chevron-right text-muted"></i>
                <span class="badge bg-light text-muted rounded-pill px-3 py-2 border">2 · Comparativo</span>
                <i class="fas fa-chevron-right text-muted"></i>
                <span class="badge bg-light text-muted rounded-pill px-3 py-2 border">3 · Confirmar</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="fas fa-eye-slash me-2 text-warning"></i>Entrada às cegas</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Digite o valor <strong>real</strong> que você contou no caixa para cada forma de pagamento.
                        Os valores do sistema serão revelados apenas no próximo passo.
                    </p>

                    <form method="POST" action="<?= htmlspecialchars($step2Url) ?>" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="step2">

                        <div class="row g-3">
                            <?php foreach ($formas as $fp):
                                $valAtual = $valoresInformados[$fp['id']] ?? '';
                            ?>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">
                                        <i class="fas fa-money-bill-wave me-1 text-secondary"></i>
                                        <?= htmlspecialchars($labelPorTipo[$fp['tipo']] ?? $fp['nome']) ?>
                                        <span class="badge bg-light text-muted border ms-1"><?= htmlspecialchars($fp['tipo']) ?></span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" name="valor[<?= (int)$fp['id'] ?>]"
                                               class="form-control" step="0.01" min="0"
                                               value="<?= htmlspecialchars((string)$valAtual) ?>"
                                               placeholder="0,00">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="<?= htmlspecialchars($caixaUrl) ?>" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Avançar para comparativo <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Como funciona</h6>
                </div>
                <div class="card-body small text-secondary">
                    <ol class="ps-3 mb-0">
                        <li class="mb-2">Digite os valores contados <strong>sem olhar</strong> os totais do sistema.</li>
                        <li class="mb-2">No próximo passo você verá o comparativo com as diferenças (sobra/falta).</li>
                        <li class="mb-2">Ao confirmar, as vendas geram <strong>movimentações</strong> (D, PIX, TB) ou <strong>contas a receber</strong> (CC, CD, BOL, AF).</li>
                        <li>O caixa será <strong>fechado</strong> automaticamente após a confirmação.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
