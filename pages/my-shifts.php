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
$profileSignaturePath = trim((string) ($userMeta['signature_path'] ?? ''));
$profileSignatureSrc = $profileSignaturePath !== ''
    ? '../uploads/signatures/' . rawurlencode($profileSignaturePath)
    : '';
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

function my_shift_type_class(string $shiftType): string
{
    return match ($shiftType) {
        'morning' => 'morning',
        'evening', 'afternoon' => 'afternoon',
        'night' => 'night',
        default => 'other',
    };
}

function my_shift_day_detail_payload(string $date, array $assignments, array $shiftTypes): string
{
    $groups = [];
    $departments = [];

    foreach ($assignments as $assignment) {
        $shiftType = (string) ($assignment['shift_type'] ?? 'custom');
        $shiftLabel = (string) ($shiftTypes[$shiftType]['label'] ?? $shiftType);
        $shiftClass = my_shift_type_class($shiftType);
        $groupKey = $shiftClass . '|' . $shiftType;
        $statusMeta = $assignment['status_meta'] ?? app_my_shift_status_meta($assignment);
        $department = (string) ($assignment['department_name'] ?? '-');

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'shiftType' => $shiftType,
                'shiftClass' => $shiftClass,
                'shiftLabel' => $shiftLabel,
                'items' => [],
            ];
        }

        if ($department !== '') {
            $departments[$department] = true;
        }

        $groups[$groupKey]['items'][] = [
            'assignmentId' => (int) ($assignment['assignment_id'] ?? 0),
            'staffId' => (int) ($assignment['staff_id'] ?? 0),
            'staffName' => (string) ($assignment['staff_name'] ?? '-'),
            'staffPosition' => (string) ($assignment['staff_position'] ?? ''),
            'department' => $department,
            'avatarUrl' => (string) ($assignment['staff_profile_image_url'] ?? ''),
            'initials' => (string) ($assignment['staff_initials'] ?? app_shift_staff_initials((string) ($assignment['staff_name'] ?? ''))),
            'timeRange' => (string) ($assignment['start_time_label'] ?? '') . '-' . (string) ($assignment['end_time_label'] ?? ''),
            'statusLabel' => (string) ($statusMeta['label'] ?? ''),
            'isMine' => !empty($assignment['is_mine']),
        ];
    }

    $payload = [
        'date' => app_format_thai_date($date, true),
        'dateRaw' => $date,
        'departments' => array_keys($departments),
        'groups' => array_values($groups),
    ];

    return htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}

function my_shift_modal_payload(array $assignment, array $shiftTypes): string
{
    global $selectedMonth, $selectedYearBe;
    $statusMeta = $assignment['status_meta'] ?? app_my_shift_status_meta($assignment);
    $isMine = !empty($assignment['is_mine']);
    $swapSourceMeta = $assignment['swap_source_meta'] ?? ['can' => false, 'reason' => ''];
    $shiftType = (string) ($assignment['shift_type'] ?? 'custom');
    $payload = [
        'assignmentId' => (int) $assignment['assignment_id'],
        'scheduleId' => (int) $assignment['schedule_id'],
        'staffId' => (int) ($assignment['staff_id'] ?? 0),
        'staffName' => (string) ($assignment['staff_name'] ?? ''),
        'staffPosition' => (string) ($assignment['staff_position'] ?? ''),
        'staffInitials' => (string) ($assignment['staff_initials'] ?? app_shift_staff_initials((string) ($assignment['staff_name'] ?? ''))),
        'staffAvatarUrl' => (string) ($assignment['staff_profile_image_url'] ?? ''),
        'isMine' => $isMine,
        'date' => app_format_thai_date((string) $assignment['schedule_date'], true),
        'dateRaw' => (string) $assignment['schedule_date'],
        'shiftType' => $shiftType,
        'shiftClass' => my_shift_type_class($shiftType),
        'shiftLabel' => (string) ($shiftTypes[$shiftType]['label'] ?? $shiftType),
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
                    ?>
                    <article class="my-shifts-day <?= $date === $today ? 'is-today' : '' ?> <?= $dayAssignments ? 'has-shift' : '' ?> <?= $dayHasMine ? 'has-my-shift' : '' ?>">
                        <div class="my-shifts-day-head">
                            <strong><?= (int) $day ?></strong>
                            <div class="my-shifts-day-actions">
                                <?php if ($date === $today): ?><span>วันนี้</span><?php endif; ?>
                                <?php if ($dayAssignments): ?>
                                    <button type="button" class="my-shifts-day-detail" data-my-shift-day-open data-day-shifts='<?= my_shift_day_detail_payload($date, $dayAssignments, $shiftTypes) ?>'>รายละเอียด</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$dayAssignments): ?>
                            <div class="my-shifts-day-empty">ไม่มีเวร</div>
                        <?php endif; ?>
                        <?php if ($dayAssignments): ?>
                        <div class="my-shifts-day-scroll">
                        <?php foreach ($dayAssignments as $assignment): ?>
                            <?php
                            $statusMeta = $assignment['status_meta'];
                            $shiftType = (string) ($assignment['shift_type'] ?? 'custom');
                            $shiftLabel = (string) ($shiftTypes[$shiftType]['label'] ?? $shiftType);
                            $shiftClass = my_shift_type_class($shiftType);
                            $swapTargetMeta = $assignment['swap_target_meta'] ?? ['can' => false, 'reason' => ''];
                            $swapClass = $swapSelectionMode
                                ? (!empty($swapTargetMeta['can']) ? 'is-swap-eligible' : 'is-swap-unavailable')
                                : '';
                            ?>
                            <button type="button" class="my-shift-pill is-shift-<?= htmlspecialchars($shiftClass) ?> is-<?= htmlspecialchars($statusMeta['class']) ?> <?= !empty($assignment['is_mine']) ? 'is-mine' : 'is-other' ?> <?= htmlspecialchars($swapClass) ?>" data-assignment-id="<?= (int) $assignment['assignment_id'] ?>" data-my-shift-open data-shift='<?= my_shift_modal_payload($assignment, $shiftTypes) ?>' aria-describedby="myShiftFloatingTooltip">
                                <span class="my-shift-pill-top">
                                    <span class="my-shift-pill-shift"><?= htmlspecialchars($shiftLabel) ?></span>
                                    <?php if (!empty($assignment['is_mine'])): ?>
                                        <b>ของฉัน</b>
                                    <?php endif; ?>
                                </span>
                                <span class="my-shift-pill-staff"><?= htmlspecialchars((string) ($assignment['staff_name'] ?? '-')) ?></span>
                                <small><?= htmlspecialchars($assignment['start_time_label']) ?>-<?= htmlspecialchars($assignment['end_time_label']) ?></small>
                                <em><?= htmlspecialchars($statusMeta['label']) ?></em>
                            </button>
                            <?php if ($swapSelectionMode): ?>
                                <?php if (!empty($swapTargetMeta['can'])): ?>
                                    <?php
                                    // Build target payload for JS confirm modal
                                    $targetPayloadJson = htmlspecialchars(json_encode([
                                        'assignmentId'   => (int) $assignment['assignment_id'],
                                        'staffName'      => (string) ($assignment['staff_name'] ?? ''),
                                        'date'           => app_format_thai_date((string) $assignment['schedule_date'], true),
                                        'dateRaw'        => (string) $assignment['schedule_date'],
                                        'shiftLabel'     => (string) ($shiftTypes[(string) $assignment['shift_type']]['label'] ?? $assignment['shift_type']),
                                        'timeRange'      => (string) $assignment['start_time_label'] . '-' . (string) $assignment['end_time_label'],
                                        'hours'          => number_format((float) $assignment['planned_hours'], 2),
                                        'department'     => (string) ($assignment['department_name'] ?? '-'),
                                        'statusLabel'    => (string) ($statusMeta['label'] ?? ''),
                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="my-shift-swap-action">
                                        <button type="button"
                                                class="my-shift-swap-request-btn"
                                                data-swap-request
                                                data-target='<?= $targetPayloadJson ?>'
                                                data-csrf="<?= htmlspecialchars($swapCsrfToken) ?>"
                                                data-source-id="<?= (int) $swapSourceId ?>"
                                                data-month="<?= (int) $selectedMonth ?>"
                                                data-year="<?= (int) $selectedYearBe ?>"
                                                data-display="<?= htmlspecialchars($display) ?>">
                                            <i class="bi bi-arrow-left-right"></i> ขอแลกเวร
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php
                                    $reason = (string) ($swapTargetMeta['reason'] ?? 'ไม่สามารถแลกได้');
                                    $badgeClass = match(true) {
                                        str_contains($reason, 'ย้อนหลัง') || str_contains($reason, 'เลย') => 'is-past',
                                        str_contains($reason, 'ลงเวร')   => 'is-logged',
                                        str_contains($reason, 'รอคำขอ') => 'is-pending-swap',
                                        str_contains($reason, 'ของคุณ') => 'is-own',
                                        default                           => '',
                                    };
                                    ?>
                                    <div class="my-shift-swap-badge <?= htmlspecialchars($badgeClass) ?>">
                                        <i class="bi bi-slash-circle"></i>
                                        <?= htmlspecialchars($reason) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </div><!-- /.my-shifts-day-scroll -->
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
                    <?php
                    $statusMeta = $assignment['status_meta'];
                    $shiftClass = my_shift_type_class((string) ($assignment['shift_type'] ?? 'custom'));
                    ?>
                    <article class="my-shifts-list-row is-shift-<?= htmlspecialchars($shiftClass) ?> <?= !empty($assignment['is_mine']) ? 'is-mine' : 'is-other' ?>">
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
        <form method="post" action="../actions/create-time-log-from-assignment.php" data-my-shift-form data-global-loading-form data-loading-message="โปรดรอสักครู่..." data-loading-sub-message="กำลังยืนยันการลงเวรของคุณ" data-loading-busy-text="กำลังบันทึก...">
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
            <section class="my-shift-modal-section">
                <div class="my-shift-modal-section-head">
                    <span>ข้อมูลเวร</span>
                    <p>ตรวจสอบวันที่ เวลา แผนก และสถานะของเวรนี้ก่อนดำเนินการ</p>
                </div>
                <div class="my-shift-modal-grid">
                    <div><span>วันที่</span><strong data-modal-date>-</strong></div>
                    <div><span>กะ</span><strong data-modal-shift>-</strong></div>
                    <div><span>เวลา</span><strong data-modal-time>-</strong></div>
                    <div><span>ชั่วโมงรวม</span><strong data-modal-hours>-</strong></div>
                    <div><span>แผนก</span><strong data-modal-department>-</strong></div>
                    <div><span>สถานะ</span><strong data-modal-status>-</strong></div>
                </div>
            </section>

            <section class="my-shift-modal-section">
                <div class="my-shift-modal-section-head">
                    <span>หมายเหตุ</span>
                    <p>ข้อมูลประกอบจากแผนเวรและการลงเวรเดิม</p>
                </div>
                <div class="my-shift-modal-note">
                    <span>รายละเอียด</span>
                    <p data-modal-description>-</p>
                </div>
            </section>

            <section class="my-shift-modal-section my-shift-action-stack" aria-label="การดำเนินการกับเวร">
                <article class="my-shift-action-card is-confirm" data-modal-confirm-group>
                    <div>
                        <span class="my-shift-action-kicker">การยืนยันลงเวร</span>
                        <h4>ยืนยันลงเวรตามแผน</h4>
                        <p>ใช้เมื่อคุณต้องการยืนยันเวรนี้ตามแผนที่ได้รับมอบหมาย</p>
                    </div>
                    <label class="my-shift-note-input" data-modal-note-wrap>
                        <span>หมายเหตุการลงเวร</span>
                        <textarea name="note" rows="3" class="form-control" placeholder="ระบุหมายเหตุเพิ่มเติมถ้ามี"></textarea>
                    </label>
                    <button type="submit" class="dash-btn dash-btn-primary my-shift-confirm-submit" data-modal-submit><i class="bi bi-check2-circle"></i> ยืนยันลงเวร</button>
                </article>

                <article class="my-shift-action-card is-swap" data-modal-swap-group>
                    <div>
                        <span class="my-shift-action-kicker">การจัดการเวร</span>
                        <h4>แลกเวร</h4>
                        <p>ใช้เมื่อต้องการเสนอแลกเวรกับเจ้าหน้าที่ในแผนก ระบบจะยังไม่สลับเวรจนกว่าจะอนุมัติครบขั้นตอน</p>
                    </div>
                    <a class="dash-btn my-shift-swap-start" href="#" data-modal-swap hidden><i class="bi bi-arrow-left-right"></i> แลกเวร</a>
                    <span class="my-shift-swap-pending" data-modal-swap-status hidden>รอคำขอแลกอยู่</span>
                </article>

                <article class="my-shift-action-card is-substitute" aria-disabled="true">
                    <div>
                        <span class="my-shift-action-kicker">การแทนเวร</span>
                        <h4>แทนเวร</h4>
                        <p>ฟังก์ชันนี้เตรียมไว้สำหรับการให้เจ้าหน้าที่คนอื่นแทนเวร และจะเปิดใช้งานในอนาคต</p>
                    </div>
                    <button type="button" class="dash-btn my-shift-substitute-soon" disabled><i class="bi bi-clock-history"></i> ยังไม่เปิดใช้งาน</button>
                </article>
            </section>

            <div class="my-shift-modal-actions">
                <button type="button" class="dash-btn dash-btn-ghost" data-my-shift-close>ปิดหน้าต่าง</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Confirm Swap Modal ──────────────────────────────────────────────── -->
<div class="my-shift-day-modal-backdrop" data-my-shift-day-modal hidden>
    <div class="my-shift-day-modal" role="dialog" aria-modal="true" aria-labelledby="myShiftDayModalTitle">
        <div class="my-shift-modal-head">
            <div>
                <p>Daily Shift Detail</p>
                <h3 id="myShiftDayModalTitle">ตารางเวรประจำวัน</h3>
                <span class="my-shift-day-modal-date" data-day-modal-date>-</span>
            </div>
            <button type="button" class="dash-icon-button" data-my-shift-day-close aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="my-shift-day-modal-meta">
            <span><i class="bi bi-building"></i> <strong data-day-modal-departments>-</strong></span>
        </div>
        <div class="my-shift-day-modal-body" data-day-modal-body></div>
    </div>
</div>

<div class="my-shift-floating-tooltip" id="myShiftFloatingTooltip" role="tooltip" data-shift-floating-tooltip hidden></div>

<style>
    .swap-document-signature-block{margin-top:1rem;display:grid;gap:.75rem;border:1px solid #cfe7e9;border-radius:1.25rem;background:#f8fcfd;padding:1rem;color:#334155}
    .swap-document-signature-block strong{display:block;color:#082b45;font-weight:800}
    .swap-document-signature-block span{display:block;color:#64748b;font-size:.85rem;font-weight:600}
    .swap-document-profile-toggle{display:inline-flex;min-height:2.75rem;align-items:center;justify-content:center;gap:.5rem;border:1px solid #cfe7e9;border-radius:999px;background:#fff;color:#063b4f;padding:.6rem 1rem;font-weight:800;transition:background-color .2s ease,border-color .2s ease,color .2s ease,box-shadow .2s ease}
    .swap-document-profile-toggle[aria-pressed="true"]{border-color:#063b4f;background:#063b4f;color:#fff;box-shadow:0 14px 30px rgba(6,59,79,.18)}
    .swap-document-profile-toggle:disabled{cursor:not-allowed;border-color:#e2e8f0;background:#f1f5f9;color:#94a3b8;box-shadow:none}
    .swap-document-profile-toggle:focus-visible{outline:3px solid rgba(15,159,149,.26);outline-offset:2px}
    .swap-document-signature-feedback{margin:0;color:#0f766e;font-size:.86rem;font-weight:800}
    .swap-document-profile-preview{display:flex;align-items:center;justify-content:center;min-height:126px;border:1px dashed #9bd8d4;border-radius:1rem;background:#fff;padding:1rem}
    .swap-document-profile-preview[hidden]{display:none}
    .swap-document-profile-preview img{max-width:100%;max-height:112px;object-fit:contain}
    .swap-document-draw-area{display:grid;gap:.75rem}
    .swap-document-draw-area[hidden]{display:none}
    .swap-document-signature-canvas{width:100%;height:150px;border:1px dashed #9bd8d4;border-radius:1rem;background:#fff;touch-action:none}
</style>

<div class="swap-confirm-backdrop" id="swapConfirmBackdrop" hidden>
    <div class="swap-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="swapConfirmTitle">
        <div class="swap-confirm-head">
            <p><i class="bi bi-arrow-left-right"></i> ยืนยันการแลกเวร</p>
            <h3 id="swapConfirmTitle">ยืนยันการขอแลกเวร</h3>
        </div>
        <div class="swap-confirm-sides">
            <div class="swap-confirm-side is-source">
                <span class="swap-confirm-side-label"><i class="bi bi-person-check"></i> เวรของฉัน (ต้นทาง)</span>
                <div class="swap-confirm-detail" id="swapConfirmSource">
                    <div><span>วันที่</span><strong data-confirm-src-date>-</strong></div>
                    <div><span>กะ</span><strong data-confirm-src-shift>-</strong></div>
                    <div><span>เวลา</span><strong data-confirm-src-time>-</strong></div>
                    <div><span>ชั่วโมง</span><strong data-confirm-src-hours>-</strong></div>
                    <div><span>แผนก</span><strong data-confirm-src-dept>-</strong></div>
                    <div><span>สถานะ</span><strong data-confirm-src-status>-</strong></div>
                </div>
            </div>
            <div class="swap-confirm-side is-target">
                <span class="swap-confirm-side-label"><i class="bi bi-person-lines-fill"></i> เวรปลายทาง</span>
                <div class="swap-confirm-detail" id="swapConfirmTarget">
                    <div><span>เจ้าหน้าที่</span><strong data-confirm-tgt-staff>-</strong></div>
                    <div><span>วันที่</span><strong data-confirm-tgt-date>-</strong></div>
                    <div><span>กะ</span><strong data-confirm-tgt-shift>-</strong></div>
                    <div><span>เวลา</span><strong data-confirm-tgt-time>-</strong></div>
                    <div><span>ชั่วโมง</span><strong data-confirm-tgt-hours>-</strong></div>
                    <div><span>แผนก</span><strong data-confirm-tgt-dept>-</strong></div>
                    <div><span>สถานะ</span><strong data-confirm-tgt-status>-</strong></div>
                </div>
            </div>
        </div>
        <div class="swap-confirm-warning">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            การยืนยันนี้เป็นการส่งคำขอแลกเวรเท่านั้น เวรจะยังไม่ถูกสลับจนกว่าเจ้าหน้าที่ปลายทางและผู้มีสิทธิ์อนุมัติจะดำเนินการครบตามขั้นตอน
        </div>
        <div class="swap-document-signature-block">
            <div>
                <strong>ลายเซ็นผู้ขอเปลี่ยนเวร</strong>
                <span>ลงลายเซ็นเพื่อแนบกับแบบขอเปลี่ยนเวรก่อนส่งคำขอ</span>
            </div>
            <button type="button"
                    class="swap-document-profile-toggle"
                    id="swapConfirmUseProfileSignature"
                    aria-pressed="false"
                    data-has-profile-signature="<?= $profileSignaturePath !== '' ? '1' : '0' ?>"
                    data-profile-signature-src="<?= htmlspecialchars($profileSignatureSrc, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $profileSignaturePath === '' ? 'disabled' : '' ?>>
                <i class="bi bi-person-badge"></i>
                <span data-swap-profile-toggle-label><?= $profileSignaturePath !== '' ? 'ใช้ลายเซ็นจากโปรไฟล์' : 'ยังไม่มีลายเซ็นในโปรไฟล์' ?></span>
            </button>
            <p class="swap-document-signature-feedback" id="swapConfirmSignatureFeedback">
                <?= $profileSignaturePath !== '' ? 'กรุณาเลือกใช้ลายเซ็นโปรไฟล์หรือวาดลายเซ็นด้านล่าง' : 'ยังไม่มีลายเซ็นในโปรไฟล์ กรุณาวาดลายเซ็นด้านล่าง' ?>
            </p>
            <div class="swap-document-profile-preview" id="swapConfirmProfileSignaturePreview" hidden>
                <?php if ($profileSignatureSrc !== ''): ?>
                    <img src="<?= htmlspecialchars($profileSignatureSrc, ENT_QUOTES, 'UTF-8') ?>" alt="ลายเซ็นจากโปรไฟล์">
                <?php endif; ?>
            </div>
            <div class="swap-document-draw-area" id="swapConfirmDrawArea">
                <canvas id="swapConfirmSignatureCanvas" class="swap-document-signature-canvas" width="720" height="180" aria-label="พื้นที่วาดลายเซ็น"></canvas>
                <button type="button" class="dash-btn dash-btn-ghost" id="swapConfirmClearSignature"><i class="bi bi-eraser"></i> ล้างลายเซ็น</button>
            </div>
        </div>
        <div id="swapConfirmError" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" hidden></div>
        <div class="swap-confirm-actions">
            <button type="button" class="dash-btn dash-btn-ghost" id="swapConfirmCancel">
                <i class="bi bi-x-circle"></i> ยกเลิก
            </button>
            <button type="button" class="dash-btn dash-btn-primary" id="swapConfirmSubmit">
                <i class="bi bi-check2-circle"></i> ยืนยันขอแลกเวร
            </button>
        </div>
    </div>
</div>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/notifications.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script>
(() => {
    // ── State ────────────────────────────────────────────────────────────
    let sourceShift = null;           // my shift to swap (from PHP swap_source_id)
    let targetShift = null;           // target shift selected
    let sourceDetailSnapshot = null;  // source payload is kept while choosing another target
    let confirmModalOpen = false;

    // ── DOM refs ─────────────────────────────────────────────────────────
    const modal           = document.querySelector('[data-my-shift-modal]');
    const form            = document.querySelector('[data-my-shift-form]');
    const submitBtn       = document.querySelector('[data-modal-submit]');
    const noteWrap        = document.querySelector('[data-modal-note-wrap]');
    const confirmGroup    = document.querySelector('[data-modal-confirm-group]');
    const swapGroup       = document.querySelector('[data-modal-swap-group]');
    const swapLink        = document.querySelector('[data-modal-swap]');
    const swapStatus      = document.querySelector('[data-modal-swap-status]');
    const confirmBackdrop = document.getElementById('swapConfirmBackdrop');
    const confirmCancel   = document.getElementById('swapConfirmCancel');
    const confirmSubmit   = document.getElementById('swapConfirmSubmit');
    const confirmError    = document.getElementById('swapConfirmError');
    const confirmSignatureCanvas = document.getElementById('swapConfirmSignatureCanvas');
    const confirmClearSignature = document.getElementById('swapConfirmClearSignature');
    const confirmUseProfileSignature = document.getElementById('swapConfirmUseProfileSignature');
    const confirmProfileToggleLabel = document.querySelector('[data-swap-profile-toggle-label]');
    const confirmSignatureFeedback = document.getElementById('swapConfirmSignatureFeedback');
    const confirmProfilePreview = document.getElementById('swapConfirmProfileSignaturePreview');
    const confirmDrawArea = document.getElementById('swapConfirmDrawArea');
    const dayModal        = document.querySelector('[data-my-shift-day-modal]');
    const dayModalDate    = document.querySelector('[data-day-modal-date]');
    const dayModalDepts   = document.querySelector('[data-day-modal-departments]');
    const dayModalBody    = document.querySelector('[data-day-modal-body]');
    const floatingTooltip = document.querySelector('[data-shift-floating-tooltip]');

    const openAssignmentId = <?= (int) $openAssignmentId ?>;

    const fields = {
        assignmentId : document.querySelector('[data-modal-assignment-id]'),
        title        : document.querySelector('[data-modal-title]'),
        date         : document.querySelector('[data-modal-date]'),
        shift        : document.querySelector('[data-modal-shift]'),
        time         : document.querySelector('[data-modal-time]'),
        hours        : document.querySelector('[data-modal-hours]'),
        department   : document.querySelector('[data-modal-department]'),
        status       : document.querySelector('[data-modal-status]'),
        description  : document.querySelector('[data-modal-description]')
    };

    const confirmFields = {
        srcDate   : document.querySelector('[data-confirm-src-date]'),
        srcShift  : document.querySelector('[data-confirm-src-shift]'),
        srcTime   : document.querySelector('[data-confirm-src-time]'),
        srcHours  : document.querySelector('[data-confirm-src-hours]'),
        srcDept   : document.querySelector('[data-confirm-src-dept]'),
        srcStatus : document.querySelector('[data-confirm-src-status]'),
        tgtStaff  : document.querySelector('[data-confirm-tgt-staff]'),
        tgtDate   : document.querySelector('[data-confirm-tgt-date]'),
        tgtShift  : document.querySelector('[data-confirm-tgt-shift]'),
        tgtTime   : document.querySelector('[data-confirm-tgt-time]'),
        tgtHours  : document.querySelector('[data-confirm-tgt-hours]'),
        tgtDept   : document.querySelector('[data-confirm-tgt-dept]'),
        tgtStatus : document.querySelector('[data-confirm-tgt-status]'),
    };
    const signatureCtx = confirmSignatureCanvas?.getContext('2d');
    let signatureDrawing = false;
    let signatureHasStroke = false;
    let useProfileSignature = false;

    // ── Helpers ───────────────────────────────────────────────────────────
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function staffAvatarHtml(staff) {
        const name = staff.staffName || 'เจ้าหน้าที่';
        const initials = staff.initials || 'U';
        if (staff.avatarUrl) {
            return `<span class="my-shift-day-avatar" data-initials="${escapeHtml(initials)}"><img src="${escapeHtml(staff.avatarUrl)}" alt="รูปโปรไฟล์ ${escapeHtml(name)}" onerror="this.parentElement.textContent=this.parentElement.dataset.initials||'U';"></span>`;
        }
        return `<span class="my-shift-day-avatar">${escapeHtml(initials)}</span>`;
    }

    function tooltipHtml(payload) {
        const mineText = payload.isMine ? '<span class="my-shift-floating-badge">เวรของฉัน</span>' : '';
        const position = payload.staffPosition || 'ไม่ระบุตำแหน่ง';
        const note = payload.roleNote || payload.scheduleNote || '';
        return `
            <div class="my-shift-floating-head">
                <strong>${escapeHtml(payload.shiftLabel || '-')}</strong>
                ${mineText}
            </div>
            <div class="my-shift-floating-grid">
                <span>เวลา</span><b>${escapeHtml(payload.timeRange || '-')}</b>
                <span>เจ้าหน้าที่</span><b>${escapeHtml(payload.staffName || '-')}</b>
                <span>ตำแหน่ง</span><b>${escapeHtml(position)}</b>
                <span>แผนก</span><b>${escapeHtml(payload.department || '-')}</b>
                <span>สถานะ</span><b>${escapeHtml(payload.statusLabel || '-')}</b>
            </div>
            ${note ? `<p>${escapeHtml(note)}</p>` : ''}
        `;
    }

    function positionFloatingTooltip(anchor) {
        if (!floatingTooltip || floatingTooltip.hidden) return;
        const rect = anchor.getBoundingClientRect();
        const tooltipRect = floatingTooltip.getBoundingClientRect();
        const gap = 10;
        let left = rect.left + Math.min(18, rect.width / 2);
        let top = rect.bottom + gap;

        if (left + tooltipRect.width > window.innerWidth - 12) {
            left = window.innerWidth - tooltipRect.width - 12;
        }
        if (left < 12) left = 12;

        if (top + tooltipRect.height > window.innerHeight - 12) {
            top = rect.top - tooltipRect.height - gap;
        }
        if (top < 12) top = 12;

        floatingTooltip.style.left = `${Math.round(left)}px`;
        floatingTooltip.style.top = `${Math.round(top)}px`;
    }

    function showShiftTooltip(button) {
        if (!floatingTooltip) return;
        const payload = JSON.parse(button.dataset.shift || '{}');
        floatingTooltip.innerHTML = tooltipHtml(payload);
        floatingTooltip.hidden = false;
        positionFloatingTooltip(button);
    }

    function hideShiftTooltip() {
        if (!floatingTooltip) return;
        floatingTooltip.hidden = true;
        floatingTooltip.innerHTML = '';
    }

    function clearSwapSignature() {
        if (!signatureCtx || !confirmSignatureCanvas) return;
        signatureCtx.clearRect(0, 0, confirmSignatureCanvas.width, confirmSignatureCanvas.height);
        signatureHasStroke = false;
        if (confirmError) {
            confirmError.hidden = true;
            confirmError.textContent = '';
        }
    }

    function hasProfileSignature() {
        return confirmUseProfileSignature?.dataset.hasProfileSignature === '1';
    }

    function setProfileSignatureMode(nextValue) {
        useProfileSignature = Boolean(nextValue && hasProfileSignature());
        if (confirmUseProfileSignature) {
            confirmUseProfileSignature.setAttribute('aria-pressed', useProfileSignature ? 'true' : 'false');
            confirmUseProfileSignature.classList.toggle('is-active', useProfileSignature);
        }
        if (confirmProfileToggleLabel) {
            confirmProfileToggleLabel.textContent = hasProfileSignature()
                ? (useProfileSignature ? 'กำลังใช้ลายเซ็นจากโปรไฟล์' : 'ใช้ลายเซ็นจากโปรไฟล์')
                : 'ยังไม่มีลายเซ็นในโปรไฟล์';
        }
        if (confirmDrawArea) {
            confirmDrawArea.hidden = useProfileSignature;
        }
        if (confirmProfilePreview) {
            confirmProfilePreview.hidden = !useProfileSignature;
        }
        if (confirmSignatureFeedback) {
            confirmSignatureFeedback.textContent = hasProfileSignature()
                ? (useProfileSignature ? 'กำลังใช้ลายเซ็นจากโปรไฟล์' : 'กรุณาวาดลายเซ็นด้านล่าง')
                : 'ยังไม่มีลายเซ็นในโปรไฟล์ กรุณาวาดลายเซ็นด้านล่าง';
        }
        if (confirmError) {
            confirmError.hidden = true;
            confirmError.textContent = '';
        }
    }

    function signaturePoint(event) {
        const rect = confirmSignatureCanvas.getBoundingClientRect();
        const source = event.touches?.[0] || event.changedTouches?.[0] || event;
        return {
            x: (source.clientX - rect.left) * (confirmSignatureCanvas.width / rect.width),
            y: (source.clientY - rect.top) * (confirmSignatureCanvas.height / rect.height),
        };
    }

    function beginSwapSignature(event) {
        if (!signatureCtx || useProfileSignature) return;
        signatureDrawing = true;
        signatureHasStroke = true;
        const p = signaturePoint(event);
        signatureCtx.beginPath();
        signatureCtx.moveTo(p.x, p.y);
        if (confirmError) {
            confirmError.hidden = true;
            confirmError.textContent = '';
        }
        event.preventDefault();
    }

    function moveSwapSignature(event) {
        if (!signatureDrawing || !signatureCtx || useProfileSignature) return;
        const p = signaturePoint(event);
        signatureCtx.lineWidth = 2.4;
        signatureCtx.lineCap = 'round';
        signatureCtx.lineJoin = 'round';
        signatureCtx.strokeStyle = '#063b4f';
        signatureCtx.lineTo(p.x, p.y);
        signatureCtx.stroke();
        event.preventDefault();
    }

    function endSwapSignature() {
        signatureDrawing = false;
    }

    function openDayModal(payload) {
        if (!dayModal || !dayModalBody) return;
        hideShiftTooltip();
        if (dayModalDate) dayModalDate.textContent = payload.date || '-';
        if (dayModalDepts) {
            const departments = Array.isArray(payload.departments) && payload.departments.length
                ? payload.departments.join(' / ')
                : '-';
            dayModalDepts.textContent = departments;
        }

        const groups = Array.isArray(payload.groups) ? payload.groups : [];
        if (!groups.length) {
            dayModalBody.innerHTML = '<div class="my-shift-day-empty-state">ไม่มีเวรในวันนี้</div>';
        } else {
            dayModalBody.innerHTML = groups.map((group) => {
                const items = Array.isArray(group.items) ? group.items : [];
                return `
                    <section class="my-shift-day-section is-shift-${escapeHtml(group.shiftClass || 'other')}">
                        <div class="my-shift-day-section-head">
                            <strong>${escapeHtml(group.shiftLabel || '-')}</strong>
                            <span>${items.length} คน</span>
                        </div>
                        <div class="my-shift-day-staff-list">
                            ${items.map((staff) => `
                                <article class="my-shift-day-staff ${staff.isMine ? 'is-mine' : ''}">
                                    ${staffAvatarHtml(staff)}
                                    <div class="my-shift-day-staff-main">
                                        <strong>${escapeHtml(staff.staffName || '-')}</strong>
                                        <span>${escapeHtml(staff.staffPosition || 'ไม่ระบุตำแหน่ง')}</span>
                                    </div>
                                    <div class="my-shift-day-staff-meta">
                                        <span>${escapeHtml(staff.department || '-')}</span>
                                        <small>${escapeHtml(staff.timeRange || '-')} · ${escapeHtml(staff.statusLabel || '-')}</small>
                                    </div>
                                </article>
                            `).join('')}
                        </div>
                    </section>
                `;
            }).join('');
        }
        dayModal.hidden = false;
        document.body.classList.add('overflow-hidden');
    }

    function closeDayModal() {
        if (!dayModal) return;
        dayModal.hidden = true;
        document.body.classList.remove('overflow-hidden');
    }

    function openDetailModal(payload) {
        if (!modal) return;
        hideShiftTooltip();
        fields.assignmentId.value = payload.assignmentId || '';
        fields.title.textContent = payload.timeLogId
            ? 'รายละเอียดเวรที่ลงแล้ว'
            : (payload.isMine ? 'ลงเวรตามแผน' : 'รายละเอียดเวรเจ้าหน้าที่');
        fields.date.textContent       = payload.date || '-';
        fields.shift.textContent      = payload.shiftLabel || '-';
        fields.time.textContent       = payload.timeRange || '-';
        fields.hours.textContent      = `${payload.hours || '0.00'} ชม.`;
        fields.department.textContent = payload.department || '-';
        fields.status.textContent     = payload.statusLabel || '-';

        const details = [
            payload.staffName   ? `เจ้าหน้าที่: ${payload.staffName}` : '',
            payload.roleNote    ? `หน้าที่: ${payload.roleNote}` : '',
            payload.scheduleNote ? `หมายเหตุแผนเวร: ${payload.scheduleNote}` : '',
            payload.timeLogId   ? `time_logs.id: ${payload.timeLogId}` : '',
            payload.source      ? `source: ${payload.source}` : '',
            payload.note        ? `หมายเหตุลงเวร: ${payload.note}` : ''
        ].filter(Boolean);
        fields.description.textContent = details.length ? details.join(' | ') : 'ไม่มีรายละเอียดเพิ่มเติม';

        const canCreate = !payload.timeLogId && payload.isMine;
        if (submitBtn) submitBtn.hidden = !canCreate;
        if (noteWrap)  noteWrap.hidden  = !canCreate;
        if (confirmGroup) confirmGroup.hidden = !canCreate;
        if (swapLink) {
            swapLink.hidden = !payload.canRequestSwap;
            swapLink.href   = payload.swapUrl || '#';
        }
        if (swapStatus) {
            const hasSwapStatus = payload.isMine && !payload.canRequestSwap && payload.swapBlockedReason === 'รอคำขอแลกอยู่';
            swapStatus.hidden      = !hasSwapStatus;
            swapStatus.textContent = payload.swapBlockedReason || '';
        }
        if (swapGroup) {
            swapGroup.hidden = !payload.isMine || (!payload.canRequestSwap && swapStatus?.hidden !== false);
        }
        modal.hidden = false;
        document.body.classList.add('overflow-hidden');
    }

    function closeDetailModal() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('overflow-hidden');
    }

    function openConfirmModal(src, tgt) {
        if (!confirmBackdrop) return;
        // Fill source side
        if (confirmFields.srcDate)   confirmFields.srcDate.textContent   = src.date || '-';
        if (confirmFields.srcShift)  confirmFields.srcShift.textContent  = src.shiftLabel || '-';
        if (confirmFields.srcTime)   confirmFields.srcTime.textContent   = src.timeRange || '-';
        if (confirmFields.srcHours)  confirmFields.srcHours.textContent  = `${src.hours || '0.00'} ชม.`;
        if (confirmFields.srcDept)   confirmFields.srcDept.textContent   = src.department || '-';
        if (confirmFields.srcStatus) confirmFields.srcStatus.textContent = src.statusLabel || '-';
        // Fill target side
        if (confirmFields.tgtStaff)  confirmFields.tgtStaff.textContent  = tgt.staffName || '-';
        if (confirmFields.tgtDate)   confirmFields.tgtDate.textContent   = tgt.date || '-';
        if (confirmFields.tgtShift)  confirmFields.tgtShift.textContent  = tgt.shiftLabel || '-';
        if (confirmFields.tgtTime)   confirmFields.tgtTime.textContent   = tgt.timeRange || '-';
        if (confirmFields.tgtHours)  confirmFields.tgtHours.textContent  = `${tgt.hours || '0.00'} ชม.`;
        if (confirmFields.tgtDept)   confirmFields.tgtDept.textContent   = tgt.department || '-';
        if (confirmFields.tgtStatus) confirmFields.tgtStatus.textContent = tgt.statusLabel || '-';

        if (confirmError) confirmError.hidden = true;
        clearSwapSignature();
        setProfileSignatureMode(false);
        if (confirmSubmit) {
            confirmSubmit.disabled = false;
            confirmSubmit.innerHTML = '<i class="bi bi-check2-circle"></i> ยืนยันขอแลกเวร';
        }
        confirmBackdrop.hidden = false;
        confirmModalOpen = true;
        document.body.classList.add('overflow-hidden');
    }

    function closeConfirmModal() {
        if (!confirmBackdrop) return;
        confirmBackdrop.hidden = true;
        confirmModalOpen = false;
    }

    function cancelSwapConfirmation() {
        closeConfirmModal();
        targetShift = null;
        if (confirmError) {
            confirmError.hidden = true;
            confirmError.textContent = '';
        }
        if (confirmSubmit) {
            confirmSubmit.disabled = false;
            confirmSubmit.innerHTML = '<i class="bi bi-check2-circle"></i> ยืนยันขอแลกเวร';
        }
        if (modal) modal.hidden = true;
        if (dayModal) dayModal.hidden = true;
        document.body.classList.remove('overflow-hidden');
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        const colorClass = type === 'success'
            ? 'bg-emerald-600 text-white'
            : 'bg-rose-600 text-white';
        toast.className = `fixed bottom-6 right-6 z-[100] rounded-2xl px-5 py-4 text-sm font-bold shadow-glass ${colorClass}`;
        toast.style.transition = 'opacity 0.4s';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; }, 3000);
        setTimeout(() => { toast.remove(); }, 3500);
    }

    // ── Daily modal open/close ────────────────────────────────────────────
    document.querySelectorAll('[data-my-shift-day-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const payload = JSON.parse(button.dataset.dayShifts || '{}');
            openDayModal(payload);
        });
    });

    document.querySelectorAll('[data-my-shift-day-close]').forEach((button) => {
        button.addEventListener('click', closeDayModal);
    });

    dayModal?.addEventListener('click', (event) => {
        if (event.target === dayModal) closeDayModal();
    });

    // ── Detail modal open/close ───────────────────────────────────────────
    document.querySelectorAll('[data-my-shift-open]').forEach((button) => {
        button.addEventListener('mouseenter', () => showShiftTooltip(button));
        button.addEventListener('focus', () => showShiftTooltip(button));
        button.addEventListener('mousemove', () => positionFloatingTooltip(button));
        button.addEventListener('mouseleave', hideShiftTooltip);
        button.addEventListener('blur', hideShiftTooltip);
        button.addEventListener('click', () => {
            const payload = JSON.parse(button.dataset.shift || '{}');
            openDetailModal(payload);
        });
    });

    window.addEventListener('scroll', hideShiftTooltip, true);
    window.addEventListener('resize', hideShiftTooltip);

    if (openAssignmentId > 0) {
        document.querySelector(`[data-my-shift-open][data-assignment-id="${openAssignmentId}"]`)?.click();
    }

    document.querySelectorAll('[data-my-shift-close]').forEach((button) => {
        button.addEventListener('click', closeDetailModal);
    });

    // Close detail modal on backdrop click
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closeDetailModal();
    });

    // ── Time log form submit ──────────────────────────────────────────────
    form?.addEventListener('submit', () => {
        if (!submitBtn) return;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังบันทึก...';
    });

    // ── "ขอแลกเวร" button — open confirm modal instead of submitting ──────
    document.querySelectorAll('[data-swap-request]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tgt = JSON.parse(btn.dataset.target || '{}');
            targetShift = {
                ...tgt,
                csrf      : btn.dataset.csrf,
                sourceId  : btn.dataset.sourceId,
                month     : btn.dataset.month,
                year      : btn.dataset.year,
                display   : btn.dataset.display,
            };

            // Capture source shift info from PHP-rendered swap banner.
            // The source remains active until the user exits swap mode from the banner.
            sourceShift = <?= $swapSourceSummary
                ? json_encode([
                    'date'        => app_format_thai_date((string) $swapSourceSummary['date'], true),
                    'shiftLabel'  => (string) $swapSourceSummary['shift_label'],
                    'timeRange'   => (string) $swapSourceSummary['time'],
                    'hours'       => number_format((float) $swapSourceSummary['hours'], 2),
                    'department'  => (string) $swapSourceSummary['department_name'],
                    'statusLabel' => 'กำหนดเวร',
                ], JSON_UNESCAPED_UNICODE)
                : 'null' ?>;

            sourceDetailSnapshot = sourceShift ? {
                assignmentId      : parseInt(btn.dataset.sourceId, 10),
                isMine            : true,
                timeLogId         : 0,
                canRequestSwap    : false,
                swapBlockedReason : '',
                swapUrl           : '#',
                staffName         : '',
                roleNote          : '',
                scheduleNote      : '',
                note              : '',
                source            : '',
                date              : sourceShift.date        || '-',
                shiftLabel        : sourceShift.shiftLabel  || '-',
                timeRange         : sourceShift.timeRange   || '-',
                hours             : sourceShift.hours       || '0.00',
                department        : sourceShift.department  || '-',
                statusLabel       : sourceShift.statusLabel || '-',
            } : null;

            openConfirmModal(sourceShift ?? {}, targetShift);
        });
    });

    // ── Confirm modal: cancel only this target selection and stay in swap mode ──
    confirmCancel?.addEventListener('click', () => {
        cancelSwapConfirmation();
    });

    confirmSignatureCanvas?.addEventListener('mousedown', beginSwapSignature);
    confirmSignatureCanvas?.addEventListener('mousemove', moveSwapSignature);
    window.addEventListener('mouseup', endSwapSignature);
    confirmSignatureCanvas?.addEventListener('touchstart', beginSwapSignature, { passive: false });
    confirmSignatureCanvas?.addEventListener('touchmove', moveSwapSignature, { passive: false });
    confirmSignatureCanvas?.addEventListener('touchend', endSwapSignature);
    confirmClearSignature?.addEventListener('click', () => {
        setProfileSignatureMode(false);
        clearSwapSignature();
    });
    confirmUseProfileSignature?.addEventListener('click', () => {
        if (!hasProfileSignature()) {
            setProfileSignatureMode(false);
            return;
        }
        setProfileSignatureMode(!useProfileSignature);
    });

    // ── Confirm modal: Submit via fetch ───────────────────────────────────
    confirmSubmit?.addEventListener('click', async () => {
        if (!targetShift) return;
        if (confirmSubmit.disabled) return;
        const signatureSource = useProfileSignature && hasProfileSignature() ? 'profile' : 'drawn';
        if (signatureSource === 'drawn' && !signatureHasStroke) {
            if (confirmError) {
                confirmError.textContent = 'กรุณาลงลายเซ็นก่อนส่งคำขอแลกเวร';
                confirmError.hidden = false;
            }
            return;
        }

        const loadingApi = window.GlobalLoading || null;
        const loadingController = loadingApi?.showPageLoading
            ? loadingApi.showPageLoading('โปรดรอสักครู่...', 'กำลังส่งคำขอแลกเวร', { trigger: confirmSubmit, busyText: 'กำลังส่งคำขอ...' })
            : null;
        confirmSubmit.disabled = true;
        confirmSubmit.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังส่งคำขอ...';
        if (confirmError) confirmError.hidden = true;

        const body = new URLSearchParams({
            _csrf                   : targetShift.csrf,
            requester_assignment_id : targetShift.sourceId,
            target_assignment_id    : targetShift.assignmentId,
            reason                  : 'ขอแลกเวรจากหน้าเวรของฉัน',
            return_to               : 'my-shifts',
            month                   : targetShift.month,
            year                    : targetShift.year,
            display                 : targetShift.display,
            requester_signature_data: signatureSource === 'profile' ? '' : confirmSignatureCanvas.toDataURL('image/png'),
            signature_source        : signatureSource,
            use_profile_signature   : signatureSource === 'profile' ? '1' : '0',
        });

        try {
            const res = await fetch('../actions/create-shift-swap-request.php', {
                method  : 'POST',
                headers : { 'X-Requested-With': 'fetch', 'Content-Type': 'application/x-www-form-urlencoded' },
                body    : body.toString(),
            });

            const data = await res.json().catch(() => ({}));

            if (res.ok && data.ok) {
                // Capture redirect params BEFORE clearing state
                const _month   = targetShift.month;
                const _year    = targetShift.year;
                const _display = targetShift.display || 'calendar';

                closeConfirmModal();
                closeDetailModal();
                sourceShift = null;
                targetShift = null;
                sourceDetailSnapshot = null;
                showToast(data.message || (signatureSource === 'drawn' ? 'บันทึกคำขอแลกเวรและอัปเดตลายเซ็นโปรไฟล์แล้ว' : 'ส่งคำขอแลกเวรสำเร็จแล้ว รอเจ้าหน้าที่ปลายทางยืนยัน'), 'success');
                loadingController?.hide();
                // Navigate to view=my (strips swap_source_id) after toast is visible
                const successUrl = 'my-shifts.php?' + new URLSearchParams({
                    month: _month, year: _year, view: 'my', display: _display,
                }).toString();
                setTimeout(() => { window.location.href = successUrl; }, 1800);
            } else {
                const errMsg = data.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่';
                if (confirmError) {
                    confirmError.textContent = errMsg;
                    confirmError.hidden = false;
                }
                confirmSubmit.disabled = false;
                confirmSubmit.innerHTML = '<i class="bi bi-check2-circle"></i> ยืนยันขอแลกเวร';
                loadingController?.hide();
            }
        } catch (err) {
            if (confirmError) {
                confirmError.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่';
                confirmError.hidden = false;
            }
            confirmSubmit.disabled = false;
            confirmSubmit.innerHTML = '<i class="bi bi-check2-circle"></i> ยืนยันขอแลกเวร';
            loadingController?.hide();
        }
    });

    // Close confirm modal on backdrop click (outside dialog)
    confirmBackdrop?.addEventListener('click', (e) => {
        if (e.target === confirmBackdrop) {
            cancelSwapConfirmation();
        }
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
