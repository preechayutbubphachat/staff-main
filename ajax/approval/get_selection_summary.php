<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_approve_logs');
ajax_require_method('POST');
ajax_verify_csrf_or_fail('approval_queue', $_POST['_csrf'] ?? null);

$selectedIds = is_array($_POST['selected_ids'] ?? null) ? $_POST['selected_ids'] : [];
$summary = app_get_selected_time_log_summary($conn, $selectedIds, true, app_get_accessible_departments($conn)['ids']);
$rows = $summary['rows'];
$staff = [];
foreach ($rows as $row) {
    $name = trim((string) ($row['fullname'] ?? ''));
    if ($name === '') {
        continue;
    }
    $staff[$name] = [
        'name' => $name,
        'user_id' => (int) ($row['user_id'] ?? 0),
    ];
}

ajax_json([
    'success' => true,
    'count' => $summary['count'],
    'staff_count' => count($summary['staff_names']),
    'department_count' => count($summary['departments']),
    'staff' => array_values($staff),
    'departments' => $summary['departments'],
    'ids' => $summary['ids'],
]);
