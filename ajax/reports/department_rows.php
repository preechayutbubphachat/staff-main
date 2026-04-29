<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/profile_modal.php';

app_require_permission('can_view_department_reports');

$reportData = app_fetch_department_report_data($conn, $_GET);
$filters = $reportData['filters'];
$staffRows = $reportData['staff_rows'];
$departmentTotals = $reportData['department_totals'];
$view = $filters['view'];
$headingContext = $reportData['heading_context'] ?? app_get_department_report_heading_context($filters);
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

ajax_html(ajax_capture(function () use ($departmentTotals, $filters, $staffRows, $view, $pagedRows, $totalRows, $totalPages, $page, $perPage, $queryBase, $headingContext) {
    require __DIR__ . '/../../partials/reports/department_results.php';
}));
