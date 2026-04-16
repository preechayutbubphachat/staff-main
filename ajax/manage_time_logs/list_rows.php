<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/profile_modal.php';

app_require_permission('can_manage_time_logs');

$csrfToken = app_csrf_token('manage_time_logs');
$reportData = app_fetch_time_log_report_data($conn, $_GET, 'all');
$filters = $reportData['filters'];
$summary = $reportData['summary'];
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, 20);
$totalRows = (int) ($summary['total_rows'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = array_slice($reportData['rows'], ($page - 1) * $perPage, $perPage);

$html = ajax_capture(function () use ($rows, $summary, $filters, $page, $perPage, $totalPages, $totalRows, $csrfToken): void {
    require __DIR__ . '/../../partials/manage_time_logs/results_block.php';
});

ajax_html($html);
