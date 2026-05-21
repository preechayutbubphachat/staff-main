/**
 * dept-report-detail.js
 *
 * Handles the department report per-staff detail modal (#deptReportDetailModal).
 * Triggered by buttons with [data-dept-report-detail-trigger] carrying:
 *   data-user-id   – users.id
 *   data-year      – CE year (e.g. 2026)
 *   data-month     – month number (1-12)
 *
 * Usage (PHP partial):
 *   <button data-dept-report-detail-trigger
 *           data-user-id="<?= $row['id'] ?>"
 *           data-year="<?= $filters['year_ce'] ?>"
 *           data-month="<?= $filters['month_number'] ?>">
 *     ดูรายละเอียด
 *   </button>
 */
(function () {
    'use strict';

    // ── Helpers ───────────────────────────────────────────────────────────────

    function el(id)   { return document.getElementById(id); }
    function show(id) { var e = el(id); if (e) e.classList.remove('d-none'); }
    function hide(id) { var e = el(id); if (e) e.classList.add('d-none'); }
    function text(id, val) { var e = el(id); if (e) e.textContent = val || '-'; }

    function endpointUrl(userId, year, month) {
        var depth  = (window.location.pathname.match(/\//g) || []).length;
        var prefix = depth >= 3 ? '../' : './';
        return prefix + 'ajax/reports/get_dept_staff_detail.php'
            + '?user_id=' + encodeURIComponent(userId)
            + '&year='    + encodeURIComponent(year)
            + '&month='   + encodeURIComponent(month);
    }

    // ── State ─────────────────────────────────────────────────────────────────

    var modalElement = null;
    var bsModal      = null;

    // ── UI states ─────────────────────────────────────────────────────────────

    function setState(state, errorMsg) {
        hide('deptReportDetailLoading');
        hide('deptReportDetailContent');
        hide('deptReportDetailError');

        if (state === 'loading') {
            show('deptReportDetailLoading');
        } else if (state === 'ready') {
            show('deptReportDetailContent');
        } else if (state === 'error') {
            var errEl = el('deptReportDetailError');
            if (errEl) {
                errEl.textContent = errorMsg || 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
                errEl.classList.remove('d-none');
            }
        }
    }

    // ── Avatar ────────────────────────────────────────────────────────────────

    function setAvatar(imageUrl) {
        var img  = el('deptReportDetailAvatarImg');
        var icon = el('deptReportDetailAvatarIcon');
        if (!img || !icon) return;

        // Clear stale handlers from previous record
        img.onload  = null;
        img.onerror = null;

        var safeUrl = typeof imageUrl === 'string' ? imageUrl.trim() : '';
        if (!safeUrl) {
            img.classList.add('d-none');
            img.removeAttribute('src');
            icon.classList.remove('d-none');
            return;
        }

        // Show placeholder while image resolves
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

    // ── Render ────────────────────────────────────────────────────────────────

    function renderDetail(payload) {
        var r = payload.record;

        setAvatar(r.profile_image_url || null);

        // Identity
        text('deptReportDetailFullname',   r.fullname);
        text('deptReportDetailPosition',   r.position_name);
        text('deptReportDetailDepartment', r.department_name);
        text('deptReportDetailDeptName',   r.department_name);

        // Period chip + field
        var periodText = r.period_label || '-';
        text('deptReportDetailPeriodChip', periodText);
        text('deptReportDetailPeriod',     periodText);

        // KPI fields
        text('deptReportDetailTotalLogs',  String(r.total_logs)  + ' เวร');
        text('deptReportDetailTotalHours', String(r.total_hours) + ' ชม.');
        text('deptReportDetailApproved',   String(r.approved_logs) + ' รายการ');
        text('deptReportDetailPending',    String(r.pending_logs)  + ' รายการ');

        // Progress bar
        var total    = r.total_logs || 0;
        var approved = r.approved_logs || 0;
        var pct      = total > 0 ? Math.round((approved / total) * 100) : 0;

        var bar = el('deptReportDetailProgressBar');
        if (bar) {
            bar.style.width   = pct + '%';
            bar.setAttribute('aria-valuenow', String(pct));
        }
        text('deptReportDetailProgressLabel',
            total > 0
                ? 'ตรวจสอบแล้ว ' + approved + ' จาก ' + total + ' รายการ (' + pct + '%)'
                : 'ยังไม่มีรายการลงเวรในช่วงเวลานี้'
        );
    }

    // ── Open ──────────────────────────────────────────────────────────────────

    function openDeptReportDetail(userId, year, month) {
        if (!modalElement || !userId) return;

        // Clear previous state immediately
        setAvatar('');
        setState('loading');

        if (!bsModal) {
            bsModal = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                ? bootstrap.Modal.getOrCreateInstance(modalElement)
                : null;
        }
        if (bsModal) bsModal.show();

        fetch(endpointUrl(userId, year, month), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลได้');
                }
                return data;
            });
        })
        .then(function (payload) {
            renderDetail(payload);
            setState('ready');
        })
        .catch(function (error) {
            setState('error', error.message);
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        modalElement = document.getElementById('deptReportDetailModal');
        if (!modalElement) return; // not included on this page

        // Reset content when modal closes
        modalElement.addEventListener('hidden.bs.modal', function () {
            setState('loading');
            setAvatar('');
        });

        // Event delegation — works for AJAX-rendered rows
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-dept-report-detail-trigger]');
            if (!trigger) return;

            var userId = trigger.getAttribute('data-user-id');
            var year   = trigger.getAttribute('data-year');
            var month  = trigger.getAttribute('data-month');
            if (!userId || !year || !month) return;

            openDeptReportDetail(userId, year, month);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for direct calls if needed
    window.openDeptReportDetail = openDeptReportDetail;
}());
