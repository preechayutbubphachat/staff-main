<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$userId = (int) $_SESSION['id'];
$message = '';
$messageType = 'success';
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));
    $signatureName = null;
    $profileImageName = null;

    $signatureFolder = __DIR__ . '/../uploads/signatures/';
    $profileFolder = __DIR__ . '/../uploads/avatars/';

    if (!is_dir($signatureFolder)) {
        mkdir($signatureFolder, 0777, true);
    }
    if (!is_dir($profileFolder)) {
        mkdir($profileFolder, 0777, true);
    }

    if (!empty($_POST['signature_base64'])) {
        $imageParts = explode(';base64,', (string) $_POST['signature_base64']);
        $imageBase64 = isset($imageParts[1]) ? base64_decode($imageParts[1], true) : false;
        if ($imageBase64 !== false) {
            $signatureName = 'sign_drawn_' . time() . '_' . uniqid('', true) . '.png';
            file_put_contents($signatureFolder . $signatureName, $imageBase64);
        }
    } elseif (!empty($_FILES['signature']['name'])) {
        $signatureExt = strtolower(pathinfo((string) $_FILES['signature']['name'], PATHINFO_EXTENSION));
        $signatureExt = in_array($signatureExt, ['png', 'jpg', 'jpeg'], true) ? $signatureExt : 'png';
        $signatureName = 'sign_upload_' . time() . '_' . uniqid('', true) . '.' . $signatureExt;
        move_uploaded_file($_FILES['signature']['tmp_name'], $signatureFolder . $signatureName);
    }

    if (!empty($_FILES['profile_image']['name']) && $hasProfileImageColumn) {
        $tmpPath = (string) ($_FILES['profile_image']['tmp_name'] ?? '');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowedProfileTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (
            $tmpPath !== ''
            && is_uploaded_file($tmpPath)
            && ((int) ($_FILES['profile_image']['size'] ?? 0) <= 2 * 1024 * 1024)
            && isset($allowedProfileTypes[$mime])
        ) {
            $profileImageName = 'profile_' . time() . '_' . uniqid('', true) . '.' . $allowedProfileTypes[$mime];
            move_uploaded_file($tmpPath, $profileFolder . $profileImageName);
        } else {
            $message = 'รูปประจำตัวต้องเป็นไฟล์ JPG, PNG หรือ WEBP และมีขนาดไม่เกิน 2 MB';
            $messageType = 'danger';
        }
    }

    if ($fullname === '') {
        $message = 'กรุณากรอกชื่อ - นามสกุลให้ครบถ้วน';
        $messageType = 'danger';
    } elseif ($messageType !== 'danger') {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, password = ? WHERE id = ?");
            $stmt->execute([$fullname, $hash, $userId]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET fullname = ? WHERE id = ?");
            $stmt->execute([$fullname, $userId]);
        }

        if ($signatureName) {
            $stmt = $conn->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
            $stmt->execute([$signatureName, $userId]);
        }

        if ($profileImageName && $hasProfileImageColumn) {
            $stmt = $conn->prepare("UPDATE users SET profile_image_path = ? WHERE id = ?");
            $stmt->execute([$profileImageName, $userId]);
        }

        $_SESSION['fullname'] = $fullname;
        $message = 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว';
        if (!$hasProfileImageColumn && !empty($_FILES['profile_image']['name'])) {
            $message .= ' แต่รูปโปรไฟล์จะใช้งานถาวรได้หลังรัน migration เพิ่มคอลัมน์ profile_image_path';
            $messageType = 'warning';
        }
    }
}

$stmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$role = app_current_role();
$roleLabel = app_role_label($role);
$roleCompactLabel = app_role_compact_label($role);
$permissions = $_SESSION['permissions'] ?? [];
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($user['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($user);
$departmentLabel = trim((string) ($user['department_name'] ?? '')) !== '' ? (string) $user['department_name'] : '-';
$positionLabel = trim((string) ($user['position_name'] ?? '')) !== '' ? (string) $user['position_name'] : $roleLabel;
$phoneLabel = trim((string) ($user['phone_number'] ?? '')) !== '' ? (string) $user['phone_number'] : '-';
$usernameLabel = trim((string) ($user['username'] ?? '')) !== '' ? (string) $user['username'] : '-';
$firstName = trim((string) ($user['first_name'] ?? ''));
$lastName = trim((string) ($user['last_name'] ?? ''));
$nameParts = preg_split('/\s+/', $displayName, 2);
$fallbackFirstName = $nameParts[0] ?? $displayName;
$fallbackLastName = $nameParts[1] ?? '';
$firstNameValue = $firstName !== '' ? $firstName : $fallbackFirstName;
$lastNameValue = $lastName !== '' ? $lastName : $fallbackLastName;
$emailLabel = trim((string) ($user['email'] ?? '')) !== '' ? (string) $user['email'] : '-';
$createdAt = !empty($user['created_at']) ? app_format_thai_date((string) $user['created_at']) : '-';
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
$notificationCount = app_get_unread_notification_count($conn, $userId);
$signatureSrc = !empty($user['signature_path']) ? '../uploads/signatures/' . rawurlencode((string) $user['signature_path']) : null;
$canDepartmentReports = !empty($permissions['can_view_department_reports']);
$canApprove = !empty($permissions['can_approve_logs']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>โปรไฟล์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell profile-page-shell">
<?php render_dashboard_sidebar('profile.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main profile-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">โปรไฟล์</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาชื่อ, ตำแหน่ง, แผนก หรือสถานะ">
        </label>

        <a href="notifications.php" class="dash-icon-button relative" aria-label="เปิดการแจ้งเตือน">
            <i class="bi bi-bell text-lg"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="absolute -right-1 -top-1 min-w-[1.15rem] rounded-full bg-rose-500 px-1 text-center text-[0.65rem] font-bold leading-[1.15rem] text-white">
                    <?= $notificationCount > 9 ? '9+' : (int) $notificationCount ?>
                </span>
            <?php endif; ?>
        </a>

        <button type="button" class="dash-profile-button" data-profile-modal-trigger data-user-id="<?= $userId ?>">
            <span class="dash-avatar">
                <?php if ($profileImageSrc): ?>
                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="h-full w-full object-cover">
                <?php else: ?>
                    <?= htmlspecialchars(mb_substr($displayName !== '-' ? $displayName : 'U', 0, 1, 'UTF-8')) ?>
                <?php endif; ?>
            </span>
            <span class="hidden text-left sm:block">
                <span class="block max-w-[8rem] truncate font-semibold text-hospital-ink"><?= htmlspecialchars($displayName) ?></span>
                <span class="block text-xs text-hospital-muted"><?= htmlspecialchars($roleLabel) ?></span>
            </span>
            <i class="bi bi-chevron-down text-xs text-hospital-muted"></i>
        </button>
    </header>

    <div class="profile-dashboard-frame">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-0"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <section class="dash-card profile-hero-card">
            <div class="profile-hero-grid">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar-large">
                        <?php if ($profileImageSrc): ?>
                            <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="รูปโปรไฟล์">
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                    <label class="profile-camera-button" for="profileImageInput" aria-label="เลือกรูปโปรไฟล์">
                        <i class="bi bi-camera"></i>
                    </label>
                </div>

                <div class="profile-hero-main">
                    <p class="profile-eyebrow">โปรไฟล์ของฉัน</p>
                    <div class="profile-name-row">
                        <h2><?= htmlspecialchars($displayName) ?></h2>
                        <span class="profile-online-badge">ออนไลน์</span>
                    </div>
                    <div class="profile-badge-row">
                        <span><i class="bi bi-person-badge"></i><?= htmlspecialchars($roleLabel) ?> (<?= htmlspecialchars($role === 'admin' ? 'System Administrator' : $roleCompactLabel) ?>)</span>
                        <span>รหัสพนักงาน: EMP-<?= str_pad((string) $userId, 4, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="profile-contact-row">
                        <span><i class="bi bi-envelope"></i><?= htmlspecialchars($emailLabel) ?></span>
                        <span><i class="bi bi-telephone"></i><?= htmlspecialchars($phoneLabel) ?></span>
                        <span><i class="bi bi-calendar2-check"></i>เข้าระบบครั้งแรก: <?= htmlspecialchars($createdAt) ?></span>
                    </div>
                    <div class="profile-tab-row">
                        <a href="#profile-personal" class="active"><i class="bi bi-person-lines-fill"></i>ข้อมูลส่วนตัว</a>
                        <a href="#profile-account"><i class="bi bi-gear"></i>การตั้งค่าบัญชี</a>
                        <a href="#profile-security"><i class="bi bi-lock"></i>ความปลอดภัย</a>
                    </div>
                </div>

                <aside class="profile-info-list">
                    <div>
                        <span class="profile-info-icon"><i class="bi bi-building"></i></span>
                        <p>แผนก</p>
                        <strong><?= htmlspecialchars($departmentLabel) ?></strong>
                    </div>
                    <div>
                        <span class="profile-info-icon"><i class="bi bi-person"></i></span>
                        <p>ตำแหน่ง</p>
                        <strong><?= htmlspecialchars($positionLabel) ?></strong>
                    </div>
                    <div>
                        <span class="profile-info-icon"><i class="bi bi-person-check"></i></span>
                        <p>ระดับสิทธิ์</p>
                        <strong><?= htmlspecialchars($roleLabel) ?></strong>
                    </div>
                    <div>
                        <span class="profile-info-icon"><i class="bi bi-person-up"></i></span>
                        <p>หัวหน้างาน</p>
                        <strong>-</strong>
                    </div>
                </aside>
            </div>
        </section>

        <section class="profile-content-grid">
            <section class="dash-card profile-form-card" id="profile-personal">
                <div class="profile-card-head">
                    <div>
                        <h2>ข้อมูลส่วนตัว</h2>
                        <p>แก้ไขข้อมูลสำคัญของบัญชีและไฟล์แนบที่ใช้ในระบบลงเวลา</p>
                    </div>
                </div>

                <form method="post" enctype="multipart/form-data" id="profileForm" data-global-loading-form data-loading-message="กำลังบันทึกข้อมูลโปรไฟล์...">
                    <div class="profile-form-grid">
                        <div class="profile-field">
                            <label>ชื่อ - นามสกุล</label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? $displayName) ?>" required>
                        </div>
                        <div class="profile-field">
                            <label>ชื่อ</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($firstNameValue) ?>" readonly>
                        </div>
                        <div class="profile-field">
                            <label>นามสกุล</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($lastNameValue !== '' ? $lastNameValue : '-') ?>" readonly>
                        </div>
                        <div class="profile-field">
                            <label>อีเมล</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($emailLabel) ?>" readonly>
                        </div>
                        <div class="profile-field">
                            <label>เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($phoneLabel) ?>" readonly>
                        </div>
                        <div class="profile-field">
                            <label>ชื่อผู้ใช้</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($usernameLabel) ?>" readonly>
                        </div>
                        <div class="profile-field">
                            <label>แผนก</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($departmentLabel) ?>" readonly>
                        </div>
                        <div class="profile-field">
                            <label>ตำแหน่ง</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($positionLabel) ?>" readonly>
                        </div>
                        <div class="profile-field">
                            <label>ประเภทพนักงาน</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($roleLabel) ?>" readonly>
                        </div>
                        <div class="profile-field is-full" id="profile-security">
                            <label>รหัสผ่านใหม่</label>
                            <input type="password" name="password" class="form-control" placeholder="เว้นว่างหากไม่ต้องการเปลี่ยนรหัสผ่าน">
                        </div>
                        <div class="profile-field is-full" id="profile-account">
                            <label>รูปโปรไฟล์</label>
                            <div class="profile-upload-row">
                                <input type="file" id="profileImageInput" name="profile_image" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp">
                                <div class="profile-thumb">
                                    <?php if ($profileImageSrc): ?>
                                        <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="Current Profile Image">
                                    <?php else: ?>
                                        <i class="bi bi-person-fill"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small>รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 2MB</small>
                        </div>
                    </div>

                    <div class="profile-signature-section">
                        <div class="profile-card-head">
                            <div>
                                <h3>ลายเซ็น</h3>
                                <p>วาดลายเซ็นใหม่ หรืออัปโหลดรูปภาพลายเซ็นเพื่อใช้กับรายงานและการอนุมัติ</p>
                            </div>
                        </div>
                        <div class="profile-signature-grid">
                            <div class="profile-signature-pad-wrap">
                                <canvas id="signature-pad"></canvas>
                                <div class="profile-signature-actions">
                                    <span>ใช้เมาส์หรือนิ้ววาดในกรอบนี้</span>
                                    <button type="button" class="dash-btn dash-btn-ghost" id="clear-signature">ล้างลายเซ็น</button>
                                </div>
                                <input type="hidden" name="signature_base64" id="signature_base64">
                            </div>
                            <div class="profile-signature-preview">
                                <?php if ($signatureSrc): ?>
                                    <img src="<?= htmlspecialchars($signatureSrc) ?>" alt="Current Signature">
                                <?php else: ?>
                                    <span>ยังไม่มีลายเซ็นในระบบ</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-field mt-3">
                            <label>หรืออัปโหลดรูปภาพลายเซ็น</label>
                            <input type="file" name="signature" class="form-control" accept="image/png, image/jpeg, image/jpg">
                        </div>
                    </div>

                    <div class="profile-form-actions">
                        <button type="submit" class="dash-btn dash-btn-primary">บันทึกข้อมูล</button>
                        <a href="dashboard.php" class="dash-btn dash-btn-ghost">ยกเลิก</a>
                    </div>
                </form>
            </section>

            <aside class="profile-side-column">
                <section class="dash-card profile-activity-card">
                    <div class="profile-card-head">
                        <div>
                            <h2>กิจกรรมล่าสุด</h2>
                            <p>ประวัติกิจกรรมของบัญชีนี้</p>
                        </div>
                    </div>
                    <div class="profile-empty-activity">
                        <span class="profile-empty-icon"><i class="bi bi-activity"></i></span>
                        <strong>ยังไม่มีบันทึกกิจกรรมล่าสุด</strong>
                        <span>ระบบจะแสดงกิจกรรมเมื่อมี event log ที่เชื่อมต่อกับบัญชีนี้</span>
                    </div>
                </section>

                <section class="dash-card profile-shortcut-card">
                    <div class="profile-card-head">
                        <div>
                            <h2>ทางลัด</h2>
                            <p>เปิดหน้าที่ใช้งานบ่อยได้ทันที</p>
                        </div>
                    </div>
                    <div class="profile-shortcut-grid">
                        <a href="time.php" class="profile-shortcut is-green"><span><i class="bi bi-clock"></i></span><strong>ไปยังหน้าลงเวลา</strong></a>
                        <a href="daily_schedule.php" class="profile-shortcut is-blue"><span><i class="bi bi-calendar2-week"></i></span><strong>เปิดตารางเวรวันนี้</strong></a>
                        <a href="my_reports.php" class="profile-shortcut is-violet"><span><i class="bi bi-bar-chart-line"></i></span><strong>ดูรายงานของฉัน</strong></a>
                        <?php if ($canDepartmentReports): ?>
                            <a href="department_reports.php" class="profile-shortcut is-amber"><span><i class="bi bi-people"></i></span><strong>ดูรายงานแผนก</strong></a>
                        <?php endif; ?>
                        <a href="profile.php" class="profile-shortcut is-muted"><span><i class="bi bi-gear"></i></span><strong>ตั้งค่าบัญชี</strong></a>
                        <?php if ($canApprove): ?>
                            <a href="approval_queue.php" class="profile-shortcut is-blue"><span><i class="bi bi-patch-check"></i></span><strong>คิวตรวจสอบ</strong></a>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('signature-pad');
    const clearButton = document.getElementById('clear-signature');
    const base64Input = document.getElementById('signature_base64');
    const form = document.getElementById('profileForm');

    if (canvas && clearButton && base64Input && form) {
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasSignature = false;

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#10243b';
        }

        function pointerPosition(event) {
            const rect = canvas.getBoundingClientRect();
            if (event.touches && event.touches[0]) {
                return {
                    x: event.touches[0].clientX - rect.left,
                    y: event.touches[0].clientY - rect.top
                };
            }

            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top
            };
        }

        function startPosition(event) {
            isDrawing = true;
            hasSignature = true;
            const pos = pointerPosition(event);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            event.preventDefault();
        }

        function endPosition() {
            isDrawing = false;
            ctx.beginPath();
        }

        function draw(event) {
            if (!isDrawing) return;
            const pos = pointerPosition(event);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            event.preventDefault();
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        canvas.addEventListener('mousedown', startPosition);
        canvas.addEventListener('mouseup', endPosition);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseleave', endPosition);
        canvas.addEventListener('touchstart', startPosition, { passive: false });
        canvas.addEventListener('touchend', endPosition);
        canvas.addEventListener('touchmove', draw, { passive: false });

        clearButton.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSignature = false;
            base64Input.value = '';
        });

        form.addEventListener('submit', function () {
            if (hasSignature) {
                base64Input.value = canvas.toDataURL('image/png');
            }
        });
    }

    const openButton = document.querySelector('[data-dashboard-sidebar-open]');
    const closeButton = document.querySelector('[data-dashboard-sidebar-close]');
    const drawer = document.querySelector('[data-dashboard-sidebar-drawer]');
    const backdrop = document.querySelector('[data-dashboard-sidebar-backdrop]');

    if (!openButton || !closeButton || !drawer || !backdrop) {
        return;
    }

    const setOpen = function (isOpen) {
        drawer.classList.toggle('is-open', isOpen);
        backdrop.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen);
    };

    openButton.addEventListener('click', function () { setOpen(true); });
    closeButton.addEventListener('click', function () { setOpen(false); });
    backdrop.addEventListener('click', function () { setOpen(false); });
});
</script>
</body>
</html>
