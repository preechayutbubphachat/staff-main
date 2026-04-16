<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();
ajax_require_method('POST');
ajax_verify_csrf_or_fail('notifications_ajax', $_POST['_csrf'] ?? null);

$userId = (int) ($_SESSION['id'] ?? 0);
$notificationId = max(0, (int) ($_POST['id'] ?? 0));

if ($notificationId <= 0) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่พบการแจ้งเตือนที่ต้องการ',
    ], 422);
}

$marked = app_mark_notification_read($conn, $userId, $notificationId);

ajax_json([
    'success' => $marked,
    'message' => $marked ? 'ทำเครื่องหมายว่าอ่านแล้ว' : 'ไม่พบการแจ้งเตือนที่ต้องการ',
    'unread_count' => app_get_unread_notification_count($conn, $userId),
], $marked ? 200 : 404);
