<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();
ajax_require_method('POST');
ajax_verify_csrf_or_fail('notifications_ajax', $_POST['_csrf'] ?? null);

$userId = (int) ($_SESSION['id'] ?? 0);
$updated = app_mark_all_notifications_read($conn, $userId);

ajax_json([
    'success' => true,
    'message' => $updated > 0 ? 'อ่านทั้งหมดแล้ว' : 'ไม่มีรายการที่ยังไม่ได้อ่าน',
    'updated_count' => $updated,
    'unread_count' => app_get_unread_notification_count($conn, $userId),
]);
