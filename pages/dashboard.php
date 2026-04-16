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
        'description' => 'บันทึกเวลาเข้าออก ดูประวัติของตัวเอง และเริ่มงานประจำวันจากหน้าที่ใช้งานบ่อยที่สุด',
        'button_label' => 'ไปหน้าลงเวลาเวร',
        'tone' => 'primary',
    ],
    [
        'href' => 'daily_schedule.php',
        'icon' => 'bi-calendar-week',
        'eyebrow' => 'ประสานงานประจำวัน',
        'title' => 'เวรวันนี้',
        'description' => 'เปิดตารางเวรประจำวันเพื่อดูว่าใครกำลังปฏิบัติงานอยู่บ้างในแต่ละแผนก',
        'button_label' => 'เปิดตารางเวรวันนี้',
        'tone' => 'teal',
    ],
    [
        'href' => 'my_reports.php',
        'icon' => 'bi-bar-chart-line',
        'eyebrow' => 'รายงานส่วนตัว',
        'title' => 'รายงานของฉัน',
        'description' => 'ดูสรุปรายสัปดาห์ รายเดือน รายปี และพิมพ์รายงานส่วนตัวได้ทันที',
        'button_label' => 'เปิดรายงานของฉัน',
        'tone' => 'light',
    ],
    [
        'href' => 'profile.php',
        'icon' => 'bi-person-circle',
        'eyebrow' => 'ข้อมูลบัญชี',
        'title' => 'โปรไฟล์และลายเซ็น',
        'description' => 'อัปเดตรูปประจำตัว ลายเซ็น และข้อมูลส่วนตัวให้พร้อมสำหรับงานเอกสารในระบบ',
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
        'description' => 'ตรวจรายการที่รออนุมัติและเปิดคิวตรวจสอบได้อย่างชัดเจนจากหน้าแดชบอร์ด',
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
        'description' => 'ดูสรุปชั่วโมง เวร และสถานะตรวจสอบของเจ้าหน้าที่ตามขอบเขตที่คุณมีสิทธิ์เข้าถึง',
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
        'description' => 'เข้าสู่เครื่องมือดูแลข้อมูลและระบบหลังบ้านสำหรับงานผู้ดูแลที่ได้รับมอบหมาย',
        'button_label' => 'เปิดหน้าหลังบ้าน',
    ];
} elseif (app_can('can_manage_user_permissions')) {
    $adminShortcut = [
        'href' => 'manage_users.php',
        'icon' => 'bi-shield-lock',
        'eyebrow' => 'พื้นที่ผู้ดูแล',
        'title' => 'จัดการผู้ใช้งาน',
        'description' => 'เปิดหน้าจัดการผู้ใช้งานและสิทธิ์เพื่อดูแลบัญชีในระบบได้จากแดชบอร์ดทันที',
        'button_label' => 'เปิดหน้าผู้ดูแลผู้ใช้งาน',
    ];
} elseif (app_can('can_manage_time_logs')) {
    $adminShortcut = [
        'href' => 'manage_time_logs.php',
        'icon' => 'bi-pencil-square',
        'eyebrow' => 'งานปฏิบัติการ',
        'title' => 'จัดการรายการลงเวลาเวร',
        'description' => 'ตรวจสอบและแก้ไขรายการลงเวลาเวรภาพรวมสำหรับผู้ที่ได้รับสิทธิ์จัดการข้อมูล',
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
        .dashboard-shell { padding: 32px 0 48px; }
        .dashboard-hero {
            padding: 32px;
            border-radius: 34px;
            overflow: hidden;
            background: linear-gradient(145deg, rgba(16, 36, 59, 0.98), rgba(28, 107, 99, 0.92)), url('../LOGO/nongphok_logo.png') right 42px center/180px no-repeat;
            color: #f6fbff;
            min-height: 320px;
            display: grid;
            align-items: stretch;
        }
        .dashboard-hero-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.65fr); gap: 24px; align-items: stretch; }
        .dashboard-hero h1, .section-title, .metric-card .value, .profile-title, .hero-side-title, .hero-side-value, .quick-action-title, .workspace-card--action h3 { font-family: 'Prompt', sans-serif; }
        .hero-badge { width: fit-content; display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.08); border-radius: 999px; padding: 10px 14px; font-size: 0.82rem; font-weight: 700; }
        .hero-copy h1 { font-size: clamp(2rem, 3vw, 3.3rem); line-height: 1.05; margin: 18px 0 12px; max-width: 720px; }
        .hero-copy p { color: rgba(246, 251, 255, 0.84); line-height: 1.8; max-width: 700px; margin: 0; }
        .hero-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
        .hero-meta span { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; background: rgba(255,255,255,0.08); color: rgba(246,251,255,0.94); font-size: 0.92rem; }
        .hero-side { padding: 22px; border-radius: 26px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(12px); display: grid; gap: 14px; align-content: start; }
        .hero-side-title { font-size: 1.05rem; margin: 0; }
        .hero-side-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .hero-side-row:last-child { padding-bottom: 0; border-bottom: 0; }
        .hero-side-label { color: rgba(246,251,255,0.82); font-size: 0.92rem; }
        .hero-side-value { font-size: 1.8rem; font-weight: 700; }
        .metric-card, .workspace-card, .profile-card { background: rgba(255,255,255,0.9); border-radius: 26px; border: 1px solid rgba(16,36,59,.08); padding: 24px; height: 100%; }
        .metric-card .label { color: #6b7a8d; font-weight: 700; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .metric-card .value { font-size: 2rem; margin-top: 10px; }
        .profile-card { display: grid; gap: 20px; }
        .profile-top { display: grid; grid-template-columns: 112px 1fr; gap: 18px; align-items: center; }
        .avatar-frame { width: 112px; height: 112px; border-radius: 32px; overflow: hidden; position: relative; background: linear-gradient(135deg, rgba(16, 36, 59, 0.96), rgba(28, 107, 99, 0.88)), url('../LOGO/nongphok_logo.png') center/72px no-repeat; }
        .avatar-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .avatar-fallback { position: absolute; inset: 0; display: grid; place-items: center; color: rgba(255,255,255,.96); background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,0)), linear-gradient(135deg, rgba(16, 36, 59, 0.96), rgba(28, 107, 99, 0.88)); }
        .avatar-fallback .person { font-size: 2.8rem; line-height: 1; transform: translateY(-4px); }
        .avatar-fallback .hospital-mark { position: absolute; inset: auto 12px 12px auto; width: 38px; height: 38px; border-radius: 12px; background: rgba(255,255,255,.12) url('../LOGO/nongphok_logo.png') center/24px no-repeat; border: 1px solid rgba(255,255,255,.14); }
        .profile-title { font-size: 1.6rem; margin-bottom: 6px; }
        .profile-subtitle, .workspace-card p { color: #6b7a8d; margin-bottom: 0; line-height: 1.7; }
        .role-pill { display: inline-flex; align-items: center; gap: 8px; margin-top: 10px; padding: 8px 12px; border-radius: 999px; background: rgba(16,36,59,.06); border: 1px solid rgba(16,36,59,.08); font-weight: 700; }
        .signature-box { padding: 14px 16px; border-radius: 20px; border: 1px dashed rgba(16,36,59,.12); background: #fbfdff; }
        .signature-box img { max-height: 86px; object-fit: contain; display: block; }
        .workspace-card { display: grid; gap: 10px; align-content: start; }
        .section-kicker { display: inline-flex; align-items: center; gap: 8px; color: #36516e; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px; }
        .section-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .section-header p { color: #6b7a8d; margin: 8px 0 0; max-width: 760px; line-height: 1.8; }
        .section-header .section-title { font-size: 1.75rem; margin: 0; color: #10243b; }
        .quick-action-card { background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(244, 249, 253, 0.96)); border-radius: 28px; border: 1px solid rgba(16, 36, 59, 0.08); padding: 24px; height: 100%; display: grid; gap: 16px; box-shadow: 0 18px 42px rgba(16, 36, 59, 0.08); transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .quick-action-card:hover { transform: translateY(-3px); box-shadow: 0 24px 48px rgba(16, 36, 59, 0.12); border-color: rgba(28, 107, 99, 0.24); }
        .quick-action-card--primary { background: linear-gradient(145deg, rgba(16, 36, 59, 0.98), rgba(22, 74, 117, 0.96)); color: #f6fbff; }
        .quick-action-card--teal { background: linear-gradient(145deg, rgba(20, 93, 85, 0.98), rgba(60, 138, 124, 0.94)); color: #f5fffd; }
        .quick-action-card--warning { background: linear-gradient(145deg, rgba(255, 247, 225, 0.98), rgba(255, 239, 196, 0.98)); border-color: rgba(212, 154, 42, 0.22); }
        .quick-action-card--light { background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(246, 249, 252, 0.98)); }
        .quick-action-card--primary .quick-action-eyebrow, .quick-action-card--primary .quick-action-title, .quick-action-card--primary .quick-action-copy, .quick-action-card--teal .quick-action-eyebrow, .quick-action-card--teal .quick-action-title, .quick-action-card--teal .quick-action-copy { color: #f6fbff; }
        .quick-action-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .quick-action-icon { width: 56px; height: 56px; border-radius: 20px; display: grid; place-items: center; font-size: 1.35rem; background: rgba(16, 36, 59, 0.08); color: #10243b; flex: 0 0 auto; }
        .quick-action-card--primary .quick-action-icon, .quick-action-card--teal .quick-action-icon { background: rgba(255,255,255,0.12); color: #fff; }
        .quick-action-eyebrow { color: #5d7288; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; }
        .quick-action-title { font-size: 1.32rem; margin: 0; color: #10243b; }
        .quick-action-copy { color: #5d7288; line-height: 1.75; margin: 0; }
        .dashboard-action-button, .workspace-action-button { width: fit-content; display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 18px; border-radius: 999px; border: 1px solid rgba(16, 36, 59, 0.12); font-weight: 700; text-decoration: none; transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease; }
        .dashboard-action-button:hover, .workspace-action-button:hover { transform: translateY(-1px); box-shadow: 0 14px 24px rgba(16, 36, 59, 0.1); }
        .dashboard-action-button { background: #fff; color: #10243b; }
        .quick-action-card--primary .dashboard-action-button, .quick-action-card--teal .dashboard-action-button { background: rgba(255,255,255,0.12); color: #fff; border-color: rgba(255,255,255,0.16); }
        .workspace-card--action { gap: 16px; }
        .workspace-card--action .workspace-header { display: grid; gap: 10px; }
        .workspace-card--action .workspace-header-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .workspace-card--action .workspace-icon { width: 48px; height: 48px; border-radius: 18px; display: grid; place-items: center; background: rgba(16,36,59,.06); color: #10243b; font-size: 1.2rem; }
        .workspace-card--action .workspace-body { display: grid; gap: 8px; }
        .workspace-card--action .workspace-body h3 { font-size: 1.16rem; }
        .workspace-card--action .workspace-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: auto; }
        .workspace-card--action .workspace-hint { color: #6b7a8d; font-size: 0.92rem; font-weight: 600; }
        .workspace-action-button { background: #10243b; color: #fff; }
        .workspace-action-button.btn-outline-style { background: transparent; color: #10243b; border-color: rgba(16, 36, 59, 0.14); }
        .issue-callout { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding: 18px 20px; border-radius: 22px; background: linear-gradient(135deg, rgba(255, 244, 220, 0.96), rgba(255, 250, 235, 0.98)); border: 1px solid rgba(211, 154, 40, 0.2); box-shadow: 0 18px 40px rgba(211, 154, 40, 0.08); }
        .issue-copy { display: grid; gap: 6px; }
        .status-row { display: grid; gap: 12px; }
        .status-pill { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 16px; border-radius: 18px; background: #f9fbfc; border: 1px solid rgba(16, 36, 59, 0.08); }
        @media (max-width: 991px) { .dashboard-hero { background-position: right -10px top 24px; background-size: 130px; min-height: auto; } .dashboard-hero-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .dashboard-shell { padding: 24px 0 38px; } .dashboard-hero, .metric-card, .workspace-card, .profile-card, .quick-action-card { border-radius: 24px; } .dashboard-hero { padding: 28px; } .profile-top { grid-template-columns: 1fr; } .section-header { flex-direction: column; align-items: flex-start; } .quick-action-card, .workspace-card--action { padding: 22px; } }
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
                    <h1>สวัสดี <?= htmlspecialchars($displayName) ?> วันนี้คุณกำลังใช้งานในบทบาท <?= htmlspecialchars($roleLabel) ?></h1>
                    <p>เข้าถึงการลงเวลาเวร รายงาน ตารางเวรประจำวัน และพื้นที่งานตามสิทธิ์ได้จากหน้าเดียว โดยจัดลำดับข้อมูลให้เห็นสิ่งสำคัญก่อนและใช้งานง่ายบนหน้าจอจริง</p>
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
            <div class="col-md-4"><div class="metric-card"><div class="label">ชั่วโมงเดือนนี้</div><div class="value"><?= number_format($monthHours, 2) ?></div><div class="text-muted">รวมเวลาปฏิบัติงานของเดือนปัจจุบัน</div></div></div>
            <div class="col-md-4"><div class="metric-card"><div class="label">ชั่วโมงปีนี้</div><div class="value"><?= number_format($yearHours, 2) ?></div><div class="text-muted">สรุปชั่วโมงสะสมทั้งปีของบัญชีนี้</div></div></div>
            <div class="col-md-4"><div class="metric-card"><div class="label">รายการเวรวันนี้</div><div class="value"><?= number_format($todayScheduleCount) ?></div><div class="text-muted">จำนวนรายการลงเวลาในภาพรวมของวันนี้</div></div></div>
        </section>

        <section class="mb-4">
            <div class="section-header">
                <div>
                    <span class="section-kicker"><i class="bi bi-lightning-charge"></i>ทางลัดการใช้งาน</span>
                    <h2 class="section-title">เริ่มงานจากปุ่มที่กดได้ชัดเจน</h2>
                    <p>รวมเมนูที่ใช้บ่อยไว้บนหน้าเดียวและทำให้แต่ละรายการดูเป็นปุ่มใช้งานจริง เพื่อให้ผู้ใช้เห็นทันทีว่ากดแล้วจะพาไปทำงานต่อที่หน้าไหน</p>
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
                        <div class="fw-semibold">วันนี้พบรายการที่ควรตรวจสอบ <?= number_format($todayIssueCount) ?> รายการ</div>
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
                                <div class="workspace-body"><h3>ลงเวลาและจัดการประวัติของฉัน</h3><p>บันทึกเวลาเข้าออก ค้นประวัติย้อนหลัง และเปิดรายงานส่วนตัวได้จากบล็อกเดียวโดยไม่ต้องเดาว่าลิงก์อยู่ตรงไหน</p></div>
                            </div>
                            <div class="workspace-actions"><span class="workspace-hint">ใช้บ่อยที่สุดในการเริ่มงานแต่ละวัน</span><a class="workspace-action-button" href="time.php"><span>ไปหน้าลงเวลาเวร</span><i class="bi bi-arrow-right"></i></a></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="workspace-card workspace-card--action">
                            <div class="workspace-header">
                                <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi bi-calendar-week"></i>ประสานงาน</div><span class="workspace-icon"><i class="bi bi-calendar-week"></i></span></div>
                                <div class="workspace-body"><h3>ดูว่าใครลงเวรวันนี้</h3><p>เปิดตารางเวรประจำวันเพื่อดูรายชื่อ แผนก และช่วงเวลาของผู้ที่ปฏิบัติงานในวันนี้ได้อย่างรวดเร็ว</p></div>
                            </div>
                            <div class="workspace-actions"><span class="workspace-hint">เหมาะสำหรับดูทีมที่กำลังปฏิบัติงานอยู่</span><a class="workspace-action-button" href="daily_schedule.php"><span>เปิดตารางเวรวันนี้</span><i class="bi bi-arrow-right"></i></a></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="workspace-card workspace-card--action">
                            <div class="workspace-header">
                                <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi bi-bar-chart-line"></i>สรุปข้อมูล</div><span class="workspace-icon"><i class="bi bi-bar-chart-line"></i></span></div>
                                <div class="workspace-body"><h3>รายงานของฉัน</h3><p>ดูรายสัปดาห์ รายเดือน รายปี หรือพิมพ์และส่งออกรายงานส่วนตัวได้ทันทีจากหน้ารายงานนี้</p></div>
                            </div>
                            <div class="workspace-actions"><span class="workspace-hint">เปิดสรุปข้อมูลของตัวเองได้ทันที</span><a class="workspace-action-button" href="my_reports.php"><span>เปิดรายงานของฉัน</span><i class="bi bi-arrow-right"></i></a></div>
                        </div>
                    </div>
                    <?php if (app_can('can_view_department_reports')): ?>
                        <div class="col-md-6">
                            <div class="workspace-card workspace-card--action">
                                <div class="workspace-header">
                                    <div class="workspace-header-top"><div class="section-kicker mb-0"><i class="bi bi-building"></i>รายงานหน่วยงาน</div><span class="workspace-icon"><i class="bi bi-building"></i></span></div>
                                    <div class="workspace-body"><h3>รายงานแผนก</h3><p>สรุปชั่วโมงรวม เวร และสถานะตรวจสอบของเจ้าหน้าที่ตามขอบเขตแผนกที่คุณเข้าถึงได้</p></div>
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
                        <div class="workspace-actions"><span class="workspace-hint">ใช้ต่อยอดเพื่อเปิดดูรายการและแก้ไขข้อมูลของตัวเอง</span><a class="workspace-action-button btn-outline-style" href="time.php"><span>เปิดหน้าลงเวลาเวร</span><i class="bi bi-arrow-right"></i></a></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (app_can('can_approve_logs')): ?>
                <div class="col-lg-6">
                    <div class="workspace-card workspace-card--action h-100">
                        <h3 class="section-title">คิวตรวจสอบ</h3>
                        <p>คุณมีสิทธิ์อนุมัติรายการลงเวลา และตอนนี้มีรายการที่ยังรอตรวจอยู่ในระบบ</p>
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
