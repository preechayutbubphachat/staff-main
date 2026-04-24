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

$uiStateContext = app_ui_state_context();
if (app_can('can_approve_logs')) {
    app_sync_reviewer_queue_notifications($conn);
}
$notificationCount = app_get_unread_notification_count($conn, (int) $uiStateContext['user_id']);
$bottomCardCount = ($latestLog ? 1 : 0) + (app_can('can_approve_logs') ? 1 : 0) + ($todayIssueCount > 0 ? 1 : 0);
$quickActionCount = count($quickActions);
$primaryActions = array_slice($quickActions, 0, min(3, $quickActionCount));
$secondaryActions = array_slice($quickActions, count($primaryActions));
$actionSpanClass = static function (int $count): string {
    if ($count <= 1) {
        return 'xl:col-span-12';
    }
    if ($count === 2) {
        return 'xl:col-span-6';
    }
    if ($count === 4) {
        return 'xl:col-span-3';
    }
    return 'xl:col-span-4';
};
$heroPrimaryAction = [
    'href' => 'time.php',
    'label' => 'ลงเวลาเวร',
    'icon' => 'bi-clock-history',
];
$heroSecondaryAction = app_can('can_approve_logs')
    ? ['href' => 'approval_queue.php', 'label' => 'ตรวจสอบเวร', 'icon' => 'bi-patch-check']
    : ['href' => 'profile.php', 'label' => 'โปรไฟล์ของฉัน', 'icon' => 'bi-person-circle'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แดชบอร์ด | ระบบลงเวลาเวร</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/dashboard-tailwind.output.css">
</head>
<body class="dash-shell">
<?php render_dashboard_sidebar('dashboard.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Dashboard</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">แดชบอร์ด</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาเมนูหรือรายงาน">
        </label>

        <a href="notifications.php" class="dash-icon-button" aria-label="การแจ้งเตือน">
            <i class="bi bi-bell"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="absolute -right-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1 text-[0.68rem] font-bold text-white"><?= (int) min($notificationCount, 99) ?></span>
            <?php endif; ?>
        </a>

        <a href="profile.php" class="hidden cursor-pointer items-center gap-3 rounded-2xl bg-white px-3 py-2 text-hospital-ink no-underline shadow-soft transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-glass focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hospital-teal focus-visible:ring-offset-2 active:translate-y-0 sm:flex">
            <span class="grid h-9 w-9 overflow-hidden rounded-xl bg-hospital-mist text-hospital-teal">
                <?php if ($profileImageSrc): ?>
                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="รูปโปรไฟล์" class="h-full w-full object-cover">
                <?php else: ?>
                    <span class="grid h-full w-full place-items-center"><i class="bi bi-person-fill"></i></span>
                <?php endif; ?>
            </span>
            <span class="grid leading-tight">
                <span class="max-w-[150px] truncate text-sm font-bold"><?= htmlspecialchars($displayName) ?></span>
                <span class="text-xs font-semibold text-hospital-muted"><?= htmlspecialchars($roleLabel) ?></span>
            </span>
        </a>
    </header>

    <div class="dash-dashboard-frame">
        <section class="dash-hero-layout">
            <article class="dash-card-strong">
                <div class="dash-hero-content-grid">
                    <div class="max-w-2xl">
                        <span class="dash-hero-badge">
                            <i class="bi bi-stars"></i>
                            Dashboard Overview
                        </span>
                        <h2 class="dash-hero-title">สวัสดี <?= htmlspecialchars($displayName) ?></h2>
                        <p class="dash-hero-copy"><?= htmlspecialchars($heroSupportText) ?></p>

                        <div class="dash-hero-chip-row">
                            <span class="dash-hero-chip"><i class="bi bi-building"></i><?= htmlspecialchars($departmentLabel) ?></span>
                            <span class="dash-hero-chip"><i class="bi bi-calendar3"></i><?= htmlspecialchars($todayLabel) ?></span>
                            <span class="dash-hero-chip"><i class="bi bi-activity"></i><?= number_format($todayScheduleCount) ?> เวรวันนี้</span>
                        </div>

                        <div class="dash-hero-actions">
                            <a href="<?= htmlspecialchars($heroPrimaryAction['href']) ?>" class="dash-btn dash-btn-secondary"><i class="bi <?= htmlspecialchars($heroPrimaryAction['icon']) ?>"></i><?= htmlspecialchars($heroPrimaryAction['label']) ?></a>
                            <a href="<?= htmlspecialchars($heroSecondaryAction['href']) ?>" class="dash-btn dash-btn-on-dark"><i class="bi <?= htmlspecialchars($heroSecondaryAction['icon']) ?>"></i><?= htmlspecialchars($heroSecondaryAction['label']) ?></a>
                        </div>
                    </div>

                    <div class="dash-profile-mini">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white/15 text-white">
                                <?php if ($profileImageSrc): ?>
                                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="รูปโปรไฟล์" class="h-full w-full object-cover">
                                <?php else: ?>
                                    <i class="bi bi-person-fill text-2xl"></i>
                                <?php endif; ?>
                            </span>
                            <div class="min-w-0">
                                <div class="truncate font-prompt text-base font-bold text-white"><?= htmlspecialchars($displayName) ?></div>
                                <div class="truncate text-xs font-semibold text-white/65"><?= htmlspecialchars($departmentLabel) ?></div>
                            </div>
                        </div>

                        <div class="mt-2 grid gap-1 text-xs font-semibold text-white/80">
                            <span class="inline-flex items-center gap-2"><i class="bi bi-person-badge"></i><?= htmlspecialchars($roleLabel) ?></span>
                            <span class="inline-flex items-center gap-2"><i class="bi bi-calendar3"></i><?= htmlspecialchars($todayLabel) ?></span>
                        </div>

                        <div class="mt-2 rounded-2xl bg-white/10 p-2">
                            <p class="text-[0.68rem] font-bold text-white/55">ลายเซ็น</p>
                            <div class="dash-signature-box">
                                <?php if (!empty($user['signature_path'])): ?>
                                    <img src="../uploads/signatures/<?= htmlspecialchars($user['signature_path']) ?>" alt="ลายเซ็น" class="max-h-7 object-contain brightness-0 invert">
                                <?php else: ?>
                                    ยังไม่ได้เพิ่มลายเซ็น
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <aside class="dash-kpi-grid" aria-label="สรุปค่าสำคัญ">
                <?php foreach ($kpiCards as $card): ?>
                    <article class="dash-kpi-card bg-gradient-to-br from-white to-hospital-mist/40">
                        <div class="flex w-full items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-bold text-hospital-muted"><?= htmlspecialchars($card['label']) ?></p>
                                <div class="mt-1 font-prompt text-2xl font-bold tracking-[-0.04em]"><?= htmlspecialchars($card['value']) ?></div>
                                <p class="mt-0.5 truncate text-[0.68rem] font-semibold text-hospital-muted"><?= htmlspecialchars($card['subtext']) ?></p>
                            </div>
                            <span class="dash-icon-badge"><i class="<?= htmlspecialchars($card['icon']) ?>"></i></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="grid min-h-0 gap-3">
            <div class="dash-section-heading">
                <div>
                    <p class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-hospital-teal">Primary Actions</p>
                    <h2 class="mt-1 font-prompt text-xl font-bold text-hospital-ink">งานหลักในระบบ</h2>
                </div>
                <p class="hidden max-w-md text-sm font-semibold text-hospital-muted xl:block">เลือกงานที่ใช้บ่อยที่สุด แล้วต่อไปยังรายงานหรือข้อมูลส่วนตัวได้ทันที</p>
            </div>

            <div class="grid min-h-0 gap-3 sm:grid-cols-2 xl:grid-cols-12 xl:auto-rows-fr">
                <?php foreach ($primaryActions as $index => $action): ?>
                    <a href="<?= htmlspecialchars($action['href']) ?>" class="dash-action-card group <?= $index === 0 ? 'sm:col-span-2' : '' ?> <?= htmlspecialchars($actionSpanClass(count($primaryActions))) ?> bg-gradient-to-br from-white to-hospital-mist/35">
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-hospital-teal"><?= htmlspecialchars($action['eyebrow']) ?></span>
                                <span class="dash-icon-badge"><i class="bi <?= htmlspecialchars($action['icon']) ?>"></i></span>
                            </div>
                            <h3 class="mt-2 font-prompt text-lg font-bold"><?= htmlspecialchars($action['title']) ?></h3>
                            <p class="mt-1 text-xs leading-5 text-hospital-muted line-clamp-2"><?= htmlspecialchars($action['description']) ?></p>
                        </div>
                        <span class="dash-action-link">
                            <span><?= htmlspecialchars($action['button_label']) ?></span>
                            <i class="bi bi-arrow-up-right transition duration-200 ease-out"></i>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($secondaryActions): ?>
                <div class="grid min-h-0 gap-3 sm:grid-cols-2 xl:grid-cols-12 xl:auto-rows-fr">
                    <?php foreach ($secondaryActions as $action): ?>
                        <a href="<?= htmlspecialchars($action['href']) ?>" class="dash-action-card group <?= htmlspecialchars($actionSpanClass(count($secondaryActions))) ?>">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-hospital-teal"><?= htmlspecialchars($action['eyebrow']) ?></span>
                                    <span class="dash-icon-badge"><i class="bi <?= htmlspecialchars($action['icon']) ?>"></i></span>
                                </div>
                                <h3 class="mt-2 font-prompt text-lg font-bold"><?= htmlspecialchars($action['title']) ?></h3>
                                <p class="mt-1 text-xs leading-5 text-hospital-muted line-clamp-2"><?= htmlspecialchars($action['description']) ?></p>
                            </div>
                            <span class="dash-action-link">
                                <span><?= htmlspecialchars($action['button_label']) ?></span>
                                <i class="bi bi-arrow-up-right transition duration-200 ease-out"></i>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($bottomCardCount > 0): ?>
        <section class="dash-summary-grid<?= app_can('can_approve_logs') ? ' has-approval' : '' ?><?= $todayIssueCount > 0 ? ' has-issue' : '' ?>">
            <?php if ($latestLog): ?>
                <article class="dash-detail-card dash-detail-card-wide">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-hospital-teal">รายการล่าสุด</p>
                            <h2 class="mt-1 font-prompt text-lg font-bold">ข้อมูลของฉัน</h2>
                        </div>
                        <a href="time.php" class="dash-btn-ghost">เปิดหน้า <i class="bi bi-arrow-right transition duration-200 ease-out"></i></a>
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-2xl bg-hospital-mist px-3 py-1.5 text-sm"><span class="block text-[0.68rem] font-semibold text-hospital-muted">วันที่</span><strong><?= htmlspecialchars($latestLogDateLabel) ?></strong></div>
                        <div class="rounded-2xl bg-hospital-mist px-3 py-1.5 text-sm"><span class="block text-[0.68rem] font-semibold text-hospital-muted">เวลา</span><strong><?= !empty($latestLog['time_in']) ? date('H:i', strtotime((string) $latestLog['time_in'])) : '-' ?> - <?= !empty($latestLog['time_out']) ? date('H:i', strtotime((string) $latestLog['time_out'])) : '-' ?></strong></div>
                        <div class="rounded-2xl bg-hospital-mist px-3 py-1.5 text-sm"><span class="block text-[0.68rem] font-semibold text-hospital-muted">สถานะ</span><strong><?= !empty($latestLog['checked_at']) ? 'ตรวจแล้ว' : 'รอตรวจ' ?></strong></div>
                    </div>
                </article>
            <?php endif; ?>

            <?php if (app_can('can_approve_logs')): ?>
                <article class="dash-detail-card dash-detail-card-side">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-hospital-teal">คิวตรวจสอบ</p>
                            <h2 class="mt-1 font-prompt text-lg font-bold">งานอนุมัติ</h2>
                            <p class="mt-1 text-sm font-semibold text-hospital-muted">รายการที่ยังรอการตรวจสอบ</p>
                        </div>
                        <div class="text-right">
                            <div class="font-prompt text-4xl font-bold tracking-[-0.05em]"><?= number_format($pendingCount) ?></div>
                            <a href="approval_queue.php" class="dash-btn-ghost mt-2"><span>เปิดหน้า</span><i class="bi bi-arrow-right transition duration-200 ease-out"></i></a>
                        </div>
                    </div>
                </article>
            <?php endif; ?>

            <?php if ($todayIssueCount > 0): ?>
                <article class="dash-detail-card dash-detail-card-side border-amber-200 bg-amber-50/90">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-amber-700">ต้องตรวจสอบ</p>
                            <h2 class="mt-1 font-prompt text-lg font-bold text-hospital-ink"><?= number_format($todayIssueCount) ?> รายการวันนี้</h2>
                            <p class="mt-1 text-xs font-semibold text-hospital-muted line-clamp-1">
                                <?php if ($todayOverlapCount > 0): ?>เวลาซ้อน <?= number_format($todayOverlapCount) ?><?php endif; ?>
                                <?php if ($todayIncompleteCount > 0): ?><?= $todayOverlapCount > 0 ? ' / ' : '' ?>เวลาไม่ครบ <?= number_format($todayIncompleteCount) ?><?php endif; ?>
                            </p>
                        </div>
                        <a href="time.php" class="dash-btn-ghost bg-white">เปิดหน้า <i class="bi bi-arrow-right transition duration-200 ease-out"></i></a>
                    </div>
                </article>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>

<script>
    (function () {
        const openButton = document.querySelector('[data-dashboard-sidebar-open]');
        const closeButton = document.querySelector('[data-dashboard-sidebar-close]');
        const drawer = document.querySelector('[data-dashboard-sidebar-drawer]');
        const backdrop = document.querySelector('[data-dashboard-sidebar-backdrop]');

        function setOpen(open) {
            if (!drawer || !backdrop) {
                return;
            }
            drawer.classList.toggle('is-open', open);
            backdrop.classList.toggle('is-open', open);
            document.body.classList.toggle('overflow-hidden', open);
        }

        openButton && openButton.addEventListener('click', function () { setOpen(true); });
        closeButton && closeButton.addEventListener('click', function () { setOpen(false); });
        backdrop && backdrop.addEventListener('click', function () { setOpen(false); });
        window.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });
    })();
</script>
</body>
</html>
