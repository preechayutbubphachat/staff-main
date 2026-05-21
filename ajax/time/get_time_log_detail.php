<?php
/**
 * General-purpose time log detail endpoint.
 *
 * Permission rules:
 *   - Logged-in user who owns the log: always allowed.
 *   - Users with can_view_all_staff, can_approve_logs, or role=admin: allowed for any log
 *     within their accessible department scope.
 *
 * Response: JSON { success, record, audit }
 */
require_once __DIR__ . '/../bootstrap.php';

app_require_login();
ajax_require_method('GET');

$timeLogId = (int) ($_GET['id'] ?? 0);
if ($timeLogId <= 0) {
    ajax_json(['success' => false, 'message' => 'ไม่พบรหัสรายการลงเวลาเวร'], 400);
}

$row = app_get_time_log_by_id($conn, $timeLogId);
if (!$row) {
    ajax_json(['success' => false, 'message' => 'ไม่พบรายการลงเวลาเวรที่ต้องการ'], 404);
}

// --- Permission check ---
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$isOwner       = (int) ($row['user_id'] ?? 0) === $currentUserId;
$canViewAll    = app_can('can_view_all_staff') || app_can('can_approve_logs') || (($_SESSION['role'] ?? '') === 'admin');

if (!$isOwner && !$canViewAll) {
    ajax_json(['success' => false, 'message' => 'ไม่มีสิทธิ์ดูรายการลงเวลาเวรนี้'], 403);
}

// For non-owners with broad access, also verify department scope
if (!$isOwner && $canViewAll && !app_time_log_within_scope($conn, $row)) {
    ajax_json(['success' => false, 'message' => 'ไม่มีสิทธิ์ดูรายการลงเวลาเวรนี้'], 403);
}

// --- Fetch profile image (checks uploads/avatars and uploads/profiles) ---
$profileImageUrl = app_resolve_user_image_url($row['profile_image_path'] ?? '');

// --- Audit trail (optional table) ---
$auditRows = [];
if (app_table_exists($conn, 'time_log_audit_trails')) {
    $auditStmt = $conn->prepare("
        SELECT id, action_type, actor_name_snapshot, created_at, note
        FROM time_log_audit_trails
        WHERE time_log_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 3
    ");
    $auditStmt->execute([$timeLogId]);
    $auditRows = $auditStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// --- Format helpers ---
$formatDateTime = static function (?string $value): string {
    $value = trim((string) $value);
    return $value !== '' ? app_format_thai_datetime($value, true) : '-';
};
$formatClock = static function (?string $value): string {
    $value = trim((string) $value);
    return $value !== '' ? date('H:i', strtotime($value)) : '-';
};

// --- Status resolution ---
$timeIn  = trim((string) ($row['time_in'] ?? ''));
$timeOut = trim((string) ($row['time_out'] ?? ''));

$isApproved  = !empty($row['checked_at']);
$isPending   = app_time_log_is_pending($row);
$isReturned  = !empty($row['checked_by']) && empty($row['checked_at']);
$statusLabel = $isApproved ? 'อนุมัติแล้ว' : ($isReturned ? 'ตีกลับแก้ไข' : ($isPending ? 'รอตรวจ' : '-'));
$statusClass = $isApproved ? 'success' : ($isReturned ? 'danger' : ($isPending ? 'warning' : 'neutral'));

ajax_json([
    'success' => true,
    'record'  => [
        'id'                => (int) ($row['id'] ?? 0),
        'user_id'           => (int) ($row['user_id'] ?? 0),
        'department_id'     => (int) ($row['department_id'] ?? 0),
        'fullname'          => (string) ($row['fullname'] ?? '-') ?: '-',
        'position_name'     => (string) ($row['position_name'] ?? '-') ?: '-',
        'department_name'   => (string) ($row['department_name'] ?? '-') ?: '-',
        'profile_image_url' => $profileImageUrl,
        'work_date'         => !empty($row['work_date']) ? app_format_thai_date((string) $row['work_date'], true) : '-',
        'work_date_raw'     => (string) ($row['work_date'] ?? ''),
        'time_in'           => $formatClock($timeIn),
        'time_out'          => $formatClock($timeOut),
        'time_range'        => ($timeIn !== '' || $timeOut !== '')
                                ? ($formatClock($timeIn) . ' - ' . $formatClock($timeOut))
                                : '-',
        'work_hours'        => isset($row['work_hours'])
                                ? number_format((float) $row['work_hours'], 2) . ' ชม.'
                                : '-',
        'shift_type'        => '-',
        'note'              => trim((string) ($row['note'] ?? '')) !== '' ? (string) $row['note'] : '-',
        'status_label'      => $statusLabel,
        'status_class'      => $statusClass,
        'checker_name'      => (string) ($row['checker_name'] ?? '') ?: '-',
        'checked_at'        => $formatDateTime($row['checked_at'] ?? null),
        'created_at'        => $formatDateTime($row['created_at'] ?? null),
        'updated_at'        => $formatDateTime($row['updated_at'] ?? null),
        'approval_note'     => trim((string) ($row['approval_note'] ?? '')) !== ''
                                ? (string) $row['approval_note']
                                : '-',
        'is_approved'       => $isApproved,
        'is_pending'        => $isPending,
        'is_returned'       => $isReturned,
    ],
    'audit' => array_map(static function (array $a): array {
        return [
            'id'          => (int) ($a['id'] ?? 0),
            'action_type' => (string) ($a['action_type'] ?? '-') ?: '-',
            'actor_name'  => (string) ($a['actor_name_snapshot'] ?? '-') ?: '-',
            'created_at'  => !empty($a['created_at']) ? app_format_thai_datetime((string) $a['created_at'], true) : '-',
            'note'        => trim((string) ($a['note'] ?? '')) !== '' ? (string) $a['note'] : '-',
        ];
    }, $auditRows),
]);
