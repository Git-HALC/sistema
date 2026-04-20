<?php
use App\Support\CsrfProtection;
$pendentes = $pendentes ?? [];
$filtros = $filtros ?? [];
$csrfToken = $csrfToken ?? CsrfProtection::token();
$action = '/sistema_dm/public/admin/fiscal/emitir.php';
?>
<div class="container-fluid py-3" style="color: var(--text-primary);">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Fiscal — Vendas sem documento</h1>
            <p class="text-muted mb-0 small">Vendas PDV faturadas sem NFC-e (produtos) ou NFS-e (serviços) emitida.</p>
        </div>
    </div>

    <form class="card mb-3" method="get" action="<?= $action ?>">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label small">Início</label><input type="date" class="form-control" name="inicio" value="<?= htmlspecialchars((string)$filtros['inicio']) ?>"></div>
                <div class="col-md-2"><label class="form-label small">Fim</label><input type="date" class="form-control" name="fim" value="<?= htmlspecialchars((string)$filtros['fim']) ?>"></div>
                <div class="col-md-3"><label class="form-label small">Cliente (nome ou CPF/CNPJ)</label><input type="text" class="form-control" name="cliente" value="<?= htmlspecialchars((string)$filtros['cliente']) ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filtrar</button></div>
            </div>
        </div>
    </form>

    <?php if (!$pendentes): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>Nenhuma venda pendente de fiscal no período.</div>
    <?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th class="text-end">Produtos</th>
                        <th class="text-end">Serviços</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendentes as $p): ?>
                    <?php
                        $temProd = (float)$p['total_produtos'] > 0;
                        $temServ = (float)$p['total_servicos'] > 0;
                        $precisaNfce = $temProd && empty($p['status_nfce']);
                        $precisaNfse = $temServ && empty($p['status_nfse']);
                    ?>
                    <tr>
                        <td><strong>#<?= (int)$p['numero'] ?></strong></td>
                        <td class="small"><?= date('d/m/Y H:i', strtotime((string)$p['created_at'])) ?></td>
                        <td><?= htmlspecialchars((string)$p['cliente']) ?></td>
                        <td class="text-end"><?= $temProd ? 'R$ ' . number_format((float)$p['total_produtos'], 2, ',', '.') : '—' ?></td>
                        <td class="text-end"><?= $temServ ? 'R$ ' . number_format((float)$p['total_servicos'], 2, ',', '.') : '—' ?></td>
                        <td class="text-center">
                            <?php if ($precisaNfce): ?>
                                <button class="btn btn-sm btn-primary me-1 btn-fiscal" data-tipo="nfce" data-venda="<?= (int)$p['venda_id'] ?>">
                                    <i class="fas fa-file-invoice me-1"></i>NFC-e
                                </button>
                            <?php endif; ?>
                            <?php if ($precisaNfse): ?>
                                <button class="btn btn-sm btn-info btn-fiscal" data-tipo="nfse" data-venda="<?= (int)$p['venda_id'] ?>">
                                    <i class="fas fa-file-contract me-1"></i>NFS-e
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function(){
    document.querySelectorAll('.btn-fiscal').forEach(function(btn){
        btn.addEventListener('click', function(){
            const tipo = btn.dataset.tipo;
            const vendaId = btn.dataset.venda;
            const label = tipo === 'nfce' ? 'NFC-e (produtos)' : 'NFS-e (serviços)';
            Swal.fire({
                title: 'Emitir ' + label + '?',
                text: 'Venda #' + vendaId,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, emitir',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-success mx-1', cancelButton: 'btn btn-secondary mx-1' }
            }).then(function(r){
                if (!r.isConfirmed) return;
                btn.disabled = true;
                const old = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                const fd = new FormData();
                fd.append('action', 'emitir-' + tipo);
                fd.append('venda_id', vendaId);
                fd.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
                fetch('<?= $action ?>', { method:'POST', body: fd })
                    .then(rr => rr.json().catch(() => ({ok:false, erro:'Resposta inválida'})))
                    .then(data => {
                        Swal.fire({
                            icon: data.ok ? 'success' : 'error',
                            title: data.ok ? 'Emitida' : 'Falha',
                            html: '<pre class="text-start small mb-0">' + JSON.stringify(data, null, 2) + '</pre>',
                            buttonsStyling: false,
                            customClass: { confirmButton: 'btn btn-' + (data.ok ? 'success' : 'danger') }
                        }).then(() => { if (data.ok) location.reload(); else { btn.disabled = false; btn.innerHTML = old; } });
                    });
            });
        });
    });
})();
</script>
