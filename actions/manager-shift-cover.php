<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shift_cover_service.php';

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
    if (!in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    }
    $signatureSource = (string) ($_POST['signature_source'] ?? '');
    if (!in_array($signatureSource, ['profile', 'drawn'], true)) {
        $signatureSource = (string) ($_POST['use_profile_signature'] ?? '') === '1' ? 'profile' : 'drawn';
    }

    $result = app_shift_cover_manager_decision(
        $conn,
        (int) ($_POST['cover_request_id'] ?? 0),
        $currentUserId,
        $decision,
        (string) ($_POST['note'] ?? ''),
        (string) ($_POST['approver_signature_data'] ?? ''),
        $signatureSource === 'profile'
    );
    $_SESSION['shift_cover_flash'] = $result['message'];
    $_SESSION['shift_cover_flash_type'] = 'success';
} catch (Throwable $e) {
    $_SESSION['shift_cover_flash'] = $e->getMessage();
    $_SESSION['shift_cover_flash_type'] = 'danger';
}

header('Location: ../pages/shift-cover-requests.php#manager');
exit;
