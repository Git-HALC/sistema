<div class="container-fluid">

    <!-- CabeÃƒÂ§alho -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo $titulo; ?></h1>
        <div class="d-flex gap-2">
            <a href="/sistema_dm/public/admin/financeiro/contas-receber.php?action=exportar-pdf"
               class="btn btn-sm btn-danger" title="Exportar para PDF">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
            <a href="/sistema_dm/public/admin/financeiro/contas-receber.php?action=novo"
               class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i> Nova conta
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

    <!-- Filtros (formulÃƒÂ¡rio ÃƒÂºnico responsivo) -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="get" action="" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label">Cliente</label>
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
                    <label class="form-label">Vencimento ate</label>
                    <input type="date" class="form-control form-control-sm" name="data_fim"
                           value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>">
                </div>
                <div class="col-6 col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="/sistema_dm/public/admin/financeiro/contas-receber.php"
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

            <?php if (empty($contasReceber)): ?>
                <div class="p-4 text-center text-muted">Nenhuma conta a receber encontrada.</div>
            <?php else: ?>

                <!-- Tabela desktop -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Descrição</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-end">Recebido</th>
                                    <th class="text-end">Desconto</th>
                                    <th>Vencimento</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contasReceber as $conta):
                                    // CÃƒÂ¡lculo de status - uma vez por linha
                                    $dtVenc    = new DateTime($conta['data_vencimento']);
                                    $dtHoje    = new DateTime();
                                    $vencido   = $conta['status'] === 'PENDENTE' && $dtVenc < $dtHoje;
                                    $diasAtrs  = $vencido ? $dtHoje->diff($dtVenc)->days : 0;
                                    $stTexto   = $conta['status'];
                                    $stCor     = match($conta['status']) {
                                        'PENDENTE'  => $vencido ? 'danger' : 'warning',
                                        'VENCIDO'   => 'danger',
                                        'PAGO'      => 'success',
                                        'CANCELADO' => 'secondary',
                                        default     => 'secondary',
                                    };
                                    // CORRIGIDO: Verificar recebimento parcial
                                    $isParcial = ($conta['status'] === 'PENDENTE' && (float)($conta['valor_pago'] ?? 0) > 0);
                                    if ($isParcial) {
                                        $stTexto = 'Recebimento Parcial';
                                        $stCor = 'info';
                                    } elseif ($vencido) {
                                        if ($diasAtrs > 30)     { $stTexto = 'Vencido (Critico)'; $stCor = 'danger'; }
                                        elseif ($diasAtrs > 15) { $stTexto = 'Vencido (Urgente)'; $stCor = 'warning'; }
                                        else                    { $stTexto = 'Vencido';            $stCor = 'danger'; }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div><?php echo htmlspecialchars($conta['cliente_nome'] ?? 'N/A'); ?></div>
                                        <small class="text-muted">Conta #<?php echo (int) $conta['id']; ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($conta['descricao'] ?? ''); ?></div>
                                        <small class="text-muted">
                                            Forma PGTO da conta: <?php echo htmlspecialchars((string)($conta['forma_pagamento_nome'] ?? '-')); ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div>R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></div>
                                        <?php 
                                            $valorPago = (float)($conta['valor_pago'] ?? 0);
                                            $descontoTotal = (float)($conta['desconto'] ?? 0);
                                            $valorRecebidoLiquido = $valorPago;
                                            $emAberto = (float)$conta['valor'] - $valorPago - $descontoTotal;
                                        ?>
                                        <?php if ($emAberto > 0): ?>
                                            <small class="text-muted">Falta: R$ <?php echo number_format($emAberto, 2, ',', '.'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        R$ <?php echo number_format($valorRecebidoLiquido, 2, ',', '.'); ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($descontoTotal > 0): ?>
                                            <span class="badge bg-info">R$ <?php echo number_format($descontoTotal, 2, ',', '.'); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
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
                                                    data-numero="<?php echo (int) $conta['id']; ?>"
                                                    data-cliente="<?php echo htmlspecialchars($conta['cliente_nome'] ?? 'N/A'); ?>"
                                                    data-descricao="<?php echo htmlspecialchars($conta['descricao'] ?? ''); ?>"
                                                    data-forma-conta="<?php echo htmlspecialchars((string)($conta['forma_pagamento_nome'] ?? '')); ?>"
                                                    data-forma-recebimento="<?php echo htmlspecialchars((string)($conta['forma_pagamento_recebimento_nome'] ?? '')); ?>"
                                                    data-valor="<?php echo $conta['valor']; ?>"
                                                    data-recebido="<?php echo $valorRecebidoLiquido; ?>"
                                                    data-desconto="<?php echo $descontoTotal; ?>"
                                                    data-status="<?php echo $conta['status']; ?>"
                                                    title="Visualizar detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($conta['status'] !== 'PAGO' && $conta['status'] !== 'CANCELADO'): ?>
                                                <button type="button" class="btn btn-outline-success btn-baixar"
                                                        data-id="<?php echo $conta['id']; ?>"
                                                        data-valor="<?php echo $conta['valor'] - ($conta['valor_pago'] ?? 0); ?>"
                                                        title="Receber">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($conta['status'] === 'PAGO'): ?>
                                                <button type="button" class="btn btn-outline-warning btn-estornar"
                                                        data-id="<?php echo $conta['id']; ?>"
                                                        data-recebido="<?php echo $valorRecebidoLiquido; ?>"
                                                        data-desconto="<?php echo $descontoTotal; ?>"
                                                        title="Estornar">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($isParcial): ?>
                                                <button type="button" class="btn btn-outline-warning btn-estornar"
                                                        data-id="<?php echo $conta['id']; ?>"
                                                        data-recebido="<?php echo $valorRecebidoLiquido; ?>"
                                                        data-desconto="<?php echo $descontoTotal; ?>"
                                                        title="Estornar recebimento parcial">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php $ehProtegido = !empty($conta['protegido']); ?>
                                            <?php if ($ehProtegido): ?>
                                                <span class="btn btn-outline-secondary disabled" title="Registro protegido (origem PDV). Não pode ser excluído.">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                            <?php elseif ($conta['status'] === 'PENDENTE' || $conta['status'] === 'VENCIDO'): ?>
                                                <a href="/sistema_dm/public/admin/financeiro/contas-receber.php?action=editar&id=<?php echo $conta['id']; ?>"
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-excluir"
                                                        data-id="<?php echo $conta['id']; ?>"
                                                        data-origem="<?php echo htmlspecialchars((string)($conta['origem'] ?? '')); ?>"
                                                        data-orcamento-id="<?php echo (int)($conta['orcamento_id'] ?? 0); ?>"
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
                    <?php foreach ($contasReceber as $conta):
                        $dtVenc    = new DateTime($conta['data_vencimento']);
                        $dtHoje    = new DateTime();
                        $vencido   = $conta['status'] === 'PENDENTE' && $dtVenc < $dtHoje;
                        $diasAtrs  = $vencido ? $dtHoje->diff($dtVenc)->days : 0;
                        $stTexto   = $conta['status'];
                        $stCor     = match($conta['status']) {
                            'PENDENTE'  => $vencido ? 'danger' : 'warning',
                            'VENCIDO'   => 'danger',
                            'PAGO'      => 'success',
                            'CANCELADO' => 'secondary',
                            default     => 'secondary',
                        };
                        // CORRIGIDO: Verificar se ÃƒÂ© recebimento parcial
                        $isParcial = ($conta['status'] === 'PENDENTE' && (float)($conta['valor_pago'] ?? 0) > 0);
                        
                        // Calcular valores para mobile
                        $valorPagoMobile = (float)($conta['valor_pago'] ?? 0);
                        $descontoTotalMobile = (float)($conta['desconto'] ?? 0);
                        $valorRecebidoLiquidoMobile = $valorPagoMobile;
                        
                        if ($isParcial) {
                            $stTexto = 'Recebimento Parcial';
                            $stCor = 'info';
                        } elseif ($vencido) {
                            if ($diasAtrs > 30)     { $stTexto = 'Vencido (Critico)'; $stCor = 'danger'; }
                            elseif ($diasAtrs > 15) { $stTexto = 'Vencido (Urgente)'; $stCor = 'warning'; }
                            else                    { $stTexto = 'Vencido';            $stCor = 'danger'; }
                        }
                    ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1">
                                            <?php echo htmlspecialchars($conta['cliente_nome'] ?? ($conta['cliente_empresa'] ?? 'N/A')); ?>
                                        </h6>
                                        <div class="small text-muted">Conta #<?php echo (int) $conta['id']; ?></div>
                                        <?php if ($conta['descricao']): ?>
                                            <p class="card-text small text-secondary mt-1 mb-0"><?php echo htmlspecialchars($conta['descricao']); ?></p>
                                        <?php endif; ?>
                                        <div class="small text-muted mt-1">
                                            Forma da conta: <?php echo htmlspecialchars((string)($conta['forma_pagamento_nome'] ?? '-')); ?>
                                        </div>
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
                                        <span class="text-muted d-block">Recebido</span>
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
                                        <span class="text-muted d-block">Recebimento</span>
                                        <strong><?php echo $conta['data_pagamento'] ? date('d/m/Y', strtotime($conta['data_pagamento'])) : '-'; ?></strong>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if ($conta['status'] !== 'PAGO' && $conta['status'] !== 'CANCELADO'): ?>
                                        <button type="button" class="btn btn-outline-success btn-sm btn-baixar flex-fill"
                                                data-id="<?php echo $conta['id']; ?>"
                                                data-valor="<?php echo $conta['valor'] - ($conta['valor_pago'] ?? 0); ?>">
                                            <i class="fas fa-check"></i> Receber
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($conta['status'] === 'PAGO'): ?>
                                        <button type="button" class="btn btn-outline-warning btn-sm btn-estornar flex-fill"
                                                data-id="<?php echo $conta['id']; ?>"
                                                data-recebido="<?php echo $valorRecebidoLiquidoMobile; ?>"
                                                data-desconto="<?php echo $descontoTotalMobile; ?>">
                                            <i class="fas fa-undo"></i> Estornar
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($isParcial): ?>
                                        <button type="button" class="btn btn-outline-warning btn-sm btn-estornar flex-fill"
                                                data-id="<?php echo $conta['id']; ?>"
                                                data-recebido="<?php echo $valorRecebidoLiquidoMobile; ?>"
                                                data-desconto="<?php echo $descontoTotalMobile; ?>">
                                            <i class="fas fa-undo"></i> Estornar
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($conta['status'] === 'PENDENTE' || $conta['status'] === 'VENCIDO'): ?>
                                        <button type="button" class="btn btn-outline-info btn-sm btn-visualizar flex-fill"
                                                data-id="<?php echo $conta['id']; ?>"
                                                data-numero="<?php echo (int) $conta['id']; ?>"
                                                data-cliente="<?php echo htmlspecialchars($conta['cliente_nome'] ?? 'N/A'); ?>"
                                                data-descricao="<?php echo htmlspecialchars($conta['descricao'] ?? ''); ?>"
                                                data-forma-conta="<?php echo htmlspecialchars((string)($conta['forma_pagamento_nome'] ?? '')); ?>"
                                                data-forma-recebimento="<?php echo htmlspecialchars((string)($conta['forma_pagamento_recebimento_nome'] ?? '')); ?>"
                                                data-valor="<?php echo $conta['valor']; ?>"
                                                data-recebido="<?php echo $valorRecebidoLiquidoMobile; ?>"
                                                data-desconto="<?php echo $descontoTotalMobile; ?>"
                                                data-status="<?php echo $conta['status']; ?>">
                                            <i class="fas fa-eye"></i> Detalhes
                                        </button>
                                        <a href="/sistema_dm/public/admin/financeiro/contas-receber.php?action=editar&id=<?php echo $conta['id']; ?>"
                                           class="btn btn-outline-primary btn-sm flex-fill">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <?php if (!empty($conta['protegido'])): ?>
                                            <span class="btn btn-outline-secondary btn-sm flex-fill disabled" title="Protegido">
                                                <i class="fas fa-lock"></i> Protegido
                                            </span>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-excluir flex-fill"
                                                    data-id="<?php echo $conta['id']; ?>"
                                                    data-origem="<?php echo htmlspecialchars((string)($conta['origem'] ?? '')); ?>"
                                                    data-orcamento-id="<?php echo (int)($conta['orcamento_id'] ?? 0); ?>">
                                                <i class="fas fa-trash"></i> Excluir
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginacao -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted"><?php echo $total; ?> titulo(s)</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">&laquo;</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($pagina < $totalPaginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">&raquo;</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php else: ?>
                    <div class="card-footer py-2">
                        <small class="text-muted"><?php echo $total; ?> titulo(s)</small>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Baixa/Recebimento (CORRIGIDO) -->
<div class="modal fade" id="modalBaixa" tabindex="-1" aria-labelledby="modalBaixaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBaixaLabel">Receber Conta</h5>
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

                    <!-- CÃƒÂ¡lculo de valores -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valor_recebido" class="form-label">Valor Recebido (dinheiro) *</label>
                            <input type="number" step="0.01" class="form-control" id="valor_recebido" 
                                   name="valor_recebido" placeholder="Ex: 900" required>
                            <small class="text-muted d-block mt-1">Quanto voce recebeu em dinheiro</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="desconto_recebimento" class="form-label">Desconto Concedido</label>
                            <input type="number" step="0.01" class="form-control" id="desconto_recebimento" 
                                   name="desconto_recebimento" value="0" min="0">
                            <small class="text-muted d-block mt-1">Desconto + valor recebido devem quitar a conta</small>
                            <small class="text-danger d-none mt-1">Desconto so permitido quando o total acumulado quita completamente</small>
                        </div>
                    </div>

                    <!-- CÃƒÂ¡lculo automÃƒÂ¡tico -->
                    <div class="card bg-light mb-3">
                        <div class="card-body py-2">
                            <small>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Valor recebido:</span>
                                    <strong id="calc_valor_recebido">R$ 0,00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Menos desconto:</span>
                                    <strong id="calc_desconto">R$ 0,00</strong>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <span>Valor creditado:</span>
                                    <strong id="calc_total">R$ 0,00</strong>
                                </div>
                                <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                                    <span>Status apos recebimento:</span>
                                    <strong id="calc_status" class="badge bg-warning">-</strong>
                                </div>
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="data_recebimento" class="form-label">Data Recebimento *</label>
                        <input type="date" class="form-control" id="data_recebimento" name="data_recebimento"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="forma_pagamento_id" class="form-label">Forma de Pagamento *</label>
                        <select class="form-select" id="forma_pagamento_id" name="forma_pagamento_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($formasPagamento as $formaPagamento): ?>
                                <option
                                    value="<?php echo (int)$formaPagamento['id']; ?>"
                                    data-tipo="<?php echo htmlspecialchars((string)($formaPagamento['tipo'] ?? '')); ?>"
                                    data-taxa="<?php echo htmlspecialchars((string)($formaPagamento['taxa'] ?? '0')); ?>"
                                    data-prazo="<?php echo (int)($formaPagamento['prazo_dias'] ?? 0); ?>"
                                    data-conta="<?php echo htmlspecialchars((string)($formaPagamento['conta_nome'] ?? '')); ?>"
                                    data-adquirente="<?php echo htmlspecialchars((string)($formaPagamento['adquirente_nome'] ?? '')); ?>"
                                >
                                    <?php echo htmlspecialchars((string)$formaPagamento['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" id="forma_pagamento_hint">Selecione a forma para validar banco, taxa e adquirente.</small>
                    </div>
                    <div class="mb-3">
                        <label for="categoria_dre_id" class="form-label">Categoria DRE (Receita) *</label>
                        <select class="form-select" id="categoria_dre_id" name="categoria_dre_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categoriasDreReceita as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Receber</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Detalhes da Conta -->
<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-labelledby="modalDetalhesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalhesLabel">Detalhes da Conta a Receber</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Informacoes gerais -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted">Cliente</label>
                        <p class="fs-6 fw-semibold" id="detalheCliente">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted">Status</label>
                        <p id="detalheStatus" class="badge bg-secondary">-</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label text-muted">Descrição</label>
                        <p class="fs-6" id="detalheDescricao">-</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label text-muted">Conta</label>
                        <p class="fs-6 fw-semibold" id="detalheNumero">-</p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted">Forma PGTO. da conta</label>
                        <p class="fs-6" id="detalheFormaConta">-</p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted">Forma do recebimento</label>
                        <p class="fs-6" id="detalheFormaRecebimento">-</p>
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
                                <small class="text-muted d-block">Recebido Liquido</small>
                                <strong id="detalheRecebido">R$ 0,00</strong>
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

<!-- Modal de Estorno -->
<div class="modal fade" id="modalEstorno" tabindex="-1" aria-labelledby="modalEstornoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalEstornoLabel">Estornar Recebimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEstorno">
                <div class="modal-body">
                    <input type="hidden" id="estorno_id" name="id">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Valor total recebido:</strong> <span id="estornoValorTotal">R$ 0,00</span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor_estorno" class="form-label">Valor a Estornar</label>
                        <input type="number" step="0.01" class="form-control" id="valor_estorno" name="valor_estorno" placeholder="Ex: 50.00">
                        <small class="text-muted d-block mt-1">Deixe em branco ou informe o valor total para estornar tudo</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        O estorno revertera o valor da <strong>ultima movimentacao</strong> registrada.
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
    function atualizarResumoFormaPagamento() {
        const option = $('#forma_pagamento_id option:selected');
        const valorRecebido = parseFloat($('#valor_recebido').val()) || 0;

        if (!option.length || !option.val()) {
            $('#forma_pagamento_hint').text('Selecione a forma para validar banco, taxa e adquirente.');
            return;
        }

        const tipo = String(option.data('tipo') || '').toUpperCase();
        const taxa = parseFloat(option.data('taxa') || 0);
        const prazo = parseInt(option.data('prazo') || 0, 10);
        const conta = String(option.data('conta') || '');
        const adquirente = String(option.data('adquirente') || '');
        let mensagem = '';

        if (tipo === 'CC' || tipo === 'CD') {
            const taxaValor = valorRecebido > 0 ? (valorRecebido * taxa / 100) : 0;
            const liquido = valorRecebido - taxaValor;
            mensagem = 'Cartao';
            mensagem += adquirente ? ' | adquirente: ' + adquirente : ' | adquirente nao cadastrada';
            mensagem += ' | prazo: ' + prazo + ' dia(s)';
            mensagem += conta ? ' | banco: ' + conta : ' | banco nao cadastrado';
            if (taxa > 0) {
                mensagem += ' | taxa: ' + taxa.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + '%';
                if (valorRecebido > 0) {
                    mensagem += ' | liquido estimado: R$ ' + liquido.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                }
            }
        } else if (tipo === 'D') {
            mensagem = conta ? 'Dinheiro com entrada no banco: ' + conta : 'Dinheiro sem banco cadastrado.';
        } else if (tipo === 'PIX' || tipo === 'TB' || tipo === 'BOL') {
            mensagem = conta ? 'Banco: ' + conta : 'Banco nao cadastrado';
            if (taxa > 0) {
                mensagem += ' | taxa: ' + taxa.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + '%';
            }
        } else {
            mensagem = 'Use a forma efetiva do recebimento para registrar a baixa.';
        }

        $('#forma_pagamento_hint').text(mensagem);
    }

    function atualizarCalculos() {
        const valorRecebido = parseFloat($('#valor_recebido').val()) || 0;
        const desconto = parseFloat($('#desconto_recebimento').val()) || 0;
        const valorEmAberto = parseFloat($('#valor_em_aberto').data('valor')) || 0;
        const valorCreditado = valorRecebido + desconto;

        let status = '-';
        let statusBg = 'secondary';
        let validacaoDesconto = '';

        if (valorRecebido === 0) {
            status = '-';
            statusBg = 'secondary';
        } else if (valorCreditado >= valorEmAberto) {
            status = 'TOTALMENTE QUITADO';
            statusBg = 'success';
        } else {
            status = 'RECEBIMENTO PARCIAL';
            statusBg = 'warning';
            if (desconto > 0) {
                validacaoDesconto = ' desconto nao permitido em parciais';
            }
        }

        $('#calc_valor_recebido').text('R$ ' + valorRecebido.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#calc_desconto').text('R$ ' + desconto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#calc_total').text('R$ ' + valorCreditado.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#calc_status').text(status + validacaoDesconto).removeClass().addClass('badge bg-' + statusBg);
        atualizarResumoFormaPagamento();
    }

    $('#valor_recebido, #desconto_recebimento').on('input', atualizarCalculos);
    $('#forma_pagamento_id').on('change', atualizarResumoFormaPagamento);

    $('.btn-baixar').on('click', function () {
        const id = $(this).data('id');
        const valorEmAberto = parseFloat($(this).data('valor')) || 0;

        $('#baixa_id').val(id);
        $('#valor_recebido').val('').focus();
        $('#desconto_recebimento').val('0');
        $('#forma_pagamento_id').val('');
        $('#valor_original').text('R$ ' + valorEmAberto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#valor_em_aberto').text('R$ ' + valorEmAberto.toLocaleString('pt-BR', {minimumFractionDigits: 2})).data('valor', valorEmAberto);
        atualizarCalculos();
        $('#modalBaixa').modal('show');
    });

    $('.btn-estornar').on('click', function () {
        const id = $(this).data('id');
        const valorRecebido = parseFloat($(this).data('recebido')) || 0;
        const desconto = parseFloat($(this).data('desconto')) || 0;
        const totalRecebido = valorRecebido + desconto;

        $('#estorno_id').val(id);
        $('#estornoValorTotal').text('R$ ' + totalRecebido.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#valor_estorno').val('').attr('max', totalRecebido);
        $('#modalEstorno').modal('show');
    });

    $('#formEstorno').on('submit', function (e) {
        e.preventDefault();
        const id = $('#estorno_id').val();
        const valorEstorno = $('#valor_estorno').val();

        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/contas-receber.php',
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
        const origem = String($(this).data('origem') || '').toUpperCase();
        const orcamentoId = parseInt($(this).data('orcamentoId') || 0, 10);

        if (origem === 'PEDIDO') {
            Swal.fire({
                title: 'Conta vinculada ao pedido',
                text: 'Esta conta foi gerada pelo faturamento de um pedido. Faca o estorno no modulo de Pedidos.',
                icon: 'warning',
                confirmButtonText: 'Entendi',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-warning mx-1' }
            });
            return;
        }

        if (origem === 'SERVICO') {
            Swal.fire({
                title: 'Conta vinculada ao serviço',
                text: 'Esta conta foi gerada pelo faturamento de um serviço. Faca o estorno no modulo de Serviços.',
                icon: 'warning',
                confirmButtonText: 'Entendi',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-warning mx-1' }
            });
            return;
        }

        if (origem === 'ADQUIRENTE') {
            Swal.fire({
                title: 'Conta vinculada a adquirente',
                text: 'Esta conta foi gerada automaticamente como repasse da adquirente do cartao. Estorne o recebimento da conta original para remover este vinculo.',
                icon: 'warning',
                confirmButtonText: 'Entendi',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-warning mx-1' }
            });
            return;
        }

        if (origem === 'ORCAMENTO' || orcamentoId > 0) {
            Swal.fire({
                title: 'Conta vinculada ao orcamento',
                text: orcamentoId > 0
                    ? `Esta conta esta vinculada ao orcamento #${orcamentoId}. Faca o estorno no modulo de Orcamentos.`
                    : 'Esta conta foi gerada por orcamento. Faca o estorno no modulo de Orcamentos.',
                icon: 'warning',
                confirmButtonText: 'Entendi',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-warning mx-1' }
            });
            return;
        }

        Swal.fire({
            title: 'Excluir conta a receber?',
            text: 'Esta acao nao pode ser desfeita.',
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
                url: '/sistema_dm/public/admin/financeiro/contas-receber.php',
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

        const valorRecebido = parseFloat($('#valor_recebido').val()) || 0;
        const desconto = parseFloat($('#desconto_recebimento').val()) || 0;
        const valorEmAberto = parseFloat($('#valor_em_aberto').data('valor')) || 0;
        const valorCreditado = valorRecebido + desconto;

        if (valorRecebido <= 0) {
            Swal.fire('Erro', 'Valor recebido deve ser maior que zero.', 'error');
            return;
        }

        if (desconto > 0 && valorCreditado < valorEmAberto) {
            Swal.fire('Erro', 'Desconto so permitido quando o total acumulado quita completamente a conta.', 'error');
            return;
        }

        if (!$('#forma_pagamento_id').val()) {
            Swal.fire('Erro', 'Selecione a forma de pagamento.', 'error');
            return;
        }

        $.ajax({
            url: '/sistema_dm/public/admin/financeiro/contas-receber.php',
            method: 'POST',
            data: $(this).serialize() + '&action=baixar',
            dataType: 'json',
            success: function (resp) {
                if (resp.success) {
                    $('#modalBaixa').modal('hide');
                    location.reload();
                } else {
                    if (resp.requires_payment_config && resp.redirect_url) {
                        Swal.fire({
                            title: 'Configuracao pendente',
                            text: resp.message + ' Deseja ir para o cadastro agora?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Ir para cadastro',
                            cancelButtonText: 'Fechar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = resp.redirect_url;
                            }
                        });
                        return;
                    }

                    Swal.fire('Erro', resp.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Erro', 'Erro ao processar recebimento. Tente novamente.', 'error');
            }
        });
    });

    $('.btn-visualizar').on('click', function () {
        const numero = $(this).data('numero');
        const cliente = $(this).data('cliente');
        const descricao = $(this).data('descricao');
        const formaConta = $(this).data('formaConta');
        const formaRecebimento = $(this).data('formaRecebimento');
        const valor = parseFloat($(this).data('valor')) || 0;
        const recebido = parseFloat($(this).data('recebido')) || 0;
        const desconto = parseFloat($(this).data('desconto')) || 0;
        const status = $(this).data('status');
        const emAberto = valor - recebido - desconto;

        $('#detalheNumero').text(numero ? ('#' + numero) : '-');
        $('#detalheCliente').text(cliente);
        $('#detalheCliente').text(cliente || '-');
        $('#detalheDescricao').text(descricao || '-');
        $('#detalheFormaConta').text(formaConta || '-');
        $('#detalheFormaRecebimento').text(formaRecebimento || '-');
        $('#detalheDescricao').text(descricao || '-');
        $('#detalheDescricao').text(descricao || 'â€”');
        $('#detalheDescricao').text(descricao || '-');
        $('#detalheValor').text('R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#detalheDesconto').text('R$ ' + desconto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#detalheRecebido').text('R$ ' + recebido.toLocaleString('pt-BR', {minimumFractionDigits: 2}));

        if (emAberto === 0) {
            $('#detalheEmAbertoLabel').text('Quitado');
            $('#detalheEmAberto').removeClass('text-danger').addClass('text-success');
        } else {
            $('#detalheEmAbertoLabel').text('Saldo em Aberto');
            $('#detalheEmAberto').removeClass('text-success').addClass('text-danger');
        }

        $('#detalheEmAberto').text('R$ ' + emAberto.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#detalheStatus').text(status).removeClass().addClass('badge bg-' + (status === 'PAGO' ? 'success' : status === 'VENCIDO' ? 'danger' : 'warning'));
        $('#modalDetalhes').modal('show');
    });
});
</script>
