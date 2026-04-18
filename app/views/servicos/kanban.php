<?php
use App\Modules\Servicos\Servico;

$servicoBaseUrl = function_exists('dmContextUrl')
    ? dmContextUrl('__dm_servico_base_url', 'admin/servicos.php')
    : tenantUrl('admin/servicos.php');
$servicoCatalogoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=catalogo')
    : $servicoBaseUrl . '?action=catalogo';
$servicoNovoUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=novo')
    : $servicoBaseUrl . '?action=novo';
$servicoKanbanUrl = function_exists('dmBuildUrl')
    ? dmBuildUrl($servicoBaseUrl, 'action=kanban')
    : $servicoBaseUrl . '?action=kanban';
$servicoStatusUpdateUrl = tenantUrl('admin/servicos/update-status.php');

$servicosPorStatus = [
    Servico::STATUS_PENDENTE => [],
    Servico::STATUS_EM_PROCESSO => [],
    Servico::STATUS_CONCLUIDO => [],
    Servico::STATUS_FATURADO => [],
];

foreach (($servicos ?? []) as $servico) {
    $status = strtoupper((string)($servico['status'] ?? Servico::STATUS_PENDENTE));
    if (!isset($servicosPorStatus[$status])) {
        $status = Servico::STATUS_PENDENTE;
    }
    $servicosPorStatus[$status][] = $servico;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-columns me-2"></i>Fluxo de Servicos
        </h1>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($servicoCatalogoUrl) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-wrench me-1"></i> Cadastrar servicos
            </a>
            <a href="<?= htmlspecialchars($servicoNovoUrl) ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Iniciar novo servico
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?= $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['mensagem']['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?= htmlspecialchars($servicoBaseUrl) ?>" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="kanban">

                <div class="col-12 col-md-4">
                    <label class="form-label small mb-1">Buscar por cliente, servico ou placa</label>
                    <input type="text"
                           name="busca"
                           value="<?= htmlspecialchars((string)($filtros['busca'] ?? '')) ?>"
                           class="form-control form-control-sm"
                           placeholder="Nome do cliente, servico ou placa...">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Data inicial</label>
                    <input type="date"
                           name="data_inicio"
                           value="<?= htmlspecialchars((string)($filtros['data_inicio'] ?? '')) ?>"
                           class="form-control form-control-sm">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Data final</label>
                    <input type="date"
                           name="data_fim"
                           value="<?= htmlspecialchars((string)($filtros['data_fim'] ?? '')) ?>"
                           class="form-control form-control-sm">
                </div>

                <div class="col-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="<?= htmlspecialchars($servicoKanbanUrl) ?>"
                       class="btn btn-outline-danger btn-sm"
                       title="Limpar filtros">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

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
                        <div class="text-muted small">Concluido</div>
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

<script>
    const servicoBaseUrl = <?= json_encode($servicoBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const servicoStatusUpdateUrl = <?= json_encode($servicoStatusUpdateUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function servicoActionUrl(query) {
        return servicoBaseUrl + (servicoBaseUrl.includes('?') ? '&' : '?') + query.replace(/^[?&]+/, '');
    }

    function createServicoCard(servico) {
        const card = document.createElement('div');
        card.className = 'kanban-card';
        card.draggable = servico.status !== 'CANCELADO' && servico.status !== 'FATURADO';
        card.dataset.servicoId = servico.id;
        card.dataset.status = servico.status;

        const statusLabels = {
            'PENDENTE': 'Pendente',
            'EM_PROCESSO': 'Em Processo',
            'CONCLUIDO': 'Concluido',
            'FATURADO': 'Faturado',
            'CANCELADO': 'Cancelado'
        };

        const statusBadges = {
            'PENDENTE': 'bg-warning',
            'EM_PROCESSO': 'bg-info',
            'CONCLUIDO': 'bg-dark',
            'FATURADO': 'bg-primary',
            'CANCELADO': 'bg-danger'
        };

        const valorFormatado = new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(servico.valor_total || 0);

        let dataFormatada = '-';
        if (servico.data_servico) {
            try {
                dataFormatada = new Date(servico.data_servico).toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            } catch (error) {
                dataFormatada = servico.data_servico;
            }
        }

        const produtoNome = servico.produto_nome ? `
            <div class="kanban-card-produto">
                <i class="fas fa-box me-1"></i>${servico.produto_nome}
                ${(parseFloat(servico.produto_quantidade || 0) > 0) ? `<span class="kanban-produto-qtd">Qtd: ${Number(servico.produto_quantidade).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 4 })}</span>` : ''}
            </div>
        ` : '';

        const veiculo = [servico.placa || '', servico.modelo_veiculo || '']
            .filter(Boolean)
            .join(' - ');

        card.innerHTML = `
            <div class="kanban-card-header">
                <div class="kanban-card-numero">
                    <i class="fas fa-hashtag"></i> ${servico.numero || servico.id}
                </div>
                <span class="badge ${statusBadges[servico.status] || 'bg-secondary'} kanban-card-status">${statusLabels[servico.status] || servico.status}</span>
            </div>
            <div class="kanban-card-cliente">
                <i class="fas fa-user"></i>
                <span>${servico.nome_cliente || '-'}</span>
            </div>
            <div class="kanban-card-servico">
                <i class="fas fa-tools me-1"></i>${servico.servico_nome || '-'}
            </div>
            ${produtoNome}
            <div class="kanban-card-info mt-2">
                <div class="kanban-card-info-item">
                    <span class="kanban-card-info-label">Veiculo</span>
                    <span class="kanban-card-info-value">${veiculo || '-'}</span>
                </div>
                <div class="kanban-card-info-item">
                    <span class="kanban-card-info-label">Data</span>
                    <span class="kanban-card-info-value">${dataFormatada}</span>
                </div>
                <div class="kanban-card-info-item">
                    <span class="kanban-card-info-label">Valor</span>
                    <span class="kanban-card-info-value">${valorFormatado}</span>
                </div>
            </div>
            <div class="kanban-card-actions">
                <a href="${servicoActionUrl('action=visualizar&id=' + encodeURIComponent(servico.id))}"
                   class="btn btn-sm btn-outline-secondary"
                   title="Visualizar">
                    <i class="fas fa-eye"></i>
                </a>
                ${servico.status !== 'FATURADO' && servico.status !== 'CANCELADO' ? `
                    <a href="${servicoActionUrl('action=editar&id=' + encodeURIComponent(servico.id))}"
                       class="btn btn-sm btn-outline-primary"
                       title="Editar">
                        <i class="fas fa-edit"></i>
                    </a>
                ` : ''}
                ${servico.status !== 'CANCELADO' && servico.status !== 'FATURADO' ? `
                    <a href="${servicoActionUrl('action=faturar&id=' + encodeURIComponent(servico.id))}"
                       class="btn btn-sm btn-outline-success"
                       title="Faturar">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </a>
                ` : ''}
                ${servico.status === 'FATURADO' ? `
                    <a href="${servicoActionUrl('action=estornar&id=' + encodeURIComponent(servico.id))}"
                       class="btn btn-sm btn-outline-warning kanban-action-estorno"
                       title="Estornar"
                       data-confirm-action="estorno-servico">
                        <i class="fas fa-undo"></i>
                    </a>
                ` : ''}
                ${servico.status !== 'CANCELADO' && servico.status !== 'FATURADO' ? `
                    <a href="${servicoActionUrl('action=cancelar&id=' + encodeURIComponent(servico.id))}"
                       class="btn btn-sm btn-outline-danger kanban-action-cancelar"
                       title="Cancelar"
                       data-confirm-action="cancelar-servico">
                        <i class="fas fa-ban"></i>
                    </a>
                ` : ''}
            </div>
        `;

        if (card.draggable) {
            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragend', handleDragEnd);
        }

        return card;
    }

    function updateColumnCount(status) {
        const column = document.getElementById(`column-${status}`);
        if (!column) {
            return;
        }

        const count = column.querySelectorAll('.kanban-card').length;
        const columnHeader = column.closest('.kanban-column').querySelector('.kanban-count');
        if (columnHeader) {
            columnHeader.textContent = count;
        }

        const topCounter = document.getElementById(`count-${status.toLowerCase()}`);
        if (topCounter) {
            topCounter.textContent = count;
        }

        const emptyMsg = column.querySelector('.kanban-column-empty');
        if (count === 0 && !emptyMsg) {
            const empty = document.createElement('div');
            empty.className = 'kanban-column-empty';
            empty.innerHTML = '<i class="fas fa-inbox fa-2x mb-2"></i><br>Nenhum servico';
            column.appendChild(empty);
        } else if (count > 0 && emptyMsg) {
            emptyMsg.remove();
        }
    }

    function updateAllCounts() {
        ['PENDENTE', 'EM_PROCESSO', 'CONCLUIDO', 'FATURADO'].forEach(updateColumnCount);
    }

    let draggedElement = null;

    function handleDragStart(e) {
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.servicoId || '');
    }

    function handleDragEnd() {
        this.classList.remove('dragging');
        document.querySelectorAll('.kanban-column-body').forEach((col) => {
            col.classList.remove('drag-over');
        });
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDragEnter() {
        this.classList.add('drag-over');
    }

    function handleDragLeave() {
        this.classList.remove('drag-over');
    }

    async function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('drag-over');

        if (!draggedElement) {
            return false;
        }

        const novoStatus = this.dataset.status;
        const statusAtual = draggedElement.dataset.status;
        const servicoId = draggedElement.dataset.servicoId;

        if (!servicoId || !novoStatus || novoStatus === statusAtual) {
            return false;
        }

        if (statusAtual === 'CANCELADO') {
            showToast('warning', 'Servicos cancelados nao podem ser movidos.');
            return false;
        }

        try {
            const response = await fetch(servicoStatusUpdateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    servico_id: servicoId,
                    novo_status: novoStatus
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Erro ao atualizar status');
            }

            const colunaOrigem = draggedElement.closest('.kanban-column-body');
            this.appendChild(draggedElement);
            draggedElement.dataset.status = novoStatus;
            draggedElement.draggable = novoStatus !== 'CANCELADO' && novoStatus !== 'FATURADO';

            const badge = draggedElement.querySelector('.kanban-card-status');
            const badgeMap = {
                'PENDENTE': 'bg-warning',
                'EM_PROCESSO': 'bg-info',
                'CONCLUIDO': 'bg-dark',
                'FATURADO': 'bg-primary',
            'CANCELADO': 'bg-danger'
            };
            const labelMap = {
                'PENDENTE': 'Pendente',
                'EM_PROCESSO': 'Em Processo',
                'CONCLUIDO': 'Concluido',
                'FATURADO': 'Faturado',
            'CANCELADO': 'Cancelado'
            };

            if (badge) {
                badge.className = `badge ${badgeMap[novoStatus]} kanban-card-status`;
                badge.textContent = labelMap[novoStatus];
            }

            if (novoStatus === 'CANCELADO') {
                draggedElement.setAttribute('draggable', 'false');
            } else {
                draggedElement.setAttribute('draggable', 'true');
            }

            if (colunaOrigem) {
                updateColumnCount(colunaOrigem.dataset.status);
            }
            updateColumnCount(novoStatus);
            showToast('success', data.message || 'Status atualizado com sucesso!');
        } catch (error) {
            console.error('Erro ao atualizar status:', error);
            showToast('danger', 'Erro ao atualizar status: ' + error.message);
        }

        return false;
    }

    function showToast(type, message) {
        if (typeof Swal !== 'undefined') {
            const iconMap = {
                success: 'success',
                warning: 'warning',
                danger: 'error',
                info: 'info'
            };

            Swal.fire({
                title: type === 'success' ? 'Sucesso!' : (type === 'warning' ? 'Atencao' : 'Erro'),
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

        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
            document.body.appendChild(container);
        }

        let alertClass = 'danger';
        if (type === 'success') alertClass = 'success';
        if (type === 'warning') alertClass = 'warning';
        if (type === 'info') alertClass = 'info';

        const toast = document.createElement('div');
        toast.className = `alert alert-${alertClass} alert-dismissible fade show`;
        toast.style.cssText = 'min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 150);
        }, 5000);
    }

    function setupConfirmationActions() {
        if (typeof Swal === 'undefined') {
            return;
        }

        document.addEventListener('click', function(e) {
            const botao = e.target.closest('[data-confirm-action]');
            if (!botao) {
                return;
            }

            e.preventDefault();

            const acao = botao.getAttribute('data-confirm-action');
            const isCancelar = acao === 'cancelar-servico';
            const isEstornar = acao === 'estorno-servico';
            const isExcluir = acao === 'excluir-servico';

            if (!isCancelar && !isEstornar && !isExcluir) {
                return;
            }

            Swal.fire({
                title: isEstornar
                    ? 'Estornar faturamento?'
                    : (isExcluir ? 'Excluir serviço?' : 'Confirmar cancelamento?'),
                text: isEstornar
                    ? 'Essa ação removerá a conta a receber vinculada, se ela ainda estiver em aberto.'
                    : (isExcluir
                        ? 'Essa ação excluirá o serviço e removerá a conta vinculada, se ela ainda estiver em aberto.'
                        : 'Confirma o cancelamento deste serviço?'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: isEstornar
                    ? 'Estornar faturamento'
                    : (isExcluir ? 'Excluir serviço' : 'Cancelar serviço'),
                cancelButtonText: 'Voltar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: isExcluir ? 'btn btn-danger mx-1' : 'btn btn-warning mx-1',
                    cancelButton: 'btn btn-secondary mx-1'
                },
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = botao.getAttribute('href');
                }
            });
        }, true);
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof servicosData !== 'undefined' && Object.keys(servicosData).length > 0) {
            for (const [colunaStatus, servicosList] of Object.entries(servicosData)) {
                const column = document.getElementById(`column-${colunaStatus}`);
                if (!column) {
                    continue;
                }
                servicosList.forEach((servico) => {
                    column.appendChild(createServicoCard(servico));
                });
            }
        }

        document.querySelectorAll('.kanban-column-body').forEach((column) => {
            column.addEventListener('dragover', handleDragOver);
            column.addEventListener('dragenter', handleDragEnter);
            column.addEventListener('dragleave', handleDragLeave);
            column.addEventListener('drop', handleDrop);
        });

        updateAllCounts();
        setupConfirmationActions();
    });
    </script>

    <div class="kanban-board" id="kanbanBoard">
        <div class="kanban-column" data-status="PENDENTE">
            <div class="kanban-column-header bg-warning bg-opacity-10 border-warning">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Pendente
                    <span class="badge bg-warning ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-PENDENTE" data-status="PENDENTE"></div>
        </div>

        <div class="kanban-column" data-status="EM_PROCESSO">
            <div class="kanban-column-header bg-info bg-opacity-10 border-info">
                <h5 class="mb-0">
                    <i class="fas fa-spinner me-2"></i>Em Processo
                    <span class="badge bg-info ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-EM_PROCESSO" data-status="EM_PROCESSO"></div>
        </div>

        <div class="kanban-column" data-status="CONCLUIDO">
            <div class="kanban-column-header bg-dark bg-opacity-10 border-dark">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>Concluido
                    <span class="badge bg-dark ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-CONCLUIDO" data-status="CONCLUIDO"></div>
        </div>

        <div class="kanban-column" data-status="FATURADO">
            <div class="kanban-column-header bg-primary bg-opacity-10 border-primary">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Faturado
                    <span class="badge bg-primary ms-2 kanban-count">0</span>
                </h5>
            </div>
            <div class="kanban-column-body" id="column-FATURADO" data-status="FATURADO"></div>
        </div>
    </div>

    <script>
    const servicosData = <?= json_encode($servicosPorStatus, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>

    <?php if (empty($servicos)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p class="mb-0">Nenhum servico encontrado.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.kanban-board {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    min-height: 500px;
    overflow-x: auto;
    padding-bottom: 1rem;
}

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

.kanban-column-body.drag-over {
    background: #f3f4f6;
    border: 2px dashed #6c757d;
    border-radius: 6px;
}

.kanban-column-empty {
    text-align: center;
    color: #adb5bd;
    padding: 2rem 1rem;
    font-size: 0.85rem;
}

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

.kanban-card[draggable="false"] {
    cursor: default;
}

.kanban-card.dragging {
    opacity: 0.5;
    cursor: grabbing;
}

.kanban-card[data-status="PENDENTE"] {
    border-left-color: #ffc107;
}

.kanban-card[data-status="EM_PROCESSO"] {
    border-left-color: #0dcaf0;
}

.kanban-card[data-status="CONCLUIDO"] {
    border-left-color: #212529;
}

.kanban-card[data-status="FATURADO"] {
    border-left-color: #0d6efd;
}

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

.kanban-card-cliente,
.kanban-card-servico,
.kanban-card-produto {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.kanban-card-cliente {
    color: #495057;
    font-size: 0.85rem;
}

.kanban-card-servico {
    color: #212529;
    font-size: 0.85rem;
}

.kanban-card-produto {
    color: #6c757d;
    font-size: 0.8rem;
}

.kanban-produto-qtd {
    display: inline-block;
    margin-left: 0.5rem;
    font-size: 0.78rem;
    color: #495057;
    font-weight: 600;
}

.kanban-card-cliente i,
.kanban-card-servico i,
.kanban-card-produto i {
    color: #6c757d;
    font-size: 0.75rem;
}

.kanban-card-info {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.45rem;
    margin-bottom: 0.75rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
}

.kanban-card-info-item {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.75rem;
}

.kanban-card-info-label {
    color: #6c757d;
}

.kanban-card-info-value {
    color: #212529;
    font-weight: 600;
    text-align: right;
}

.kanban-card-actions {
    display: flex;
    gap: 0.25rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
    align-items: center;
}

.kanban-card-actions .btn {
    flex: 1;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.kanban-count {
    font-size: 0.75rem;
    min-width: 24px;
    text-align: center;
}

@media (max-width: 1199.98px) {
    .kanban-board {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 767.98px) {
    .kanban-board {
        grid-template-columns: 1fr;
    }
}
</style>
