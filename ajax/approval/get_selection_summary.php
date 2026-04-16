<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_approve_logs');
ajax_require_method('POST');
ajax_verify_csrf_or_fail('approval_queue', $_POST['_csrf'] ?? null);

$selectedIds = is_array($_POST['selected_ids'] ?? null) ? $_POST['selected_ids'] : [];
$summary = app_get_selected_time_log_summary($conn, $selectedIds, true, app_get_accessible_departments($conn)['ids']);
$rows = $summary['rows'];
$staff = [];
 $selectedRows = [];
foreach ($rows as $row) {
    $name = trim((string) ($row['fullname'] ?? ''));
    if ($name === '') {
        $name = '-';
    }
    $staff[$name] = [
        'name' => $name,
        'user_id' => (int) ($row['user_id'] ?? 0),
    ];

    $timeIn = trim((string) ($row['time_in'] ?? ''));
    $timeOut = trim((string) ($row['time_out'] ?? ''));
    $timeRange = '-';
    if ($timeIn !== '' || $timeOut !== '') {
        $timeRange = ($timeIn !== '' ? date('H.i', strtotime($timeIn)) . ' น.' : '-') . ' - ' . ($timeOut !== '' ? date('H.i', strtotime($timeOut)) . ' น.' : '-');
    }

    $selectedRows[] = [
        'id' => (int) ($row['id'] ?? 0),
        'date' => app_format_thai_date((string) ($row['work_date'] ?? '')),
        'name' => $name,
        'position_name' => (string) ($row['position_name'] ?? '-') ?: '-',
        'department_name' => (string) ($row['department_name'] ?? '-') ?: '-',
        'time_range' => $timeRange,
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
    'rows' => $selectedRows,
]);
