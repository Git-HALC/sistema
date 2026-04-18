<?php include __DIR__ . '/../../../../public/includes/header.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $titulo; ?></h1>
        <div class="d-flex gap-2">
            <a href="/sistema_dm/public/admin/financeiro/movimentacoes.php?action=exportar-pdf" 
               class="btn btn-sm btn-danger shadow-sm" title="Exportar para PDF">
                <i class="fas fa-file-pdf fa-sm text-white-50"></i> PDF
            </a>
            <a href="/sistema_dm/public/admin/financeiro/movimentacoes.php?action=novo" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nova Movimentação
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <!-- Versão Desktop -->
            <div class="d-none d-md-block">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="conta_id" class="form-label">Conta:</label>
                        <select class="form-control" id="conta_id" name="conta_id">
                            <option value="">Todas</option>
                            <?php foreach ($contas as $conta): ?>
                                <option value="<?php echo $conta['id']; ?>" <?php echo (isset($_GET['conta_id']) && $_GET['conta_id'] == $conta['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conta['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="tipo" class="form-label">Tipo:</label>
                        <select class="form-control" id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="Entrada" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == 'Entrada') ? 'selected' : ''; ?>>Entrada</option>
                            <option value="Saída" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == 'Saída') ? 'selected' : ''; ?>>Saída</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="data_inicio" class="form-label">Data Início:</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="data_fim" class="form-label">Data Fim:</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="/sistema_dm/public/admin/financeiro/movimentacoes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Versão Mobile -->
            <div class="d-md-none">
                <form method="get" action="">
                    <div class="mb-3">
                        <label for="conta_id_mobile" class="form-label">Conta:</label>
                        <select class="form-control" id="conta_id_mobile" name="conta_id">
                            <option value="">Todas</option>
                            <?php foreach ($contas as $conta): ?>
                                <option value="<?php echo $conta['id']; ?>" <?php echo (isset($_GET['conta_id']) && $_GET['conta_id'] == $conta['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conta['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo_mobile" class="form-label">Tipo:</label>
                        <select class="form-control" id="tipo_mobile" name="tipo">
                            <option value="">Todos</option>
                            <option value="Entrada" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == 'Entrada') ? 'selected' : ''; ?>>Entrada</option>
                            <option value="Saída" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == 'Saída') ? 'selected' : ''; ?>>Saída</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="data_inicio_mobile" class="form-label">Data Início:</label>
                        <input type="date" class="form-control" id="data_inicio_mobile" name="data_inicio" value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="data_fim_mobile" class="form-label">Data Fim:</label>
                        <input type="date" class="form-control" id="data_fim_mobile" name="data_fim" value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>">
                    </div>
                    
                    <div class="d-grid gap-2 d-flex">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="/sistema_dm/public/admin/financeiro/movimentacoes.php" class="btn btn-secondary flex-fill">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Card de Listagem -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Movimentações</h6>
            <div>
                <span class="badge bg-primary">Total: <?php echo $total; ?> movimentação(ões)</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($movimentacoes)): ?>
                <div class="alert alert-info">Nenhuma movimentação encontrada.</div>
            <?php else: ?>
                <!-- Versão Desktop -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Conta</th>
                                    <th>Tipo</th>
                                    <th>Valor</th>
                                    <th>Descrição</th>
                                    <th>Usuário</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimentacoes as $mov): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($mov['data_movimentacao'])); ?></td>
                                        <td><?php echo htmlspecialchars($mov['conta_nome'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $mov['tipo'] === 'Entrada' ? 'success' : 'danger'; ?>">
                                                <?php echo $mov['tipo'] === 'Entrada' ? 'Entrada' : 'Saída'; ?>
                                            </span>
                                        </td>
                                        <td>R$ <?php echo number_format($mov['valor'], 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($mov['descricao'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($mov['usuario_nome'] ?? '-'); ?></td>
                                        <td class="text-center">
                                            <?php if (empty($mov['conta_receber_id']) && empty($mov['conta_pagar_id'])): ?>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $mov['id']; ?>" 
                                                    data-tipo="<?php echo $mov['tipo']; ?>" 
                                                    data-conta-id="<?php echo $mov['conta_id']; ?>" 
                                                    data-categoria-dre-id="<?php echo $mov['categoria_dre_id'] ?? ''; ?>" 
                                                    data-valor="<?php echo $mov['valor']; ?>" 
                                                    data-descricao="<?php echo htmlspecialchars($mov['descricao'] ?? ''); ?>" 
                                                    data-data-movimentacao="<?php echo substr($mov['data_movimentacao'], 0, 10); ?>"
                                                    data-referencia-tipo="<?php echo (!empty($mov['conta_receber_id']) ? 'ContaReceber' : (!empty($mov['conta_pagar_id']) ? 'ContaPagar' : '')); ?>"
                                                    data-referencia-id="<?php echo $mov['conta_receber_id'] ?? $mov['conta_pagar_id'] ?? ''; ?>"
                                                    title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-excluir" 
                                                            data-id="<?php echo $mov['id']; ?>" 
                                                            title="Excluir">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-info" title="Esta movimentação foi gerada automaticamente de um título e não pode ser editada.">Vinculada</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Versão Mobile -->
                <div class="d-md-none">
                    <?php foreach ($movimentacoes as $mov): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($mov['conta_nome'] ?? 'N/A'); ?></h6>
                                        <p class="card-text small text-muted mb-1">
                                            <?php echo date('d/m/Y', strtotime($mov['data_movimentacao'])); ?>
                                        </p>
                                        <?php if ($mov['descricao']): ?>
                                            <p class="card-text small mb-1"><?php echo htmlspecialchars($mov['descricao']); ?></p>
                                        <?php endif; ?>
                                        <p class="card-text small mb-1">Usuário: <?php echo htmlspecialchars($mov['usuario_nome'] ?? '-'); ?></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $mov['tipo'] === 'Entrada' ? 'success' : 'danger'; ?>">
                                            <?php echo $mov['tipo'] === 'Entrada' ? 'Entrada' : 'Saída'; ?>
                                        </span>
                                        <div class="mt-1">
                                            <strong>R$ <?php echo number_format($mov['valor'], 2, ',', '.'); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (empty($mov['conta_receber_id']) && empty($mov['conta_pagar_id'])): ?>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-primary btn-editar flex-fill" 
                                                data-id="<?php echo $mov['id']; ?>" 
                                                data-tipo="<?php echo $mov['tipo']; ?>" 
                                                data-conta-id="<?php echo $mov['conta_id']; ?>" 
                                                data-categoria-dre-id="<?php echo $mov['categoria_dre_id'] ?? ''; ?>" 
                                                data-valor="<?php echo $mov['valor']; ?>" 
                                                data-descricao="<?php echo htmlspecialchars($mov['descricao'] ?? ''); ?>" 
                                                data-data-movimentacao="<?php echo substr($mov['data_movimentacao'], 0, 10); ?>"
                                                data-referencia-tipo="<?php echo (!empty($mov['conta_receber_id']) ? 'ContaReceber' : (!empty($mov['conta_pagar_id']) ? 'ContaPagar' : '')); ?>"
                                                data-referencia-id="<?php echo $mov['conta_receber_id'] ?? $mov['conta_pagar_id'] ?? ''; ?>">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger btn-excluir flex-fill" 
                                                data-id="<?php echo $mov['id']; ?>">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle"></i> Esta movimentação foi gerada automaticamente de um título.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Navegação de páginas" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagina > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">Anterior</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($pagina < $totalPaginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">Próximo</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Edição -->
<div class="modal fade" id="modalEditarMovimentacao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Movimentação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/sistema_dm/public/admin/financeiro/movimentacoes.php">
                <input type="hidden" name="action" value="atualizar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_tipo" class="form-label">Tipo *</label>
                            <select class="form-select" id="edit_tipo" name="tipo" required>
                                <option value="Entrada">Entrada (Receita)</option>
                                <option value="Saída">Saída (Despesa)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_conta_id" class="form-label">Conta *</label>
                            <select class="form-select" id="edit_conta_id" name="conta_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($contas as $conta): ?>
                                    <option value="<?php echo $conta['id']; ?>">
                                        <?php echo htmlspecialchars($conta['nome']); ?> 
                                        (Saldo: R$ <?php echo number_format($conta['saldo_atual'], 2, ',', '.'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_categoria_dre_id" class="form-label">Categoria DRE *</label>
                            <select class="form-select" id="edit_categoria_dre_id" name="categoria_dre_id" required>
                                <option value="">Selecione o tipo primeiro</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_valor" class="form-label">Valor *</label>
                            <input type="number" step="0.01" class="form-control" id="edit_valor" name="valor" required min="0.01">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="edit_data_movimentacao" class="form-label">Data da Movimentação *</label>
                            <input type="date" class="form-control" id="edit_data_movimentacao" name="data_movimentacao" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="edit_descricao" name="descricao" rows="3" placeholder="Descreva a movimentação..."></textarea>
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

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Carregar categorias DRE para o modal de edição
    const categoriasReceita = <?php echo json_encode($categoriasReceita ?? []); ?>;
    const categoriasDespesa = <?php echo json_encode($categoriasDespesa ?? []); ?>;
    
    // Botão de edição
    $('.btn-editar').on('click', function() {
        const btn = $(this);
        const referenciaTipo = btn.data('referencia-tipo') || '';
        
        // Permitir edição apenas para movimentações manuais
        if (referenciaTipo === 'ContaReceber' || referenciaTipo === 'ContaPagar') {
            alert('Movimentações provenientes de contas a receber/pagar não podem ser editadas. Estorne o título original para fazer alterações.');
            return;
        }
        
        $('#edit_id').val(btn.data('id'));
        $('#edit_tipo').val(btn.data('tipo'));
        $('#edit_conta_id').val(btn.data('conta-id'));
        $('#edit_categoria_dre_id').val(btn.data('categoria-dre-id') || '');
        $('#edit_valor').val(btn.data('valor'));
        $('#edit_descricao').val(btn.data('descricao'));
        $('#edit_data_movimentacao').val(btn.data('data-movimentacao'));
        
        // Carregar categorias baseado no tipo
        const tipo = btn.data('tipo');
        const categoriaSelect = $('#edit_categoria_dre_id');
        const categoriaAtualId = btn.data('categoria-dre-id');
        categoriaSelect.empty();
        
        if (tipo === 'Entrada') {
            categoriaSelect.append('<option value="">Selecione...</option>');
            categoriasReceita.forEach(function(cat) {
                const selected = cat.id == categoriaAtualId ? ' selected' : '';
                categoriaSelect.append(`<option value="${cat.id}"${selected}>${cat.nome}</option>`);
            });
        } else if (tipo === 'Saída') {
            categoriaSelect.append('<option value="">Selecione...</option>');
            categoriasDespesa.forEach(function(cat) {
                const selected = cat.id == categoriaAtualId ? ' selected' : '';
                categoriaSelect.append(`<option value="${cat.id}"${selected}>${cat.nome}</option>`);
            });
        }
        
        $('#modalEditarMovimentacao').modal('show');
    });
    
    // Mudança de tipo no modal de edição
    $('#edit_tipo').on('change', function() {
        const tipo = $(this).val();
        const categoriaSelect = $('#edit_categoria_dre_id');
        const valorAtual = categoriaSelect.val();
        
        categoriaSelect.empty();
        
        if (tipo === 'Entrada') {
            categoriaSelect.append('<option value="">Selecione...</option>');
            categoriasReceita.forEach(function(cat) {
                categoriaSelect.append(`<option value="${cat.id}">${cat.nome}</option>`);
            });
        } else if (tipo === 'Saída') {
            categoriaSelect.append('<option value="">Selecione...</option>');
            categoriasDespesa.forEach(function(cat) {
                categoriaSelect.append(`<option value="${cat.id}">${cat.nome}</option>`);
            });
        }
        
        // Tentar manter o valor anterior se ainda for válido
        if (valorAtual) {
            $('#edit_categoria_dre_id').val(valorAtual);
        }
    });
    
    // Botão de exclusão
    $('.btn-excluir').on('click', function() {
        const id = $(this).data('id');
        const referenciaTipo = $(this).data('referencia-tipo');
        const referenciaId = $(this).data('referencia-id');
        
        // Se for movimentação de título, perguntar se deseja estornar o título
        if (referenciaTipo === 'ContaReceber' || referenciaTipo === 'ContaPagar') {
            const tipoTitulo = referenciaTipo === 'ContaReceber' ? 'Conta a Receber' : 'Conta a Pagar';
            const mensagem = `Deseja estornar o ${tipoTitulo} associado?`;

            Swal.fire({
                title: 'Confirmar',
                text: mensagem,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Estornar',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-warning mx-1',
                    cancelButton: 'btn btn-secondary mx-1'
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    estornarTitulo(referenciaTipo, referenciaId);
                }
            });
            return;
        }
        
        // Para movimentações manuais, usar exclusão normal com confirmação
        Swal.fire({
            title: 'Confirmar',
            text: 'Tem certeza que deseja excluir esta movimentação?',
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-danger mx-1',
                cancelButton: 'btn btn-secondary mx-1'
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                excluirMovimentacao(id);
            }
        });
    });
    
    function estornarTitulo(tipo, id) {
        const url = tipo === 'ContaReceber' 
            ? '/sistema_dm/public/admin/financeiro/contas-receber.php'
            : '/sistema_dm/public/admin/financeiro/contas-pagar.php';
        
        $.ajax({
            url: url,
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: { 
                action: 'estornar', 
                id: id,
                estorno_via_movimentacoes: true // Flag para identificar origem
            },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    Swal.fire({
                        title: 'Sucesso',
                        text: resp.message || 'Título estornado com sucesso!',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        buttonsStyling: false,
                        customClass: {
                            confirmButton: 'btn btn-warning mx-1'
                        }
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', resp.message || 'Erro ao estornar título.', 'error');
                }
            },
            error: function(xhr, status, error) {
                if (xhr.status === 403) {
                    Swal.fire('Erro', 'Erro de permissão ao estornar título.', 'error');
                } else {
                    Swal.fire('Erro', 'Erro de comunicação ao estornar título: ' + error, 'error');
                }
            }
        });
    }
    
    function excluirMovimentacao(id) {
        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/movimentacoes.php',
            method: 'POST',
            data: { action: 'excluir', id: id },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    location.reload();
                } else {
                    Swal.fire('Erro', resp.message, 'error');
                }
            }
        });
    }
});
</script>

