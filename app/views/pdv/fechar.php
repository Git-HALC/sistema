<?php
$caixa = is_array($caixa ?? null) ? $caixa : [];
$csrfToken = (string) ($csrfToken ?? '');
$caixaId = (int) ($caixa['id'] ?? 0);

$formatarDataHora = static function (?string $valor): string {
    if (empty($valor)) {
        return '--';
    }

    $timestamp = strtotime($valor);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '--';
};
?>
<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-xxl-8 col-xl-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                        <div>
                            <h1 class="h4 mb-1">Fechamento cego do caixa #<?= (int) ($caixa['numero_caixa'] ?? 0) ?></h1>
                            <p class="text-muted mb-0">Informe os valores contados fisicamente antes de concluir o turno.</p>
                        </div>
                        <span class="badge text-bg-light">
                            Aberto em <?= htmlspecialchars($formatarDataHora($caixa['data_abertura'] ?? null)) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= htmlspecialchars(tenantCleanUrl('pdv')) ?>">
                        <input type="hidden" name="action" value="fechar-post">
                        <input type="hidden" name="id" value="<?= $caixaId ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                        <div class="alert alert-warning" role="alert">
                            <strong>Atencao:</strong> estes campos representam o valor digitado pelo operador e serao comparados com o total do sistema no relatorio.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="dinheiro">Dinheiro</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="dinheiro" name="dinheiro" value="0.00" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cartao">Cartao</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="cartao" name="cartao" value="0.00" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="pix">PIX</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="pix" name="pix" value="0.00" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="a_faturar">A faturar</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="a_faturar" name="a_faturar" value="0.00" required>
                            </div>
                        </div>

                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="deseja_imprimir" name="deseja_imprimir" checked>
                            <label class="form-check-label" for="deseja_imprimir">
                                Abrir o relatorio para impressao logo apos o fechamento
                            </label>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . $caixaId)) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check me-1"></i> Confirmar fechamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
