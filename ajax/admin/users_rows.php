<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_manage_user_permissions');

$filters = app_build_manageable_user_filters($_GET);
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, 20);
$totalRows = app_count_manageable_users($conn, $filters);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = app_get_manageable_users($conn, $filters, $perPage, ($page - 1) * $perPage);

function app_manage_users_query(array $filters, array $extra = []): string
{
    return app_build_table_query([
        'fullname' => $filters['fullname'],
        'username' => $filters['username'],
        'position_name' => $filters['position_name'],
        'department' => $filters['department'],
        'role' => $filters['role'],
        'per_page' => $extra['per_page'] ?? 20,
    ], $extra);
}

ajax_html(ajax_capture(function () use ($rows, $page, $perPage, $totalRows, $totalPages, $filters): void {
    require __DIR__ . '/../../partials/admin/manage_users_results.php';
}));
