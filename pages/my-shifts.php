<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';
require_once __DIR__ . '/../includes/notification_helpers.php';
require_once __DIR__ . '/../includes/shift_schedule_service.php';
require_once __DIR__ . '/../includes/shift_swap_service.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$filter = app_my_shifts_month_filter($_GET);
$selectedMonth = (int) $filter['month'];
$selectedYear = (int) $filter['year'];
$selectedYearBe = (int) $filter['year_be'];
$view = (string) $filter['view'];
$display = (string) $filter['display'];
$monthOptions = app_get_thai_month_select_options();
$shiftTypes = app_shift_schedule_types();
$csrfToken = app_csrf_token('my_shifts_create_time_log');
$swapCsrfToken = app_csrf_token('shift_swap');
$flash = (string) ($_SESSION['my_shifts_flash'] ?? '');
$flashType = (string) ($_SESSION['my_shifts_flash_type'] ?? 'success');
unset($_SESSION['my_shifts_flash'], $_SESSION['my_shifts_flash_type']);

$currentDepartmentId = app_get_current_user_department_id($conn);
$swapSourceId = max(0, (int) ($_GET['swap_source_id'] ?? 0));
$openAssignmentId = max(0, (int) ($_GET['open_assignment_id'] ?? 0));
$swapSelectionMode = false;
$swapSourceAssignment = null;
$swapSourceError = '';
if ($swapSourceId > 0) {
    $view = 'department';
    $swapSourceAssignment = app_shift_swap_get_assignment($conn, $swapSourceId);
    if (!$swapSourceAssignment) {
        $swapSourceError = 'ไม่พบเวรต้นทางสำหรับแลกเวร';
    } else {
        try {
            app_shift_swap_assert_assignment_swappable($conn, $swapSourceAssignment, $currentUserId);
            $swapSelectionMode = true;
        } catch (Throwable $e) {
            $swapSourceError = $e->getMessage();
        }
    }
}
$myAssignments = app_get_my_shift_assignments($conn, $currentUserId, $selectedMonth, $selectedYear);
$assignments = $view === 'department' && $currentDepartmentId > 0
    ? app_get_department_shift_assignments($conn, $currentUserId, $currentDepartmentId, $selectedMonth, $selectedYear)
    : $myAssignments;
$stats = app_my_shift_stats($view === 'department' ? $myAssignments : $assignments);
$departmentStaffIds = [];
$departmentMineCount = 0;
foreach ($assignments as $assignment) {
    $departmentStaffIds[(int) ($assignment['staff_id'] ?? 0)] = true;
    if (!empty($assignment['is_mine'])) {
        $departmentMineCount++;
    }
}
$departmentTotal = count($assignments);
$departmentStaffCount = count(array_filter(array_keys($departmentStaffIds), static function (int $id): bool {
    return $id > 0;
}));
$departmentOtherCount = max(0, $departmentTotal - $departmentMineCount);
$swapEligibleCount = 0;
foreach ($assignments as &$assignment) {
    $assignment['swap_source_meta'] = my_shift_swap_source_meta($conn, $assignment, $currentUserId);
    $assignment['swap_target_meta'] = $swapSelectionMode
        ? my_shift_swap_target_meta($conn, $assignment, $currentUserId, $swapSourceAssignment)
        : ['can' => false, 'reason' => ''];
    if (!empty($assignment['swap_target_meta']['can'])) {
        $swapEligibleCount++;
    }
}
unset($assignment);
$assignmentsByDate = app_my_shifts_group_by_date($assignments);
$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth));
$daysInMonth = (int) $firstDay->format('t');
$startOffset = (int) $firstDay->format('N') - 1;
$today = date('Y-m-d');
$monthLabel = (string) ($monthOptions[$selectedMonth] ?? '');
$loggedCount = $stats['pending'] + $stats['approved'] + $stats['returned'];
$progressPercent = $stats['total'] > 0 ? round(($loggedCount / $stats['total']) * 100) : 0;

$mostShift = '-';
if ($assignments) {
    $shiftCounts = [];
    foreach ($assignments as $assignment) {
        $key = (string) ($assignment['shift_type'] ?? '');
        $shiftCounts[$key] = ($shiftCounts[$key] ?? 0) + 1;
    }
    arsort($shiftCounts);
    $topShift = (string) array_key_first($shiftCounts);
    $mostShift = $shiftTypes[$topShift]['label'] ?? $topShift ?: '-';
}
$latestShiftLabel = '-';
if ($assignments) {
    $last = $assignments[count($assignments) - 1];
    $latestShiftLabel = app_format_thai_date((string) $last['schedule_date']) . ' ' . ($shiftTypes[(string) $last['shift_type']]['label'] ?? $last['shift_type']);
}
$swapSourceSummary = $swapSourceAssignment ? app_shift_swap_assignment_summary($swapSourceAssignment) : null;
$swapCancelUrl = $swapSourceSummary
    ? my_shift_url([
        'view' => 'my',
        'display' => 'calendar',
        'swap_source_id' => 0,
        'open_assignment_id' => (int) $swapSourceSummary['assignment_id'],
    ])
    : my_shift_url(['view' => 'my', 'swap_source_id' => 0]);

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$currentUserId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$role = app_current_role();
$roleLabel = app_role_label($role);
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($userMeta ?: ['fullname' => $_SESSION['fullname'] ?? '-']);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');

function my_shift_url(array $overrides = []): string
{
    global $view, $display, $selectedMonth, $selectedYearBe, $swapSelectionMode, $swapSourceId;
    $query = array_merge([
        'month' => $selectedMonth,
        'year' => $selectedYearBe,
        'view' => $view,
        'display' => $display,
    ], $overrides);
    if ($swapSelectionMode && !array_key_exists('swap_source_id', $overrides)) {
        $query['swap_source_id'] = $swapSourceId;
    }
    if (array_key_exists('swap_source_id', $overrides) && (int) $overrides['swap_source_id'] <= 0) {
        unset($query['swap_source_id']);
    }

    return 'my-shifts.php?' . http_build_query($query);
}

function my_shift_swap_source_meta(PDO $conn, array $assignment, int $currentUserId): array
{
    $assignmentId = (int) ($assignment['assignment_id'] ?? 0);
    if (empty($assignment['is_mine'])) {
        return ['can' => false, 'reason' => 'เวรของเจ้าหน้าที่คนอื่น'];
    }
    if ($assignmentId <= 0) {
        return ['can' => false, 'reason' => 'ไม่มี assignment อ้างอิง'];
    }
    if (app_shift_swap_has_active_request($conn, [$assignmentId])) {
        return ['can' => false, 'reason' => 'รอคำขอแลกอยู่'];
    }

    $swapAssignment = app_shift_swap_get_assignment($conn, $assignmentId);
    if (!$swapAssignment) {
        return ['can' => false, 'reason' => 'ไม่พบ assignment'];
    }

    try {
        app_shift_swap_assert_assignment_swappable($conn, $swapAssignment, $currentUserId);
        return ['can' => true, 'reason' => ''];
    } catch (Throwable $e) {
        return ['can' => false, 'reason' => $e->getMessage()];
    }
}

function my_shift_swap_target_meta(PDO $conn, array $assignment, int $currentUserId, ?array $sourceAssignment): array
{
    $assignmentId = (int) ($assignment['assignment_id'] ?? 0);
    if (!$sourceAssignment) {
        return ['can' => false, 'reason' => 'ไม่มีเวรต้นทาง'];
    }
    if ($assignmentId <= 0) {
        return ['can' => false, 'reason' => 'ไม่มี assignment อ้างอิง'];
    }
    if (!empty($assignment['is_mine'])) {
        return ['can' => false, 'reason' => 'เวรของคุณ'];
    }
    if (app_shift_swap_has_active_request($conn, [$assignmentId])) {
        return ['can' => false, 'reason' => 'รอคำขอแลก'];
    }

    $targetAssignment = app_shift_swap_get_assignment($conn, $assignmentId);
    if (!$targetAssignment) {
        return ['can' => false, 'reason' => 'ไม่พบ assignment'];
    }

    try {
        app_shift_swap_assert_assignment_swappable($conn, $targetAssignment);
        app_shift_swap_revalidate_pair($conn, $sourceAssignment, $targetAssignment);
        return ['can' => true, 'reason' => 'แลกได้'];
    } catch (Throwable $e) {
        return ['can' => false, 'reason' => $e->getMessage()];
    }
}

function my_shift_modal_payload(array $assignment, array $shiftTypes): string
{
    global $selectedMonth, $selectedYearBe;
    $statusMeta = $assignment['status_meta'] ?? app_my_shift_status_meta($assignment);
    $isMine = !empty($assignment['is_mine']);
    $swapSourceMeta = $assignment['swap_source_meta'] ?? ['can' => false, 'reason' => ''];
    $payload = [
        'assignmentId' => (int) $assignment['assignment_id'],
        'scheduleId' => (int) $assignment['schedule_id'],
        'staffId' => (int) ($assignment['staff_id'] ?? 0),
        'staffName' => (string) ($assignment['staff_name'] ?? ''),
        'isMine' => $isMine,
        'date' => app_format_thai_date((string) $assignment['schedule_date'], true),
        'dateRaw' => (string) $assignment['schedule_date'],
        'shiftLabel' => (string) ($shiftTypes[(string) $assignment['shift_type']]['label'] ?? $assignment['shift_type']),
        'timeRange' => (string) $assignment['start_time_label'] . '-' . (string) $assignment['end_time_label'],
        'hours' => number_format((float) $assignment['planned_hours'], 2),
        'department' => (string) ($assignment['department_name'] ?? '-'),
        'roleNote' => (string) ($assignment['role_note'] ?? ''),
        'scheduleNote' => (string) ($assignment['schedule_note'] ?? ''),
        'statusLabel' => (string) ($statusMeta['label'] ?? 'ยังไม่ลงเวร'),
        'statusKey' => (string) ($statusMeta['key'] ?? 'not_logged'),
        'timeLogId' => (int) ($assignment['time_log_id'] ?? 0),
        'timeLogStatus' => (string) ($assignment['time_log_status'] ?? ''),
        'timeLogHours' => isset($assignment['time_log_total_hours']) ? number_format((float) $assignment['time_log_total_hours'], 2) : '',
        'source' => (string) ($assignment['time_log_source'] ?? ''),
        'note' => (string) ($assignment['time_log_note'] ?? ''),
        'canRequestSwap' => !empty($swapSourceMeta['can']),
        'swapBlockedReason' => (string) ($swapSourceMeta['reason'] ?? ''),
        'swapUrl' => 'my-shifts.php?' . http_build_query([
            'month' => $selectedMonth,
            'year' => $selectedYearBe,
            'view' => 'department',
            'display' => 'calendar',
            'swap_source_id' => (int) $assignment['assignment_id'],
        ]),
    ];

    return htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เวรของฉัน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell my-shifts-page-shell">
<?php render_dashboard_sidebar('my-shifts.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main my-shifts-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Personal Shift Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">เวรของฉัน</h1>
        </div>
        <?php render_notification_bell(); ?>
        <button type="button" class="dash-profile-button" data-profile-modal-trigger data-user-id="<?= $currentUserId ?>">
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

    <div class="my-shifts-frame">
        <?php if ($flash !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($flashType) ?> rounded-4 border-0 shadow-sm" role="alert">
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>
        <?php if ($swapSourceError !== ''): ?>
            <div class="alert alert-warning rounded-4 border-0 shadow-sm" role="alert">
                <?= htmlspecialchars($swapSourceError) ?>
            </div>
        <?php endif; ?>

        <section class="my-shifts-hero">
            <div class="my-shifts-hero-copy">
                <span class="dash-hero-pill"><i class="bi bi-person-lines-fill"></i> My Monthly Schedule</span>
                <h2>เวรของฉัน</h2>
                <p>ตารางเวรรายเดือนของคุณ และการลงเวรตามแผนที่ได้รับมอบหมาย</p>
            </div>
            <div class="my-shifts-stats">
                <div><span>เวรทั้งหมด</span><strong><?= number_format($stats['total']) ?></strong></div>
                <div><span>ยังไม่ลงเวร</span><strong><?= number_format($stats['not_logged']) ?></strong></div>
                <div><span>รอตรวจ</span><strong><?= number_format($stats['pending']) ?></strong></div>
                <div><span>ตรวจแล้ว</span><strong><?= number_format($stats['approved']) ?></strong></div>
                <div><span>ตีกลับ</span><strong><?= number_format($stats['returned']) ?></strong></div>
            </div>
        </section>

        <?php if ($view === 'department'): ?>
            <section class="my-shifts-department-summary" aria-label="department shift summary">
                <div><span>เวรทั้งแผนก</span><strong><?= number_format($departmentTotal) ?></strong></div>
                <div><span>เจ้าหน้าที่ที่มีเวร</span><strong><?= number_format($departmentStaffCount) ?></strong></div>
                <div><span>เวรของฉัน</span><strong><?= number_format($departmentMineCount) ?></strong></div>
                <div><span>เวรคนอื่น</span><strong><?= number_format($departmentOtherCount) ?></strong></div>
            </section>
        <?php endif; ?>

        <section class="my-shifts-toolbar">
            <form method="get" class="my-shifts-filter-form">
                <label>
                    <span>เดือน</span>
                    <select name="month" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($monthOptions as $value => $label): ?>
                            <option value="<?= (int) $value ?>" <?= (int) $value === $selectedMonth ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>ปี พ.ศ.</span>
                    <input type="number" name="year" min="2543" max="2643" class="form-control" value="<?= (int) $selectedYearBe ?>">
                </label>
                <div class="my-shifts-view-toggle" role="group" aria-label="เลือกมุมมอง">
                    <a href="<?= htmlspecialchars(my_shift_url(['view' => 'my'])) ?>" class="<?= $view === 'my' ? 'is-active' : '' ?>"><i class="bi bi-person-check"></i> เวรของฉัน</a>
                    <a href="<?= htmlspecialchars(my_shift_url(['view' => 'department'])) ?>" class="<?= $view === 'department' ? 'is-active' : '' ?>"><i class="bi bi-people"></i> เวรในแผนก</a>
                </div>
                <div class="my-shifts-view-toggle" role="group" aria-label="display mode">
                    <a href="<?= htmlspecialchars(my_shift_url(['display' => 'calendar'])) ?>" class="<?= $display === 'calendar' ? 'is-active' : '' ?>"><i class="bi bi-calendar3"></i> Calendar</a>
                    <a href="<?= htmlspecialchars(my_shift_url(['display' => 'list'])) ?>" class="<?= $display === 'list' ? 'is-active' : '' ?>"><i class="bi bi-list-check"></i> List</a>
                </div>
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <input type="hidden" name="display" value="<?= htmlspecialchars($display) ?>">
                <?php if ($swapSelectionMode): ?>
                    <input type="hidden" name="swap_source_id" value="<?= (int) $swapSourceId ?>">
                <?php endif; ?>
                <button type="submit" class="dash-btn dash-btn-ghost my-shifts-refresh-btn" title="รีเฟรช"><i class="bi bi-arrow-clockwise"></i></button>
            </form>
            <div class="my-shifts-summary-strip">
                <div><span>เดือนที่เลือก</span><strong><?= htmlspecialchars($monthLabel) ?> <?= (int) $selectedYearBe ?></strong></div>
                <div><span>กะที่มากที่สุด</span><strong><?= htmlspecialchars($mostShift) ?></strong></div>
                <div><span>เวรล่าสุด</span><strong><?= htmlspecialchars($latestShiftLabel) ?></strong></div>
                <div class="my-shifts-progress">
                    <span>ความคืบหน้า</span>
                    <strong><?= (int) $progressPercent ?>%</strong>
                    <div><span style="width: <?= (int) $progressPercent ?>%"></span></div>
                </div>
            </div>
        </section>

        <?php if ($swapSelectionMode && $swapSourceSummary): ?>
            <section class="my-shifts-swap-banner" aria-label="เลือกเวรที่ต้องการแลก">
                <div class="my-shifts-swap-banner-copy">
                    <span><i class="bi bi-arrow-left-right"></i> เลือกเวรที่ต้องการแลก</span>
                    <h3>เลือกเวรในแผนกที่ต้องการแลกกับเวรของคุณ</h3>
                    <p>
                        เวรต้นทาง:
                        <?= htmlspecialchars(app_format_thai_date((string) $swapSourceSummary['date'])) ?>
                        · <?= htmlspecialchars((string) $swapSourceSummary['shift_label']) ?>
                        · <?= htmlspecialchars((string) $swapSourceSummary['time']) ?>
                        · <?= htmlspecialchars((string) $swapSourceSummary['department_name']) ?>
                    </p>
                </div>
                <div class="my-shifts-swap-banner-actions">
                    <span class="my-shifts-swap-count"><?= number_format($swapEligibleCount) ?> เวรที่ขอแลกได้</span>
                    <a class="dash-btn dash-btn-ghost" href="<?= htmlspecialchars($swapCancelUrl) ?>"><i class="bi bi-x-circle"></i> ยกเลิกแลกเวร</a>
                </div>
                <?php if ($swapEligibleCount <= 0): ?>
                    <div class="my-shifts-swap-empty">ไม่มีเวรในแผนกที่สามารถขอแลกได้ในช่วงเวลานี้</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$assignments): ?>
            <section class="my-shifts-empty">
                <i class="bi bi-calendar2-x"></i>
                <strong>ยังไม่มีเวรที่ได้รับมอบหมายในเดือนนี้</strong>
                <span>เมื่อหัวหน้าแผนกเผยแพร่แผนเวร รายการของคุณจะแสดงที่นี่</span>
            </section>
        <?php elseif ($display === 'calendar'): ?>
            <section class="my-shifts-calendar" aria-label="Calendar view เวรของฉัน">
                <?php foreach (['จันทร์', 'อังคาร', 'พุธ', 'พฤหัส', 'ศุกร์', 'เสาร์', 'อาทิตย์'] as $weekday): ?>
                    <div class="my-shifts-weekday"><?= htmlspecialchars($weekday) ?></div>
                <?php endforeach; ?>
                <?php for ($blank = 0; $blank < $startOffset; $blank++): ?>
                    <div class="my-shifts-day is-empty"></div>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                    $date = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
                    $dayAssignments = $assignmentsByDate[$date] ?? [];
                    $dayHasMine = (bool) array_filter($dayAssignments, static function (array $assignment): bool {
                        return !empty($assignment['is_mine']);
                    });
                    $visibleDayAssignments = array_slice($dayAssignments, 0, 3);
                    $hiddenDayAssignmentCount = max(0, count($dayAssignments) - count($visibleDayAssignments));
                    ?>
                    <article class="my-shifts-day <?= $date === $today ? 'is-today' : '' ?> <?= $dayAssignments ? 'has-shift' : '' ?> <?= $dayHasMine ? 'has-my-shift' : '' ?>">
                        <div class="my-shifts-day-head">
                            <strong><?= (int) $day ?></strong>
                            <?php if ($date === $today): ?><span>วันนี้</span><?php endif; ?>
                        </div>
                        <?php if (!$dayAssignments): ?>
                            <div class="my-shifts-day-empty">ไม่มีเวร</div>
                        <?php endif; ?>
                        <?php foreach ($visibleDayAssignments as $assignment): ?>
                            <?php
                            $statusMeta = $assignment['status_meta'];
                            $swapTargetMeta = $assignment['swap_target_meta'] ?? ['can' => false, 'reason' => ''];
                            $swapClass = $swapSelectionMode
                                ? (!empty($swapTargetMeta['can']) ? 'is-swap-eligible' : 'is-swap-unavailable')
                                : '';
                            ?>
                            <button type="button" class="my-shift-pill is-<?= htmlspecialchars($statusMeta['class']) ?> <?= !empty($assignment['is_mine']) ? 'is-mine' : 'is-other' ?> <?= htmlspecialchars($swapClass) ?>" data-assignment-id="<?= (int) $assignment['assignment_id'] ?>" data-my-shift-open data-shift='<?= my_shift_modal_payload($assignment, $shiftTypes) ?>'>
                                <span><?= htmlspecialchars($shiftTypes[(string) $assignment['shift_type']]['label'] ?? $assignment['shift_type']) ?></span>
                                <?php if ($view === 'department'): ?>
                                    <b><?= !empty($assignment['is_mine']) ? 'ของฉัน' : htmlspecialchars((string) ($assignment['staff_name'] ?? '-')) ?></b>
                                <?php endif; ?>
                                <small><?= htmlspecialchars($assignment['start_time_label']) ?>-<?= htmlspecialchars($assignment['end_time_label']) ?></small>
                                <em><?= htmlspecialchars($statusMeta['label']) ?></em>
                            </button>
                            <?php if ($swapSelectionMode): ?>
                                <?php if (!empty($swapTargetMeta['can'])): ?>
                                    <form method="post" action="../actions/create-shift-swap-request.php" class="my-shift-swap-action">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($swapCsrfToken) ?>">
                                        <input type="hidden" name="requester_assignment_id" value="<?= (int) $swapSourceId ?>">
                                        <input type="hidden" name="target_assignment_id" value="<?= (int) $assignment['assignment_id'] ?>">
                                        <input type="hidden" name="reason" value="ขอแลกเวรจากหน้าเวรของฉัน">
                                        <input type="hidden" name="return_to" value="my-shifts">
                                        <input type="hidden" name="month" value="<?= (int) $selectedMonth ?>">
                                        <input type="hidden" name="year" value="<?= (int) $selectedYearBe ?>">
                                        <input type="hidden" name="display" value="<?= htmlspecialchars($display) ?>">
                                        <button type="submit"><i class="bi bi-arrow-left-right"></i> ขอแลกเวร</button>
                                    </form>
                                <?php else: ?>
                                    <div class="my-shift-swap-reason"><?= htmlspecialchars((string) ($swapTargetMeta['reason'] ?? 'ไม่สามารถแลกได้')) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($hiddenDayAssignmentCount > 0): ?>
                            <div class="my-shift-overflow">+<?= (int) $hiddenDayAssignmentCount ?> รายการในวันนี้</div>
                        <?php endif; ?>
                    </article>
                <?php endfor; ?>
            </section>
        <?php else: ?>
            <section class="my-shifts-list" aria-label="List view เวรของฉัน">
                <div class="my-shifts-list-head">
                    <span>วันที่</span>
                    <span>กะ</span>
                    <span>เวลา</span>
                    <span>ชั่วโมง</span>
                    <span>แผนก</span>
                    <span>สถานะ</span>
                    <span>การจัดการ</span>
                </div>
                <?php foreach ($assignments as $assignment): ?>
                    <?php $statusMeta = $assignment['status_meta']; ?>
                    <article class="my-shifts-list-row <?= !empty($assignment['is_mine']) ? 'is-mine' : 'is-other' ?>">
                        <div data-label="วันที่"><strong><?= htmlspecialchars(app_format_thai_date((string) $assignment['schedule_date'])) ?></strong></div>
                        <div data-label="กะ"><?= htmlspecialchars($shiftTypes[(string) $assignment['shift_type']]['label'] ?? $assignment['shift_type']) ?></div>
                        <div data-label="เวลา"><?= htmlspecialchars($assignment['start_time_label']) ?>-<?= htmlspecialchars($assignment['end_time_label']) ?></div>
                        <div data-label="ชั่วโมง"><?= number_format((float) $assignment['planned_hours'], 2) ?></div>
                        <div data-label="แผนก"><?= htmlspecialchars($assignment['department_name'] ?? '-') ?></div>
                        <div data-label="สถานะ"><span class="my-shift-status is-<?= htmlspecialchars($statusMeta['class']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span></div>
                        <div data-label="การจัดการ">
                            <button type="button" class="dash-btn <?= empty($assignment['time_log_id']) && !empty($assignment['is_mine']) ? 'dash-btn-primary' : 'dash-btn-secondary' ?>" data-assignment-id="<?= (int) $assignment['assignment_id'] ?>" data-my-shift-open data-shift='<?= my_shift_modal_payload($assignment, $shiftTypes) ?>'>
                                <i class="bi <?= empty($assignment['time_log_id']) && !empty($assignment['is_mine']) ? 'bi-box-arrow-in-right' : 'bi-eye' ?>"></i>
                                <?= empty($assignment['time_log_id']) && !empty($assignment['is_mine']) ? 'ลงเวร' : 'ดูรายละเอียด' ?>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<div class="my-shift-modal-backdrop" data-my-shift-modal hidden>
    <div class="my-shift-modal" role="dialog" aria-modal="true" aria-labelledby="myShiftModalTitle">
        <form method="post" action="../actions/create-time-log-from-assignment.php" data-my-shift-form>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="assignment_id" value="" data-modal-assignment-id>
            <input type="hidden" name="month" value="<?= (int) $selectedMonth ?>">
            <input type="hidden" name="year" value="<?= (int) $selectedYearBe ?>">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <input type="hidden" name="display" value="<?= htmlspecialchars($display) ?>">
            <div class="my-shift-modal-head">
                <div>
                    <p>ลงเวรตามแผน</p>
                    <h3 id="myShiftModalTitle" data-modal-title>รายละเอียดเวร</h3>
                </div>
                <button type="button" class="dash-icon-button" data-my-shift-close aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="my-shift-modal-grid">
                <div><span>วันที่</span><strong data-modal-date>-</strong></div>
                <div><span>กะ</span><strong data-modal-shift>-</strong></div>
                <div><span>เวลา</span><strong data-modal-time>-</strong></div>
                <div><span>ชั่วโมงรวม</span><strong data-modal-hours>-</strong></div>
                <div><span>แผนก</span><strong data-modal-department>-</strong></div>
                <div><span>สถานะ</span><strong data-modal-status>-</strong></div>
            </div>
            <div class="my-shift-modal-note">
                <span>รายละเอียด</span>
                <p data-modal-description>-</p>
            </div>
            <label class="my-shift-note-input" data-modal-note-wrap>
                <span>หมายเหตุการลงเวร</span>
                <textarea name="note" rows="3" class="form-control" placeholder="ระบุหมายเหตุเพิ่มเติมถ้ามี"></textarea>
            </label>
            <div class="my-shift-modal-actions">
                <button type="button" class="dash-btn dash-btn-ghost" data-my-shift-close>ยกเลิก</button>
                <a class="dash-btn dash-btn-secondary my-shift-swap-start" href="#" data-modal-swap hidden><i class="bi bi-arrow-left-right"></i> แลกเวร</a>
                <span class="my-shift-swap-pending" data-modal-swap-status hidden>รอคำขอแลกอยู่</span>
                <button type="submit" class="dash-btn dash-btn-primary" data-modal-submit><i class="bi bi-check2-circle"></i> ยืนยันลงเวร</button>
            </div>
        </form>
    </div>
</div>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/notifications.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script>
(() => {
    const modal = document.querySelector('[data-my-shift-modal]');
    const form = document.querySelector('[data-my-shift-form]');
    const submit = document.querySelector('[data-modal-submit]');
    const noteWrap = document.querySelector('[data-modal-note-wrap]');
    const swapLink = document.querySelector('[data-modal-swap]');
    const swapStatus = document.querySelector('[data-modal-swap-status]');
    const openAssignmentId = <?= (int) $openAssignmentId ?>;
    const fields = {
        assignmentId: document.querySelector('[data-modal-assignment-id]'),
        title: document.querySelector('[data-modal-title]'),
        date: document.querySelector('[data-modal-date]'),
        shift: document.querySelector('[data-modal-shift]'),
        time: document.querySelector('[data-modal-time]'),
        hours: document.querySelector('[data-modal-hours]'),
        department: document.querySelector('[data-modal-department]'),
        status: document.querySelector('[data-modal-status]'),
        description: document.querySelector('[data-modal-description]')
    };

    document.querySelectorAll('[data-my-shift-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal) return;
            const payload = JSON.parse(button.dataset.shift || '{}');
            fields.assignmentId.value = payload.assignmentId || '';
            fields.title.textContent = payload.timeLogId ? 'รายละเอียดเวรที่ลงแล้ว' : 'ลงเวรตามแผน';
            fields.date.textContent = payload.date || '-';
            fields.shift.textContent = payload.shiftLabel || '-';
            fields.time.textContent = payload.timeRange || '-';
            fields.hours.textContent = `${payload.hours || '0.00'} ชม.`;
            fields.department.textContent = payload.department || '-';
            fields.status.textContent = payload.statusLabel || '-';
            if (!payload.isMine) {
                fields.title.textContent = 'รายละเอียดเวรเจ้าหน้าที่';
            }
            const details = [
                payload.staffName ? `เจ้าหน้าที่: ${payload.staffName}` : '',
                payload.roleNote ? `หน้าที่: ${payload.roleNote}` : '',
                payload.scheduleNote ? `หมายเหตุแผนเวร: ${payload.scheduleNote}` : '',
                payload.timeLogId ? `time_logs.id: ${payload.timeLogId}` : '',
                payload.source ? `source: ${payload.source}` : '',
                payload.note ? `หมายเหตุลงเวร: ${payload.note}` : ''
            ].filter(Boolean);
            fields.description.textContent = details.length ? details.join(' | ') : 'ไม่มีรายละเอียดเพิ่มเติม';

            const canCreate = !payload.timeLogId && payload.isMine;
            if (submit) submit.hidden = !canCreate;
            if (noteWrap) noteWrap.hidden = !canCreate;
            if (swapLink) {
                swapLink.hidden = !payload.canRequestSwap;
                swapLink.href = payload.swapUrl || '#';
            }
            if (swapStatus) {
                const hasSwapStatus = payload.isMine && !payload.canRequestSwap && payload.swapBlockedReason === 'รอคำขอแลกอยู่';
                swapStatus.hidden = !hasSwapStatus;
                swapStatus.textContent = payload.swapBlockedReason || '';
            }
            modal.hidden = false;
            document.body.classList.add('overflow-hidden');
        });
    });

    if (openAssignmentId > 0) {
        document.querySelector(`[data-my-shift-open][data-assignment-id="${openAssignmentId}"]`)?.click();
    }

    document.querySelectorAll('[data-my-shift-close]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal) return;
            modal.hidden = true;
            document.body.classList.remove('overflow-hidden');
        });
    });

    form?.addEventListener('submit', () => {
        if (!submit) return;
        submit.disabled = true;
        submit.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังบันทึก...';
    });
})();

// Auto-submit on year input change (debounced 700 ms)
var _myShiftsYearTimer;
(function () {
    var yearInput = document.querySelector('.my-shifts-filter-form [name="year"]');
    if (!yearInput) return;
    yearInput.addEventListener('input', function () {
        clearTimeout(_myShiftsYearTimer);
        _myShiftsYearTimer = setTimeout(function () {
            document.querySelector('.my-shifts-filter-form').submit();
        }, 700);
    });
}());
</script>
</body>
</html>
