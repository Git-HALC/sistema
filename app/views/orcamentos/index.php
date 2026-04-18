<div class="container-fluid">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Orçamentos</h1>
        <a href="/sistema_dm/public/admin/orcamentos.php?action=novo" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Novo Orçamento
        </a>
    </div>

    <!-- Flash message -->
    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" action="/sistema_dm/public/admin/orcamentos.php" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="listar">

                <div class="col-12 col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($filtros['busca']) ?>"
                           class="form-control form-control-sm" placeholder="Código, cliente…">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($statusOpcoes as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $filtros['status'] === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Emissão de</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio']) ?>"
                           class="form-control form-control-sm" data-skip-datepicker="1">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Emissão até</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($filtros['data_fim']) ?>"
                           class="form-control form-control-sm" data-skip-datepicker="1">
                </div>

                <div class="col-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <?php if (array_filter($filtros)): ?>
                        <a href="/sistema_dm/public/admin/orcamentos.php" class="btn btn-outline-danger btn-sm" title="Limpar filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Listagem -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">

            <?php if (empty($orcamentos)): ?>
                <div class="p-4 text-center text-muted">Nenhum orçamento encontrado.</div>
            <?php else: ?>

                <!-- Tabela desktop -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Cliente</th>
                                    <th>Emissão</th>
                                    <th>Validade</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orcamentos as $orc): /** @var Orcamento $orc */ ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($orc->codigo ?? "ORC-{$orc->id}") ?></td>
                                        <td><?= htmlspecialchars($orc->clienteNome ?? '—') ?></td>
                                        <td><?= $orc->dataEmissaoFormatada() ?></td>
                                        <td><?= $orc->dataValidadeFormatada() ?></td>
                                        <td class="text-end fw-semibold"><?= $orc->valorTotalFormatado() ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $orc->statusBadge() ?>">
                                                <?= $orc->statusLabel() ?>
                                            </span>
                                            <?php if ($orc->contaReceberId): ?>
                                                <a href="/sistema_dm/public/admin/financeiro/contas-receber.php"
                                                   class="badge bg-info text-decoration-none ms-1" title="Ver conta a receber">
                                                    <i class="fas fa-link"></i> CR
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="/sistema_dm/public/admin/orcamentos.php?action=visualizar&id=<?= $orc->id ?>"
                                                   class="btn btn-outline-secondary" title="Visualizar">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($orc->estaAberto()): ?>
                                                    <a href="/sistema_dm/public/admin/orcamentos.php?action=editar&id=<?= $orc->id ?>"
                                                       class="btn btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="/sistema_dm/public/admin/orcamentos.php?action=aprovar&id=<?= $orc->id ?>"
                                                       class="btn btn-outline-success" title="Aprovar">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <button type="button"
                                                            class="btn btn-outline-warning"
                                                            title="Cancelar"
                                                            onclick="confirmarAcao('Cancelar orçamento <?= $orc->codigo ?>?', '/sistema_dm/public/admin/orcamentos.php?action=cancelar&id=<?= $orc->id ?>', 'warning', 'Cancelar')">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-outline-danger"
                                                            title="Excluir"
                                                            onclick="confirmarAcao('Excluir orçamento <?= $orc->codigo ?>?', '/sistema_dm/public/admin/orcamentos.php?action=excluir&id=<?= $orc->id ?>', 'error', 'Excluir')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php elseif ($orc->estaCancelado()): ?>
                                                    <span class="text-muted small px-2">—</span>
                                                <?php elseif ($orc->estaAprovado()): ?>
                                                    <button type="button"
                                                            class="btn btn-outline-warning"
                                                            title="Estornar"
                                                            onclick="confirmarAcao('Estornar orçamento <?= $orc->codigo ?>?', '/sistema_dm/public/admin/orcamentos.php?action=estornar&id=<?= $orc->id ?>', 'warning', 'Estornar')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small px-2">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cards mobile -->
                <div class="d-md-none p-3">
                    <?php foreach ($orcamentos as $orc): /** @var Orcamento $orc */ ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1">
                                            <?= htmlspecialchars($orc->clienteNome ?? 'Cliente não informado') ?>
                                        </h6>
                                        <small class="text-muted"><?= htmlspecialchars($orc->codigo ?? "ORC-{$orc->id}") ?></small>
                                    </div>
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        <span class="badge <?= $orc->statusBadge() ?> ms-2 flex-shrink-0">
                                            <?= $orc->statusLabel() ?>
                                        </span>
                                        <?php if ($orc->contaReceberId): ?>
                                            <a href="/sistema_dm/public/admin/financeiro/contas-receber.php"
                                               class="badge bg-info text-decoration-none" title="Ver conta a receber">
                                                <i class="fas fa-link"></i> CR
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3 small">
                                    <div class="col-6">
                                        <span class="text-muted d-block">Valor Total</span>
                                        <strong><?= $orc->valorTotalFormatado() ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Emissão</span>
                                        <strong><?= $orc->dataEmissaoFormatada() ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Validade</span>
                                        <strong><?= $orc->dataValidadeFormatada() ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Status</span>
                                        <strong><?= $orc->statusLabel() ?></strong>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="/sistema_dm/public/admin/orcamentos.php?action=visualizar&id=<?= $orc->id ?>"
                                       class="btn btn-outline-secondary btn-sm flex-fill">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <?php if ($orc->estaAberto()): ?>
                                        <a href="/sistema_dm/public/admin/orcamentos.php?action=editar&id=<?= $orc->id ?>"
                                           class="btn btn-outline-primary btn-sm flex-fill">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="/sistema_dm/public/admin/orcamentos.php?action=aprovar&id=<?= $orc->id ?>"
                                           class="btn btn-outline-success btn-sm flex-fill">
                                            <i class="fas fa-check"></i> Aprovar
                                        </a>
                                        <button type="button"
                                                class="btn btn-outline-warning btn-sm flex-fill"
                                                onclick="confirmarAcao('Cancelar orçamento <?= $orc->codigo ?>?', '/sistema_dm/public/admin/orcamentos.php?action=cancelar&id=<?= $orc->id ?>', 'warning', 'Cancelar')">
                                            <i class="fas fa-ban"></i> Cancelar
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm flex-fill"
                                                onclick="confirmarAcao('Excluir orçamento <?= $orc->codigo ?>?', '/sistema_dm/public/admin/orcamentos.php?action=excluir&id=<?= $orc->id ?>', 'error', 'Excluir')">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    <?php elseif ($orc->estaCancelado()): ?>
                                        <span class="text-muted small">Sem ações disponíveis</span>
                                    <?php elseif ($orc->estaAprovado()): ?>
                                        <button type="button"
                                                class="btn btn-outline-warning btn-sm flex-fill"
                                                onclick="confirmarAcao('Estornar orçamento <?= $orc->codigo ?>?', '/sistema_dm/public/admin/orcamentos.php?action=estornar&id=<?= $orc->id ?>', 'warning', 'Estornar')">
                                            <i class="fas fa-undo"></i> Estornar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted">
                            <?= $total ?> orçamento<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
                        </small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($paginaAtual > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?action=listar&pagina=<?= $paginaAtual - 1 ?>&<?= http_build_query(array_filter($filtros)) ?>">‹</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                                    <li class="page-item <?= $p === $paginaAtual ? 'active' : '' ?>">
                                        <a class="page-link"
                                           href="?action=listar&pagina=<?= $p ?>&<?= http_build_query(array_filter($filtros)) ?>">
                                            <?= $p ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($paginaAtual < $totalPaginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?action=listar&pagina=<?= $paginaAtual + 1 ?>&<?= http_build_query(array_filter($filtros)) ?>">›</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php else: ?>
                    <div class="card-footer py-2">
                        <small class="text-muted">
                            <?= $total ?> orçamento<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
                        </small>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmarAcao(mensagem, url, icon, confirmText) {
    const iconType = icon || 'warning';
    const confirmClass = iconType === 'error' ? 'btn btn-danger mx-1' : 'btn btn-warning mx-1';

    Swal.fire({
        title: 'Confirmar',
        text: mensagem,
        icon: iconType,
        showCancelButton: true,
        confirmButtonText: confirmText || 'Confirmar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            confirmButton: confirmClass,
            cancelButton: 'btn btn-secondary mx-1'
        }
    }).then(result => {
        if (result.isConfirmed) window.location.href = url;
    });
}
</script>