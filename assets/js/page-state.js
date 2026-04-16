(function (window, document) {
    const STORAGE_PREFIX = 'staff_main';
    let contextCache = null;

    function getStorage() {
        try {
            return window.localStorage;
        } catch (error) {
            return null;
        }
    }

    function setContext(context) {
        contextCache = {
            userId: Number(context && context.userId ? context.userId : 0),
            loginMarker: String(context && context.loginMarker ? context.loginMarker : '')
        };
        ensureCurrentMarker();
    }

    function getContext() {
        if (contextCache) {
            return contextCache;
        }

        const navbar = document.querySelector('.app-navbar[data-ui-user-id]');
        if (!navbar) {
            return { userId: 0, loginMarker: '' };
        }

        contextCache = {
            userId: Number(navbar.getAttribute('data-ui-user-id') || 0),
            loginMarker: String(navbar.getAttribute('data-ui-login-marker') || '')
        };

        return contextCache;
    }

    function getMarkerKey(userId) {
        return STORAGE_PREFIX + ':' + userId + ':login_marker';
    }

    function getUserPrefix(userId) {
        return STORAGE_PREFIX + ':' + userId + ':';
    }

    function ensureCurrentMarker() {
        const storage = getStorage();
        const context = getContext();
        if (!storage || !context.userId || !context.loginMarker) {
            return;
        }

        const markerKey = getMarkerKey(context.userId);
        const storedMarker = storage.getItem(markerKey);
        if (storedMarker === context.loginMarker) {
            return;
        }

        const prefix = getUserPrefix(context.userId);
        for (let i = storage.length - 1; i >= 0; i -= 1) {
            const key = storage.key(i);
            if (key && key.indexOf(prefix) === 0) {
                storage.removeItem(key);
            }
        }

        storage.setItem(markerKey, context.loginMarker);
    }

    function getScopedStateKey(pageKey) {
        const context = getContext();
        if (!context.userId || !pageKey) {
            return '';
        }
        return STORAGE_PREFIX + ':' + context.userId + ':' + pageKey + ':state';
    }

    function loadPageState(pageKey) {
        const storage = getStorage();
        const key = getScopedStateKey(pageKey);
        if (!storage || !key) {
            return null;
        }

        ensureCurrentMarker();

        try {
            const raw = storage.getItem(key);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            storage.removeItem(key);
            return null;
        }
    }

    function savePageState(pageKey, state) {
        const storage = getStorage();
        const key = getScopedStateKey(pageKey);
        if (!storage || !key) {
            return;
        }

        ensureCurrentMarker();

        try {
            storage.setItem(key, JSON.stringify(state || {}));
        } catch (error) {
            // ignore storage errors
        }
    }

    function clearPageState(pageKey) {
        const storage = getStorage();
        const key = getScopedStateKey(pageKey);
        if (!storage || !key) {
            return;
        }
        storage.removeItem(key);
    }

    function clearAllPageStateForUser(userId) {
        const storage = getStorage();
        if (!storage || !userId) {
            return;
        }

        const prefix = getUserPrefix(userId);
        for (let i = storage.length - 1; i >= 0; i -= 1) {
            const key = storage.key(i);
            if (key && key.indexOf(prefix) === 0) {
                storage.removeItem(key);
            }
        }
    }

    function getScopedFields(form) {
        if (!form) {
            return [];
        }

        const selector = 'input[name], select[name], textarea[name]';
        const scoped = Array.from(form.querySelectorAll(selector));
        const external = form.id
            ? Array.from(document.querySelectorAll(selector + '[form="' + form.id + '"]'))
            : [];
        const seen = new Set();

        return scoped.concat(external).filter(function (field) {
            if (seen.has(field)) {
                return false;
            }
            seen.add(field);
            return true;
        });
    }

    function shouldSkipField(field) {
        const type = (field.type || '').toLowerCase();
        return ['submit', 'button', 'reset', 'file', 'password'].indexOf(type) !== -1;
    }

    function captureFormState(form) {
        const state = {};

        getScopedFields(form).forEach(function (field) {
            if (!field.name || field.disabled || shouldSkipField(field)) {
                return;
            }

            if (field.name === 'p' || field.name === 'page') {
                return;
            }

            const type = (field.type || '').toLowerCase();
            if (type === 'checkbox') {
                state[field.name] = field.checked ? field.value || '1' : '';
                return;
            }

            if (type === 'radio') {
                if (field.checked) {
                    state[field.name] = field.value;
                }
                return;
            }

            state[field.name] = field.value;
        });

        return state;
    }

    function formHasUrlState(form) {
        const params = new URLSearchParams(window.location.search);
        return getScopedFields(form).some(function (field) {
            if (!field.name || field.name === 'p' || field.name === 'page') {
                return false;
            }
            return params.has(field.name) && params.get(field.name) !== '';
        });
    }

    function applyFormState(form, state) {
        if (!form || !state || typeof state !== 'object') {
            return false;
        }

        let applied = false;
        getScopedFields(form).forEach(function (field) {
            if (!field.name || !Object.prototype.hasOwnProperty.call(state, field.name)) {
                return;
            }

            const value = state[field.name];
            const type = (field.type || '').toLowerCase();

            if (type === 'checkbox') {
                field.checked = String(value) === String(field.value || '1');
                applied = true;
                return;
            }

            if (type === 'radio') {
                field.checked = String(field.value) === String(value);
                applied = true;
                return;
            }

            field.value = value;
            applied = true;
        });

        const pageField = form.querySelector('[name="p"], [name="page"]');
        if (pageField) {
            pageField.value = '1';
        }

        return applied;
    }

    function restoreFormState(options) {
        const pageKey = options && options.pageKey ? options.pageKey : '';
        const form = options && options.form ? options.form : null;
        if (!pageKey || !form) {
            return { restored: false, state: null };
        }

        const state = loadPageState(pageKey);
        if (!state || formHasUrlState(form)) {
            return { restored: false, state: state };
        }

        return {
            restored: applyFormState(form, state),
            state: state
        };
    }

    function saveFormState(pageKey, form) {
        if (!pageKey || !form) {
            return;
        }
        savePageState(pageKey, captureFormState(form));
    }

    window.PageState = {
        setContext: setContext,
        loadPageState: loadPageState,
        savePageState: savePageState,
        clearPageState: clearPageState,
        clearAllPageStateForUser: clearAllPageStateForUser,
        getScopedStateKey: getScopedStateKey,
        captureFormState: captureFormState,
        restoreFormState: restoreFormState,
        saveFormState: saveFormState
    };
})(window, document);
