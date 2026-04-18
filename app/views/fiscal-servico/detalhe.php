<?php

declare(strict_types=1);

$nota = $nota ?? null;
$servico = $servico ?? [];
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Detalhe da NFS-e</h1>
            <p class="text-muted mb-0">Servico #<?php echo htmlspecialchars((string)($servico['numero'] ?? $nota?->servico_id ?? '')); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars(tenantUrl('admin/fiscal-servico.php?action=index')); ?>" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Dados da nota</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Status</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$nota?->status); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Numero NFS-e</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)($nota?->numero_nfse ?? 'Pendente')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Numero RPS</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$nota?->numero_rps); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Codigo de verificacao</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)($nota?->codigo_verificacao ?? '')); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Valor do servico</div>
                            <div class="fw-semibold">R$ <?php echo number_format((float)($servico['servico_valor'] ?? 0), 2, ',', '.'); ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Descricao</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)($servico['servico_nome'] ?? '')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Tomador</h2>
                    <div class="mb-2"><span class="text-muted small">Nome</span><div class="fw-semibold"><?php echo htmlspecialchars((string)($servico['cliente_nome'] ?? 'Nao informado')); ?></div></div>
                    <div class="mb-2"><span class="text-muted small">Documento</span><div class="fw-semibold"><?php echo htmlspecialchars((string)($servico['cliente_documento'] ?? '')); ?></div></div>
                    <div class="mb-2"><span class="text-muted small">E-mail</span><div class="fw-semibold"><?php echo htmlspecialchars((string)($servico['cliente_email'] ?? '')); ?></div></div>
                    <div><span class="text-muted small">Telefone</span><div class="fw-semibold"><?php echo htmlspecialchars((string)($servico['cliente_telefone'] ?? '')); ?></div></div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Historico de envios</h2>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">XML enviado</div>
                        <pre class="bg-light p-3 rounded small overflow-auto"><?php echo htmlspecialchars((string)$nota?->xml_enviado); ?></pre>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">XML retorno</div>
                        <pre class="bg-light p-3 rounded small overflow-auto"><?php echo htmlspecialchars((string)$nota?->xml_retorno); ?></pre>
                    </div>
                    <div>
                        <div class="text-muted small mb-1">Mensagem de erro</div>
                        <pre class="bg-light p-3 rounded small overflow-auto mb-0"><?php echo htmlspecialchars((string)$nota?->erro_mensagem); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
