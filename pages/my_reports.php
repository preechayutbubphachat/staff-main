<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$userId = (int) $_SESSION['id'];
$reportData = app_fetch_my_report_data($conn, $userId, $_GET);
$filters = $reportData['filters'];
$summary = $reportData['summary'];
$logs = $reportData['logs'];

$period = $filters['period'];
$year = $filters['year'];
$yearBe = $filters['year_be'] ?? ((int) date('Y') + 543);
$dateFrom = $filters['date_from'];
$dateTo = $filters['date_to'];
$titleRange = $filters['title_range'];
$monthOptions = app_get_thai_month_select_options();
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, 20);
$totalRows = count($logs);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$pagedLogs = array_slice($logs, ($page - 1) * $perPage, $perPage);

$queryBase = [
    'period' => $period,
    'month' => $filters['month_number'] ?? date('n'),
    'year_be' => $yearBe,
    'year' => $year,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'per_page' => $perPage,
];

$printQuery = app_build_table_query($queryBase, ['type' => 'my']);
$pdfQuery = app_build_table_query($queryBase, ['type' => 'my', 'download' => 'pdf']);
$csvQuery = app_build_table_query($queryBase, ['type' => 'my']);
$historyQuery = app_build_table_query($queryBase, ['period' => 'year', 'p' => 1]);

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$userId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$role = app_current_role();
$roleLabel = app_role_label($role);
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($userMeta ?: ['fullname' => $_SESSION['fullname'] ?? '-']);
$departmentLabel = trim((string) ($userMeta['department_name'] ?? '')) !== '' ? (string) $userMeta['department_name'] : '-';
$positionLabel = trim((string) ($userMeta['position_name'] ?? '')) !== '' ? (string) $userMeta['position_name'] : $roleLabel;
$periodLabel = trim((string) ($filters['heading_month_year_th'] ?? '')) !== '' ? (string) $filters['heading_month_year_th'] : $titleRange;
$totalLogs = (int) ($summary['total_logs'] ?? 0);
$totalHours = (float) ($summary['total_hours'] ?? 0);
$approvedLogs = (int) ($summary['approved_logs'] ?? 0);
$pendingLogs = (int) ($summary['pending_logs'] ?? 0);
$approvedPercent = $totalLogs > 0 ? ($approvedLogs / $totalLogs) * 100 : 0;
$pendingPercent = $totalLogs > 0 ? ($pendingLogs / $totalLogs) * 100 : 0;
$latestLog = $logs[0] ?? null;
$latestLabel = $latestLog ? app_format_thai_date((string) ($latestLog['work_date'] ?? '')) : '-';
$latestTime = $latestLog && !empty($latestLog['time_in']) ? date('H:i', strtotime((string) $latestLog['time_in'])) . ' น.' : '-';
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
$notificationCount = app_get_unread_notification_count($conn, $userId);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายงานของฉัน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell my-report-page-shell">
<?php render_dashboard_sidebar('my_reports.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main my-report-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">รายงานของฉัน</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาชื่อ, ตำแหน่ง, แผนก หรือสถานะ">
        </label>

        <a href="notifications.php" class="dash-icon-button relative" aria-label="เปิดการแจ้งเตือน">
            <i class="bi bi-bell text-lg"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="absolute -right-1 -top-1 min-w-[1.15rem] rounded-full bg-rose-500 px-1 text-center text-[0.65rem] font-bold leading-[1.15rem] text-white">
                    <?= $notificationCount > 9 ? '9+' : (int) $notificationCount ?>
                </span>
            <?php endif; ?>
        </a>

        <button type="button" class="dash-profile-button" data-profile-modal-trigger data-user-id="<?= $userId ?>">
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

    <div class="my-report-dashboard-frame">
        <section class="my-report-hero-stage">
            <article class="dash-card-strong my-report-hero-card">
                <div class="my-report-hero-grid">
                    <div class="my-report-hero-copy">
                        <span class="dash-hero-pill"><i class="bi bi-clipboard2-data"></i> My Reports</span>
                        <h2 class="dash-hero-title my-report-hero-title">รายงานการลงเวลาเวรของฉัน</h2>
                        <p class="dash-hero-copy">
                            ตรวจสอบ บันทึก และติดตามรายงานการลงเวลาเวรของคุณ สามารถส่งออกข้อมูลหรือพิมพ์รายงานเพื่อใช้งานได้ทันที
                        </p>
                        <div class="dash-hero-chips">
                            <span class="dash-hero-chip"><i class="bi bi-calendar-event"></i><span data-my-report-period><?= htmlspecialchars($periodLabel) ?></span></span>
                            <span class="dash-hero-chip"><i class="bi bi-person"></i>ผู้ใช้: <?= htmlspecialchars($displayName) ?> (<?= htmlspecialchars($positionLabel) ?>)</span>
                        </div>
                    </div>

                    <div class="my-report-hero-divider" aria-hidden="true"></div>

                    <div class="my-report-hero-metrics" aria-label="สรุปรายงานของฉัน">
                        <div class="my-report-hero-metric">
                            <span class="my-report-hero-icon is-blue"><i class="bi bi-receipt"></i></span>
                            <strong data-my-report-total><?= number_format($totalLogs) ?></strong>
                            <span>รายการทั้งหมด<br>รายการ</span>
                        </div>
                        <div class="my-report-hero-metric">
                            <span class="my-report-hero-icon is-green"><i class="bi bi-clock"></i></span>
                            <strong data-my-report-hours><?= number_format($totalHours, 2) ?></strong>
                            <span>ชั่วโมงรวม<br>ชั่วโมง</span>
                        </div>
                        <div class="my-report-hero-metric">
                            <span class="my-report-hero-icon is-mint"><i class="bi bi-check-lg"></i></span>
                            <strong data-my-report-approved><?= number_format($approvedLogs) ?></strong>
                            <span>ตรวจแล้ว<br>รายการ</span>
                        </div>
                        <div class="my-report-hero-metric">
                            <span class="my-report-hero-icon is-amber"><i class="bi bi-hourglass-split"></i></span>
                            <strong data-my-report-pending><?= number_format($pendingLogs) ?></strong>
                            <span>รอตรวจ<br>รายการ</span>
                        </div>
                    </div>

                    <div class="my-report-hero-actions">
                        <a href="#my-report-results-panel" class="dash-btn dash-btn-secondary">
                            <i class="bi bi-file-earmark-text"></i>เปิดรายงานส่วนตัว
                        </a>
                        <a href="my_reports.php?<?= htmlspecialchars($historyQuery) ?>" class="dash-btn dash-btn-on-dark">
                            <i class="bi bi-clock-history"></i>ดูประวัติรายงาน
                        </a>
                    </div>
                </div>
            </article>
        </section>

        <div id="myReportsSummary"></div>

        <section class="my-report-workspace-grid">
            <aside class="dash-card my-report-filter-card">
                <div>
                    <p class="my-report-section-eyebrow">Report filters</p>
                    <h2 class="my-report-card-title">ตัวกรองรายงานและเครื่องมือ</h2>
                </div>

                <form method="get" id="myReportsFilterForm" class="my-report-filter-form" data-page-state-key="my_reports">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">

                    <div class="my-report-filter-field is-wide">
                        <label class="my-report-field-label">ประเภทรายงาน</label>
                        <select name="period" class="form-select">
                            <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>รายสัปดาห์</option>
                            <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>รายเดือน</option>
                            <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>รายปี</option>
                            <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>กำหนดเอง</option>
                        </select>
                    </div>

                    <div class="my-report-filter-grid">
                        <div class="my-report-filter-field">
                            <label class="my-report-field-label">เดือน</label>
                            <select name="month" class="form-select">
                                <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                    <option value="<?= (int) $monthValue ?>" <?= (int) ($filters['month_number'] ?? 0) === (int) $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="my-report-filter-field">
                            <label class="my-report-field-label">ปี (พ.ศ.)</label>
                            <input type="number" name="year_be" class="form-control" min="2400" max="2800" step="1" value="<?= htmlspecialchars((string) $yearBe) ?>" inputmode="numeric">
                        </div>
                        <div class="my-report-filter-field">
                            <label class="my-report-field-label">แผนก</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($departmentLabel) ?>" readonly>
                        </div>
                        <div class="my-report-filter-field">
                            <label class="my-report-field-label">สถานะ</label>
                            <select class="form-select" aria-label="สถานะรายงาน">
                                <option>ทั้งหมดสถานะ</option>
                                <option>รอตรวจ</option>
                                <option>ตรวจแล้ว</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($period === 'year'): ?>
                        <div class="my-report-filter-field is-wide">
                            <label class="my-report-field-label">ปี ค.ศ.</label>
                            <input type="number" name="year" class="form-control" min="2020" max="2100" value="<?= htmlspecialchars((string) $year) ?>">
                        </div>
                    <?php elseif ($period === 'custom'): ?>
                        <div class="my-report-filter-grid">
                            <div class="my-report-filter-field">
                                <label class="my-report-field-label">วันที่เริ่มต้น</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="my-report-filter-field">
                                <label class="my-report-field-label">วันที่สิ้นสุด</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="my-report-filter-field is-wide">
                        <label class="my-report-field-label">ค้นหาข้อมูล</label>
                        <label class="my-report-search-field">
                            <input type="search" placeholder="ค้นหาชื่อ, หมายเหตุ หรือสถานะ" aria-label="ค้นหาข้อมูลในรายงาน">
                            <i class="bi bi-search"></i>
                        </label>
                    </div>

                    <div class="my-report-filter-actions">
                        <a class="dash-btn dash-btn-ghost my-report-action-btn" href="my_reports.php">
                            <i class="bi bi-arrow-clockwise"></i>ล้างตัวกรอง
                        </a>
                        <button type="submit" class="dash-btn dash-btn-primary my-report-action-btn">
                            <i class="bi bi-search"></i>ค้นหา
                        </button>
                    </div>
                </form>

                <div class="my-report-tools-card">
                    <h3>จัดการรายงาน</h3>
                    <div class="my-report-tool-grid">
                        <a class="dash-btn dash-btn-ghost my-report-tool-btn" data-export-base="report_print.php" data-export-type="my" href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer"></i>พิมพ์รายงาน
                        </a>
                        <a class="dash-btn dash-btn-ghost my-report-tool-btn" data-export-base="report_print.php" data-export-type="my" href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-filetype-pdf"></i>ส่งออก PDF
                        </a>
                        <a class="dash-btn dash-btn-ghost my-report-tool-btn" data-export-base="export_report.php" data-export-type="my" href="export_report.php?<?= htmlspecialchars($csvQuery) ?>">
                            <i class="bi bi-filetype-csv"></i>ส่งออก CSV
                        </a>
                    </div>
                </div>
            </aside>

            <div id="myReportsResults" class="min-w-0">
                <?php require __DIR__ . '/../partials/reports/my_results.php'; ?>
            </div>
        </section>

        <div id="myReportsBottomSummary"></div>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/table-filters.js"></script>
<script>
function moveMyReportBlock(container, selector, targetId) {
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

function updateMyReportHero(summaryBlock) {
    if (!summaryBlock) {
        return;
    }

    const mappings = {
        total: '[data-my-report-total]',
        hours: '[data-my-report-hours]',
        approved: '[data-my-report-approved]',
        pending: '[data-my-report-pending]'
    };

    Object.entries(mappings).forEach(function ([key, selector]) {
        const target = document.querySelector(selector);
        const value = summaryBlock.getAttribute('data-' + key);
        if (target && value !== null && value !== '') {
            target.textContent = key === 'hours' ? Number(value).toFixed(2) : Number(value).toLocaleString('th-TH');
        }
    });
}

function syncMyReportsLayout(payload) {
    const resultsContainer = payload && payload.container ? payload.container : document.getElementById('myReportsResults');
    const summaryBlock = moveMyReportBlock(resultsContainer, '[data-results-summary]', 'myReportsSummary');
    moveMyReportBlock(resultsContainer, '[data-bottom-summary]', 'myReportsBottomSummary');
    updateMyReportHero(summaryBlock);
}

TableFilters.init({
    formId: 'myReportsFilterForm',
    containerId: 'myReportsResults',
    endpoint: '../ajax/reports/my_report_rows.php',
    pushBase: 'my_reports.php',
    scopeSelector: '.my-report-dashboard-frame',
    onRefresh: syncMyReportsLayout
});

syncMyReportsLayout({
    container: document.getElementById('myReportsResults')
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
</body>
</html>
