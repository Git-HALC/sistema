<?php
$filtros = $filtros ?? [];
$movimentacoes = $movimentacoes ?? [];
$produtos = $produtos ?? [];
$produtoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_produto_base_url', 'admin/produtos.php')
    : tenantUrl('admin/produtos.php');
$inventarioUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($produtoBaseUrl, 'action=inventario')
    : $produtoBaseUrl . '?action=inventario';
$movimentarUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($produtoBaseUrl, 'action=movimentar-estoque')
    : $produtoBaseUrl . '?action=movimentar-estoque';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Inventário de Estoque</h1>
        <div class="d-flex gap-2">
            <a href="/sistema_dm/public/admin/relatorios/produto/relatorio_produtos.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-chart-bar me-1"></i> Relatório
            </a>
            <a href="<?php echo htmlspecialchars($produtoBaseUrl); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header"><strong>Nova Movimentação</strong></div>
                <div class="card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($movimentarUrl); ?>">
                        <input type="hidden" name="action" value="movimentar-estoque">

                        <div class="mb-3">
                            <label class="form-label">Produto</label>
                            <select name="produto_id" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($produtos as $produto): ?>
                                    <option value="<?php echo (int)$produto['id']; ?>">
                                        <?php echo htmlspecialchars((string)(($produto['codigo'] ?: 'SEM COD') . ' - ' . $produto['nome'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="ENTRADA">Entrada</option>
                                    <option value="SAIDA">Saída</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Quantidade</label>
                                <input type="number" name="quantidade" class="form-control" min="0.0001" step="0.0001" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Observação</label>
                            <textarea name="observacao" class="form-control" rows="3" placeholder="Motivo da movimentação"></textarea>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Registrar movimentação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header"><strong>Histórico de Movimentações</strong></div>
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($produtoBaseUrl); ?>" class="row g-2 mb-3">
                        <input type="hidden" name="action" value="inventario">

                        <div class="col-md-4">
                            <input type="text" name="busca" class="form-control" placeholder="Buscar por nome ou código"
                                   value="<?php echo htmlspecialchars((string)($filtros['busca'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="produto_id" class="form-select">
                                <option value="">Todos os produtos</option>
                                <?php foreach ($produtos as $produto): ?>
                                    <option value="<?php echo (int)$produto['id']; ?>" <?php echo ((int)($filtros['produto_id'] ?? 0) === (int)$produto['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)$produto['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="tipo" class="form-select">
                                <option value="">Todos</option>
                                <option value="ENTRADA" <?php echo (($filtros['tipo'] ?? '') === 'ENTRADA') ? 'selected' : ''; ?>>Entradas</option>
                                <option value="SAIDA" <?php echo (($filtros['tipo'] ?? '') === 'SAIDA') ? 'selected' : ''; ?>>Saídas</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                            <a href="<?php echo htmlspecialchars($inventarioUrl); ?>" class="btn btn-outline-secondary">Limpar</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Produto</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Qtd</th>
                                    <th class="text-end">Antes</th>
                                    <th class="text-end">Depois</th>
                                    <th>Usuário</th>
                                    <th>Origem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($movimentacoes === []): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Nenhuma movimentação encontrada.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($movimentacoes as $mov): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$mov['created_at']))); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string)$mov['nome']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string)($mov['codigo'] ?: 'SEM COD')); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo ($mov['tipo'] === 'ENTRADA') ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo htmlspecialchars((string)$mov['tipo']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?php echo number_format((float)$mov['quantidade'], 4, ',', '.'); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$mov['estoque_anterior'], 4, ',', '.'); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$mov['estoque_posterior'], 4, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($mov['usuario_nome'] ?: 'Sistema')); ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars((string)$mov['origem']); ?></div>
                                                <?php if (!empty($mov['observacao'])): ?>
                                                    <div class="small text-muted"><?php echo htmlspecialchars((string)$mov['observacao']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
