<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();

$userId = max(0, (int) ($_GET['id'] ?? 0));
if ($userId <= 0) {
    ajax_html('<div class="alert alert-warning rounded-4 mb-0">ไม่พบข้อมูลเจ้าหน้าที่ที่ต้องการ</div>', 400);
}

$firstNameSelect = app_column_exists($conn, 'users', 'first_name') ? 'u.first_name' : "'' AS first_name";
$lastNameSelect = app_column_exists($conn, 'users', 'last_name') ? 'u.last_name' : "'' AS last_name";
$stmt = $conn->prepare("SELECT u.id, u.username, u.fullname, {$firstNameSelect}, {$lastNameSelect}, u.role, u.position_name, u.phone_number, u.signature_path, u.profile_image_path, u.department_id, d.department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    ajax_html('<div class="alert alert-warning rounded-4 mb-0">ไม่พบข้อมูลเจ้าหน้าที่ที่ต้องการ</div>', 404);
}

$scope = app_get_accessible_departments($conn);
if (!app_can('can_view_all_staff') && !in_array((int) ($user['department_id'] ?? 0), $scope['ids'], true)) {
    ajax_html('<div class="alert alert-danger rounded-4 mb-0">ไม่มีสิทธิ์ดูข้อมูลเจ้าหน้าที่รายนี้</div>', 403);
}

$assetBase = '..';
$html = ajax_capture(function () use ($user, $assetBase): void {
    require __DIR__ . '/../../partials/profile/modal_body.php';
});

ajax_html($html);
