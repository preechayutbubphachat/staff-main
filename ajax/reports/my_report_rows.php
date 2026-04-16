<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();

$userId = (int) ($_SESSION['id'] ?? 0);
$reportData = app_fetch_my_report_data($conn, $userId, $_GET);
$filters = $reportData['filters'];
$summary = $reportData['summary'];
$logs = $reportData['logs'];
$period = $filters['period'];
$month = $filters['month'];
$year = $filters['year'];
$yearBe = $filters['year_be'] ?? ((int) date('Y') + 543);
$dateFrom = $filters['date_from'];
$dateTo = $filters['date_to'];
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

ajax_html(ajax_capture(function () use ($summary, $pagedLogs, $totalPages, $page, $perPage, $queryBase) {
    require __DIR__ . '/../../partials/reports/my_results.php';
}));
