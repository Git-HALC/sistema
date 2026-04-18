<div class="container-fluid">

    <!-- Cabe?alho -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo $titulo; ?></h1>
        <div class="d-flex gap-2">
            <a href="/sistema_dm/public/admin/financeiro/contas-pagar.php?action=exportar-pdf"
               class="btn btn-sm btn-danger" title="Exportar para PDF">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
            <a href="/sistema_dm/public/admin/financeiro/contas-pagar.php?action=novo"
               class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i> Nova Conta a Pagar
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

    <!-- Filtros (formul?rio ?nico responsivo) -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="get" action="" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label">Cliente/Fornecedor</label>
                    <select class="form-select form-select-sm" name="cliente_id">
                        <option value="">Todos</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>"
                                <?php echo (isset($_GET['cliente_id']) && $_GET['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nome'] . ($cliente['empresa'] ? ' - ' . $cliente['empresa'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="">Todos</option>
                        <option value="PENDENTE" <?php echo (isset($_GET['status']) && $_GET['status'] == 'PENDENTE') ? 'selected' : ''; ?>>Pendente</option>
                        <option value="PAGO" <?php echo (isset($_GET['status']) && $_GET['status'] == 'PAGO') ? 'selected' : ''; ?>>Pago</option>
                        <option value="VENCIDO" <?php echo (isset($_GET['status']) && $_GET['status'] == 'VENCIDO') ? 'selected' : ''; ?>>Vencido</option>
                        <option value="CANCELADO" <?php echo (isset($_GET['status']) && $_GET['status'] == 'CANCELADO') ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Vencimento de</label>
                    <input type="date" class="form-control form-control-sm" name="data_inicio"
                           value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Vencimento at?</label>
                    <input type="date" class="form-control form-control-sm" name="data_fim"
                           value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>">
                </div>
                <div class="col-6 col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="/sistema_dm/public/admin/financeiro/contas-pagar.php"
                       class="btn btn-outline-danger btn-sm" title="Limpar filtros">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Listagem -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">

            <?php if (empty($contasPagar)): ?>
                <div class="p-4 text-center text-muted">Nenhuma conta a pagar encontrada.</div>
            <?php else: ?>

                <!-- Tabela desktop -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fornecedor/Cliente</th>
                                    <th>Descri??o</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-end">Pago L?quido</th>
                                    <th class="text-end">Desconto</th>
                                    <th>Vencimento</th>
                                    <th>Status</th>
                                    <th class="text-end">A??es</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contasPagar as $conta):
                                    $dtVenc   = new DateTime($conta['data_vencimento']);
                                    $dtHoje   = new DateTime();
                                    $vencido  = $conta['status'] === 'PENDENTE' && $dtVenc < $dtHoje;
                                    $diasAtrs = $vencido ? $dtHoje->diff($dtVenc)->days : 0;
                                    $stTexto  = $conta['status'];
                                    $stCor    = match($conta['status']) {
                                        'PENDENTE'  => $vencido ? 'danger' : 'warning',
                                        'VENCIDO'   => 'danger',
                                        'PAGO'      => 'success',
                                        'CANCELADO' => 'secondary',
                                        default     => 'secondary',
                                    };
                                    if ($vencido) {
                                        if ($diasAtrs > 30)     { $stTexto = 'Vencido (Cr?tico)'; $stCor = 'danger'; }
                                        elseif ($diasAtrs > 15) { $stTexto = 'Vencido (Urgente)'; $stCor = 'warning'; }
                                        else                    { $stTexto = 'Vencido';            $stCor = 'danger'; }
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($conta['cliente_nome'] ?? $conta['fornecedor'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($conta['descricao'] ?? '?'); ?></td>
                                    <td class="text-end">
                                        <div>R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></div>
                                        <?php 
                                            $valorPago = (float)($conta['valor_pago'] ?? 0);
                                            $descontoTotal = (float)($conta['desconto'] ?? 0);
                                            $valorPagoLiquido = $valorPago;
                                            $emAberto = (float)$conta['valor'] - $valorPago - $descontoTotal;
                                        ?>
                                        <?php if ($emAberto > 0): ?>
                                            <small class="text-muted">Falta: R$ <?php echo number_format($emAberto, 2, ',', '.'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        R$ <?php echo number_format($valorPagoLiquido, 2, ',', '.'); ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($descontoTotal > 0): ?>
                                            <span class="badge bg-info">R$ <?php echo number_format($descontoTotal, 2, ',', '.'); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">?</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($vencido): ?>
                                            <span class="text-danger fw-semibold">
                                                <?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?>
                                                <small>(<?php echo $diasAtrs; ?>d)</small>
                                            </span>
                                        <?php else: ?>
                                            <?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $stCor; ?>">
                                            <?php echo htmlspecialchars($stTexto); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-info btn-visualizar"
                                                    data-id="<?php echo $conta['id']; ?>"
                                                    data-fornecedor="<?php echo htmlspecialchars($conta['cliente_nome'] ?? $conta['fornecedor'] ?? 'N/A'); ?>"
                                                    data-descricao="<?php echo htmlspecialchars($conta['descricao'] ?? ''); ?>"
                                                    data-valor="<?php echo $conta['valor']; ?>"
                                                    data-pago="<?php echo $valorPagoLiquido; ?>"
                                                    data-desconto="<?php echo $descontoTotal; ?>"
                                                    data-status="<?php echo $conta['status']; ?>"
                                                    title="Visualizar detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($conta['status'] !== 'PAGO' && $conta['status'] !== 'CANCELADO'): ?>
                                                <button type="button" class="btn btn-outline-success btn-baixar"
                                                        data-id="<?php echo $conta['id']; ?>"
                                                        data-valor="<?php echo $conta['valor']; ?>"
                                                        data-pago="<?php echo $valorPagoLiquido; ?>"
                                                        data-desconto="<?php echo $descontoTotal; ?>"
                                                        title="Baixar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($conta['status'] === 'PAGO'): ?>
                                                <button type="button" class="btn btn-outline-warning btn-estornar"
                                                        data-id="<?php echo $conta['id']; ?>"
                                                        data-pago="<?php echo $valorPagoLiquido; ?>"
                                                        data-desconto="<?php echo $descontoTotal; ?>"
                                                        title="Estornar">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($conta['status'] === 'PENDENTE' || $conta['status'] === 'VENCIDO'): ?>
                                                <a href="/sistema_dm/public/admin/financeiro/contas-pagar.php?action=editar&id=<?php echo $conta['id']; ?>"
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-excluir"
                                                        data-id="<?php echo $conta['id']; ?>"
                                                        title="Excluir">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cards mobile -->
                <div class="d-md-none p-3">
                    <?php foreach ($contasPagar as $conta):
                        $dtVenc   = new DateTime($conta['data_vencimento']);
                        $dtHoje   = new DateTime();
                        $vencido  = $conta['status'] === 'PENDENTE' && $dtVenc < $dtHoje;
                        $diasAtrs = $vencido ? $dtHoje->diff($dtVenc)->days : 0;
                        $stTexto  = $conta['status'];
                        $stCor    = match($conta['status']) {
                            'PENDENTE'  => $vencido ? 'danger' : 'warning',
                            'VENCIDO'   => 'danger',
                            'PAGO'      => 'success',
                            'CANCELADO' => 'secondary',
                            default     => 'secondary',
                        };
                        if ($vencido) {
                            if ($diasAtrs > 30)     { $stTexto = 'Vencido (Cr?tico)'; $stCor = 'danger'; }
                            elseif ($diasAtrs > 15) { $stTexto = 'Vencido (Urgente)'; $stCor = 'warning'; }
                            else                    { $stTexto = 'Vencido';            $stCor = 'danger'; }
                        }
                        
                        // Calcular valores para mobile
                        $valorPagoMobile = (float)($conta['valor_pago'] ?? 0);
                        $descontoTotalMobile = (float)($conta['desconto'] ?? 0);
                        $valorPagoLiquidoMobile = $valorPagoMobile;
                    ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1">
                                            <?php echo htmlspecialchars($conta['cliente_nome'] ?? $conta['fornecedor'] ?? 'N/A'); ?>
                                        </h6>
                                        <?php if ($conta['descricao']): ?>
                                            <p class="card-text small text-secondary mt-1 mb-0"><?php echo htmlspecialchars($conta['descricao']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-<?php echo $stCor; ?> ms-2 flex-shrink-0">
                                        <?php echo htmlspecialchars($stTexto); ?>
                                    </span>
                                </div>
                                <div class="row g-2 mb-3 small">
                                    <div class="col-6">
                                        <span class="text-muted d-block">Valor</span>
                                        <strong>R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Pago</span>
                                        <strong>R$ <?php echo number_format($conta['valor_pago'] ?? 0, 2, ',', '.'); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Vencimento</span>
                                        <?php if ($vencido): ?>
                                            <strong class="text-danger"><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?> <small>(<?php echo $diasAtrs; ?>d)</small></strong>
                                        <?php else: ?>
                                            <strong><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Pagamento</span>
                                        <strong><?php echo $conta['data_pagamento'] ? date('d/m/Y', strtotime($conta['data_pagamento'])) : '?'; ?></strong>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-wrap">
                                    <button type="button" class="btn btn-outline-info btn-sm btn-visualizar flex-fill"
                                            data-id="<?php echo $conta['id']; ?>"
                                            data-fornecedor="<?php echo htmlspecialchars($conta['cliente_nome'] ?? $conta['fornecedor'] ?? 'N/A'); ?>"
                                            data-descricao="<?php echo htmlspecialchars($conta['descricao'] ?? ''); ?>"
                                            data-valor="<?php echo $conta['valor']; ?>"
                                            data-pago="<?php echo $valorPagoLiquidoMobile; ?>"
                                            data-desconto="<?php echo $descontoTotalMobile; ?>"
                                            data-status="<?php echo $conta['status']; ?>">
                                        <i class="fas fa-eye"></i> Visualizar
                                    </button>
                                    <?php if ($conta['status'] !== 'PAGO' && $conta['status'] !== 'CANCELADO'): ?>
                                        <button type="button" class="btn btn-outline-success btn-sm btn-baixar flex-fill"
                                                data-id="<?php echo $conta['id']; ?>"
                                                data-valor="<?php echo $conta['valor']; ?>"
                                                data-pago="<?php echo $valorPagoLiquidoMobile; ?>"
                                                data-desconto="<?php echo $descontoTotalMobile; ?>">
                                            <i class="fas fa-check"></i> Baixar
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($conta['status'] === 'PAGO'): ?>
                                        <button type="button" class="btn btn-outline-warning btn-sm btn-estornar flex-fill"
                                                data-id="<?php echo $conta['id']; ?>"
                                                data-pago="<?php echo $valorPagoLiquidoMobile; ?>"
                                                data-desconto="<?php echo $descontoTotalMobile; ?>">
                                            <i class="fas fa-undo"></i> Estornar
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($conta['status'] === 'PENDENTE' || $conta['status'] === 'VENCIDO'): ?>
                                        <a href="/sistema_dm/public/admin/financeiro/contas-pagar.php?action=editar&id=<?php echo $conta['id']; ?>"
                                           class="btn btn-outline-primary btn-sm flex-fill">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-excluir flex-fill"
                                                data-id="<?php echo $conta['id']; ?>">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagina??o -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted"><?php echo $total; ?> t?tulo(s)</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">?</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($pagina < $totalPaginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">?</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php else: ?>
                    <div class="card-footer py-2">
                        <small class="text-muted"><?php echo $total; ?> t?tulo(s)</small>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Detalhes -->
<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-labelledby="modalDetalhesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDetalhesLabel">Detalhes da Conta a Pagar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label text-muted">Fornecedor/Cliente</label>
                        <p class="fs-5 fw-semibold" id="detalheFornecedor">?</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <label class="form-label text-muted">Status</label>
                        <div><span id="detalheStatus" class="badge bg-secondary">?</span></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label text-muted">Descri??o</label>
                        <p class="fs-6" id="detalheDescricao">?</p>
                    </div>
                </div>

                <!-- Resumo de valores -->
                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted d-block">Valor Total</small>
                                <strong id="detalheValor">R$ 0,00</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Desconto</small>
                                <strong id="detalheDesconto" class="text-info">R$ 0,00</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Pago L?quido</small>
                                <strong id="detalhePago">R$ 0,00</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block" id="detalheEmAbertoLabel">Saldo em Aberto</small>
                                <strong id="detalheEmAberto" class="text-danger">R$ 0,00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Baixa -->
<div class="modal fade" id="modalBaixa" tabindex="-1" aria-labelledby="modalBaixaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBaixaLabel">Baixar Conta a Pagar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formBaixa">
                <div class="modal-body">
                    <input type="hidden" id="baixa_id" name="id">
                    
                    <div class="alert alert-info mb-3">
                        <small>
                            <strong>Valor original:</strong> <span id="valor_original">R$ 0,00</span><br>
                            <strong>Valor em aberto:</strong> <span id="valor_em_aberto">R$ 0,00</span>
                        </small>
                    </div>

                    <!-- C?lculo de valores -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valor_pago" class="form-label">Valor Pago (dinheiro) *</label>
                            <input type="number" step="0.01" class="form-control" id="valor_pago" 
                                   name="valor_pago" placeholder="Ex: 900" required>
                            <small class="text-muted d-block mt-1">Quanto voc? pagou em dinheiro</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="desconto_pagamento" class="form-label">Desconto Obtido</label>
                            <input type="number" step="0.01" class="form-control" id="desconto_pagamento" 
                                   name="desconto_pagamento" value="0" min="0">
                            <small class="text-muted d-block mt-1">Desconto + valor pago devem quitar a conta</small>
                            <small class="text-danger d-none mt-1">Desconto s? permitido quando o total acumulado quita completamente</small>
                        </div>
                    </div>

                    <!-- C?lculo autom?tico -->
                    <div class="card bg-light mb-3">
                        <div class="card-body py-2">
                            <small>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Valor pago:</span>
                                    <strong id="calc_valor_pago">R$ 0,00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Mais desconto obtido:</span>
                                    <strong id="calc_desconto">R$ 0,00</strong>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <span>Valor total quitado:</span>
                                    <strong id="calc_total">R$ 0,00</strong>
                                </div>
                                <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                                    <span>Status ap?s pagamento:</span>
                                    <strong id="calc_status" class="badge bg-warning">-</strong>
                                </div>
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="data_pagamento" class="form-label">Data Pagamento *</label>
                        <input type="date" class="form-control" id="data_pagamento" name="data_pagamento"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="conta_id" class="form-label">Conta Banc?ria *</label>
                        <select class="form-select" id="conta_id" name="conta_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($contasBanco as $contaBanco): ?>
                                <option value="<?php echo $contaBanco['id']; ?>">
                                    <?php echo htmlspecialchars($contaBanco['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="categoria_dre_id" class="form-label">Categoria DRE (Despesa) *</label>
                        <select class="form-select" id="categoria_dre_id" name="categoria_dre_id" required>
                            <option value="">Carregando...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Baixar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Estorno -->
<div class="modal fade" id="modalEstorno" tabindex="-1" aria-labelledby="modalEstornoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalEstornoLabel">Estornar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEstorno">
                <div class="modal-body">
                    <input type="hidden" id="estorno_id" name="id">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Valor total pago:</strong> <span id="estornoValorTotal">R$ 0,00</span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor_estorno" class="form-label">Valor a Estornar</label>
                        <input type="number" step="0.01" class="form-control" id="valor_estorno" name="valor_estorno" placeholder="Ex: 50.00">
                        <small class="text-muted d-block mt-1">Deixe em branco ou informe o valor total para estornar tudo</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        O estorno reverter? o valor da <strong>?ltima movimenta??o</strong> registrada.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Estornar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    function carregarCategoriasDRE() {
        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/contas-pagar.php',
            method: 'GET',
            data: { action: 'buscar-categorias-dre' },
            dataType: 'json',
            success: function (resp) {
                const select = $('#categoria_dre_id');
                select.empty().append('<option value="">Selecione...</option>');
                if (resp.success && resp.data && resp.data.length > 0) {
                    resp.data.forEach(cat => select.append(`<option value="${cat.id}">${cat.nome} (${cat.tipo})</option>`));
                } else {
                    select.append('<option value="" disabled>Nenhuma categoria encontrada</option>');
                }
            },
        });
    }

    // Função para atualizar cálculos em tempo real
    function atualizarCalculosPagar() {
        const valorPago = parseFloat($('#valor_pago').val()) || 0;
        const desconto = parseFloat($('#desconto_pagamento').val()) || 0;
        const valorEmAberto = parseFloat($('#valor_em_aberto').data('valor')) || 0;
        
        const valorTotalQuitado = valorPago + desconto;
        const status = valorTotalQuitado >= valorEmAberto ? 'TOTALMENTE QUITADO' : 'PAGAMENTO PARCIAL';
        
        $('#calc_valor_pago').text('R$ ' + valorPago.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#calc_desconto').text('R$ ' + desconto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#calc_total').text('R$ ' + valorTotalQuitado.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#calc_status').text(status).removeClass().addClass('badge bg-' + (status === 'TOTALMENTE QUITADO' ? 'success' : 'warning'));
    }

    // Atualizar c?lculos quando usu?rio digitar
    $('#valor_pago, #desconto_pagamento').on('input', atualizarCalculosPagar);

    $('.btn-baixar').on('click', function () {
        const valor = parseFloat($(this).data('valor')) || 0;
        const pago = parseFloat($(this).data('pago')) || 0;
        const desconto = parseFloat($(this).data('desconto')) || 0;
        const emAberto = valor - pago - desconto;
        
        $('#baixa_id').val($(this).data('id'));
        
        // Preencher alert com informa??es
        $('#valor_original').text('R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#valor_em_aberto').text('R$ ' + emAberto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#valor_em_aberto').data('valor', emAberto);
        
        // N?O pr?-preencher valor_pago - deixar vazio para usu?rio digitar
        $('#valor_pago').val('');
        $('#desconto_pagamento').val('0');
        
        // Atualizar c?lculos iniciais
        atualizarCalculosPagar();
        
        carregarCategoriasDRE();
        $('#modalBaixa').modal('show');
        
        // Focar no campo ap?s modal abrir
        setTimeout(() => $('#valor_pago').focus(), 300);
    });

    // Ao visualizar detalhes
    $('.btn-visualizar').on('click', function () {
        const id = $(this).data('id');
        const fornecedor = $(this).data('fornecedor');
        const descricao = $(this).data('descricao');
        const valor = parseFloat($(this).data('valor')) || 0;
        const pago = parseFloat($(this).data('pago')) || 0;
        const desconto = parseFloat($(this).data('desconto')) || 0;
        const status = $(this).data('status');
        const emAberto = valor - pago - desconto;
        
        // Popular modal com informa??es b?sicas
        $('#detalheFornecedor').text(fornecedor);
        $('#detalheDescricao').text(descricao || '?');
        $('#detalheValor').text('R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#detalheDesconto').text('R$ ' + desconto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#detalhePago').text('R$ ' + pago.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        
        // Alterar r?tulo e cor se est? quitado
        if (emAberto === 0  || emAberto < 0.01) {
            $('#detalheEmAbertoLabel').text('Quitado');
            $('#detalheEmAberto').removeClass('text-danger').addClass('text-success');
        } else {
            $('#detalheEmAbertoLabel').text('Saldo em Aberto');
            $('#detalheEmAberto').removeClass('text-success').addClass('text-danger');
        }
        
        $('#detalheEmAberto').text('R$ ' + emAberto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#detalheStatus').text(status).removeClass().addClass('badge bg-' + (status === 'PAGO' ? 'success' : status === 'VENCIDO' ? 'danger' : 'warning'));
        
        // Mostrar modal
        $('#modalDetalhes').modal('show');
    });

    $('.btn-estornar').on('click', function () {
        const id = $(this).data('id');
        const valorPago = parseFloat($(this).data('pago')) || 0;
        const desconto = parseFloat($(this).data('desconto')) || 0;
        const totalPago = valorPago + desconto;
        
        $('#estorno_id').val(id);
        $('#estornoValorTotal').text('R$ ' + totalPago.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#valor_estorno').val('').attr('max', totalPago);
        $('#modalEstorno').modal('show');
    });

    // Processar estorno
    $('#formEstorno').on('submit', function (e) {
        e.preventDefault();
        const id = $('#estorno_id').val();
        const valorEstorno = $('#valor_estorno').val();
        
        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/contas-pagar.php',
            method: 'POST',
            data: { 
                action: 'estornar', 
                id: id,
                valor_estorno: valorEstorno || null
            },
            dataType: 'json',
            success: function (resp) {
                if (resp.success) {
                    $('#modalEstorno').modal('hide');
                    Swal.fire('Sucesso', resp.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erro', resp.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro', 'Erro ao processar estorno. Tente novamente.', 'error');
            }
        });
    });

    $('.btn-excluir').on('click', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Excluir conta a pagar?',
            text: 'Esta ação não pode ser desfeita.',
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-danger mx-1',
                cancelButton: 'btn btn-secondary mx-1'
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: '/sistema_dm/public/admin/financeiro/contas-pagar.php',
                method: 'POST',
                data: { action: 'excluir', id: id },
                dataType: 'json',
                success: function (resp) {
                    resp.success ? location.reload() : Swal.fire('Erro', resp.message, 'error');
                },
            });
        });
    });

    $('#formBaixa').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/contas-pagar.php',
            method: 'POST',
            data: $(this).serialize() + '&action=baixar',
            dataType: 'json',
            success: function (resp) {
                if (resp.success) {
                    $('#modalBaixa').modal('hide');
                    location.reload();
                } else {
                    Swal.fire('Erro', resp.message, 'error');
                }
            },
        });
    });

});
</script>
