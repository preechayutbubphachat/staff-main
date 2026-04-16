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

    function initApprovalQueue(options) {
        const config = options || {};
        const form = document.getElementById(config.formId || 'bulkApproveForm');
        const resultsContainer = document.getElementById(config.resultsId || 'approvalResultsContainer');
        const summaryContainer = document.getElementById(config.summaryId || 'approvalSummary');
        const filterForm = document.getElementById(config.filterFormId || 'approvalFilterForm');
        const bulkBar = document.getElementById('bulkBar');
        const selectedSummaryText = document.getElementById('selectedSummaryText');
        const openApproveModalBtn = document.getElementById('openApproveModalBtn');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const selectedItemsTableBody = document.getElementById('selectedItemsTableBody');
        const approveModalElement = document.getElementById('approveModal');
        const messageContainer = document.getElementById(config.messageId || 'approvalQueueMessage');

        if (!form || !resultsContainer || !filterForm || !approveModalElement || typeof bootstrap === 'undefined') {
            return;
        }

        const approveModal = bootstrap.Modal.getOrCreateInstance(approveModalElement);
        const interactiveSelector = 'a,button,input,select,textarea,label,[data-bs-toggle],[role="button"],.btn';
        const pageStateKey = config.pageStateKey || filterForm.getAttribute('data-page-state-key') || '';
        const canUsePageState = !!(window.PageState && pageStateKey);
        const loadingApi = window.GlobalLoading || null;
        let selectedIds = new Set();
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
            if (window.TableFilters && typeof window.TableFilters.syncSummaryBlock === 'function') {
                window.TableFilters.syncSummaryBlock(resultsContainer, summaryContainer);
            }
        }

        function getCheckboxes() {
            return Array.from(resultsContainer.querySelectorAll('.row-checkbox'));
        }

        function getSelectableCheckboxes() {
            return getCheckboxes().filter(function (checkbox) {
                return !checkbox.disabled;
            });
        }

        function updateRowHighlight(checkbox) {
            const row = checkbox.closest('[data-select-row]');
            if (row) {
                row.classList.toggle('is-selected', checkbox.checked);
            }
        }

        function updateSelectionUI() {
            const checkboxes = getCheckboxes();
            const selectable = getSelectableCheckboxes();
            const count = selectedIds.size;
            const selectAllToggle = resultsContainer.querySelector('#selectAllTable, #selectAllCards');

            checkboxes.forEach(function (checkbox) {
                const shouldCheck = selectedIds.has(checkbox.value) && !checkbox.disabled;
                checkbox.checked = shouldCheck;
                updateRowHighlight(checkbox);
            });

            if (bulkBar) {
                bulkBar.classList.toggle('visible', count > 0);
            }

            if (selectedSummaryText) {
                selectedSummaryText.textContent = count > 0
                    ? 'เลือกรายการแล้ว ' + count + ' รายการ'
                    : 'เลือกรายการ 0 รายการ';
            }

            if (openApproveModalBtn) {
                openApproveModalBtn.disabled = count === 0 || openApproveModalBtn.hasAttribute('data-signature-required');
            }

            if (selectAllToggle) {
                selectAllToggle.checked = selectable.length > 0 && selectable.every(function (checkbox) {
                    return checkbox.checked;
                });
            }
        }

        function clearSelection() {
            selectedIds = new Set();
            updateSelectionUI();
        }

        function renderSelectedItemsTable(rows) {
            if (!selectedItemsTableBody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                selectedItemsTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการที่เลือก</td></tr>';
                return;
            }

            selectedItemsTableBody.innerHTML = rows.map(function (row, index) {
                return '<tr>'
                    + '<td class="fw-semibold">' + (index + 1) + '</td>'
                    + '<td>' + (row.date || '-') + '</td>'
                    + '<td>' + (row.name || '-') + '</td>'
                    + '<td>' + (row.position_name || '-') + '</td>'
                    + '<td>' + (row.department_name || '-') + '</td>'
                    + '<td>' + (row.time_range || '-') + '</td>'
                    + '</tr>';
            }).join('');
        }

        function setCheckboxSelected(checkbox, checked) {
            if (!checkbox || checkbox.disabled) {
                return;
            }

            if (checked) {
                selectedIds.add(checkbox.value);
            } else {
                selectedIds.delete(checkbox.value);
            }

            checkbox.checked = checked;
            updateRowHighlight(checkbox);
        }

        async function fetchSummary() {
            const csrf = form.querySelector('input[name="_csrf"]');
            const body = new FormData();
            body.append('_csrf', csrf ? csrf.value : '');
            selectedIds.forEach(function (id) {
                body.append('selected_ids[]', id);
            });

            const response = await fetch('../ajax/approval/get_selection_summary.php', {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            return response.json();
        }

        async function refreshResults(queryString, pushState, resetSelection) {
            if (resetSelection !== false) {
                clearSelection();
            }

            resultsContainer.innerHTML = '<div class="ops-loading">กำลังโหลดข้อมูล...</div>';
            resultsContainer.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch('../ajax/approval/list_rows.php?' + queryString, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                resultsContainer.innerHTML = await response.text();
                resultsContainer.removeAttribute('aria-busy');
                syncSummary();
                updateSelectionUI();

                if (window.TableFilters && typeof window.TableFilters.syncExportLinks === 'function') {
                    window.TableFilters.syncExportLinks(filterForm, filterForm.closest('.panel') || document);
                }

                if (pushState) {
                    window.history.replaceState({}, '', 'approval_queue.php?' + queryString);
                }
            } catch (error) {
                resultsContainer.removeAttribute('aria-busy');
                resultsContainer.innerHTML = '<div class="ops-empty">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
                setMessage('ไม่สามารถโหลดข้อมูลคิวตรวจสอบได้ กรุณาลองใหม่อีกครั้ง', 'danger');
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
            refreshResults(collectQuery(resetPage), true, true);
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

        resultsContainer.addEventListener('change', function (event) {
            const target = event.target;

            if (target.matches('.row-checkbox')) {
                setCheckboxSelected(target, target.checked);
                updateSelectionUI();
                return;
            }

            if (target.matches('#selectAllTable, #selectAllCards')) {
                getSelectableCheckboxes().forEach(function (checkbox) {
                    setCheckboxSelected(checkbox, target.checked);
                });
                updateSelectionUI();
            }
        });

        resultsContainer.addEventListener('click', function (event) {
            const selectAllButton = event.target.closest('[data-select-all-visible]');
            if (selectAllButton) {
                event.preventDefault();
                getSelectableCheckboxes().forEach(function (checkbox) {
                    setCheckboxSelected(checkbox, true);
                });
                updateSelectionUI();
                return;
            }

            const pageLink = event.target.closest('[data-approval-page-link], [data-approval-view-link]');
            if (pageLink) {
                event.preventDefault();
                const href = new URL(pageLink.href, window.location.href);
                savePageState();
                refreshResults(href.searchParams.toString(), true, true);
                return;
            }

            const row = event.target.closest('[data-select-row]');
            if (!row || !resultsContainer.contains(row)) {
                return;
            }

            if (event.target.closest(interactiveSelector)) {
                return;
            }

            const checkbox = row.querySelector('.row-checkbox:not([disabled])');
            if (!checkbox) {
                return;
            }

            setCheckboxSelected(checkbox, !checkbox.checked);
            updateSelectionUI();
        });

        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', function () {
                clearSelection();
                renderSelectedItemsTable([]);
            });
        }

        approveModalElement.addEventListener('hidden.bs.modal', function () {
            renderSelectedItemsTable([]);
        });

        if (openApproveModalBtn) {
            openApproveModalBtn.addEventListener('click', async function () {
                if (selectedIds.size === 0) {
                    setMessage('กรุณาเลือกรายการอย่างน้อย 1 รายการ', 'warning');
                    return;
                }

                try {
                    const summary = await fetchSummary();
                    document.getElementById('modalSelectedCount').textContent = summary.count || 0;
                    document.getElementById('modalStaffCount').textContent = summary.staff_count || 0;
                    document.getElementById('modalDepartmentCount').textContent = summary.department_count || 0;
                    renderSelectedItemsTable(summary.rows || []);

                    approveModal.show();
                } catch (error) {
                    setMessage('ไม่สามารถโหลดสรุปรายการที่เลือกได้ กรุณาลองใหม่อีกครั้ง', 'danger');
                }
            });
        }

        const confirmApproveBtn = document.getElementById('confirmApproveBtn');
        if (confirmApproveBtn) {
            confirmApproveBtn.addEventListener('click', async function () {
                if (selectedIds.size === 0) {
                    setMessage('กรุณาเลือกรายการอย่างน้อย 1 รายการ', 'warning');
                    approveModal.hide();
                    return;
                }

                const csrf = form.querySelector('input[name="_csrf"]');
                const body = new FormData();
                body.append('_csrf', csrf ? csrf.value : '');
                selectedIds.forEach(function (id) {
                    body.append('selected_ids[]', id);
                });

                confirmApproveBtn.disabled = true;
                if (openApproveModalBtn) {
                    openApproveModalBtn.disabled = true;
                }

                try {
                    const response = loadingApi && typeof loadingApi.withGlobalLoading === 'function'
                        ? await loadingApi.withGlobalLoading(fetch('../ajax/approval/bulk_approve.php', {
                            method: 'POST',
                            body: body,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }), 'กำลังตรวจสอบข้อมูล...', { trigger: confirmApproveBtn })
                        : await fetch('../ajax/approval/bulk_approve.php', {
                            method: 'POST',
                            body: body,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                    const result = await response.json();
                    const suffix = result.skipped_count ? ' (' + result.skipped_count + ' รายการถูกข้าม)' : '';
                    setMessage((result.message || 'ดำเนินการเรียบร้อยแล้ว') + suffix, result.success ? 'success' : 'warning');
                    approveModal.hide();
                    await refreshResults(collectQuery(false), false, true);
                } catch (error) {
                    if (loadingApi && typeof loadingApi.hideGlobalLoading === 'function') {
                        loadingApi.hideGlobalLoading({ trigger: confirmApproveBtn });
                    }
                    setMessage('ไม่สามารถตรวจสอบรายการที่เลือกได้ กรุณาลองใหม่อีกครั้ง', 'danger');
                } finally {
                    confirmApproveBtn.disabled = false;
                    if (openApproveModalBtn && !openApproveModalBtn.hasAttribute('data-signature-required')) {
                        openApproveModalBtn.disabled = selectedIds.size === 0;
                    }
                }
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
        updateSelectionUI();

        if (restoredStateOnLoad) {
            refreshResults(collectQuery(true), true, true);
        }
    }

    window.ApprovalQueuePage = { init: initApprovalQueue };
})(window, document);
