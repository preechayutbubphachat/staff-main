<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';

app_require_login();

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
$permissions = $_SESSION['permissions'] ?? [];
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($user['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($user);
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
    <style>
        :root {
            --ink: #10243b;
            --muted: #6b7a8d;
            --line: rgba(16, 36, 59, 0.1);
            --surface: rgba(255, 255, 255, 0.9);
        }

        body {
            background:
                radial-gradient(circle at top right, rgba(28, 107, 99, 0.08), transparent 24%),
                linear-gradient(180deg, #f8fbfd, #eef4f8);
            font-family: 'Sarabun', sans-serif;
            color: var(--ink);
        }

        .hero,
        .panel,
        .mini-card {
            background: var(--surface);
            border: 1px solid rgba(16, 36, 59, .08);
            border-radius: 28px;
            box-shadow: 0 18px 44px rgba(16, 36, 59, .08);
        }

        .hero,
        .panel {
            padding: 28px;
        }

        .hero h1,
        .section-title,
        .profile-name {
            font-family: 'Prompt', sans-serif;
        }

        .profile-block {
            display: grid;
            grid-template-columns: 132px 1fr;
            gap: 20px;
            align-items: center;
        }

        .avatar-frame {
            width: 132px;
            height: 132px;
            border-radius: 36px;
            overflow: hidden;
            position: relative;
            background:
                linear-gradient(135deg, rgba(16, 36, 59, 0.96), rgba(28, 107, 99, 0.88)),
                url('../LOGO/nongphok_logo.png') center/84px no-repeat;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.12);
        }

        .avatar-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .avatar-fallback {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            color: rgba(255,255,255,.96);
            background:
                linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,0)),
                linear-gradient(135deg, rgba(16, 36, 59, 0.96), rgba(28, 107, 99, 0.88));
        }

        .avatar-fallback .person {
            font-size: 3.2rem;
            line-height: 1;
            transform: translateY(-4px);
        }

        .avatar-fallback .hospital-mark {
            position: absolute;
            inset: auto 14px 14px auto;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(255,255,255,.12) url('../LOGO/nongphok_logo.png') center/26px no-repeat;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.14);
        }

        .profile-name {
            font-size: clamp(1.6rem, 2.4vw, 2.2rem);
            margin-bottom: 8px;
        }

        .hero-copy {
            max-width: 680px;
            color: var(--muted);
            line-height: 1.75;
            margin-bottom: 0;
        }

        .meta-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .meta-pills span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(16, 36, 59, 0.05);
            border: 1px solid rgba(16, 36, 59, 0.08);
        }

        .mini-card {
            padding: 18px 20px;
            height: 100%;
        }

        .mini-card .label {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .mini-card .value {
            margin-top: 8px;
            font-family: 'Prompt', sans-serif;
            font-size: 1.45rem;
        }

        .form-control {
            border-radius: 16px;
            padding: 13px 14px;
            border-color: rgba(16, 36, 59, .12);
        }

        .soft-board {
            border-radius: 22px;
            border: 1px solid rgba(16, 36, 59, .08);
            background: #fbfdff;
            padding: 16px;
        }

        #signature-pad {
            width: 100%;
            height: 180px;
            border-radius: 18px;
            border: 1px dashed rgba(16, 36, 59, .2);
            background: linear-gradient(180deg, #ffffff, #f9fbfd);
            touch-action: none;
        }

        .preview-frame {
            min-height: 180px;
            border-radius: 22px;
            border: 1px dashed rgba(16, 36, 59, .14);
            background: #fbfdff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .preview-frame img {
            max-width: 100%;
            max-height: 180px;
            object-fit: contain;
            display: block;
        }

        .action-stack {
            display: grid;
            gap: 12px;
        }

        .action-stack .btn {
            justify-content: flex-start;
            text-align: left;
            padding: 14px 18px;
            border-radius: 18px;
        }

        @media (max-width: 767.98px) {
            .hero,
            .panel,
            .mini-card {
                border-radius: 22px;
            }

            .hero,
            .panel {
                padding: 22px;
            }

            .profile-block {
                grid-template-columns: 1fr;
            }

            .avatar-frame {
                width: 112px;
                height: 112px;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('profile.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="hero mb-4">
        <div class="profile-block">
            <div class="avatar-frame">
                <?php if ($profileImageSrc): ?>
                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="รูปโปรไฟล์">
                <?php else: ?>
                    <div class="avatar-fallback" aria-hidden="true">
                        <i class="bi bi-person-fill person"></i>
                        <span class="hospital-mark"></span>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="text-uppercase small text-muted fw-semibold mb-2">เกี่ยวกับฉัน</div>
                <div class="profile-name"><?= htmlspecialchars($displayName) ?></div>
                <p class="hero-copy">จัดการชื่อ รหัสผ่าน รูปโปรไฟล์ และลายเซ็นที่ใช้กับรายงานหรือการอนุมัติได้จากหน้าเดียว โดยถ้ายังไม่ได้อัปโหลดรูป ระบบจะแสดงภาพเงาบุคคลพร้อมโลโก้โรงพยาบาลเป็นค่าเริ่มต้น</p>
                <div class="meta-pills">
                    <span><i class="bi bi-person-badge"></i><?= htmlspecialchars($roleLabel) ?></span>
                    <span><i class="bi bi-building"></i><?= htmlspecialchars($user['department_name'] ?? '-') ?></span>
                    <span><i class="bi bi-at"></i><?= htmlspecialchars($user['username'] ?? '-') ?></span>
                </div>
            </div>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="row g-4 mb-4">
        <div class="col-md-4"><div class="mini-card"><div class="label">บทบาทในระบบ</div><div class="value"><?= htmlspecialchars($roleLabel) ?></div></div></div>
        <div class="col-md-4"><div class="mini-card"><div class="label">ดูรายงานแผนก</div><div class="value"><?= !empty($permissions['can_view_department_reports']) ? 'ได้' : 'ไม่ได้' ?></div></div></div>
        <div class="col-md-4"><div class="mini-card"><div class="label">สิทธิ์อนุมัติ</div><div class="value"><?= !empty($permissions['can_approve_logs']) ? 'มี' : 'ไม่มี' ?></div></div></div>
    </section>

    <section class="row g-4">
        <div class="col-xl-7">
            <div class="panel h-100">
                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
                    <div>
                        <h2 class="section-title h4 mb-1">ข้อมูลส่วนตัว</h2>
                        <div class="text-muted small">แก้ไขเฉพาะข้อมูลของบัญชีนี้</div>
                    </div>
                </div>

                <form method="post" enctype="multipart/form-data" id="profileForm" class="row g-4" data-global-loading-form data-loading-message="กำลังบันทึกข้อมูลโปรไฟล์...">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">ชื่อ - นามสกุล</label>
                        <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">รหัสผ่านใหม่</label>
                        <input type="password" name="password" class="form-control" placeholder="เว้นว่างหากไม่ต้องการเปลี่ยน">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">อัปโหลดรูปโปรไฟล์</label>
                        <input type="file" name="profile_image" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp">
                        <div class="small text-muted mt-2">รองรับไฟล์ `PNG`, `JPG`, `JPEG`, `WEBP`</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">ตัวอย่างรูปโปรไฟล์ปัจจุบัน</label>
                        <div class="preview-frame">
                            <?php if ($profileImageSrc): ?>
                                <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="Current Profile Image">
                            <?php else: ?>
                                <div class="avatar-frame" style="width: 120px; height: 120px;">
                                    <div class="avatar-fallback" aria-hidden="true">
                                        <i class="bi bi-person-fill person"></i>
                                        <span class="hospital-mark"></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">วาดลายเซ็น</label>
                        <div class="soft-board">
                            <canvas id="signature-pad"></canvas>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                                <span class="text-muted small">ใช้เมาส์หรือนิ้ววาดในกรอบนี้</span>
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" id="clear-signature">ล้างลายเซ็น</button>
                            </div>
                            <input type="hidden" name="signature_base64" id="signature_base64">
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">หรืออัปโหลดรูปภาพลายเซ็น</label>
                        <input type="file" name="signature" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-3">
                        <a href="dashboard.php" class="btn btn-outline-dark rounded-pill px-4">กลับไปแดชบอร์ด</a>
                        <button type="submit" class="btn btn-dark rounded-pill px-4">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="panel h-100">
                <h2 class="section-title h4 mb-3">ลายเซ็นปัจจุบันและทางลัด</h2>
                <div class="preview-frame mb-4">
                    <?php if (!empty($user['signature_path'])): ?>
                        <img src="../uploads/signatures/<?= htmlspecialchars($user['signature_path']) ?>" alt="Current Signature">
                    <?php else: ?>
                        <div class="text-muted">ยังไม่มีลายเซ็นในระบบ</div>
                    <?php endif; ?>
                </div>

                <div class="action-stack">
                    <a href="time.php" class="btn btn-outline-dark"><i class="bi bi-clock-history me-2"></i>ไปยังหน้าลงเวลา</a>
                    <a href="daily_schedule.php" class="btn btn-outline-dark"><i class="bi bi-calendar-week me-2"></i>เปิดตารางเวรวันนี้</a>
                    <a href="my_reports.php" class="btn btn-outline-dark"><i class="bi bi-bar-chart-line me-2"></i>เปิดรายงานของฉัน</a>
                    <?php if (app_can('can_view_department_reports')): ?>
                        <a href="department_reports.php" class="btn btn-outline-dark"><i class="bi bi-building me-2"></i>เปิดรายงานแผนก</a>
                    <?php endif; ?>
                    <?php if (app_can('can_approve_logs')): ?>
                        <a href="approval_queue.php" class="btn btn-outline-dark"><i class="bi bi-patch-check me-2"></i>เปิดคิวตรวจสอบ</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('signature-pad');
        const ctx = canvas.getContext('2d');
        const clearButton = document.getElementById('clear-signature');
        const base64Input = document.getElementById('signature_base64');
        const form = document.getElementById('profileForm');
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

        function startPosition(e) {
            isDrawing = true;
            hasSignature = true;
            draw(e);
        }

        function endPosition() {
            isDrawing = false;
            ctx.beginPath();
        }

        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();

            let clientX;
            let clientY;
            if (e.type.includes('touch')) {
                const rect = canvas.getBoundingClientRect();
                clientX = e.touches[0].clientX - rect.left;
                clientY = e.touches[0].clientY - rect.top;
            } else {
                clientX = e.offsetX;
                clientY = e.offsetY;
            }

            ctx.lineTo(clientX, clientY);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(clientX, clientY);
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
    });
</script>
</body>
</html>
