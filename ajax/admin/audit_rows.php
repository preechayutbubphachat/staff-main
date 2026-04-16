<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/db_admin_helpers.php';

app_require_permission('can_manage_database');

$page = app_parse_table_page($_GET, 'page');
$perPage = app_parse_table_page_size($_GET, 20);
$search = trim((string) ($_GET['q'] ?? ''));
$rows = [];
$totalRows = 0;

if (app_table_exists($conn, 'db_admin_audit_logs')) {
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM db_admin_audit_logs
            WHERE table_name LIKE ? OR action_type LIKE ? OR actor_name_snapshot LIKE ? OR COALESCE(note, '') LIKE ?
        ");
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like, $like]);
        $totalRows = (int) $stmt->fetchColumn();
    } else {
        $totalRows = (int) $conn->query('SELECT COUNT(*) FROM db_admin_audit_logs')->fetchColumn();
    }
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

if (app_table_exists($conn, 'db_admin_audit_logs')) {
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT *
            FROM db_admin_audit_logs
            WHERE table_name LIKE ? OR action_type LIKE ? OR actor_name_snapshot LIKE ? OR COALESCE(note, '') LIKE ?
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");
        $like = '%' . $search . '%';
        $stmt->bindValue(1, $like, PDO::PARAM_STR);
        $stmt->bindValue(2, $like, PDO::PARAM_STR);
        $stmt->bindValue(3, $like, PDO::PARAM_STR);
        $stmt->bindValue(4, $like, PDO::PARAM_STR);
        $stmt->bindValue(5, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(6, $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$tableConfigs = app_db_admin_tables();

ajax_html(ajax_capture(function () use ($rows, $search, $page, $perPage, $totalRows, $totalPages, $tableConfigs): void {
    require __DIR__ . '/../../partials/admin/db_change_log_results.php';
}));
