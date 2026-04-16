<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();

$userId = (int) ($_SESSION['id'] ?? 0);
$searchDate = trim((string) ($_GET['date'] ?? ''));
$limit = app_parse_table_page_size($_GET, 20);
$page = app_parse_table_page($_GET);
$totalRows = app_get_user_time_history_count($conn, $userId, $searchDate);
$totalPages = max(1, (int) ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;
$historyLogs = app_get_user_time_history_rows($conn, $userId, $searchDate, $limit, $offset);
$historyFlags = app_get_user_time_history_flags($conn, $userId, $historyLogs);
$canPrivilegedLockedEdit = app_can('can_edit_locked_time_logs');

$html = ajax_capture(function () use ($historyLogs, $historyFlags, $canPrivilegedLockedEdit, $page, $searchDate, $limit, $totalPages): void {
    require __DIR__ . '/../../partials/time/history_list.php';
});

ajax_html($html);
