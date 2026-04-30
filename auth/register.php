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
        :root {
            --reg-navy: #073763;
            --reg-navy-2: #075a8a;
            --reg-teal: #17b7b2;
            --reg-blue: #0969e8;
            --reg-ink: #0b2d4d;
            --reg-muted: #6b7f95;
            --reg-border: #dbe8f0;
            --reg-card: rgba(255,255,255,.96);
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Sarabun', sans-serif;
            background:
                radial-gradient(circle at 100% 0%, rgba(34,211,238,.28), transparent 30rem),
                linear-gradient(135deg, #eef8fb 0%, #f8fbff 54%, #e9faf6 100%);
            color: var(--reg-ink);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,.45) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.35) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(90deg, rgba(0,0,0,.22), transparent 58%);
        }

        .wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(360px, 44fr) minmax(620px, 56fr);
            gap: 28px;
            padding: 28px;
        }

        .poster {
            position: sticky;
            top: 28px;
            min-height: calc(100vh - 56px);
            padding: clamp(34px, 4vw, 58px);
            color: #fff;
            overflow: hidden;
            border-radius: 34px;
            background:
                radial-gradient(circle at 10% 10%, rgba(34,211,238,.22), transparent 19rem),
                radial-gradient(circle at 92% 94%, rgba(23,183,178,.42), transparent 20rem),
                linear-gradient(145deg, #042f63 0%, #06457d 44%, #0b827e 100%);
            box-shadow: 0 34px 90px rgba(4,48,99,.28);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            isolation: isolate;
        }

        .poster::before {
            content: "";
            position: absolute;
            inset: auto -15% -8% -10%;
            height: 42%;
            background:
                linear-gradient(135deg, transparent 12%, rgba(255,255,255,.08) 13%, transparent 14%),
                url('../LOGO/nongphok_logo.png') center bottom / 220px no-repeat;
            opacity: .32;
            z-index: -1;
        }

        .poster::after {
            content: "";
            position: absolute;
            right: -120px;
            bottom: -120px;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.16);
            z-index: -1;
        }

        .poster h1,
        .card-title,
        .section-title {
            font-family: 'Prompt', sans-serif;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: 16px;
            font-family: 'Prompt', sans-serif;
        }

        .brand-mark img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            padding: 7px;
            border-radius: 22px;
            background: rgba(255,255,255,.95);
            box-shadow: 0 18px 36px rgba(0,0,0,.18);
        }

        .brand-mark strong {
            display: block;
            font-size: clamp(26px, 2vw, 34px);
            line-height: 1.05;
        }

        .brand-mark span {
            color: rgba(255,255,255,.82);
            font-size: 16px;
        }

        .poster-heading {
            max-width: 580px;
            margin-top: clamp(56px, 9vh, 120px);
        }

        .poster-label {
            margin-bottom: 14px;
            color: rgba(255,255,255,.72);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .poster h1 {
            margin: 0 0 18px;
            font-size: clamp(40px, 4vw, 58px);
            line-height: 1.08;
            letter-spacing: -.045em;
            font-weight: 900;
        }

        .poster-copy {
            max-width: 520px;
            margin: 0;
            color: rgba(255,255,255,.86);
            font-size: 18px;
            line-height: 1.8;
        }

        .feature-list {
            display: grid;
            gap: 22px;
            margin-top: 48px;
            max-width: 520px;
        }

        .feature-item {
            display: grid;
            grid-template-columns: 64px 1fr;
            gap: 18px;
            align-items: center;
        }

        .feature-icon,
        .role-icon,
        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.18);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.18);
            font-size: 28px;
        }

        .feature-item strong {
            display: block;
            margin-bottom: 4px;
            font-family: 'Prompt', sans-serif;
            font-size: 18px;
        }

        .feature-item p {
            margin: 0;
            color: rgba(255,255,255,.76);
            line-height: 1.6;
        }

        .brand-login-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-top: 46px;
            padding: 22px;
            border-radius: 24px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            box-shadow: 0 20px 44px rgba(0,0,0,.16);
            backdrop-filter: blur(14px);
        }

        .brand-login-card p {
            margin: 4px 0 0;
            color: rgba(255,255,255,.76);
        }

        .brand-login-card a {
            min-height: 46px;
            padding: 0 20px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.5);
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 900;
            white-space: nowrap;
            transition: transform .18s ease, background .18s ease, box-shadow .18s ease;
        }

        .brand-login-card a:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,.14);
            box-shadow: 0 12px 28px rgba(0,0,0,.16);
        }

        .panel {
            min-width: 0;
            display: grid;
            align-items: center;
            padding: 8px 6px;
        }

        .card-shell {
            width: min(100%, 940px);
            justify-self: center;
            background: var(--reg-card);
            border: 1px solid rgba(219,232,240,.95);
            border-radius: 32px;
            box-shadow: 0 34px 90px rgba(12,49,82,.14);
            backdrop-filter: blur(18px);
            padding: clamp(26px, 3vw, 42px);
        }

        .register-back-row {
            margin-bottom: 22px;
        }

        .btn-back {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid var(--reg-border);
            background: #f7fbfd;
            color: var(--reg-ink);
            font-weight: 900;
            box-shadow: 0 10px 24px rgba(12,49,82,.06);
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn-back:hover {
            transform: translateY(-1px);
            background: #fff;
            box-shadow: 0 14px 28px rgba(12,49,82,.1);
        }

        .form-eyebrow {
            color: #0a9f97;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .card-title {
            margin: 6px 0 8px;
            color: #092d4c;
            font-size: clamp(32px, 3vw, 44px);
            line-height: 1.15;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .section-box {
            position: relative;
            border: 1px solid rgba(219,232,240,.96);
            border-radius: 24px;
            padding: 22px;
            background: #fff;
            box-shadow: 0 16px 40px rgba(12,49,82,.06);
        }

        .section-box.section-login { order: 1; }
        .section-box.section-general { order: 2; }

        .section-heading {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }

        .step-badge {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            border-radius: 999px;
            background: linear-gradient(135deg, #0969e8, #0aa5c8);
            color: #fff;
            font-weight: 900;
            box-shadow: 0 10px 20px rgba(9,105,232,.18);
        }

        .section-title {
            margin: 0;
            color: #103451;
            font-size: 21px;
            line-height: 1.25;
            font-weight: 900;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 14px;
            border-color: #d9e6ee;
            padding: 11px 13px;
            color: #143650;
            font-weight: 700;
            box-shadow: none !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0891b2;
            box-shadow: 0 0 0 4px rgba(8,145,178,.14) !important;
        }

        .form-label {
            color: #425d72;
            font-size: 13px;
        }

        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .role-card {
            position: relative;
        }

        .role-card input {
            position: absolute;
            inset: 0;
            opacity: 0;
        }

        .role-card label {
            display: grid;
            grid-template-columns: 54px 1fr;
            gap: 14px;
            height: 100%;
            min-height: 106px;
            padding: 16px;
            border: 1px solid rgba(16,36,59,.12);
            border-radius: 18px;
            cursor: pointer;
            transition: transform .18s ease, border-color .18s ease, background .18s ease, box-shadow .18s ease;
        }

        .role-card label:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(12,49,82,.08);
        }

        .role-card input:focus-visible + label {
            outline: 4px solid rgba(8,145,178,.18);
            outline-offset: 2px;
        }

        .role-card input:checked + label {
            border-color: #0969e8;
            background: linear-gradient(135deg, rgba(9,105,232,.08), rgba(23,183,178,.06));
            box-shadow: 0 16px 34px rgba(9,105,232,.12);
        }

        .role-icon {
            width: 54px;
            height: 54px;
            border-radius: 999px;
            background: #e9f3ff;
            color: #0969e8;
            font-size: 24px;
        }

        .role-finance .role-icon {
            background: #ddf8ec;
            color: #059669;
        }

        .role-checker .role-icon {
            background: #f1e8ff;
            color: #7c3aed;
        }

        .finance-box {
            padding: 16px;
            border-radius: 18px;
            background: #f7fbfd;
            border: 1px solid #e2edf4;
        }

        .avatar-upload-card {
            display: grid;
            grid-template-columns: 102px 1fr;
            gap: 18px;
            align-items: center;
            border: 1px dashed #c9dbe6;
            border-radius: 18px;
            padding: 16px;
            background: #f8fbfd;
        }

        .avatar-preview {
            width: 96px;
            height: 96px;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #e1f2f2, #edf5fb);
            border: 1px solid #dcebf2;
            box-shadow: inset 0 0 0 6px #fff, 0 12px 24px rgba(12,49,82,.08);
        }

        .avatar-preview img {
            display: none;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-preview.has-image img {
            display: block;
        }

        .avatar-placeholder {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            color: #58708a;
            font-size: 42px;
        }

        .signature-panel {
            border: 1px solid #dfeaf2;
            border-radius: 20px;
            padding: 16px;
            background: #fbfdff;
        }

        .signature-canvas {
            border-radius: 16px;
            border: 1px dashed rgba(16,36,59,.18);
            background: #fff;
            width: 100%;
            height: 150px;
            touch-action: none;
        }

        .form-footer {
            order: 3;
            padding-top: 6px;
        }

        .form-footer .btn-dark {
            border: 0;
            background: linear-gradient(135deg, #0969e8, #034da3);
            box-shadow: 0 16px 34px rgba(9,105,232,.18);
            font-weight: 900;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .form-footer .btn-dark:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 42px rgba(9,105,232,.24);
        }

        .soft-disabled-field {
            background: #f7fbfd !important;
            color: #91a3b4 !important;
        }

        @media (max-width: 1180px) {
            .wrapper {
                grid-template-columns: 1fr;
                padding: 18px;
            }

            .poster {
                position: relative;
                top: auto;
                min-height: auto;
            }

            .poster-heading {
                margin-top: 42px;
            }

            .panel {
                padding: 0;
            }

            .card-shell {
                width: 100%;
            }
        }

        @media (max-width: 760px) {
            .wrapper {
                gap: 16px;
                padding: 12px;
            }

            .poster,
            .card-shell {
                border-radius: 24px;
            }

            .poster {
                padding: 26px;
            }

            .brand-mark img {
                width: 58px;
                height: 58px;
            }

            .poster h1 {
                font-size: 34px;
            }

            .poster-copy {
                font-size: 16px;
            }

            .feature-list {
                gap: 16px;
                margin-top: 32px;
            }

            .feature-item {
                grid-template-columns: 52px 1fr;
            }

            .feature-icon {
                width: 52px;
                height: 52px;
                font-size: 22px;
            }

            .brand-login-card,
            .avatar-upload-card {
                grid-template-columns: 1fr;
                display: grid;
            }

            .role-grid {
                grid-template-columns: 1fr;
            }

            .section-box {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <section class="poster" aria-label="ภาพรวมระบบลงเวลาโรงพยาบาล">
        <div>
            <div class="brand-mark">
                <img src="../LOGO/nongphok_logo.png" alt="โลโก้โรงพยาบาลหนองพอก">
                <div>
                    <strong>ระบบลงเวลา</strong>
                    <span>โรงพยาบาลหนองพอก</span>
                </div>
            </div>

            <div class="poster-heading">
                <div class="poster-label">Registration</div>
                <h1>สร้างบัญชีผู้ใช้งานใหม่</h1>
                <p class="poster-copy">ลงทะเบียนเพื่อเข้าใช้งานระบบลงเวลาเวรสำหรับบุคลากรโรงพยาบาล ด้วยข้อมูลที่ชัดเจน ปลอดภัย และพร้อมใช้งานจริง</p>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <span class="feature-icon"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <strong>ปลอดภัย เชื่อถือได้</strong>
                        <p>ข้อมูลบัญชีและไฟล์สำคัญถูกจัดเก็บตามมาตรฐานระบบภายในโรงพยาบาล</p>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <strong>ใช้งานได้ทุกที่ ทุกเวลา</strong>
                        <p>รองรับการลงเวลา ตรวจสอบข้อมูล และติดตามประวัติการทำงานของเจ้าหน้าที่</p>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon"><i class="bi bi-people"></i></span>
                    <div>
                        <strong>สำหรับบุคลากรโรงพยาบาล</strong>
                        <p>ออกแบบเพื่อบทบาทเจ้าหน้าที่ หัวหน้างาน และผู้ตรวจสอบในระบบลงเวลาเวร</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="brand-login-card">
            <div>
                <strong>หากคุณมีบัญชีอยู่แล้ว</strong>
                <p>เข้าสู่ระบบเพื่อใช้งานระบบลงเวลา</p>
            </div>
            <a href="login.php">เข้าสู่ระบบ <i class="bi bi-arrow-right"></i></a>
        </div>
    </section>

    <section class="panel d-grid align-items-center">
        <div class="card-shell">
            <div class="register-back-row">
                <button type="button" class="btn btn-back" data-simple-back data-fallback-href="login.php">
                    <i class="bi bi-arrow-left"></i>ย้อนกลับ
                </button>
            </div>

            <div class="form-eyebrow">ลงทะเบียนผู้ใช้งาน</div>
            <h2 class="card-title mt-2 mb-2">สร้างบัญชีผู้ใช้งาน</h2>
            <p class="text-muted mb-0">ระบบจะบันทึกชื่อและนามสกุลแยกกัน พร้อมสร้างชื่อแสดงผลแบบเดิมไว้เพื่อให้ข้อมูลเก่ายังทำงานได้ต่อเนื่อง</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mt-4 mb-0"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="mt-4 d-grid gap-4" data-global-loading-form data-loading-message="กำลังสร้างบัญชีผู้ใช้งาน...">
                <div class="section-box section-login">
                    <div class="section-heading">
                        <span class="step-badge">1</span>
                        <div>
                            <div class="section-title h5 mb-1">ข้อมูลบัญชีผู้ใช้งาน</div>
                            <div class="text-muted small">ใช้สำหรับเข้าสู่ระบบในภายหลัง</div>
                        </div>
                    </div>

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

                    <div class="section-heading mt-2">
                        <span class="step-badge">2</span>
                        <div>
                            <div class="section-title h5 mb-1">บทบาทการใช้งาน</div>
                            <div class="text-muted small">เลือกสิทธิ์เริ่มต้นให้ตรงกับหน้าที่ของผู้ใช้งาน</div>
                        </div>
                    </div>
                    <div class="role-grid">
                        <div class="role-card">
                            <input type="radio" name="role" id="role_staff" value="staff" <?= $form['role'] === 'staff' ? 'checked' : '' ?>>
                            <label for="role_staff">
                                <span class="role-icon"><i class="bi bi-person-fill"></i></span>
                                <span>
                                    <strong>เจ้าหน้าที่ทั่วไป</strong>
                                    <span class="small text-muted d-block mt-2">ลงเวลาเวร ดูประวัติ และรายงานของตนเอง</span>
                                </span>
                            </label>
                        </div>
                        <div class="role-card role-finance">
                            <input type="radio" name="role" id="role_finance" value="finance" <?= $form['role'] === 'finance' ? 'checked' : '' ?>>
                            <label for="role_finance">
                                <span class="role-icon"><i class="bi bi-person-check-fill"></i></span>
                                <span>
                                    <strong>เจ้าหน้าที่หัวหน้างาน</strong>
                                    <span class="small text-muted d-block mt-2">ดูข้อมูลรายบุคคลหรือรายแผนกได้ตามสิทธิ์เสริม</span>
                                </span>
                            </label>
                        </div>
                        <div class="role-card role-checker">
                            <input type="radio" name="role" id="role_checker" value="checker" <?= $form['role'] === 'checker' ? 'checked' : '' ?>>
                            <label for="role_checker">
                                <span class="role-icon"><i class="bi bi-person-badge-fill"></i></span>
                                <span>
                                    <strong>ผู้ตรวจสอบ</strong>
                                    <span class="small text-muted d-block mt-2">ตรวจสอบรายการลงเวลาเวรและดูรายงานที่เกี่ยวข้อง</span>
                                </span>
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
                    <div class="section-heading">
                        <span class="step-badge">3</span>
                        <div>
                            <div class="section-title h5 mb-1">ข้อมูลส่วนตัว</div>
                            <div class="text-muted small">ข้อมูลส่วนตัวและข้อมูลการทำงานของเจ้าหน้าที่</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">คำนำหน้า</label>
                            <select class="form-select soft-disabled-field" disabled>
                                <option>เลือกคำนำหน้า</option>
                            </select>
                            <div class="small text-muted mt-2">ยังไม่เชื่อมกับ schema สมัครปัจจุบัน</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">หมายเลขบัตรประชาชน</label>
                            <input type="text" class="form-control soft-disabled-field" placeholder="เช่น 1-2345-67890-12-3" disabled>
                            <div class="small text-muted mt-2">ช่อง UI เตรียมรองรับ ไม่ส่งค่าไป backend</div>
                        </div>
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
                            <label class="form-label fw-semibold">อีเมล</label>
                            <input type="email" class="form-control soft-disabled-field" placeholder="เช่น name@email.com" disabled>
                            <div class="small text-muted mt-2">ยังไม่บันทึกใน flow สมัครปัจจุบัน</div>
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
                            <div class="section-heading mb-3">
                                <span class="step-badge">4</span>
                                <div>
                                    <div class="section-title h5 mb-1">รูปโปรไฟล์</div>
                                    <div class="text-muted small">ไฟล์ JPG, PNG, WEBP ไม่เกิน 1MB</div>
                                </div>
                            </div>
                            <label class="form-label fw-semibold">รูปประจำตัว (ไม่บังคับ)</label>
                            <div class="avatar-upload-card">
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
                            <div class="section-heading mb-3">
                                <span class="step-badge">5</span>
                                <div>
                                    <div class="section-title h5 mb-1">ลายมือชื่อสำหรับใช้งานในระบบ (ไม่บังคับ)</div>
                                    <div class="text-muted small">วาดลายมือชื่อหรืออัปโหลดรูปภาพได้ ระบบยังสมัครได้หากเว้นว่าง</div>
                                </div>
                            </div>
                            <label class="form-label fw-semibold">ลายเซ็นสำหรับใช้งานในระบบ (ไม่บังคับ)</label>
                            <div class="signature-panel">
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
