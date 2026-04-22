<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_permission('can_view_department_reports');

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
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('department_reports.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="ops-hero mb-4">
        <div class="ops-hero-grid">
            <div>
                <div class="eyebrow mb-2">รายงานแผนก</div>
                <h1 class="mb-2"><?= htmlspecialchars($headingContext['heading_text']) ?></h1>
                <p class="mb-0 text-white-50"><?= htmlspecialchars($headingContext['subheading_text']) ?></p>
            </div>
            <aside class="ops-hero-side">
                <div class="ops-hero-stat">
                    <span>ขอบเขตรายงาน</span>
                    <strong><?= htmlspecialchars($headingContext['department_label']) ?></strong>
                </div>
                <div class="ops-hero-stat">
                    <span>ช่วงเดือน</span>
                    <strong><?= htmlspecialchars($headingContext['month_year_label']) ?></strong>
                </div>
            </aside>
        </div>
    </section>

    <section class="panel mb-4">
        <div id="departmentReportsSummary" class="mb-4"></div>

        <div class="table-toolbar table-toolbar--filters">
            <div class="table-toolbar-main">
                <div class="table-toolbar-title">ตัวกรองรายงานแผนก</div>
                <div class="table-toolbar-help">เลือกแผนก เดือน และปี แล้วผลลัพธ์จะอัปเดตทันที</div>
                <form method="get" id="departmentReportsFilterForm" class="table-toolbar-form" data-page-state-key="department_reports">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <div class="toolbar-col-4">
                        <label class="form-label fw-semibold small text-muted">ขอบเขตรายงาน</label>
                        <select name="department_id" class="form-select">
                            <option value="">ทั้งหมดตามสิทธิ์ที่เข้าถึงได้</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= $filters['selected_department_id'] === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">เดือน</label>
                        <select name="month" class="form-select">
                            <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                <option value="<?= (int) $monthValue ?>" <?= (int) $filters['month_number'] === (int) $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">ปี (พ.ศ.)</label>
                        <input type="number" name="year_be" class="form-control" min="2400" max="2800" step="1" value="<?= htmlspecialchars((string) $filters['year_be']) ?>" inputmode="numeric">
                    </div>
                </form>
            </div>
        </div>

        <div class="table-toolbar table-toolbar--actions">
            <div class="table-toolbar-side">
                <label class="table-page-size">
                    <span>แสดง</span>
                    <select name="per_page" form="departmentReportsFilterForm">
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

        <div id="departmentReportsResults"><?php require __DIR__ . '/../partials/reports/department_results.php'; ?></div>
    </section>
</main>
<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/table-filters.js"></script>
<script>
function buildDepartmentReportMonthLabel(form) {
    const monthField = form ? form.querySelector('[name="month"]') : null;
    const yearField = form ? form.querySelector('[name="year_be"]') : null;
    const selectedMonthOption = monthField && monthField.options.length
        ? monthField.options[monthField.selectedIndex]
        : null;
    const monthLabel = selectedMonthOption ? String(selectedMonthOption.text || '').trim() : '';
    const yearBe = yearField ? String(yearField.value || '').trim() : '';

    if (!monthLabel || !yearBe) {
        return '';
    }

    return monthLabel + ' ' + yearBe;
}

function buildDepartmentHeroContext(form) {
    if (!form) {
        return null;
    }

    const departmentField = form.querySelector('[name="department_id"]');
    const selectedDepartmentOption = departmentField && departmentField.options.length
        ? departmentField.options[departmentField.selectedIndex]
        : null;
    const departmentId = departmentField ? String(departmentField.value || '').trim() : '';
    const selectedDepartmentName = selectedDepartmentOption ? String(selectedDepartmentOption.text || '').trim() : '';
    const isSpecificDepartment = departmentId !== '' && departmentId !== '0' && selectedDepartmentName !== '';
    const departmentLabel = isSpecificDepartment ? 'แผนก ' + selectedDepartmentName : 'แผนกทั้งหมด';
    const monthYearLabel = buildDepartmentReportMonthLabel(form);

    return {
        departmentLabel: departmentLabel,
        monthYearLabel: monthYearLabel,
        headingText: 'รายงานสรุป' + departmentLabel + (monthYearLabel ? ' ประจำเดือน ' + monthYearLabel : ''),
        subheadingText: isSpecificDepartment
            ? 'ข้อมูลสรุปของแผนกที่เลือกตามตัวกรองปัจจุบัน'
            : 'ข้อมูลสรุปของทุกแผนกตามสิทธิ์ที่เข้าถึงได้ในช่วงเวลาที่เลือก'
    };
}

function syncDepartmentReportLayout(payload) {
    const form = payload && payload.form ? payload.form : document.getElementById('departmentReportsFilterForm');
    const hero = document.querySelector('.ops-hero-grid');
    const resultsContainer = payload && payload.container ? payload.container : document.getElementById('departmentReportsResults');

    if (window.TableFilters && typeof window.TableFilters.syncSummaryBlock === 'function') {
        window.TableFilters.syncSummaryBlock(resultsContainer, 'departmentReportsSummary');
    }

    if (!form || !hero) {
        return;
    }

    const context = buildDepartmentHeroContext(form);
    if (!context) {
        return;
    }

    const heading = hero.querySelector('h1');
    const helper = hero.querySelector('p');
    const statValues = hero.querySelectorAll('.ops-hero-stat strong');

    if (heading) {
        heading.textContent = context.headingText;
    }

    if (helper) {
        helper.textContent = context.subheadingText;
    }

    if (statValues[0]) {
        statValues[0].textContent = context.departmentLabel;
    }

    if (statValues[1]) {
        statValues[1].textContent = context.monthYearLabel;
    }
}

TableFilters.init({
    formId: 'departmentReportsFilterForm',
    containerId: 'departmentReportsResults',
    endpoint: '../ajax/reports/department_rows.php',
    pushBase: 'department_reports.php',
    onRefresh: syncDepartmentReportLayout
});

syncDepartmentReportLayout({
    form: document.getElementById('departmentReportsFilterForm'),
    container: document.getElementById('departmentReportsResults')
});
</script>
</body>
</html>
