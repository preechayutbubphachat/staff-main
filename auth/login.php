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
            --ink: #10243b;
            --teal: #1c6b63;
            --gold: #d3a448;
            --fog: #eef4f4;
            --line: rgba(16, 36, 59, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Sarabun', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 15% 15%, rgba(211, 164, 72, 0.18), transparent 28%),
                radial-gradient(circle at 85% 80%, rgba(28, 107, 99, 0.14), transparent 32%),
                linear-gradient(135deg, #f7fafc 0%, #edf3f5 46%, #f8fbfb 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .login-shell {
            width: min(1120px, 100%);
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            border-radius: 34px;
            overflow: hidden;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(255,255,255,0.75);
            backdrop-filter: blur(18px);
            box-shadow: 0 28px 70px rgba(16, 36, 59, 0.14);
        }

        .visual-side {
            min-height: 740px;
            padding: 54px;
            background:
                linear-gradient(160deg, rgba(16, 36, 59, 0.98), rgba(24, 80, 92, 0.94)),
                url('../LOGO/nongphok_logo.png') center 28%/240px no-repeat;
            color: #f6fbff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .visual-side::after {
            content: "";
            position: absolute;
            inset: auto -12% -16% auto;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(211, 164, 72, 0.46), transparent 68%);
            filter: blur(10px);
        }

        .brand-line {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-family: 'Prompt', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.82rem;
        }

        .brand-line img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 14px;
            padding: 6px;
            background: rgba(255,255,255,0.08);
        }

        .visual-copy {
            max-width: 520px;
            position: relative;
            z-index: 1;
        }

        .visual-copy .badge-soft {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .visual-copy h1 {
            font-family: 'Prompt', sans-serif;
            font-size: clamp(2.3rem, 4.2vw, 4.4rem);
            line-height: 1.06;
            margin: 18px 0 16px;
        }

        .visual-copy p {
            max-width: 470px;
            font-size: 1.02rem;
            line-height: 1.85;
            color: rgba(246,251,255,0.82);
        }

        .mini-grid {
            display: grid;
            gap: 14px;
            margin-top: 30px;
        }

        .mini-grid div {
            display: flex;
            gap: 12px;
            align-items: start;
            color: rgba(246,251,255,0.82);
        }

        .mini-grid i { color: var(--gold); }

        .login-side {
            padding: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-panel {
            width: 100%;
            max-width: 430px;
        }

        .panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--fog);
            color: var(--teal);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .login-panel h2,
        .modal-title {
            font-family: 'Prompt', sans-serif;
        }

        .login-panel h2 {
            font-size: 2.1rem;
            margin: 20px 0 8px;
        }

        .login-panel p,
        .modal-subtitle {
            color: #64748b;
            line-height: 1.75;
        }

        .login-panel p {
            margin-bottom: 28px;
        }

        .field-label {
            font-size: 0.86rem;
            font-weight: 700;
            color: #425166;
            margin-bottom: 8px;
        }

        .field-help {
            margin-top: 8px;
            color: #708296;
            font-size: 0.85rem;
        }

        .input-wrap {
            position: relative;
        }

        .form-control {
            border-radius: 18px;
            border: 1px solid var(--line);
            padding: 15px 50px 15px 16px;
            background: rgba(255,255,255,0.88);
        }

        .form-control:focus {
            border-color: rgba(28, 107, 99, 0.55);
            box-shadow: 0 0 0 4px rgba(28, 107, 99, 0.12);
        }

        .input-icon,
        .toggle-password {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: #708296;
        }

        .input-icon { right: 16px; }

        .toggle-password {
            right: 16px;
            border: 0;
            background: transparent;
            padding: 0;
        }

        .btn-submit,
        .btn-outline-action {
            border-radius: 18px;
            padding: 15px 18px;
            font-weight: 700;
        }

        .btn-submit {
            border: 0;
            width: 100%;
            background: linear-gradient(135deg, var(--ink), #24586a);
            color: #fff;
            box-shadow: 0 18px 34px rgba(16, 36, 59, 0.16);
        }

        .btn-submit:hover { color: #fff; }

        .btn-outline-action {
            border: 1px solid rgba(16, 36, 59, 0.16);
            background: #fff;
            color: var(--ink);
        }

        .page-back {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 10;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 999px;
            border: 1px solid rgba(16, 36, 59, 0.12);
            background: rgba(255,255,255,0.92);
            color: var(--ink);
            font-weight: 700;
            box-shadow: 0 12px 26px rgba(16, 36, 59, 0.08);
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        .quick-links button,
        .quick-links a {
            text-decoration: none;
        }

        .support-note {
            margin-top: 24px;
            padding-top: 22px;
            border-top: 1px solid rgba(16, 36, 59, 0.08);
            color: #64748b;
            font-size: 0.92rem;
        }

        .text-link {
            text-decoration: none;
            color: var(--teal);
            font-weight: 700;
        }

        .modal-content {
            border: 0;
            border-radius: 28px;
            box-shadow: 0 30px 70px rgba(16, 36, 59, 0.16);
        }

        .modal-header,
        .modal-body,
        .modal-footer {
            padding-left: 28px;
            padding-right: 28px;
        }

        .modal-header {
            padding-top: 24px;
            padding-bottom: 8px;
            border-bottom: 0;
        }

        .modal-footer {
            border-top: 0;
            padding-top: 8px;
            padding-bottom: 24px;
        }

        @media (max-width: 980px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .visual-side {
                min-height: 380px;
                padding: 34px 28px;
            }
        }

        @media (max-width: 640px) {
            body { padding: 12px; }
            .login-side { padding: 22px; }
            .visual-side { min-height: 320px; }
            .modal-header,
            .modal-body,
            .modal-footer {
                padding-left: 18px;
                padding-right: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="page-back">
        <button type="button" class="btn btn-back" data-simple-back data-fallback-href="/staff-main/">
            <i class="bi bi-arrow-left"></i>ย้อนกลับ
        </button>
    </div>

    <div class="login-shell">
        <section class="visual-side">
            <div class="brand-line">
                <img src="../LOGO/nongphok_logo.png" alt="Logo">
                <span>Nong Phok Hospital</span>
            </div>

            <div class="visual-copy">
                <div class="badge-soft"><i class="bi bi-diagram-3"></i> Unified Access</div>
                <h1>เข้าสู่ระบบครั้งเดียว แล้วใช้งานตามสิทธิ์ได้ทันที</h1>
                <p>ระบบจะอ่านบทบาทจากบัญชีผู้ใช้โดยอัตโนมัติ เพื่อพาไปยังหน้าที่เหมาะกับการทำงานจริงของแต่ละคน ทั้งงานลงเวลาเวร รายงาน และคิวตรวจสอบ</p>

                <div class="mini-grid">
                    <div><i class="bi bi-check2-circle"></i><span>เจ้าหน้าที่ทั่วไปดูข้อมูลและจัดการรายการของตัวเองได้อย่างชัดเจน</span></div>
                    <div><i class="bi bi-check2-circle"></i><span>เจ้าหน้าที่การเงินและผู้ได้รับสิทธิ์ดูรายงานตามขอบเขตที่กำหนด</span></div>
                    <div><i class="bi bi-check2-circle"></i><span>ผู้ตรวจสอบและผู้ดูแลระบบเข้าถึงคิวตรวจสอบและงานหลังบ้านได้ครบถ้วน</span></div>
                </div>
            </div>

            <div class="text-white-50 small">Role-driven sign in for attendance, reports, and approvals</div>
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
        document.querySelectorAll('[data-simple-back]').forEach(function (button) {
            button.addEventListener('click', function () {
                const fallbackHref = button.getAttribute('data-fallback-href') || '/staff-main/';
                const hasHistory = window.history.length > 1 && document.referrer;

                if (hasHistory) {
                    window.history.back();
                    return;
                }

                window.location.href = fallbackHref;
            });
        });

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
