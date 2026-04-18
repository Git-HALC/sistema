(function () {
    function q(selector, root) { return (root || document).querySelector(selector); }
    function qa(selector, root) { return Array.from((root || document).querySelectorAll(selector)); }

    function bindSidebarToggle() {
        const btn = q('[data-nav-toggle]');
        if (!btn) return;
        btn.addEventListener('click', function () {
            document.body.classList.toggle('nav-open');
        });
    }

    function isValidCnpj(cnpj) {
        const cleaned = (cnpj || '').replace(/\D+/g, '');
        if (cleaned.length !== 14) return false;
        if (/^(\d)\1+$/.test(cleaned)) return false;

        function calc(base, factor) {
            let sum = 0;
            for (let i = 0; i < base.length; i += 1) {
                sum += parseInt(base.charAt(i), 10) * factor;
                factor = factor === 2 ? 9 : factor - 1;
            }
            const mod = sum % 11;
            return mod < 2 ? 0 : 11 - mod;
        }

        const d1 = calc(cleaned.slice(0, 12), 5);
        const d2 = calc(cleaned.slice(0, 12) + d1, 6);
        return cleaned.endsWith(String(d1) + String(d2));
    }

    function bindCnpjMask() {
        const input = q('input[name="cnpj"]');
        if (!input) return;

        function format(v) {
            const d = (v || '').replace(/\D+/g, '').slice(0, 14);
            return d
                .replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        }

        function applyState() {
            const onlyDigits = input.value.replace(/\D+/g, '');
            input.classList.remove('input-valid', 'input-invalid');
            if (onlyDigits.length === 14) {
                if (isValidCnpj(input.value)) {
                    input.classList.add('input-valid');
                } else {
                    input.classList.add('input-invalid');
                }
            }
        }

        input.addEventListener('input', function () {
            input.value = format(input.value);
            applyState();
        });

        applyState();
    }

    function bindFloatLabels() {
        qa('.float-field').forEach(function (field) {
            const control = q('input, select, textarea', field);
            if (!control) return;

            function refresh() {
                const filled = String(control.value || '').trim() !== '';
                field.classList.toggle('is-filled', filled);
            }

            control.addEventListener('focus', function () {
                field.classList.add('is-focused');
            });

            control.addEventListener('blur', function () {
                field.classList.remove('is-focused');
                refresh();
            });

            control.addEventListener('input', refresh);
            control.addEventListener('change', refresh);
            refresh();
        });
    }

    function bindPlanCards() {
        const wrap = q('[data-license-plans]');
        const select = q('select[name="licenca_tipo"]');
        if (!wrap || !select) return;

        const cards = qa('[data-plan]', wrap);
        const sync = function (value) {
            cards.forEach(function (card) {
                card.classList.toggle('is-selected', card.getAttribute('data-plan') === value);
            });
        };

        cards.forEach(function (card) {
            card.addEventListener('click', function () {
                const value = card.getAttribute('data-plan') || '';
                if (!value) return;
                select.value = value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                sync(value);
            });
        });

        select.addEventListener('change', function () {
            sync(select.value);
        });

        sync(select.value);
    }

    function bindSubmitLoading() {
        qa('form[data-loading-submit]').forEach(function (form) {
            form.addEventListener('submit', function () {
                const btn = q('[type="submit"]', form);
                if (!btn) return;
                btn.classList.add('submit-loading');
                btn.setAttribute('disabled', 'disabled');
            });
        });
    }

    function bindCopyButtons() {
        qa('[data-copy-target]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const targetId = btn.getAttribute('data-copy-target');
                const feedbackId = btn.getAttribute('data-copy-feedback');
                const target = targetId ? document.getElementById(targetId) : null;
                if (!target) return;
                const text = (target.textContent || '').trim();
                if (!text) return;

                try {
                    await navigator.clipboard.writeText(text);
                    if (feedbackId) {
                        const feedback = document.getElementById(feedbackId);
                        if (feedback) {
                            feedback.classList.add('is-visible');
                            setTimeout(function () { feedback.classList.remove('is-visible'); }, 1300);
                        }
                    }
                } catch (e) {
                    // noop
                }
            });
        });
    }

    function clamp(num, min, max) {
        return Math.min(Math.max(num, min), max);
    }

    function bindDaysProgress() {
        qa('.days-wrap[data-days]').forEach(function (box) {
            const days = parseInt(box.getAttribute('data-days') || '0', 10);
            const label = q('.days-value', box);
            const fill = q('.progress > span', box);
            if (!fill || !label) return;

            let css = 'ok';
            if (days <= 3) css = 'danger';
            else if (days <= 7) css = 'warn';

            label.classList.remove('ok', 'warn', 'danger');
            label.classList.add(css);
            fill.classList.remove('ok', 'warn', 'danger');
            fill.classList.add(css);

            const pct = clamp(Math.round((days / 30) * 100), 0, 100);
            fill.style.width = pct + '%';
        });
    }

    function toDateStr(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function addDays(base, days) {
        const d = new Date(base.getTime());
        d.setDate(d.getDate() + days);
        return d;
    }

    function bindRenewPreview() {
        const form = q('[data-renew-form]');
        if (!form) return;

        const typeEl = q('select[name="licenca_tipo"]', form);
        const dateEl = q('input[name="licenca_fim"]', form);
        const currentDateEl = q('[data-current-fim]', form);
        const previewEl = q('[data-preview-fim]', form);
        const compareTypeEl = q('[data-compare-tipo]', form);
        if (!typeEl || !dateEl || !previewEl || !currentDateEl || !compareTypeEl) return;

        function calcByType() {
            const now = new Date();
            const type = typeEl.value;
            if (type === 'trial') return toDateStr(addDays(now, 15));
            if (type === 'anual') return toDateStr(addDays(now, 365));
            return toDateStr(addDays(now, 30));
        }

        function refresh() {
            const value = (dateEl.value || '').trim() || calcByType();
            previewEl.textContent = value;
            compareTypeEl.textContent = typeEl.value;
        }

        typeEl.addEventListener('change', refresh);
        dateEl.addEventListener('input', refresh);
        refresh();

        const modal = q('#confirmRenewModal');
        if (!modal) return;

        const openBtn = q('[data-open-renew-modal]');
        const cancelBtn = q('[data-close-renew-modal]', modal);
        const confirmBtn = q('[data-confirm-renew]', modal);

        if (openBtn) {
            openBtn.addEventListener('click', function () {
                modal.classList.add('is-open');
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                modal.classList.remove('is-open');
            });
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.remove('is-open');
            }
        });

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                modal.classList.remove('is-open');
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }
    }

    function bindClientFilter() {
        const input = q('[data-table-filter]');
        const rows = qa('tr[data-search-row]');
        if (!input || !rows.length) return;

        input.addEventListener('input', function () {
            const term = (input.value || '').toLowerCase().trim();
            rows.forEach(function (row) {
                const text = (row.getAttribute('data-search-row') || '').toLowerCase();
                row.style.display = text.indexOf(term) >= 0 ? '' : 'none';
            });
        });
    }

    bindSidebarToggle();
    bindCnpjMask();
    bindFloatLabels();
    bindPlanCards();
    bindSubmitLoading();
    bindCopyButtons();
    bindDaysProgress();
    bindRenewPreview();
    bindClientFilter();
})();
