<?php
defined('APP_PATH') || die('Acesso negado');

$pedidos = is_array($pedidos ?? null) ? $pedidos : [];
$csrfToken = \App\Support\CsrfProtection::token();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0 text-gray-800">Documentos para Emissão de NF-e</h1>
        <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=listar')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-list me-1"></i> NF-e Emitidas
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo ($_SESSION['mensagem']['tipo'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars((string)($_SESSION['mensagem']['texto'] ?? '')); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="alert alert-warning d-flex align-items-center mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Valide cada pedido antes de emitir. Produtos sem NCM, CFOP ou CST completos impedirão a emissão.
    </div>

    <?php if ($pedidos === []): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Nenhum pedido ou serviço com produtos aguardando emissão de NF-e.
            <div class="mt-2">
                <a href="<?php echo htmlspecialchars(tenantUrl('admin/pedidos.php?status=FATURADO')); ?>" class="btn btn-outline-primary btn-sm">Ver pedidos faturados</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th>ORIGEM</th>
                                <th>NÚMERO</th>
                                <th>CLIENTE</th>
                                <th>FATURADO EM</th>
                                <th>VALOR</th>
                                <th>ITENS</th>
                                <th>STATUS FISCAL</th>
                                <th class="text-center">AÇÕES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $p): ?>
                                <tr>
                                        <td>
                                            <span class="badge <?php echo (($p['origem_tipo'] ?? 'PEDIDO') === 'SERVICO') ? 'bg-info text-dark' : 'bg-primary'; ?>">
                                                <?php echo htmlspecialchars((string)($p['origem_tipo'] ?? 'PEDIDO')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)($p['numero'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($p['cliente_nome'] ?? '')); ?></td>
                                    <td><?php echo !empty($p['data_faturamento']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$p['data_faturamento']))) : '—'; ?></td>
                                    <td><?php echo 'R$ ' . number_format((float)($p['valor_total'] ?? 0), 2, ',', '.'); ?></td>
                                    <td><?php echo isset($p['total_itens']) ? htmlspecialchars((string)$p['total_itens']) : '—'; ?></td>
                                    <td>
                                        <span class="badge bg-secondary"
                                            id="badge-<?php echo htmlspecialchars((string)(($p['origem_tipo'] ?? 'PEDIDO') === 'SERVICO' ? ($p['servico_id'] ?? '') : ($p['pedido_id'] ?? ''))); ?>"
                                            data-bs-toggle="popover"
                                            data-bs-placement="top"
                                            data-bs-trigger="hover"
                                            data-bs-html="true"
                                            data-bs-content="">
                                            Não verificado
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-info btn-sm btn-validar" title="Validar" data-origem-tipo="<?php echo htmlspecialchars((string)($p['origem_tipo'] ?? 'PEDIDO')); ?>" data-pedido-id="<?php echo htmlspecialchars((string)($p['pedido_id'] ?? '')); ?>" data-servico-id="<?php echo htmlspecialchars((string)($p['servico_id'] ?? '')); ?>" data-numero="<?php echo htmlspecialchars((string)($p['numero'] ?? '')); ?>">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                            <button type="button" class="btn btn-primary btn-sm btn-emitir" title="Emitir NF-e" id="btnEmitir-<?php echo htmlspecialchars((string)(($p['origem_tipo'] ?? 'PEDIDO') === 'SERVICO' ? ($p['servico_id'] ?? '') : ($p['pedido_id'] ?? ''))); ?>" data-origem-tipo="<?php echo htmlspecialchars((string)($p['origem_tipo'] ?? 'PEDIDO')); ?>" data-pedido-id="<?php echo htmlspecialchars((string)($p['pedido_id'] ?? '')); ?>" data-servico-id="<?php echo htmlspecialchars((string)($p['servico_id'] ?? '')); ?>" data-numero="<?php echo htmlspecialchars((string)($p['numero'] ?? '')); ?>" disabled>
                                                <i class="fas fa-file-invoice"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer" style="font-size:12px;">
                <span class="text-muted"><?php echo count($pedidos); ?> pedido(s) aguardando emissão</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const fiscalUrl = '<?= htmlspecialchars(
    function_exists('tenantUrl')
        ? tenantUrl('admin/fiscal.php')
        : (isset($tenant_slug)
            ? '/sistema_dm/public/' . $tenant_slug . '/admin/fiscal.php'
            : '/admin/fiscal.php')
) ?>';
const fiscalCsrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>';

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
        new bootstrap.Popover(el, { html: true });
    });

    document.querySelectorAll('.btn-validar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const origemTipo = this.dataset.origemTipo || 'PEDIDO';
            const documentoId = origemTipo === 'SERVICO' ? this.dataset.servicoId : this.dataset.pedidoId;
            const currentBtn = this;
            currentBtn.disabled = true;
            currentBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch(fiscalUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': fiscalCsrfToken
                },
                body: 'action=validar&csrf_token=' + encodeURIComponent(fiscalCsrfToken)
                    + '&' + (origemTipo === 'SERVICO' ? 'servico_id=' : 'pedido_id=') + encodeURIComponent(documentoId)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                const badge = document.getElementById('badge-' + documentoId);
                const btnEmitir = document.getElementById('btnEmitir-' + documentoId);
                const popover = bootstrap.Popover.getInstance(badge);

                if (data.valido) {
                    badge.className = 'badge bg-success';
                    badge.textContent = 'Pronto';
                    if (popover) {
                        popover.dispose();
                    }
                    btnEmitir.disabled = false;
                } else {
                    badge.className = 'badge bg-danger';
                    badge.textContent = 'Com erros';
                    const html = '<ul class="mb-0 ps-3 text-start">' + data.erros.map(function(e) {
                        return '<li>' + e + '</li>';
                    }).join('') + '</ul>';
                    if (popover) {
                        popover.dispose();
                    }
                    new bootstrap.Popover(badge, {
                        html: true,
                        trigger: 'hover focus',
                        placement: 'top',
                        content: html
                    });
                }

                currentBtn.disabled = false;
                currentBtn.innerHTML = '<i class="fas fa-check-circle"></i>';
            })
            .catch(function() {
                Swal.fire('Erro', 'Falha ao validar pedido.', 'error');
                currentBtn.disabled = false;
                currentBtn.innerHTML = '<i class="fas fa-check-circle"></i>';
            });
        });
    });

    document.querySelectorAll('.btn-emitir').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const origemTipo = this.dataset.origemTipo || 'PEDIDO';
            const documentoId = origemTipo === 'SERVICO' ? this.dataset.servicoId : this.dataset.pedidoId;
            const numero = this.dataset.numero;
            const currentBtn = this;

            Swal.fire({
                title: 'Emitir NF-e?',
                text: 'Confirma a emissão da NF-e para o pedido nº ' + numero + '?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Emitir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                footer: '<i class="fas fa-info-circle text-info"></i> A NF-e será enviada à SEFAZ.'
            }).then(function(result) {
                if (!result.isConfirmed) {
                    return;
                }

                Swal.fire({
                    title: 'Emitindo NF-e...',
                    text: 'Aguarde, comunicando com a SEFAZ.',
                    allowOutsideClick: false,
                    didOpen: function() { Swal.showLoading(); }
                });

                fetch(fiscalUrl, {
                    method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': fiscalCsrfToken
                },
                    body: 'action=emitir&csrf_token=' + encodeURIComponent(fiscalCsrfToken)
                        + '&' + (origemTipo === 'SERVICO' ? 'servico_id=' : 'pedido_id=') + encodeURIComponent(documentoId)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.sucesso) {
                        const linha = currentBtn.closest('tr');
                        linha.style.transition = 'opacity 0.4s';
                        linha.style.opacity = '0';
                        setTimeout(function() {
                            linha.remove();
                        }, 400);
                        Swal.fire({
                            icon: 'success',
                            title: 'NF-e emitida!',
                            text: 'Nota fiscal autorizada pela SEFAZ.',
                            timer: 2500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Erro na emissão', data.erro || 'Tente novamente.', 'error');
                    }
                })
                .catch(function() {
                    Swal.fire('Erro', 'Falha na comunicação.', 'error');
                });
            });
        });
    });
});
</script>
