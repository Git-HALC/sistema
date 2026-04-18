<?php
$usuarioNome = (string)($usuarioNome ?? '');
$usuarioEmail = (string)($usuarioEmail ?? '');
$csrfToken = (string)($csrfToken ?? '');
?>
<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h1 class="h4 mb-1">Abrir Caixa</h1>
                    <p class="text-muted mb-0">Confirme sua senha e informe o suprimento inicial para começar o turno.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Operador</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($usuarioNome) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($usuarioEmail) ?>" readonly>
                        </div>
                    </div>

                    <form method="post" action="<?= htmlspecialchars(tenantCleanUrl('pdv')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="abrir-post">

                        <div class="mb-3">
                            <label class="form-label" for="valor_suprimento">Suprimento inicial</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="valor_suprimento" name="valor_suprimento" value="0.00" required>
                            <div class="form-text">Informe apenas o dinheiro colocado no caixa na abertura.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="senha_confirmacao">Confirmação de senha</label>
                            <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" required autocomplete="current-password">
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= htmlspecialchars(tenantCleanUrl('pdv')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-lock-open me-1"></i> Abrir caixa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
