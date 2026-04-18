<?php
defined('APP_PATH') || die('Acesso negado');

$nfe = $nfe ?? null;
$pedido = is_array($pedido ?? null) ? $pedido : [];
$servico = is_array($servico ?? null) ? $servico : [];
$cliente = is_array($cliente ?? null) ? $cliente : [];
$empresa = $empresa ?? null;

$statusBadgeMap = [
    'AUTORIZADA' => 'bg-success',
    'CANCELADA' => 'bg-secondary',
    'REJEITADA' => 'bg-danger',
    'PENDENTE' => 'bg-warning text-dark',
    'EMITIDA' => 'bg-info text-dark',
];

$formatarDocumento = static function (?string $valor): string {
    $digits = preg_replace('/\D+/', '', (string)$valor) ?? '';
    if (strlen($digits) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $digits;
    }
    if (strlen($digits) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
    }
    return $digits !== '' ? $digits : '?';
};

$status = strtoupper((string)($nfe?->status ?? ''));
$badgeClass = $statusBadgeMap[$status] ?? 'bg-secondary';
$ambienteHomologacao = $empresa !== null && (string)$empresa->ambiente_nfe === '2';
$serie = (string)($empresa->serie_nfe ?? '?');
$origemTipo = strtoupper((string)($origemTipo ?? ($nfe?->origemTipo() ?? 'PEDIDO')));
$documento = $origemTipo === 'SERVICO' ? $servico : $pedido;
$valorTotal = (float)($documento['valor_total'] ?? 0);
$itens = is_array($documento['itens'] ?? null) ? $documento['itens'] : [];
?>

<div class="container-fluid">
    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo ($_SESSION['mensagem']['tipo'] ?? '') === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mt-3" role="alert">
            <?php echo htmlspecialchars((string)($_SESSION['mensagem']['texto'] ?? '')); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <div>
            <h1 class="h3 mb-0 text-gray-800 d-inline">NF-e nº <?php echo htmlspecialchars((string)($nfe?->numero_nfe ?? '')); ?></h1>
            <span class="badge <?php echo htmlspecialchars($badgeClass); ?> ms-2"><?php echo htmlspecialchars($status); ?></span>
        </div>
        <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=listar')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <nav class="breadcrumb-nav mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php')); ?>">Fiscal</a></li>
            <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=listar')); ?>">NF-e Emitidas</a></li>
            <li class="breadcrumb-item active">NF-e nº <?php echo htmlspecialchars((string)($nfe?->numero_nfe ?? '')); ?></li>
        </ol>
    </nav>

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card shadow mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0 text-primary fw-bold"><i class="fas fa-file-invoice me-1"></i>Identificação</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <small class="text-muted">Número</small>
                            <div class="fw-bold"><?php echo htmlspecialchars((string)($nfe?->numero_nfe ?? '')); ?></div>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Série</small>
                            <div class="fw-bold"><?php echo htmlspecialchars($serie); ?></div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Emissão</small>
                            <div class="fw-bold"><?php echo !empty($nfe?->data_emissao) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$nfe->data_emissao))) : '?'; ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Ambiente</small>
                            <div>
                                <?php if ($ambienteHomologacao): ?>
                                    <span class="badge bg-warning text-dark">HOMOLOGAÇÃO</span>
                                <?php else: ?>
                                    <span class="badge bg-success">PRODUÇÃO</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <small class="text-muted">Protocolo SEFAZ</small>
                            <div class="font-monospace small"><?php echo htmlspecialchars((string)($nfe?->n_prot ?? '?')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Valor Total</small>
                            <div class="fw-bold text-success h5 mb-0"><?php echo 'R$ ' . number_format($valorTotal, 2, ',', '.'); ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Status</small>
                            <div><span class="badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($status); ?></span></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <small class="text-muted">Origem</small>
                            <div class="fw-bold"><?php echo htmlspecialchars($origemTipo); ?></div>
                        </div>
                        <div class="col-md-9">
                            <small class="text-muted"><?php echo $origemTipo === 'SERVICO' ? 'ServiÃ§o vinculado' : 'Pedido vinculado'; ?></small>
                            <div class="fw-bold">#<?php echo htmlspecialchars((string)($documento['numero'] ?? $documento['id'] ?? '?')); ?></div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted d-block">Chave de Acesso</small>
                        <code class="d-block bg-light p-2 rounded mt-1 user-select-all" style="font-size:0.72rem;word-break:break-all;font-family:monospace;letter-spacing:0.5px">
                            <?php echo htmlspecialchars(implode(' ', str_split((string)($nfe?->chave_acesso ?? ''), 11))); ?>
                        </code>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0 text-primary fw-bold"><i class="fas fa-building me-1"></i>Partes</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="text-primary small fw-bold mb-2">EMITENTE</h6>
                            <p class="mb-1 fw-bold"><?php echo htmlspecialchars((string)($empresa->nome ?? '?')); ?></p>
                            <small class="text-muted d-block">CNPJ: <?php echo htmlspecialchars($empresa !== null ? $formatarDocumento($empresa->cnpj) : '?'); ?></small>
                            <small class="text-muted d-block">IE: <?php echo htmlspecialchars((string)($empresa->ie ?? '?')); ?></small>
                        </div>
                        <div class="col-md-6 ps-md-4">
                            <h6 class="text-primary small fw-bold mb-2">DESTINAT?RIO</h6>
                            <p class="mb-1 fw-bold"><?php echo htmlspecialchars((string)($cliente['nome'] ?? '?')); ?></p>
                            <small class="text-muted d-block">CPF/CNPJ: <?php echo htmlspecialchars($formatarDocumento((string)($cliente['cpf_cnpj'] ?? ''))); ?></small>
                            <small class="text-muted d-block">IE: <?php echo htmlspecialchars((string)($cliente['ie'] ?? 'N?o contribuinte')); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-2">
                    <h6 class="mb-0 text-primary fw-bold"><i class="fas fa-list me-1"></i>Itens da NF-e</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>PRODUTO</th>
                                    <th>NCM</th>
                                    <th>CFOP</th>
                                    <th class="text-end">QTD</th>
                                    <th class="text-end">VL.UNIT</th>
                                    <th class="text-end">VL.TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)($item['nome_produto'] ?? '')); ?></td>
                                        <td class="font-monospace small"><?php echo htmlspecialchars((string)($item['ncm'] ?? '?')); ?></td>
                                        <td class="font-monospace small"><?php echo htmlspecialchars((string)($item['cfop'] ?? '?')); ?></td>
                                        <td class="text-end"><?php echo htmlspecialchars(number_format((float)($item['quantidade'] ?? 0), 2, ',', '.')); ?></td>
                                        <td class="text-end"><?php echo 'R$ ' . number_format((float)($item['valor_unitario'] ?? 0), 2, ',', '.'); ?></td>
                                        <td class="text-end fw-bold"><?php echo 'R$ ' . number_format((float)($item['valor_total_item'] ?? 0), 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Total</td>
                                    <td class="text-end fw-bold text-success"><?php echo 'R$ ' . number_format($valorTotal, 2, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0 text-primary fw-bold">A??es</h6>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=xml&id=' . urlencode((string)($nfe?->id ?? '')))); ?>" class="btn btn-outline-dark btn-sm" target="_blank">
                        <i class="fas fa-download me-1"></i> Download XML
                    </a>
                    <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=listar')); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Voltar ? lista
                    </a>
                    <?php if ($nfe !== null && $nfe->isCancelavel()): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-cancelar" data-id="<?php echo htmlspecialchars($nfe->id); ?>" data-numero="<?php echo htmlspecialchars((string)$nfe->numero_nfe); ?>">
                            <i class="fas fa-times-circle me-1"></i> Cancelar NF-e
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($nfe?->xml_nfe) && $nfe->xml_nfe !== 'PENDENTE_SEFAZ'): ?>
                <div class="card shadow">
                    <div class="card-header py-2">
                        <h6 class="mb-0 text-primary fw-bold"><i class="fas fa-code me-1"></i>XML</h6>
                    </div>
                    <div class="card-body p-0">
                        <pre class="bg-dark text-success p-3 mb-0 font-monospace" style="font-size:10px;max-height:180px;overflow-y:auto"><?php echo htmlspecialchars(substr((string)$nfe->xml_nfe, 0, 800)); ?></pre>
                        <div class="card-footer text-end py-1">
                            <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal.php?action=xml&id=' . urlencode((string)$nfe->id))); ?>" class="btn btn-sm btn-outline-dark" target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i> Ver completo
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const fiscalUrl = '<?= htmlspecialchars(
    function_exists('tenantUrl')
        ? tenantUrl('admin/fiscal.php')
        : (isset($tenant_slug)
            ? '/sistema_dm/public/' . $tenant_slug . '/admin/fiscal.php'
            : '/admin/fiscal.php')
) ?>';
</script>

<?php include __DIR__ . '/_modal_cancelar.php'; ?>
