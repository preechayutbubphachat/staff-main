<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();
ajax_require_method('POST');
ajax_verify_csrf_or_fail('time_page_delete', $_POST['delete_csrf'] ?? null);

$userId = (int) ($_SESSION['id'] ?? 0);
$timeLogId = max(0, (int) ($_POST['id'] ?? 0));

if ($timeLogId <= 0) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่พบรายการที่ต้องการลบ',
    ], 422);
}

$deleteStmt = $conn->prepare('SELECT * FROM time_logs WHERE id = ? AND user_id = ? LIMIT 1');
$deleteStmt->execute([$timeLogId, $userId]);
$timeLog = $deleteStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$timeLog) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่พบรายการลงเวลาเวรที่ต้องการลบ',
    ], 404);
}

$canPrivilegedLockedEdit = app_can('can_edit_locked_time_logs');
if (app_time_log_is_locked($timeLog) && !$canPrivilegedLockedEdit) {
    ajax_json([
        'success' => false,
        'message' => 'รายการนี้ได้รับการอนุมัติแล้ว ไม่สามารถลบได้',
    ], 403);
}

$actorName = (string) ($_SESSION['fullname'] ?? 'ผู้ใช้งาน');
$oldValues = $timeLog;

try {
    $conn->beginTransaction();

    app_insert_time_log_audit(
        $conn,
        (int) $timeLog['id'],
        'self_service_delete',
        $oldValues,
        null,
        $userId,
        $actorName,
        'ลบจากหน้าลงเวลาเวร'
    );

    $stmt = $conn->prepare('DELETE FROM time_logs WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $timeLog['id'], $userId]);

    app_sync_reviewer_queue_notifications($conn);

    $conn->commit();

    ajax_json([
        'success' => true,
        'message' => 'ลบรายการลงเวลาเวรเรียบร้อยแล้ว',
        'deleted_id' => (int) $timeLog['id'],
    ]);
} catch (Throwable $exception) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Time log delete failed: ' . $exception->getMessage());

    ajax_json([
        'success' => false,
        'message' => 'ไม่สามารถลบรายการได้ กรุณาลองใหม่อีกครั้ง',
    ], 500);
}
