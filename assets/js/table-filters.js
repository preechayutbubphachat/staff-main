(function (window, document) {
    function getScopedFields(form, selector) {
        if (!form) {
            return [];
        }

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

    function debounce(callback, delay) {
        let timer = null;
        return function () {
            const context = this;
            const args = arguments;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                callback.apply(context, args);
            }, delay);
        };
    }

    function syncExportLinksFor(form, scope) {
        if (!form || !scope) {
            return;
        }

        const params = new URLSearchParams(new FormData(form));
        params.delete('p');
        params.delete('page');

        const exportLinks = scope.querySelectorAll('[data-export-base], a[href*="report_print.php"], a[href*="export_report.php"]');
        exportLinks.forEach(function (link) {
            const exportParams = new URLSearchParams(params.toString());
            const currentHref = link.getAttribute('href') || '';
            const resolvedUrl = new URL(currentHref, window.location.href);
            const type = link.getAttribute('data-export-type') || resolvedUrl.searchParams.get('type');
            const download = link.getAttribute('data-export-download') || resolvedUrl.searchParams.get('download');
            const baseHref = link.getAttribute('data-export-base') || resolvedUrl.pathname.split('/').pop();

            if (type) {
                exportParams.set('type', type);
            }
            if (download) {
                exportParams.set('download', download);
            } else {
                exportParams.delete('download');
            }

            link.setAttribute('href', baseHref + '?' + exportParams.toString());
        });
    }

    function initTableFilters(options) {
        const config = options || {};
        const form = document.getElementById(config.formId);
        const container = document.getElementById(config.containerId);
        const endpoint = config.endpoint;
        const pushBase = config.pushBase || '';
        const scope = config.scopeSelector ? document.querySelector(config.scopeSelector) : (form ? (form.closest('.panel') || document) : document);
        const pageStateKey = config.pageStateKey || (form ? form.getAttribute('data-page-state-key') || '' : '');

        if (!form || !container || !endpoint) {
            return;
        }

        const canUsePageState = !!(window.PageState && pageStateKey);
        let restoredStateOnLoad = false;

        function syncExportLinks() {
            syncExportLinksFor(form, scope);
        }

        function savePageState() {
            if (canUsePageState) {
                window.PageState.saveFormState(pageStateKey, form);
            }
        }

        async function refresh(pushState) {
            const queryString = new URLSearchParams(new FormData(form)).toString();
            container.setAttribute('aria-busy', 'true');
            container.innerHTML = '<div class="ops-loading">กำลังโหลดข้อมูล...</div>';

            const response = await fetch(endpoint + '?' + queryString, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            container.innerHTML = await response.text();
            container.removeAttribute('aria-busy');
            bindPagination();
            syncExportLinks();

            if (typeof config.onRefresh === 'function') {
                config.onRefresh({
                    form: form,
                    container: container,
                    queryString: queryString
                });
            }

            if (pushState) {
                window.history.replaceState({}, '', pushBase + '?' + queryString);
            }
        }

        function setPageValue(value) {
            const pageInput = form.querySelector('[name="p"], [name="page"]');
            if (pageInput) {
                pageInput.value = String(value);
            }
        }

        function resetPage() {
            setPageValue(1);
        }

        function bindPagination() {
            container.querySelectorAll('[data-table-page-link], [data-table-view-link]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    const href = new URL(link.href, window.location.href);
                    const params = href.searchParams;

                    for (const pair of params.entries()) {
                        const field = form.querySelector('[name="' + pair[0] + '"]');
                        if (field) {
                            field.value = pair[1];
                        }
                    }

                    savePageState();
                    refresh(true);
                });
            });
        }

        const debouncedRefresh = debounce(function () {
            resetPage();
            savePageState();
            refresh(true);
        }, config.debounceMs || 380);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            resetPage();
            savePageState();
            refresh(true);
        });

        getScopedFields(form, 'select, input[type="date"], input[type="month"], input[type="number"]').forEach(function (field) {
            field.addEventListener('change', function () {
                resetPage();
                savePageState();
                refresh(true);
            });
            field.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    resetPage();
                    savePageState();
                    refresh(true);
                }
            });
        });

        getScopedFields(form, 'input[type="text"], input[type="search"]').forEach(function (field) {
            field.addEventListener('input', debouncedRefresh);
            field.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    resetPage();
                    savePageState();
                    refresh(true);
                }
            });
        });

        if (canUsePageState) {
            const restoreResult = window.PageState.restoreFormState({
                pageKey: pageStateKey,
                form: form
            });
            restoredStateOnLoad = !!restoreResult.restored;
            if (!restoredStateOnLoad) {
                savePageState();
            }
        }

        bindPagination();
        syncExportLinks();

        if (restoredStateOnLoad) {
            refresh(true);
        }
    }

    function syncSummaryBlock(containerOrId, mountOrId) {
        const container = typeof containerOrId === 'string'
            ? document.getElementById(containerOrId)
            : containerOrId;
        const mount = typeof mountOrId === 'string'
            ? document.getElementById(mountOrId)
            : mountOrId;

        if (!container || !mount) {
            return;
        }

        const summaryBlock = container.querySelector('[data-results-summary]');
        mount.innerHTML = '';

        if (summaryBlock) {
            mount.appendChild(summaryBlock);
        }
    }

    window.TableFilters = {
        init: initTableFilters,
        syncExportLinks: syncExportLinksFor,
        syncSummaryBlock: syncSummaryBlock
    };
})(window, document);
