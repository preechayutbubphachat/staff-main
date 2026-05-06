<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['id'])) {
    app_redirect_after_login();
}

$loginError = '';
$resetError = '';
$successMessage = '';
$usernameValue = '';
$resetUsernameValue = '';
$resetPhoneValue = '';
$openResetModal = false;
$hasRoleColumns = (bool) $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'reset_password') {
        $openResetModal = true;
        $resetUsernameValue = trim($_POST['reset_username'] ?? '');
        $resetPhoneValue = preg_replace('/\D+/', '', $_POST['reset_phone_number'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!app_verify_csrf_token($_POST['_csrf'] ?? '', 'forgot_password')) {
            $resetError = 'ไม่สามารถตรวจสอบคำขอได้ กรุณาลองใหม่อีกครั้ง';
        } elseif ($resetUsernameValue === '' || $resetPhoneValue === '' || $newPassword === '' || $confirmPassword === '') {
            $resetError = 'กรุณากรอกชื่อผู้ใช้ เบอร์โทร และรหัสผ่านใหม่ให้ครบ';
        } elseif (!preg_match('/^\d{10}$/', $resetPhoneValue)) {
            $resetError = 'เบอร์โทรต้องเป็นตัวเลข 10 หลัก';
        } elseif (mb_strlen($newPassword) < 6) {
            $resetError = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $resetError = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $stmt = $conn->prepare('SELECT id, username, phone_number FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$resetUsernameValue]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || preg_replace('/\D+/', '', (string) ($user['phone_number'] ?? '')) !== $resetPhoneValue) {
                $resetError = 'ชื่อผู้ใช้หรือเบอร์โทรไม่ตรงกับข้อมูลในระบบ';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->execute([$hashedPassword, $user['id']]);

                $successMessage = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว สามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้ทันที';
                $openResetModal = false;
                $resetUsernameValue = '';
                $resetPhoneValue = '';
            }
        }
    } else {
        $usernameValue = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!app_verify_csrf_token($_POST['_csrf'] ?? '', 'login')) {
            $loginError = 'ไม่สามารถตรวจสอบคำขอเข้าสู่ระบบได้ กรุณาลองใหม่อีกครั้ง';
        } elseif ($usernameValue === '' || $password === '') {
            $loginError = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบ';
        } elseif (!$hasRoleColumns) {
            $loginError = 'ระบบสิทธิ์แบบใหม่ยังไม่พร้อมใช้งาน กรุณารัน migrations/001_add_roles_permissions.sql ก่อน';
        } else {
            $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$usernameValue]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $passwordOk = false;
            if ($user) {
                $storedPassword = (string) ($user['password'] ?? '');
                if (password_get_info($storedPassword)['algo'] !== null) {
                    $passwordOk = password_verify($password, $storedPassword);
                } else {
                    $passwordOk = hash_equals($storedPassword, $password);
                }
            }

            if ($user && $passwordOk) {
                app_set_auth_session($user);
                app_redirect_after_login();
            } else {
                $loginError = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | ระบบลงเวลาเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --ink: #082B45;
            --teal: #0F9F95;
            --teal-dark: #063B4F;
            --teal-mid: #075D6E;
            --fog: #EAF7F8;
            --line: rgba(8, 43, 69, 0.13);
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Prompt', 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.18), transparent 35%),
                radial-gradient(circle at top right, rgba(125, 211, 252, 0.18), transparent 35%),
                linear-gradient(135deg, #F7FEFF 0%, #EAF7F8 100%);
            display: grid;
            place-items: center;
            padding: 24px 20px;
        }

        /* ── Shell ── */
        .login-shell {
            width: min(1180px, 100%);
            max-width: 100%;
            min-width: 0;
            max-height: calc(100vh - 48px);
            max-height: min(820px, calc(100vh - 48px));
            display: grid;
            grid-template-columns: 55% 45%;
            border-radius: 32px;
            overflow: hidden;
            background: #fff;
            border: 1px solid #D8E7EA;
            box-shadow: 0 24px 72px rgba(6, 59, 79, 0.14);
        }

        /* ── Left (Brand) Panel — same visual language as register page ── */
        .visual-side {
            padding: clamp(28px, 3vw, 42px);
            background:
                radial-gradient(circle at 15% 15%,  rgba(34, 211, 238, .20), transparent 18rem),
                radial-gradient(circle at 88% 88%,  rgba(15, 159, 148, .35), transparent 18rem),
                linear-gradient(145deg, #042840 0%, #07395A 42%, #0C6E6A 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0;
            position: relative;
            isolation: isolate;
            overflow: hidden;
            min-width: 0;
        }

        /* subtle grid pattern — same as reg-brand */
        .visual-side::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: .07;
            background-image:
                linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px);
            background-size: 36px 36px;
            pointer-events: none;
        }

        /* decorative circle — same as reg-brand */
        .visual-side::after {
            content: "";
            position: absolute;
            right: -100px;
            bottom: -100px;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, .12);
            z-index: -1;
            pointer-events: none;
        }

        .visual-side > * { position: relative; z-index: 1; }

        /* ── Brand logo row */
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: clamp(8px, 1.6vh, 16px);
        }

        .brand-logo-icon {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.65rem;
            color: #5EE8E2;
            flex-shrink: 0;
        }

        .brand-logo-text strong {
            display: block;
            font-family: 'Prompt', sans-serif;
            font-size: clamp(1.45rem, 2vw, 1.72rem);
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -.02em;
            color: #fff;
        }

        .brand-logo-text span {
            font-size: 0.84rem;
            color: rgba(255, 255, 255, .65);
        }

        /* ── Body (center) */
        .brand-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(24px, 4vh, 44px) 0;
        }

        .brand-desc {
            font-size: 0.95rem;
            line-height: 1.78;
            color: rgba(255, 255, 255, .78);
            margin: 0 0 26px;
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
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: rgba(94, 232, 226, .14);
            border: 1px solid rgba(94, 232, 226, .22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            color: #5EE8E2;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .brand-feature-text strong {
            display: block;
            font-size: 0.87rem;
            font-weight: 600;
            margin-bottom: 1px;
            color: #fff;
        }

        .brand-feature-text span {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, .62);
            line-height: 1.5;
        }

        /* ── Bottom glass CTA card */
        .brand-cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 20px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .18);
            backdrop-filter: blur(12px);
        }

        .brand-cta p {
            margin: 0;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, .72);
        }

        .brand-cta strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
        }

        /* ── Right (Form) Panel ── */
        .login-side {
            padding: 40px 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            min-width: 0;
        }

        .login-panel {
            width: 100%;
            max-width: 440px;
        }

        .panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            background: var(--fog);
            color: var(--teal);
            font-size: 0.78rem;
            font-weight: 700;
        }

        .login-panel h2,
        .modal-title {
            font-family: 'Prompt', sans-serif;
        }

        .login-panel h2 {
            font-size: clamp(2rem, 3.2vw, 2.6rem);
            line-height: 1.1;
            letter-spacing: -0.04em;
            margin: 16px 0 10px;
            color: #082B45;
        }

        .login-panel > p,
        .modal-subtitle {
            color: #64748b;
            line-height: 1.65;
        }

        .login-panel > p {
            margin-bottom: 22px;
            font-size: 0.92rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .field-label {
            font-size: 0.84rem;
            font-weight: 700;
            color: #425166;
            margin-bottom: 6px;
        }

        .field-help {
            margin-top: 6px;
            color: #708296;
            font-size: 0.82rem;
        }

        .input-wrap { position: relative; }

        .form-control {
            min-height: 50px;
            border-radius: 14px;
            border: 1px solid var(--line);
            padding: 12px 46px 12px 16px;
            background: #fafcfc;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: rgba(15, 159, 148, 0.55);
            box-shadow: 0 0 0 4px rgba(15, 159, 148, 0.12);
            background: #fff;
        }

        .input-icon,
        .toggle-password {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .input-icon { right: 14px; pointer-events: none; }

        .toggle-password {
            right: 14px;
            border: 0;
            background: transparent;
            padding: 0;
            cursor: pointer;
        }

        .btn-submit,
        .btn-outline-action {
            border-radius: 14px;
            min-height: 50px;
            padding: 12px 20px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .btn-submit {
            border: 0;
            width: 100%;
            background: linear-gradient(135deg, #073B5A 0%, #0F9F95 100%);
            color: #fff;
            box-shadow: 0 14px 30px rgba(6, 59, 79, 0.22);
            font-size: 0.97rem;
        }

        .btn-submit:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 18px 38px rgba(6, 59, 79, 0.30);
        }

        .btn-outline-action {
            border: 1px solid rgba(15, 37, 68, 0.16);
            background: #fff;
            color: var(--ink);
        }

        .btn-outline-action:hover { transform: translateY(-1px); }

        .page-back { display: none; }

        /* SSO hidden — no SSO flow implemented yet */
        .login-divider,
        .sso-button { display: none !important; }

        /* Back button — pill outline white on dark panel */
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, .35);
            background: rgba(255, 255, 255, .10);
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            text-decoration: none;
            transition: background .16s, transform .16s;
            align-self: flex-start;
            width: auto;
            margin-bottom: 20px;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, .20);
            transform: translateY(-1px);
            color: #fff;
            text-decoration: none;
        }

        .support-note {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(15, 37, 68, 0.08);
            color: #64748b;
            font-size: 0.9rem;
        }

        .text-link {
            text-decoration: none;
            color: var(--teal);
            font-weight: 700;
        }

        .text-link:hover { color: var(--teal-mid); text-decoration: underline; }

        .modal-content {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(6, 47, 79, 0.16);
        }

        .modal-header,
        .modal-body,
        .modal-footer {
            padding-left: 26px;
            padding-right: 26px;
        }

        .modal-header {
            padding-top: 22px;
            padding-bottom: 6px;
            border-bottom: 0;
        }

        .modal-footer {
            border-top: 0;
            padding-top: 6px;
            padding-bottom: 22px;
        }

        /* ── Responsive ── */
        @media (max-width: 980px) {
            .login-shell {
                grid-template-columns: 1fr;
                max-height: none;
            }

            .visual-side {
                padding: 28px 26px;
                min-height: 360px;
            }

            .brand-body { padding: 20px 0; }

            .login-side { padding: 32px 26px; }
        }

        @media (max-width: 640px) {
            body {
                padding: 10px;
                overflow-x: hidden;
            }
            .login-shell {
                width: calc(100vw - 20px);
                max-width: calc(100vw - 20px);
                border-radius: 20px;
            }
            .login-side { padding: 22px 18px; }
            .visual-side {
                min-height: auto;
                padding: 22px 18px 24px;
                width: 100%;
            }
            .brand-body { padding: 16px 0; }
            .brand-logo {
                gap: 14px;
                margin-bottom: 12px;
            }
            .brand-logo-icon {
                width: 52px;
                height: 52px;
                border-radius: 16px;
                font-size: 1.45rem;
            }
            .brand-logo-text strong {
                font-size: 1.35rem;
            }
            .brand-logo-text span {
                font-size: 0.78rem;
            }
            .brand-features { gap: 10px; }
            .brand-cta { flex-direction: column; align-items: flex-start; gap: 10px; }
            .modal-header,
            .modal-body,
            .modal-footer { padding-left: 16px; padding-right: 16px; }
        }

        @media (max-width: 520px) {
            .login-shell { max-width: 370px; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <section class="visual-side">

            <!-- ปุ่มหน้าแรก — link ตรงไป /staff-main/ -->
            <a href="/staff-main/" class="btn-back">
                <i class="bi bi-arrow-left"></i>หน้าแรก
            </a>

            <!-- Brand logo -->
            <div class="brand-logo">
                <div class="brand-logo-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="brand-logo-text">
                    <strong>Over Time</strong>
                    <span>ระบบลงเวลางานสำหรับโรงพยาบาล</span>
                </div>
            </div>

            <!-- Body copy -->
            <div class="brand-body">
                <p class="brand-desc">เข้าสู่ระบบเพื่อดูสถานะการลงเวลางานของบุคลากรและแผนกต่าง ๆ แบบเรียลไทม์ พร้อมจัดการสิทธิ์ตามบทบาทผู้ใช้งาน</p>

                <div class="brand-features">
                    <div class="brand-feature">
                        <div class="brand-feature-icon"><i class="bi bi-check2-circle"></i></div>
                        <div class="brand-feature-text">
                            <strong>ดูและจัดการรายการของตัวเองได้ทันที</strong>
                            <span>เจ้าหน้าที่เข้าถึงประวัติเวรและข้อมูลส่วนตัวได้ชัดเจน</span>
                        </div>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon"><i class="bi bi-bar-chart-line"></i></div>
                        <div class="brand-feature-text">
                            <strong>รายงานตามขอบเขตสิทธิ์ที่กำหนด</strong>
                            <span>เจ้าหน้าที่การเงินและหัวหน้าแผนกดูรายงานได้ตามสิทธิ์</span>
                        </div>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="brand-feature-text">
                            <strong>ระบบสิทธิ์และตรวจสอบครบถ้วน</strong>
                            <span>ผู้ดูแลระบบเข้าถึงคิวตรวจสอบและงานหลังบ้านได้ครบ</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom glass card — hospital info -->
            <div class="brand-cta">
                <div>
                    <strong>โรงพยาบาลหนองพอก</strong>
                    <p>ลงเวลาเวร รายงาน และตรวจสอบสิทธิ์ในจุดเดียว</p>
                </div>
                <i class="bi bi-shield-check" style="font-size:1.6rem;color:rgba(255,255,255,.65);flex-shrink:0"></i>
            </div>

        </section>

        <section class="login-side">
            <div class="login-panel">
                <div class="panel-badge"><i class="bi bi-box-arrow-in-right"></i> Sign In</div>
                <h2>เข้าสู่ระบบ</h2>
                <p>ใช้ชื่อผู้ใช้และรหัสผ่านเดิมเพื่อเข้าสู่ระบบ หากลืมรหัสผ่านสามารถยืนยันตัวตนด้วยชื่อผู้ใช้และเบอร์โทรที่ลงทะเบียนไว้ แล้วตั้งรหัสผ่านใหม่ได้ทันที</p>

                <?php if ($loginError !== ''): ?>
                    <div class="alert alert-danger rounded-4 mb-4"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success rounded-4 mb-4"><?= htmlspecialchars($successMessage) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(app_csrf_token('login')) ?>">

                    <div class="mb-3">
                        <label class="field-label">ชื่อผู้ใช้งาน</label>
                        <div class="input-wrap">
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($usernameValue) ?>" placeholder="ระบุ Username" required>
                            <i class="bi bi-person input-icon"></i>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="field-label">รหัสผ่าน</label>
                        <div class="input-wrap">
                            <input type="password" id="password" name="password" class="form-control" placeholder="ระบุ Password" required>
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                <i class="bi bi-eye" id="eyeOpen"></i>
                                <i class="bi bi-eye-slash d-none" id="eyeClosed"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mb-4">
                        <button
                            type="button"
                            class="btn btn-link text-link p-0"
                            data-bs-toggle="modal"
                            data-bs-target="#forgotPasswordModal"
                        >
                            ลืมรหัสผ่าน / เปลี่ยนรหัสผ่าน
                        </button>
                    </div>

                    <button type="submit" class="btn btn-submit">เข้าสู่ระบบและไปยัง Dashboard</button>
                </form>

                <div class="login-divider">หรือ</div>

                <button type="button" class="btn sso-button" aria-disabled="true" title="ยังไม่ได้เชื่อมต่อ SSO ในระบบนี้">
                    <i class="bi bi-shield-check"></i>
                    เข้าสู่ระบบด้วย SSO (องค์กร)
                </button>

                <div class="support-note">
                    ยังไม่มีบัญชี?
                    <a href="register.php" class="text-link">สร้างบัญชีใหม่พร้อมกำหนดสิทธิ์</a>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4 mb-2" id="forgotPasswordModalLabel">ลืมรหัสผ่าน / เปลี่ยนรหัสผ่าน</h3>
                        <div class="modal-subtitle">ยืนยันตัวตนด้วยชื่อผู้ใช้และเบอร์โทร 10 หลักที่ลงทะเบียนไว้ จากนั้นกำหนดรหัสผ่านใหม่เพื่อเข้าใช้งาน</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <?php if ($resetError !== ''): ?>
                        <div class="alert alert-danger rounded-4 mb-4"><?= htmlspecialchars($resetError) ?></div>
                    <?php endif; ?>

                    <form method="post" id="resetPasswordForm">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(app_csrf_token('forgot_password')) ?>">

                        <div class="mb-3">
                            <label class="field-label">ชื่อผู้ใช้</label>
                            <input type="text" class="form-control" name="reset_username" value="<?= htmlspecialchars($resetUsernameValue) ?>" placeholder="ระบุ Username" required>
                        </div>

                        <div class="mb-3">
                            <label class="field-label">เบอร์โทรที่ลงทะเบียนไว้</label>
                            <input
                                type="text"
                                class="form-control"
                                name="reset_phone_number"
                                value="<?= htmlspecialchars($resetPhoneValue) ?>"
                                placeholder="เช่น 0812345678"
                                inputmode="numeric"
                                maxlength="10"
                                pattern="\d{10}"
                                required
                            >
                            <div class="field-help">ระบบจะใช้ชื่อผู้ใช้และเบอร์โทรคู่นี้ในการยืนยันตัวตนก่อนเปลี่ยนรหัสผ่าน</div>
                        </div>

                        <div class="mb-3">
                            <label class="field-label">รหัสผ่านใหม่</label>
                            <input type="password" class="form-control" name="new_password" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                        </div>

                        <div class="mb-0">
                            <label class="field-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="กรอกรหัสผ่านใหม่ซ้ำอีกครั้ง" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-action" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="resetPasswordForm" class="btn btn-submit w-auto px-4">บันทึกรหัสผ่านใหม่</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeOpen = document.getElementById('eyeOpen');
        const eyeClosed = document.getElementById('eyeClosed');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                const showPassword = passwordInput.type === 'password';
                passwordInput.type = showPassword ? 'text' : 'password';
                eyeOpen.classList.toggle('d-none', showPassword);
                eyeClosed.classList.toggle('d-none', !showPassword);
            });
        }

        <?php if ($openResetModal): ?>
        window.addEventListener('load', function () {
            const modalElement = document.getElementById('forgotPasswordModal');
            if (!modalElement) {
                return;
            }

            const resetModal = new bootstrap.Modal(modalElement);
            resetModal.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>
