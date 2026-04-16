(function (window, document) {
    const DEFAULT_MESSAGE = 'โปรดรอสักครู่...';
    const AUTO_HIDE_TIMEOUT = 2200;
    let overlay;
    let titleElement;
    let activeCount = 0;
    let initialized = false;

    function ensureOverlay() {
        if (overlay) {
            return overlay;
        }

        overlay = document.querySelector('[data-global-loading-overlay]');
        if (overlay) {
            titleElement = overlay.querySelector('[data-global-loading-message]');
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.className = 'global-loading-overlay';
        overlay.setAttribute('data-global-loading-overlay', '1');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = ''
            + '<div class="global-loading-panel" role="status" aria-live="polite" aria-atomic="true">'
            + '  <div class="spinner-border global-loading-spinner" aria-hidden="true"></div>'
            + '  <div class="global-loading-title" data-global-loading-message>' + DEFAULT_MESSAGE + '</div>'
            + '  <div class="global-loading-subtitle">ระบบกำลังประมวลผลข้อมูล กรุณารอสักครู่</div>'
            + '</div>';

        document.body.appendChild(overlay);
        titleElement = overlay.querySelector('[data-global-loading-message]');
        return overlay;
    }

    function setMessage(message) {
        ensureOverlay();
        if (titleElement) {
            titleElement.textContent = message || DEFAULT_MESSAGE;
        }
    }

    function markBusy(trigger, isBusy) {
        if (!trigger) {
            return;
        }

        if (isBusy) {
            trigger.classList.add('is-busy');
            if ('disabled' in trigger) {
                trigger.dataset.loadingWasDisabled = trigger.disabled ? '1' : '0';
                trigger.disabled = true;
            }
            trigger.setAttribute('aria-disabled', 'true');
        } else {
            trigger.classList.remove('is-busy');
            if ('disabled' in trigger) {
                const wasDisabled = trigger.dataset.loadingWasDisabled === '1';
                if (!wasDisabled) {
                    trigger.disabled = false;
                }
                delete trigger.dataset.loadingWasDisabled;
            }
            trigger.removeAttribute('aria-disabled');
        }
    }

    function showGlobalLoading(message, options) {
        const settings = options || {};
        ensureOverlay();
        setMessage(message || settings.message || DEFAULT_MESSAGE);
        activeCount += 1;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('global-loading-active');

        if (settings.trigger) {
            markBusy(settings.trigger, true);
        }

        return {
            hide: function () {
                hideGlobalLoading({
                    trigger: settings.trigger
                });
            }
        };
    }

    function hideGlobalLoading(options) {
        const settings = options || {};
        if (settings.trigger) {
            markBusy(settings.trigger, false);
        }

        activeCount = Math.max(0, activeCount - 1);
        if (activeCount > 0) {
            return;
        }

        ensureOverlay();
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('global-loading-active');
        setMessage(DEFAULT_MESSAGE);
    }

    async function withGlobalLoading(operation, message, options) {
        const controller = showGlobalLoading(message, options);
        try {
            if (typeof operation === 'function') {
                return await operation();
            }
            return await operation;
        } finally {
            controller.hide();
        }
    }

    function showPageNavigationLoading(message, options) {
        const settings = options || {};
        showGlobalLoading(message || settings.message || DEFAULT_MESSAGE, {
            trigger: settings.trigger || null
        });
    }

    function bindFormLoading() {
        document.addEventListener('submit', function (event) {
            const form = event.target.closest('form[data-global-loading-form]');
            if (!form || event.defaultPrevented || form.dataset.loadingLocked === '1') {
                return;
            }

            const submitter = event.submitter || form.querySelector('[type="submit"]');
            if (submitter && submitter.name) {
                let preservedField = form.querySelector('[data-loading-submit-preserve="' + submitter.name + '"]');
                if (!preservedField) {
                    preservedField = document.createElement('input');
                    preservedField.type = 'hidden';
                    preservedField.name = submitter.name;
                    preservedField.setAttribute('data-loading-submit-preserve', submitter.name);
                    form.appendChild(preservedField);
                }
                preservedField.value = submitter.value || '1';
            }
            const message = submitter && submitter.getAttribute('data-loading-message')
                ? submitter.getAttribute('data-loading-message')
                : (form.getAttribute('data-loading-message') || DEFAULT_MESSAGE);

            form.dataset.loadingLocked = '1';
            showGlobalLoading(message, { trigger: submitter || form });
            window.setTimeout(function () {
                hideGlobalLoading({ trigger: submitter || form });
                delete form.dataset.loadingLocked;
            }, Number(form.getAttribute('data-loading-autohide-ms') || 15000));
        });
    }

    function bindNavigationLoading() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-global-loading-nav], [data-global-loading-trigger]');
            if (!trigger || event.defaultPrevented) {
                return;
            }

            if (trigger.matches('[data-bs-toggle], [data-bs-dismiss]')) {
                return;
            }

            if (trigger.tagName === 'A') {
                const href = trigger.getAttribute('href') || '';
                if (href === '' || href === '#' || href.startsWith('javascript:')) {
                    return;
                }
            }

            const message = trigger.getAttribute('data-loading-message') || DEFAULT_MESSAGE;
            showPageNavigationLoading(message, { trigger: trigger });

            if (trigger.getAttribute('target') === '_blank' || trigger.hasAttribute('download')) {
                window.setTimeout(function () {
                    hideGlobalLoading({ trigger: trigger });
                }, Number(trigger.getAttribute('data-loading-autohide-ms') || AUTO_HIDE_TIMEOUT));
            }
        });
    }

    function bindExportLoading() {
        document.addEventListener('click', function (event) {
            const link = event.target.closest('.table-export-group a');
            if (!link || link.hasAttribute('data-global-loading-nav') || event.defaultPrevented) {
                return;
            }

            const message = link.getAttribute('data-loading-message') || 'กำลังส่งออกเอกสาร...';
            showPageNavigationLoading(message, { trigger: link });

            if (link.getAttribute('target') === '_blank' || link.hasAttribute('download')) {
                window.setTimeout(function () {
                    hideGlobalLoading({ trigger: link });
                }, Number(link.getAttribute('data-loading-autohide-ms') || AUTO_HIDE_TIMEOUT));
            }
        });
    }

    function init() {
        if (initialized) {
            ensureOverlay();
            return;
        }
        initialized = true;
        ensureOverlay();
        bindFormLoading();
        bindNavigationLoading();
        bindExportLoading();
    }

    window.GlobalLoading = {
        init: init,
        showGlobalLoading: showGlobalLoading,
        hideGlobalLoading: hideGlobalLoading,
        withGlobalLoading: withGlobalLoading,
        showPageNavigationLoading: showPageNavigationLoading
    };
})(window, document);
