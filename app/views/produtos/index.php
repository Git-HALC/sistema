<?php
$produtoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_produto_base_url', 'admin/produtos.php')
    : tenantUrl('admin/produtos.php');
$produtoInventarioUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($produtoBaseUrl, 'action=inventario')
    : $produtoBaseUrl . '?action=inventario';
$produtoNovoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($produtoBaseUrl, 'action=novo')
    : $produtoBaseUrl . '?action=novo';
?>
<div class="container-fluid">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0 text-gray-800">Produtos</h1>
        <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars($produtoInventarioUrl); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-boxes me-1"></i> Inventário de Estoque
            </a>
            <a href="/sistema_dm/public/admin/relatorios/produto/relatorio_produtos.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-chart-bar me-1"></i> Relatório de Inventário
            </a>
            <a href="<?php echo htmlspecialchars($produtoNovoUrl); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Novo Produto
            </a>
        </div>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <!-- Busca -->
    <div class="card shadow mb-4">
        <div class="card-body py-2">
            <form method="GET" action="<?php echo htmlspecialchars($produtoBaseUrl); ?>" class="d-flex gap-2">
                <input type="hidden" name="action" value="listar">
                <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>"
                       class="form-control form-control-sm" placeholder="Buscar por nome ou código…" style="max-width:320px;">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($busca)): ?>
                    <a href="<?php echo htmlspecialchars($produtoBaseUrl); ?>" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px;">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th class="text-center">Unidade</th>
                            <th class="text-end">Preço Venda</th>
                            <th class="text-end">Estoque</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <?php echo !empty($busca) ? 'Nenhum produto encontrado para "' . htmlspecialchars($busca) . '".' : 'Nenhum produto cadastrado.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td class="text-muted"><?php echo htmlspecialchars($p['codigo'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($p['nome']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($p['unidade']); ?></span>
                                </td>
                                <td class="text-end">
                                    R$ <?php echo number_format((float)$p['preco_venda'], 2, ',', '.'); ?>
                                </td>
                                <td class="text-end">
                                    <?php
                                    $est = (float)$p['estoque_atual'];
                                    $min = 0; // mínimo não está na listagem — só no form
                                    $cor = $est <= 0 ? 'text-danger' : 'text-dark';
                                    echo "<span class='{$cor}'>" . number_format($est, 2, ',', '.') . "</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($produtoBaseUrl, 'action=editar&id=' . urlencode((string)$p['id'])) : $produtoBaseUrl . '?action=editar&id=' . urlencode((string)$p['id'])); ?>"
                                           class="btn btn-outline-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-excluir"
                                                data-id="<?php echo $p['id']; ?>"
                                                data-nome="<?php echo htmlspecialchars($p['nome']); ?>"
                                                title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPaginas > 1 || $total > 0): ?>
        <div class="card-footer d-flex justify-content-between align-items-center py-2" style="font-size:12px;">
            <span class="text-muted"><?php echo $total; ?> produto(s) encontrado(s)</span>
            <?php if ($totalPaginas > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($paginaAtual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?action=listar&busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $paginaAtual - 1; ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                            <a class="page-link" href="?action=listar&busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($paginaAtual < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?action=listar&busca=<?php echo urlencode($busca); ?>&pagina=<?php echo $paginaAtual + 1; ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-excluir').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            var id   = this.dataset.id;
            var nome = this.dataset.nome;

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Excluir produto?',
                    text: 'Tem certeza que deseja excluir o produto "' + nome + '"?',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonText: 'Excluir',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444',
                    footer: '<i class="fas fa-exclamation-triangle text-warning"></i> Esta ação não pode ser desfeita!'
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({
                        title: 'Excluindo...',
                        allowOutsideClick: false,
                        didOpen: function() { Swal.showLoading(); }
                    });
                    excluirProduto(id);
                });
                return;
            }

            if (confirm('Tem certeza que deseja excluir o produto "' + nome + '"?\n\nEsta ação não pode ser desfeita!')) {
                excluirProduto(id);
            }
        });
    });

    function excluirProduto(id) {
        fetch(<?php echo json_encode($produtoBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=excluir&id=' + encodeURIComponent(id)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    alert(data.message);
                    location.reload();
                }
                return;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire('Erro', data.message || 'Erro ao excluir produto.', 'error');
            } else {
                alert(data.message || 'Erro ao excluir produto.');
            }
        })
        .catch(function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Erro', 'Ocorreu um erro ao tentar excluir o produto.', 'error');
            } else {
                alert('Ocorreu um erro ao tentar excluir o produto.');
            }
        });
    }
});
</script>
