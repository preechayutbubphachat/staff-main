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

    function initManageTimeLogs(options) {
        const config = options || {};
        const filterForm = document.getElementById(config.filterFormId || 'manageTimeLogsFilterForm');
        const resultsContainer = document.getElementById(config.resultsId || 'manageTimeLogsResults');
        const summaryContainer = document.getElementById(config.summaryId || 'manageTimeLogsSummary');
        const modalElement = document.getElementById(config.modalId || 'manageTimeLogModal');
        const modalContent = document.getElementById(config.modalContentId || 'manageTimeLogModalContent');
        const messageContainer = document.getElementById(config.messageId || 'manageTimeLogsMessage');

        if (!filterForm || !resultsContainer || !modalElement || !modalContent || typeof bootstrap === 'undefined') {
            return;
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        const pageStateKey = config.pageStateKey || filterForm.getAttribute('data-page-state-key') || '';
        const canUsePageState = !!(window.PageState && pageStateKey);
        const loadingApi = window.GlobalLoading || null;
        let restoredStateOnLoad = false;
        let searchTimer = null;

        function setMessage(message, type) {
            if (!messageContainer) {
                return;
            }
            messageContainer.innerHTML = message
                ? '<div class="alert alert-' + type + ' rounded-4 mb-4">' + message + '</div>'
                : '';
        }

        function savePageState() {
            if (canUsePageState) {
                window.PageState.saveFormState(pageStateKey, filterForm);
            }
        }

        function syncSummary() {
            const resultsSummary = resultsContainer
                ? resultsContainer.querySelector('[data-results-summary]')
                : null;

            if (resultsSummary && window.TableFilters && typeof window.TableFilters.syncSummaryBlock === 'function') {
                window.TableFilters.syncSummaryBlock(resultsContainer, summaryContainer);
            }

            if (summaryContainer) {
                const activeSummary = summaryContainer.querySelector('.manage-time-summary-row');
                if (activeSummary) {
                    activeSummary.hidden = false;
                    activeSummary.removeAttribute('aria-hidden');
                    activeSummary.style.removeProperty('display');
                }
            }
        }

        async function refreshResults(queryString, pushState) {
            resultsContainer.innerHTML = '<div class="ops-loading">กำลังโหลดข้อมูล...</div>';

            const response = await fetch('../ajax/manage_time_logs/list_rows.php?' + queryString, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            resultsContainer.innerHTML = await response.text();
            syncSummary();
            bindAsync();

            if (window.TableFilters && typeof window.TableFilters.syncExportLinks === 'function') {
                window.TableFilters.syncExportLinks(filterForm, filterForm.closest('.panel') || document);
            }

            if (pushState) {
                window.history.replaceState({}, '', 'manage_time_logs.php?' + queryString);
            }
        }

        function collectQuery(resetPage) {
            const formData = new FormData(filterForm);
            if (resetPage) {
                formData.set('p', '1');
            }
            return new URLSearchParams(formData).toString();
        }

        function triggerFilterRefresh(resetPage) {
            savePageState();
            refreshResults(collectQuery(resetPage), true);
        }

        function bindFilterAutoRefresh() {
            getScopedFields(filterForm, 'select, input[type="date"], input[type="month"], input[type="number"]').forEach(function (field) {
                field.addEventListener('change', function () {
                    triggerFilterRefresh(true);
                });
            });

            getScopedFields(filterForm, 'input[type="text"], input[type="search"]').forEach(function (field) {
                field.addEventListener('input', function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(function () {
                        triggerFilterRefresh(true);
                    }, 350);
                });

                field.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        window.clearTimeout(searchTimer);
                        triggerFilterRefresh(true);
                    }
                });
            });
        }

        async function openEdit(id) {
            modalContent.innerHTML = '<div class="text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>';
            modalInstance.show();

            const response = await fetch('../ajax/manage_time_logs/get_row.php?id=' + encodeURIComponent(id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            modalContent.innerHTML = await response.text();
            const form = modalContent.querySelector('[data-manage-edit-form]');

            if (!form) {
                return;
            }

            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const submitter = event.submitter || form.querySelector('button[type="submit"]');
                if (submitter) {
                    submitter.disabled = true;
                }

                try {
                    const submitResponse = loadingApi && typeof loadingApi.withGlobalLoading === 'function'
                        ? await loadingApi.withGlobalLoading(fetch('../ajax/manage_time_logs/update_row.php', {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }), 'กำลังบันทึกข้อมูล...', { trigger: submitter })
                        : await fetch('../ajax/manage_time_logs/update_row.php', {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                    const result = await submitResponse.json();

                    if (result.success) {
                        modalInstance.hide();
                        setMessage(result.message, 'success');
                        await refreshResults(collectQuery(false), false);
                    } else {
                        if (loadingApi && typeof loadingApi.hideGlobalLoading === 'function') {
                            loadingApi.hideGlobalLoading({ trigger: submitter });
                        }
                        setMessage(result.message || 'ไม่สามารถบันทึกข้อมูลได้', 'danger');
                    }
                } catch (error) {
                    if (loadingApi && typeof loadingApi.hideGlobalLoading === 'function') {
                        loadingApi.hideGlobalLoading({ trigger: submitter });
                    }
                    setMessage('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง', 'danger');
                } finally {
                    if (submitter) {
                        submitter.disabled = false;
                    }
                }
            });
        }

        function bindAsync() {
            resultsContainer.querySelectorAll('[data-manage-page-link]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    const href = new URL(link.href, window.location.href);
                    savePageState();
                    refreshResults(href.searchParams.toString(), true);
                });
            });

            resultsContainer.querySelectorAll('[data-manage-edit-link]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    openEdit(link.getAttribute('data-id'));
                });
            });
        }

        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            triggerFilterRefresh(true);
        });

        if (canUsePageState) {
            const restoreResult = window.PageState.restoreFormState({
                pageKey: pageStateKey,
                form: filterForm
            });
            restoredStateOnLoad = !!restoreResult.restored;

            if (!restoredStateOnLoad) {
                savePageState();
            }
        }

        bindFilterAutoRefresh();
        if (window.TableFilters && typeof window.TableFilters.syncExportLinks === 'function') {
            window.TableFilters.syncExportLinks(filterForm, filterForm.closest('.panel') || document);
        }
        syncSummary();
        bindAsync();

        if (restoredStateOnLoad) {
            refreshResults(collectQuery(true), true);
        }
    }

    window.ManageTimeLogsPage = { init: initManageTimeLogs };
})(window, document);
