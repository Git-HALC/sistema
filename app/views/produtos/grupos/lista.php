<?php
$baseUrl = tenantUrl('admin/produto-grupos.php');
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Grupos de Produtos</h1>
        <a href="<?= htmlspecialchars($baseUrl . '?action=novo') ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Novo Grupo
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <?php $msg = $_SESSION['mensagem']; unset($_SESSION['mensagem']); ?>
        <div class="alert alert-<?= $msg['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $msg['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?= htmlspecialchars($baseUrl) ?>" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="form-control form-control-sm" placeholder="Nome ou descrição">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Status</label>
                    <select name="ativo" class="form-select form-select-sm">
                        <option value="" <?= $ativoFiltro === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $ativoFiltro === '1' ? 'selected' : '' ?>>Ativos</option>
                        <option value="0" <?= $ativoFiltro === '0' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body p-0">
            <?php if (empty($grupos)): ?>
                <div class="text-center text-muted py-5">Nenhum grupo cadastrado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th class="text-center">Subgrupos</th>
                                <th class="text-center">Produtos</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $g): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars((string)$g['nome']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars(mb_strimwidth((string)($g['descricao'] ?? ''), 0, 80, '…')) ?></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?= (int)$g['qtd_subgrupos'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?= (int)$g['qtd_produtos'] ?></span></td>
                                    <td class="text-center">
                                        <?php if ($g['ativo']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= htmlspecialchars($baseUrl . '?action=editar&id=' . (int)$g['id']) ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>" class="d-inline js-confirm-form"
                                              data-confirm-title="<?= $g['ativo'] ? 'Inativar grupo?' : 'Reativar grupo?' ?>"
                                              data-confirm-text="<?= htmlspecialchars((string)$g['nome']) ?>"
                                              data-confirm-icon="<?= $g['ativo'] ? 'warning' : 'question' ?>"
                                              data-confirm-btn="<?= $g['ativo'] ? 'Inativar' : 'Reativar' ?>">
                                            <input type="hidden" name="action" value="<?= $g['ativo'] ? 'inativar' : 'reativar' ?>">
                                            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $g['ativo'] ? 'warning' : 'success' ?>" title="<?= $g['ativo'] ? 'Inativar' : 'Reativar' ?>">
                                                <i class="fas fa-<?= $g['ativo'] ? 'ban' : 'undo' ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (($totalPaginas ?? 1) > 1): ?>
                    <nav class="p-2 border-top">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                                <li class="page-item <?= $p === $paginaAtual ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars($baseUrl . '?pagina=' . $p . '&busca=' . urlencode($busca) . '&ativo=' . urlencode($ativoFiltro)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.js-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1' || typeof Swal === 'undefined') return;
            e.preventDefault();
            var icon = form.dataset.confirmIcon || 'question';
            var confirmClass = icon === 'warning' ? 'btn btn-warning mx-1'
                              : (icon === 'error' ? 'btn btn-danger mx-1' : 'btn btn-primary mx-1');
            Swal.fire({
                title: form.dataset.confirmTitle || 'Confirmar?',
                text: form.dataset.confirmText || '',
                icon: icon,
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmBtn || 'Confirmar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: { confirmButton: confirmClass, cancelButton: 'btn btn-outline-secondary mx-1' }
            }).then(function (r) {
                if (r.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    });
}());
</script>
