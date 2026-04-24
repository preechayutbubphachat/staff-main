<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();

$userId = (int) ($_SESSION['id'] ?? 0);
$historyFilters = app_normalize_user_time_history_filters([
    'date' => $_GET['date'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? 'all',
    'query' => $_GET['query'] ?? '',
]);
$limit = app_parse_table_page_size($_GET, 20);
$page = app_parse_table_page($_GET);
$totalRows = app_get_user_time_history_count($conn, $userId, $historyFilters);
$totalPages = max(1, (int) ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;
$historyLogs = app_get_user_time_history_rows($conn, $userId, $historyFilters, $limit, $offset);
$historyFlags = app_get_user_time_history_flags($conn, $userId, $historyLogs);
$canPrivilegedLockedEdit = app_can('can_edit_locked_time_logs');
$historyDate = (string) ($historyFilters['date'] ?? '');
$dateFrom = (string) ($historyFilters['date_from'] ?? '');
$dateTo = (string) ($historyFilters['date_to'] ?? '');
$historyStatus = (string) ($historyFilters['status'] ?? 'all');
$historyQuery = (string) ($historyFilters['query'] ?? '');

$html = ajax_capture(function () use ($historyLogs, $historyFlags, $canPrivilegedLockedEdit, $page, $historyDate, $dateFrom, $dateTo, $historyStatus, $historyQuery, $limit, $totalPages): void {
    require __DIR__ . '/../../partials/time/history_list.php';
});

ajax_html($html);
