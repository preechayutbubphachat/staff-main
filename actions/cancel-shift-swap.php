<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shift_swap_service.php';

app_require_login();

$currentUserId = (int) ($_SESSION['id'] ?? 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'shift_swap')) {
        throw new RuntimeException('โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่');
    }

    $result = app_shift_swap_cancel_request($conn, (int) ($_POST['swap_request_id'] ?? 0), $currentUserId);
    $_SESSION['shift_swap_flash'] = $result['message'];
    $_SESSION['shift_swap_flash_type'] = 'success';
} catch (Throwable $e) {
    $_SESSION['shift_swap_flash'] = $e->getMessage();
    $_SESSION['shift_swap_flash_type'] = 'danger';
}

header('Location: ../pages/shift-swap-requests.php#sent');
exit;
