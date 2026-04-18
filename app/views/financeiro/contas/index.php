<div class="container-fluid">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo $titulo; ?></h1>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalConta">
            <i class="fas fa-plus me-1"></i> Nova Conta
        </button>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <!-- Desktop -->
            <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome do Banco</th>
                                <th>Tipo</th>
                                <th>Banco</th>
                                <th>Agência</th>
                                <th>Conta</th>
                                <th class="text-end">Saldo Atual</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas as $conta): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($conta['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($conta['tipo']); ?></td>
                                    <td><?php echo htmlspecialchars($conta['banco'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($conta['agencia'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($conta['numero_conta'] ?? '—'); ?></td>
                                    <td class="text-end">R$ <?php echo number_format($conta['saldo_atual'], 2, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $conta['ativo'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $conta['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-editar"
                                                    data-id="<?php echo $conta['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($conta['nome']); ?>"
                                                    data-tipo="<?php echo $conta['tipo']; ?>"
                                                    data-banco="<?php echo htmlspecialchars($conta['banco'] ?? ''); ?>"
                                                    data-agencia="<?php echo htmlspecialchars($conta['agencia'] ?? ''); ?>"
                                                    data-numero-conta="<?php echo htmlspecialchars($conta['numero_conta'] ?? ''); ?>"
                                                    data-saldo-inicial="<?php echo $conta['saldo_inicial']; ?>"
                                                    data-data-saldo-inicial="<?php echo !empty($conta['created_at']) ? date('Y-m-d', strtotime((string)$conta['created_at'])) : date('Y-m-d'); ?>"
                                                    data-ativo="<?php echo $conta['ativo'] ? '1' : '0'; ?>"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-excluir"
                                                    data-id="<?php echo $conta['id']; ?>"
                                                    title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($contas as $conta): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($conta['nome']); ?></h6>
                                    <p class="card-text text-muted small mb-1"><?php echo htmlspecialchars($conta['tipo']); ?></p>
                                    <?php if ($conta['agencia'] || $conta['numero_conta']): ?>
                                        <p class="card-text small mb-1">
                                            <?php if ($conta['agencia']): ?>Ag: <?php echo htmlspecialchars($conta['agencia']); ?><?php endif; ?>
                                            <?php if ($conta['agencia'] && $conta['numero_conta']): ?> | <?php endif; ?>
                                            <?php if ($conta['numero_conta']): ?>CC: <?php echo htmlspecialchars($conta['numero_conta']); ?><?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="card-text small mb-0">
                                        <strong>Saldo:</strong> R$ <?php echo number_format($conta['saldo_atual'], 2, ',', '.'); ?>
                                    </p>
                                </div>
                                <span class="badge bg-<?php echo $conta['ativo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $conta['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-outline-primary btn-sm btn-editar flex-fill"
                                        data-id="<?php echo $conta['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($conta['nome']); ?>"
                                        data-tipo="<?php echo $conta['tipo']; ?>"
                                        data-banco="<?php echo htmlspecialchars($conta['banco'] ?? ''); ?>"
                                        data-agencia="<?php echo htmlspecialchars($conta['agencia'] ?? ''); ?>"
                                        data-numero-conta="<?php echo htmlspecialchars($conta['numero_conta'] ?? ''); ?>"
                                        data-saldo-inicial="<?php echo $conta['saldo_inicial']; ?>"
                                        data-data-saldo-inicial="<?php echo !empty($conta['created_at']) ? date('Y-m-d', strtotime((string)$conta['created_at'])) : date('Y-m-d'); ?>"
                                        data-ativo="<?php echo $conta['ativo'] ? '1' : '0'; ?>">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-excluir flex-fill"
                                        data-id="<?php echo $conta['id']; ?>">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalConta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/sistema_dm/public/admin/financeiro/contas.php">
                <input type="hidden" name="action" value="salvar">
                <input type="hidden" name="id" id="form_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome da Conta *</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo *</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="Banco">Banco</option>
                            <option value="Caixa">Caixa</option>
                            <option value="Poupança">Poupança</option>
                            <option value="Investimento">Investimento</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="banco" class="form-label">Banco</label>
                        <input type="text" class="form-control" id="banco" name="banco">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="agencia" class="form-label">Agência</label>
                            <input type="text" class="form-control" id="agencia" name="agencia">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="numero_conta" class="form-label">Conta</label>
                            <input type="text" class="form-control" id="numero_conta" name="numero_conta">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="saldo_inicial" class="form-label">Saldo Inicial</label>
                        <input type="number" step="0.01" class="form-control" id="saldo_inicial" name="saldo_inicial" value="0">
                    </div>
                    <div class="mb-3">
                        <label for="data_saldo_inicial" class="form-label">Data do Saldo Inicial</label>
                        <input type="date" class="form-control" id="data_saldo_inicial" name="data_saldo_inicial" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="ativo" class="form-label">Status</label>
                        <select class="form-select" id="ativo" name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    $('button[data-bs-target="#modalConta"]').on('click', function () {
        $('#form_id').val('');
        $('#nome').val('');
        $('#tipo').val('Banco');
        $('#banco').val('');
        $('#agencia').val('');
        $('#numero_conta').val('');
        $('#saldo_inicial').val('0');
        $('#data_saldo_inicial').val('<?php echo date('Y-m-d'); ?>');
        $('#saldo_atual').val('0.00');
        $('#ativo').val('1');
    });

    $('.btn-editar').on('click', function () {
        $('#form_id').val($(this).data('id'));
        $('#nome').val($(this).data('nome'));
        $('#tipo').val($(this).data('tipo'));
        $('#banco').val($(this).data('banco'));
        $('#agencia').val($(this).data('agencia'));
        $('#numero_conta').val($(this).data('numeroConta'));
        $('#saldo_inicial').val($(this).data('saldoInicial'));
        $('#data_saldo_inicial').val($(this).data('dataSaldoInicial') || '<?php echo date('Y-m-d'); ?>');
        $('#ativo').val($(this).data('ativo') ? '1' : '0');
        $('#modalConta').modal('show');
    });



    $('.btn-excluir').on('click', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Excluir conta?',
            text: 'Esta ação não pode ser desfeita.',
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: '/sistema_dm/public/admin/financeiro/contas.php',
                method: 'POST',
                data: { action: 'excluir', id: id },
                dataType: 'json',
                success: function (resp) {
                    resp.success ? location.reload() : Swal.fire('Erro', resp.message, 'error');
                },
            });
        });
    });

    $('#modalConta').on('hidden.bs.modal', function () {
        $('#form_id').val('');
        $('#nome').val('');
        $('#tipo').val('Banco');
        $('#banco').val('');
        $('#agencia').val('');
        $('#numero_conta').val('');
        $('#saldo_inicial').val('0');
        $('#data_saldo_inicial').val('<?php echo date('Y-m-d'); ?>');
        $('#ativo').val('1');
    });

});
</script>
