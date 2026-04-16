<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/profile_modal.php';

app_require_login();

$view = $_GET['view'] ?? 'table';
$view = in_array($view, ['cards', 'table'], true) ? $view : 'table';
$schedule = app_fetch_daily_schedule_data($conn, $_GET);
$mode = $schedule['mode'];
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
$scopeLabel = $headingContext['scope_label'];
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

ajax_html(ajax_capture(function () use ($schedule, $view, $pagedLogs, $pagedGroups, $dateLabel, $dateHeading, $scopeLabel, $page, $perPage, $totalPages, $totalRows, $queryBase): void {
    require __DIR__ . '/../../partials/reports/daily_schedule_results.php';
}));
