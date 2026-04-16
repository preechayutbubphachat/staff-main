<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();
ajax_require_method('POST');
ajax_verify_csrf_or_fail('time_page_edit', $_POST['_csrf'] ?? null);

$userId = (int) ($_SESSION['id'] ?? 0);
$timeLogId = max(0, (int) ($_POST['id'] ?? 0));
$editStmt = $conn->prepare("SELECT * FROM time_logs WHERE id = ? AND user_id = ? LIMIT 1");
$editStmt->execute([$timeLogId, $userId]);
$editLog = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$editLog) {
    ajax_json(['success' => false, 'message' => 'ไม่พบรายการลงเวลาเวรที่ต้องการแก้ไข'], 404);
}

$canPrivilegedLockedEdit = app_can('can_edit_locked_time_logs');
if (app_time_log_is_locked($editLog) && !$canPrivilegedLockedEdit) {
    ajax_json(['success' => false, 'message' => 'รายการนี้ได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขได้'], 403);
}

$editNote = trim((string) ($_POST['edit_note'] ?? ''));
$editDepartmentId = app_can('can_view_department_reports')
    ? (int) ($_POST['edit_department_id'] ?? $editLog['department_id'])
    : (int) $editLog['department_id'];
$timeInVal = app_parse_time_input($_POST, 'edit_time_in', '24h');
$timeOutVal = app_parse_time_input($_POST, 'edit_time_out', '24h');

if ($timeInVal === null || $timeOutVal === null) {
    ajax_json(['success' => false, 'message' => 'กรุณาระบุเวลาเข้าและเวลาออกในรูปแบบ 24 ชั่วโมงให้ถูกต้อง'], 422);
}

$range = app_build_time_log_range((string) $editLog['work_date'], $timeInVal, $timeOutVal);
if ($range === null) {
    ajax_json(['success' => false, 'message' => 'ไม่สามารถคำนวณช่วงเวลาได้'], 422);
}

$overlap = app_find_overlapping_time_log($conn, $userId, $range['time_in'], $range['time_out'], (int) $editLog['id']);
if ($overlap) {
    ajax_json([
        'success' => false,
        'message' => sprintf(
            'ช่วงเวลานี้ชนกับรายการเดิมวันที่ %s เวลา %s - %s',
            app_format_thai_date((string) $overlap['work_date']),
            date('H:i', strtotime($overlap['time_in'])),
            date('H:i', strtotime($overlap['time_out']))
        ),
    ], 422);
}

$actorName = (string) ($_SESSION['fullname'] ?? 'ผู้ใช้งาน');
$oldValues = $editLog;

$updateStmt = $conn->prepare("
    UPDATE time_logs
    SET department_id = ?, time_in = ?, time_out = ?, work_hours = ?, note = ?, status = 'submitted', checked_by = NULL, checked_at = NULL, signature = NULL, approval_note = NULL
    WHERE id = ? AND user_id = ?
");
$updateStmt->execute([
    $editDepartmentId,
    $range['time_in'],
    $range['time_out'],
    $range['hours'],
    $editNote,
    $editLog['id'],
    $userId,
]);

$newValues = array_merge($editLog, [
    'department_id' => $editDepartmentId,
    'time_in' => $range['time_in'],
    'time_out' => $range['time_out'],
    'work_hours' => $range['hours'],
    'note' => $editNote,
    'status' => 'submitted',
    'checked_by' => null,
    'checked_at' => null,
    'signature' => null,
    'approval_note' => null,
]);
app_insert_time_log_audit(
    $conn,
    (int) $editLog['id'],
    'self_service_update',
    $oldValues,
    $newValues,
    $userId,
    $actorName,
    'แก้ไขจากหน้าลงเวลาเวร'
);
app_sync_reviewer_queue_notifications($conn);

ajax_json([
    'success' => true,
    'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว',
]);
