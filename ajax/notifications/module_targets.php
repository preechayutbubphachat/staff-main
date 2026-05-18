<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/navigation.php';

app_require_login();
ajax_require_method('POST');
ajax_verify_csrf_or_fail('notifications_ajax', $_POST['_csrf'] ?? null);

$userId = (int) ($_SESSION['id'] ?? 0);
$moduleKey = trim((string) ($_POST['module'] ?? ''));

if ($moduleKey === '' || !isset(app_notification_sidebar_mapping()[$moduleKey])) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่พบกลุ่มการแจ้งเตือนที่ต้องการ',
    ], 422);
}

if (!app_sidebar_notification_key_is_visible($moduleKey)) {
    ajax_json([
        'success' => false,
        'message' => 'ไม่มีสิทธิ์อ่านการแจ้งเตือนกลุ่มนี้',
    ], 403);
}

ajax_json([
    'success' => true,
    'module' => $moduleKey,
    'targets' => app_get_unread_notification_targets_for_sidebar_key($conn, $userId, $moduleKey, 5),
    'unread_count' => app_get_unread_notification_count($conn, $userId),
    'sidebar_counts' => app_get_sidebar_notification_counts($conn, $userId),
]);
