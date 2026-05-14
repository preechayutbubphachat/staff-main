<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';
require_once __DIR__ . '/../includes/shift_schedule_service.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$month = (int) ($_POST['month'] ?? date('n'));
$yearBe = (int) ($_POST['year'] ?? ($_POST['year_be'] ?? ((int) date('Y') + 543)));
$view = (string) ($_POST['view'] ?? 'my');
$display = (string) ($_POST['display'] ?? 'calendar');
$redirectQuery = http_build_query([
    'month' => max(1, min(12, $month)),
    'year' => $yearBe > 2400 ? $yearBe : $yearBe + 543,
    'view' => in_array($view, ['my', 'department'], true) ? $view : 'my',
    'display' => in_array($display, ['calendar', 'list'], true) ? $display : 'calendar',
]);
$redirectUrl = '../pages/my-shifts.php?' . $redirectQuery;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('วิธีเรียกข้อมูลไม่ถูกต้อง');
    }
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'my_shifts_create_time_log')) {
        throw new RuntimeException('โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
    }

    $result = app_create_time_log_from_assignment(
        $conn,
        (int) ($_POST['assignment_id'] ?? 0),
        (int) ($_SESSION['id'] ?? 0),
        (string) ($_POST['note'] ?? '')
    );

    $_SESSION['my_shifts_flash'] = $result['message'];
    $_SESSION['my_shifts_flash_type'] = 'success';
} catch (Throwable $e) {
    $_SESSION['my_shifts_flash'] = $e->getMessage();
    $_SESSION['my_shifts_flash_type'] = 'danger';
}

header('Location: ' . $redirectUrl);
exit;
