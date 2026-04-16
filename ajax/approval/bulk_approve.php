<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_approve_logs');
ajax_require_method('POST');
ajax_verify_csrf_or_fail('approval_queue', $_POST['_csrf'] ?? null);

$checkerId = (int) ($_SESSION['id'] ?? 0);
$checkerName = (string) ($_SESSION['fullname'] ?? '');
$checkerSignatureStmt = $conn->prepare('SELECT signature_path FROM users WHERE id = ?');
$checkerSignatureStmt->execute([$checkerId]);
$checkerSignature = (string) ($checkerSignatureStmt->fetchColumn() ?: '');
$selectedIds = is_array($_POST['selected_ids'] ?? null) ? $_POST['selected_ids'] : [];
$result = app_process_bulk_approval($conn, $selectedIds, $checkerId, $checkerName, $checkerSignature);

ajax_json($result, $result['success'] ? 200 : 422);
