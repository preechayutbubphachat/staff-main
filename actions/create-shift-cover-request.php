<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shift_cover_service.php';

app_require_login();

$isFetchRequest = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$currentUserId = (int) ($_SESSION['id'] ?? 0);
$redirect = '../pages/my-shifts.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'shift_swap')) {
        throw new RuntimeException('โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่');
    }

    $signatureSource = (string) ($_POST['signature_source'] ?? '');
    if (!in_array($signatureSource, ['profile', 'drawn'], true)) {
        $signatureSource = (string) ($_POST['use_profile_signature'] ?? '') === '1' ? 'profile' : 'drawn';
    }

    $result = app_shift_cover_create_request(
        $conn,
        $currentUserId,
        (int) ($_POST['source_assignment_id'] ?? 0),
        (int) ($_POST['substitute_staff_id'] ?? 0),
        (string) ($_POST['reason'] ?? ''),
        (string) ($_POST['requester_signature_data'] ?? ''),
        $signatureSource === 'profile'
    );

    if ($isFetchRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => $result['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['my_shifts_flash'] = $result['message'];
    $_SESSION['my_shifts_flash_type'] = 'success';
} catch (Throwable $e) {
    if ($isFetchRequest) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['my_shifts_flash'] = $e->getMessage();
    $_SESSION['my_shifts_flash_type'] = 'danger';
}

header('Location: ' . $redirect);
exit;
