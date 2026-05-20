(function (window, document) {
    'use strict';

    const DEFAULT_MESSAGE = 'โปรดรอสักครู่...';
    const DEFAULT_SUB_MESSAGE = 'ระบบกำลังประมวลผลข้อมูล กรุณารอสักครู่';
    const DEFAULT_DELAY_MS = 180;
    const DEFAULT_TIMEOUT_MS = 60000;
    const NAV_AUTO_HIDE_TIMEOUT = 2200;

    let overlay = null;
    let titleElement = null;
    let subTitleElement = null;
    let initialized = false;
    let activeCount = 0;
    let showTimer = null;
    let safetyTimer = null;
    const busyButtons = new WeakMap();

    function ensureOverlay() {
        if (overlay) {
            return overlay;
        }

        overlay = document.querySelector('[data-global-loading-overlay]');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'global-loading-overlay';
            overlay.setAttribute('data-global-loading-overlay', '1');
            overlay.setAttribute('aria-hidden', 'true');
            overlay.innerHTML = ''
                + '<div class="global-loading-panel" role="status" aria-live="polite" aria-atomic="true" aria-busy="true">'
                + '  <span class="global-loading-spinner" aria-hidden="true"></span>'
                + '  <div class="global-loading-title" data-global-loading-message>' + DEFAULT_MESSAGE + '</div>'
                + '  <div class="global-loading-subtitle" data-global-loading-sub-message>' + DEFAULT_SUB_MESSAGE + '</div>'
                + '</div>';
            document.body.appendChild(overlay);
        }

        titleElement = overlay.querySelector('[data-global-loading-message]');
        subTitleElement = overlay.querySelector('[data-global-loading-sub-message]');
        return overlay;
    }

    function normalizeOptions(messageOrOptions, subMessageOrOptions, maybeOptions) {
        const settings = {};

        if (typeof messageOrOptions === 'object' && messageOrOptions !== null) {
            Object.assign(settings, messageOrOptions);
        } else if (messageOrOptions) {
            settings.message = String(messageOrOptions);
        }

        if (typeof subMessageOrOptions === 'object' && subMessageOrOptions !== null) {
            Object.assign(settings, subMessageOrOptions);
        } else if (subMessageOrOptions) {
            settings.subMessage = String(subMessageOrOptions);
        }

        if (typeof maybeOptions === 'object' && maybeOptions !== null) {
            Object.assign(settings, maybeOptions);
        }

        return settings;
    }

    function updateMessage(message, subMessage) {
        ensureOverlay();
        if (titleElement) {
            titleElement.textContent = message || DEFAULT_MESSAGE;
        }
        if (subTitleElement) {
            subTitleElement.textContent = subMessage || DEFAULT_SUB_MESSAGE;
        }
    }

    function showOverlayNow() {
        ensureOverlay();
        if (activeCount <= 0) {
            return;
        }
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('global-loading-active');
    }

    function scheduleSafetyTimeout(timeoutMs) {
        window.clearTimeout(safetyTimer);
        safetyTimer = window.setTimeout(function () {
            if (activeCount <= 0) {
                return;
            }
            console.warn('Global loading timed out and was force-hidden.');
            activeCount = 0;
            hideOverlayNow();
        }, timeoutMs || DEFAULT_TIMEOUT_MS);
    }

    function hideOverlayNow() {
        window.clearTimeout(showTimer);
        window.clearTimeout(safetyTimer);
        showTimer = null;
        safetyTimer = null;
        ensureOverlay();
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('global-loading-active');
        updateMessage(DEFAULT_MESSAGE, DEFAULT_SUB_MESSAGE);
    }

    function setButtonBusy(button, isBusy, busyText) {
        if (!button) {
            return;
        }

        if (isBusy) {
            if (!busyButtons.has(button)) {
                busyButtons.set(button, {
                    disabled: !!button.disabled,
                    html: button.innerHTML,
                    text: button.textContent
                });
            }
            button.classList.add('is-busy');
            button.setAttribute('aria-busy', 'true');
            button.setAttribute('aria-disabled', 'true');
            if ('disabled' in button) {
                button.disabled = true;
            }
            if (busyText) {
                button.textContent = busyText;
            }
            return;
        }

        const original = busyButtons.get(button);
        button.classList.remove('is-busy');
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-disabled');
        if (original) {
            if ('disabled' in button) {
                button.disabled = original.disabled;
            }
            button.innerHTML = original.html;
            busyButtons.delete(button);
        } else if ('disabled' in button) {
            button.disabled = false;
        }
    }

    function showPageLoading(message, subMessage, options) {
        const settings = normalizeOptions(message, subMessage, options);
        const trigger = settings.trigger || null;
        const delayMs = Number.isFinite(Number(settings.delayMs)) ? Number(settings.delayMs) : DEFAULT_DELAY_MS;
        const timeoutMs = Number.isFinite(Number(settings.timeoutMs)) ? Number(settings.timeoutMs) : DEFAULT_TIMEOUT_MS;

        activeCount += 1;
        updateMessage(settings.message || DEFAULT_MESSAGE, settings.subMessage || DEFAULT_SUB_MESSAGE);
        if (trigger) {
            setButtonBusy(trigger, true, settings.busyText || trigger.getAttribute?.('data-loading-busy-text') || '');
        }

        if (!showTimer && !ensureOverlay().classList.contains('is-visible')) {
            showTimer = window.setTimeout(function () {
                showTimer = null;
                showOverlayNow();
            }, Math.max(0, delayMs));
        } else if (overlay.classList.contains('is-visible')) {
            showOverlayNow();
        }
        scheduleSafetyTimeout(timeoutMs);

        let hidden = false;
        return {
            hide: function () {
                if (hidden) {
                    return;
                }
                hidden = true;
                hidePageLoading({ trigger: trigger });
            }
        };
    }

    function hidePageLoading(options) {
        const settings = options || {};
        if (settings.trigger) {
            setButtonBusy(settings.trigger, false);
        }

        activeCount = Math.max(0, activeCount - 1);
        if (activeCount > 0) {
            scheduleSafetyTimeout(Number(settings.timeoutMs) || DEFAULT_TIMEOUT_MS);
            return;
        }
        hideOverlayNow();
    }

    async function withPageLoading(operation, options, legacyOptions) {
        const settings = normalizeOptions(options, null, legacyOptions);
        const controller = showPageLoading(settings.message || options, settings.subMessage, settings);
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
        return showPageLoading(message || (options && options.message) || DEFAULT_MESSAGE, options && options.subMessage, options || {});
    }

    function findSubmitter(event, form) {
        return event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    }

    function preserveSubmitterValue(form, submitter) {
        if (!submitter || !submitter.name) {
            return;
        }

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

    function handleSubmitEvent(event, form) {
        if (!form || event.defaultPrevented) {
            return;
        }
        if (form.dataset.loadingLocked === '1') {
            event.preventDefault();
            event.stopPropagation();
            return;
        }
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        const submitter = findSubmitter(event, form);
        preserveSubmitterValue(form, submitter);
        form.dataset.loadingLocked = '1';
        showPageLoading(
            submitter?.getAttribute('data-loading-message') || form.getAttribute('data-loading-message') || DEFAULT_MESSAGE,
            submitter?.getAttribute('data-loading-sub-message') || form.getAttribute('data-loading-sub-message') || 'กำลังส่งข้อมูลไปยังระบบ',
            {
                trigger: submitter || form,
                busyText: submitter?.getAttribute('data-loading-busy-text') || form.getAttribute('data-loading-busy-text') || '',
                timeoutMs: Number(form.getAttribute('data-loading-timeout-ms')) || DEFAULT_TIMEOUT_MS
            }
        );
    }

    function preventDoubleSubmit(form) {
        if (!form || form.dataset.loadingGuardBound === '1') {
            return;
        }
        form.dataset.loadingGuardBound = '1';
        form.addEventListener('submit', function (event) {
            handleSubmitEvent(event, form);
        });
    }

    function bindFormLoading() {
        document.addEventListener('submit', function (event) {
            const form = event.target.closest('form[data-global-loading-form], form[data-prevent-double-submit], form[data-loading-submit]');
            if (!form) {
                return;
            }
            handleSubmitEvent(event, form);
        });
    }

    function bindNavigationLoading() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-global-loading-nav], [data-global-loading-trigger]');
            if (!trigger || event.defaultPrevented || trigger.matches('[data-bs-toggle], [data-bs-dismiss]')) {
                return;
            }

            if (trigger.tagName === 'A') {
                const href = trigger.getAttribute('href') || '';
                if (href === '' || href === '#' || href.startsWith('javascript:')) {
                    return;
                }
            }

            showPageNavigationLoading(trigger.getAttribute('data-loading-message') || DEFAULT_MESSAGE, {
                trigger: trigger,
                subMessage: trigger.getAttribute('data-loading-sub-message') || 'กำลังเปิดหน้าที่เลือก',
                busyText: trigger.getAttribute('data-loading-busy-text') || ''
            });

            if (trigger.getAttribute('target') === '_blank' || trigger.hasAttribute('download')) {
                window.setTimeout(function () {
                    hidePageLoading({ trigger: trigger });
                }, Number(trigger.getAttribute('data-loading-autohide-ms') || NAV_AUTO_HIDE_TIMEOUT));
            }
        });
    }

    function bindExportLoading() {
        document.addEventListener('click', function (event) {
            const link = event.target.closest('.table-export-group a');
            if (!link || link.hasAttribute('data-global-loading-nav') || event.defaultPrevented) {
                return;
            }

            showPageNavigationLoading(link.getAttribute('data-loading-message') || 'กำลังส่งออกเอกสาร...', {
                trigger: link,
                subMessage: 'ระบบกำลังเตรียมไฟล์เอกสาร'
            });

            if (link.getAttribute('target') === '_blank' || link.hasAttribute('download')) {
                window.setTimeout(function () {
                    hidePageLoading({ trigger: link });
                }, Number(link.getAttribute('data-loading-autohide-ms') || NAV_AUTO_HIDE_TIMEOUT));
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
        showPageLoading: showPageLoading,
        hidePageLoading: hidePageLoading,
        withPageLoading: withPageLoading,
        setButtonBusy: setButtonBusy,
        preventDoubleSubmit: preventDoubleSubmit,
        showGlobalLoading: showPageLoading,
        hideGlobalLoading: hidePageLoading,
        withGlobalLoading: function (operation, message, options) {
            return withPageLoading(operation, normalizeOptions(message, null, options));
        },
        showPageNavigationLoading: showPageNavigationLoading
    };

    window.showPageLoading = showPageLoading;
    window.hidePageLoading = hidePageLoading;
    window.withPageLoading = withPageLoading;
    window.setButtonBusy = setButtonBusy;
    window.preventDoubleSubmit = preventDoubleSubmit;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})(window, document);
