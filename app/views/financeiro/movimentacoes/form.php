<?php include __DIR__ . '/../../../../public/includes/header.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $titulo; ?></h1>
        <a href="/sistema_dm/public/admin/financeiro/movimentacoes.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Voltar
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Dados da Movimentação</h6>
        </div>
        <div class="card-body">
            <form method="post" action="/sistema_dm/public/admin/financeiro/movimentacoes.php">
                <input type="hidden" name="action" value="salvar">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="tipo" class="form-label">Tipo *</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="">Selecione...</option>
                            <option value="Entrada">Entrada (Receita)</option>
                            <option value="Saida">Saída (Despesa)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="conta_id" class="form-label">Conta *</label>
                        <select class="form-select" id="conta_id" name="conta_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($contas as $conta): ?>
                                <option value="<?php echo $conta['id']; ?>">
                                    <?php echo htmlspecialchars($conta['nome']); ?> 
                                    (Saldo: R$ <?php echo number_format($conta['saldo_atual'], 2, ',', '.'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="categoria_dre_id" class="form-label">Categoria DRE *</label>
                        <select class="form-select" id="categoria_dre_id" name="categoria_dre_id" required>
                            <option value="">Selecione o tipo primeiro</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="valor" class="form-label">Valor *</label>
                        <input type="number" step="0.01" class="form-control" id="valor" name="valor" required min="0.01">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="data_movimentacao" class="form-label">Data da Movimentação *</label>
                        <input type="date" class="form-control" id="data_movimentacao" name="data_movimentacao" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                        <small class="form-text text-muted">Datas anteriores à atual não são permitidas. Mesma data é permitida. (Fuso: Brasil)</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3" placeholder="Descreva a movimentação..."></textarea>
                </div>
                
                <div class="d-flex justify-content-end">
                    <a href="/sistema_dm/public/admin/financeiro/movimentacoes.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../../public/includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const categoriasReceita = <?php echo json_encode($categoriasReceita); ?>;
    const categoriasDespesa = <?php echo json_encode($categoriasDespesa); ?>;
    const categoriaSelect = $('#categoria_dre_id');
    
    // Debug: mostrar categorias carregadas
    console.log('Categorias Receita:', categoriasReceita);
    console.log('Categorias Despesa:', categoriasDespesa);
    
    $('#tipo').on('change', function() {
        const tipo = $(this).val();
        categoriaSelect.empty();
        
        console.log('Tipo selecionado:', tipo);
        
        if (tipo === 'Entrada') {
            categoriaSelect.append('<option value="">Selecione...</option>');
            categoriasReceita.forEach(function(cat) {
                categoriaSelect.append(`<option value="${cat.id}">${cat.nome}</option>`);
            });
            console.log('Carregadas', categoriasReceita.length, 'categorias de receita');
        } else if (tipo === 'Saída') {
            categoriaSelect.append('<option value="">Selecione...</option>');
            categoriasDespesa.forEach(function(cat) {
                categoriaSelect.append(`<option value="${cat.id}">${cat.nome}</option>`);
            });
            console.log('Carregadas', categoriasDespesa.length, 'categorias de despesa');
        } else {
            categoriaSelect.append('<option value="">Selecione o tipo primeiro</option>');
        }
    });
});
</script>

