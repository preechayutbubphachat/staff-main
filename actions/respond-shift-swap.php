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

    $decision = (string) ($_POST['decision'] ?? '');
    if (!in_array($decision, ['confirm', 'reject'], true)) {
        throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    }

    $result = app_shift_swap_update_target_response(
        $conn,
        (int) ($_POST['swap_request_id'] ?? 0),
        $currentUserId,
        $decision,
        (string) ($_POST['note'] ?? ''),
        (string) ($_POST['responder_signature_data'] ?? ''),
        (string) ($_POST['use_profile_signature'] ?? '') === '1'
    );
    $_SESSION['shift_swap_flash'] = $result['message'];
    $_SESSION['shift_swap_flash_type'] = 'success';
} catch (Throwable $e) {
    $_SESSION['shift_swap_flash'] = $e->getMessage();
    $_SESSION['shift_swap_flash_type'] = 'danger';
}

header('Location: ../pages/shift-swap-requests.php#incoming');
exit;
