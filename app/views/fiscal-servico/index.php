<?php

declare(strict_types=1);

$filtros = $filtros ?? [];
$notas = $notas ?? [];
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Fiscal de Servicos</h1>
            <p class="text-muted mb-0">Gerencie a emissao e o acompanhamento das NFS-e.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=configuracoes')); ?>" class="btn btn-outline-secondary">Configuracoes</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php')); ?>" class="row g-3">
                <input type="hidden" name="action" value="index">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (['pendente', 'enviada', 'cancelada', 'erro'] as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (($filtros['status'] ?? '') === $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Data inicial</label>
                    <input type="date" id="data_inicio" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['data_inicio'] ?? '')); ?>">
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Data final</label>
                    <input type="date" id="data_fim" name="data_fim" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['data_fim'] ?? '')); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=index')); ?>" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Gerar NFS-e de um servico faturado</h2>
            <form method="post" action="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=gerar')); ?>" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Support\CsrfProtection::token()); ?>">
                <div class="col-md-9">
                    <label for="servico_id" class="form-label">ID do servico</label>
                    <input type="text" id="servico_id" name="servico_id" class="form-control" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Gerar NFS-e</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Numero</th>
                        <th>RPS</th>
                        <th>Servico</th>
                        <th>Cliente</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($notas === []): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Nenhuma NFS-e encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notas as $nota): ?>
                            <?php
                            $status = strtoupper((string)($nota['status'] ?? ''));
                            $badge = match ($status) {
                                'ENVIADA' => 'success',
                                'CANCELADA' => 'secondary',
                                'ERRO' => 'danger',
                                default => 'warning text-dark',
                            };
                            ?>
                            <tr>
                                <td><span class="badge bg-<?php echo htmlspecialchars($badge); ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                <td><?php echo htmlspecialchars((string)($nota['numero_nfse'] ?? 'Pendente')); ?></td>
                                <td><?php echo htmlspecialchars((string)($nota['numero_rps'] ?? '')); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars((string)($nota['servico_nome'] ?? '')); ?></div>
                                    <div class="text-muted small">#<?php echo htmlspecialchars((string)($nota['servico_numero'] ?? $nota['servico_id'] ?? '')); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars((string)($nota['cliente_nome'] ?? 'Nao informado')); ?></td>
                                <td>R$ <?php echo number_format((float)($nota['servico_valor'] ?? 0), 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars((string)($nota['created_at'] ?? '')); ?></td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=detalhe&id=' . urlencode((string)$nota['id']))); ?>" class="btn btn-sm btn-outline-primary">Detalhe</a>
                                        <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=pdf&id=' . urlencode((string)$nota['id']))); ?>" class="btn btn-sm btn-outline-secondary">PDF</a>
                                        <?php if (($nota['status'] ?? '') === 'erro'): ?>
                                            <form method="post" action="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=reenviar')); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Support\CsrfProtection::token()); ?>">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$nota['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Reenviar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (($nota['status'] ?? '') === 'enviada'): ?>
                                            <form method="post" action="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=enviar-email')); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Support\CsrfProtection::token()); ?>">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$nota['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-info">E-mail</button>
                                            </form>
                                            <form method="post" action="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=cancelar')); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Support\CsrfProtection::token()); ?>">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$nota['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
