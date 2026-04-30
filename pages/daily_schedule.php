<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$view = $_GET['view'] ?? 'table';
$view = in_array($view, ['cards', 'table'], true) ? $view : 'table';
$schedule = app_fetch_daily_schedule_data($conn, $_GET);
$mode = $schedule['mode'];
$modeOptions = app_daily_schedule_mode_options();
if ($mode === 'monthly') {
    $view = 'table';
}
$selectedDate = $schedule['selected_date'];
$selectedDepartment = $schedule['selected_department'];
$name = $schedule['name'];
$reviewStatus = $schedule['review_status'];
$selectedMonth = (int) ($schedule['month_number'] ?? date('n'));
$selectedYearBe = (int) ($schedule['year_be'] ?? ((int) date('Y') + 543));
$logs = $schedule['logs'];
$dateLabel = $schedule['date_label'];
$headingContext = $schedule['heading_context'];
$dateHeading = $headingContext['main_heading'];
$heroPeriodLabel = $mode === 'monthly'
    ? 'เดือน ' . ($schedule['heading_month_year_th'] ?? '')
    : $dateLabel;
$departmentOptions = app_get_daily_schedule_departments($conn)['departments'];
$monthOptions = app_get_thai_month_select_options();
$scopeLabel = $headingContext['scope_label'];
$scopeBadgeLabel = $headingContext['department_name'] !== '' ? $headingContext['department_name'] : 'ทุกแผนก';
$reviewStatusOptions = app_daily_schedule_status_options();
$scheduleStats = app_daily_schedule_derived_stats($schedule);
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, $view === 'table' ? 20 : 12);
$totalRows = (int) ($schedule['total_rows'] ?? count($logs));
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$pagedLogs = $mode === 'daily' ? array_slice($logs, ($page - 1) * $perPage, $perPage) : [];
$pagedGroups = $mode === 'daily' ? app_group_daily_schedule_rows_by_shift($pagedLogs) : [];
$matrixRows = $schedule['matrix_rows'] ?? [];
$pagedMatrixRows = $mode === 'monthly' ? array_slice($matrixRows, ($page - 1) * $perPage, $perPage) : [];
$matrixDays = $schedule['matrix_days'] ?? [];

$queryBase = [
    'mode' => $mode,
    'date' => $selectedDate,
    'month' => $selectedMonth,
    'year_be' => $selectedYearBe,
    'department' => $selectedDepartment,
    'name' => $name,
    'review_status' => $reviewStatus,
    'per_page' => $perPage,
];
$printQuery = app_build_table_query($queryBase, ['type' => 'daily']);
$pdfQuery = app_build_table_query($queryBase, ['type' => 'daily', 'download' => 'pdf']);
$csvQuery = app_build_table_query($queryBase, ['type' => 'daily']);
$historyQuery = app_build_table_query($queryBase, ['mode' => 'monthly', 'p' => 1]);

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$currentUserId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['department_name' => '-'];
$role = app_current_role();
$roleLabel = app_role_label($role);
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($userMeta);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
$notificationCount = app_get_unread_notification_count($conn, $currentUserId);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เวรวันนี้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell daily-page-shell">
<?php render_dashboard_sidebar('daily_schedule.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main daily-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">เวรวันนี้</h1>
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

    <div class="daily-dashboard-frame">
        <section class="daily-hero-stage">
            <article class="dash-card-strong daily-hero-card">
                <div class="daily-hero-grid">
                    <div class="daily-hero-copy">
                        <span class="dash-hero-pill"><i class="bi bi-calendar2-week"></i> Today’s Shift Workspace</span>
                        <h2 class="dash-hero-title daily-hero-title">ภาพรวมเวรประจำวันนี้</h2>
                        <p class="dash-hero-copy">
                            ตรวจสอบและบริหารจัดการเวรประจำวันของทุกแผนก ติดตามสถานะเวรแบบเรียลไทม์ และจัดการการเปลี่ยนเวรได้อย่างมีประสิทธิภาพ
                        </p>
                        <div class="dash-hero-chips">
                            <span class="dash-hero-chip"><i class="bi bi-calendar-event"></i><span data-daily-hero-period><?= htmlspecialchars($heroPeriodLabel) ?></span></span>
                            <span class="dash-hero-chip"><i class="bi bi-clock-history"></i>อัปเดตล่าสุด <span data-daily-hero-updated><?= htmlspecialchars($scheduleStats['updated_time_label']) ?></span></span>
                        </div>
                    </div>

                    <div class="daily-hero-divider" aria-hidden="true"></div>

                    <div class="daily-hero-metrics" aria-label="สรุปสถานะเวรวันนี้">
                        <p class="daily-hero-metrics-title">สรุปสถานะเวรวันนี้</p>
                        <div class="daily-hero-metric-grid">
                            <div class="daily-hero-metric">
                                <span class="daily-hero-metric-icon is-blue"><i class="bi bi-calendar-check"></i></span>
                                <div><strong data-daily-hero-total><?= number_format($scheduleStats['total']) ?></strong><span>เวรทั้งหมดวันนี้<br>รายการ</span></div>
                            </div>
                            <div class="daily-hero-metric">
                                <span class="daily-hero-metric-icon is-green"><i class="bi bi-check-lg"></i></span>
                                <div><strong data-daily-hero-active><?= number_format($scheduleStats['active']) ?></strong><span>กำลังปฏิบัติงาน<br>รายการ</span></div>
                            </div>
                            <div class="daily-hero-metric">
                                <span class="daily-hero-metric-icon is-violet"><i class="bi bi-people"></i></span>
                                <div><strong data-daily-hero-completed><?= number_format($scheduleStats['completed']) ?></strong><span>ครบเวรแล้ว<br>รายการ</span></div>
                            </div>
                            <div class="daily-hero-metric">
                                <span class="daily-hero-metric-icon is-amber"><i class="bi bi-exclamation-triangle"></i></span>
                                <div><strong data-daily-hero-pending><?= number_format($scheduleStats['pending']) ?></strong><span>รอจัดสรร/ขาดเวร<br>รายการ</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="daily-hero-actions">
                        <a href="#daily-schedule-results-panel" class="dash-btn dash-btn-secondary daily-hero-cta-primary">
                            <i class="bi bi-calendar2-check"></i>เปิดตารางเวร
                        </a>
                        <a href="daily_schedule.php?<?= htmlspecialchars($historyQuery) ?>" class="dash-btn dash-btn-on-dark daily-hero-cta-secondary">
                            <i class="bi bi-clock-history"></i>ดูประวัติการเวร
                        </a>
                    </div>
                </div>
            </article>
        </section>

        <div id="dailyScheduleSummary"></div>

        <section class="daily-workspace-grid">
            <aside class="dash-card daily-filter-card">
                <div class="daily-card-head">
                    <div>
                        <p class="daily-section-eyebrow">Shift filters</p>
                        <h2 class="daily-card-title">ตัวกรองและเครื่องมือ</h2>
                    </div>
                    <span class="daily-filter-scope"><?= htmlspecialchars($scopeBadgeLabel) ?></span>
                </div>

                <form method="get" id="dailyScheduleFilterForm" class="daily-filter-form" data-page-state-key="daily_schedule">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">

                    <div class="daily-filter-group">
                        <label class="daily-field-label" for="dailySearchName">ค้นหา</label>
                        <label class="daily-search-field" for="dailySearchName">
                            <i class="bi bi-search"></i>
                            <input type="search" id="dailySearchName" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="ค้นหาชื่อ, ตำแหน่ง, แผนก หรือหมายเหตุ">
                        </label>
                    </div>

                    <div class="daily-filter-grid">
                        <div class="daily-filter-field">
                            <label class="daily-field-label" for="dailyDepartment">แผนก</label>
                            <select id="dailyDepartment" name="department" class="form-select">
                                <option value="">ทั้งหมดแผนก</option>
                                <?php foreach ($departmentOptions as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= (string) $selectedDepartment === (string) $department['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="daily-filter-field">
                            <label class="daily-field-label" for="dailyMode">ประเภทเวร</label>
                            <select id="dailyMode" name="mode" class="form-select">
                                <?php foreach ($modeOptions as $modeValue => $modeLabel): ?>
                                    <option value="<?= htmlspecialchars($modeValue) ?>" <?= $mode === $modeValue ? 'selected' : '' ?>><?= htmlspecialchars($modeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="daily-filter-field">
                            <label class="daily-field-label" for="dailyStatus">สถานะ</label>
                            <select id="dailyStatus" name="review_status" class="form-select">
                                <?php foreach ($reviewStatusOptions as $statusValue => $statusLabel): ?>
                                    <option value="<?= htmlspecialchars($statusValue) ?>" <?= $reviewStatus === $statusValue ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="daily-filter-field">
                            <label class="daily-field-label" for="dailyDate">วันที่</label>
                            <input id="dailyDate" type="date" name="date" class="form-control thai-date-input" value="<?= htmlspecialchars($selectedDate) ?>" data-thai-date-display="full" data-thai-date-empty="วัน/เดือน/ปี">
                        </div>
                        <div class="daily-filter-field">
                            <label class="daily-field-label" for="dailyMonth">เดือน</label>
                            <select id="dailyMonth" name="month" class="form-select">
                                <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                    <option value="<?= (int) $monthValue ?>" <?= $selectedMonth === (int) $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="daily-filter-field">
                            <label class="daily-field-label" for="dailyYear">ปี (พ.ศ.)</label>
                            <input id="dailyYear" type="number" name="year_be" class="form-control" min="2400" max="2800" step="1" value="<?= htmlspecialchars((string) $selectedYearBe) ?>" inputmode="numeric">
                        </div>
                        <div class="daily-filter-field">
                            <label class="daily-field-label" for="dailyPerPage">แสดง</label>
                            <select id="dailyPerPage" name="per_page" class="form-select">
                                <?php foreach ([10, 20, 50, 100] as $size): ?>
                                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> รายการ</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="daily-filter-actions">
                        <a href="daily_schedule.php" class="dash-btn dash-btn-ghost daily-tool-btn"><i class="bi bi-arrow-clockwise"></i>ล้างตัวกรอง</a>
                        <button type="submit" class="dash-btn dash-btn-primary daily-tool-btn"><i class="bi bi-search"></i>ค้นหา</button>
                    </div>
                </form>

                <div class="daily-tools-card">
                    <p class="daily-field-label !mb-1">เครื่องมือเพิ่มเติม</p>
                    <div class="daily-tool-grid">
                        <a class="dash-btn dash-btn-ghost daily-tool-btn" data-export-base="report_print.php" data-export-type="daily" href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer"></i>พิมพ์รายงาน
                        </a>
                        <a class="dash-btn dash-btn-ghost daily-tool-btn" data-export-base="report_print.php" data-export-type="daily" data-export-download="pdf" href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-filetype-pdf"></i>ส่งออก PDF
                        </a>
                        <a class="dash-btn dash-btn-ghost daily-tool-btn" data-export-base="export_report.php" data-export-type="daily" href="export_report.php?<?= htmlspecialchars($csvQuery) ?>">
                            <i class="bi bi-filetype-csv"></i>ส่งออก CSV
                        </a>
                    </div>
                </div>
            </aside>

            <div id="dailyScheduleResults" class="min-w-0">
                <?php require __DIR__ . '/../partials/reports/daily_schedule_results.php'; ?>
            </div>
        </section>

        <div id="dailyScheduleBottomSummary"></div>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/table-filters.js"></script>
<script>
function buildDailyScheduleHeroContext(form) {
    if (!form) {
        return null;
    }

    const modeField = form.querySelector('[name="mode"]');
    const dateField = form.querySelector('[name="date"]');
    const monthField = form.querySelector('[name="month"]');
    const yearField = form.querySelector('[name="year_be"]');
    const modeValue = modeField ? String(modeField.value || 'daily').trim() : 'daily';
    const dateValue = dateField ? String(dateField.value || '').trim() : '';
    const monthValue = monthField ? parseInt(monthField.value || '0', 10) : 0;
    const yearBeValue = yearField ? parseInt(yearField.value || '0', 10) : 0;
    const thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

    let periodLabel = '';
    if (modeValue === 'monthly') {
        if (monthValue >= 1 && monthValue <= 12 && yearBeValue >= 2400) {
            periodLabel = 'เดือน ' + (thaiMonths[monthValue] || '') + ' ' + yearBeValue;
        }
    } else if (/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
        const parts = dateValue.split('-');
        periodLabel = 'วันที่ ' + parseInt(parts[2], 10) + ' ' + (thaiMonths[parseInt(parts[1], 10)] || '') + ' ' + (parseInt(parts[0], 10) + 543);
    }

    return { periodLabel };
}

function moveDailyScheduleBlock(container, selector, targetId) {
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

function updateDailyHeroFromSummary(summaryBlock, form) {
    const context = buildDailyScheduleHeroContext(form);
    const periodTarget = document.querySelector('[data-daily-hero-period]');
    if (periodTarget && context && context.periodLabel) {
        periodTarget.textContent = context.periodLabel;
    }

    if (!summaryBlock) {
        return;
    }

    const mappings = {
        total: '[data-daily-hero-total]',
        active: '[data-daily-hero-active]',
        completed: '[data-daily-hero-completed]',
        pending: '[data-daily-hero-pending]',
        updated: '[data-daily-hero-updated]'
    };

    Object.entries(mappings).forEach(function ([key, selector]) {
        const target = document.querySelector(selector);
        const value = summaryBlock.getAttribute('data-' + key);
        if (target && value !== null && value !== '') {
            target.textContent = key === 'updated' ? value : Number(value).toLocaleString('th-TH');
        }
    });
}

function syncDailyScheduleLayout(payload) {
    const form = payload && payload.form ? payload.form : document.getElementById('dailyScheduleFilterForm');
    const resultsContainer = payload && payload.container ? payload.container : document.getElementById('dailyScheduleResults');
    const summaryBlock = moveDailyScheduleBlock(resultsContainer, '[data-results-summary]', 'dailyScheduleSummary');
    moveDailyScheduleBlock(resultsContainer, '[data-bottom-summary]', 'dailyScheduleBottomSummary');
    updateDailyHeroFromSummary(summaryBlock, form);
}

TableFilters.init({
    formId: 'dailyScheduleFilterForm',
    containerId: 'dailyScheduleResults',
    endpoint: '../ajax/reports/daily_schedule_rows.php',
    pushBase: 'daily_schedule.php',
    scopeSelector: '.daily-dashboard-frame',
    onRefresh: syncDailyScheduleLayout
});

syncDailyScheduleLayout({
    form: document.getElementById('dailyScheduleFilterForm'),
    container: document.getElementById('dailyScheduleResults')
});
</script>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
