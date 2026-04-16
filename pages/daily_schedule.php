<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_login();

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
$reviewStatusOptions = app_daily_schedule_status_options();
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
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('daily_schedule.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="ops-hero mb-4">
        <div class="ops-hero-grid">
            <div>
                <div class="eyebrow mb-2">เวรประจำวัน</div>
                <h1 class="mb-2">ตารางเวรประจำวัน</h1>
                <p class="mb-0 text-white-50">ดูรายชื่อเจ้าหน้าที่ตามเวรประจำวันแบบสแกนง่าย แยกกลุ่มเวรชัดเจน พร้อมเบอร์โทรศัพท์สำหรับติดต่อหน้างานได้ทันที</p>
            </div>
            <aside class="ops-hero-side">
                <div class="ops-hero-stat">
                    <span>วันที่ที่เลือก</span>
                    <strong><?= htmlspecialchars($heroPeriodLabel) ?></strong>
                </div>
                <div class="ops-hero-stat">
                    <span>ขอบเขตข้อมูล</span>
                    <strong><?= htmlspecialchars($scopeLabel) ?></strong>
                </div>
            </aside>
        </div>
    </section>

    <section class="panel mb-4">
        <div id="dailyScheduleSummary" class="mb-4"></div>

        <div class="table-toolbar table-toolbar--filters">
            <div class="table-toolbar-main">
                <div class="table-toolbar-title">ตัวกรองตารางเวรประจำวัน</div>
                <div class="table-toolbar-help">ตัวกรองจะรีเฟรชผลลัพธ์อัตโนมัติ สามารถคลิกชื่อเจ้าหน้าที่เพื่อเปิดข้อมูลโปรไฟล์ และเลือกสถานะการตรวจสอบเพื่อดูเฉพาะรายการที่ต้องการได้ทันที</div>
                <form method="get" id="dailyScheduleFilterForm" class="table-toolbar-form" data-page-state-key="daily_schedule">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">??????????</label>
                        <select name="mode" class="form-select">
                            <?php foreach ($modeOptions as $modeValue => $modeLabel): ?>
                                <option value="<?= htmlspecialchars($modeValue) ?>" <?= $mode === $modeValue ? 'selected' : '' ?>><?= htmlspecialchars($modeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">??????</label>
                        <input type="date" name="date" class="form-control thai-date-input" value="<?= htmlspecialchars($selectedDate) ?>" data-thai-date-display="full" data-thai-date-empty="???/?????/??">
                    </div>
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">?????</label>
                        <select name="month" class="form-select">
                            <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                <option value="<?= (int) $monthValue ?>" <?= $selectedMonth === (int) $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">?? (?.?.)</label>
                        <input type="number" name="year_be" class="form-control" min="2400" max="2800" step="1" value="<?= htmlspecialchars((string) $selectedYearBe) ?>" inputmode="numeric">
                    </div>
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">????</label>
                        <select name="department" class="form-select">
                            <option value="">?????????????</option>
                            <?php foreach ($departmentOptions as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= (string) $selectedDepartment === (string) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">???????????????</label>
                        <select name="review_status" class="form-select">
                            <?php foreach ($reviewStatusOptions as $statusValue => $statusLabel): ?>
                                <option value="<?= htmlspecialchars($statusValue) ?>" <?= $reviewStatus === $statusValue ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">???????????????</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" placeholder="???????????????????????">
                    </div>
                </form>
            </div>
        </div>

        <div class="table-toolbar table-toolbar--actions">
            <div class="table-toolbar-side">
                <label class="table-page-size">
                    <span>แสดง</span>
                    <select name="per_page" form="dailyScheduleFilterForm">
                        <?php foreach ([10, 20, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>รายการต่อหน้า</span>
                </label>
                <div class="table-export-group">
                    <a class="btn btn-outline-dark btn-pill" href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i>พิมพ์รายงาน</a>
                    <a class="btn btn-outline-dark btn-pill" href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i>ส่งออก PDF</a>
                    <a class="btn btn-dark btn-pill" href="export_report.php?<?= htmlspecialchars($csvQuery) ?>"><i class="bi bi-download"></i>ส่งออก CSV</a>
                </div>
            </div>
        </div>

        <div id="dailyScheduleResults"><?php require __DIR__ . '/../partials/reports/daily_schedule_results.php'; ?></div>
    </section>
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
    const departmentField = form.querySelector('[name="department"]');
    const departmentOption = departmentField && departmentField.options.length
        ? departmentField.options[departmentField.selectedIndex]
        : null;
    const modeValue = modeField ? String(modeField.value || 'daily').trim() : 'daily';
    const dateValue = dateField ? String(dateField.value || '').trim() : '';
    const monthValue = monthField ? parseInt(monthField.value || '0', 10) : 0;
    const yearBeValue = yearField ? parseInt(yearField.value || '0', 10) : 0;
    const departmentValue = departmentField ? String(departmentField.value || '').trim() : '';
    const thaiMonths = ['', '??????', '??????????', '??????', '??????', '???????', '????????', '???????', '???????', '???????', '??????', '?????????', '???????'];

    let periodLabel = '';
    if (modeValue === 'monthly') {
        if (monthValue >= 1 && monthValue <= 12 && yearBeValue >= 2400) {
            periodLabel = '????? ' + (thaiMonths[monthValue] || '') + ' ' + yearBeValue;
        }
    } else if (/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
        const parts = dateValue.split('-');
        periodLabel = '?????? ' + parseInt(parts[2], 10) + ' ' + (thaiMonths[parseInt(parts[1], 10)] || '') + ' ' + (parseInt(parts[0], 10) + 543);
    }

    return {
        periodLabel: periodLabel,
        scopeLabel: departmentValue !== '' && departmentOption
            ? '??????????????????? ' + String(departmentOption.text || '').trim()
            : '?????????????'
    };
}

function syncDailyScheduleLayout(payload) {
    const form = payload && payload.form ? payload.form : document.getElementById('dailyScheduleFilterForm');
    const hero = document.querySelector('.ops-hero-grid');
    const resultsContainer = payload && payload.container ? payload.container : document.getElementById('dailyScheduleResults');

    if (window.TableFilters && typeof window.TableFilters.syncSummaryBlock === 'function') {
        window.TableFilters.syncSummaryBlock(resultsContainer, 'dailyScheduleSummary');
    }

    if (!form || !hero) {
        return;
    }

    const context = buildDailyScheduleHeroContext(form);
    if (!context) {
        return;
    }

    const statValues = hero.querySelectorAll('.ops-hero-stat strong');
    if (statValues[0] && context.periodLabel) {
        statValues[0].textContent = context.periodLabel;
    }
    if (statValues[1] && context.scopeLabel) {
        statValues[1].textContent = context.scopeLabel;
    }
}

TableFilters.init({
    formId: 'dailyScheduleFilterForm',
    containerId: 'dailyScheduleResults',
    endpoint: '../ajax/reports/daily_schedule_rows.php',
    pushBase: 'daily_schedule.php',
    onRefresh: syncDailyScheduleLayout
});

syncDailyScheduleLayout({
    form: document.getElementById('dailyScheduleFilterForm'),
    container: document.getElementById('dailyScheduleResults')
});
</script>
</body>
</html>
