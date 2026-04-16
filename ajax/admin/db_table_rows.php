<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/db_admin_helpers.php';
require_once __DIR__ . '/../../includes/profile_modal.php';

app_require_permission('can_manage_database');

$table = trim((string) ($_GET['table'] ?? ''));
$config = app_db_admin_get_table_config($table);
if (!$config) {
    ajax_html('<div class="ops-empty">ไม่พบตารางที่อนุญาตให้จัดการ</div>', 404);
}

$filters = app_db_admin_build_filters($table, $_GET, $config);
$page = max(1, (int) $filters['page']);
$perPage = (int) ($filters['per_page'] ?? 20);
$totalRows = app_db_admin_count_rows($conn, $config, $filters);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = app_db_admin_fetch_rows($conn, $config, array_merge($filters, ['page' => $page]), $perPage, ($page - 1) * $perPage);
$csrfToken = app_csrf_token('db_table_browser');

ajax_html(ajax_capture(function () use ($config, $table, $filters, $rows, $page, $perPage, $totalRows, $totalPages, $csrfToken): void {
    require __DIR__ . '/../../partials/admin/db_table_results.php';
}));
