<?php
$caixa = is_array($caixa ?? null) ? $caixa : [];
$caixasAbertos = is_array($caixasAbertos ?? null) ? $caixasAbertos : [];
$podeFechar = (bool) ($podeFechar ?? false);
$caixaId = (int) ($caixa['id'] ?? 0);
$outrosCaixas = array_values(array_filter(
    $caixasAbertos,
    static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $caixaId
));

$formatarDataHora = static function (?string $valor): string {
    if (empty($valor)) {
        return '--';
    }

    $timestamp = strtotime($valor);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '--';
};

$formatarMoeda = static fn ($valor): string => 'R$ ' . number_format((float) $valor, 2, ',', '.');
$status = strtolower((string) ($caixa['status'] ?? ''));
$statusClasse = match ($status) {
    'aberto' => 'success',
    'fechado' => 'secondary',
    default => 'warning',
};
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Caixa #<?= (int) ($caixa['numero_caixa'] ?? 0) ?></h1>
            <p class="text-muted mb-0">Painel rapido para operar o PDV com este caixa.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . $caixaId . '/relatorio')) ?>" class="btn btn-outline-primary">
                <i class="fas fa-file-alt me-1"></i> Relatorio
            </a>
            <?php if ($podeFechar): ?>
                <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . $caixaId . '/fechar')) ?>" class="btn btn-primary">
                    <i class="fas fa-lock me-1"></i> Fechar caixa
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Operador</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars((string) ($caixa['operador_nome'] ?? 'Nao informado')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Abertura</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarDataHora($caixa['data_abertura'] ?? null)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Suprimento</div>
                    <div class="fw-semibold fs-5"><?= htmlspecialchars($formatarMoeda($caixa['valor_suprimento'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Status</div>
                    <span class="badge text-bg-<?= htmlspecialchars($statusClasse) ?> fs-6">
                        <?= htmlspecialchars(ucfirst($status !== '' ? $status : 'indefinido')) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/clientes')) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-body">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge text-bg-light">Cadastro</span>
                        <i class="fas fa-users text-primary"></i>
                    </div>
                    <h2 class="h5 mb-2">Clientes</h2>
                    <p class="text-muted mb-0">Consulte, cadastre e edite clientes durante o atendimento.</p>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/produtos')) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-body">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge text-bg-light">Catalogo</span>
                        <i class="fas fa-box-open text-primary"></i>
                    </div>
                    <h2 class="h5 mb-2">Produtos</h2>
                    <p class="text-muted mb-0">Acesse o cadastro de produtos e movimentacoes de estoque.</p>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/pedidos?action=kanban')) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-body">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge text-bg-light">Vendas</span>
                        <i class="fas fa-shopping-cart text-primary"></i>
                    </div>
                    <h2 class="h5 mb-2">Pedidos</h2>
                    <p class="text-muted mb-0">Crie e acompanhe pedidos com lancamento automatico no caixa.</p>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/servicos?action=kanban')) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-body">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge text-bg-light">Atendimento</span>
                        <i class="fas fa-tools text-primary"></i>
                    </div>
                    <h2 class="h5 mb-2">Servicos</h2>
                    <p class="text-muted mb-0">Registre servicos, faturamento e lancamentos vinculados ao caixa.</p>
                </div>
            </a>
        </div>
    </div>

    <?php if ($outrosCaixas !== []): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Outros caixas abertos</span>
                <span class="badge text-bg-light"><?= count($outrosCaixas) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Caixa</th>
                                <th>Operador</th>
                                <th>Abertura</th>
                                <th class="text-end">Suprimento</th>
                                <th class="text-end">Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outrosCaixas as $outroCaixa): ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int) ($outroCaixa['numero_caixa'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($outroCaixa['operador_nome'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($formatarDataHora($outroCaixa['data_abertura'] ?? null)) ?></td>
                                    <td class="text-end"><?= htmlspecialchars($formatarMoeda($outroCaixa['valor_suprimento'] ?? 0)) ?></td>
                                    <td class="text-end">
                                        <a href="<?= htmlspecialchars(tenantCleanUrl('pdv/caixa/' . (int) ($outroCaixa['id'] ?? 0))) ?>" class="btn btn-sm btn-outline-primary">
                                            Acessar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
