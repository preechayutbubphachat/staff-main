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
$isModal = isset($_GET['modal']) && $_GET['modal'] === '1';
$returnUrl = 'db_table_browser.php?table=users';

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
$pageTitle = 'แก้ไขข้อมูลผู้ใช้งาน';
$roleDisplay = app_role_label((string)($user['role'] ?? 'staff'));
$accountStatusLabel = !empty($user['is_active']) ? 'ใช้งานอยู่' : 'ปิดใช้งาน';
$accountStatusClass = !empty($user['is_active']) ? 'is-active' : 'is-inactive';
$editFormAction = 'edit_user.php?id=' . $id . ($isModal ? '&modal=1' : '');
$notifyParentOnSuccess = $isModal && $_SERVER['REQUEST_METHOD'] === 'POST' && $messageType === 'success' && $message !== '';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/app-ui.css">
    <style>
        body {
            margin: 0;
            background: linear-gradient(180deg, #f7fbfd, #eef5f9);
            font-family: 'Sarabun', sans-serif;
            color: #10243b;
        }

        body.admin-user-modal-partial {
            background: transparent;
        }

        .admin-user-page {
            padding: 1.5rem 0 2rem;
        }

        .admin-user-page.is-modal {
            padding: 0;
        }

        .admin-user-hero,
        .admin-user-preview,
        .admin-user-section {
            border-radius: 28px;
            border: 1px solid rgba(16, 36, 59, 0.08);
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 18px 44px rgba(16, 36, 59, 0.08);
        }

        .admin-user-hero {
            padding: 24px 28px;
        }

        .admin-user-modal-shell {
            display: grid;
            gap: 18px;
        }

        .admin-user-modal-shell.is-modal {
            min-height: 100%;
            padding: 0;
        }

        .admin-user-eyebrow {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #159d9a;
        }

        .admin-user-hero h1,
        .admin-user-section h3,
        .admin-user-preview h3 {
            margin: 0;
            font-family: 'Prompt', sans-serif;
            color: #092d4c;
            letter-spacing: -0.035em;
            font-weight: 900;
        }

        .admin-user-hero h1 {
            font-size: 30px;
            line-height: 1.15;
        }

        .admin-user-hero p,
        .admin-user-subtitle,
        .admin-user-preview-role {
            color: #6d7f8e;
            line-height: 1.55;
            font-weight: 600;
        }

        .admin-user-hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .admin-user-hero-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid #dce8f0;
            background: #fff;
            color: #12334f;
            text-decoration: none;
            font-weight: 800;
        }

        .admin-user-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .admin-user-preview {
            position: sticky;
            top: 0;
            padding: 22px;
            display: grid;
            gap: 18px;
            align-self: start;
        }

        .admin-user-avatar-wrap {
            width: 104px;
            height: 104px;
            border-radius: 28px;
            overflow: hidden;
            background: linear-gradient(135deg, #dff4f1, #eef6fb);
            border: 1px solid #dcebf2;
            box-shadow: 0 12px 26px rgba(7, 41, 68, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-user-avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-user-avatar-placeholder {
            font-size: 44px;
            color: #4d6d86;
        }

        .admin-user-preview-head {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-user-preview h3 {
            font-size: 24px;
            line-height: 1.15;
        }

        .admin-user-preview-list {
            display: grid;
            gap: 10px;
            margin: 0;
        }

        .admin-user-preview-item {
            padding: 13px 14px;
            border-radius: 16px;
            background: #f4f8fb;
            border: 1px solid #e6eff5;
        }

        .admin-user-preview-item dt {
            margin: 0 0 4px;
            font-size: 12px;
            color: #7c8fa2;
            font-weight: 800;
        }

        .admin-user-preview-item dd {
            margin: 0;
            color: #143650;
            font-weight: 900;
        }

        .admin-user-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
        }

        .admin-user-status.is-active {
            background: #dff8ed;
            color: #16865a;
        }

        .admin-user-status.is-inactive {
            background: #ffe4e7;
            color: #c9354d;
        }

        .admin-user-form-stack {
            display: grid;
            gap: 16px;
            min-width: 0;
        }

        .admin-user-section {
            padding: 22px;
        }

        .admin-user-section h3 {
            font-size: 22px;
            margin-bottom: 16px;
        }

        .admin-user-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .admin-user-form-grid .full {
            grid-column: 1 / -1;
        }

        .admin-user-label,
        .admin-user-section label {
            display: grid;
            gap: 7px;
            color: #526b7e;
            font-size: 13px;
            font-weight: 800;
        }

        .admin-user-form-grid input,
        .admin-user-form-grid select,
        .admin-user-form-grid textarea,
        .admin-user-section input,
        .admin-user-section select,
        .admin-user-section textarea {
            width: 100%;
            min-height: 44px;
            border-radius: 14px;
            border: 1px solid #dce8f0;
            background: #fff;
            padding: 0 13px;
            color: #12334f;
            font-weight: 700;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .admin-user-form-grid textarea,
        .admin-user-section textarea {
            min-height: 96px;
            padding: 12px 13px;
            resize: vertical;
        }

        .admin-user-form-grid input:focus,
        .admin-user-form-grid select:focus,
        .admin-user-form-grid textarea:focus,
        .admin-user-section input:focus,
        .admin-user-section select:focus,
        .admin-user-section textarea:focus {
            border-color: #1297a3;
            box-shadow: 0 0 0 4px rgba(18, 151, 163, .12);
        }

        .admin-user-permission-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 18px;
        }

        .admin-user-check {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #143650;
        }

        .admin-user-check input {
            width: 18px;
            height: 18px;
            min-height: 18px;
        }

        .admin-user-media-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .admin-user-media-box {
            min-height: 148px;
            border-radius: 18px;
            border: 1px dashed #cbdce6;
            background: #f8fbfd;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 12px;
        }

        .admin-user-media-box img {
            max-width: 100%;
            max-height: 140px;
            object-fit: contain;
        }

        .admin-user-media-box span {
            color: #7c8fa2;
            font-weight: 700;
        }

        .admin-user-alert {
            margin: 0;
            border-radius: 18px;
            padding: 14px 16px;
            font-weight: 700;
        }

        .admin-user-actions {
            position: sticky;
            bottom: 0;
            z-index: 3;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #e6f0f6;
            background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,.96) 24%);
        }

        .admin-user-btn {
            min-height: 46px;
            padding: 0 20px;
            border-radius: 999px;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .admin-user-btn:hover {
            transform: translateY(-1px);
        }

        .admin-user-btn.is-primary {
            border: 1px solid #07385e;
            background: #07385e;
            color: #fff;
            box-shadow: 0 12px 26px rgba(7, 56, 94, .18);
        }

        .admin-user-btn.is-ghost {
            border: 1px solid #dce8f0;
            background: #fff;
            color: #12334f;
        }

        .admin-user-password-actions {
            display: flex;
            justify-content: flex-end;
        }

        @media (max-width: 920px) {
            .admin-user-page {
                padding: 1rem 0 1.5rem;
            }

            .admin-user-layout,
            .admin-user-form-grid,
            .admin-user-media-grid,
            .admin-user-permission-grid {
                grid-template-columns: 1fr;
            }

            .admin-user-preview {
                position: static;
            }

            .admin-user-hero,
            .admin-user-preview,
            .admin-user-section {
                border-radius: 22px;
            }

            .admin-user-hero-top {
                flex-direction: column;
            }

            .admin-user-actions {
                position: static;
                background: transparent;
            }
        }
    </style>
</head>
<body class="app-ui<?= $isModal ? ' admin-user-modal-partial' : '' ?>">
<?php if (!$isModal): ?>
    <?php render_app_navigation('edit_user.php'); ?>
<?php endif; ?>

<main class="<?= $isModal ? '' : 'container' ?> admin-user-page<?= $isModal ? ' is-modal' : '' ?>">
    <div class="admin-user-modal-shell<?= $isModal ? ' is-modal' : '' ?>">
        <?php if (!$isModal): ?>
            <section class="admin-user-hero">
                <div class="admin-user-hero-top">
                    <div>
                        <span class="admin-user-eyebrow">Admin Only</span>
                        <h1><?= htmlspecialchars($pageTitle) ?></h1>
                        <p class="admin-user-subtitle mt-2">บล็อกด้านซ้ายใช้สำหรับดูข้อมูลปัจจุบันเท่านั้น ส่วนบล็อกด้านขวาเป็นพื้นที่แก้ไขจริง เพื่อลดความสับสนและป้องกันการแก้ข้อมูลผิดคน</p>
                    </div>
                    <a href="<?= htmlspecialchars($returnUrl) ?>" class="admin-user-hero-back">
                        <i class="bi bi-arrow-left"></i>
                        กลับไปรายการข้อมูลในตาราง
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> admin-user-alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="admin-user-layout">
            <aside class="admin-user-preview">
                <div>
                    <span class="admin-user-eyebrow">Preview Only</span>
                    <h3>ตัวอย่างข้อมูลปัจจุบัน</h3>
                    <p class="admin-user-subtitle mt-2">สำหรับตรวจสอบข้อมูลก่อนบันทึก ไม่ใช่พื้นที่แก้ไข</p>
                </div>

                <div class="admin-user-preview-head">
                    <div class="admin-user-avatar-wrap">
                        <?php if ($avatarUrl): ?>
                            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="รูปผู้ใช้">
                        <?php else: ?>
                            <span class="admin-user-avatar-placeholder"><i class="bi bi-person-circle"></i></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($displayName) ?></h3>
                        <p class="admin-user-preview-role"><?= htmlspecialchars($roleDisplay) ?></p>
                    </div>
                </div>

                <dl class="admin-user-preview-list">
                    <div class="admin-user-preview-item">
                        <dt>Username</dt>
                        <dd><?= htmlspecialchars($user['username'] ?? '-') ?></dd>
                    </div>
                    <div class="admin-user-preview-item">
                        <dt>ตำแหน่ง</dt>
                        <dd><?= htmlspecialchars($user['position_name'] ?: '-') ?></dd>
                    </div>
                    <div class="admin-user-preview-item">
                        <dt>แผนก</dt>
                        <dd><?= htmlspecialchars($user['department_name'] ?: '-') ?></dd>
                    </div>
                    <div class="admin-user-preview-item">
                        <dt>สถานะบัญชี</dt>
                        <dd><span class="admin-user-status <?= $accountStatusClass ?>"><?= htmlspecialchars($accountStatusLabel) ?></span></dd>
                    </div>
                    <div class="admin-user-preview-item">
                        <dt>เบอร์โทร</dt>
                        <dd><?= htmlspecialchars($user['phone_number'] ?: '-') ?></dd>
                    </div>
                </dl>
            </aside>

            <div class="admin-user-form-stack">
                <form id="userEditForm" method="post" action="<?= htmlspecialchars($editFormAction) ?>" enctype="multipart/form-data" class="admin-user-section">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div>
                        <span class="admin-user-eyebrow">Editable Form</span>
                        <h3>ข้อมูลบัญชีและสิทธิ์</h3>
                        <p class="admin-user-subtitle mt-2">สามารถเปลี่ยนชื่อผู้ใช้ รูปประจำตัว ลายเซ็น บทบาท และสิทธิ์ต่าง ๆ ได้จากส่วนนี้โดยตรง</p>
                    </div>

                    <div class="admin-user-form-grid mt-4">
                        <label class="admin-user-label">
                            <span>ชื่อ</span>
                            <input type="text" name="first_name" value="<?= htmlspecialchars((string) ($user['first_name'] ?? '')) ?>" required>
                        </label>
                        <label class="admin-user-label">
                            <span>นามสกุล</span>
                            <input type="text" name="last_name" value="<?= htmlspecialchars((string) ($user['last_name'] ?? '')) ?>" required>
                        </label>
                        <label class="admin-user-label">
                            <span>ชื่อผู้ใช้</span>
                            <input type="text" name="username" value="<?= htmlspecialchars((string) ($user['username'] ?? '')) ?>" required>
                        </label>
                        <label class="admin-user-label">
                            <span>บทบาท</span>
                            <select name="role">
                                <?php foreach ($roleLabels as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= ($user['role'] ?? 'staff') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="admin-user-label">
                            <span>ตำแหน่ง</span>
                            <input type="text" name="position_name" value="<?= htmlspecialchars((string) ($user['position_name'] ?? '')) ?>">
                        </label>
                        <label class="admin-user-label">
                            <span>เบอร์โทร</span>
                            <input type="text" name="phone_number" value="<?= htmlspecialchars((string) ($user['phone_number'] ?? '')) ?>">
                        </label>
                        <label class="admin-user-label">
                            <span>แผนก</span>
                            <select name="department_id">
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= (int) ($user['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="admin-user-label">
                            <span>สถานะบัญชี</span>
                            <span class="admin-user-check">
                                <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($user['is_active']) ? 'checked' : '' ?>>
                                <span>เปิดใช้งานบัญชี</span>
                            </span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <span class="admin-user-eyebrow">Permission Scope</span>
                        <h3 class="mt-1">สิทธิ์ที่เกี่ยวข้องกับงานลงเวลาเวร</h3>
                        <div class="admin-user-permission-grid mt-3">
                            <label class="admin-user-check"><input type="checkbox" id="perm_approve" name="can_approve_logs" value="1" <?= !empty($user['can_approve_logs']) ? 'checked' : '' ?>><span>อนุมัติรายการลงเวลาเวร</span></label>
                            <label class="admin-user-check"><input type="checkbox" id="perm_manage_logs" name="can_manage_time_logs" value="1" <?= !empty($user['can_manage_time_logs']) ? 'checked' : '' ?>><span>จัดการรายการลงเวลาเวร</span></label>
                            <label class="admin-user-check"><input type="checkbox" id="perm_edit_locked" name="can_edit_locked_time_logs" value="1" <?= !empty($user['can_edit_locked_time_logs']) ? 'checked' : '' ?>><span>แก้ไขรายการที่ถูกล็อกแล้ว</span></label>
                            <label class="admin-user-check"><input type="checkbox" id="perm_manage_users" name="can_manage_user_permissions" value="1" <?= !empty($user['can_manage_user_permissions']) ? 'checked' : '' ?>><span>จัดการสิทธิ์ผู้ใช้</span></label>
                            <label class="admin-user-check"><input type="checkbox" id="perm_manage_db" name="can_manage_database" value="1" <?= !empty($user['can_manage_database']) ? 'checked' : '' ?>><span>จัดการระบบหลังบ้าน</span></label>
                        </div>
                    </div>

                    <div class="mt-4">
                        <span class="admin-user-eyebrow">Media Preview</span>
                        <h3 class="mt-1">รูปประจำตัวและลายเซ็น</h3>
                        <div class="admin-user-media-grid mt-3">
                            <div>
                                <label class="admin-user-label">
                                    <span>เปลี่ยนรูปประจำตัว</span>
                                    <input type="file" name="profile_image" accept="image/png,image/jpeg,image/webp">
                                </label>
                                <div class="admin-user-media-box mt-2">
                                    <?php if ($avatarUrl): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="รูปประจำตัว">
                                    <?php else: ?>
                                        <span>ยังไม่มีรูปประจำตัว</span>
                                    <?php endif; ?>
                                </div>
                                <label class="admin-user-check mt-2"><input type="checkbox" id="remove_avatar" name="remove_avatar" value="1"><span>ลบรูปประจำตัวปัจจุบัน</span></label>
                            </div>
                            <div>
                                <label class="admin-user-label">
                                    <span>เปลี่ยนลายเซ็น</span>
                                    <input type="file" name="signature_file" accept="image/png,image/jpeg,image/webp">
                                </label>
                                <div class="admin-user-media-box mt-2">
                                    <?php if ($signatureUrl): ?>
                                        <img src="<?= htmlspecialchars($signatureUrl) ?>" alt="ลายเซ็น">
                                    <?php else: ?>
                                        <span>ยังไม่มีลายเซ็น</span>
                                    <?php endif; ?>
                                </div>
                                <label class="admin-user-check mt-2"><input type="checkbox" id="remove_signature" name="remove_signature" value="1"><span>ลบลายเซ็นปัจจุบัน</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="admin-user-actions">
                        <?php if ($isModal): ?>
                            <button type="button" class="admin-user-btn is-ghost" data-modal-close-proxy>ยกเลิก</button>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($returnUrl) ?>" class="admin-user-btn is-ghost">กลับไปรายการข้อมูลในตาราง</a>
                        <?php endif; ?>
                        <button type="submit" class="admin-user-btn is-primary"><i class="bi bi-save"></i>บันทึกข้อมูล</button>
                    </div>
                </form>

                <form method="post" action="<?= htmlspecialchars($editFormAction) ?>" class="admin-user-section">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <span class="admin-user-eyebrow">Password Action</span>
                    <h3 class="mt-1">เปลี่ยนรหัสผ่าน</h3>
                    <p class="admin-user-subtitle mt-2">รหัสผ่านเดิมจะไม่แสดงเพื่อความปลอดภัย หากต้องการเปลี่ยนรหัสผ่าน กรุณากำหนดรหัสผ่านใหม่และยืนยันก่อนบันทึกในส่วนนี้เท่านั้น</p>
                    <div class="admin-user-form-grid mt-4">
                        <label class="admin-user-label">
                            <span>รหัสผ่านใหม่</span>
                            <input type="password" name="new_password">
                        </label>
                        <label class="admin-user-label">
                            <span>ยืนยันรหัสผ่านใหม่</span>
                            <input type="password" name="confirm_new_password">
                        </label>
                    </div>
                    <div class="admin-user-password-actions mt-4">
                        <button type="submit" class="admin-user-btn is-ghost"><i class="bi bi-key"></i>เปลี่ยนรหัสผ่าน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php if ($isModal): ?>
<script>
    (function () {
        const closeButtons = document.querySelectorAll('[data-modal-close-proxy]');
        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'db-user-edit-close' }, window.location.origin);
                }
            });
        });

        <?php if ($notifyParentOnSuccess): ?>
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'db-user-edit-saved' }, window.location.origin);
        }
        <?php endif; ?>
    })();
</script>
<?php endif; ?>
</body>
</html>
