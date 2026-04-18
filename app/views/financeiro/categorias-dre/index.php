<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $titulo; ?></h1>
        <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCategoria">
            <i class="fas fa-plus fa-sm text-white-50"></i> Nova Categoria
        </button>
    </div>

    <!-- Resumo das Categorias -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Receitas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count(array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Receita'; })); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Deduções</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count(array_filter($categorias ?? [], function($c) { return in_array($c['tipo'], ['Deducao', 'Dedução'], true); })); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-minus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">CPV/CMV</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count(array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'CPV'; })); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Despesas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $despesasOperacionais = count(array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa Operacional'; }));
                                $despesasFinanceiras = count(array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa Financeira'; }));
                                $despesasLegado = count(array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa'; }));
                                echo $despesasOperacionais + $despesasFinanceiras + $despesasLegado;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Receitas -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="m-0 font-weight-bold">Receitas</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $receitas = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Receita'; });
                                    foreach ($receitas as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="Receita"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $receitas = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Receita'; });
                        foreach ($receitas as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="Receita"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deduções -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-dark">
                    <h6 class="m-0 font-weight-bold">Deduções</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $deducoes = array_filter($categorias ?? [], function($c) { return in_array($c['tipo'], ['Deducao', 'Dedução'], true); });
                                    foreach ($deducoes as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="Deducao"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $deducoes = array_filter($categorias ?? [], function($c) { return in_array($c['tipo'], ['Deducao', 'Dedução'], true); });
                        foreach ($deducoes as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="Deducao"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CPV -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="m-0 font-weight-bold">CPV/CMV</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $cpv = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'CPV'; });
                                    foreach ($cpv as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="CPV"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $cpv = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'CPV'; });
                        foreach ($cpv as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="CPV"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Despesas Operacionais -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">Despesas Operacionais</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $despesasOperacionais = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa Operacional'; });
                                    foreach ($despesasOperacionais as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="Despesa Operacional"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $despesasOperacionais = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa Operacional'; });
                        foreach ($despesasOperacionais as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="Despesa Operacional"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Despesas Financeiras -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-secondary text-white">
                    <h6 class="m-0 font-weight-bold">Despesas Financeiras</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $despesasFinanceiras = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa Financeira'; });
                                    foreach ($despesasFinanceiras as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="Despesa Financeira"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $despesasFinanceiras = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa Financeira'; });
                        foreach ($despesasFinanceiras as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="Despesa Financeira"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tributos -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-dark text-white">
                    <h6 class="m-0 font-weight-bold">Tributos</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $tributos = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Tributo'; });
                                    foreach ($tributos as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="Tributo"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $tributos = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Tributo'; });
                        foreach ($tributos as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="Tributo"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Outras -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-light text-dark">
                    <h6 class="m-0 font-weight-bold">Outras</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $outras = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Outras'; });
                                    foreach ($outras as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="Outras"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $outras = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Outras'; });
                        foreach ($outras as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="Outras"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Despesas (legado) -->
        <div class="col-12 col-lg-6 col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">Despesas (Legado)</h6>
                </div>
                <div class="card-body">
                    <!-- Versão Desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Ordem</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $despesas = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa'; });
                                    foreach ($despesas as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo $cat['ordem']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-editar" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-tipo="Despesa"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                        data-ordem="<?php echo $cat['ordem']; ?>"
                                                        data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Versão Mobile -->
                    <div class="d-md-none">
                        <?php 
                        $despesas = array_filter($categorias ?? [], function($c) { return $c['tipo'] === 'Despesa'; });
                        foreach ($despesas as $cat): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['nome']); ?></h6>
                                            <small class="text-muted">Ordem: <?php echo $cat['ordem']; ?></small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary btn-editar" 
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                    data-tipo="Despesa"
                                                    data-descricao="<?php echo htmlspecialchars($cat['descricao'] ?? ''); ?>"
                                                    data-ordem="<?php echo $cat['ordem']; ?>"
                                                    data-ativo="<?php echo $cat['ativo'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-excluir" data-id="<?php echo $cat['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Categoria DRE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/sistema_dm/public/admin/financeiro/categorias-dre.php">
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
                            <option value="Receita">Receita</option>
                            <option value="Deducao">Dedução</option>
                            <option value="CPV">CPV/CMV</option>
                            <option value="Despesa Operacional">Despesa Operacional</option>
                            <option value="Despesa Financeira">Despesa Financeira</option>
                            <option value="Tributo">Tributo</option>
                            <option value="Outras">Outras</option>
                            <option value="Despesa">Despesa (Legado)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="ordem" class="form-label">Ordem</label>
                        <input type="number" class="form-control" id="ordem" name="ordem" value="0">
                        <small class="text-muted">Usado para ordenar as categorias na DRE</small>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                        <small class="text-muted">Categorias inativas não aparecem nos seletores</small>
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
$(document).ready(function() {
    $('.btn-editar').on('click', function() {
        $('#form_id').val($(this).data('id'));
        $('#nome').val($(this).data('nome'));
        $('#tipo').val($(this).data('tipo'));
        $('#ordem').val($(this).data('ordem'));
        $('#descricao').val($(this).data('descricao'));
        $('#ativo').prop('checked', $(this).data('ativo') === '1');
        $('#modalCategoria').modal('show');
    });
    
    $('.btn-excluir').on('click', function() {
        if (!confirm('Tem certeza que deseja excluir esta categoria?')) return;
        const id = $(this).data('id');
        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/categorias-dre.php',
            method: 'POST',
            data: { action: 'excluir', id: id },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    location.reload();
                } else {
                    alert(resp.message || 'Erro ao excluir categoria.');
                }
            },
            error: function() {
                alert('Erro ao comunicar com o servidor.');
            }
        });
    });
    
    $('#modalCategoria').on('hidden.bs.modal', function() {
        $('#form_id').val('');
        $('#nome').val('');
        $('#tipo').val('Receita');
        $('#ordem').val('0');
        $('#descricao').val('');
        $('#ativo').prop('checked', true);
    });
});
</script>

