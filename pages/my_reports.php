<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';

app_require_login();

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
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('my_reports.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="ops-hero mb-4">
        <div class="ops-hero-grid">
            <div>
                <div class="eyebrow mb-2">รายงานส่วนตัว</div>
                <h1 class="mb-2">รายงานการลงเวลาเวรของฉัน</h1>
                <p class="mb-0 text-white-50">ดูข้อมูลส่วนตัวตามช่วงเวลาแบบรายสัปดาห์ รายเดือน รายปี หรือกำหนดเอง พร้อมส่งออกจากชุดตัวกรองเดียวกับที่กำลังแสดงอยู่</p>
            </div>
            <aside class="ops-hero-side">
                <div class="ops-hero-stat">
                    <span>ช่วงรายงาน</span>
                    <strong><?= htmlspecialchars($titleRange) ?></strong>
                </div>
                <div class="ops-hero-stat">
                    <span>ผู้ใช้งาน</span>
                    <strong><?= htmlspecialchars((string) ($_SESSION['fullname'] ?? '-')) ?></strong>
                </div>
            </aside>
        </div>
    </section>

    <section class="panel mb-4">
        <div id="myReportsSummary" class="mb-4"></div>

        <div class="table-toolbar table-toolbar--filters">
            <div class="table-toolbar-main">
                <div class="table-toolbar-title">ตัวกรองรายงาน</div>
                <div class="table-toolbar-help">เปลี่ยนตัวกรองแล้วตารางจะรีเฟรชอัตโนมัติ และปุ่มส่งออกจะอ้างอิงขอบเขตเดียวกับข้อมูลในตารางนี้</div>
                <form method="get" id="myReportsFilterForm" class="table-toolbar-form" data-page-state-key="my_reports">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">รูปแบบรายงาน</label>
                        <select name="period" class="form-select">
                            <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>รายสัปดาห์</option>
                            <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>รายเดือน</option>
                            <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>รายปี</option>
                            <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>กำหนดเอง</option>
                        </select>
                    </div>
                    <?php if ($period === 'month'): ?>
                        <div class="toolbar-col-3">
                            <label class="form-label fw-semibold small text-muted">เดือน</label>
                            <select name="month" class="form-select">
                                <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                    <option value="<?= (int) $monthValue ?>" <?= (int) ($filters['month_number'] ?? 0) === (int) $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="toolbar-col-2">
                            <label class="form-label fw-semibold small text-muted">ปี (พ.ศ.)</label>
                            <input type="number" name="year_be" class="form-control" min="2400" max="2800" step="1" value="<?= htmlspecialchars((string) $yearBe) ?>" inputmode="numeric">
                        </div>
                    <?php elseif ($period === 'year'): ?>
                        <div class="toolbar-col-3">
                            <label class="form-label fw-semibold small text-muted">ปี</label>
                            <input type="number" name="year" class="form-control" min="2020" max="2100" value="<?= htmlspecialchars((string) $year) ?>">
                        </div>
                    <?php elseif ($period === 'custom'): ?>
                        <div class="toolbar-col-3">
                            <label class="form-label fw-semibold small text-muted">วันที่เริ่ม</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="toolbar-col-3">
                            <label class="form-label fw-semibold small text-muted">วันที่สิ้นสุด</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-toolbar table-toolbar--actions">
            <div class="table-toolbar-side">
                <label class="table-page-size">
                    <span>แสดง</span>
                    <select name="per_page" form="myReportsFilterForm">
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

        <div id="myReportsResults"><?php require __DIR__ . '/../partials/reports/my_results.php'; ?></div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/table-filters.js"></script>
<script>
function syncMyReportsLayout(payload) {
    const resultsContainer = payload && payload.container ? payload.container : document.getElementById('myReportsResults');
    if (window.TableFilters && typeof window.TableFilters.syncSummaryBlock === 'function') {
        window.TableFilters.syncSummaryBlock(resultsContainer, 'myReportsSummary');
    }
}

TableFilters.init({
    formId: 'myReportsFilterForm',
    containerId: 'myReportsResults',
    endpoint: '../ajax/reports/my_report_rows.php',
    pushBase: 'my_reports.php',
    onRefresh: syncMyReportsLayout
});

syncMyReportsLayout({
    container: document.getElementById('myReportsResults')
});
</script>
</body>
</html>
