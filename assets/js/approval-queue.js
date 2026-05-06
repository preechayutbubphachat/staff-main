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
        const summaryContainer = document.getElementById(config.summaryId || 'approvalSummaryDynamic');
        const filterForm = document.getElementById(config.filterFormId || 'approvalFilterForm');
        const messageContainer = document.getElementById(config.messageId || 'approvalQueueMessage');

        // ── Toolbar elements (static — live outside AJAX container) ──
        const selectedSummaryBadge = document.getElementById('selectedSummaryBadge');
        const selectedSummaryText = document.getElementById('selectedSummaryText');
        const openApproveModalBtn = document.getElementById('openApproveModalBtn');
        const bulkRejectBtn = document.getElementById('bulkRejectBtn');
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        const exportCsvBtn = document.getElementById('exportCsvBtn');
        const printReportBtn = document.getElementById('printReportBtn');
        // clearSelectionBtn lives inside the AJAX container — re-query each time

        // ── Modal elements ──
        const selectedItemsTableBody = document.getElementById('selectedItemsTableBody');
        const approveModalElement = document.getElementById('approveModal');
        const shiftDetailModalElement = document.getElementById('shiftReviewDetailModal');
        const shiftDetailLoading = document.getElementById('shiftReviewDetailLoading');
        const shiftDetailContent = document.getElementById('shiftReviewDetailContent');
        const shiftDetailError = document.getElementById('shiftReviewDetailError');
        const shiftDetailApproveBtn = document.getElementById('shiftDetailApproveBtn');
        const shiftDetailRejectBtn = document.getElementById('shiftDetailRejectBtn');
        const shiftDetailRoot = document.getElementById('shiftReviewDetailRoot');
        const shiftDetailAvatarImg = document.getElementById('shiftDetailAvatarImg');
        const shiftDetailAvatarIcon = document.getElementById('shiftDetailAvatarIcon');
        const shiftRejectPanel = document.getElementById('shiftRejectPanel');
        const shiftRejectReason = document.getElementById('shiftRejectReason');
        const shiftRejectCancelBtn = document.getElementById('shiftRejectCancelBtn');
        const shiftRejectConfirmBtn = document.getElementById('shiftRejectConfirmBtn');

        if (!form || !resultsContainer || !filterForm || !approveModalElement || typeof bootstrap === 'undefined') {
            return;
        }

        const approveModal = bootstrap.Modal.getOrCreateInstance(approveModalElement);
        const shiftDetailModal = shiftDetailModalElement ? bootstrap.Modal.getOrCreateInstance(shiftDetailModalElement) : null;
        const interactiveSelector = 'a,button,input,select,textarea,label,[data-bs-toggle],[role="button"],.btn';
        const pageStateKey = config.pageStateKey || filterForm.getAttribute('data-page-state-key') || '';
        const canUsePageState = !!(window.PageState && pageStateKey);
        const loadingApi = window.GlobalLoading || null;
        let selectedIds = new Set();
        let activeDetailLogId = null;
        let restoredStateOnLoad = false;
        let searchTimer = null;

        // ─────────────────────────────────────────
        // Helpers
        // ─────────────────────────────────────────

        function setMessage(message, type) {
            if (!messageContainer) return;
            messageContainer.innerHTML = message
                ? '<div class="alert alert-' + type + ' rounded-4 mb-4">' + message + '</div>'
                : '';
        }

        function setText(id, value) {
            const node = document.getElementById(id);
            if (node) node.textContent = value || '-';
        }

        function escapeHtml(value) {
            return String(value || '-')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setShiftAvatar(url) {
            if (!shiftDetailAvatarImg || !shiftDetailAvatarIcon) return;
            const safeUrl = typeof url === 'string' ? url.trim() : '';
            if (!safeUrl) {
                shiftDetailAvatarImg.classList.add('d-none');
                shiftDetailAvatarImg.removeAttribute('src');
                shiftDetailAvatarIcon.classList.remove('d-none');
                return;
            }
            shiftDetailAvatarImg.onerror = function () {
                shiftDetailAvatarImg.classList.add('d-none');
                shiftDetailAvatarImg.removeAttribute('src');
                shiftDetailAvatarIcon.classList.remove('d-none');
            };
            shiftDetailAvatarImg.onload = function () {
                shiftDetailAvatarIcon.classList.add('d-none');
                shiftDetailAvatarImg.classList.remove('d-none');
            };
            shiftDetailAvatarImg.src = safeUrl;
        }

        function resetRejectPanel() {
            if (shiftRejectPanel) shiftRejectPanel.classList.add('d-none');
            if (shiftRejectReason) shiftRejectReason.value = '';
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
            return getCheckboxes().filter(function (cb) { return !cb.disabled; });
        }

        function updateRowHighlight(checkbox) {
            const row = checkbox.closest('[data-select-row]');
            if (row) {
                row.classList.toggle('is-selected', checkbox.checked);
            }
        }

        // ─────────────────────────────────────────
        // Toolbar state — enable / disable all bulk buttons
        // ─────────────────────────────────────────

        function setAnchorDisabled(el, disabled) {
            if (!el) return;
            if (disabled) {
                el.setAttribute('data-disabled', 'true');
                el.setAttribute('tabindex', '-1');
                el.setAttribute('aria-disabled', 'true');
            } else {
                el.removeAttribute('data-disabled');
                el.removeAttribute('tabindex');
                el.removeAttribute('aria-disabled');
            }
        }

        function updateSelectionUI() {
            const checkboxes = getCheckboxes();
            const selectable = getSelectableCheckboxes();
            const count = selectedIds.size;
            const hasSelection = count > 0;
            const selectAllToggle = resultsContainer.querySelector('#selectAllTable, #selectAllCards');

            // Sync checkbox visual state
            checkboxes.forEach(function (checkbox) {
                const shouldCheck = selectedIds.has(checkbox.value) && !checkbox.disabled;
                checkbox.checked = shouldCheck;
                updateRowHighlight(checkbox);
            });

            // Badge: class + text
            if (selectedSummaryBadge) {
                selectedSummaryBadge.classList.toggle('is-empty', !hasSelection);
                selectedSummaryBadge.classList.toggle('has-selection', hasSelection);
                // Swap icon
                const icon = selectedSummaryBadge.querySelector('i');
                if (icon) {
                    icon.className = hasSelection ? 'bi bi-check2-square' : 'bi bi-square';
                }
            }
            if (selectedSummaryText) {
                selectedSummaryText.textContent = hasSelection
                    ? 'เลือกแล้ว ' + count + ' รายการ'
                    : 'ยังไม่ได้เลือกรายการ';
            }

            // Approve button (also guarded by signature requirement)
            if (openApproveModalBtn) {
                openApproveModalBtn.disabled =
                    !hasSelection || openApproveModalBtn.hasAttribute('data-signature-required');
            }

            // Reject button (disabled — workflow not yet open; still reflect selection state visually)
            if (bulkRejectBtn) {
                bulkRejectBtn.disabled = !hasSelection;
            }

            // Clear selection button (inside AJAX container — re-query each call)
            const clearBtn = resultsContainer.querySelector('#clearSelectionBtn');
            if (clearBtn) {
                clearBtn.disabled = !hasSelection;
            }

            // Anchor buttons: PDF / CSV / Print — always active (not gated by selection)
            setAnchorDisabled(exportPdfBtn, false);
            setAnchorDisabled(exportCsvBtn, false);
            setAnchorDisabled(printReportBtn, false);

            // Select-all toggle state
            if (selectAllToggle) {
                selectAllToggle.checked =
                    selectable.length > 0 &&
                    selectable.every(function (cb) { return cb.checked; });
            }
        }

        function clearSelection() {
            selectedIds = new Set();
            updateSelectionUI();
        }

        // ─────────────────────────────────────────
        // Modal helpers
        // ─────────────────────────────────────────

        function renderSelectedItemsTable(rows) {
            if (!selectedItemsTableBody) return;

            if (!Array.isArray(rows) || rows.length === 0) {
                selectedItemsTableBody.innerHTML =
                    '<tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการที่เลือก</td></tr>';
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

        function setShiftDetailState(state, message) {
            if (!shiftDetailLoading || !shiftDetailContent || !shiftDetailError) return;
            shiftDetailLoading.classList.toggle('d-none', state !== 'loading');
            shiftDetailContent.classList.toggle('d-none', state !== 'ready');
            shiftDetailError.classList.toggle('d-none', state !== 'error');
            shiftDetailError.textContent = state === 'error' ? (message || 'ไม่สามารถโหลดข้อมูลรายการลงเวลาเวรได้') : '';
        }

        function renderShiftDetail(payload) {
            const record = payload && payload.record ? payload.record : {};
            activeDetailLogId = record.id ? String(record.id) : null;

            if (shiftDetailRoot) {
                shiftDetailRoot.setAttribute('data-time-log-id', activeDetailLogId || '');
                shiftDetailRoot.setAttribute('data-current-status', record.status_label || '');
            }
            resetRejectPanel();
            setShiftAvatar(record.profile_image_url || '');

            setText('shiftDetailFullname', record.fullname);
            setText('shiftDetailPosition', record.position_name);
            setText('shiftDetailDepartment', record.department_name);
            setText('shiftDetailRecordId', record.id ? '#' + record.id : '-');
            setText('shiftDetailRawId', record.id ? record.id : '-');
            setText('shiftDetailWorkDate', record.work_date);
            setText('shiftDetailTimeIn', record.time_in);
            setText('shiftDetailTimeOut', record.time_out);
            setText('shiftDetailHours', record.work_hours);
            setText('shiftDetailType', record.shift_type);
            setText('shiftDetailWorkDepartment', record.department_name);
            setText('shiftDetailStatusText', record.status_label);
            setText('shiftDetailChecker', record.checker_name);
            setText('shiftDetailCreatedAt', record.created_at);
            setText('shiftDetailUpdatedAt', record.updated_at);
            setText('shiftDetailNote', record.note);
            setText('shiftDetailApprovalNote', record.approval_note);

            const statusChip = document.getElementById('shiftDetailStatus');
            if (statusChip) {
                statusChip.className = 'status-chip ' + (record.status_class || 'warning');
                statusChip.textContent = record.status_label || '-';
            }

            if (shiftDetailApproveBtn) {
                shiftDetailApproveBtn.disabled = !record.can_review || !activeDetailLogId || (openApproveModalBtn && openApproveModalBtn.hasAttribute('data-signature-required'));
                shiftDetailApproveBtn.title = record.can_review ? '' : 'รายการนี้ไม่อยู่ในสถานะรอตรวจ';
            }

            if (shiftDetailRejectBtn) {
                shiftDetailRejectBtn.disabled = !record.can_review || !activeDetailLogId;
                shiftDetailRejectBtn.title = record.can_review ? '' : 'รายการนี้ไม่อยู่ในสถานะรอตรวจ';
            }

            if (shiftRejectConfirmBtn) {
                shiftRejectConfirmBtn.disabled = false;
            }

            const auditList = document.getElementById('shiftDetailAuditList');
            const audits = Array.isArray(payload.audit) ? payload.audit : [];
            if (auditList) {
                auditList.innerHTML = audits.length
                    ? audits.map(function (item) {
                        return '<div class="shift-review-audit-item">'
                            + '<strong>' + escapeHtml(item.action_type) + '</strong>'
                            + '<span>' + escapeHtml(item.created_at) + ' โดย ' + escapeHtml(item.actor_name) + '</span>'
                            + '<small>' + escapeHtml(item.note) + '</small>'
                            + '</div>';
                    }).join('')
                    : '<div class="shift-review-audit-empty">-</div>';
            }
        }

        async function openShiftReviewDetail(timeLogId) {
            if (!shiftDetailModal || !timeLogId) return;

            activeDetailLogId = String(timeLogId);
            resetRejectPanel();
            setShiftAvatar('');
            setShiftDetailState('loading');
            shiftDetailModal.show();

            try {
                const response = await fetch('../ajax/approval/get_time_log_detail.php?id=' + encodeURIComponent(timeLogId), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'ไม่สามารถโหลดข้อมูลรายการลงเวลาเวรได้');
                }
                renderShiftDetail(payload);
                setShiftDetailState('ready');
            } catch (error) {
                setShiftDetailState('error', error.message);
            }
        }

        // ─────────────────────────────────────────
        // Checkbox logic
        // ─────────────────────────────────────────

        function setCheckboxSelected(checkbox, checked) {
            if (!checkbox || checkbox.disabled) return;
            if (checked) {
                selectedIds.add(checkbox.value);
            } else {
                selectedIds.delete(checkbox.value);
            }
            checkbox.checked = checked;
            updateRowHighlight(checkbox);
        }

        // ─────────────────────────────────────────
        // API calls
        // ─────────────────────────────────────────

        async function fetchSummary() {
            const csrf = form.querySelector('input[name="_csrf"]');
            const body = new FormData();
            body.append('_csrf', csrf ? csrf.value : '');
            selectedIds.forEach(function (id) { body.append('selected_ids[]', id); });

            const response = await fetch('../ajax/approval/get_selection_summary.php', {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            return response.json();
        }

        async function openApproveModalForSelection() {
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
        }

        // ─────────────────────────────────────────
        // AJAX refresh
        // ─────────────────────────────────────────

        function syncExportLinks() {
            if (window.TableFilters && typeof window.TableFilters.syncExportLinks === 'function') {
                // Pass `document` so it updates toolbar anchors too
                window.TableFilters.syncExportLinks(filterForm, document);
            }
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
                syncExportLinks();
                updateSelectionUI();

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
            if (resetPage) formData.set('p', '1');
            return new URLSearchParams(formData).toString();
        }

        function triggerFilterRefresh(resetPage) {
            savePageState();
            refreshResults(collectQuery(resetPage), true, true);
        }

        // ─────────────────────────────────────────
        // Filter auto-refresh
        // ─────────────────────────────────────────

        function bindFilterAutoRefresh() {
            getScopedFields(filterForm, 'select, input[type="date"], input[type="month"], input[type="number"]').forEach(function (field) {
                field.addEventListener('change', function () { triggerFilterRefresh(true); });
            });

            getScopedFields(filterForm, 'input[type="text"], input[type="search"]').forEach(function (field) {
                field.addEventListener('input', function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(function () { triggerFilterRefresh(true); }, 350);
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

        // ─────────────────────────────────────────
        // Results container event delegation
        // ─────────────────────────────────────────

        resultsContainer.addEventListener('change', function (event) {
            const target = event.target;

            if (target.matches('.row-checkbox')) {
                setCheckboxSelected(target, target.checked);
                updateSelectionUI();
                return;
            }

            if (target.matches('#selectAllTable, #selectAllCards')) {
                getSelectableCheckboxes().forEach(function (cb) {
                    setCheckboxSelected(cb, target.checked);
                });
                updateSelectionUI();
            }
        });

        resultsContainer.addEventListener('click', function (event) {
            const selectAllButton = event.target.closest('[data-select-all-visible]');
            if (selectAllButton) {
                event.preventDefault();
                getSelectableCheckboxes().forEach(function (cb) { setCheckboxSelected(cb, true); });
                updateSelectionUI();
                return;
            }

            if (event.target.closest('#clearSelectionBtn')) {
                clearSelection();
                renderSelectedItemsTable([]);
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

            const detailButton = event.target.closest('[data-shift-review-detail]');
            if (detailButton) {
                event.preventDefault();
                openShiftReviewDetail(detailButton.getAttribute('data-time-log-id'));
                return;
            }

            const row = event.target.closest('[data-select-row]');
            if (!row || !resultsContainer.contains(row)) return;

            const approveSingleButton = event.target.closest('[data-approve-single]');
            if (approveSingleButton) {
                event.preventDefault();
                const checkbox = row.querySelector('.row-checkbox:not([disabled])');
                if (!checkbox) return;
                clearSelection();
                setCheckboxSelected(checkbox, true);
                updateSelectionUI();
                openApproveModalForSelection();
                return;
            }

            if (event.target.closest(interactiveSelector)) return;

            const checkbox = row.querySelector('.row-checkbox:not([disabled])');
            if (!checkbox) return;
            setCheckboxSelected(checkbox, !checkbox.checked);
            updateSelectionUI();
        });

        // ─────────────────────────────────────────
        // Toolbar button listeners
        // ─────────────────────────────────────────

        if (openApproveModalBtn) {
            openApproveModalBtn.addEventListener('click', function () {
                openApproveModalForSelection();
            });
        }

        // bulkRejectBtn: workflow not open yet — show informational message
        if (bulkRejectBtn) {
            bulkRejectBtn.addEventListener('click', function () {
                setMessage('ฟีเจอร์ตีกลับแบบกลุ่มยังไม่เปิดใช้งานในรุ่นนี้', 'warning');
            });
        }

        if (shiftDetailRejectBtn) {
            shiftDetailRejectBtn.addEventListener('click', function () {
                if (!activeDetailLogId || shiftDetailRejectBtn.disabled) {
                    setMessage('รายการนี้ไม่อยู่ในสถานะที่ตีกลับได้', 'warning');
                    return;
                }
                if (shiftRejectPanel) shiftRejectPanel.classList.remove('d-none');
                if (shiftRejectReason) shiftRejectReason.focus();
            });
        }

        if (shiftRejectCancelBtn) {
            shiftRejectCancelBtn.addEventListener('click', resetRejectPanel);
        }

        if (shiftRejectConfirmBtn) {
            shiftRejectConfirmBtn.addEventListener('click', async function () {
                if (!activeDetailLogId) {
                    setMessage('ไม่พบรหัสรายการลงเวลาเวรที่ต้องการตีกลับ', 'warning');
                    return;
                }
                const reason = shiftRejectReason ? shiftRejectReason.value.trim() : '';
                if (!reason) {
                    setMessage('กรุณาระบุเหตุผลการตีกลับ/ไม่อนุมัติ', 'warning');
                    if (shiftRejectReason) shiftRejectReason.focus();
                    return;
                }
                if (!window.confirm('ยืนยันตีกลับ/ไม่อนุมัติรายการลงเวลาเวรนี้?')) {
                    return;
                }

                const csrf = form.querySelector('input[name="_csrf"]');
                const body = new FormData();
                body.append('_csrf', csrf ? csrf.value : '');
                body.append('id', activeDetailLogId);
                body.append('reason', reason);

                shiftRejectConfirmBtn.disabled = true;
                if (shiftDetailRejectBtn) shiftDetailRejectBtn.disabled = true;

                try {
                    const response = loadingApi && typeof loadingApi.withGlobalLoading === 'function'
                        ? await loadingApi.withGlobalLoading(fetch('../ajax/approval/reject_time_log.php', {
                            method: 'POST', body: body,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }), 'กำลังตีกลับรายการ...', { trigger: shiftRejectConfirmBtn })
                        : await fetch('../ajax/approval/reject_time_log.php', {
                            method: 'POST', body: body,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'ไม่สามารถตีกลับรายการได้');
                    }
                    setMessage(result.message || 'ตีกลับรายการเรียบร้อยแล้ว', 'success');
                    if (shiftDetailModal) shiftDetailModal.hide();
                    await refreshResults(collectQuery(false), false, true);
                } catch (error) {
                    setMessage(error.message || 'ไม่สามารถตีกลับรายการได้ กรุณาลองใหม่อีกครั้ง', 'danger');
                    shiftRejectConfirmBtn.disabled = false;
                    if (shiftDetailRejectBtn) shiftDetailRejectBtn.disabled = false;
                }
            });
        }

        if (shiftDetailApproveBtn) {
            shiftDetailApproveBtn.addEventListener('click', async function () {
                if (!activeDetailLogId) {
                    setMessage('ไม่พบรหัสรายการลงเวลาเวรที่ต้องการอนุมัติ', 'warning');
                    return;
                }
                if (!window.confirm('ยืนยันอนุมัติรายการลงเวลาเวรนี้?')) {
                    return;
                }

                const csrf = form.querySelector('input[name="_csrf"]');
                const body = new FormData();
                body.append('_csrf', csrf ? csrf.value : '');
                body.append('selected_ids[]', activeDetailLogId);
                shiftDetailApproveBtn.disabled = true;

                try {
                    const response = loadingApi && typeof loadingApi.withGlobalLoading === 'function'
                        ? await loadingApi.withGlobalLoading(fetch('../ajax/approval/bulk_approve.php', {
                            method: 'POST', body: body,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }), 'กำลังอนุมัติรายการ...', { trigger: shiftDetailApproveBtn })
                        : await fetch('../ajax/approval/bulk_approve.php', {
                            method: 'POST', body: body,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'ไม่สามารถอนุมัติรายการได้');
                    }
                    setMessage(result.message || 'อนุมัติรายการเรียบร้อยแล้ว', 'success');
                    if (shiftDetailModal) shiftDetailModal.hide();
                    await refreshResults(collectQuery(false), false, true);
                } catch (error) {
                    setMessage(error.message || 'ไม่สามารถอนุมัติรายการได้ กรุณาลองใหม่อีกครั้ง', 'danger');
                    shiftDetailApproveBtn.disabled = false;
                }
            });
        }

        // Intercept disabled anchor clicks (belt-and-suspenders safety)
        [exportPdfBtn, exportCsvBtn, printReportBtn].forEach(function (btn) {
            if (!btn) return;
            btn.addEventListener('click', function (event) {
                if (btn.getAttribute('data-disabled') === 'true') {
                    event.preventDefault();
                }
            });
        });

        approveModalElement.addEventListener('hidden.bs.modal', function () {
            renderSelectedItemsTable([]);
        });

        // ─────────────────────────────────────────
        // Confirm approve (modal submit)
        // ─────────────────────────────────────────

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
                selectedIds.forEach(function (id) { body.append('selected_ids[]', id); });

                confirmApproveBtn.disabled = true;
                if (openApproveModalBtn) openApproveModalBtn.disabled = true;

                try {
                    const response = loadingApi && typeof loadingApi.withGlobalLoading === 'function'
                        ? await loadingApi.withGlobalLoading(fetch('../ajax/approval/bulk_approve.php', {
                            method: 'POST', body: body,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }), 'กำลังตรวจสอบข้อมูล...', { trigger: confirmApproveBtn })
                        : await fetch('../ajax/approval/bulk_approve.php', {
                            method: 'POST', body: body,
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
                    // Re-evaluate approve button state (may still be disabled by signature requirement)
                    updateSelectionUI();
                }
            });
        }

        // ─────────────────────────────────────────
        // Filter form submit
        // ─────────────────────────────────────────

        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            triggerFilterRefresh(true);
        });

        // ─────────────────────────────────────────
        // Init
        // ─────────────────────────────────────────

        if (canUsePageState) {
            const restoreResult = window.PageState.restoreFormState({
                pageKey: pageStateKey,
                form: filterForm
            });
            restoredStateOnLoad = !!restoreResult.restored;
            if (!restoredStateOnLoad) savePageState();
        }

        bindFilterAutoRefresh();
        syncExportLinks();
        syncSummary();
        updateSelectionUI();

        if (restoredStateOnLoad) {
            refreshResults(collectQuery(true), true, true);
        }
    }

    window.ApprovalQueuePage = { init: initApprovalQueue };
})(window, document);
