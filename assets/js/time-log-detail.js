/**
 * time-log-detail.js
 *
 * Handles the generic time log detail modal (#timeLogDetailModal).
 * Used on pages that show time log rows but are NOT the approval queue
 * (daily_schedule, my_reports, etc.).
 *
 * Usage (PHP partial):
 *   <button data-time-log-detail-trigger data-time-log-id="123">ดูรายละเอียด</button>
 *
 * The endpoint path is resolved relative to the current page's directory,
 * so we detect depth at runtime.
 */
(function () {
    'use strict';

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Resolve the AJAX endpoint URL relative to the current page.
     * Pages are always one level below the project root (pages/*.php)
     * when loaded directly. AJAX partials are typically inline in the
     * same origin, so we use a root-relative path.
     */
    function endpointUrl(timeLogId) {
        // Detect if we are inside a pages/ subfolder or at root.
        var depth = (window.location.pathname.match(/\//g) || []).length;
        var prefix = depth >= 3 ? '../' : './';
        return prefix + 'ajax/time/get_time_log_detail.php?id=' + encodeURIComponent(timeLogId);
    }

    function el(id) { return document.getElementById(id); }
    function show(id) { var e = el(id); if (e) e.classList.remove('d-none'); }
    function hide(id) { var e = el(id); if (e) e.classList.add('d-none'); }
    function text(id, value) { var e = el(id); if (e) e.textContent = value || '-'; }

    // ──────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────
    var modalElement = null;
    var bsModal = null;

    // ──────────────────────────────────────────────
    // UI State transitions
    // ──────────────────────────────────────────────

    function setDetailState(state, errorMsg) {
        hide('timeLogDetailLoading');
        hide('timeLogDetailContent');
        hide('timeLogDetailError');

        if (state === 'loading') {
            show('timeLogDetailLoading');
        } else if (state === 'ready') {
            show('timeLogDetailContent');
        } else if (state === 'error') {
            var errEl = el('timeLogDetailError');
            if (errEl) {
                errEl.textContent = errorMsg || 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
                errEl.classList.remove('d-none');
            }
        }
    }

    // ──────────────────────────────────────────────
    // Avatar helpers
    // ──────────────────────────────────────────────

    function setAvatar(imageUrl) {
        var img  = el('timeLogDetailAvatarImg');
        var icon = el('timeLogDetailAvatarIcon');
        if (!img || !icon) return;

        // Always reset handlers to prevent stale closures from previous records
        img.onload  = null;
        img.onerror = null;

        var safeUrl = typeof imageUrl === 'string' ? imageUrl.trim() : '';
        if (!safeUrl) {
            img.classList.add('d-none');
            img.removeAttribute('src');
            icon.classList.remove('d-none');
            return;
        }

        // Hide both until we know the image loaded successfully
        img.classList.add('d-none');
        icon.classList.remove('d-none');

        img.onerror = function () {
            img.classList.add('d-none');
            img.removeAttribute('src');
            icon.classList.remove('d-none');
        };
        img.onload = function () {
            icon.classList.add('d-none');
            img.classList.remove('d-none');
        };

        img.src = safeUrl;
    }

    // ──────────────────────────────────────────────
    // Status badge
    // ──────────────────────────────────────────────

    function renderStatusBadge(badgeEl, statusClass, statusLabel) {
        if (!badgeEl) return;
        badgeEl.className = 'status-chip ' + (statusClass || 'neutral');
        badgeEl.textContent = statusLabel || '-';
    }

    // ──────────────────────────────────────────────
    // Shift type inference
    // ──────────────────────────────────────────────

    function inferShiftType(timeInStr) {
        if (!timeInStr || timeInStr === '-') return '-';
        var parts = timeInStr.split(':');
        if (parts.length < 1) return '-';
        var h = parseInt(parts[0], 10);
        if (h >= 6 && h < 13)   return 'เวรเช้า';
        if (h >= 13 && h < 21)  return 'เวรบ่าย';
        return 'เวรดึก';
    }

    // ──────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────

    function renderDetail(payload) {
        var r = payload.record;

        setAvatar(r.profile_image_url || null);

        text('timeLogDetailFullname', r.fullname);
        text('timeLogDetailPosition', r.position_name);
        text('timeLogDetailDepartment', r.department_name);
        text('timeLogDetailRecordId', String(r.id));
        text('timeLogDetailRawId', String(r.id));

        renderStatusBadge(el('timeLogDetailStatusBadge'), r.status_class, r.status_label);

        text('timeLogDetailWorkDate', r.work_date);
        text('timeLogDetailTimeIn', r.time_in);
        text('timeLogDetailTimeOut', r.time_out);
        text('timeLogDetailHours', r.work_hours);
        text('timeLogDetailShiftType', r.shift_type !== '-' ? r.shift_type : inferShiftType(r.time_in));
        text('timeLogDetailWorkDepartment', r.department_name);
        text('timeLogDetailStatusText', r.status_label);
        text('timeLogDetailChecker', r.checker_name);
        text('timeLogDetailCreatedAt', r.created_at);
        text('timeLogDetailUpdatedAt', r.updated_at);
        text('timeLogDetailNote', r.note);

        // Approval note row (hide if not relevant)
        var approvalRow = el('timeLogDetailApprovalNoteRow');
        if (r.approval_note && r.approval_note !== '-') {
            text('timeLogDetailApprovalNote', r.approval_note);
            if (approvalRow) approvalRow.style.display = '';
        } else {
            if (approvalRow) approvalRow.style.display = 'none';
        }

        // Audit trail
        var auditSection = el('timeLogDetailAuditSection');
        var auditList    = el('timeLogDetailAuditList');
        var audit = payload.audit || [];

        if (auditList) auditList.innerHTML = '';

        if (audit.length > 0 && auditSection && auditList) {
            auditSection.style.display = '';
            audit.forEach(function (entry) {
                var item = document.createElement('div');
                item.className = 'shift-review-audit-item';
                item.innerHTML =
                    '<span class="shift-review-audit-action">' + escHtml(entry.action_type) + '</span>' +
                    '<span class="shift-review-audit-actor">' + escHtml(entry.actor_name) + '</span>' +
                    '<span class="shift-review-audit-time">' + escHtml(entry.created_at) + '</span>' +
                    (entry.note !== '-' ? '<span class="shift-review-audit-note">' + escHtml(entry.note) + '</span>' : '');
                auditList.appendChild(item);
            });
        } else if (auditSection) {
            auditSection.style.display = 'none';
        }
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ──────────────────────────────────────────────
    // Main open function (exported globally)
    // ──────────────────────────────────────────────

    function openTimeLogDetail(timeLogId) {
        if (!modalElement || !timeLogId) return;

        // Clear previous content immediately to prevent stale data flash
        setAvatar('');
        setDetailState('loading');

        if (!bsModal) {
            bsModal = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                ? bootstrap.Modal.getOrCreateInstance(modalElement)
                : null;
        }
        if (bsModal) bsModal.show();

        fetch(endpointUrl(timeLogId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลรายการลงเวลาเวรได้');
                }
                return data;
            });
        })
        .then(function (payload) {
            renderDetail(payload);
            setDetailState('ready');
        })
        .catch(function (error) {
            setDetailState('error', error.message);
        });
    }

    // ──────────────────────────────────────────────
    // Init — event delegation
    // ──────────────────────────────────────────────

    function init() {
        modalElement = document.getElementById('timeLogDetailModal');
        if (!modalElement) return; // modal not included on this page

        // Clear stale content when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function () {
            setDetailState('loading');
            setAvatar('');
        });

        // Delegated click — works for both static and AJAX-rendered rows
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-time-log-detail-trigger]');
            if (!trigger) return;
            var timeLogId = trigger.getAttribute('data-time-log-id');
            if (!timeLogId) return;
            openTimeLogDetail(timeLogId);
        });
    }

    // Run after DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose globally in case other scripts need to call it directly
    window.openTimeLogDetail = openTimeLogDetail;
}());
