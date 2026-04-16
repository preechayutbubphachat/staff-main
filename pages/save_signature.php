<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int) ($_POST['staff_id'] ?? $_POST['user_id'] ?? $_SESSION['id']);
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user']);
    exit;
}

$folder = __DIR__ . '/../uploads/signatures/';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$relative_path = null;

if (!empty($_POST['signature'])) {
    $data = $_POST['signature'];
    if (str_contains($data, ',')) {
        [, $data] = explode(',', $data, 2);
    }

    $binary = base64_decode($data, true);
    if ($binary === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature payload']);
        exit;
    }

    $file_name = 'sign_' . $user_id . '_' . time() . '.png';
    file_put_contents($folder . $file_name, $binary);
    $relative_path = 'uploads/signatures/' . $file_name;
} elseif (!empty($_FILES['file']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $ext = in_array($ext, ['png', 'jpg', 'jpeg'], true) ? $ext : 'png';
    $file_name = 'sign_' . $user_id . '_' . time() . '.' . $ext;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $folder . $file_name)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
        exit;
    }

    $relative_path = 'uploads/signatures/' . $file_name;
}

if ($relative_path === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No signature provided']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE time_logs
    SET signature = ?
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$relative_path, $user_id]);

echo json_encode([
    'status' => 'ok',
    'path' => $relative_path,
]);
