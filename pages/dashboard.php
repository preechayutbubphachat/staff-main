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
$departmentLabel = (string) ($user['department_name'] ?? 'ยังไม่ได้ระบุแผนก');

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

$heroSupportText = app_can('can_approve_logs')
    ? 'ดูภาพรวมและเปิดคิวตรวจสอบได้ทันทีจากหน้าเดียว'
    : 'เริ่มงานต่อได้ทันทีจากข้อมูลสรุปและทางลัดที่ใช้บ่อย';
$priorityReviewValue = app_can('can_approve_logs') ? $pendingCount : $todayIssueCount;
$priorityReviewLabel = app_can('can_approve_logs') ? 'รอตรวจสอบ' : 'จุดที่ควรเช็ก';
$priorityReviewSubtext = app_can('can_approve_logs')
    ? 'รายการที่อยู่ในคิวอนุมัติ'
    : 'ข้อมูลที่ยังไม่ครบหรือมีเวลาซ้อน';

$kpiCards = [
    [
        'label' => 'ชั่วโมงเดือนนี้',
        'value' => number_format($monthHours, 2),
        'subtext' => 'สะสมของเดือนปัจจุบัน',
        'tone' => 'teal',
        'icon' => 'bi bi-calendar2-week',
    ],
    [
        'label' => 'ชั่วโมงปีนี้',
        'value' => number_format($yearHours, 2),
        'subtext' => 'สรุปตั้งแต่ต้นปี',
        'tone' => 'sky',
        'icon' => 'bi bi-graph-up-arrow',
    ],
    [
        'label' => 'เวรวันนี้',
        'value' => number_format($todayScheduleCount),
        'subtext' => 'รายการเวรของวันนี้',
        'tone' => 'violet',
        'icon' => 'bi bi-calendar-check',
    ],
    [
        'label' => $priorityReviewLabel,
        'value' => number_format($priorityReviewValue),
        'subtext' => $priorityReviewSubtext,
        'tone' => 'amber',
        'icon' => 'bi bi-shield-check',
    ],
];

$quickActions = [
    [
        'href' => 'time.php',
        'icon' => 'bi-clock-history',
        'eyebrow' => 'งานประจำวันที่ใช้บ่อย',
        'title' => 'ลงเวลาเวร',
        'description' => 'บันทึกเวลาเข้าออกได้ทันที',
        'button_label' => 'เปิดหน้า',
        'tone' => 'primary',
    ],
    [
        'href' => 'daily_schedule.php',
        'icon' => 'bi-calendar-week',
        'eyebrow' => 'ประสานงานประจำวัน',
        'title' => 'เวรวันนี้',
        'description' => 'ดูทีมที่ปฏิบัติงานอยู่ตอนนี้',
        'button_label' => 'เปิดหน้า',
        'tone' => 'teal',
    ],
    [
        'href' => 'my_reports.php',
        'icon' => 'bi-bar-chart-line',
        'eyebrow' => 'รายงานส่วนตัว',
        'title' => 'รายงานของฉัน',
        'description' => 'สรุปข้อมูลส่วนตัวแบบสั้นและชัด',
        'button_label' => 'เปิดหน้า',
        'tone' => 'light',
    ],
    [
        'href' => 'profile.php',
        'icon' => 'bi-person-circle',
        'eyebrow' => 'ข้อมูลบัญชี',
        'title' => 'โปรไฟล์และลายเซ็น',
        'description' => 'อัปเดตรูป ลายเซ็น และข้อมูลส่วนตัว',
        'button_label' => 'เปิดหน้า',
        'tone' => 'light',
    ],
];

if (app_can('can_approve_logs')) {
    array_splice($quickActions, 1, 0, [[
        'href' => 'approval_queue.php',
        'icon' => 'bi-patch-check',
        'eyebrow' => 'งานตรวจสอบ',
        'title' => 'ตรวจสอบเวร',
        'description' => 'เปิดคิวอนุมัติที่ต้องจัดการ',
        'button_label' => 'เปิดหน้า',
        'tone' => 'warning',
    ]]);
}

if (app_can('can_view_department_reports')) {
    $quickActions[] = [
        'href' => 'department_reports.php',
        'icon' => 'bi-building',
        'eyebrow' => 'ภาพรวมหน่วยงาน',
        'title' => 'รายงานแผนก',
        'description' => 'ดูภาพรวมเวรและชั่วโมงตามสิทธิ์',
        'button_label' => 'เปิดหน้า',
        'tone' => 'light',
    ];
}

$adminShortcut = null;
if (app_can('can_manage_database')) {
    $adminShortcut = [
        'href' => 'db_admin_dashboard.php',
        'icon' => 'bi-database-gear',
        'eyebrow' => 'พื้นที่ผู้ดูแล',
        'title' => 'หน้าหลังบ้าน',
        'description' => 'ดูแลข้อมูลและเครื่องมือส่วนกลาง',
        'button_label' => 'เปิดหน้า',
    ];
} elseif (app_can('can_manage_user_permissions')) {
    $adminShortcut = [
        'href' => 'manage_users.php',
        'icon' => 'bi-shield-lock',
        'eyebrow' => 'พื้นที่ผู้ดูแล',
        'title' => 'จัดการผู้ใช้งาน',
        'description' => 'ดูแลบัญชีและสิทธิ์การเข้าถึง',
        'button_label' => 'เปิดหน้า',
    ];
} elseif (app_can('can_manage_time_logs')) {
    $adminShortcut = [
        'href' => 'manage_time_logs.php',
        'icon' => 'bi-pencil-square',
        'eyebrow' => 'งานปฏิบัติการ',
        'title' => 'จัดการลงเวลาเวร',
        'description' => 'ตรวจสอบและแก้ไขข้อมูลลงเวลาเวร',
        'button_label' => 'เปิดหน้า',
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
</head>
<body class="app-ui">
<?php render_app_navigation('dashboard.php'); ?>

<main class="dashboard-shell prism-dashboard-shell">
    <div class="container">
        <section class="prism-hero glass-card glass-card--hero mb-4">
            <div class="prism-hero-grid">
                <div class="prism-hero-copy">
                    <span class="prism-chip prism-chip--soft">
                        <i class="bi bi-stars"></i>
                        ศูนย์กลางการทำงานประจำวัน
                    </span>
                    <h1 class="prism-hero-title">สวัสดี <?= htmlspecialchars($displayName) ?></h1>
                    <p class="prism-hero-subtitle"><?= htmlspecialchars($heroSupportText) ?></p>

                    <div class="prism-chip-row">
                        <span class="prism-chip"><i class="bi bi-building"></i><?= htmlspecialchars($departmentLabel) ?></span>
                        <span class="prism-chip"><i class="bi bi-person-badge"></i><?= htmlspecialchars($roleLabel) ?></span>
                        <span class="prism-chip"><i class="bi bi-calendar3"></i><?= htmlspecialchars($todayLabel) ?></span>
                    </div>
                </div>

                <aside class="glass-card glass-card--inner prism-summary-panel">
                    <div class="section-header section-header--compact mb-0">
                        <div>
                            <span class="section-kicker"><i class="bi bi-activity"></i>ภาพรวมตอนนี้</span>
                            <h2 class="section-title section-title--medium">ตัวเลขสำคัญ</h2>
                        </div>
                    </div>

                    <div class="prism-summary-list">
                        <div class="prism-summary-item">
                            <span class="prism-summary-label">เวรทั้งหมดวันนี้</span>
                            <strong class="prism-summary-value"><?= number_format($todayScheduleCount) ?></strong>
                        </div>
                        <div class="prism-summary-item">
                            <span class="prism-summary-label">ชั่วโมงเดือนนี้</span>
                            <strong class="prism-summary-value"><?= number_format($monthHours, 2) ?></strong>
                        </div>
                        <div class="prism-summary-item">
                            <span class="prism-summary-label"><?= htmlspecialchars($priorityReviewLabel) ?></span>
                            <strong class="prism-summary-value"><?= number_format($priorityReviewValue) ?></strong>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="mb-4">
            <div class="prism-kpi-grid">
                <?php foreach ($kpiCards as $card): ?>
                    <article class="glass-card glass-card--kpi prism-kpi-card prism-kpi-card--<?= htmlspecialchars($card['tone']) ?>">
                        <div class="prism-kpi-top">
                            <span class="prism-kpi-label"><?= htmlspecialchars($card['label']) ?></span>
                            <span class="prism-kpi-icon"><i class="<?= htmlspecialchars($card['icon']) ?>"></i></span>
                        </div>
                        <div class="prism-kpi-value"><?= htmlspecialchars($card['value']) ?></div>
                        <div class="prism-helper-text"><?= htmlspecialchars($card['subtext']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mb-4">
            <div class="section-header">
                <div>
                    <span class="section-kicker"><i class="bi bi-lightning-charge"></i>ทางลัดการใช้งาน</span>
                    <h2 class="section-title">เปิดงานต่อได้ทันที</h2>
                    <p class="section-subtitle">แสดงเฉพาะงานที่ใช้จริงและกดต่อได้เร็ว</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($quickActions as $action): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="glass-card glass-card--action prism-action-card prism-action-card--<?= htmlspecialchars($action['tone']) ?>">
                            <div class="prism-action-head">
                                <div>
                                    <div class="prism-action-eyebrow"><?= htmlspecialchars($action['eyebrow']) ?></div>
                                    <h3 class="prism-action-title"><?= htmlspecialchars($action['title']) ?></h3>
                                </div>
                                <div class="prism-action-icon">
                                    <i class="bi <?= htmlspecialchars($action['icon']) ?>"></i>
                                </div>
                            </div>
                            <p class="prism-action-copy"><?= htmlspecialchars($action['description']) ?></p>
                            <a href="<?= htmlspecialchars($action['href']) ?>" class="prism-action-link">
                                <span><?= htmlspecialchars($action['button_label']) ?></span>
                                <i class="bi bi-arrow-up-right"></i>
                            </a>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($todayIssueCount > 0): ?>
            <section class="mb-4">
                <div class="glass-card glass-card--alert prism-alert-card">
                    <div>
                        <div class="prism-alert-title">วันนี้มี <?= number_format($todayIssueCount) ?> รายการที่ควรตรวจสอบ</div>
                        <div class="prism-helper-text">
                            <?php if ($todayOverlapCount > 0): ?>ช่วงเวลาซ้อน <?= number_format($todayOverlapCount) ?> รายการ<?php endif; ?>
                            <?php if ($todayIncompleteCount > 0): ?><?= $todayOverlapCount > 0 ? ' และ ' : '' ?>ข้อมูลเวลาไม่ครบ <?= number_format($todayIncompleteCount) ?> รายการ<?php endif; ?>
                        </div>
                    </div>
                    <a class="btn btn-outline-dark rounded-pill px-4" href="time.php">
                        <i class="bi bi-clock-history me-1"></i>เปิดหน้าเวลาเวร
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <section class="row g-4 align-items-start dashboard-profile-row">
            <div class="col-xl-4 dashboard-profile-col">
                <article class="glass-card glass-card--profile prism-profile-card dashboard-profile-card">
                    <div class="prism-profile-top">
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
                            <div class="prism-profile-name"><?= htmlspecialchars($displayName) ?></div>
                            <div class="prism-profile-meta"><?= htmlspecialchars($departmentLabel) ?></div>
                            <span class="prism-chip prism-chip--role"><i class="bi bi-shield-check"></i><?= htmlspecialchars($roleLabel) ?></span>
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

                    <a href="profile.php" class="prism-secondary-link">
                        <span>จัดการโปรไฟล์และรูปภาพ</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </article>
            </div>

            <div class="col-xl-8">
                <div class="row g-4">
                    <?php if ($latestLog): ?>
                        <div class="col-md-6">
                            <article class="glass-card glass-card--panel prism-panel-card h-100">
                                <div class="section-header section-header--compact">
                                    <div>
                                        <span class="section-kicker"><i class="bi bi-clock-history"></i>รายการล่าสุด</span>
                                        <h3 class="section-title section-title--medium">ข้อมูลของฉัน</h3>
                                    </div>
                                </div>

                                <div class="prism-status-stack">
                                    <div class="prism-status-row"><span>วันที่</span><strong><?= htmlspecialchars($latestLogDateLabel) ?></strong></div>
                                    <div class="prism-status-row"><span>เวลา</span><strong><?= !empty($latestLog['time_in']) ? date('H:i', strtotime((string) $latestLog['time_in'])) : '-' ?> - <?= !empty($latestLog['time_out']) ? date('H:i', strtotime((string) $latestLog['time_out'])) : '-' ?></strong></div>
                                    <div class="prism-status-row"><span>สถานะ</span><strong><?= !empty($latestLog['checked_at']) ? 'ตรวจแล้ว' : 'รอตรวจ' ?></strong></div>
                                </div>

                                <a class="prism-secondary-link" href="time.php">
                                    <span>เปิดหน้าลงเวลาเวร</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </article>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                        <article class="glass-card glass-card--panel prism-panel-card h-100">
                            <div class="section-header section-header--compact">
                                <div>
                                    <span class="section-kicker"><i class="bi bi-calendar-week"></i>ภาพรวมวันนี้</span>
                                    <h3 class="section-title section-title--medium">งานที่เห็นเร็ว</h3>
                                </div>
                            </div>

                            <div class="prism-mini-grid">
                                <div class="prism-mini-card">
                                    <span>เวรวันนี้</span>
                                    <strong><?= number_format($todayScheduleCount) ?></strong>
                                </div>
                                <div class="prism-mini-card">
                                    <span>ชั่วโมงเดือนนี้</span>
                                    <strong><?= number_format($monthHours, 2) ?></strong>
                                </div>
                            </div>

                            <a class="prism-secondary-link" href="daily_schedule.php">
                                <span>เปิดตารางเวรวันนี้</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </article>
                    </div>

                    <?php if (app_can('can_approve_logs')): ?>
                        <div class="col-md-6">
                            <article class="glass-card glass-card--panel prism-panel-card h-100">
                                <div class="section-header section-header--compact">
                                    <div>
                                        <span class="section-kicker"><i class="bi bi-patch-check"></i>คิวตรวจสอบ</span>
                                        <h3 class="section-title section-title--medium">งานอนุมัติ</h3>
                                    </div>
                                </div>

                                <div class="prism-panel-stat"><?= number_format($pendingCount) ?></div>
                                <div class="prism-helper-text">รายการที่ยังรอการตรวจสอบ</div>

                                <a class="prism-secondary-link" href="approval_queue.php">
                                    <span>เปิดหน้าตรวจสอบเวร</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </article>
                        </div>
                    <?php endif; ?>

                    <?php if ($adminShortcut): ?>
                        <div class="col-md-6">
                            <article class="glass-card glass-card--panel prism-panel-card h-100">
                                <div class="section-header section-header--compact">
                                    <div>
                                        <span class="section-kicker"><i class="bi <?= htmlspecialchars($adminShortcut['icon']) ?>"></i><?= htmlspecialchars($adminShortcut['eyebrow']) ?></span>
                                        <h3 class="section-title section-title--medium"><?= htmlspecialchars($adminShortcut['title']) ?></h3>
                                    </div>
                                </div>

                                <div class="prism-helper-text prism-helper-text--panel"><?= htmlspecialchars($adminShortcut['description']) ?></div>

                                <a class="prism-secondary-link" href="<?= htmlspecialchars($adminShortcut['href']) ?>">
                                    <span><?= htmlspecialchars($adminShortcut['button_label']) ?></span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </article>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
