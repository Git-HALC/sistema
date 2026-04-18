<?php
/** @var \App\Modules\Servicos\ServicoCatalogo $servico */
/** @var bool $editando */
$baseUrl = tenantUrl('admin/servicos.php');
?>
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo $editando ? 'Editar Serviço' : 'Novo Serviço'; ?></h1>
        <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <?php $msg = $_SESSION['mensagem']; unset($_SESSION['mensagem']); ?>
        <div class="alert alert-<?php echo $msg['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($baseUrl); ?>" autocomplete="off">
                <input type="hidden" name="action" value="store">
                <?php if ($editando && $servico->id): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$servico->id; ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" maxlength="255" required
                               class="form-control"
                               value="<?php echo htmlspecialchars($servico->nome); ?>">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Valor Base (R$)</label>
                        <input type="number" name="valor_base" step="0.01" min="0"
                               class="form-control"
                               value="<?php echo htmlspecialchars(number_format($servico->valor_base, 2, '.', '')); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" rows="3" class="form-control"
                                  placeholder="Descrição opcional do serviço"><?php echo htmlspecialchars((string)$servico->descricao); ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="ativo" id="ativo" class="form-check-input"
                                   <?php echo $servico->ativo ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">Serviço ativo</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Salvar
                    </button>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
