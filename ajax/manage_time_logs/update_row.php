<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_manage_time_logs');
ajax_require_method('POST');
ajax_verify_csrf_or_fail('manage_time_logs_ajax', $_POST['_csrf'] ?? null);

$timeLogId = (int) ($_POST['id'] ?? 0);
$row = $timeLogId > 0 ? app_get_time_log_by_id($conn, $timeLogId) : null;
if (!$row) {
    ajax_json(['success' => false, 'message' => 'ไม่พบรายการลงเวลาเวรที่ต้องการ'], 404);
}
if (!app_time_log_within_scope($conn, $row)) {
    ajax_json(['success' => false, 'message' => 'รายการนี้อยู่นอกขอบเขตสิทธิ์ที่จัดการได้'], 403);
}
if (!app_can_edit_time_log_record($row)) {
    ajax_json(['success' => false, 'message' => 'รายการนี้ถูกล็อกแล้ว และบัญชีนี้ไม่มีสิทธิ์แก้ไขรายการที่อนุมัติแล้ว'], 403);
}

$workDate = trim((string) ($_POST['work_date'] ?? ''));
$timeIn = trim((string) ($_POST['time_in'] ?? ''));
$timeOut = trim((string) ($_POST['time_out'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
if ($workDate === '' || $timeIn === '' || $timeOut === '') {
    ajax_json(['success' => false, 'message' => 'กรุณากรอกวันที่ เวลาเข้า และเวลาออกให้ครบถ้วน'], 422);
}

$range = app_build_time_log_range($workDate, $timeIn, $timeOut);
if ($range === null) {
    ajax_json(['success' => false, 'message' => 'รูปแบบวันที่หรือเวลาไม่ถูกต้อง'], 422);
}

$overlap = app_find_overlapping_time_log($conn, (int) $row['user_id'], $range['time_in'], $range['time_out'], (int) $row['id']);
if ($overlap) {
    ajax_json([
        'success' => false,
        'message' => sprintf(
            'เวลาแก้ไขชนกับรายการเดิมวันที่ %s เวลา %s - %s',
            app_format_thai_date((string) $overlap['work_date']),
            date('H:i', strtotime($overlap['time_in'])),
            date('H:i', strtotime($overlap['time_out']))
        ),
    ], 422);
}

$beforeRow = $row;
$stmt = $conn->prepare("UPDATE time_logs SET work_date = ?, time_in = ?, time_out = ?, work_hours = ?, note = ?, checked_by = NULL, checked_at = NULL, signature = NULL WHERE id = ?");
$stmt->execute([$workDate, $range['time_in'], $range['time_out'], $range['hours'], $note, $timeLogId]);
$afterRow = app_get_time_log_by_id($conn, $timeLogId);
app_insert_time_log_audit($conn, $timeLogId, 'admin_edit', $beforeRow, $afterRow, (int) ($_SESSION['id'] ?? 0), (string) ($_SESSION['fullname'] ?? ''), 'แก้ไขข้อมูลจากหน้าจัดการลงเวลาเวรแบบไม่รีเฟรชหน้า');
if ($afterRow && (!empty($beforeRow['checked_at']) || !empty($beforeRow['checked_by']))) {
    app_notify_log_returned($conn, $afterRow, (int) ($_SESSION['id'] ?? 0));
}
app_sync_reviewer_queue_notifications($conn);

ajax_json([
    'success' => true,
    'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว และรีเซ็ตสถานะอนุมัติเดิมให้ตรวจสอบใหม่',
]);
