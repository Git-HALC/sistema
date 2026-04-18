<?php
defined('APP_PATH') || die('Acesso negado');

$csrfToken = \App\Support\CsrfProtection::token();
?>

<div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancelar NF-e nº <span id="modalNumeroNfe"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Cancelamento irreversível. Prazo máximo: 24h após a emissão.
                </div>
                <label for="txtJustificativa" class="form-label">Justificativa *</label>
                <textarea id="txtJustificativa" class="form-control" rows="3" maxlength="255"></textarea>
                <div class="form-text"><span id="contadorChars">0</span>/15 caracteres mínimos</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarCancelar" disabled>Confirmar Cancelamento</button>
            </div>
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
const fiscalCsrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>';

document.addEventListener('DOMContentLoaded', function() {
    var modalElement = document.getElementById('modalCancelar');
    var txtJustificativa = document.getElementById('txtJustificativa');
    var contadorChars = document.getElementById('contadorChars');
    var btnConfirmarCancelar = document.getElementById('btnConfirmarCancelar');
    var nfeCancelarId = null;

    if (!modalElement || !txtJustificativa || !contadorChars || !btnConfirmarCancelar) {
        return;
    }

    document.querySelectorAll('.btn-cancelar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            nfeCancelarId = this.dataset.id;
            document.getElementById('modalNumeroNfe').textContent = this.dataset.numero;
            txtJustificativa.value = '';
            contadorChars.textContent = '0';
            btnConfirmarCancelar.disabled = true;
            new bootstrap.Modal(modalElement).show();
        });
    });

    txtJustificativa.addEventListener('input', function() {
        var n = this.value.length;
        contadorChars.textContent = String(n);
        btnConfirmarCancelar.disabled = n < 15;
    });

    btnConfirmarCancelar.addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(fiscalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': fiscalCsrfToken
            },
            body: 'action=cancelar&csrf_token=' + encodeURIComponent(fiscalCsrfToken)
                + '&id=' + encodeURIComponent(nfeCancelarId)
                + '&justificativa=' + encodeURIComponent(txtJustificativa.value)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            bootstrap.Modal.getInstance(modalElement).hide();
            if (data.sucesso) {
                Swal.fire({
                    icon: 'success',
                    title: 'NF-e cancelada!',
                    text: 'O lançamento financeiro foi liberado automaticamente.',
                    timer: 2500,
                    showConfirmButton: false
                }).then(function() {
                    location.reload();
                });
            } else {
                Swal.fire('Erro', data.erro || 'Erro ao cancelar.', 'error');
                btn.disabled = false;
                btn.textContent = 'Confirmar Cancelamento';
            }
        })
        .catch(function() {
            Swal.fire('Erro', 'Ocorreu um erro ao tentar cancelar.', 'error');
            btn.disabled = false;
            btn.textContent = 'Confirmar Cancelamento';
        });
    });
});
</script>
