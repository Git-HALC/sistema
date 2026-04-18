<?php
$servicoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_servico_base_url', 'admin/servicos.php')
    : tenantUrl('admin/servicos.php');
$kanbanServicoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=kanban')
    : $servicoBaseUrl . '?action=kanban';
$salvarCatalogoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=salvar-catalogo')
    : $servicoBaseUrl . '?action=salvar-catalogo';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Cadastro de Serviços</h1>
        <a href="<?= htmlspecialchars($kanbanServicoUrl) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header"><?= $catalogoEdicao ? 'Editar serviço' : 'Novo serviço' ?></div>
                <div class="card-body">
                    <form method="POST" action="<?= htmlspecialchars($salvarCatalogoUrl) ?>">
                        <?php if ($catalogoEdicao): ?>
                            <input type="hidden" name="id" value="<?= (int)$catalogoEdicao->id ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars((string)($catalogoEdicao?->nome ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars((string)($catalogoEdicao?->descricao ?? '')) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor base</label>
                            <input type="number" name="valor_base" class="form-control" min="0" step="0.01" value="<?= number_format((float)($catalogoEdicao?->valorBase ?? 0), 2, '.', '') ?>">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="ativo" id="ativo" class="form-check-input" <?= !isset($catalogoEdicao) || $catalogoEdicao->ativo ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Salvar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">Serviços cadastrados</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>Valor base</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($servicosCatalogo ?? []) as $item): ?>
                                    <?php
                                    $editarCatalogoUrl = function_exists('dmBuildUrl')
                                        ? dmBuildUrl($servicoBaseUrl, 'action=catalogo&id=' . (int)$item->id)
                                        : $servicoBaseUrl . '?action=catalogo&id=' . (int)$item->id;
                                    $excluirCatalogoUrl = function_exists('dmBuildUrl')
                                        ? dmBuildUrl($servicoBaseUrl, 'action=excluir-catalogo&id=' . (int)$item->id)
                                        : $servicoBaseUrl . '?action=excluir-catalogo&id=' . (int)$item->id;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($item->nome) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($item->descricao ?? '')) ?></div>
                                        </td>
                                        <td>R$ <?= number_format((float)$item->valorBase, 2, ',', '.') ?></td>
                                        <td><span class="badge <?= $item->ativo ? 'bg-success' : 'bg-secondary' ?>"><?= $item->ativo ? 'Ativo' : 'Inativo' ?></span></td>
                                        <td class="text-end">
                                            <a href="<?= htmlspecialchars($editarCatalogoUrl) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <a href="<?= htmlspecialchars($excluirCatalogoUrl) ?>" class="btn btn-sm btn-outline-danger js-confirmar-exclusao-servico" data-servico-nome="<?= htmlspecialchars((string)$item->nome, ENT_QUOTES, 'UTF-8') ?>">Excluir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($servicosCatalogo)): ?>
                                    <tr><td colspan="4" class="text-center text-muted p-4">Nenhum serviço cadastrado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-confirmar-exclusao-servico').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();

            const url = link.getAttribute('href');
            const nome = link.dataset.servicoNome || 'este serviço';

            Swal.fire({
                title: 'Confirmar exclusão?',
                text: 'Tem certeza que deseja excluir o serviço "' + nome + '"?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then(function (result) {
                if (result.isConfirmed && url) {
                    window.location.href = url;
                }
            });
        });
    });
});
</script>
