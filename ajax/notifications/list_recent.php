<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();

$userId = (int) ($_SESSION['id'] ?? 0);
$limit = max(1, min(12, (int) ($_GET['limit'] ?? 6)));
if (app_can('can_approve_logs')) {
    app_sync_reviewer_queue_notifications($conn);
}

ajax_json([
    'success' => true,
    'items' => app_get_recent_notifications($conn, $userId, $limit),
    'unread_count' => app_get_unread_notification_count($conn, $userId),
]);
