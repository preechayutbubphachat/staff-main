<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shift_swap_service.php';

app_require_login();

// Detect fetch/XHR request (from JS confirm modal) — respond JSON instead of redirect
$isFetchRequest = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$redirect = '../pages/shift-swap-requests.php';
$returnToMyShifts = (string) ($_POST['return_to'] ?? '') === 'my-shifts';
if ($returnToMyShifts) {
    $month = max(1, min(12, (int) ($_POST['month'] ?? date('n'))));
    $yearBe = (int) ($_POST['year'] ?? ($_POST['year_be'] ?? ((int) date('Y') + 543)));
    if ($yearBe < 2400) {
        $yearBe += 543;
    }
    $display = in_array((string) ($_POST['display'] ?? 'calendar'), ['calendar', 'list'], true)
        ? (string) $_POST['display']
        : 'calendar';
    $openAssignmentId = max(0, (int) ($_POST['requester_assignment_id'] ?? 0));
    $redirect = '../pages/my-shifts.php?' . http_build_query([
        'month' => $month,
        'year' => $yearBe,
        'view' => 'my',
        'display' => $display,
        'open_assignment_id' => $openAssignmentId,
    ]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'shift_swap')) {
        throw new RuntimeException('โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่');
    }

    $result = app_shift_swap_create_request(
        $conn,
        $currentUserId,
        (int) ($_POST['requester_assignment_id'] ?? 0),
        (int) ($_POST['target_assignment_id'] ?? 0),
        (string) ($_POST['reason'] ?? ''),
        (string) ($_POST['requester_signature_data'] ?? ''),
        (string) ($_POST['use_profile_signature'] ?? '') === '1'
    );

    if ($isFetchRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => $result['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($returnToMyShifts) {
        $_SESSION['my_shifts_flash'] = $result['message'];
        $_SESSION['my_shifts_flash_type'] = 'success';
    } else {
        $_SESSION['shift_swap_flash'] = $result['message'];
        $_SESSION['shift_swap_flash_type'] = 'success';
    }
} catch (Throwable $e) {
    if ($isFetchRequest) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($returnToMyShifts) {
        $_SESSION['my_shifts_flash'] = $e->getMessage();
        $_SESSION['my_shifts_flash_type'] = 'danger';
    } else {
        $_SESSION['shift_swap_flash'] = $e->getMessage();
        $_SESSION['shift_swap_flash_type'] = 'danger';
        $assignmentId = (int) ($_POST['requester_assignment_id'] ?? 0);
        if ($assignmentId > 0) {
            $redirect .= '?assignment_id=' . $assignmentId;
        }
    }
}

header('Location: ' . $redirect);
exit;
