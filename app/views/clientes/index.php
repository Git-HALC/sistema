<?php
$clienteBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_cliente_base_url', 'admin/clientes.php')
    : tenantUrl('admin/clientes.php');
$novoClienteUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($clienteBaseUrl, 'action=novo')
    : $clienteBaseUrl . '?action=novo';
?>
<div class="container-fluid">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">Clientes</h1>
        <a href="<?php echo htmlspecialchars($novoClienteUrl); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Novo Cliente
        </a>
    </div>

    <!-- Flash messages -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?php echo htmlspecialchars($clienteBaseUrl); ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>"
                           class="form-control form-control-sm" placeholder="Nome, CPF/CNPJ, e-mail…">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php 
                        $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                        foreach ($estados as $uf): 
                        ?>
                            <option value="<?php echo $uf; ?>" <?php echo (($_GET['estado'] ?? '') === $uf) ? 'selected' : ''; ?>>
                                <?php echo $uf; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" value="<?php echo htmlspecialchars($_GET['cidade'] ?? ''); ?>"
                           class="form-control form-control-sm" placeholder="Cidade">
                </div>

                <div class="col-6 col-md-5 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <?php if (!empty($_GET['busca']) || !empty($_GET['estado']) || !empty($_GET['cidade'])): ?>
                        <a href="<?php echo htmlspecialchars($clienteBaseUrl); ?>" class="btn btn-outline-danger btn-sm" title="Limpar filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Listagem -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">

            <?php if (empty($clientes)): ?>
                <div class="p-4 text-center text-muted">Nenhum cliente encontrado.</div>
            <?php else: ?>

                <!-- Tabela desktop -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF/CNPJ</th>
                                    <th>E-mail</th>
                                    <th>Telefone</th>
                                    <th>Cidade</th>
                                    <th>Estado</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                                        <td>
                                            <?php 
                                            if (!empty($cliente['cpf_cnpj'])) {
                                                $cpfCnpj = preg_replace('/\D/', '', $cliente['cpf_cnpj']);
                                                if (strlen($cpfCnpj) == 11) {
                                                    echo substr($cpfCnpj, 0, 3) . '.' . 
                                                         substr($cpfCnpj, 3, 3) . '.' . 
                                                         substr($cpfCnpj, 6, 3) . '-' . 
                                                         substr($cpfCnpj, 9, 2);
                                                } elseif (strlen($cpfCnpj) == 14) {
                                                    echo substr($cpfCnpj, 0, 2) . '.' . 
                                                         substr($cpfCnpj, 2, 3) . '.' . 
                                                         substr($cpfCnpj, 5, 3) . '/' . 
                                                         substr($cpfCnpj, 8, 4) . '-' . 
                                                         substr($cpfCnpj, 12, 2);
                                                } else {
                                                    echo htmlspecialchars($cliente['cpf_cnpj']);
                                                }
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cliente['email'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefone'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['cidade'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['estado'] ?? '—'); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?php echo htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($clienteBaseUrl, 'action=editar&id=' . urlencode((string)$cliente['id'])) : $clienteBaseUrl . '?action=editar&id=' . urlencode((string)$cliente['id'])); ?>"
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-excluir"
                                                        data-id="<?php echo $cliente['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cliente['nome']); ?>"
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

                <!-- Cards mobile -->
                <div class="d-md-none p-3">
                    <?php foreach ($clientes as $cliente): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1">
                                            <?php echo htmlspecialchars($cliente['nome']); ?>
                                        </h6>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3 small">
                                    <div class="col-6">
                                        <span class="text-muted d-block">E-mail</span>
                                        <strong><?php echo htmlspecialchars($cliente['email'] ?? '—'); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Telefone</span>
                                        <strong><?php echo htmlspecialchars($cliente['telefone'] ?? '—'); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Localização</span>
                                        <strong>
                                            <?php echo htmlspecialchars($cliente['cidade'] ?? '—'); ?>
                                            <?php if (!empty($cliente['estado'])): ?>
                                                - <?php echo htmlspecialchars($cliente['estado']); ?>
                                            <?php endif; ?>
                                        </strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">CPF/CNPJ</span>
                                        <strong>
                                            <?php 
                                            if (!empty($cliente['cpf_cnpj'])) {
                                                $cpfCnpj = preg_replace('/\D/', '', $cliente['cpf_cnpj']);
                                                if (strlen($cpfCnpj) == 11) {
                                                    echo substr($cpfCnpj, 0, 3) . '.' . 
                                                         substr($cpfCnpj, 3, 3) . '.' . 
                                                         substr($cpfCnpj, 6, 3) . '-' . 
                                                         substr($cpfCnpj, 9, 2);
                                                } elseif (strlen($cpfCnpj) == 14) {
                                                    echo substr($cpfCnpj, 0, 2) . '.' . 
                                                         substr($cpfCnpj, 2, 3) . '.' . 
                                                         substr($cpfCnpj, 5, 3) . '/' . 
                                                         substr($cpfCnpj, 8, 4) . '-' . 
                                                         substr($cpfCnpj, 12, 2);
                                                } else {
                                                    echo htmlspecialchars($cliente['cpf_cnpj']);
                                                }
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="<?php echo htmlspecialchars(function_exists('dmBuildUrl') ? dmBuildUrl($clienteBaseUrl, 'action=editar&id=' . urlencode((string)$cliente['id'])) : $clienteBaseUrl . '?action=editar&id=' . urlencode((string)$cliente['id'])); ?>"
                                       class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-excluir flex-fill"
                                            data-id="<?php echo $cliente['id']; ?>"
                                            data-nome="<?php echo htmlspecialchars($cliente['nome']); ?>">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação -->
                <?php if (isset($totalPaginas) && $totalPaginas > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted">
                            <?php echo $totalClientes ?? count($clientes); ?> cliente<?php echo (($totalClientes ?? count($clientes)) !== 1) ? 's' : ''; ?> encontrado<?php echo (($totalClientes ?? count($clientes)) !== 1) ? 's' : ''; ?>
                        </small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($paginaAtual > 1): ?>
                                    <li class="page-item">
                                        <?php
                                        $filtrosUrl = $_GET;
                                        unset($filtrosUrl['pagina']);
                                        $queryString = !empty($filtrosUrl) ? '&' . http_build_query($filtrosUrl) : '';
                                        ?>
                                        <a class="page-link" href="?pagina=<?php echo $paginaAtual - 1; ?><?php echo $queryString; ?>">‹</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                                        <?php
                                        $filtrosUrl = $_GET;
                                        unset($filtrosUrl['pagina']);
                                        $queryString = !empty($filtrosUrl) ? '&' . http_build_query($filtrosUrl) : '';
                                        ?>
                                        <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo $queryString; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($paginaAtual < $totalPaginas): ?>
                                    <li class="page-item">
                                        <?php
                                        $filtrosUrl = $_GET;
                                        unset($filtrosUrl['pagina']);
                                        $queryString = !empty($filtrosUrl) ? '&' . http_build_query($filtrosUrl) : '';
                                        ?>
                                        <a class="page-link" href="?pagina=<?php echo $paginaAtual + 1; ?><?php echo $queryString; ?>">›</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php else: ?>
                    <div class="card-footer py-2">
                        <small class="text-muted">
                            <?php echo $totalClientes ?? count($clientes); ?> cliente<?php echo (($totalClientes ?? count($clientes)) !== 1) ? 's' : ''; ?> encontrado<?php echo (($totalClientes ?? count($clientes)) !== 1) ? 's' : ''; ?>
                        </small>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar exclusão de clientes
    document.querySelectorAll('.btn-excluir').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const id = this.dataset.id;
            const nome = this.dataset.nome;
            
            // Verificar se SweetAlert2 está disponível
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Excluir cliente?',
                    text: `Tem certeza que deseja excluir o cliente "${nome}"?`,
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonText: 'Excluir',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444',
                    footer: '<i class="fas fa-exclamation-triangle text-warning"></i> Esta ação não pode ser desfeita!'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    
                    // Mostrar loading
                    Swal.fire({
                        title: 'Excluindo...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    
                    excluirCliente(id);
                });
            } else {
                // Fallback para confirm nativo
                if (confirm(`Tem certeza que deseja excluir o cliente "${nome}"?\n\nEsta ação não pode ser desfeita!`)) {
                    excluirCliente(id);
                }
            }
        });
    });
    
    function excluirCliente(id) {
        // Fazer requisição AJAX
        fetch(<?php echo json_encode($clienteBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: `action=excluir&id=${encodeURIComponent(id)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    alert(data.message);
                    location.reload();
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Erro', data.message, 'error');
                } else {
                    alert('Erro: ' + data.message);
                }
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Erro', 'Ocorreu um erro ao tentar excluir o cliente.', 'error');
            } else {
                alert('Ocorreu um erro ao tentar excluir o cliente.');
            }
        });
    }
});
</script>
