(function (window, document) {
    const storageKey = 'overTime.auditLog.viewMode';
    const allowedModes = new Set(['table', 'timeline']);

    function getStoredMode() {
        try {
            const value = window.localStorage ? window.localStorage.getItem(storageKey) : '';
            return allowedModes.has(value) ? value : 'table';
        } catch (error) {
            return 'table';
        }
    }

    function storeMode(mode) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem(storageKey, mode);
            }
        } catch (error) {
            // localStorage may be unavailable in restricted browser contexts.
        }
    }

    function setMode(root, mode, shouldStore) {
        const nextMode = allowedModes.has(mode) ? mode : 'table';

        root.querySelectorAll('[data-audit-view-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-audit-view-panel') !== nextMode;
        });

        root.querySelectorAll('[data-audit-view-button]').forEach(function (button) {
            const isActive = button.getAttribute('data-audit-view-button') === nextMode;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        if (shouldStore) {
            storeMode(nextMode);
        }
    }

    function init(container) {
        const scope = container || document;
        const root = scope.querySelector('.audit-log-results');

        if (!root || root.getAttribute('data-view-toggle-ready') === 'true') {
            return;
        }

        root.setAttribute('data-view-toggle-ready', 'true');
        setMode(root, getStoredMode(), false);

        root.querySelectorAll('[data-audit-view-button]').forEach(function (button) {
            button.addEventListener('click', function () {
                setMode(root, button.getAttribute('data-audit-view-button'), true);
            });
        });
    }

    window.AuditLogViewToggle = {
        init: init
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        });
    } else {
        init(document);
    }
})(window, document);
