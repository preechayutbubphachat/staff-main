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
$currentReviewStatus = !empty($row['checked_at'])
    ? 'checked'
    : (!empty($row['checked_by']) ? 'returned' : 'pending');
$reviewStatus = trim((string) ($_POST['review_status'] ?? $currentReviewStatus));
$allowedReviewStatuses = ['pending', 'checked', 'returned'];
if (!in_array($reviewStatus, $allowedReviewStatuses, true)) {
    ajax_json(['success' => false, 'message' => 'สถานะรายการไม่ถูกต้อง'], 422);
}
if ($reviewStatus !== $currentReviewStatus && !app_can('can_approve_logs')) {
    ajax_json(['success' => false, 'message' => 'คุณไม่มีสิทธิ์เปลี่ยนสถานะรายการลงเวลาเวร'], 403);
}
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
$actorId = (int) ($_SESSION['id'] ?? 0);
$actorName = (string) ($_SESSION['fullname'] ?? '');
$now = date('Y-m-d H:i:s');
$statusChanged = $reviewStatus !== $currentReviewStatus;
$nextCheckedBy = $beforeRow['checked_by'] ?? null;
$nextCheckedAt = $beforeRow['checked_at'] ?? null;
$nextSignature = $beforeRow['signature'] ?? null;
$nextApprovalNote = $beforeRow['approval_note'] ?? null;

if ($reviewStatus === 'pending') {
    $nextCheckedBy = null;
    $nextCheckedAt = null;
    $nextSignature = null;
    $nextApprovalNote = null;
} elseif ($reviewStatus === 'checked') {
    if (empty($beforeRow['checked_at'])) {
        if (!app_can('can_approve_logs')) {
            ajax_json(['success' => false, 'message' => 'คุณไม่มีสิทธิ์เปลี่ยนรายการเป็นสถานะตรวจแล้ว'], 403);
        }

        $signatureStmt = $conn->prepare('SELECT signature_path FROM users WHERE id = ? LIMIT 1');
        $signatureStmt->execute([$actorId]);
        $checkerSignature = (string) ($signatureStmt->fetchColumn() ?: '');
        if ($checkerSignature === '') {
            ajax_json(['success' => false, 'message' => 'ยังไม่สามารถเปลี่ยนเป็นสถานะตรวจแล้วได้ เนื่องจากยังไม่ได้ตั้งค่าลายเซ็นผู้ตรวจสอบ'], 422);
        }

        $nextCheckedBy = $actorId;
        $nextCheckedAt = $now;
        $nextSignature = 'uploads/signatures/' . $checkerSignature;
    }
    $nextApprovalNote = null;
} elseif ($reviewStatus === 'returned') {
    if ($statusChanged && !app_can('can_approve_logs')) {
        ajax_json(['success' => false, 'message' => 'คุณไม่มีสิทธิ์เปลี่ยนรายการเป็นสถานะตีกลับ'], 403);
    }

    if ($statusChanged) {
        $nextCheckedBy = $actorId;
        $nextCheckedAt = null;
        $nextSignature = null;
        $nextApprovalNote = $note !== '' ? $note : 'เปลี่ยนสถานะเป็นตีกลับ/ไม่อนุมัติจาก modal แก้ไข';
    }
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        UPDATE time_logs
        SET work_date = ?, time_in = ?, time_out = ?, work_hours = ?, note = ?,
            checked_by = ?, checked_at = ?, signature = ?, approval_note = ?, updated_at = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $workDate,
        $range['time_in'],
        $range['time_out'],
        $range['hours'],
        $note,
        $nextCheckedBy,
        $nextCheckedAt,
        $nextSignature,
        $nextApprovalNote,
        $now,
        $timeLogId,
    ]);

    $afterRow = app_get_time_log_by_id($conn, $timeLogId);
    $auditNote = $statusChanged
        ? 'edit_modal status_change: ' . $currentReviewStatus . ' -> ' . $reviewStatus
        : 'แก้ไขข้อมูลจาก modal หน้าจัดการลงเวลาเวร';
    app_insert_time_log_audit(
        $conn,
        $timeLogId,
        $statusChanged ? 'edit_modal_status_change' : 'admin_edit',
        $beforeRow,
        $afterRow,
        $actorId,
        $actorName,
        $auditNote
    );

    if ($afterRow && $reviewStatus === 'checked' && $statusChanged) {
        app_create_approval_completed_notification($conn, $afterRow, $actorName);
    } elseif ($afterRow && $reviewStatus === 'returned' && $statusChanged) {
        app_notify_log_returned($conn, $afterRow, $actorId);
    }
    app_sync_reviewer_queue_notifications($conn);

    $conn->commit();
} catch (Throwable $exception) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    ajax_json(['success' => false, 'message' => 'ไม่สามารถบันทึกการแก้ไขได้ กรุณาลองใหม่อีกครั้ง'], 500);
}

ajax_json([
    'success' => true,
    'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว',
]);
