<?php
$servicoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_servico_base_url', 'admin/servicos.php')
    : tenantUrl('admin/servicos.php');
$catalogoServicoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=catalogo')
    : $servicoBaseUrl . '?action=catalogo';
$novoServicoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=novo')
    : $servicoBaseUrl . '?action=novo';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Lista de ServiÃ§os</h1>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($catalogoServicoUrl) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-wrench"></i> Cadastrar serviÃ§os</a>
            <a href="<?= htmlspecialchars($novoServicoUrl) ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Iniciar novo serviÃ§o</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="<?= htmlspecialchars($servicoBaseUrl) ?>" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="listar">
                <div class="col-md-4">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filtros['busca'] ?? '')) ?>" placeholder="NÂº, cliente, serviÃ§o, placa...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (($statusOpcoes ?? []) as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= ($filtros['status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars(\App\Modules\Servicos\Servico::STATUS_LABELS[$status] ?? $status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data inicial</label>
                    <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filtros['data_inicio'] ?? '')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data final</label>
                    <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filtros['data_fim'] ?? '')) ?>">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>NÃºmero</th>
                            <th>Cliente</th>
                            <th>ServiÃ§o</th>
                            <th>Produto</th>
                            <th>Qtd.</th>
                            <th>VeÃ­culo</th>
                            <th>Data</th>
                            <th class="text-end">Valor</th>
                            <th>Status</th>
                            <th class="text-end">AÃ§Ãµes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($servicos ?? []) as $servico): ?>
                            <?php
                            $status = strtoupper((string)($servico['status'] ?? ''));
                            $visualizarUrl = function_exists('dmBuildUrl')
                                ? dmBuildUrl($servicoBaseUrl, 'action=visualizar&id=' . urlencode((string)($servico['id'] ?? '')))
                                : $servicoBaseUrl . '?action=visualizar&id=' . urlencode((string)($servico['id'] ?? ''));
                            $editarUrl = function_exists('dmBuildUrl')
                                ? dmBuildUrl($servicoBaseUrl, 'action=editar&id=' . urlencode((string)($servico['id'] ?? '')))
                                : $servicoBaseUrl . '?action=editar&id=' . urlencode((string)($servico['id'] ?? ''));
                            $faturarUrl = function_exists('dmBuildUrl')
                                ? dmBuildUrl($servicoBaseUrl, 'action=faturar&id=' . urlencode((string)($servico['id'] ?? '')))
                                : $servicoBaseUrl . '?action=faturar&id=' . urlencode((string)($servico['id'] ?? ''));
                            $estornarUrl = function_exists('dmBuildUrl')
                                ? dmBuildUrl($servicoBaseUrl, 'action=estornar&id=' . urlencode((string)($servico['id'] ?? '')))
                                : $servicoBaseUrl . '?action=estornar&id=' . urlencode((string)($servico['id'] ?? ''));
                            $cancelarUrl = function_exists('dmBuildUrl')
                                ? dmBuildUrl($servicoBaseUrl, 'action=cancelar&id=' . urlencode((string)($servico['id'] ?? '')))
                                : $servicoBaseUrl . '?action=cancelar&id=' . urlencode((string)($servico['id'] ?? ''));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($servico['numero'] ?? '')) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string)($servico['nome_cliente'] ?? 'â€”')) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string)($servico['telefone_cliente'] ?? '')) ?></div>
                                </td>
                                <td><?= htmlspecialchars((string)($servico['servico_nome'] ?? 'â€”')) ?></td>
                                <td><?= htmlspecialchars((string)($servico['produto_nome'] ?? 'â€”')) ?></td>
                                <td><?= (float)($servico['produto_quantidade'] ?? 0) > 0 ? htmlspecialchars(number_format((float)($servico['produto_quantidade'] ?? 0), 4, ',', '.')) : 'â€”' ?></td>
                                <td>
                                    <div><?= htmlspecialchars((string)($servico['placa'] ?? 'â€”')) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string)($servico['modelo_veiculo'] ?? '')) ?></div>
                                </td>
                                <td><?= !empty($servico['data_servico']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$servico['data_servico']))) : 'â€”' ?></td>
                                <td class="text-end">R$ <?= number_format((float)($servico['valor_total'] ?? 0), 2, ',', '.') ?></td>
                                <td><span class="badge <?= \App\Modules\Servicos\Servico::STATUS_BADGES[$status] ?? 'bg-secondary' ?>"><?= htmlspecialchars(\App\Modules\Servicos\Servico::STATUS_LABELS[$status] ?? $status) ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= htmlspecialchars($visualizarUrl) ?>" class="btn btn-outline-secondary" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if (($servico['status'] ?? '') !== 'FATURADO' && ($servico['status'] ?? '') !== 'CANCELADO'): ?>
                                            <a href="<?= htmlspecialchars($editarUrl) ?>" class="btn btn-outline-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (($servico['status'] ?? '') !== 'FATURADO' && ($servico['status'] ?? '') !== 'CANCELADO'): ?>
                                            <a href="<?= htmlspecialchars($faturarUrl) ?>" class="btn btn-outline-success" title="Faturar">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (($servico['status'] ?? '') === 'FATURADO'): ?>
                                            <a href="<?= htmlspecialchars($estornarUrl) ?>" class="btn btn-outline-warning" title="Estornar faturamento" data-confirm-action="estornar-faturamento-lista-servico">
                                                <i class="fas fa-undo"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (($servico['status'] ?? '') !== 'CANCELADO' && ($servico['status'] ?? '') !== 'FATURADO'): ?>
                                            <a href="<?= htmlspecialchars($cancelarUrl) ?>" class="btn btn-outline-danger" title="Cancelar" data-confirm-action="cancelar-servico-lista">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($servicos)): ?>
                            <tr><td colspan="10" class="text-center text-muted p-4">Nenhum serviÃ§o encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal === 'undefined') return;

    document.querySelectorAll('[data-confirm-action="estornar-faturamento-lista-servico"], [data-confirm-action="cancelar-servico-lista"]').forEach(function (botao) {
        botao.addEventListener('click', function (event) {
            event.preventDefault();

            const acao = botao.getAttribute('data-confirm-action');
            const isEstorno = acao === 'estornar-faturamento-lista-servico';

            Swal.fire({
                title: isEstorno ? 'Estornar faturamento?' : 'Confirmar cancelamento?',
                text: isEstorno
                    ? 'Essa aÃ§Ã£o removerÃ¡ as contas a receber geradas por este serviÃ§o. Deseja continuar?'
                    : 'Confirma cancelamento do serviÃ§o?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: isEstorno ? 'Estornar faturamento' : 'Cancelar serviÃ§o',
                cancelButtonText: 'Voltar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-warning mx-1',
                    cancelButton: 'btn btn-secondary mx-1'
                },
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    window.location.href = botao.getAttribute('href');
                }
            });
        });
    });
});
</script>
