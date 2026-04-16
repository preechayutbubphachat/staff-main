<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

function app_register_slug(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9ก-๙_-]/u', '_', $value);
    return trim((string) $value, '_') ?: 'user';
}

function app_store_avatar_file(string $username, array $file, ?string &$error = null): ?string
{
    $error = null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'ไม่สามารถอัปโหลดรูปประจำตัวได้';
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $error = 'ไม่พบไฟล์รูปประจำตัว';
        return null;
    }
    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $error = 'รูปประจำตัวต้องมีขนาดไม่เกิน 2 MB';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $error = 'รองรับเฉพาะ JPG, PNG หรือ WEBP';
        return null;
    }

    $folder = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $name = 'avatar_' . date('YmdHis') . '_' . app_register_slug($username) . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    return move_uploaded_file($tmp, $folder . $name) ? $name : null;
}

function app_store_signature_file(string $username, array $post, array $files, ?string &$error = null): ?string
{
    $error = null;
    $folder = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    if (!empty($post['signature_base64'])) {
        $parts = explode(';base64,', (string) $post['signature_base64']);
        $binary = isset($parts[1]) ? base64_decode($parts[1], true) : false;
        if ($binary !== false) {
            $name = 'sig_' . date('YmdHis') . '_' . app_register_slug($username) . '.png';
            file_put_contents($folder . $name, $binary);
            return $name;
        }
    }

    $file = $files['signature_file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'ไม่สามารถอัปโหลดลายเซ็นได้';
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $error = 'ไม่พบไฟล์ลายเซ็น';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $error = 'รองรับไฟล์ลายเซ็นเฉพาะ JPG, PNG หรือ WEBP';
        return null;
    }

    $name = 'sig_' . date('YmdHis') . '_' . app_register_slug($username) . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    return move_uploaded_file($tmp, $folder . $name) ? $name : null;
}

$form = [
    'first_name' => '',
    'last_name' => '',
    'position_name' => '',
    'phone_number' => '',
    'department_id' => '1',
    'username' => '',
    'role' => 'staff',
    'can_view_all_staff' => '0',
    'can_view_department_reports' => '0',
    'can_export_reports' => '0',
];
$message = '';
$messageType = '';

$hasRoleColumn = app_column_exists($conn, 'users', 'role');
$hasPositionColumn = app_column_exists($conn, 'users', 'position_name');
$hasPhoneColumn = app_column_exists($conn, 'users', 'phone_number');
$hasManageTimeLogsColumn = app_column_exists($conn, 'users', 'can_manage_time_logs');
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$hasFirstNameColumn = app_column_exists($conn, 'users', 'first_name');
$hasLastNameColumn = app_column_exists($conn, 'users', 'last_name');

$departmentReferenceTable = $conn->query("SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1")->fetchColumn();
$departmentSourceTable = in_array($departmentReferenceTable, ['departments', 'bk01_departments'], true) ? $departmentReferenceTable : 'departments';
$departmentNameColumn = $departmentSourceTable === 'bk01_departments' && app_column_exists($conn, 'bk01_departments', 'name') ? 'name' : 'department_name';
$departments = $conn->query("SELECT id, {$departmentNameColumn} AS department_name FROM {$departmentSourceTable} ORDER BY {$departmentNameColumn}")->fetchAll(PDO::FETCH_ASSOC);

if ($departments && !array_filter($departments, static fn ($d) => (string) $d['id'] === $form['department_id'])) {
    $form['department_id'] = (string) $departments[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['first_name'] = trim((string) ($_POST['first_name'] ?? ''));
    $form['last_name'] = trim((string) ($_POST['last_name'] ?? ''));
    $fullname = app_compose_fullname($form['first_name'], $form['last_name']);
    $form['position_name'] = trim((string) ($_POST['position_name'] ?? ''));
    $form['phone_number'] = preg_replace('/\D+/', '', trim((string) ($_POST['phone_number'] ?? '')));
    $form['department_id'] = (string) ($_POST['department_id'] ?? $form['department_id']);
    $form['username'] = trim((string) ($_POST['username'] ?? ''));

    $requestedRole = app_normalize_role($_POST['role'] ?? 'staff');
    $form['role'] = in_array($requestedRole, ['staff', 'finance', 'checker'], true) ? $requestedRole : 'staff';
    $form['can_view_all_staff'] = isset($_POST['can_view_all_staff']) ? '1' : '0';
    $form['can_view_department_reports'] = isset($_POST['can_view_department_reports']) ? '1' : '0';
    $form['can_export_reports'] = isset($_POST['can_export_reports']) ? '1' : '0';

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $selectedDepartmentExists = false;
    foreach ($departments as $department) {
        if ((string) $department['id'] === $form['department_id']) {
            $selectedDepartmentExists = true;
            break;
        }
    }

    if (!$hasRoleColumn) {
        $message = 'กรุณารัน migration ระบบสิทธิ์ก่อน';
        $messageType = 'danger';
    } elseif (!$selectedDepartmentExists) {
        $message = 'แผนกที่เลือกไม่ถูกต้อง';
        $messageType = 'danger';
    } elseif ($form['first_name'] === '' || $form['last_name'] === '') {
        $message = 'กรุณากรอกชื่อและนามสกุลให้ครบถ้วน';
        $messageType = 'danger';
    } elseif ($form['username'] === '' || $password === '') {
        $message = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน';
        $messageType = 'danger';
    } elseif ($form['phone_number'] !== '' && !preg_match('/^\d{10}$/', $form['phone_number'])) {
        $message = 'เบอร์โทรต้องเป็นตัวเลข 10 หลัก';
        $messageType = 'danger';
    } elseif ($password !== $confirmPassword) {
        $message = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
        $messageType = 'danger';
    } elseif (mb_strlen($password) < 6) {
        $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        $messageType = 'danger';
    } else {
        $avatarError = null;
        $signatureError = null;
        $avatarFileName = $hasProfileImageColumn ? app_store_avatar_file($form['username'], $_FILES['profile_image'] ?? [], $avatarError) : null;
        $signatureFileName = app_store_signature_file($form['username'], $_POST, $_FILES, $signatureError);

        if ($avatarError !== null) {
            $message = $avatarError;
            $messageType = 'danger';
        } elseif ($signatureError !== null) {
            $message = $signatureError;
            $messageType = 'danger';
        } else {
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
            $checkStmt->execute([$form['username']]);

            if ($checkStmt->fetchColumn()) {
                $message = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
                $messageType = 'danger';
            } else {
                $permissions = app_default_permissions($form['role']);
                if ($form['role'] === 'finance') {
                    $permissions['can_view_all_staff'] = (int) $form['can_view_all_staff'];
                    $permissions['can_view_department_reports'] = (int) $form['can_view_department_reports'];
                    $permissions['can_export_reports'] = (int) $form['can_export_reports'];
                }

                $columns = ['fullname', 'username', 'password', 'department_id', 'signature_path', 'role', 'can_view_all_staff', 'can_view_department_reports', 'can_export_reports', 'can_approve_logs'];
                $values = [$fullname, $form['username'], password_hash($password, PASSWORD_DEFAULT), (int) $form['department_id'], $signatureFileName, $form['role'], $permissions['can_view_all_staff'], $permissions['can_view_department_reports'], $permissions['can_export_reports'], $permissions['can_approve_logs']];

                if ($hasFirstNameColumn) {
                    $columns[] = 'first_name';
                    $values[] = $form['first_name'];
                }
                if ($hasLastNameColumn) {
                    $columns[] = 'last_name';
                    $values[] = $form['last_name'];
                }
                if ($hasManageTimeLogsColumn) {
                    $columns[] = 'can_manage_time_logs';
                    $values[] = $permissions['can_manage_time_logs'];
                }
                if ($hasPositionColumn) {
                    $columns[] = 'position_name';
                    $values[] = $form['position_name'] !== '' ? $form['position_name'] : null;
                }
                if ($hasPhoneColumn) {
                    $columns[] = 'phone_number';
                    $values[] = $form['phone_number'] !== '' ? $form['phone_number'] : null;
                }
                if ($hasProfileImageColumn) {
                    $columns[] = 'profile_image_path';
                    $values[] = $avatarFileName;
                }

                $stmt = $conn->prepare('INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
                try {
                    $stmt->execute($values);
                    $message = 'สร้างบัญชีสำเร็จแล้ว สามารถเข้าสู่ระบบได้ทันที';
                    if ($avatarFileName === null) {
                        $message .= ' หากยังไม่อัปโหลดรูป ระบบจะใช้ภาพเริ่มต้นให้อัตโนมัติ';
                    }
                    if ($signatureFileName === null) {
                        $message .= ' และเพิ่มลายเซ็นภายหลังได้จากหน้าโปรไฟล์';
                    }
                    $messageType = 'success';
                    header('refresh:2;url=login.php');
                } catch (PDOException $exception) {
                    $message = 'เกิดข้อผิดพลาดระหว่างสร้างบัญชี กรุณาตรวจสอบโครงสร้างฐานข้อมูลและลองใหม่อีกครั้ง';
                    $messageType = 'danger';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>สร้างบัญชีผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/loading-overlay.css">
    <style>
        body { min-height: 100vh; font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #f6fbff, #eef5f4); color: #10243b; }
        .wrapper { min-height: 100vh; display: grid; grid-template-columns: 1fr minmax(420px, 760px); }
        .poster { padding: 48px; color: #fff; background: linear-gradient(160deg, rgba(16,36,59,.98), rgba(23,91,95,.92)), url('../LOGO/nongphok_logo.png') right 48px bottom 40px / 160px no-repeat; display: flex; flex-direction: column; justify-content: space-between; }
        .poster h1, .card-title, .section-title { font-family: 'Prompt', sans-serif; }
        .panel { padding: 30px; }
        .card-shell { background: rgba(255,255,255,.94); border: 1px solid rgba(16,36,59,.08); border-radius: 28px; box-shadow: 0 22px 50px rgba(16,36,59,.08); }
        .section-box { border: 1px solid rgba(16,36,59,.1); border-radius: 22px; padding: 20px; background: #fff; }
        .section-box.section-login { order: 1; }
        .section-box.section-general { order: 2; }
        .form-control, .form-select { border-radius: 16px; padding: 13px 14px; }
        .role-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .role-card { position: relative; }
        .role-card input { position: absolute; inset: 0; opacity: 0; }
        .role-card label { display: block; height: 100%; padding: 16px; border: 1px solid rgba(16,36,59,.12); border-radius: 18px; }
        .role-card input:checked + label { border-color: #1c6d67; background: rgba(28,109,103,.06); }
        .avatar-preview { width: 96px; height: 96px; border-radius: 24px; overflow: hidden; position: relative; background: linear-gradient(135deg, rgba(16,36,59,.96), rgba(28,107,99,.88)), url('../LOGO/nongphok_logo.png') center / 54px no-repeat; }
        .avatar-preview img { display: none; width: 100%; height: 100%; object-fit: cover; }
        .avatar-preview.has-image img { display: block; }
        .avatar-placeholder { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; }
        .signature-canvas { border-radius: 16px; border: 1px dashed rgba(16,36,59,.18); background: #fff; width: 100%; height: 150px; touch-action: none; }
        .form-footer { order: 3; margin-top: .5rem; padding-top: 1.25rem; border-top: 1px solid rgba(16,36,59,.08); }
        .page-back { position: fixed; top: 18px; left: 18px; z-index: 10; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 999px;
            border: 1px solid rgba(16,36,59,.12); background: rgba(255,255,255,.92); color: #10243b;
            font-weight: 700; box-shadow: 0 12px 26px rgba(16,36,59,.08);
        }
        @media (max-width: 1100px) {
            .wrapper { grid-template-columns: 1fr; }
            .poster { display: none; }
            .role-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page-back">
    <button type="button" class="btn btn-back" data-simple-back data-fallback-href="login.php">
        <i class="bi bi-arrow-left"></i>ย้อนกลับ
    </button>
</div>

<div class="wrapper">
    <section class="poster">
        <div>
            <div class="d-inline-flex align-items-center gap-2 fw-semibold mb-4" style="font-family:'Prompt',sans-serif">
                <img src="../LOGO/nongphok_logo.png" alt="Logo" style="width:42px;height:42px;object-fit:contain;border-radius:12px;background:rgba(255,255,255,.08);padding:4px">
                <span>NONG PHOK HOSPITAL</span>
            </div>
            <div class="small text-uppercase fw-semibold mb-3">Registration</div>
            <h1 class="display-5 mb-3">สร้างบัญชีเจ้าหน้าที่ให้พร้อมใช้งาน</h1>
            <p class="mb-0" style="max-width:620px;color:rgba(255,255,255,.82)">แยกข้อมูลทั่วไปออกจากข้อมูลเข้าสู่ระบบอย่างชัดเจน เพื่อให้กรอกง่าย ลดความสับสน และเหมาะกับการใช้งานของเจ้าหน้าที่โรงพยาบาล</p>
        </div>
        <div class="small" style="color:rgba(255,255,255,.72)">หากยังไม่พร้อมอัปโหลดรูปหรือลายเซ็น สามารถสมัครได้ก่อนและกลับมาเพิ่มในหน้าโปรไฟล์ภายหลัง</div>
    </section>

    <section class="panel d-grid align-items-center">
        <div class="card-shell p-4 p-lg-5">
            <div class="small text-uppercase fw-semibold text-success">ลงทะเบียนผู้ใช้งาน</div>
            <h2 class="card-title mt-2 mb-2">สร้างบัญชีผู้ใช้งาน</h2>
            <p class="text-muted mb-0">ระบบจะบันทึกชื่อและนามสกุลแยกกัน พร้อมสร้างชื่อแสดงผลแบบเดิมไว้เพื่อให้ข้อมูลเก่ายังทำงานได้ต่อเนื่อง</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mt-4 mb-0"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="mt-4 d-grid gap-4" data-global-loading-form data-loading-message="กำลังสร้างบัญชีผู้ใช้งาน...">
                <div class="section-box section-login">
                    <div class="section-title h5 mb-1">ข้อมูลเข้าสู่ระบบ</div>
                    <div class="text-muted small mb-3">ใช้สำหรับเข้าสู่ระบบในภายหลัง</div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold d-flex align-items-center gap-2">
                                <span>ชื่อผู้ใช้ (username)</span>
                                <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="ชื่อนี้ใช้สำหรับล็อกอินเข้าสู่ระบบ"></i>
                            </label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($form['username']) ?>" required>
                            <div class="small text-muted mt-2">ชื่อนี้ใช้สำหรับเข้าสู่ระบบ</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">รหัสผ่าน</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">ยืนยันรหัสผ่าน</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <label class="form-label fw-semibold d-block mb-3">บทบาทการใช้งาน</label>
                    <div class="role-grid">
                        <div class="role-card">
                            <input type="radio" name="role" id="role_staff" value="staff" <?= $form['role'] === 'staff' ? 'checked' : '' ?>>
                            <label for="role_staff">
                                <strong>เจ้าหน้าที่ทั่วไป</strong>
                                <div class="small text-muted mt-2">ลงเวลาเวร ดูประวัติ และรายงานของตนเอง</div>
                            </label>
                        </div>
                        <div class="role-card">
                            <input type="radio" name="role" id="role_finance" value="finance" <?= $form['role'] === 'finance' ? 'checked' : '' ?>>
                            <label for="role_finance">
                                <strong>เจ้าหน้าที่การเงิน</strong>
                                <div class="small text-muted mt-2">ดูข้อมูลรายบุคคลหรือรายแผนกได้ตามสิทธิ์เสริม</div>
                            </label>
                        </div>
                        <div class="role-card">
                            <input type="radio" name="role" id="role_checker" value="checker" <?= $form['role'] === 'checker' ? 'checked' : '' ?>>
                            <label for="role_checker">
                                <strong>ผู้ตรวจสอบ</strong>
                                <div class="small text-muted mt-2">ตรวจสอบรายการลงเวลาเวรและดูรายงานที่เกี่ยวข้อง</div>
                            </label>
                        </div>
                    </div>

                    <div class="finance-box mt-3" id="financePermissions" <?= $form['role'] === 'finance' ? '' : 'hidden' ?>>
                        <div class="fw-semibold mb-2">สิทธิ์เพิ่มเติมสำหรับเจ้าหน้าที่การเงิน</div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="perm_view_all_staff" name="can_view_all_staff" value="1" <?= $form['can_view_all_staff'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="perm_view_all_staff">ดูข้อมูลรายบุคคลของเจ้าหน้าที่ในหน่วยงานที่เกี่ยวข้องได้</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="perm_view_department" name="can_view_department_reports" value="1" <?= $form['can_view_department_reports'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="perm_view_department">ดูรายงานสรุปรายแผนกได้</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="perm_export" name="can_export_reports" value="1" <?= $form['can_export_reports'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="perm_export">ส่งออกรายงานได้</label>
                        </div>
                    </div>
                </div>

                <div class="section-box section-general">
                    <div class="section-title h5 mb-1">ข้อมูลทั่วไป</div>
                    <div class="text-muted small mb-3">ข้อมูลส่วนตัวและข้อมูลการทำงานของเจ้าหน้าที่</div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อ</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($form['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">นามสกุล</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($form['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ตำแหน่ง</label>
                            <input type="text" name="position_name" class="form-control" value="<?= htmlspecialchars($form['position_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เบอร์โทร</label>
                            <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($form['phone_number']) ?>" inputmode="numeric" maxlength="10" pattern="\d{10}" placeholder="เช่น 0812345678">
                            <div class="small text-muted mt-2">กรอกเป็นตัวเลข 10 หลักเท่านั้น</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">แผนก</label>
                            <select name="department_id" class="form-select" required>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= (string) $form['department_id'] === (string) $department['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">รูปประจำตัว (ไม่บังคับ)</label>
                            <div class="d-flex gap-3 align-items-center border rounded-4 p-3">
                                <div class="avatar-preview" id="avatarPreview">
                                    <img src="" alt="ตัวอย่างรูปประจำตัว" id="avatarPreviewImage">
                                    <div class="avatar-placeholder" id="avatarPlaceholder"><i class="bi bi-person-circle fs-1"></i></div>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                                    <div class="small text-muted mt-2">แนะนำรูปหน้าตรง พื้นหลังเรียบ และสัดส่วนใกล้เคียง 1:1</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">ลายเซ็นสำหรับใช้งานในระบบ (ไม่บังคับ)</label>
                            <div class="border rounded-4 p-3">
                                <div class="d-flex gap-2 flex-wrap mb-3">
                                    <button class="btn btn-sm btn-primary rounded-pill active" type="button" data-signature-mode="draw">วาดลายเซ็น</button>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill" type="button" data-signature-mode="upload">อัปโหลดรูปภาพ</button>
                                </div>
                                <div id="signatureDrawPanel">
                                    <canvas id="signatureCanvas" class="signature-canvas"></canvas>
                                    <input type="hidden" name="signature_base64" id="signature_base64">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                        <span class="small text-muted">ใช้เมาส์หรือหน้าจอสัมผัสวาดลายเซ็นในกรอบนี้</span>
                                        <button type="button" class="btn btn-outline-secondary rounded-pill" id="clearSignatureBtn">ล้างลายเซ็น</button>
                                    </div>
                                </div>
                                <div id="signatureUploadPanel" hidden>
                                    <input type="file" name="signature_file" class="form-control" accept="image/png,image/jpeg,image/webp">
                                    <div class="small text-muted mt-2">หากยังไม่เพิ่มลายเซ็นตอนนี้ ระบบยังสมัครให้ได้ตามปกติ</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn btn-dark rounded-pill py-3 w-100">สร้างบัญชีและเริ่มใช้งาน</button>
                    <div class="text-center small text-muted mt-3">มีบัญชีอยู่แล้ว? <a href="login.php" class="text-decoration-none fw-semibold">กลับไปหน้าเข้าสู่ระบบ</a></div>
                </div>
            </form>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/global-loading.js"></script>
<script>
window.GlobalLoading && window.GlobalLoading.init();
document.querySelectorAll('[data-simple-back]').forEach(function (button) {
    button.addEventListener('click', function () {
        const fallbackHref = button.getAttribute('data-fallback-href') || 'login.php';
        const hasHistory = window.history.length > 1 && document.referrer;

        if (hasHistory) {
            window.history.back();
            return;
        }

        window.location.href = fallbackHref;
    });
});

const roleInputs = Array.from(document.querySelectorAll('input[name="role"]'));
const financePermissions = document.getElementById('financePermissions');
const avatarInput = document.getElementById('profile_image');
const avatarPreview = document.getElementById('avatarPreview');
const avatarPreviewImage = document.getElementById('avatarPreviewImage');
const avatarPlaceholder = document.getElementById('avatarPlaceholder');
const signatureButtons = Array.from(document.querySelectorAll('[data-signature-mode]'));
const signatureDrawPanel = document.getElementById('signatureDrawPanel');
const signatureUploadPanel = document.getElementById('signatureUploadPanel');
const signatureCanvas = document.getElementById('signatureCanvas');
const signatureBase64 = document.getElementById('signature_base64');
const clearSignatureBtn = document.getElementById('clearSignatureBtn');
const ctx = signatureCanvas.getContext('2d');
let isDrawing = false;

function syncRoleUI() {
    const selectedRole = roleInputs.find((input) => input.checked)?.value || 'staff';
    financePermissions.hidden = selectedRole !== 'finance';
}

function updateAvatarPreview(file) {
    if (!file) {
        avatarPreview.classList.remove('has-image');
        avatarPreviewImage.removeAttribute('src');
        avatarPlaceholder.hidden = false;
        return;
    }
    const objectUrl = URL.createObjectURL(file);
    avatarPreviewImage.src = objectUrl;
    avatarPreview.classList.add('has-image');
    avatarPlaceholder.hidden = true;
}

function setSignatureMode(mode) {
    signatureButtons.forEach((button) => {
        const active = button.dataset.signatureMode === mode;
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-outline-primary', !active);
        button.classList.toggle('active', active);
    });
    signatureDrawPanel.hidden = mode !== 'draw';
    signatureUploadPanel.hidden = mode !== 'upload';
}

function resizeCanvas() {
    const ratio = window.devicePixelRatio || 1;
    const rect = signatureCanvas.getBoundingClientRect();
    signatureCanvas.width = rect.width * ratio;
    signatureCanvas.height = rect.height * ratio;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(ratio, ratio);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = 2;
    ctx.strokeStyle = '#10243b';
}

function getPoint(event) {
    const rect = signatureCanvas.getBoundingClientRect();
    const source = event.touches ? event.touches[0] : event;
    return { x: source.clientX - rect.left, y: source.clientY - rect.top };
}

function startDrawing(event) {
    isDrawing = true;
    const point = getPoint(event);
    ctx.beginPath();
    ctx.moveTo(point.x, point.y);
}

function drawSignature(event) {
    if (!isDrawing) {
        return;
    }
    event.preventDefault();
    const point = getPoint(event);
    ctx.lineTo(point.x, point.y);
    ctx.stroke();
    signatureBase64.value = signatureCanvas.toDataURL('image/png');
}

function stopDrawing() {
    if (!isDrawing) {
        return;
    }
    isDrawing = false;
    ctx.closePath();
}

roleInputs.forEach((input) => input.addEventListener('change', syncRoleUI));
syncRoleUI();

avatarInput?.addEventListener('change', (event) => updateAvatarPreview(event.target.files?.[0] || null));
signatureButtons.forEach((button) => button.addEventListener('click', () => setSignatureMode(button.dataset.signatureMode)));

setSignatureMode('draw');
resizeCanvas();
window.addEventListener('resize', resizeCanvas);
signatureCanvas.addEventListener('mousedown', startDrawing);
signatureCanvas.addEventListener('mousemove', drawSignature);
signatureCanvas.addEventListener('mouseup', stopDrawing);
signatureCanvas.addEventListener('mouseleave', stopDrawing);
signatureCanvas.addEventListener('touchstart', startDrawing, { passive: true });
signatureCanvas.addEventListener('touchmove', drawSignature, { passive: false });
signatureCanvas.addEventListener('touchend', stopDrawing);
clearSignatureBtn?.addEventListener('click', () => {
    ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
    signatureBase64.value = '';
});

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
    new bootstrap.Tooltip(element);
});
</script>
</body>
</html>
