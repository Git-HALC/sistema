<?php
$tipos = [
    'D' => 'Dinheiro',
    'PIX' => 'PIX',
    'TB' => 'Transferencia Bancaria',
    'CC' => 'Cartao de Credito',
    'CD' => 'Cartao de Debito',
    'BOL' => 'Boleto',
    'AF' => 'A faturar',
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo $titulo; ?></h1>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalFormaPagamento">
            <i class="fas fa-plus me-1"></i> Nova Forma de Pagamento
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
            <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Banco</th>
                                <th>Adquirente</th>
                                <th class="text-end">Taxa</th>
                                <th class="text-center">Prazo</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($formasPagamento as $fp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$fp['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($tipos[$fp['tipo']] ?? (string)$fp['tipo']); ?></td>
                                    <td><?php echo htmlspecialchars((string)($fp['conta_nome'] ?? '—')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($fp['adquirente_nome'] ?? '—')); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($fp['taxa'] ?? 0), 2, ',', '.'); ?>%</td>
                                    <td class="text-center"><?php echo (int)($fp['prazo_dias'] ?? 0); ?> dias</td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo !empty($fp['ativo']) ? 'success' : 'secondary'; ?>">
                                            <?php echo !empty($fp['ativo']) ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-editar"
                                                data-id="<?php echo (int)$fp['id']; ?>"
                                                data-nome="<?php echo htmlspecialchars((string)$fp['nome']); ?>"
                                                data-tipo="<?php echo htmlspecialchars((string)$fp['tipo']); ?>"
                                                data-descricao="<?php echo htmlspecialchars((string)($fp['descricao'] ?? '')); ?>"
                                                data-adquirente-id="<?php echo (int)($fp['adquirente_id'] ?? 0); ?>"
                                                data-taxa="<?php echo htmlspecialchars((string)($fp['taxa'] ?? '0')); ?>"
                                                data-prazo-dias="<?php echo (int)($fp['prazo_dias'] ?? 0); ?>"
                                                data-conta-id="<?php echo (int)($fp['conta_id'] ?? 0); ?>"
                                                title="Editar"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-excluir" data-id="<?php echo (int)$fp['id']; ?>" title="Excluir">
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

            <div class="d-md-none p-3">
                <?php foreach ($formasPagamento as $fp): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars((string)$fp['nome']); ?></h6>
                                    <p class="card-text text-muted small mb-1"><?php echo htmlspecialchars($tipos[$fp['tipo']] ?? (string)$fp['tipo']); ?></p>
                                    <p class="card-text small mb-1">Banco: <?php echo htmlspecialchars((string)($fp['conta_nome'] ?? '—')); ?></p>
                                    <p class="card-text small mb-1">Adquirente: <?php echo htmlspecialchars((string)($fp['adquirente_nome'] ?? '—')); ?></p>
                                    <p class="card-text small mb-0">Taxa: <?php echo number_format((float)($fp['taxa'] ?? 0), 2, ',', '.'); ?>% | Prazo: <?php echo (int)($fp['prazo_dias'] ?? 0); ?> dias</p>
                                </div>
                                <span class="badge bg-<?php echo !empty($fp['ativo']) ? 'success' : 'secondary'; ?>">
                                    <?php echo !empty($fp['ativo']) ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                            <div class="d-flex gap-1">
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm btn-editar flex-fill"
                                    data-id="<?php echo (int)$fp['id']; ?>"
                                    data-nome="<?php echo htmlspecialchars((string)$fp['nome']); ?>"
                                    data-tipo="<?php echo htmlspecialchars((string)$fp['tipo']); ?>"
                                    data-descricao="<?php echo htmlspecialchars((string)($fp['descricao'] ?? '')); ?>"
                                    data-adquirente-id="<?php echo (int)($fp['adquirente_id'] ?? 0); ?>"
                                    data-taxa="<?php echo htmlspecialchars((string)($fp['taxa'] ?? '0')); ?>"
                                    data-prazo-dias="<?php echo (int)($fp['prazo_dias'] ?? 0); ?>"
                                    data-conta-id="<?php echo (int)($fp['conta_id'] ?? 0); ?>"
                                >
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-excluir flex-fill" data-id="<?php echo (int)$fp['id']; ?>">
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

<div class="modal fade" id="modalFormaPagamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Forma de Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/sistema_dm/public/admin/financeiro/formas-pagamento.php" id="formFormaPagamento">
                <input type="hidden" name="action" value="salvar">
                <input type="hidden" name="id" id="form_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>

                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo *</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <?php foreach ($tipos as $codigo => $rotulo): ?>
                                <option value="<?php echo htmlspecialchars($codigo); ?>"><?php echo htmlspecialchars($rotulo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descricao</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                    </div>

                    <div class="mb-3 campo-banco">
                        <label for="conta_id" class="form-label">Banco que recebe *</label>
                        <select class="form-select" id="conta_id" name="conta_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($contasBanco as $contaBanco): ?>
                                <option value="<?php echo (int)$contaBanco['id']; ?>">
                                    <?php echo htmlspecialchars((string)$contaBanco['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 campo-adquirente">
                        <label for="adquirente_id" class="form-label">Adquirente *</label>
                        <select class="form-select" id="adquirente_id" name="adquirente_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($fornecedores as $fornecedor): ?>
                                <option value="<?php echo (int)$fornecedor['id']; ?>">
                                    <?php echo htmlspecialchars((string)$fornecedor['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 campo-taxa">
                        <label for="taxa" class="form-label">Taxa (%) *</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="taxa" name="taxa" value="0">
                    </div>

                    <div class="mb-3 campo-prazo">
                        <label for="prazo_dias" class="form-label">Prazo de recebimento (dias) *</label>
                        <input type="number" min="0" class="form-control" id="prazo_dias" name="prazo_dias" value="0">
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
    function atualizarCamposPorTipo() {
        const tipo = ($('#tipo').val() || '').toUpperCase();
        const isDinheiro = tipo === 'D';
        const isPixOuTransferencia = tipo === 'PIX' || tipo === 'TB';
        const isCartao = tipo === 'CC' || tipo === 'CD';
        const isBoleto = tipo === 'BOL';
        const isAFaturar = tipo === 'AF';

        $('.campo-banco').toggle(isDinheiro || isPixOuTransferencia || isCartao || isBoleto);
        $('.campo-adquirente').toggle(isCartao);
        $('.campo-prazo').toggle(isCartao);
        $('.campo-taxa').toggle(isPixOuTransferencia || isCartao || isBoleto || isDinheiro);

        $('#adquirente_id').prop('required', isCartao);
        $('#prazo_dias').prop('required', isCartao);
        $('#conta_id').prop('required', !isAFaturar);
        $('#taxa').prop('required', !isAFaturar);

        if (isDinheiro) {
            $('#taxa').val('0').prop('readonly', true);
            $('#prazo_dias').val('0');
            $('#adquirente_id').val('');
        } else {
            $('#taxa').prop('readonly', false);
        }

        if (isPixOuTransferencia || isBoleto) {
            $('#adquirente_id').val('');
            $('#prazo_dias').val('0');
        }

        if (isAFaturar) {
            $('#taxa').val('0');
            $('#prazo_dias').val('0');
            $('#adquirente_id').val('');
            $('#conta_id').val('');
        }
    }

    function limparFormulario() {
        $('#form_id').val('');
        $('#nome').val('');
        $('#tipo').val('D');
        $('#descricao').val('');
        $('#adquirente_id').val('');
        $('#taxa').val('0');
        $('#prazo_dias').val('0');
        $('#conta_id').val('');
        atualizarCamposPorTipo();
    }

    $('.btn-editar').on('click', function () {
        $('#form_id').val($(this).data('id'));
        $('#nome').val($(this).data('nome'));
        $('#tipo').val($(this).data('tipo'));
        $('#descricao').val($(this).data('descricao'));
        $('#adquirente_id').val($(this).data('adquirente-id') || '');
        $('#taxa').val($(this).data('taxa') || '0');
        $('#prazo_dias').val($(this).data('prazo-dias') || '0');
        $('#conta_id').val($(this).data('conta-id') || '');
        atualizarCamposPorTipo();
        $('#modalFormaPagamento').modal('show');
    });

    $('.btn-excluir').on('click', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Excluir forma de pagamento?',
            text: 'Esta acao nao pode ser desfeita.',
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: '/sistema_dm/public/admin/financeiro/formas-pagamento.php',
                method: 'POST',
                data: { action: 'excluir', id: id },
                dataType: 'json',
                success: function (resp) {
                    resp.success ? location.reload() : Swal.fire('Erro', resp.message, 'error');
                },
                error: function () {
                    Swal.fire('Erro', 'Nao foi possivel excluir. Tente novamente.', 'error');
                },
            });
        });
    });

    $('#tipo').on('change', atualizarCamposPorTipo);

    $('#modalFormaPagamento').on('hidden.bs.modal', limparFormulario);

    limparFormulario();
});
</script>
