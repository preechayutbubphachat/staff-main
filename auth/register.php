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
                    $message = 'สร้างบัญชีสำเร็จแล้ว กำลังพาไปหน้าเข้าสู่ระบบ...';
                    if ($signatureFileName === null) {
                        $message .= ' เพิ่มลายเซ็นภายหลังได้จากหน้าโปรไฟล์';
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
    <title>สมัครใช้งาน — Over Time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700;900&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* ─── Design tokens ────────────────────────────────────────── */
        :root {
            --ot-teal:      #0F9F95;
            --ot-teal-dk:   #0C8780;
            --ot-teal-lt:   #E4F6F5;
            --ot-navy:      #07395A;
            --ot-navy-dk:   #042840;
            --ot-navy-md:   #0B4A70;
            --ot-cyan-bg:   #EAF7F8;
            --ot-mint-bg:   #F4FCFB;
            --ot-border:    #D2E8EC;
            --ot-card:      rgba(255,255,255,0.97);
            --ot-ink:       #0D3347;
            --ot-muted:     #5A7A8E;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Sarabun', sans-serif;
            color: var(--ot-ink);
        }

        /* ─── Page background ───────────────────────────────────────── */
        body {
            background:
                radial-gradient(ellipse 60% 50% at 110% -10%, rgba(15,159,149,.18), transparent),
                radial-gradient(ellipse 50% 60% at -10% 110%, rgba(7,57,90,.12), transparent),
                linear-gradient(150deg, #EAF7F8 0%, #F6FEFB 45%, #EDF6FA 100%);
            min-height: 100vh;
        }

        /* ─── Outer shell ───────────────────────────────────────────── */
        .reg-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reg-shell {
            width: min(1300px, calc(100vw - 40px));
            height: calc(100vh - 40px);
            max-height: 940px;
            display: grid;
            grid-template-columns: 0.88fr 1.12fr;
            gap: 24px;
        }

        /* ─── Brand panel (left) ────────────────────────────────────── */
        .reg-brand {
            border-radius: 28px;
            overflow: hidden;
            background:
                radial-gradient(circle at 15% 15%,  rgba(34,211,238,.20), transparent 18rem),
                radial-gradient(circle at 88% 88%,  rgba(15,159,149,.35), transparent 18rem),
                linear-gradient(145deg, #042840 0%, #07395A 42%, #0C6E6A 100%);
            box-shadow: 0 28px 72px rgba(4,40,64,.30);
            color: #fff;
            padding: clamp(28px, 3vw, 44px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            isolation: isolate;
        }

        /* subtle grid pattern */
        .reg-brand::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: .07;
            background-image:
                linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px);
            background-size: 36px 36px;
        }

        /* decorative circle */
        .reg-brand::after {
            content: '';
            position: absolute;
            right: -100px;
            bottom: -100px;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.12);
            z-index: -1;
        }

        /* ── Logo row */
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #5EE8E2;
            flex-shrink: 0;
        }

        .brand-logo-text strong {
            display: block;
            font-family: 'Prompt', sans-serif;
            font-size: 20px;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -.02em;
        }

        .brand-logo-text span {
            font-size: 12px;
            color: rgba(255,255,255,.65);
        }

        /* ── Heading block */
        .brand-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(28px,4vh,52px) 0;
        }

        .brand-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #5EE8E2;
            margin-bottom: 12px;
        }

        .brand-heading {
            font-family: 'Prompt', sans-serif;
            font-size: clamp(28px, 3.2vw, 42px);
            font-weight: 700;
            line-height: 1.12;
            letter-spacing: -.04em;
            margin: 0 0 14px;
        }

        .brand-desc {
            font-size: 15px;
            line-height: 1.75;
            color: rgba(255,255,255,.78);
            margin: 0 0 28px;
            max-width: 380px;
        }

        /* ── Feature list */
        .brand-features {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .brand-feature {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .brand-feature-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(94,232,226,.14);
            border: 1px solid rgba(94,232,226,.22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #5EE8E2;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .brand-feature-text strong {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 1px;
        }

        .brand-feature-text span {
            font-size: 13px;
            color: rgba(255,255,255,.65);
            line-height: 1.5;
        }

        /* ── Login CTA */
        .brand-cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 20px;
            border-radius: 18px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(12px);
        }

        .brand-cta p {
            margin: 0;
            font-size: 13px;
            color: rgba(255,255,255,.72);
        }

        .brand-cta strong {
            display: block;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-brand-login {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.40);
            background: rgba(255,255,255,.10);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background .18s, transform .18s;
        }

        .btn-brand-login:hover {
            background: rgba(255,255,255,.20);
            color: #fff;
            transform: translateY(-1px);
        }

        /* ─── Form panel (right) ────────────────────────────────────── */
        .reg-form-panel {
            background: var(--ot-card);
            border: 1px solid rgba(210,232,236,.90);
            border-radius: 28px;
            box-shadow: 0 28px 72px rgba(7,57,90,.11);
            backdrop-filter: blur(18px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Sticky header inside card */
        .reg-form-header {
            flex-shrink: 0;
            padding: 22px 28px 16px;
            border-bottom: 1px solid var(--ot-border);
            background: var(--ot-card);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid var(--ot-border);
            background: #F4FBFC;
            color: var(--ot-ink);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s, transform .15s;
            cursor: pointer;
        }

        .back-btn:hover {
            background: #fff;
            color: var(--ot-ink);
            transform: translateY(-1px);
        }

        .form-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: var(--ot-teal);
            margin-top: 10px;
            margin-bottom: 3px;
        }

        .form-main-title {
            font-family: 'Prompt', sans-serif;
            font-size: clamp(22px, 2.4vw, 30px);
            font-weight: 700;
            color: var(--ot-navy);
            line-height: 1.15;
            letter-spacing: -.03em;
            margin: 0;
        }

        .form-subtitle {
            font-size: 13px;
            color: var(--ot-muted);
            margin: 4px 0 0;
        }

        /* Scrollable body */
        .reg-form-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px 24px;
            scroll-behavior: smooth;
        }

        .reg-form-body::-webkit-scrollbar { width: 5px; }
        .reg-form-body::-webkit-scrollbar-track { background: transparent; }
        .reg-form-body::-webkit-scrollbar-thumb { background: var(--ot-border); border-radius: 99px; }

        /* ─── Section blocks ────────────────────────────────────────── */
        .reg-section {
            border: 1px solid var(--ot-border);
            border-radius: 18px;
            padding: 18px 20px;
            background: #fff;
            margin-bottom: 16px;
        }

        .reg-section:last-of-type { margin-bottom: 0; }

        .section-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ot-teal), #0C7A74);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(15,159,149,.22);
        }

        .section-title {
            font-family: 'Prompt', sans-serif;
            font-size: 15px;
            font-weight: 600;
            color: var(--ot-navy);
            margin: 0;
        }

        .section-sub {
            font-size: 12px;
            color: var(--ot-muted);
            margin-left: auto;
        }

        /* ─── Form controls ─────────────────────────────────────────── */
        .form-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #3A6070;
            margin-bottom: 5px;
        }

        .form-label .req { color: #E05252; margin-left: 2px; }

        .form-control, .form-select {
            height: 42px;
            border-radius: 10px;
            border: 1px solid #C8DEE5;
            padding: 0 12px;
            font-size: 14px;
            color: var(--ot-ink);
            font-family: 'Sarabun', sans-serif;
            transition: border-color .18s, box-shadow .18s;
            box-shadow: none !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--ot-teal) !important;
            box-shadow: 0 0 0 3px rgba(15,159,149,.15) !important;
            outline: none;
        }

        .form-control::placeholder { color: #9BB4BD; }

        .field-hint {
            font-size: 11.5px;
            color: #8DAABB;
            margin-top: 3px;
        }

        /* ─── Role cards ────────────────────────────────────────────── */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .role-card { position: relative; }

        .role-card input[type="radio"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 1;
            margin: 0;
        }

        .role-card label {
            display: flex;
            flex-direction: column;
            gap: 7px;
            padding: 12px 14px;
            min-height: 80px;
            border: 1.5px solid #D4E6EC;
            border-radius: 14px;
            cursor: pointer;
            background: #FAFCFD;
            transition: border-color .18s, background .18s, box-shadow .18s, transform .15s;
        }

        .role-card label:hover {
            border-color: var(--ot-teal);
            background: var(--ot-teal-lt);
            transform: translateY(-1px);
        }

        .role-card input:checked + label {
            border-color: var(--ot-teal);
            background: linear-gradient(135deg, rgba(15,159,149,.09), rgba(15,159,149,.04));
            box-shadow: 0 8px 20px rgba(15,159,149,.12);
        }

        .role-card input:focus-visible + label {
            outline: 3px solid rgba(15,159,149,.30);
            outline-offset: 2px;
        }

        .role-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .role-staff  .role-icon { background: #E3F2FD; color: #1976D2; }
        .role-finance .role-icon { background: #E8F5E9; color: #2E7D32; }
        .role-checker .role-icon { background: #F3E5F5; color: #7B1FA2; }

        .role-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--ot-navy);
            line-height: 1.2;
        }

        .role-desc {
            font-size: 11.5px;
            color: var(--ot-muted);
            line-height: 1.4;
        }

        /* ─── Finance extra perms ───────────────────────────────────── */
        .finance-perms {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #F4FCFB;
            border: 1px solid #C2E6E4;
        }

        .finance-perms .perm-title {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ot-teal-dk);
            margin-bottom: 8px;
        }

        .form-check-label { font-size: 13px; color: var(--ot-ink); }
        .form-check-input:checked {
            background-color: var(--ot-teal);
            border-color: var(--ot-teal);
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(15,159,149,.18);
        }

        /* ─── Avatar upload ─────────────────────────────────────────── */
        .avatar-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .avatar-thumb {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #E0F5F3, #EBF4F8);
            border: 2px solid var(--ot-border);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ot-muted);
            font-size: 26px;
            position: relative;
        }

        .avatar-thumb img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .avatar-thumb.has-image img { display: block; }
        .avatar-thumb.has-image .avatar-ph { display: none; }

        .avatar-file-wrap { flex: 1; min-width: 0; }

        /* ─── Signature accordion ───────────────────────────────────── */
        .sig-accordion-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            padding: 10px 0 2px;
            border-top: 1px solid var(--ot-border);
            margin-top: 4px;
        }

        .sig-accordion-header .sig-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--ot-teal-dk);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .sig-accordion-header .chevron {
            font-size: 14px;
            color: var(--ot-muted);
            transition: transform .22s;
        }

        .sig-accordion-header.open .chevron { transform: rotate(180deg); }

        .sig-accordion-body {
            display: none;
            padding-top: 12px;
        }

        .sig-accordion-body.open { display: block; }

        .sig-canvas {
            width: 100%;
            height: 130px;
            border-radius: 10px;
            border: 1.5px dashed #B8D8DE;
            background: #FAFCFD;
            touch-action: none;
            display: block;
        }

        .sig-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            gap: 8px;
            flex-wrap: wrap;
        }

        .sig-mode-btns { display: flex; gap: 6px; }

        .btn-sig {
            padding: 5px 12px;
            border-radius: 7px;
            font-size: 12.5px;
            font-weight: 600;
            border: 1.5px solid var(--ot-border);
            background: #fff;
            color: var(--ot-ink);
            cursor: pointer;
            transition: .15s;
        }

        .btn-sig.active, .btn-sig:hover {
            border-color: var(--ot-teal);
            background: var(--ot-teal-lt);
            color: var(--ot-teal-dk);
        }

        .btn-sig-clear {
            padding: 5px 12px;
            border-radius: 7px;
            font-size: 12.5px;
            font-weight: 600;
            border: 1.5px solid #EFC0B8;
            background: #FFF5F4;
            color: #C0392B;
            cursor: pointer;
            transition: .15s;
        }

        .btn-sig-clear:hover { background: #FDECEA; }

        /* ─── Submit button ─────────────────────────────────────────── */
        .btn-submit {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, var(--ot-teal) 0%, #0C8780 100%);
            color: #fff;
            font-family: 'Prompt', sans-serif;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: .01em;
            cursor: pointer;
            box-shadow: 0 12px 28px rgba(15,159,149,.28);
            transition: transform .18s, box-shadow .18s, opacity .18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 36px rgba(15,159,149,.34);
        }

        .btn-submit:disabled {
            opacity: .65;
            cursor: not-allowed;
            transform: none;
        }

        .login-link {
            text-align: center;
            font-size: 13px;
            color: var(--ot-muted);
            margin-top: 12px;
        }

        .login-link a {
            color: var(--ot-teal-dk);
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover { text-decoration: underline; }

        /* ─── Alert ─────────────────────────────────────────────────── */
        .reg-alert {
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 14px;
            border: 1px solid transparent;
        }

        .reg-alert.success {
            background: #E6F8F5;
            border-color: #A8E0D9;
            color: #0B6B62;
        }

        .reg-alert.danger {
            background: #FEF0EF;
            border-color: #F9BBBB;
            color: #9B2020;
        }

        /* ─── Responsive ────────────────────────────────────────────── */
        @media (max-width: 1080px) {
            .reg-shell {
                grid-template-columns: 1fr;
                height: auto;
                max-height: none;
            }

            .reg-brand {
                min-height: auto;
                padding: 24px;
            }

            .brand-body { padding: 20px 0; }

            .reg-form-panel {
                overflow: visible;
                max-height: none;
            }

            .reg-form-body {
                overflow: visible;
                max-height: none;
            }
        }

        @media (max-width: 640px) {
            .reg-page { padding: 12px; }
            .reg-shell { gap: 14px; }
            .reg-brand, .reg-form-panel { border-radius: 20px; }
            .reg-form-header { padding: 16px 18px 12px; }
            .reg-form-body { padding: 14px 18px 18px; }
            .role-grid { grid-template-columns: 1fr; }
            .brand-cta { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="reg-page">
    <div class="reg-shell">

        <!-- ══════════════════════════════════════
             LEFT — Brand Panel
        ══════════════════════════════════════ -->
        <aside class="reg-brand">
            <div class="brand-logo">
                <div class="brand-logo-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="brand-logo-text">
                    <strong>Over Time</strong>
                    <span>ระบบลงเวลางานสำหรับโรงพยาบาล</span>
                </div>
            </div>

            <div class="brand-body">
                <div class="brand-label">Registration</div>
                <h1 class="brand-heading">สร้างบัญชีผู้ใช้งานใหม่</h1>
                <p class="brand-desc">ลงทะเบียนเพื่อใช้งานระบบลงเวลางานสำหรับบุคลากรโรงพยาบาล ครบทุกบทบาท ปลอดภัย และพร้อมใช้งานได้ทันที</p>
                <div class="brand-features">
                    <div class="brand-feature">
                        <div class="brand-feature-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="brand-feature-text">
                            <strong>ปลอดภัยและตรวจสอบย้อนหลังได้</strong>
                            <span>ข้อมูลบัญชีถูกเก็บตามมาตรฐานความปลอดภัยของระบบโรงพยาบาล</span>
                        </div>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon"><i class="bi bi-calendar2-check"></i></div>
                        <div class="brand-feature-text">
                            <strong>ใช้งานกับเวรและรายงานได้ทันที</strong>
                            <span>ลงเวลาเวร ดูประวัติ และส่งออกรายงานได้หลังสมัครเสร็จ</span>
                        </div>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon"><i class="bi bi-people"></i></div>
                        <div class="brand-feature-text">
                            <strong>รองรับบทบาทเจ้าหน้าที่และผู้ตรวจสอบ</strong>
                            <span>ออกแบบสำหรับเจ้าหน้าที่ หัวหน้างาน และผู้ตรวจสอบ</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="brand-cta">
                <div>
                    <strong>มีบัญชีอยู่แล้ว?</strong>
                    <p>เข้าสู่ระบบเพื่อใช้งานระบบลงเวลา</p>
                </div>
                <a href="login.php" class="btn-brand-login">
                    เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </aside>

        <!-- ══════════════════════════════════════
             RIGHT — Form Panel
        ══════════════════════════════════════ -->
        <main class="reg-form-panel">

            <!-- sticky header -->
            <div class="reg-form-header">
                <button type="button" class="back-btn" data-simple-back data-fallback-href="login.php">
                    <i class="bi bi-arrow-left"></i> ย้อนกลับ
                </button>
                <div class="form-eyebrow mt-2">ลงทะเบียนผู้ใช้งาน</div>
                <h2 class="form-main-title">สร้างบัญชีผู้ใช้งาน</h2>
                <p class="form-subtitle">กรอกข้อมูลที่จำเป็นเพื่อสร้างบัญชีสำหรับเข้าใช้งานระบบ</p>
            </div>

            <!-- scrollable body -->
            <div class="reg-form-body">

                <?php if ($message !== ''): ?>
                    <div class="reg-alert <?= $messageType === 'danger' ? 'danger' : 'success' ?>">
                        <i class="bi bi-<?= $messageType === 'danger' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" id="regForm">

                    <!-- ── Section 1: Account ── -->
                    <div class="reg-section">
                        <div class="section-head">
                            <div class="step-num">1</div>
                            <span class="section-title">ข้อมูลบัญชีผู้ใช้งาน</span>
                            <span class="section-sub">ใช้สำหรับเข้าสู่ระบบ</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">ชื่อผู้ใช้<span class="req">*</span></label>
                                <input type="text" name="username" class="form-control"
                                    value="<?= htmlspecialchars($form['username']) ?>"
                                    placeholder="กรอก username" required autocomplete="username">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">รหัสผ่าน<span class="req">*</span></label>
                                <input type="password" name="password" class="form-control"
                                    placeholder="อย่างน้อย 6 ตัวอักษร" required autocomplete="new-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ยืนยันรหัสผ่าน<span class="req">*</span></label>
                                <input type="password" name="confirm_password" class="form-control"
                                    placeholder="กรอกรหัสผ่านอีกครั้ง" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <!-- ── Section 2: Role ── -->
                    <div class="reg-section">
                        <div class="section-head">
                            <div class="step-num">2</div>
                            <span class="section-title">บทบาทการใช้งาน</span>
                        </div>
                        <div class="role-grid">
                            <div class="role-card role-staff">
                                <input type="radio" name="role" id="role_staff" value="staff"
                                    <?= $form['role'] === 'staff' ? 'checked' : '' ?>>
                                <label for="role_staff">
                                    <div class="role-icon"><i class="bi bi-person-fill"></i></div>
                                    <div class="role-name">เจ้าหน้าที่ทั่วไป</div>
                                    <div class="role-desc">ลงเวลาเวร ดูประวัติ และรายงานของตนเอง</div>
                                </label>
                            </div>
                            <div class="role-card role-finance">
                                <input type="radio" name="role" id="role_finance" value="finance"
                                    <?= $form['role'] === 'finance' ? 'checked' : '' ?>>
                                <label for="role_finance">
                                    <div class="role-icon"><i class="bi bi-person-check-fill"></i></div>
                                    <div class="role-name">เจ้าหน้าที่หัวหน้างาน</div>
                                    <div class="role-desc">ดูข้อมูลรายบุคคลและรายแผนกได้</div>
                                </label>
                            </div>
                            <div class="role-card role-checker">
                                <input type="radio" name="role" id="role_checker" value="checker"
                                    <?= $form['role'] === 'checker' ? 'checked' : '' ?>>
                                <label for="role_checker">
                                    <div class="role-icon"><i class="bi bi-person-badge-fill"></i></div>
                                    <div class="role-name">ผู้ตรวจสอบ</div>
                                    <div class="role-desc">ตรวจสอบ อนุมัติ และส่งออกรายงาน</div>
                                </label>
                            </div>
                        </div>

                        <div class="finance-perms" id="financePermissions" <?= $form['role'] === 'finance' ? '' : 'hidden' ?>>
                            <div class="perm-title"><i class="bi bi-toggles"></i> สิทธิ์เพิ่มเติมสำหรับเจ้าหน้าที่หัวหน้างาน</div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="perm_view_all_staff"
                                    name="can_view_all_staff" value="1"
                                    <?= $form['can_view_all_staff'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="perm_view_all_staff">ดูข้อมูลรายบุคคลของเจ้าหน้าที่ได้</label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="perm_view_department"
                                    name="can_view_department_reports" value="1"
                                    <?= $form['can_view_department_reports'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="perm_view_department">ดูรายงานสรุปรายแผนกได้</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="perm_export"
                                    name="can_export_reports" value="1"
                                    <?= $form['can_export_reports'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="perm_export">ส่งออกรายงานได้</label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Section 3: Personal Info ── -->
                    <div class="reg-section">
                        <div class="section-head">
                            <div class="step-num">3</div>
                            <span class="section-title">ข้อมูลส่วนตัว</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">ชื่อ<span class="req">*</span></label>
                                <input type="text" name="first_name" class="form-control"
                                    value="<?= htmlspecialchars($form['first_name']) ?>"
                                    placeholder="ชื่อจริง" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">นามสกุล<span class="req">*</span></label>
                                <input type="text" name="last_name" class="form-control"
                                    value="<?= htmlspecialchars($form['last_name']) ?>"
                                    placeholder="นามสกุล" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ตำแหน่ง</label>
                                <input type="text" name="position_name" class="form-control"
                                    value="<?= htmlspecialchars($form['position_name']) ?>"
                                    placeholder="เช่น พยาบาลวิชาชีพ">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">เบอร์โทร</label>
                                <input type="text" name="phone_number" class="form-control"
                                    value="<?= htmlspecialchars($form['phone_number']) ?>"
                                    placeholder="0812345678" inputmode="numeric" maxlength="10" pattern="\d{10}">
                                <div class="field-hint">ตัวเลข 10 หลัก</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">แผนก<span class="req">*</span></label>
                                <select name="department_id" class="form-select" required>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= (int) $dept['id'] ?>"
                                            <?= (string) $form['department_id'] === (string) $dept['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Avatar (compact) -->
                            <?php if ($hasProfileImageColumn): ?>
                            <div class="col-12">
                                <label class="form-label">รูปโปรไฟล์ <span style="color:var(--ot-muted);font-weight:400">(ไม่บังคับ)</span></label>
                                <div class="avatar-row">
                                    <div class="avatar-thumb" id="avatarThumb">
                                        <i class="bi bi-person avatar-ph"></i>
                                        <img src="" alt="preview" id="avatarImg">
                                    </div>
                                    <div class="avatar-file-wrap">
                                        <input type="file" name="profile_image" id="profile_image"
                                            class="form-control" accept="image/png,image/jpeg,image/webp"
                                            style="height:42px;padding:8px 12px;">
                                        <div class="field-hint">JPG / PNG / WEBP ไม่เกิน 1MB · สัดส่วน 1:1</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Signature accordion -->
                            <div class="col-12">
                                <div class="sig-accordion-header" id="sigToggle">
                                    <span class="sig-title">
                                        <i class="bi bi-pen"></i>
                                        ลายมือชื่อ <span style="font-weight:400;color:var(--ot-muted)">(ไม่บังคับ)</span>
                                    </span>
                                    <i class="bi bi-chevron-down chevron"></i>
                                </div>
                                <div class="sig-accordion-body" id="sigBody">
                                    <div class="sig-controls mb-2">
                                        <div class="sig-mode-btns">
                                            <button type="button" class="btn-sig active" data-sig-mode="draw">วาดลายเซ็น</button>
                                            <button type="button" class="btn-sig" data-sig-mode="upload">อัปโหลดรูปภาพ</button>
                                        </div>
                                        <button type="button" class="btn-sig-clear" id="clearSigBtn">ล้าง</button>
                                    </div>
                                    <div id="sigDrawPanel">
                                        <canvas id="sigCanvas" class="sig-canvas"></canvas>
                                        <input type="hidden" name="signature_base64" id="sigBase64">
                                        <div class="field-hint mt-1">ใช้เมาส์หรือหน้าจอสัมผัสวาดลายเซ็นในกรอบด้านบน</div>
                                    </div>
                                    <div id="sigUploadPanel" hidden>
                                        <input type="file" name="signature_file" class="form-control"
                                            accept="image/png,image/jpeg,image/webp" style="height:42px;padding:8px 12px;">
                                        <div class="field-hint mt-1">JPG / PNG / WEBP · เพิ่มลายเซ็นภายหลังได้จากโปรไฟล์</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Submit ── -->
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="bi bi-person-plus"></i>
                        สร้างบัญชีและเริ่มใช้งาน
                    </button>
                    <div class="login-link">
                        มีบัญชีอยู่แล้ว?
                        <a href="login.php">กลับไปหน้าเข้าสู่ระบบ</a>
                    </div>

                </form>
            </div><!-- /reg-form-body -->
        </main>

    </div><!-- /reg-shell -->
</div><!-- /reg-page -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    /* ── Back button ── */
    document.querySelectorAll('[data-simple-back]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const fallback = btn.getAttribute('data-fallback-href') || 'login.php';
            if (window.history.length > 1 && document.referrer) {
                window.history.back();
            } else {
                window.location.href = fallback;
            }
        });
    });

    /* ── Role toggle ── */
    const roleInputs      = document.querySelectorAll('input[name="role"]');
    const financePerms    = document.getElementById('financePermissions');

    function syncRole() {
        const val = document.querySelector('input[name="role"]:checked')?.value || 'staff';
        if (financePerms) financePerms.hidden = val !== 'finance';
    }

    roleInputs.forEach(function (r) { r.addEventListener('change', syncRole); });
    syncRole();

    /* ── Avatar preview ── */
    const avatarInput = document.getElementById('profile_image');
    const avatarThumb = document.getElementById('avatarThumb');
    const avatarImg   = document.getElementById('avatarImg');

    if (avatarInput) {
        avatarInput.addEventListener('change', function (e) {
            const file = e.target.files?.[0];
            if (!file) {
                avatarThumb?.classList.remove('has-image');
                if (avatarImg) avatarImg.removeAttribute('src');
                return;
            }
            avatarImg.src = URL.createObjectURL(file);
            avatarThumb?.classList.add('has-image');
        });
    }

    /* ── Signature accordion ── */
    const sigToggle = document.getElementById('sigToggle');
    const sigBody   = document.getElementById('sigBody');

    sigToggle?.addEventListener('click', function () {
        const open = sigBody.classList.toggle('open');
        sigToggle.classList.toggle('open', open);
        if (open) resizeCanvas();
    });

    /* ── Signature mode switch ── */
    const sigModeBtns     = document.querySelectorAll('[data-sig-mode]');
    const sigDrawPanel    = document.getElementById('sigDrawPanel');
    const sigUploadPanel  = document.getElementById('sigUploadPanel');

    function setSigMode(mode) {
        sigModeBtns.forEach(function (b) {
            b.classList.toggle('active', b.dataset.sigMode === mode);
        });
        if (sigDrawPanel)   sigDrawPanel.hidden   = mode !== 'draw';
        if (sigUploadPanel) sigUploadPanel.hidden  = mode !== 'upload';
    }

    sigModeBtns.forEach(function (b) {
        b.addEventListener('click', function () { setSigMode(b.dataset.sigMode); });
    });
    setSigMode('draw');

    /* ── Canvas drawing ── */
    const canvas   = document.getElementById('sigCanvas');
    const sigB64   = document.getElementById('sigBase64');
    const clearBtn = document.getElementById('clearSigBtn');
    let ctx, isDrawing = false;

    if (canvas) {
        ctx = canvas.getContext('2d');

        function resizeCanvas() {
            const ratio = window.devicePixelRatio || 1;
            const rect  = canvas.getBoundingClientRect();
            canvas.width  = rect.width  * ratio;
            canvas.height = rect.height * ratio;
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(ratio, ratio);
            ctx.lineCap   = 'round';
            ctx.lineJoin  = 'round';
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#0D3347';
        }

        function pt(e) {
            const r = canvas.getBoundingClientRect();
            const s = e.touches ? e.touches[0] : e;
            return { x: s.clientX - r.left, y: s.clientY - r.top };
        }

        canvas.addEventListener('mousedown',  function (e) { isDrawing = true; ctx.beginPath(); const p = pt(e); ctx.moveTo(p.x, p.y); });
        canvas.addEventListener('mousemove',  function (e) { if (!isDrawing) return; const p = pt(e); ctx.lineTo(p.x, p.y); ctx.stroke(); if (sigB64) sigB64.value = canvas.toDataURL('image/png'); });
        canvas.addEventListener('mouseup',    function ()  { isDrawing = false; ctx.closePath(); });
        canvas.addEventListener('mouseleave', function ()  { isDrawing = false; });
        canvas.addEventListener('touchstart', function (e) { isDrawing = true; ctx.beginPath(); const p = pt(e); ctx.moveTo(p.x, p.y); }, { passive: true });
        canvas.addEventListener('touchmove',  function (e) { if (!isDrawing) return; e.preventDefault(); const p = pt(e); ctx.lineTo(p.x, p.y); ctx.stroke(); if (sigB64) sigB64.value = canvas.toDataURL('image/png'); }, { passive: false });
        canvas.addEventListener('touchend',   function ()  { isDrawing = false; });

        clearBtn?.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (sigB64) sigB64.value = '';
        });

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }

    window.resizeCanvas = resizeCanvas || function(){};

    /* ── Submit loading state ── */
    document.getElementById('regForm')?.addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังสร้างบัญชี...';
        }
    });

})();
</script>
</body>
</html>
