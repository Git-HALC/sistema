<?php
$baseUrl = tenantUrl('admin/servicos.php');
$novoUrl = $baseUrl . '?action=novo';
$editarUrl = fn (int $id) => $baseUrl . '?action=editar&id=' . $id;
$brl = fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Serviços</h1>
        <a href="<?php echo htmlspecialchars($novoUrl); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Novo Serviço
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <?php $msg = $_SESSION['mensagem']; unset($_SESSION['mensagem']); ?>
        <div class="alert alert-<?php echo $msg['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?php echo htmlspecialchars($baseUrl); ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca ?? ''); ?>"
                           class="form-control form-control-sm" placeholder="Nome ou descrição…">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Status</label>
                    <select name="ativo" class="form-select form-select-sm">
                        <option value="" <?php echo ($ativoFiltro ?? '') === '' ? 'selected' : ''; ?>>Todos</option>
                        <option value="1" <?php echo ($ativoFiltro ?? '') === '1' ? 'selected' : ''; ?>>Ativos</option>
                        <option value="0" <?php echo ($ativoFiltro ?? '') === '0' ? 'selected' : ''; ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-6 col-md-4 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <?php if (!empty($busca) || ($ativoFiltro ?? '') !== ''): ?>
                        <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="btn btn-outline-danger btn-sm" title="Limpar">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <?php if (empty($servicos)): ?>
                <div class="p-4 text-center text-muted">Nenhum serviço encontrado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th class="text-end">Valor Base</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servicos as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s->nome); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars(mb_strimwidth((string)$s->descricao, 0, 80, '…')); ?></td>
                                    <td class="text-end"><?php echo $brl($s->valor_base); ?></td>
                                    <td class="text-center">
                                        <?php if ($s->ativo): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?php echo htmlspecialchars($editarUrl((int)$s->id)); ?>"
                                           class="btn btn-outline-primary btn-sm" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($s->ativo): ?>
                                            <form method="POST" action="<?php echo htmlspecialchars($baseUrl); ?>"
                                                  class="d-inline js-confirm-form"
                                                  data-confirm-title="Inativar serviço?"
                                                  data-confirm-text="<?= htmlspecialchars((string)$s->nome) ?>"
                                                  data-confirm-icon="warning"
                                                  data-confirm-btn="Inativar">
                                                <input type="hidden" name="action" value="inativar">
                                                <input type="hidden" name="id" value="<?php echo (int)$s->id; ?>">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Inativar">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?php echo htmlspecialchars($baseUrl); ?>"
                                                  class="d-inline">
                                                <input type="hidden" name="action" value="reativar">
                                                <input type="hidden" name="id" value="<?php echo (int)$s->id; ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm" title="Reativar">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
                                <li class="page-item <?php echo $p === $paginaAtual ? 'active' : ''; ?>">
                                    <a class="page-link"
                                       href="<?php echo htmlspecialchars($baseUrl . '?pagina=' . $p
                                           . '&busca=' . urlencode($busca ?? '')
                                           . '&ativo=' . urlencode((string)($ativoFiltro ?? ''))); ?>">
                                        <?php echo $p; ?>
                                    </a>
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
                icon: icon, showCancelButton: true,
                confirmButtonText: form.dataset.confirmBtn || 'Confirmar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: { confirmButton: confirmClass, cancelButton: 'btn btn-outline-secondary mx-1' }
            }).then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); } });
        });
    });
}());
</script>
