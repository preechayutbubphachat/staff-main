<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();

$userId = (int) ($_SESSION['id'] ?? 0);
if (app_can('can_approve_logs')) {
    app_sync_reviewer_queue_notifications($conn);
}

ajax_json([
    'success' => true,
    'count' => app_get_unread_notification_count($conn, $userId),
]);
