<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_approve_logs');
ajax_require_method('POST');
ajax_verify_csrf_or_fail('approval_queue', $_POST['_csrf'] ?? null);

$timeLogId = (int) ($_POST['id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));

if ($timeLogId <= 0) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่พบรหัสรายการลงเวลาเวร',
    ], 400);
}

if ($reason === '') {
    ajax_json([
        'success' => false,
        'message' => 'กรุณาระบุเหตุผลการตีกลับ/ไม่อนุมัติ',
    ], 422);
}

$reason = mb_substr($reason, 0, 1000, 'UTF-8');
$reviewerId = (int) ($_SESSION['id'] ?? 0);
$reviewerName = (string) ($_SESSION['fullname'] ?? '');
$beforeRow = app_get_time_log_by_id($conn, $timeLogId);

if (!$beforeRow) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่พบรายการลงเวลาเวรที่ต้องการตีกลับ',
    ], 404);
}

if (!app_time_log_within_scope($conn, $beforeRow)) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่มีสิทธิ์ตีกลับรายการลงเวลาเวรนี้',
    ], 403);
}

if (empty($beforeRow['time_out'])) {
    ajax_json([
        'success' => false,
        'message' => 'รายการนี้ยังไม่มีเวลาออก จึงยังไม่พร้อมสำหรับการตรวจสอบ',
    ], 422);
}

if (!empty($beforeRow['checked_at'])) {
    ajax_json([
        'success' => false,
        'message' => 'รายการนี้ถูกอนุมัติไปแล้ว กรุณารีเฟรชรายการตรวจสอบ',
    ], 409);
}

if (!empty($beforeRow['checked_by'])) {
    ajax_json([
        'success' => false,
        'message' => 'รายการนี้ถูกตีกลับหรือเปลี่ยนสถานะแล้ว กรุณารีเฟรชรายการตรวจสอบ',
    ], 409);
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare('
        UPDATE time_logs
        SET checked_by = ?, checked_at = NULL, signature = NULL, approval_note = ?, updated_at = ?
        WHERE id = ? AND time_out IS NOT NULL AND checked_at IS NULL AND checked_by IS NULL
    ');
    $stmt->execute([$reviewerId, $reason, date('Y-m-d H:i:s'), $timeLogId]);

    if ($stmt->rowCount() !== 1) {
        $conn->rollBack();
        ajax_json([
            'success' => false,
            'message' => 'สถานะรายการเปลี่ยนไประหว่างดำเนินการ กรุณารีเฟรชรายการตรวจสอบ',
        ], 409);
    }

    $afterRow = app_get_time_log_by_id($conn, $timeLogId);
    app_insert_time_log_audit(
        $conn,
        $timeLogId,
        'returned',
        $beforeRow,
        $afterRow,
        $reviewerId,
        $reviewerName,
        'ตีกลับ/ไม่อนุมัติจาก modal รายละเอียดรายการลงเวลาเวร: ' . $reason
    );

    if ($afterRow) {
        app_notify_log_returned($conn, $afterRow, $reviewerId);
    }
    app_sync_reviewer_queue_notifications($conn);

    $conn->commit();
} catch (Throwable $exception) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    ajax_json([
        'success' => false,
        'message' => 'ไม่สามารถตีกลับรายการได้ กรุณาลองใหม่อีกครั้ง',
    ], 500);
}

ajax_json([
    'success' => true,
    'message' => 'ตีกลับ/ไม่อนุมัติรายการเรียบร้อยแล้ว',
    'status_label' => 'ตีกลับแก้ไข',
]);
