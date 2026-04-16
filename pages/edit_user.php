<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_permission('can_manage_user_permissions');

$hasFirstNameColumn = app_column_exists($conn, 'users', 'first_name');
$hasLastNameColumn = app_column_exists($conn, 'users', 'last_name');

function app_admin_media_upload(string $type, string $username, array $file, ?string &$error = null): ?string {
    $error = null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { $error = 'ไม่สามารถอัปโหลดไฟล์ได้'; return null; }
    $tmp = (string)($file['tmp_name'] ?? ''); if ($tmp === '' || !is_uploaded_file($tmp)) { $error = 'ไม่พบไฟล์ที่อัปโหลด'; return null; }
    if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) { $error = 'ไฟล์ต้องมีขนาดไม่เกิน 2 MB'; return null; }
    $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = $finfo ? (string)finfo_file($finfo, $tmp) : ''; if ($finfo) finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']; if (!isset($allowed[$mime])) { $error = 'รองรับเฉพาะไฟล์ JPG, PNG หรือ WEBP'; return null; }
    $folder = __DIR__ . '/../uploads/' . ($type === 'signature' ? 'signatures/' : 'avatars/'); if (!is_dir($folder)) mkdir($folder, 0777, true);
    $prefix = $type === 'signature' ? 'sig_' : 'avatar_';
    $name = $prefix . date('YmdHis') . '_' . preg_replace('/[^A-Za-z0-9ก-๙_-]/u', '_', $username) . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    return move_uploaded_file($tmp, $folder . $name) ? $name : null;
}

function app_delete_media_if_exists(string $type, ?string $fileName): void {
    $fileName = trim((string)$fileName); if ($fileName === '') return; $path = __DIR__ . '/../uploads/' . ($type === 'signature' ? 'signatures/' : 'avatars/') . $fileName; if (is_file($path)) @unlink($path);
}

function app_admin_snapshot_changed(array $before, array $after, array $fields): bool {
    foreach ($fields as $field) {
        $beforeValue = $before[$field] ?? null;
        $afterValue = $after[$field] ?? null;

        if (is_bool($beforeValue) || is_bool($afterValue)) {
            if ((int) $beforeValue !== (int) $afterValue) {
                return true;
            }
            continue;
        }

        if (is_numeric($beforeValue) || is_numeric($afterValue)) {
            if ((string) ((int) $beforeValue) !== (string) ((int) $afterValue)) {
                return true;
            }
            continue;
        }

        if (trim((string) $beforeValue) !== trim((string) $afterValue)) {
            return true;
        }
    }

    return false;
}

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id <= 0) { header('Location: manage_users.php'); exit; }

$actorId = (int)($_SESSION['id'] ?? 0);
$actorName = (string)($_SESSION['fullname'] ?? '');
$csrf = app_csrf_token('edit_user');
$message = ''; $messageType = 'success';
$departments = app_fetch_departments($conn); $roleLabels = app_role_labels();

$stmt = $conn->prepare("SELECT u.*, d.department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: manage_users.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'edit_user')) { $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'; $messageType = 'danger'; }
    elseif (($_POST['action'] ?? '') === 'change_password') {
        $newPassword = (string)($_POST['new_password'] ?? ''); $confirmPassword = (string)($_POST['confirm_new_password'] ?? '');
        if ($newPassword === '' || $confirmPassword === '') { $message = 'กรุณากรอกรหัสผ่านใหม่ให้ครบถ้วน'; $messageType = 'danger'; }
        elseif ($newPassword !== $confirmPassword) { $message = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน'; $messageType = 'danger'; }
        elseif (mb_strlen($newPassword) < 6) { $message = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'; $messageType = 'danger'; }
        else {
            $conn->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            app_insert_user_permission_audit($conn, $id, 'password_reset', null, ['password_changed' => true], $actorId, $actorName, 'เปลี่ยนรหัสผ่านจากหน้าแก้ไขผู้ใช้งาน');
            app_db_admin_write_audit_log($conn, 'users', (string)$id, 'update', ['password' => '[hidden]'], ['password' => '[changed]'], $actorId, $actorName, 'เปลี่ยนรหัสผ่านผู้ใช้งานจากหน้าแก้ไขผู้ใช้งาน');
            $message = 'บันทึกรหัสผ่านใหม่เรียบร้อยแล้ว';
        }
    } else {
        $firstName = trim((string)($_POST['first_name'] ?? '')); $lastName = trim((string)($_POST['last_name'] ?? '')); $fullname = app_compose_fullname($firstName, $lastName);
        $username = trim((string)($_POST['username'] ?? '')); $positionName = trim((string)($_POST['position_name'] ?? '')); $phoneNumber = trim((string)($_POST['phone_number'] ?? '')); $departmentId = max(0, (int)($_POST['department_id'] ?? 0)); $role = app_normalize_role($_POST['role'] ?? ($user['role'] ?? 'staff')); $isActive = isset($_POST['is_active']) ? 1 : 0;
        if (!in_array($role, ['admin', 'checker', 'finance', 'staff'], true)) $role = 'staff';
        $checkUsername = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1'); $checkUsername->execute([$username, $id]);
        $avatarError = null; $signatureError = null; $newAvatar = app_admin_media_upload('avatar', $username !== '' ? $username : ($user['username'] ?? 'user'), $_FILES['profile_image'] ?? [], $avatarError); $newSignature = app_admin_media_upload('signature', $username !== '' ? $username : ($user['username'] ?? 'user'), $_FILES['signature_file'] ?? [], $signatureError);
        if ($firstName === '' || $lastName === '') { $message = 'กรุณากรอกชื่อและนามสกุลให้ครบถ้วน'; $messageType = 'danger'; }
        elseif ($username === '') { $message = 'กรุณากรอกชื่อผู้ใช้'; $messageType = 'danger'; }
        elseif ($checkUsername->fetchColumn()) { $message = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาเลือกชื่อผู้ใช้อื่น'; $messageType = 'danger'; }
        elseif ($avatarError !== null) { $message = $avatarError; $messageType = 'danger'; }
        elseif ($signatureError !== null) { $message = $signatureError; $messageType = 'danger'; }
        else {
            $oldSnapshot = $user;
            $permissions = app_default_permissions($role);
            if ($role !== 'admin') {
                $permissions['can_approve_logs'] = isset($_POST['can_approve_logs']) ? 1 : 0;
                $permissions['can_manage_time_logs'] = isset($_POST['can_manage_time_logs']) ? 1 : 0;
                $permissions['can_edit_locked_time_logs'] = isset($_POST['can_edit_locked_time_logs']) ? 1 : 0;
                $permissions['can_manage_user_permissions'] = isset($_POST['can_manage_user_permissions']) ? 1 : 0;
                $permissions['can_manage_database'] = isset($_POST['can_manage_database']) ? 1 : 0;
            }
            $avatarPath = !empty($_POST['remove_avatar']) ? null : ($newAvatar ?: ($user['profile_image_path'] ?? null));
            $signaturePath = !empty($_POST['remove_signature']) ? null : ($newSignature ?: ($user['signature_path'] ?? null));
            if (!empty($_POST['remove_avatar'])) app_delete_media_if_exists('avatar', $user['profile_image_path'] ?? null);
            if (!empty($_POST['remove_signature'])) app_delete_media_if_exists('signature', $user['signature_path'] ?? null);
            if ($newAvatar && !empty($user['profile_image_path'])) app_delete_media_if_exists('avatar', $user['profile_image_path']);
            if ($newSignature && !empty($user['signature_path'])) app_delete_media_if_exists('signature', $user['signature_path']);
            $updateColumns = [];
            $updateValues = [];
            if ($hasFirstNameColumn) {
                $updateColumns[] = 'first_name = ?';
                $updateValues[] = $firstName;
            }
            if ($hasLastNameColumn) {
                $updateColumns[] = 'last_name = ?';
                $updateValues[] = $lastName;
            }
            $updateColumns = array_merge($updateColumns, [
                'fullname = ?',
                'username = ?',
                'position_name = ?',
                'phone_number = ?',
                'department_id = ?',
                'role = ?',
                'is_active = ?',
                'can_view_all_staff = ?',
                'can_view_department_reports = ?',
                'can_export_reports = ?',
                'can_approve_logs = ?',
                'can_manage_time_logs = ?',
                'can_edit_locked_time_logs = ?',
                'can_manage_user_permissions = ?',
                'can_manage_database = ?',
                'profile_image_path = ?',
                'signature_path = ?',
            ]);
            $updateValues = array_merge($updateValues, [$fullname, $username, $positionName !== '' ? $positionName : null, $phoneNumber !== '' ? $phoneNumber : null, $departmentId, $role, $isActive, $permissions['can_view_all_staff'], $permissions['can_view_department_reports'], $permissions['can_export_reports'], $permissions['can_approve_logs'], $permissions['can_manage_time_logs'], $permissions['can_edit_locked_time_logs'], $permissions['can_manage_user_permissions'], $permissions['can_manage_database'], $avatarPath, $signaturePath, $id]);
            $update = $conn->prepare('UPDATE users SET ' . implode(', ', $updateColumns) . ' WHERE id = ?');
            $update->execute($updateValues);
            $stmt->execute([$id]); $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;
            $newSnapshot = $user;
            $permissionFields = [
                'role',
                'is_active',
                'can_view_all_staff',
                'can_view_department_reports',
                'can_export_reports',
                'can_approve_logs',
                'can_manage_time_logs',
                'can_edit_locked_time_logs',
                'can_manage_user_permissions',
                'can_manage_database',
            ];
            $profileFields = [
                'first_name',
                'last_name',
                'fullname',
                'username',
                'position_name',
                'phone_number',
                'department_id',
                'profile_image_path',
                'signature_path',
            ];
            $permissionsChanged = app_admin_snapshot_changed($oldSnapshot, $newSnapshot, $permissionFields);
            $profileChanged = app_admin_snapshot_changed($oldSnapshot, $newSnapshot, $profileFields);
            app_insert_user_permission_audit($conn, $id, 'user_update', $oldSnapshot, $newSnapshot, $actorId, $actorName, 'แก้ไขข้อมูลผู้ใช้งานจากหน้าแอดมิน');
            app_db_admin_write_audit_log($conn, 'users', (string)$id, 'update', $oldSnapshot, $newSnapshot, $actorId, $actorName, 'แก้ไขข้อมูลผู้ใช้งานจากหน้าแอดมิน');
            if ($permissionsChanged) {
                app_notify_permission_changed($conn, $id, $actorId);
            }
            if ($profileChanged) {
                app_notify_user_profile_updated($conn, $id, $actorId);
            }
            $message = 'บันทึกข้อมูลผู้ใช้งานเรียบร้อยแล้ว';
        }
    }
}

$displayName = app_user_display_name($user);
$avatarUrl = app_resolve_user_image_url($user['profile_image_path'] ?? '');
$signatureUrl = null; $signatureName = trim((string)($user['signature_path'] ?? '')); if ($signatureName !== '' && is_file(__DIR__ . '/../uploads/signatures/' . $signatureName)) $signatureUrl = '../uploads/signatures/' . rawurlencode($signatureName);
?>
<!doctype html>
<html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>แก้ไขข้อมูลผู้ใช้งาน</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"><style>body{background:linear-gradient(180deg,#f8fbfd,#eef4f8);font-family:'Sarabun',sans-serif;color:#10243b}.hero,.panel,.preview-card{background:rgba(255,255,255,.92);border:1px solid rgba(16,36,59,.08);border-radius:28px;box-shadow:0 18px 44px rgba(16,36,59,.08)}.hero,.panel,.preview-card{padding:24px}.hero h1,.section-title{font-family:'Prompt',sans-serif}.avatar{width:120px;height:120px;border-radius:32px;overflow:hidden;position:relative;background:linear-gradient(135deg,rgba(16,36,59,.96),rgba(28,107,99,.88)),url('../LOGO/nongphok_logo.png') center/60px no-repeat}.avatar img{width:100%;height:100%;object-fit:cover}.placeholder{position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:3rem}.form-control,.form-select{border-radius:16px;padding:12px 14px}.signature-box{min-height:140px;border:1px dashed rgba(16,36,59,.16);border-radius:18px;background:#fbfdff;display:flex;align-items:center;justify-content:center;padding:12px}.signature-box img{max-width:100%;max-height:120px}.summary-list{display:grid;gap:10px}.summary-item{padding:12px 14px;border-radius:16px;background:#f6f9fc}.section-subtitle{color:#6b7a8d}</style><link rel="stylesheet" href="../assets/css/app-ui.css"></head><body class="app-ui">
<?php render_app_navigation('edit_user.php'); ?>
<main class="container py-4 py-lg-5"><section class="hero mb-4"><div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><div class="small text-uppercase fw-semibold text-secondary mb-2">Admin Only</div><h1 class="mb-2">แก้ไขข้อมูลผู้ใช้งาน</h1><p class="section-subtitle mb-0">บล็อกด้านซ้ายใช้สำหรับดูข้อมูลปัจจุบันเท่านั้น ส่วนบล็อกด้านขวาเป็นพื้นที่แก้ไขจริง เพื่อลดความสับสนและป้องกันการแก้ข้อมูลผิดคน</p></div><a href="db_table_browser.php?table=users" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left me-1"></i>กลับไปรายการข้อมูลในตาราง</a></div></section><?php if ($message !== ''): ?><div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?><div class="row g-4"><div class="col-lg-4 d-grid gap-4"><section class="preview-card"><div class="small text-uppercase fw-semibold text-secondary mb-2">Preview Only</div><div class="section-title h5 mb-1">ตัวอย่างข้อมูลปัจจุบัน</div><div class="section-subtitle mb-3">ส่วนนี้เอาไว้ตรวจสอบข้อมูลเดิมก่อนบันทึก ไม่ใช่พื้นที่แก้ไข</div><div class="d-flex gap-3 align-items-center mb-3"><div class="avatar"><?php if ($avatarUrl): ?><img src="<?= htmlspecialchars($avatarUrl) ?>" alt="รูปประจำตัว"><?php else: ?><div class="placeholder"><i class="bi bi-person-circle"></i></div><?php endif; ?></div><div><div class="h4 mb-1"><?= htmlspecialchars($displayName) ?></div><div class="text-muted"><?= htmlspecialchars(app_role_label((string)($user['role'] ?? 'staff'))) ?></div></div></div><div class="summary-list"><div class="summary-item"><div class="small text-muted">Username</div><div class="fw-semibold"><?= htmlspecialchars($user['username'] ?? '-') ?></div></div><div class="summary-item"><div class="small text-muted">ตำแหน่ง</div><div class="fw-semibold"><?= htmlspecialchars($user['position_name'] ?: '-') ?></div></div><div class="summary-item"><div class="small text-muted">แผนก</div><div class="fw-semibold"><?= htmlspecialchars($user['department_name'] ?: '-') ?></div></div><div class="summary-item"><div class="small text-muted">เบอร์โทร</div><div class="fw-semibold"><?= htmlspecialchars($user['phone_number'] ?: '-') ?></div></div></div></section><section class="preview-card"><div class="small text-uppercase fw-semibold text-secondary mb-2">Media Preview</div><div class="section-title h5 mb-1">ตัวอย่างรูปประจำตัวและลายเซ็น</div><div class="section-subtitle mb-3">ดูสภาพปัจจุบันก่อนอัปโหลดหรือเลือกลบออก</div><div class="small text-muted mb-2">รูปประจำตัว</div><div class="signature-box mb-3"><?php if ($avatarUrl): ?><img src="<?= htmlspecialchars($avatarUrl) ?>" alt="รูปประจำตัว"><?php else: ?><span class="text-muted">ยังไม่มีรูปประจำตัว</span><?php endif; ?></div><div class="small text-muted mb-2">ลายเซ็น</div><div class="signature-box"><?php if ($signatureUrl): ?><img src="<?= htmlspecialchars($signatureUrl) ?>" alt="ลายเซ็น"><?php else: ?><span class="text-muted">ยังไม่มีลายเซ็น</span><?php endif; ?></div></section></div><div class="col-lg-8 d-grid gap-4"><section class="panel"><div class="small text-uppercase fw-semibold text-secondary mb-2">Editable Form</div><div class="section-title h4 mb-1">ฟอร์มแก้ไขข้อมูลจริง</div><div class="section-subtitle mb-3">สามารถเปลี่ยนชื่อผู้ใช้ รูปประจำตัว ลายเซ็น บทบาท และสิทธิ์ต่าง ๆ ได้จากส่วนนี้โดยตรง</div><form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><div class="col-md-6"><label class="form-label fw-semibold">ชื่อ</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars((string)($user['first_name'] ?? '')) ?>" required></div><div class="col-md-6"><label class="form-label fw-semibold">นามสกุล</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars((string)($user['last_name'] ?? '')) ?>" required></div><div class="col-md-6"><label class="form-label fw-semibold">ชื่อผู้ใช้</label><input type="text" name="username" class="form-control" value="<?= htmlspecialchars((string)($user['username'] ?? '')) ?>" required><div class="small text-muted mt-2">ระบบจะตรวจสอบชื่อผู้ใช้ซ้ำก่อนบันทึกทุกครั้ง</div></div><div class="col-md-6"><label class="form-label fw-semibold">บทบาท</label><select name="role" class="form-select"><?php foreach ($roleLabels as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($user['role'] ?? 'staff') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label fw-semibold">ตำแหน่ง</label><input type="text" name="position_name" class="form-control" value="<?= htmlspecialchars((string)($user['position_name'] ?? '')) ?>"></div><div class="col-md-6"><label class="form-label fw-semibold">เบอร์โทร</label><input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars((string)($user['phone_number'] ?? '')) ?>"></div><div class="col-md-6"><label class="form-label fw-semibold">แผนก</label><select name="department_id" class="form-select"><?php foreach ($departments as $department): ?><option value="<?= (int)$department['id'] ?>" <?= (int)($user['department_id'] ?? 0) === (int)$department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label fw-semibold d-block">สถานะบัญชี</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($user['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">เปิดใช้งานบัญชี</label></div></div><div class="col-12"><hr class="my-1"><div class="fw-semibold mb-2">สิทธิ์ที่เกี่ยวข้องกับงานลงเวลาเวร</div><div class="row g-2"><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="perm_approve" name="can_approve_logs" value="1" <?= !empty($user['can_approve_logs']) ? 'checked' : '' ?>><label class="form-check-label" for="perm_approve">อนุมัติรายการลงเวลาเวร</label></div></div><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="perm_manage_logs" name="can_manage_time_logs" value="1" <?= !empty($user['can_manage_time_logs']) ? 'checked' : '' ?>><label class="form-check-label" for="perm_manage_logs">จัดการรายการลงเวลาเวร</label></div></div><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="perm_edit_locked" name="can_edit_locked_time_logs" value="1" <?= !empty($user['can_edit_locked_time_logs']) ? 'checked' : '' ?>><label class="form-check-label" for="perm_edit_locked">แก้ไขรายการที่ถูกล็อกแล้ว</label></div></div><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="perm_manage_users" name="can_manage_user_permissions" value="1" <?= !empty($user['can_manage_user_permissions']) ? 'checked' : '' ?>><label class="form-check-label" for="perm_manage_users">จัดการสิทธิ์ผู้ใช้</label></div></div><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="perm_manage_db" name="can_manage_database" value="1" <?= !empty($user['can_manage_database']) ? 'checked' : '' ?>><label class="form-check-label" for="perm_manage_db">จัดการระบบหลังบ้าน</label></div></div></div></div><div class="col-md-6"><label class="form-label fw-semibold">เปลี่ยนรูปประจำตัว</label><input type="file" name="profile_image" class="form-control" accept="image/png,image/jpeg,image/webp"><div class="small text-muted mt-2">อัปโหลดรูปใหม่เพื่อแทนที่ของเดิม หรือเลือกลบรูปเดิมออก</div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_avatar" name="remove_avatar" value="1"><label class="form-check-label" for="remove_avatar">ลบรูปประจำตัวปัจจุบัน</label></div></div><div class="col-md-6"><label class="form-label fw-semibold">เปลี่ยนลายเซ็น</label><input type="file" name="signature_file" class="form-control" accept="image/png,image/jpeg,image/webp"><div class="small text-muted mt-2">อัปโหลดลายเซ็นใหม่เพื่อแทนที่ของเดิม หรือเลือกลบลายเซ็นเดิมออก</div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_signature" name="remove_signature" value="1"><label class="form-check-label" for="remove_signature">ลบลายเซ็นปัจจุบัน</label></div></div><div class="col-12 d-flex gap-2 flex-wrap justify-content-end"><a href="db_table_browser.php?table=users" class="btn btn-outline-secondary rounded-pill">กลับไปรายการข้อมูลในตาราง</a><button type="submit" class="btn btn-dark rounded-pill"><i class="bi bi-save me-1"></i>บันทึกข้อมูล</button></div></form></section><section class="panel"><div class="small text-uppercase fw-semibold text-secondary mb-2">Password Action</div><div class="section-title h5 mb-1">เปลี่ยนรหัสผ่าน</div><div class="section-subtitle mb-3">รหัสผ่านเดิมจะไม่แสดงเพื่อความปลอดภัย หากต้องการเปลี่ยนรหัสผ่าน กรุณากำหนดรหัสผ่านใหม่และยืนยันก่อนบันทึกในส่วนนี้เท่านั้น</div><form method="post" class="row g-3"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="change_password"><div class="col-md-6"><label class="form-label fw-semibold">รหัสผ่านใหม่</label><input type="password" name="new_password" class="form-control"></div><div class="col-md-6"><label class="form-label fw-semibold">ยืนยันรหัสผ่านใหม่</label><input type="password" name="confirm_new_password" class="form-control"></div><div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-danger rounded-pill"><i class="bi bi-key me-1"></i>เปลี่ยนรหัสผ่าน</button></div></form></section></div></div></main></body></html>
