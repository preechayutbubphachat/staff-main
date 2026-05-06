<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/db_admin_helpers.php';

app_require_permission('can_manage_database');

$page = app_parse_table_page($_GET, 'page');
$perPage = app_parse_table_page_size($_GET, 20);
$search = trim((string) ($_GET['q'] ?? ''));
$tableFilter = trim((string) ($_GET['table_name'] ?? ''));
$actionFilter = trim((string) ($_GET['action_type'] ?? ''));
$actorFilter = trim((string) ($_GET['actor'] ?? ''));
$dateFilter = trim((string) ($_GET['log_date'] ?? ''));
$rows = [];
$totalRows = 0;
$totalPages = 1;
$tableConfigs = app_db_admin_tables();

if (app_table_exists($conn, 'db_admin_audit_logs')) {
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "(table_name LIKE ? OR action_type LIKE ? OR actor_name_snapshot LIKE ? OR COALESCE(note, '') LIKE ?)";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($tableFilter !== '') {
        $where[] = 'table_name = ?';
        $params[] = $tableFilter;
    }
    if ($actionFilter !== '') {
        $where[] = 'action_type = ?';
        $params[] = $actionFilter;
    }
    if ($actorFilter !== '') {
        $where[] = 'actor_name_snapshot = ?';
        $params[] = $actorFilter;
    }
    if ($dateFilter !== '') {
        $where[] = 'DATE(created_at) = ?';
        $params[] = $dateFilter;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM db_admin_audit_logs' . $whereSql);
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare('SELECT * FROM db_admin_audit_logs' . $whereSql . ' ORDER BY id DESC LIMIT ? OFFSET ?');
    $bindIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($bindIndex++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$baseQuery = [
    'q' => $search,
    'table_name' => $tableFilter,
    'action_type' => $actionFilter,
    'actor' => $actorFilter,
    'log_date' => $dateFilter,
    'per_page' => $perPage,
];
$printQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs']);
$pdfQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs', 'download' => 'pdf']);
$csvQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs']);

ajax_html(ajax_capture(function () use (
    $rows,
    $search,
    $tableFilter,
    $actionFilter,
    $actorFilter,
    $dateFilter,
    $page,
    $perPage,
    $totalRows,
    $totalPages,
    $tableConfigs,
    $printQuery,
    $pdfQuery,
    $csvQuery
): void {
    require __DIR__ . '/../../partials/admin/db_change_log_results.php';
}));
