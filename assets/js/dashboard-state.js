/**
 * dashboard-state.js
 * UI state persistence for Over Time hospital dashboard.
 *
 * Namespace: overtime:ui:
 * Keys are scoped to userId + pageKey so users never share state.
 *
 * Depends on page-state.js (window.PageState) being loaded first.
 *
 * Responsibilities:
 *  1. Auto-bind every form[data-page-state-key] → save/restore on change
 *  2. Sidebar scroll position → save with throttle, restore on load
 *  3. Clear page state when a "ล้างตัวกรอง" reset link is activated
 *  4. View-mode toggles (card/table) → save immediately
 *  5. Never store passwords, tokens, CSRF, file inputs, or checkboxes
 *     that could trigger bulk actions across sessions.
 */
(function (window, document) {
    'use strict';

    /* ── guards ── */
    if (window.DashboardState) { return; }     // prevent double-init from multiple includes

    var SIDEBAR_KEY_SCOPE = 'sidebar-scroll';
    var READY = false;

    /* ────────────────────────────────────────
       Utility: debounce / throttle
    ──────────────────────────────────────── */
    function debounce(fn, delay) {
        var timer;
        return function () {
            var ctx  = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    function throttle(fn, limit) {
        var waiting = false;
        return function () {
            if (!waiting) {
                fn.apply(this, arguments);
                waiting = true;
                setTimeout(function () { waiting = false; }, limit);
            }
        };
    }

    /* ────────────────────────────────────────
       Sidebar scroll persistence
    ──────────────────────────────────────── */
    function getSidebarScrollKey() {
        var ps = window.PageState;
        if (!ps) { return ''; }
        return ps.getScopedStateKey(SIDEBAR_KEY_SCOPE);
    }

    function saveSidebarScroll(panel) {
        var key = getSidebarScrollKey();
        if (!key) { return; }
        try {
            window.localStorage.setItem(key, String(panel.scrollTop));
        } catch (e) { /* ignore */ }
    }

    function restoreSidebarScroll(panel) {
        var key = getSidebarScrollKey();
        if (!key) { return; }
        try {
            var saved = window.localStorage.getItem(key);
            if (saved !== null && saved !== '') {
                panel.scrollTop = parseInt(saved, 10) || 0;
                return;
            }
        } catch (e) { /* ignore */ }

        /* fallback: scroll active menu item into view gently */
        var active = panel.querySelector('.dash-sidebar-link.is-active, .dash-sidebar-link[aria-current="page"]');
        if (active && typeof active.scrollIntoView === 'function') {
            active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function bindSidebar() {
        var panel = document.querySelector('.dash-sidebar-panel');
        if (!panel || panel.dataset.dashStatesBound) { return; }
        panel.dataset.dashStatesBound = '1';

        restoreSidebarScroll(panel);

        var saveThrottled = throttle(function () { saveSidebarScroll(panel); }, 250);
        panel.addEventListener('scroll', saveThrottled, { passive: true });
    }

    /* ────────────────────────────────────────
       Filter form — save / restore
    ──────────────────────────────────────── */
    /**
     * Return all saveable fields from a form.
     * Excludes: submit, button, reset, file, password, hidden, CSRF-like,
     *           page/p (pagination) to avoid stale-page restores,
     *           view (managed separately by view-mode logic),
     *           checkbox fields that could drive bulk operations.
     */
    function getFilterFields(form) {
        var SKIP_TYPES  = ['submit', 'button', 'reset', 'file', 'password', 'hidden'];
        var SKIP_NAMES  = ['p', 'page', '_csrf', 'csrf', 'token', '_token', 'view'];
        var result = [];
        var fields = form.querySelectorAll('input[name], select[name], textarea[name]');
        for (var i = 0; i < fields.length; i++) {
            var f    = fields[i];
            var type = (f.type || '').toLowerCase();
            var name = (f.name || '').toLowerCase();
            if (SKIP_TYPES.indexOf(type) !== -1) { continue; }
            if (SKIP_NAMES.indexOf(name)  !== -1) { continue; }
            result.push(f);
        }
        return result;
    }

    function captureFilterState(form) {
        var state = {};
        getFilterFields(form).forEach(function (f) {
            var type = (f.type || '').toLowerCase();
            if (type === 'checkbox') {
                // Only save non-row-selection checkboxes (e.g. global filter toggles)
                // Skip any checkbox that looks like a "select row" control
                if ((f.name || '').match(/^(select|check|row|id)/i)) { return; }
                state[f.name] = f.checked ? (f.value || '1') : '';
                return;
            }
            if (type === 'radio') {
                if (f.checked) { state[f.name] = f.value; }
                return;
            }
            state[f.name] = f.value;
        });
        return state;
    }

    function applyFilterState(form, state) {
        if (!state || typeof state !== 'object') { return; }
        getFilterFields(form).forEach(function (f) {
            if (!Object.prototype.hasOwnProperty.call(state, f.name)) { return; }
            var val  = state[f.name];
            var type = (f.type || '').toLowerCase();
            if (type === 'checkbox') {
                f.checked = String(val) === String(f.value || '1');
                return;
            }
            if (type === 'radio') {
                f.checked = String(f.value) === String(val);
                return;
            }
            /* text/search/select/date/number — set as value (XSS-safe, not innerHTML) */
            if (f.tagName === 'SELECT') {
                /* validate: only apply if the option actually exists */
                var exists = false;
                for (var j = 0; j < f.options.length; j++) {
                    if (f.options[j].value === String(val)) { exists = true; break; }
                }
                if (exists) { f.value = val; }
            } else {
                f.value = val;
            }
        });
        /* always reset pagination to 1 on restore */
        var pageField = form.querySelector('[name="p"], [name="page"]');
        if (pageField) { pageField.value = '1'; }
    }

    function urlHasFilterParams(form) {
        /* If the current URL already encodes filter params, don't override with stored state */
        var params = new URLSearchParams(window.location.search);
        var fields  = getFilterFields(form);
        for (var i = 0; i < fields.length; i++) {
            var name = fields[i].name;
            if (params.has(name) && params.get(name) !== '') { return true; }
        }
        return false;
    }

    function bindFilterForm(form) {
        if (form.dataset.dashStateBound) { return; }
        form.dataset.dashStateBound = '1';

        var pageKey = form.dataset.pageStateKey;
        if (!pageKey || !window.PageState) { return; }

        /* ── Restore ── */
        if (!urlHasFilterParams(form)) {
            var saved = window.PageState.loadPageState(pageKey);
            if (saved) { applyFilterState(form, saved); }
        }

        /* ── Save helpers ── */
        function save() {
            if (!window.PageState) { return; }
            window.PageState.savePageState(pageKey, captureFilterState(form));
        }
        var debouncedSave = debounce(save, 400);

        /* ── Bind field events ── */
        var fields = getFilterFields(form);
        fields.forEach(function (f) {
            var type = (f.type || '').toLowerCase();
            if (type === 'text' || type === 'search' || type === 'number') {
                f.addEventListener('input', debouncedSave);
            } else {
                f.addEventListener('change', save);
            }
        });

        /* ── Bind "ล้างตัวกรอง" / reset links within the same card ── */
        /* Walk up to the card container and find reset anchors */
        var card = form.closest('.dash-card, .approval-filter-card, .my-report-filter-card, .department-report-filter-card, .manage-time-filter-card, .daily-filter-card, aside');
        var scope = card || document;
        var resetLinks = scope.querySelectorAll(
            'a[href*="approval_queue.php"][href*=".php"], a.dash-btn-ghost, a[href$=".php"]'
        );
        /* more targeted: look for any link that navigates to the base page without query params */
        var basePage = window.location.pathname.split('/').pop();
        var resetAnchors = scope.querySelectorAll('a[href="' + basePage + '"]');
        if (!resetAnchors.length) {
            /* fallback: any element with class suggesting clear/reset within this scope */
            resetAnchors = scope.querySelectorAll('[class*="clear"], [class*="reset"], [href*="ล้าง"]');
        }
        /* Also hook the form's reset event */
        form.addEventListener('reset', function () {
            if (window.PageState) {
                window.PageState.clearPageState(pageKey);
            }
        });

        resetAnchors.forEach(function (anchor) {
            anchor.addEventListener('click', function () {
                if (window.PageState) {
                    window.PageState.clearPageState(pageKey);
                }
            });
        });
    }

    /* ────────────────────────────────────────
       View-mode toggle (card / table pills)
    ──────────────────────────────────────── */
    function bindViewMode() {
        /* Forms already track the `view` hidden field via PHP URL round-trip.
           No additional JS state is needed because the server renders the
           correct view and the filter form's hidden[name=view] is excluded
           from our save list intentionally. */
    }

    /* ────────────────────────────────────────
       Public init
    ──────────────────────────────────────── */
    function init() {
        if (READY) { return; }
        READY = true;

        /* Sidebar scroll */
        bindSidebar();

        /* All filter forms with data-page-state-key */
        var forms = document.querySelectorAll('form[data-page-state-key]');
        for (var i = 0; i < forms.length; i++) {
            bindFilterForm(forms[i]);
        }

        /* View-mode toggles */
        bindViewMode();
    }

    /* ────────────────────────────────────────
       Boot
    ──────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* Expose for external use / testing */
    window.DashboardState = {
        init: init,
        bindFilterForm: bindFilterForm,
        bindSidebar: bindSidebar,
        saveSidebarScroll: function () {
            var panel = document.querySelector('.dash-sidebar-panel');
            if (panel) { saveSidebarScroll(panel); }
        },
        clearPageState: function (pageKey) {
            if (window.PageState) { window.PageState.clearPageState(pageKey); }
        }
    };

})(window, document);
