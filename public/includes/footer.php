            </div><!-- /.container-fluid -->
        </div><!-- /.page-body -->

    </div><!-- /.main-wrapper -->

</div><!-- /.app-layout -->

<footer class="app-footer">
    Sistema DM &copy; <?php echo date('Y'); ?> - Todos os direitos reservados
</footer>

<?php $menuJsVersion = @filemtime(__DIR__ . '/../assets/js/menu.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars(tenantUrl('assets/js/menu.js')); ?>?v=<?php echo $menuJsVersion; ?>"></script>

<script>
/* Theme */
(function () {
    function applyTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        localStorage.setItem('theme', t);
    }

    function initTheme() {
        var saved = localStorage.getItem('theme');
        var t = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        applyTheme(t);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();

        function protegerControlesInterativos() {
            document.querySelectorAll('select, input[type="date"], .datepicker').forEach(function (el) {
                if (el.dataset.clickGuard === '1') return;
                el.dataset.clickGuard = '1';

                el.addEventListener('mousedown', function (ev) {
                    ev.stopPropagation();
                });
                el.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                });
            });
        }

        protegerControlesInterativos();

        if (window.__flashMessage && typeof Swal !== 'undefined') {
            var tipo = String(window.__flashMessage.tipo || 'info').toLowerCase();
            var texto = String(window.__flashMessage.texto || '');
            var icon = (tipo === 'success') ? 'success' : ((tipo === 'error' || tipo === 'danger') ? 'error' : 'warning');
            var confirmClass = (icon === 'error') ? 'btn btn-danger mx-1' : ((icon === 'success') ? 'btn btn-success mx-1' : 'btn btn-warning mx-1');

            Swal.fire({
                title: (icon === 'success') ? 'Sucesso' : (icon === 'error' ? 'AtenÃ§Ã£o' : 'Aviso'),
                html: texto,
                icon: icon,
                confirmButtonText: 'OK',
                buttonsStyling: false,
                customClass: {
                    confirmButton: confirmClass
                }
            });
        }

        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', function () {
                var cur = document.documentElement.getAttribute('data-theme');
                applyTheme(cur === 'dark' ? 'light' : 'dark');
            });
        }
    });
})();
</script>

<?php
$chatFile = __DIR__ . '/chat-floating.php';
if (file_exists($chatFile)) include $chatFile;
?>
<div class="modal fade" id="modalFaturamentoGlobal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Faturar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formFaturamentoGlobal">
                <div class="modal-body">
                    <input type="hidden" name="data_faturamento" id="faturamento_data" value="<?php echo date('Y-m-d'); ?>">
                    <div class="mb-3">
                        <label for="faturamento_forma_pagamento_id" class="form-label">Forma de Pagamento *</label>
                        <select class="form-select" id="faturamento_forma_pagamento_id" name="forma_pagamento_id" required>
                            <option value="">Selecione...</option>
                        </select>
                        <small class="text-muted d-block mt-1" id="faturamento_hint">Selecione a forma para continuar.</small>
                    </div>
                    <div class="mb-3 d-none" id="faturamento_vencimento_wrapper">
                        <label for="faturamento_data_vencimento" class="form-label">Data de Vencimento *</label>
                        <input type="date" class="form-control" id="faturamento_data_vencimento" name="data_vencimento">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar faturamento</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const modalEl = document.getElementById('modalFaturamentoGlobal');
    const formEl = document.getElementById('formFaturamentoGlobal');
    if (!modalEl || !formEl || typeof window.bootstrap === 'undefined') {
        return;
    }

    const modal = new bootstrap.Modal(modalEl);
    const selectEl = document.getElementById('faturamento_forma_pagamento_id');
    const hintEl = document.getElementById('faturamento_hint');
    const vencimentoWrapperEl = document.getElementById('faturamento_vencimento_wrapper');
    const vencimentoEl = document.getElementById('faturamento_data_vencimento');
    const dataEl = document.getElementById('faturamento_data');
    const opcoesUrl = '/sistema_dm/public/admin/financeiro/formas-pagamento.php?action=opcoes-json';
    let actionUrl = '';
    let optionsLoaded = false;

    function resetModal(clearActionUrl = true) {
        if (clearActionUrl) {
            actionUrl = '';
        }
        formEl.reset();
        dataEl.value = new Date().toISOString().slice(0, 10);
        hintEl.textContent = 'Selecione a forma para continuar.';
        vencimentoWrapperEl.classList.add('d-none');
        vencimentoEl.required = false;
    }

    function updateHint() {
        const option = selectEl.options[selectEl.selectedIndex];
        if (!option || !option.value) {
            hintEl.textContent = 'Selecione a forma para continuar.';
            vencimentoWrapperEl.classList.add('d-none');
            vencimentoEl.required = false;
            return;
        }

        const tipo = String(option.dataset.tipo || '').toUpperCase();
        const taxa = parseFloat(option.dataset.taxa || '0');
        const prazo = parseInt(option.dataset.prazo || '0', 10);
        const conta = String(option.dataset.conta || '');
        const adquirente = String(option.dataset.adquirente || '');

        if (tipo === 'AF') {
            vencimentoWrapperEl.classList.remove('d-none');
            vencimentoEl.required = true;
            hintEl.textContent = 'A faturar: informe a data de vencimento para gerar o titulo pendente.';
            return;
        }

        vencimentoWrapperEl.classList.add('d-none');
        vencimentoEl.required = false;

        if (tipo === 'CC' || tipo === 'CD') {
            hintEl.textContent = 'Cartao: ' + (adquirente ? ('repasse via ' + adquirente) : 'adquirente nao configurada')
                + ' | prazo ' + prazo + ' dia(s)'
                + (taxa > 0 ? (' | taxa ' + taxa.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + '%') : '');
            return;
        }

        hintEl.textContent = (conta ? ('Banco configurado: ' + conta) : 'Forma sem banco configurado')
            + (taxa > 0 ? (' | taxa ' + taxa.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + '%') : '');
    }

    function fillOptions(formas) {
        selectEl.innerHTML = '<option value="">Selecione...</option>';
        formas.forEach(function (forma) {
            const option = document.createElement('option');
            option.value = String(forma.id);
            option.textContent = forma.nome;
            option.dataset.tipo = forma.tipo || '';
            option.dataset.taxa = String(forma.taxa ?? 0);
            option.dataset.prazo = String(forma.prazo_dias ?? 0);
            option.dataset.conta = forma.conta_nome || '';
            option.dataset.adquirente = forma.adquirente_nome || '';
            selectEl.appendChild(option);
        });
    }

    function ensureOptions(callback) {
        if (optionsLoaded) {
            callback();
            return;
        }

        $.getJSON(opcoesUrl)
            .done(function (resp) {
                fillOptions(resp.formas || []);
                optionsLoaded = true;
                callback();
            })
            .fail(function () {
                Swal.fire('Erro', 'Nao foi possivel carregar as formas de pagamento.', 'error');
            });
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[href*="pedidos.php?action=invoice"], a[href*="servicos.php?action=faturar"]');
        if (!link) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        const nextActionUrl = link.getAttribute('href') || '';
        ensureOptions(function () {
            resetModal(false);
            actionUrl = nextActionUrl;
            modal.show();
        });
    }, true);

    selectEl.addEventListener('change', updateHint);
    modalEl.addEventListener('hidden.bs.modal', resetModal);

    formEl.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!actionUrl) {
            return;
        }

        const payload = $(formEl).serialize() + '&format=json';
        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                modal.hide();
                window.location.href = resp.redirect || actionUrl;
                return;
            }

            if (resp.requires_payment_config && resp.redirect_url) {
                Swal.fire({
                    title: 'Configuracao pendente',
                    text: resp.message + ' Deseja ir para o cadastro agora?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ir para cadastro',
                    cancelButtonText: 'Fechar'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = resp.redirect_url;
                    }
                });
                return;
            }

            Swal.fire('Erro', resp.message || 'Nao foi possivel faturar.', 'error');
        }).fail(function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Nao foi possivel faturar.';
            Swal.fire('Erro', message, 'error');
        });
    });
})();
</script>
</body>
</html>

