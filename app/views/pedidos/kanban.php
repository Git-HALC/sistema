<?php
/**
 * View Kanban - Gestão de Pedidos
 * 
 * Exibe pedidos em formato de quadro Kanban com 4 colunas:
 * - PENDENTE
 * - EM PROCESSO
 * - CONCLUÍDO
 * - FATURADO
 * 
 * Suporta drag-and-drop para alterar status dos pedidos.
 */

use App\Modules\Gestao_Pedidos\Pedido;

$pedidoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_pedido_base_url', 'admin/pedidos.php')
    : tenantUrl('admin/pedidos.php');
$pedidoNovoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($pedidoBaseUrl, 'action=novo')
    : $pedidoBaseUrl . '?action=novo';
$pedidoKanbanUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($pedidoBaseUrl, 'action=kanban')
    : $pedidoBaseUrl . '?action=kanban';
$pedidoStatusUpdateUrl = tenantUrl('admin/pedidos/update-status.php');
?>

<div class="container-fluid">
    <!-- Header com botões de ação -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-columns me-2"></i>Fluxo de Pedidos
        </h1>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($pedidoNovoUrl) ?>" 
               class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Novo Pedido
            </a>
        </div>
    </div>

    <!-- Mensagens Flash -->
    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?= htmlspecialchars($pedidoBaseUrl) ?>" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="kanban">

                <div class="col-12 col-md-4">
                    <label class="form-label small mb-1">Buscar por Cliente ou Número</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($filtros['busca'] ?? '') ?>"
                           class="form-control form-control-sm" placeholder="Nº pedido, nome do cliente...">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Entrega prevista de</label>
                    <input type="date" name="data_inicio"
                           value="<?= htmlspecialchars($filtros['data_inicio'] ?? '') ?>"
                           class="form-control form-control-sm">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Entrega prevista até</label>
                    <input type="date" name="data_fim"
                           value="<?= htmlspecialchars($filtros['data_fim'] ?? '') ?>"
                           class="form-control form-control-sm">
                </div>

                <div class="col-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="<?= htmlspecialchars($pedidoKanbanUrl) ?>" 
                       class="btn btn-outline-danger btn-sm" title="Limpar filtros">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contadores por Status -->
    <div class="row mb-3 g-2">
        <div class="col-6 col-md-3">
            <div class="card border-warning bg-warning bg-opacity-10">
                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Pendente</div>
                        <div class="fw-bold fs-5" id="count-pendente">0</div>
                    </div>
                    <i class="fas fa-clock fa-2x text-warning opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-info bg-info bg-opacity-10">
                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Em Processo</div>
                        <div class="fw-bold fs-5" id="count-em_processo">0</div>
                    </div>
                    <i class="fas fa-spinner fa-2x text-info opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-dark bg-dark bg-opacity-10">
                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Concluído</div>
                        <div class="fw-bold fs-5" id="count-concluido">0</div>
                    </div>
                    <i class="fas fa-check-circle fa-2x text-dark opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-primary bg-primary bg-opacity-10">
                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Faturado</div>
                        <div class="fw-bold fs-5" id="count-faturado">0</div>
                    </div>
                    <i class="fas fa-file-invoice-dollar fa-2x text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript do Kanban (DEFINIR FUNÇÕES ANTES DE USAR) -->
    <script>
    // ============================================================================
    // KANBAN - Gestão de Pedidos
    // ============================================================================

    /**
     * Criar card HTML para um pedido
     */
    const pedidoBaseUrl = <?= json_encode($pedidoBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const pedidoStatusUpdateUrl = <?= json_encode($pedidoStatusUpdateUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function pedidoActionUrl(query) {
        return pedidoBaseUrl + (pedidoBaseUrl.includes('?') ? '&' : '?') + query.replace(/^[?&]+/, '');
    }

    function createPedidoCard(pedido) {
        const card = document.createElement('div');
        card.className = 'kanban-card';
        card.draggable = true;
        card.dataset.pedidoId = pedido.id;
        card.dataset.status = pedido.status;

        // Status badge
        const statusLabels = {
            'RASCUNHO': 'Rascunho',
            'PENDENTE': 'Pendente',
            'EM_PROCESSO': 'Em Processo',
            'APROVADO': 'Aprovado',
            'FATURADO': 'Faturado',
            'CANCELADO': 'Cancelado',
            'CONCLUIDO': 'Concluído'
        };

        const statusBadges = {
            'RASCUNHO': 'bg-secondary',
            'PENDENTE': 'bg-warning',
            'EM_PROCESSO': 'bg-info',
            'APROVADO': 'bg-success',
            'FATURADO': 'bg-primary',
            'CANCELADO': 'bg-danger',
            'CONCLUIDO': 'bg-dark'
        };

        const statusLabel = statusLabels[pedido.status] || pedido.status;
        const statusBadge = statusBadges[pedido.status] || 'bg-secondary';

        // Formatar valor
        const valorFormatado = new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(pedido.valor_total || 0);

        // Formatar data
        let dataFormatada = '?';
        if (pedido.data_pedido) {
            try {
                const data = new Date(pedido.data_pedido);
                dataFormatada = data.toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            } catch (e) {
                dataFormatada = pedido.data_pedido;
            }
        }

        // Montar HTML do card
        card.innerHTML = `
            <div class="kanban-card-header">
                <div class="kanban-card-numero">
                    <i class="fas fa-hashtag"></i> ${pedido.numero || pedido.id}
                </div>
                <span class="badge ${statusBadge} kanban-card-status">${statusLabel}</span>
            </div>
            <div class="kanban-card-cliente">
                <i class="fas fa-user"></i>
                <span>${pedido.cliente_nome || '?'}</span>
            </div>
            <div class="kanban-card-info">
                <div class="kanban-card-info-item">
                    <span class="kanban-card-info-label">Valor</span>
                    <span class="kanban-card-info-value">${valorFormatado}</span>
                </div>
                <div class="kanban-card-info-item">
                    <span class="kanban-card-info-label">Data</span>
                    <span class="kanban-card-info-value">${dataFormatada}</span>
                </div>
            </div>
            <div class="kanban-card-actions">
                <a href="${pedidoActionUrl('action=show&id=' + encodeURIComponent(pedido.id))}" 
                   class="btn btn-sm btn-outline-secondary" title="Visualizar">
                    <i class="fas fa-eye"></i>
                </a>
                ${pedido.status !== 'FATURADO' && pedido.status !== 'CANCELADO' ? `
                    <a href="${pedidoActionUrl('action=editar&id=' + encodeURIComponent(pedido.id))}" 
                       class="btn btn-sm btn-outline-primary" title="Editar">
                        <i class="fas fa-edit"></i>
                    </a>
                ` : ''}
                ${pedido.status !== 'FATURADO' && pedido.status !== 'CANCELADO' ? `
                    <a href="${pedidoActionUrl('action=invoice&id=' + encodeURIComponent(pedido.id))}" 
                       class="btn btn-sm btn-outline-success" title="Faturar">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </a>
                ` : ''}
                ${pedido.status === 'FATURADO' ? `
                    <a href="${pedidoActionUrl('action=uninvoice&id=' + encodeURIComponent(pedido.id))}" 
                       class="btn btn-sm btn-outline-warning kanban-action-estorno" title="Estornar"
                       data-confirm-action="estorno-kanban">
                        <i class="fas fa-undo"></i>
                    </a>
                ` : ''}
                ${pedido.status !== 'CANCELADO' && pedido.status !== 'FATURADO' ? `
                    <a href="${pedidoActionUrl('action=cancel&id=' + encodeURIComponent(pedido.id))}" 
                       class="btn btn-sm btn-outline-danger kanban-action-cancelar" title="Cancelar"
                       data-confirm-action="cancelar-kanban">
                        <i class="fas fa-ban"></i>
                    </a>
                ` : ''}
            </div>
        `;

        // Eventos de Drag
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);

        return card;
    }

    /**
     * Atualizar contador de uma coluna
     */
    function updateColumnCount(status) {
        const column = document.getElementById(`column-${status}`);
        if (!column) return;

        const count = column.querySelectorAll('.kanban-card').length;
        
        // Atualizar badge na coluna
        const columnHeader = column.closest('.kanban-column').querySelector('.kanban-count');
        if (columnHeader) {
            columnHeader.textContent = count;
        }

        // Atualizar contador no topo
        const topCounter = document.getElementById(`count-${status.toLowerCase()}`);
        if (topCounter) {
            topCounter.textContent = count;
        }

        // Adicionar mensagem se vazio
        const emptyMsg = column.querySelector('.kanban-column-empty');
        if (count === 0 && !emptyMsg) {
            const empty = document.createElement('div');
            empty.className = 'kanban-column-empty';
            empty.innerHTML = '<i class="fas fa-inbox fa-2x mb-2"></i><br>Nenhum pedido';
            column.appendChild(empty);
        } else if (count > 0 && emptyMsg) {
            emptyMsg.remove();
        }
    }

    /**
     * Atualizar todos os contadores
     */
    function updateAllCounts() {
        ['PENDENTE', 'EM_PROCESSO', 'CONCLUIDO', 'FATURADO'].forEach(updateColumnCount);
    }

    // ============================================================================
    // DRAG AND DROP
    // ============================================================================

    let draggedElement = null;

    function handleDragStart(e) {
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        
        // Remover highlight de todas as colunas
        document.querySelectorAll('.kanban-column-body').forEach(col => {
            col.classList.remove('drag-over');
        });
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDragEnter(e) {
        this.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }

    async function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        this.classList.remove('drag-over');

        if (draggedElement && draggedElement !== this) {
            const novoStatus = this.dataset.status;
            const statusAtual = draggedElement.dataset.status;
            const pedidoId = draggedElement.dataset.pedidoId;

            // Evitar drop se já está na mesma coluna
            if (novoStatus === statusAtual) {
                return false;
            }

            // Mapear status de coluna para status real do BD
            const statusMap = {
                'PENDENTE': 'PENDENTE',
                'EM_PROCESSO': 'EM_PROCESSO',
                'CONCLUIDO': 'CONCLUIDO',
                'FATURADO': 'FATURADO'
            };

            const novoStatusBD = statusMap[novoStatus];

            // Validar se ? uma transição permitida (regras de negócio)
            if (statusAtual === 'FATURADO') {
                showToast('warning', 'Pedidos faturados não podem ser movidos. Use a ação "Estornar Faturamento".');
                return false;
            }

            if (statusAtual === 'CANCELADO') {
                showToast('warning', 'Pedidos cancelados não podem ser movidos.');
                return false;
            }

            if (novoStatusBD === 'FATURADO') {
                showToast('warning', 'Não é possível mover para FATURADO. Use a ação "Faturar Pedido".');
                return false;
            }

            // Atualizar via AJAX
            try {
                const response = await fetch(pedidoStatusUpdateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        pedido_id: pedidoId,
                        novo_status: novoStatusBD
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao atualizar status');
                }

                // Sucesso: mover card visualmente
                const colunaOrigem = draggedElement.closest('.kanban-column-body');
                this.appendChild(draggedElement);
                
                // Atualizar atributos do card
                draggedElement.dataset.status = novoStatusBD;
                
                // Atualizar badge visual
                const badge = draggedElement.querySelector('.kanban-card-status');
                const statusLabels = {
                    'PENDENTE': 'Pendente',
                    'EM_PROCESSO': 'Em Processo',
                    'CONCLUIDO': 'Concluído',
                    'FATURADO': 'Faturado'
                };
                const statusBadges = {
                    'PENDENTE': 'bg-warning',
                    'EM_PROCESSO': 'bg-info',
                    'CONCLUIDO': 'bg-dark',
                    'FATURADO': 'bg-primary'
                };
                if (badge) {
                    badge.className = `badge ${statusBadges[novoStatusBD]} kanban-card-status`;
                    badge.textContent = statusLabels[novoStatusBD];
                }

                // Atualizar contadores
                if (colunaOrigem) {
                    updateColumnCount(colunaOrigem.dataset.status);
                }
                updateColumnCount(novoStatus);

                // Feedback visual
                showToast('success', data.message || 'Status atualizado com sucesso!');

            } catch (error) {
                console.error('Erro ao atualizar status:', error);
                showToast('danger', 'Erro ao atualizar status: ' + error.message);
                return false;
            }
        }

        return false;
    }

    /**
     * Toast de feedback
     */
    function showToast(type, message) {
        // Se SweetAlert está disponível, usar como modal com estilo
        if (typeof Swal !== 'undefined') {
            const iconMap = {
                'success': 'success',
                'warning': 'warning',
                'danger': 'error',
                'info': 'info'
            };
            
            Swal.fire({
                title: type === 'success' ? 'Sucesso!' : (type === 'warning' ? 'Atenção' : 'Erro'),
                text: message,
                icon: iconMap[type] || 'info',
                confirmButtonText: 'OK',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary'
                },
                allowOutsideClick: false,
                timer: type === 'success' ? 3000 : undefined
            });
            return;
        }

        // Fallback para toast simples se SweetAlert não está disponível
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
            document.body.appendChild(container);
        }

        // Mapear tipos para classes Bootstrap
        let alertClass = 'danger';
        if (type === 'success') alertClass = 'success';
        else if (type === 'warning') alertClass = 'warning';
        else if (type === 'info') alertClass = 'info';
        else alertClass = 'danger';

        // Criar toast
        const toast = document.createElement('div');
        toast.className = `alert alert-${alertClass} alert-dismissible fade show`;
        toast.style.cssText = 'min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        container.appendChild(toast);

        // Auto-remover após 5 segundos
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 150);
        }, 5000);
    }

    /**
     * Configurar confirmações via SweetAlert para ações no Kanban
     */
    function setupConfirmationActions() {
        if (typeof Swal === 'undefined') return;

        // Interceptar cliques em botões de estorno e cancelamento
        document.addEventListener('click', function(e) {
            const botao = e.target.closest('[data-confirm-action]');
            if (!botao) return;

            const acao = botao.getAttribute('data-confirm-action');
            const href = botao.getAttribute('href');

            // Determinar tipo de ação
            let titulo = '';
            let texto = '';
            let icone = 'warning';
            let corBotao = 'btn-warning';

            if (acao === 'estorno-kanban') {
                titulo = 'Estornar faturamento?';
                texto = 'Essa ação remover? as contas a receber geradas por este pedido. Deseja continuar?';
                icone = 'warning';
                corBotao = 'btn-warning';
            } else if (acao === 'cancelar-kanban') {
                titulo = 'Confirmar cancelamento?';
                texto = 'Confirma cancelamento do pedido? O estoque será reajustado.';
                icone = 'warning';
                corBotao = 'btn-danger';
            } else {
                return;
            }

            e.preventDefault();

            Swal.fire({
                title: titulo,
                text: texto,
                icon: icone,
                showCancelButton: true,
                confirmButtonText: acao === 'estorno-kanban' ? 'Estornar faturamento' : 'Cancelar pedido',
                cancelButtonText: 'Voltar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: `btn ${corBotao} mx-1`,
                    cancelButton: 'btn btn-secondary mx-1'
                },
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        }, true); // Usar captura para garantir que intercepte antes
    }

    // ============================================================================
    // INICIALIZAÇÃO
    // ============================================================================
    
    // ========================================================================
    // DARK MODE: Sincronizar com tema global (já aplicado no header)
    // ========================================================================
    // O tema é aplicado no header.php via data-theme attribute
    // Aqui apenas garantimos que o CSS local funciona com o sistema global

    document.addEventListener('DOMContentLoaded', function() {
        // Renderizar cards dos pedidos nas colunas
        if (typeof pedidosData !== 'undefined' && Object.keys(pedidosData).length > 0) {
            for (const [colunaStatus, pedidosList] of Object.entries(pedidosData)) {
                if (pedidosList && pedidosList.length > 0) {
                    const column = document.getElementById(`column-${colunaStatus}`);
                    if (column) {
                        pedidosList.forEach(pedido => {
                            const card = createPedidoCard(pedido);
                            column.appendChild(card);
                        });
                    }
                }
            }
        }

        // Configurar eventos de drag-and-drop nas colunas
        const columns = document.querySelectorAll('.kanban-column-body');
        columns.forEach(column => {
            column.addEventListener('dragover', handleDragOver);
            column.addEventListener('dragenter', handleDragEnter);
            column.addEventListener('dragleave', handleDragLeave);
            column.addEventListener('drop', handleDrop);
        });

        // Atualizar contadores iniciais
        updateAllCounts();

        // Configurar confirmações para estorno e cancelamento via SweetAlert
        setupConfirmationActions();

        // ========================================================================
        // DARK MODE: Alternar entre tema claro e escuro
        // ========================================================================
        
        // ========================================================================
        // KANBAN DARK MODE TOGGLE: Sincronizar com tema global
        // ========================================================================
        const toggleButton = document.getElementById('toggleDarkMode');
        const toggleIcon = toggleButton.querySelector('i');
        const toggleText = toggleButton.querySelector('span');
        
        // Função para aplicar tema (sincronizado com header.php)
        function applyTheme(isDark) {
            // Atualiza atributo HTML (mesmo do header.php)
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            
            // Atualizar ícone e texto do botão
            if (isDark) {
                toggleIcon.classList.remove('fa-moon');
                toggleIcon.classList.add('fa-sun');
                if (toggleText) toggleText.textContent = 'Modo Claro';
                toggleButton.title = 'Alternar para modo claro';
            } else {
                toggleIcon.classList.remove('fa-sun');
                toggleIcon.classList.add('fa-moon');
                if (toggleText) toggleText.textContent = 'Modo Escuro';
                toggleButton.title = 'Alternar para modo escuro';
            }
        }
        
        // Carregar preferência salva (mesmo sistema do header: localStorage.theme)
        let isDarkMode = localStorage.getItem('theme') === 'dark';
        
        // Aplicar tema ao carregar a página
        applyTheme(isDarkMode);
        
        // Bot?o de toggle
        toggleButton.addEventListener('click', () => {
            isDarkMode = !isDarkMode;
            applyTheme(isDarkMode);
            // Salvar com a mesma chave do header.php
            localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
        });
        
        // Ouvir mudanças de tema do sistema
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (localStorage.getItem('theme') === null) {
                isDarkMode = e.matches;
                applyTheme(isDarkMode);
            }
        });

        console.log('? Kanban inicializado com sucesso');
    });
    </script>

    <!-- Board Kanban -->
    <div class="kanban-board" id="kanbanBoard">
        <!-- Coluna: PENDENTE -->
        <div class="kanban-column" data-status="PENDENTE">
            <div class="kanban-column-header bg-warning bg-opacity-10 border-warning">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Pendente
                    <span class="badge bg-warning ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-PENDENTE" data-status="PENDENTE">
                <!-- Cards serão injetados aqui via PHP -->
            </div>
        </div>

        <!-- Coluna: EM PROCESSO -->
        <div class="kanban-column" data-status="EM_PROCESSO">
            <div class="kanban-column-header bg-info bg-opacity-10 border-info">
                <h5 class="mb-0">
                    <i class="fas fa-spinner me-2"></i>Em Processo
                    <span class="badge bg-info ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-EM_PROCESSO" data-status="EM_PROCESSO">
                <!-- Cards serão injetados aqui via PHP -->
            </div>
        </div>

        <!-- Coluna: CONCLUÍDO -->
        <div class="kanban-column" data-status="CONCLUIDO">
            <div class="kanban-column-header bg-dark bg-opacity-10 border-dark">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>Concluído
                    <span class="badge bg-dark ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-CONCLUIDO" data-status="CONCLUIDO">
                <!-- Cards serão injetados aqui via PHP -->
            </div>
        </div>

        <!-- Coluna: FATURADO -->
        <div class="kanban-column" data-status="FATURADO">
            <div class="kanban-column-header bg-primary bg-opacity-10 border-primary">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Faturado
                    <span class="badge bg-primary ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-FATURADO" data-status="FATURADO">
                <!-- Cards serão injetados aqui via PHP -->
            </div>
        </div>
    </div>

    <!-- Renderizar Cards PHP -->
    <?php
    // Organizar pedidos por status
    $pedidosPorStatus = [
        'PENDENTE' => [],
        'EM_PROCESSO' => [],
        'CONCLUIDO' => [],
        'FATURADO' => [],
    ];

    // Incluir também RASCUNHO e APROVADO na coluna PENDENTE
    $mapStatusColuna = [
        'RASCUNHO' => 'PENDENTE',
        'PENDENTE' => 'PENDENTE',
        'EM_PROCESSO' => 'EM_PROCESSO',
        'APROVADO' => 'EM_PROCESSO',
        'CONCLUIDO' => 'CONCLUIDO',
        'FATURADO' => 'FATURADO',
        'CANCELADO' => null, // Não exibir cancelados no Kanban
    ];

    foreach (($pedidos ?? []) as $pedido) {
        $status = strtoupper((string)($pedido['status'] ?? 'PENDENTE'));
        $coluna = $mapStatusColuna[$status] ?? null;
        
        if ($coluna !== null) {
            $pedidosPorStatus[$coluna][] = $pedido;
        }
    }
    ?>

    <script>
    // Dados dos pedidos (carregados pelo PHP)
    const pedidosData = <?= json_encode($pedidosPorStatus, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>

    <!-- Mensagem se vazio -->
    <?php if (empty($pedidos)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p class="mb-0">Nenhum pedido encontrado.</p>
        </div>
    <?php endif; ?>
</div>

<!-- CSS do Kanban -->
<style>
/* ========================================================================== */
/* BOTÃO TOGGLE DARK MODE */
/* ========================================================================== */

#toggleDarkMode {
    transition: all 0.3s ease;
}

#toggleDarkMode:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#toggleDarkMode i {
    transition: transform 0.3s ease;
}

#toggleDarkMode:hover i {
    transform: rotate(15deg);
}

/* ========================================================================== */
/* MODO LIGHT - ESTILOS PADR?O */
/* ========================================================================== */

.kanban-board {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    min-height: 500px;
    overflow-x: auto;
    padding-bottom: 1rem;
}

@media (max-width: 1200px) {
    .kanban-board {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .kanban-board {
        grid-template-columns: 1fr;
    }
}

/* Coluna Kanban - Light Mode */
.kanban-column {
    display: flex;
    flex-direction: column;
    min-width: 280px;
    background: #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.kanban-column-header {
    padding: 0.75rem 1rem;
    border-bottom: 2px solid;
    position: sticky;
    top: 0;
    z-index: 10;
}

.kanban-column-header h5 {
    font-size: 0.95rem;
    color: #495057;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.kanban-column-body {
    flex: 1;
    padding: 0.75rem;
    overflow-y: auto;
    min-height: 400px;
    background: #f4f4f5;
}

/* Estados de Drag - Light Mode */
.kanban-column-body.drag-over {
    background: #f3f4f6;
    border: 2px dashed #6c757d;
    border-radius: 6px;
}

/* Card do Pedido - Light Mode */
.kanban-card {
    background: white;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 0.875rem;
    margin-bottom: 0.75rem;
    cursor: grab;
    transition: all 0.2s ease;
    border-left: 4px solid;
    color: #212529;
}

.kanban-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.kanban-card.dragging {
    opacity: 0.5;
    cursor: grabbing;
}

.kanban-card[data-status="RASCUNHO"],
.kanban-card[data-status="PENDENTE"] {
    border-left-color: #ffc107;
}

.kanban-card[data-status="EM_PROCESSO"],
.kanban-card[data-status="APROVADO"] {
    border-left-color: #0dcaf0;
}

.kanban-card[data-status="CONCLUIDO"] {
    border-left-color: #212529;
}

.kanban-card[data-status="FATURADO"] {
    border-left-color: #0d6efd;
}

/* Card Header - Light Mode */
.kanban-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 0.5rem;
}

.kanban-card-numero {
    font-weight: 600;
    color: #212529;
    font-size: 0.95rem;
}

.kanban-card-status {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
}

/* Card Body - Light Mode */
.kanban-card-cliente {
    color: #495057;
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.kanban-card-cliente i {
    color: #6c757d;
    font-size: 0.75rem;
}

.kanban-card-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
}

.kanban-card-info-item {
    font-size: 0.75rem;
}

.kanban-card-info-label {
    color: #6c757d;
    display: block;
    margin-bottom: 0.1rem;
}

.kanban-card-info-value {
    color: #212529;
    font-weight: 600;
}

/* Card Actions - Light Mode */
.kanban-card-actions {
    display: flex;
    gap: 0.25rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
}

.kanban-card-actions .btn {
    flex: 1;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* Empty State - Light Mode */
.kanban-column-empty {
    text-align: center;
    color: #adb5bd;
    padding: 2rem 1rem;
    font-size: 0.85rem;
}

/* Loading State - Light Mode */
.kanban-loading {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 9999;
    display: none;
    color: #212529;
}

.kanban-loading.active {
    display: block;
}

/* Contador de Badge */
.kanban-count {
    font-size: 0.75rem;
    min-width: 24px;
    text-align: center;
}

/* ========================================================================== */
/* MODO DARK - ESTILOS QUANDO TEMA ESCURO EST? ATIVO */
/* ========================================================================== */

/* Bot?o em Dark Mode */
html[data-theme="dark"] #toggleDarkMode {
    background-color: #495057;
    border-color: #6c757d;
    color: #f8f9fa;
}

html[data-theme="dark"] #toggleDarkMode:hover {
    background-color: #6c757d;
    border-color: #adb5bd;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

/* Coluna Kanban - Dark Mode */
html[data-theme="dark"] .kanban-column {
    background: #212529;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
}

html[data-theme="dark"] .kanban-column-header {
    background-color: rgba(33, 37, 41, 0.5);
}

html[data-theme="dark"] .kanban-column-header h5 {
    color: #f8f9fa;
}

html[data-theme="dark"] .kanban-column-body {
    background: #212529;
}

/* Estados de Drag - Dark Mode */
html[data-theme="dark"] .kanban-column-body.drag-over {
    background: #343a40;
    border-color: #6c757d;
}

/* Card do Pedido - Dark Mode */
html[data-theme="dark"] .kanban-card {
    background: #343a40;
    color: #f8f9fa;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

html[data-theme="dark"] .kanban-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
}

/* Card Header - Dark Mode */
html[data-theme="dark"] .kanban-card-numero {
    color: #f8f9fa;
}

html[data-theme="dark"] .kanban-card-cliente {
    color: #dee2e6;
}

html[data-theme="dark"] .kanban-card-cliente i {
    color: #adb5bd;
}

/* Card Info - Dark Mode */
html[data-theme="dark"] .kanban-card-info {
    border-top-color: #495057;
}

html[data-theme="dark"] .kanban-card-info-label {
    color: #adb5bd;
}

html[data-theme="dark"] .kanban-card-info-value {
    color: #f8f9fa;
}

/* Card Actions - Dark Mode */
html[data-theme="dark"] .kanban-card-actions {
    border-top-color: #495057;
}

/* Empty State - Dark Mode */
html[data-theme="dark"] .kanban-column-empty {
    color: #6c757d;
}

/* Loading State - Dark Mode */
html[data-theme="dark"] .kanban-loading {
    background: #343a40;
    color: #f8f9fa;
}
</style>
