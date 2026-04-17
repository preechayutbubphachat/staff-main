<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';

app_require_login();

$userId = (int) $_SESSION['id'];
$role = app_current_role();
$roleLabel = app_role_label($role);

$stmt = $conn->prepare('SELECT u.*, d.department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($user['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($user);
$todayLabel = app_format_thai_date(date('Y-m-d'), true);

$monthStmt = $conn->prepare("
    SELECT COALESCE(SUM(work_hours), 0)
    FROM time_logs
    WHERE user_id = ? AND YEAR(work_date) = YEAR(CURDATE()) AND MONTH(work_date) = MONTH(CURDATE())
");
$monthStmt->execute([$userId]);
$monthHours = (float) $monthStmt->fetchColumn();

$yearStmt = $conn->prepare("
    SELECT COALESCE(SUM(work_hours), 0)
    FROM time_logs
    WHERE user_id = ? AND YEAR(work_date) = YEAR(CURDATE())
");
$yearStmt->execute([$userId]);
$yearHours = (float) $yearStmt->fetchColumn();

$latestLogStmt = $conn->prepare("
    SELECT work_date, time_in, time_out, work_hours, checked_at
    FROM time_logs
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$latestLogStmt->execute([$userId]);
$latestLog = $latestLogStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$latestLogDateLabel = $latestLog ? app_format_thai_date((string) $latestLog['work_date'], true) : '';

$pendingCount = 0;
if (app_can('can_approve_logs')) {
    $pendingCount = (int) $conn->query("SELECT COUNT(*) FROM time_logs WHERE " . app_time_log_pending_condition(''))->fetchColumn();
}

$todayScheduleStmt = $conn->prepare("SELECT COUNT(*) FROM time_logs WHERE work_date = CURDATE()");
$todayScheduleStmt->execute();
$todayScheduleCount = (int) $todayScheduleStmt->fetchColumn();

$todayIncompleteStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM time_logs
    WHERE user_id = ? AND work_date = CURDATE() AND (time_in IS NULL OR time_out IS NULL)
");
$todayIncompleteStmt->execute([$userId]);
$todayIncompleteCount = (int) $todayIncompleteStmt->fetchColumn();

$todayOverlapStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM time_logs a
    INNER JOIN time_logs b
        ON a.user_id = b.user_id
       AND a.id < b.id
       AND a.work_date = b.work_date
       AND a.time_in IS NOT NULL
       AND a.time_out IS NOT NULL
       AND b.time_in IS NOT NULL
       AND b.time_out IS NOT NULL
       AND a.time_out > b.time_in
       AND a.time_in < b.time_out
    WHERE a.user_id = ? AND a.work_date = CURDATE()
");
$todayOverlapStmt->execute([$userId]);
$todayOverlapCount = (int) $todayOverlapStmt->fetchColumn();
$todayIssueCount = $todayIncompleteCount + $todayOverlapCount;

$quickActions = [
    [
        'href' => 'time.php',
        'icon' => 'bi-clock-history',
        'eyebrow' => 'งานประจำที่ใช้บ่อย',
        'title' => 'ลงเวลาเวร',
        'description' => 'บันทึกเวลาเข้าออกและกลับไปดูรายการของตัวเองได้ทันที',
        'button_label' => 'ไปหน้าลงเวลาเวร',
        'tone' => 'primary',
    ],
    [
        'href' => 'daily_schedule.php',
        'icon' => 'bi-calendar-week',
        'eyebrow' => 'ประสานงานประจำวัน',
        'title' => 'เวรวันนี้',
        'description' => 'ดูทีมที่กำลังปฏิบัติงานในวันนี้ได้จากตารางเวรแบบรวดเร็ว',
        'button_label' => 'เปิดตารางเวรวันนี้',
        'tone' => 'teal',
    ],
    [
        'href' => 'my_reports.php',
        'icon' => 'bi-bar-chart-line',
        'eyebrow' => 'รายงานส่วนตัว',
        'title' => 'รายงานของฉัน',
        'description' => 'ดูสรุปและพิมพ์รายงานส่วนตัวจากหน้าเดียว',
        'button_label' => 'เปิดรายงานของฉัน',
        'tone' => 'light',
    ],
    [
        'href' => 'profile.php',
        'icon' => 'bi-person-circle',
        'eyebrow' => 'ข้อมูลบัญชี',
        'title' => 'โปรไฟล์และลายเซ็น',
        'description' => 'อัปเดตรูปประจำตัว ลายเซ็น และข้อมูลส่วนตัวให้พร้อมใช้งาน',
        'button_label' => 'จัดการโปรไฟล์และรูปภาพ',
        'tone' => 'light',
    ],
];

if (app_can('can_approve_logs')) {
    array_splice($quickActions, 1, 0, [[
        'href' => 'approval_queue.php',
        'icon' => 'bi-patch-check',
        'eyebrow' => 'งานตรวจสอบ',
        'title' => 'ตรวจสอบเวร',
        'description' => 'เปิดคิวรายการที่รอตรวจและยืนยันงานที่อยู่ในความรับผิดชอบ',
        'button_label' => 'เปิดหน้าตรวจสอบเวร',
        'tone' => 'warning',
    ]]);
}

if (app_can('can_view_department_reports')) {
    $quickActions[] = [
        'href' => 'department_reports.php',
        'icon' => 'bi-building',
        'eyebrow' => 'ภาพรวมหน่วยงาน',
        'title' => 'รายงานแผนก',
        'description' => 'ดูภาพรวมเวรและชั่วโมงของเจ้าหน้าที่ตามสิทธิ์ที่เข้าถึงได้',
        'button_label' => 'เปิดรายงานแผนก',
        'tone' => 'light',
    ];
}

$adminShortcut = null;
if (app_can('can_manage_database')) {
    $adminShortcut = [
        'href' => 'db_admin_dashboard.php',
        'icon' => 'bi-database-gear',
        'eyebrow' => 'พื้นที่ผู้ดูแล',
        'title' => 'เปิดหน้าหลังบ้าน',
        'description' => 'เข้าสู่เครื่องมือดูแลข้อมูลและงานหลังบ้านของระบบ',
        'button_label' => 'เปิดหน้าหลังบ้าน',
    ];
} elseif (app_can('can_manage_user_permissions')) {
    $adminShortcut = [
        'href' => 'manage_users.php',
        'icon' => 'bi-shield-lock',
        'eyebrow' => 'พื้นที่ผู้ดูแล',
        'title' => 'จัดการผู้ใช้งาน',
        'description' => 'ดูแลบัญชีผู้ใช้และสิทธิ์การเข้าถึงจากหน้าจัดการหลัก',
        'button_label' => 'เปิดหน้าผู้ดูแลผู้ใช้งาน',
    ];
} elseif (app_can('can_manage_time_logs')) {
    $adminShortcut = [
        'href' => 'manage_time_logs.php',
        'icon' => 'bi-pencil-square',
        'eyebrow' => 'งานปฏิบัติการ',
        'title' => 'จัดการรายการลงเวลาเวร',
        'description' => 'ตรวจสอบและแก้ไขรายการลงเวลาเวรในภาพรวม',
        'button_label' => 'เปิดหน้าจัดการลงเวลาเวร',
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แดชบอร์ด | ระบบลงเวลาเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/app-ui.css">
    <style>
        .dashboard-shell { padding: 24px 0 44px; }
        .dashboard-hero {
            position: relative;
            padding: 32px;
            border-radius: 32px;
            overflow: hidden;
            min-height: 280px;
            background:
                radial-gradient(circle at top right, rgba(110, 223, 209, 0.34), transparent 24%),
                radial-gradient(circle at bottom left, rgba(184, 229, 255, 0.46), transparent 30%),
                linear-gradient(180deg, #fcfeff, #eef8fa);
            border: 1px solid rgba(16, 36, 59, 0.08);
            box-shadow: 0 20px 44px rgba(16, 36, 59, 0.06);
        }
        .dashboard-hero-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr); gap: 22px; align-items: stretch; }
        .dashboard-hero h1, .section-title, .metric-card .value, .profile-title, .hero-side-title, .hero-side-value, .quick-action-title, .workspace-card--action h3 { font-family: 'Prompt', sans-serif; }
        .hero-badge { width: fit-content; display: inline-flex; align-items: center; gap: 8px; background: rgba(28, 107, 99, 0.08); color: #0f5f59; border: 1px solid rgba(28, 107, 99, 0.12); border-radius: 999px; padding: 9px 14px; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.03em; }
        .hero-copy h1 { font-size: clamp(2rem, 3vw, 3rem); line-height: 1.08; margin: 16px 0 10px; max-width: 640px; color: #10243b; }
        .hero-copy p { color: #5f7287; line-height: 1.75; max-width: 560px; margin: 0; font-size: 0.98rem; }
        .hero-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .hero-meta span { display: inline-flex; align-items: center; gap: 8px; padding: 9px 13px; border-radius: 999px; background: rgba(255,255,255,0.78); color: #35516a; font-size: 0.9rem; border: 1px solid rgba(16,36,59,0.08); }
        .hero-side {
            padding: 22px;
            border-radius: 24px;
            background: rgba(255,255,255,0.88);
            border: 1px solid rgba(16,36,59,0.08);
            box-shadow: 0 16px 34px rgba(16,36,59,0.06);
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .hero-side-title { font-size: 1.02rem; margin: 0; color: #10243b; }
        .hero-side-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(16,36,59,0.08); }
        .hero-side-row:first-of-type { padding-top: 4px; }
        .hero-side-row:last-child { padding-bottom: 0; border-bottom: 0; }
        .hero-side-label { color: #677a8e; font-size: 0.92rem; }
        .hero-side-value { font-size: 1.6rem; font-weight: 700; color: #10243b; }
        .metric-card, .workspace-card, .profile-card {
            background: rgba(255,255,255,0.94);
            border-radius: 24px;
            border: 1px solid rgba(16,36,59,.07);
            padding: 22px;
            height: 100%;
            box-shadow: 0 16px 30px rgba(16,36,59,.05);
        }
        .metric-card { position: relative; overflow: hidden; }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 22px;
            right: 22px;
            height: 3px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(28,107,99,0.84), rgba(103,173,255,0.84));
        }
        .metric-card .label { color: #6b7a8d; font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .metric-card .value { font-size: 1.9rem; margin-top: 12px; color: #10243b; }
        .metric-card .text-muted { margin-top: 8px; line-height: 1.65; }
        .profile-card { display: grid; gap: 18px; }
        .profile-top { display: grid; grid-template-columns: 104px 1fr; gap: 16px; align-items: center; }
        .avatar-frame { width: 104px; height: 104px; border-radius: 28px; overflow: hidden; position: relative; background: linear-gradient(145deg, rgba(16, 36, 59, 0.92), rgba(69, 165, 151, 0.78)), url('../LOGO/nongphok_logo.png') center/68px no-repeat; }
        .avatar-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .avatar-fallback { position: absolute; inset: 0; display: grid; place-items: center; color: rgba(255,255,255,.96); background: linear-gradient(145deg, rgba(16, 36, 59, 0.92), rgba(69, 165, 151, 0.78)); }
        .avatar-fallback .person { font-size: 2.5rem; line-height: 1; transform: translateY(-3px); }
        .avatar-fallback .hospital-mark { position: absolute; inset: auto 10px 10px auto; width: 34px; height: 34px; border-radius: 11px; background: rgba(255,255,255,.14) url('../LOGO/nongphok_logo.png') center/22px no-repeat; border: 1px solid rgba(255,255,255,.16); }
        .profile-title { font-size: 1.5rem; margin-bottom: 4px; color: #10243b; }
        .profile-subtitle, .workspace-card p { color: #6b7a8d; margin-bottom: 0; line-height: 1.68; }
        .role-pill { display: inline-flex; align-items: center; gap: 8px; margin-top: 10px; padding: 8px 12px; border-radius: 999px; background: rgba(103, 173, 255, 0.08); border: 1px solid rgba(103, 173, 255, 0.18); font-weight: 700; color: #23476f; }
        .signature-box { padding: 14px 16px; border-radius: 18px; border: 1px dashed rgba(16,36,59,.12); background: #fbfdff; }
        .signature-box img { max-height: 82px; object-fit: contain; display: block; }
        .workspace-card { display: grid; gap: 10px; align-content: start; }
        .section-kicker { display: inline-flex; align-items: center; gap: 8px; color: #4e6983; font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px; }
        .section-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .section-header p { color: #6b7a8d; margin: 6px 0 0; max-width: 640px; line-height: 1.7; font-size: 0.96rem; }
        .section-header .section-title { font-size: 1.6rem; margin: 0; color: #10243b; }
        .quick-action-card {
            background: rgba(255,255,255,0.96);
            border-radius: 24px;
            border: 1px solid rgba(16, 36, 59, 0.08);
            padding: 22px;
            height: 100%;
            display: grid;
            gap: 14px;
            box-shadow: 0 16px 34px rgba(16, 36, 59, 0.05);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .quick-action-card:hover { transform: translateY(-2px); box-shadow: 0 22px 38px rgba(16, 36, 59, 0.08); border-color: rgba(28, 107, 99, 0.18); }
        .quick-action-card--primary { background: linear-gradient(180deg, rgba(237,248,255,0.96), rgba(255,255,255,0.98)); border-color: rgba(44,115,186,0.12); }
        .quick-action-card--teal { background: linear-gradient(180deg, rgba(235,251,248,0.96), rgba(255,255,255,0.98)); border-color: rgba(28,107,99,0.14); }
        .quick-action-card--warning { background: linear-gradient(180deg, rgba(255,250,236,0.96), rgba(255,255,255,0.98)); border-color: rgba(211,154,40,0.16); }
        .quick-action-card--light { background: rgba(255,255,255,0.96); }
        .quick-action-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .quick-action-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 1.2rem;
            background: rgba(16, 36, 59, 0.06);
            color: #10243b;
            flex: 0 0 auto;
        }
        .quick-action-card--primary .quick-action-icon { background: rgba(44,115,186,0.10); color: #1f538d; }
        .quick-action-card--teal .quick-action-icon { background: rgba(28,107,99,0.10); color: #0f5f59; }
        .quick-action-card--warning .quick-action-icon { background: rgba(211,154,40,0.12); color: #9c6d12; }
        .quick-action-eyebrow { color: #6c7f92; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
        .quick-action-title { font-size: 1.24rem; margin: 0; color: #10243b; }
        .quick-action-copy { color: #637487; line-height: 1.65; margin: 0; font-size: 0.95rem; }
        .dashboard-action-button, .workspace-action-button {
            width: fit-content;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 11px 18px;
            border-radius: 999px;
            border: 1px solid rgba(16, 36, 59, 0.10);
            font-weight: 700;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease;
        }
        .dashboard-action-button:hover, .workspace-action-button:hover { transform: translateY(-1px); box-shadow: 0 14px 24px rgba(16, 36, 59, 0.08); }
        .dashboard-action-button { background: #10243b; color: #fff; }
        .workspace-card--action { gap: 16px; }
        .workspace-card--action .workspace-header { display: grid; gap: 10px; }
        .workspace-card--action .workspace-header-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .workspace-card--action .workspace-icon { width: 44px; height: 44px; border-radius: 16px; display: grid; place-items: center; background: rgba(16,36,59,.06); color: #10243b; font-size: 1.1rem; }
        .workspace-card--action .workspace-body { display: grid; gap: 8px; }
        .workspace-card--action .workspace-body h3 { font-size: 1.1rem; margin: 0; }
        .workspace-card--action .workspace-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: auto; }
        .workspace-card--action .workspace-hint { color: #7a8998; font-size: 0.88rem; font-weight: 600; }
        .workspace-action-button { background: #10243b; color: #fff; }
        .workspace-action-button.btn-outline-style { background: transparent; color: #10243b; border-color: rgba(16, 36, 59, 0.12); }
        .issue-callout { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding: 16px 18px; border-radius: 22px; background: linear-gradient(180deg, rgba(255,249,234,0.96), rgba(255,254,249,0.98)); border: 1px solid rgba(211,154,40,0.18); box-shadow: 0 12px 26px rgba(211,154,40,0.06); }
        .issue-copy { display: grid; gap: 4px; }
        .status-row { display: grid; gap: 10px; }
        .status-pill { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 13px 15px; border-radius: 16px; background: #fbfdff; border: 1px solid rgba(16, 36, 59, 0.07); }
        @media (max-width: 991px) { .dashboard-hero-grid { grid-template-columns: 1fr; } .dashboard-hero { min-height: auto; } }
        @media (max-width: 768px) {
            .dashboard-shell { padding: 20px 0 36px; }
            .dashboard-hero, .metric-card, .workspace-card, .profile-card, .quick-action-card { border-radius: 22px; }
            .dashboard-hero { padding: 26px; }
            .profile-top { grid-template-columns: 1fr; }
            .section-header { flex-direction: column; align-items: flex-start; }
            .quick-action-card, .workspace-card--action { padding: 20px; }
        }
    </style>
</head>
<body class="app-ui">
<?php render_app_navigation('dashboard.php'); ?>

<main class="dashboard-shell">
    <div class="container">
        <section class="dashboard-hero mb-4">
            <div class="dashboard-hero-grid">
                <div class="hero-copy">
                    <span class="hero-badge"><i class="bi bi-stars"></i>ศูนย์กลางการทำงานประจำวัน</span>
                    <h1>สวัสดี <?= htmlspecialchars($displayName) ?> พร้อมเริ่มงานวันนี้</h1>
                    <p>ดูภาพรวมสั้น ๆ แล้วกดไปยังหน้าที่ต้องใช้ต่อได้ทันทีจากหน้าเดียว</p>
                    <div class="hero-meta">
                        <span><i class="bi bi-building"></i><?= htmlspecialchars($user['department_name'] ?? 'ยังไม่ได้ระบุแผนก') ?></span>
                        <span><i class="bi bi-person-badge"></i><?= htmlspecialchars($roleLabel) ?></span>
                        <span><i class="bi bi-calendar3"></i><?= htmlspecialchars($todayLabel) ?></span>
                    </div>
                </div>
                <aside class="hero-side">
                    <h2 class="hero-side-title">ภาพรวมวันนี้</h2>
                    <div class="hero-side-row"><div class="hero-side-label">เวรทั้งหมดวันนี้</div><div class="hero-side-value"><?= number_format($todayScheduleCount) ?></div></div>
                    <div class="hero-side-row"><div class="hero-side-label">ชั่วโมงเดือนนี้</div><div class="hero-side-value"><?= number_format($monthHours, 2) ?></div></div>
                    <div class="hero-side-row"><div class="hero-side-label">รายการที่ควรตรวจสอบ</div><div class="hero-side-value"><?= number_format($todayIssueCount) ?></div></div>
                </aside>
            </div>
        </section>

        <section class="row g-4 mb-4">
            <div class="col-md-4"><div class="metric-card"><div class="label">ชั่วโมงเดือนนี้</div><div class="value"><?= number_format($monthHours, 2) ?></div><div class="text-muted">เวลาสะสมของเดือนนี้</div></div></div>
            <div class="col-md-4"><div class="metric-card"><div class="label">ชั่วโมงปีนี้</div><div class="value"><?= number_format($yearHours, 2) ?></div><div class="text-muted">เวลาสะสมของปีนี้</div></div></div>
            <div class="col-md-4"><div class="metric-card"><div class="label">รายการเวรวันนี้</div><div class="value"><?= number_format($todayScheduleCount) ?></div><div class="text-muted">รายการเวรทั้งหมดของวันนี้</div></div></div>
        </section>

        <section class="mb-4">
            <div class="section-header">
                <div>
                    <span class="section-kicker"><i class="bi bi-lightning-charge"></i>ทางลัดการใช้งาน</span>
                    <h2 class="section-title">ทางลัดที่ใช้บ่อยที่สุด</h2>
                    <p>กดเปิดงานต่อได้ทันทีจากปุ่มลัดที่ชัดเจนและสแกนง่าย</p>
                </div>
            </div>
            <div class="row g-4">
                <?php foreach ($quickActions as $action): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="quick-action-card quick-action-card--<?= htmlspecialchars($action['tone']) ?>">
                            <div class="quick-action-head">
                                <div>
                                    <div class="quick-action-eyebrow"><?= htmlspecialchars($action['eyebrow']) ?></div>
                                    <h3 class="quick-action-title"><?= htmlspecialchars($action['title']) ?></h3>
                                </div>
                                <div class="quick-action-icon"><i class="bi <?= htmlspecialchars($action['icon']) ?>"></i></div>
                            </div>
                            <p class="quick-action-copy"><?= htmlspecialchars($action['description']) ?></p>
                            <a href="<?= htmlspecialchars($action['href']) ?>" class="dashboard-action-button"><span><?= htmlspecialchars($action['button_label']) ?></span><i class="bi bi-arrow-right"></i></a>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($todayIssueCount > 0): ?>
            <section class="mb-4">
                <div class="issue-callout">
                    <div class="issue-copy">
                        <div class="fw-semibold">วันนี้มี <?= number_format($todayIssueCount) ?> รายการที่ควรตรวจสอบ</div>
                        <div class="text-muted"><?php if ($todayOverlapCount > 0): ?>ช่วงเวลาชนกัน <?= number_format($todayOverlapCount) ?> รายการ<?php endif; ?><?php if ($todayIncompleteCount > 0): ?><?= $todayOverlapCount > 0 ? ' และ ' : '' ?>ข้อมูลเวลาไม่ครบ <?= number_format($todayIncompleteCount) ?> รายการ<?php endif; ?></div>
                    </div>
                    <a class="btn btn-outline-dark rounded-pill px-4" href="time.php"><i class="bi bi-clock-history me-1"></i>เปิดหน้าลงเวลาเวร</a>
                </div>
            </section>
        <?php endif; ?>
        <section class="row g-4 mb-4 align-items-stretch">
            <div class="col-xl-4">
                <div class="profile-card">
                    <div class="profile-top">
                        <div class="avatar-frame">
                            <?php if ($profileImageSrc): ?>
                                <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="รูปโปรไฟล์">
                            <?php else: ?>
                                <div class="avatar-fallback" aria-hidden="true"><i class="bi bi-person-fill person"></i><span class="hospital-mark"></span></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="profile-title"><?= htmlspecialchars($displayName) ?></div>
                            <div class="profile-subtitle"><?= htmlspecialchars($user['department_name'] ?? '-') ?></div>
                            <span class="role-pill"><i class="bi bi-shield-check"></i><?= htmlspecialchars($roleLabel) ?></span>
                        </div>
                    </div>
                    <div class="signature-box">
                        <div class="small text-muted mb-2">ลายเซ็นที่บันทึกในระบบ</div>
                        <?php if (!empty($user['signature_path'])): ?>
                            <img src="../uploads/signatures/<?= htmlspecialchars($user['signature_path']) ?>" alt="ลายเซ็น">
                        <?php else: ?>
                            <div class="text-muted">ยังไม่ได้เพิ่มลายเซ็น</div>
                        <?php endif; ?>
                    </div>
                    <a href="profile.php" class="workspace-action-button btn-outline-style"><span>จัดการโปรไฟล์และรูปภาพ</span><i class="bi bi-arrow-right"></i></a>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="workspace-card workspace-card--action">
                            <div class="workspace-header">
                                <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi bi-clock-history"></i>งานส่วนตัว</div><span class="workspace-icon"><i class="bi bi-clock-history"></i></span></div>
                                <div class="workspace-body"><h3>ลงเวลาและประวัติของฉัน</h3><p>บันทึกเวลา ดูประวัติ และเปิดงานต่อจากจุดเดียว</p></div>
                            </div>
                            <div class="workspace-actions"><span class="workspace-hint">ใช้บ่อยที่สุดในการเริ่มงานแต่ละวัน</span><a class="workspace-action-button" href="time.php"><span>ไปหน้าลงเวลาเวร</span><i class="bi bi-arrow-right"></i></a></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="workspace-card workspace-card--action">
                            <div class="workspace-header">
                                <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi bi-calendar-week"></i>ประสานงาน</div><span class="workspace-icon"><i class="bi bi-calendar-week"></i></span></div>
                                <div class="workspace-body"><h3>เวรวันนี้</h3><p>ดูรายชื่อ แผนก และช่วงเวลาของทีมที่ปฏิบัติงานวันนี้</p></div>
                            </div>
                            <div class="workspace-actions"><span class="workspace-hint">เหมาะสำหรับดูทีมที่กำลังปฏิบัติงานอยู่</span><a class="workspace-action-button" href="daily_schedule.php"><span>เปิดตารางเวรวันนี้</span><i class="bi bi-arrow-right"></i></a></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="workspace-card workspace-card--action">
                            <div class="workspace-header">
                                <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi bi-bar-chart-line"></i>สรุปข้อมูล</div><span class="workspace-icon"><i class="bi bi-bar-chart-line"></i></span></div>
                                <div class="workspace-body"><h3>รายงานของฉัน</h3><p>ดูสรุปส่วนตัวและพิมพ์รายงานได้จากหน้าเดียว</p></div>
                            </div>
                            <div class="workspace-actions"><span class="workspace-hint">เปิดสรุปข้อมูลของตัวเองได้ทันที</span><a class="workspace-action-button" href="my_reports.php"><span>เปิดรายงานของฉัน</span><i class="bi bi-arrow-right"></i></a></div>
                        </div>
                    </div>
                    <?php if (app_can('can_view_department_reports')): ?>
                        <div class="col-md-6">
                            <div class="workspace-card workspace-card--action">
                                <div class="workspace-header">
                                    <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi bi-building"></i>รายงานหน่วยงาน</div><span class="workspace-icon"><i class="bi bi-building"></i></span></div>
                                    <div class="workspace-body"><h3>รายงานแผนก</h3><p>ดูภาพรวมเวร ชั่วโมง และสถานะตามขอบเขตที่เข้าถึงได้</p></div>
                                </div>
                                <div class="workspace-actions"><span class="workspace-hint">แสดงเฉพาะผู้ที่มีสิทธิ์เข้าถึงรายงานแผนก</span><a class="workspace-action-button" href="department_reports.php"><span>เปิดรายงานแผนก</span><i class="bi bi-arrow-right"></i></a></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($adminShortcut): ?>
                        <div class="col-md-6">
                            <div class="workspace-card workspace-card--action">
                                <div class="workspace-header">
                                    <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi <?= htmlspecialchars($adminShortcut['icon']) ?>"></i><?= htmlspecialchars($adminShortcut['eyebrow']) ?></div><span class="workspace-icon"><i class="bi <?= htmlspecialchars($adminShortcut['icon']) ?>"></i></span></div>
                                    <div class="workspace-body"><h3><?= htmlspecialchars($adminShortcut['title']) ?></h3><p><?= htmlspecialchars($adminShortcut['description']) ?></p></div>
                                </div>
                                <div class="workspace-actions"><span class="workspace-hint">แสดงเฉพาะผู้ใช้ที่มีสิทธิ์ดูแลระบบเท่านั้น</span><a class="workspace-action-button" href="<?= htmlspecialchars($adminShortcut['href']) ?>"><span><?= htmlspecialchars($adminShortcut['button_label']) ?></span><i class="bi bi-arrow-right"></i></a></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="row g-4 align-items-stretch">
            <?php if ($latestLog): ?>
                <div class="col-lg-6">
                    <div class="workspace-card workspace-card--action h-100">
                        <h3 class="section-title">รายการล่าสุดของฉัน</h3>
                        <div class="status-row">
                            <div class="status-pill"><span>วันที่</span><strong><?= htmlspecialchars($latestLogDateLabel) ?></strong></div>
                            <div class="status-pill"><span>เวลา</span><strong><?= !empty($latestLog['time_in']) ? date('H:i', strtotime((string) $latestLog['time_in'])) : '-' ?> - <?= !empty($latestLog['time_out']) ? date('H:i', strtotime((string) $latestLog['time_out'])) : '-' ?></strong></div>
                            <div class="status-pill"><span>สถานะ</span><strong><?= !empty($latestLog['checked_at']) ? 'ตรวจแล้ว' : 'รอตรวจ' ?></strong></div>
                        </div>
                        <div class="workspace-actions"><span class="workspace-hint">กลับไปดูรายการล่าสุดของตัวเองได้ทันที</span><a class="workspace-action-button btn-outline-style" href="time.php"><span>เปิดหน้าลงเวลาเวร</span><i class="bi bi-arrow-right"></i></a></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (app_can('can_approve_logs')): ?>
                <div class="col-lg-6">
                    <div class="workspace-card workspace-card--action h-100">
                        <h3 class="section-title">คิวตรวจสอบ</h3>
                        <p>รายการที่ยังรอตรวจอยู่ตอนนี้สำหรับบทบาทของคุณ</p>
                        <div class="d-flex align-items-center justify-content-between mt-2 gap-3 flex-wrap"><span class="fs-2 fw-bold"><?= number_format($pendingCount) ?></span><span class="workspace-hint">แสดงเฉพาะผู้มีสิทธิ์อนุมัติรายการ</span></div>
                        <div class="workspace-actions"><span class="workspace-hint">เปิดคิวเพื่อตรวจสอบรายการที่รออยู่ตอนนี้</span><a class="workspace-action-button" href="approval_queue.php"><span>เปิดหน้าตรวจสอบเวร</span><i class="bi bi-arrow-right"></i></a></div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
