<?php
$caixas = is_array($caixas ?? null) ? $caixas : [];
$caixaAtualUsuario = is_array($caixaAtualUsuario ?? null) ? $caixaAtualUsuario : null;
$podeAbrirNovoCaixa = (bool)($podeAbrirNovoCaixa ?? false);
$formatarDataHora = static function (?string $valor): string {
    if (empty($valor)) {
        return '--';
    }

    $timestamp = strtotime($valor);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '--';
};
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">PDV</h1>
            <p class="text-muted mb-0">Selecione um caixa aberto ou inicie um novo para começar a operar.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($caixaAtualUsuario !== null): ?>
                <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . (int)($caixaAtualUsuario['id'] ?? 0))) ?>" class="btn btn-primary">
                    <i class="fas fa-cash-register me-1"></i> Ir para meu caixa
                </a>
            <?php endif; ?>
            <?php if ($podeAbrirNovoCaixa): ?>
                <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/abrir')) ?>" class="btn btn-outline-primary">
                    <i class="fas fa-plus me-1"></i> Abrir novo caixa
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($caixaAtualUsuario !== null): ?>
        <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . (int)($caixaAtualUsuario['id'] ?? 0))) ?>"
           class="card border-0 shadow-sm mb-4 text-decoration-none text-body border-start border-4 border-success"
           style="transition: transform .15s, box-shadow .15s;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 .5rem 1rem rgba(0,0,0,.1)'"
           onmouseout="this.style.transform=''; this.style.boxShadow=''">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8 d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded bg-success bg-opacity-10 text-success"
                             style="width:56px; height:56px;">
                            <i class="fas fa-cash-register fa-2x"></i>
                        </div>
                        <div>
                            <div class="small text-muted fw-semibold text-uppercase">Meu caixa aberto</div>
                            <div class="h4 mb-1">Caixa #<?= (int)($caixaAtualUsuario['numero_caixa'] ?? 0) ?></div>
                            <div class="small text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Aberto em <?= htmlspecialchars($formatarDataHora($caixaAtualUsuario['data_abertura'] ?? null)) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="text-muted small">Suprimento inicial</div>
                        <div class="fs-5 fw-semibold">R$ <?= number_format((float)($caixaAtualUsuario['valor_suprimento'] ?? 0), 2, ',', '.') ?></div>
                        <div class="small text-success mt-1">
                            <i class="fas fa-arrow-right me-1"></i>Ir para meu caixa
                        </div>
                    </div>
                </div>
            </div>
        </a>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Caixas abertos agora</span>
            <span class="badge text-bg-light"><?= count($caixas) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if ($caixas === []): ?>
                <div class="p-4 text-center text-muted">Nenhum caixa aberto no momento.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Caixa</th>
                                <th>Operador</th>
                                <th>Abertura</th>
                                <th class="text-end">Suprimento</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($caixas as $caixa): ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int)($caixa['numero_caixa'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string)($caixa['operador_nome'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($formatarDataHora($caixa['data_abertura'] ?? null)) ?></td>
                                    <td class="text-end">R$ <?= number_format((float)($caixa['valor_suprimento'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-end">
                                        <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . (int)($caixa['id'] ?? 0))) ?>" class="btn btn-sm btn-outline-primary">Acessar</a>
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
