<?php
/** @var array $subgrupo */
/** @var array $grupos */
/** @var bool $editando */
$baseUrl = tenantUrl('admin/produto-subgrupos.php');
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?= $editando ? 'Editar Subgrupo' : 'Novo Subgrupo' ?></h1>
        <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <?php $msg = $_SESSION['mensagem']; unset($_SESSION['mensagem']); ?>
        <div class="alert alert-<?= $msg['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $msg['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>" autocomplete="off">
                <input type="hidden" name="action" value="salvar">
                <?php if ($editando && !empty($subgrupo['id'])): ?>
                    <input type="hidden" name="id" value="<?= (int)$subgrupo['id'] ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Grupo <span class="text-danger">*</span></label>
                        <select name="grupo_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($grupos as $g): ?>
                                <option value="<?= (int)$g['id'] ?>" <?= ((int)($subgrupo['grupo_id'] ?? 0) === (int)$g['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$g['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control" maxlength="100" required
                               value="<?= htmlspecialchars((string)$subgrupo['nome']) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="ativo" id="ativo" class="form-check-input"
                                   <?= !empty($subgrupo['ativo']) ? 'checked' : '' ?>>
                            <label for="ativo" class="form-check-label">Ativo</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" rows="3" class="form-control"><?= htmlspecialchars((string)($subgrupo['descricao'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar</button>
                    <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
