<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_permission('can_view_department_reports');
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$reportData = app_fetch_department_report_data($conn, $_GET);
$filters = $reportData['filters'];
$staffRows = $reportData['staff_rows'];
$departmentTotals = $reportData['department_totals'];
$departments = $filters['scope']['departments'];
$view = $filters['view'];
$headingContext = $reportData['heading_context'] ?? app_get_department_report_heading_context($filters);
$monthOptions = app_get_thai_month_select_options();
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, $view === 'cards' ? 12 : 20);
$totalRows = count($staffRows);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$pagedRows = array_slice($staffRows, ($page - 1) * $perPage, $perPage);

$queryBase = [
    'department_id' => $filters['selected_department_id'] > 0 ? $filters['selected_department_id'] : '',
    'month' => $filters['month_number'],
    'year_be' => $filters['year_be'],
    'per_page' => $perPage,
];
$printQuery = app_build_table_query($queryBase, ['type' => 'department']);
$pdfQuery = app_build_table_query($queryBase, ['type' => 'department', 'download' => 'pdf']);
$csvQuery = app_build_table_query($queryBase, ['type' => 'department']);
$historyQuery = app_build_table_query($queryBase, ['p' => 1]);

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$currentUserId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$role = app_current_role();
$roleLabel = app_role_label($role);
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($userMeta ?: ['fullname' => $_SESSION['fullname'] ?? '-']);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
$notificationCount = app_get_unread_notification_count($conn, $currentUserId);

$staffCount = (int) ($departmentTotals['staff_count'] ?? 0);
$totalLogs = (int) ($departmentTotals['total_logs'] ?? 0);
$totalHours = (float) ($departmentTotals['total_hours'] ?? 0);
$approvedLogs = (int) ($departmentTotals['approved_logs'] ?? 0);
$pendingLogs = (int) ($departmentTotals['pending_logs'] ?? 0);
$approvedPercent = $totalLogs > 0 ? ($approvedLogs / $totalLogs) * 100 : 0;
$pendingPercent = $totalLogs > 0 ? ($pendingLogs / $totalLogs) * 100 : 0;
$departmentLogTotals = [];
foreach ($staffRows as $row) {
    $departmentName = trim((string) ($row['department_name'] ?? '')) ?: '-';
    $departmentLogTotals[$departmentName] = ($departmentLogTotals[$departmentName] ?? 0) + (int) ($row['total_logs'] ?? 0);
}
arsort($departmentLogTotals);
$topDepartmentName = $departmentLogTotals ? (string) array_key_first($departmentLogTotals) : '-';
$topDepartmentLogs = $departmentLogTotals ? (int) reset($departmentLogTotals) : 0;
$topDepartmentPercent = $totalLogs > 0 ? ($topDepartmentLogs / $totalLogs) * 100 : 0;
$scopeLabel = (string) ($headingContext['department_label'] ?? 'ทุกแผนกในระบบ');
$monthYearLabel = (string) ($headingContext['month_year_label'] ?? '');
$latestLabel = app_format_thai_date(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายงานแผนก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell department-report-page-shell">
<?php render_dashboard_sidebar('department_reports.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main department-report-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">รายงานแผนก</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาชื่อ, ตำแหน่ง, แผนก หรือสถานะ">
        </label>
        <?php render_notification_bell(); ?>

        <button type="button" class="dash-profile-button" data-profile-modal-trigger data-user-id="<?= $currentUserId ?>">
            <span class="dash-avatar">
                <?php if ($profileImageSrc): ?>
                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="h-full w-full object-cover">
                <?php else: ?>
                    <?= htmlspecialchars(mb_substr($displayName !== '-' ? $displayName : 'U', 0, 1, 'UTF-8')) ?>
                <?php endif; ?>
            </span>
            <span class="hidden text-left sm:block">
                <span class="block max-w-[8rem] truncate font-semibold text-hospital-ink"><?= htmlspecialchars($displayName) ?></span>
                <span class="block text-xs text-hospital-muted"><?= htmlspecialchars($roleLabel) ?></span>
            </span>
            <i class="bi bi-chevron-down text-xs text-hospital-muted"></i>
        </button>
    </header>

    <div class="department-report-dashboard-frame">
        <section class="department-report-hero-stage">
            <article class="dash-card-strong department-report-hero-card">
                <div class="department-report-hero-grid">
                    <div class="department-report-hero-copy">
                        <span class="dash-hero-pill"><i class="bi bi-building"></i> Department Reports</span>
                        <h2 class="dash-hero-title department-report-hero-title">รายงานสรุปแผนกทั้งหมด</h2>
                        <p class="dash-hero-copy">
                            สรุปข้อมูลการลงเวลาและเวรทุกแผนกในระบบ เพื่อใช้เปรียบเทียบภาระงานและติดตามสถานะการตรวจสอบของแต่ละแผนก
                        </p>
                        <div class="dash-hero-chips">
                            <span class="dash-hero-chip"><i class="bi bi-calendar-event"></i><span data-department-report-period><?= htmlspecialchars($monthYearLabel) ?></span></span>
                            <span class="dash-hero-chip"><i class="bi bi-diagram-3"></i>ขอบเขตรายงาน: <span data-department-report-scope><?= htmlspecialchars($scopeLabel) ?></span></span>
                            <span class="dash-hero-chip"><i class="bi bi-clock-history"></i>อัปเดตล่าสุด: <?= htmlspecialchars($latestLabel) ?></span>
                        </div>
                    </div>

                    <div class="department-report-hero-divider" aria-hidden="true"></div>

                    <div class="department-report-hero-metrics" aria-label="สรุปรายงานแผนก">
                        <div class="department-report-hero-metric">
                            <span class="department-report-hero-icon is-blue"><i class="bi bi-people-fill"></i></span>
                            <strong data-department-report-staff><?= number_format($staffCount) ?></strong>
                            <span>จำนวนเจ้าหน้าที่</span>
                        </div>
                        <div class="department-report-hero-metric">
                            <span class="department-report-hero-icon is-green"><i class="bi bi-calendar2-check"></i></span>
                            <strong data-department-report-logs><?= number_format($totalLogs) ?></strong>
                            <span>จำนวนเวร</span>
                        </div>
                        <div class="department-report-hero-metric">
                            <span class="department-report-hero-icon is-mint"><i class="bi bi-clock"></i></span>
                            <strong data-department-report-hours><?= number_format($totalHours, 2) ?></strong>
                            <span>ชั่วโมงรวม</span>
                        </div>
                        <div class="department-report-hero-metric">
                            <span class="department-report-hero-icon is-amber"><i class="bi bi-hourglass-split"></i></span>
                            <strong data-department-report-pending><?= number_format($pendingLogs) ?></strong>
                            <span>รอตรวจ</span>
                        </div>
                    </div>

                    <div class="department-report-hero-actions">
                        <a href="#department-report-results-panel" class="dash-btn dash-btn-secondary">
                            <i class="bi bi-file-earmark-bar-graph"></i>เปิดรายงานแผนก
                        </a>
                        <a href="department_reports.php?<?= htmlspecialchars($historyQuery) ?>" class="dash-btn dash-btn-on-dark">
                            <i class="bi bi-clock-history"></i>ดูประวัติรายงาน
                        </a>
                    </div>
                </div>
            </article>
        </section>

        <div id="departmentReportsSummary"></div>

        <section class="department-report-workspace-grid">
            <aside class="dash-card department-report-filter-card">
                <div>
                    <p class="department-report-section-eyebrow">Report filters</p>
                    <h2 class="department-report-card-title">ตัวกรองรายงานและเครื่องมือ</h2>
                </div>

                <form method="get" id="departmentReportsFilterForm" class="department-report-filter-form" data-page-state-key="department_reports">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">

                    <div class="department-report-filter-field is-wide">
                        <label class="department-report-field-label">ขอบเขตรายงาน</label>
                        <select name="department_id" class="form-select">
                            <option value="">ทุกแผนกในระบบ</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= $filters['selected_department_id'] === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="department-report-filter-grid">
                        <div class="department-report-filter-field">
                            <label class="department-report-field-label">เดือน</label>
                            <select name="month" class="form-select">
                                <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                    <option value="<?= (int) $monthValue ?>" <?= (int) $filters['month_number'] === (int) $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="department-report-filter-field">
                            <label class="department-report-field-label">ปี (พ.ศ.)</label>
                            <input type="number" name="year_be" class="form-control" min="2400" max="2800" step="1" value="<?= htmlspecialchars((string) $filters['year_be']) ?>" inputmode="numeric">
                        </div>
                        <div class="department-report-filter-field">
                            <label class="department-report-field-label">แผนก</label>
                            <select class="form-select" aria-label="แผนก">
                                <option><?= htmlspecialchars($scopeLabel) ?></option>
                            </select>
                        </div>
                        <div class="department-report-filter-field">
                            <label class="department-report-field-label">สถานะ</label>
                            <select class="form-select" aria-label="สถานะ">
                                <option>ทั้งหมดสถานะ</option>
                                <option>ตรวจแล้ว</option>
                                <option>รอตรวจ</option>
                            </select>
                        </div>
                    </div>

                    <div class="department-report-filter-field is-wide">
                        <label class="department-report-field-label">ค้นหาชื่อเจ้าหน้าที่ / ตำแหน่ง</label>
                        <label class="department-report-search-field">
                            <input type="search" placeholder="พิมพ์ชื่อ, ตำแหน่ง หรือคำค้น" aria-label="ค้นหาชื่อเจ้าหน้าที่หรือตำแหน่ง">
                            <i class="bi bi-search"></i>
                        </label>
                    </div>

                    <div class="department-report-filter-actions">
                        <a class="dash-btn dash-btn-ghost department-report-action-btn" href="department_reports.php">
                            <i class="bi bi-arrow-clockwise"></i>ล้างตัวกรอง
                        </a>
                        <button type="submit" class="dash-btn dash-btn-primary department-report-action-btn">
                            <i class="bi bi-search"></i>ค้นหา
                        </button>
                    </div>
                </form>

            </aside>

            <div id="departmentReportsResults" class="min-w-0">
                <?php require __DIR__ . '/../partials/reports/department_results.php'; ?>
            </div>
        </section>

        <div id="departmentReportsBottomSummary"></div>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/table-filters.js"></script>
<script>
function moveDepartmentReportBlock(container, selector, targetId) {
    const mount = document.getElementById(targetId);
    if (!container || !mount) {
        return null;
    }

    const block = container.querySelector(selector);
    mount.innerHTML = '';
    if (block) {
        mount.appendChild(block);
    }
    return block;
}

function updateDepartmentReportHero(summaryBlock, form) {
    if (form) {
        const monthField = form.querySelector('[name="month"]');
        const yearField = form.querySelector('[name="year_be"]');
        const departmentField = form.querySelector('[name="department_id"]');
        const monthLabel = monthField && monthField.options.length ? monthField.options[monthField.selectedIndex].textContent.trim() : '';
        const yearLabel = yearField ? String(yearField.value || '').trim() : '';
        const departmentLabel = departmentField && departmentField.value !== ''
            ? departmentField.options[departmentField.selectedIndex].textContent.trim()
            : 'ทุกแผนกในระบบ';

        const periodTarget = document.querySelector('[data-department-report-period]');
        const scopeTarget = document.querySelector('[data-department-report-scope]');
        if (periodTarget && monthLabel && yearLabel) {
            periodTarget.textContent = monthLabel + ' ' + yearLabel;
        }
        if (scopeTarget) {
            scopeTarget.textContent = departmentLabel;
        }
    }

    if (!summaryBlock) {
        return;
    }

    const mappings = {
        staff: '[data-department-report-staff]',
        logs: '[data-department-report-logs]',
        hours: '[data-department-report-hours]',
        pending: '[data-department-report-pending]'
    };

    Object.entries(mappings).forEach(function ([key, selector]) {
        const target = document.querySelector(selector);
        const value = summaryBlock.getAttribute('data-' + key);
        if (target && value !== null && value !== '') {
            target.textContent = key === 'hours' ? Number(value).toFixed(2) : Number(value).toLocaleString('th-TH');
        }
    });
}

function syncDepartmentReportLayout(payload) {
    const form = payload && payload.form ? payload.form : document.getElementById('departmentReportsFilterForm');
    const resultsContainer = payload && payload.container ? payload.container : document.getElementById('departmentReportsResults');
    const summaryBlock = moveDepartmentReportBlock(resultsContainer, '[data-results-summary]', 'departmentReportsSummary');
    moveDepartmentReportBlock(resultsContainer, '[data-bottom-summary]', 'departmentReportsBottomSummary');
    updateDepartmentReportHero(summaryBlock, form);
}

TableFilters.init({
    formId: 'departmentReportsFilterForm',
    containerId: 'departmentReportsResults',
    endpoint: '../ajax/reports/department_rows.php',
    pushBase: 'department_reports.php',
    scopeSelector: '.department-report-dashboard-frame',
    onRefresh: syncDepartmentReportLayout
});

syncDepartmentReportLayout({
    form: document.getElementById('departmentReportsFilterForm'),
    container: document.getElementById('departmentReportsResults')
});

(function () {
    const openButton = document.querySelector('[data-dashboard-sidebar-open]');
    const closeButton = document.querySelector('[data-dashboard-sidebar-close]');
    const drawer = document.querySelector('[data-dashboard-sidebar-drawer]');
    const backdrop = document.querySelector('[data-dashboard-sidebar-backdrop]');

    if (!openButton || !closeButton || !drawer || !backdrop) {
        return;
    }

    const setOpen = function (isOpen) {
        drawer.classList.toggle('is-open', isOpen);
        backdrop.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen);
    };

    openButton.addEventListener('click', function () { setOpen(true); });
    closeButton.addEventListener('click', function () { setOpen(false); });
    backdrop.addEventListener('click', function () { setOpen(false); });
})();
</script>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
