<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/time_entry_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

date_default_timezone_set('Asia/Bangkok');
app_require_login();

$userId = (int) $_SESSION['id'];
$role = app_current_role();
$roleLabel = app_role_label($role);
$fullName = (string) ($_SESSION['fullname'] ?? '');
$canViewDepartmentReports = app_can('can_view_department_reports');

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$userId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['department_id' => 1, 'department_name' => '-'];
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($userMeta);
if ($displayName !== '-') {
    $fullName = $displayName;
}

$departmentId = (int) ($_SESSION['department_id'] ?? $userMeta['department_id']);
if (!$canViewDepartmentReports) {
    $departmentId = (int) $userMeta['department_id'];
    $_SESSION['department_id'] = $departmentId;
}

$today = date('Y-m-d');
$message = (string) ($_SESSION['time_page_flash'] ?? '');
$messageType = (string) ($_SESSION['time_page_flash_type'] ?? 'success');
unset($_SESSION['time_page_flash'], $_SESSION['time_page_flash_type']);
$historyStateInput = array_merge($_GET, $_POST);
$historyDateState = trim((string) ($_POST['history_date'] ?? ($historyStateInput['date'] ?? '')));
$historyDateFromState = trim((string) ($_POST['history_date_from'] ?? ($historyStateInput['date_from'] ?? '')));
$historyDateToState = trim((string) ($_POST['history_date_to'] ?? ($historyStateInput['date_to'] ?? '')));
$historyStatusState = trim((string) ($_POST['history_status'] ?? ($historyStateInput['status'] ?? 'all')));
$historyQueryState = trim((string) ($_POST['history_query'] ?? ($historyStateInput['query'] ?? '')));
$historyLimitState = app_parse_table_page_size([
    'per_page' => $_POST['history_per_page'] ?? ($historyStateInput['per_page'] ?? 20),
], 20);
$historyPageState = app_parse_table_page([
    'p' => $_POST['history_page'] ?? ($historyStateInput['p'] ?? 1),
]);
$historyFilters = app_normalize_user_time_history_filters([
    'date' => $historyDateState,
    'date_from' => $historyDateFromState,
    'date_to' => $historyDateToState,
    'status' => $historyStatusState,
    'query' => $historyQueryState,
]);
$historyRedirectBase = [
    'per_page' => $historyLimitState,
];
if ($historyFilters['date'] !== '') {
    $historyRedirectBase['date'] = $historyFilters['date'];
}
if ($historyFilters['date_from'] !== '') {
    $historyRedirectBase['date_from'] = $historyFilters['date_from'];
}
if ($historyFilters['date_to'] !== '') {
    $historyRedirectBase['date_to'] = $historyFilters['date_to'];
}
if ($historyFilters['status'] !== 'all') {
    $historyRedirectBase['status'] = $historyFilters['status'];
}
if ($historyFilters['query'] !== '') {
    $historyRedirectBase['query'] = $historyFilters['query'];
}
$shiftPresets = [
    'morning' => ['label' => 'เช้า', 'time_in' => '08:30', 'time_out' => '16:30'],
    'afternoon' => ['label' => 'บ่าย', 'time_in' => '16:30', 'time_out' => '00:30'],
    'night' => ['label' => 'ดึก', 'time_in' => '00:30', 'time_out' => '08:30'],
];
$defaultTimeParts = app_time_to_parts($shiftPresets['morning']['time_in']);
$defaultTimeOutParts = app_time_to_parts($shiftPresets['morning']['time_out']);
$newForm = [
    'time_in_hour' => $defaultTimeParts['hour'],
    'time_in_minute' => $defaultTimeParts['minute'],
    'time_out_hour' => $defaultTimeOutParts['hour'],
    'time_out_minute' => $defaultTimeOutParts['minute'],
    'note' => '',
];
$hourOptions = array_map(static fn ($hour) => sprintf('%02d', $hour), range(0, 23));
$minuteOptions = array_map(static fn ($minute) => sprintf('%02d', $minute), range(0, 59));

if (isset($_POST['change_department']) && $canViewDepartmentReports) {
    $departmentId = (int) ($_POST['department_id'] ?? $departmentId);
    $_SESSION['department_id'] = $departmentId;
    $message = 'เปลี่ยนแผนกสำหรับการลงเวลาเรียบร้อยแล้ว';
}

if (($_POST['create_time_log'] ?? '') === '1' || isset($_POST['save_all_time'])) {
    $newForm['time_in_hour'] = trim((string) ($_POST['manual_time_in_hour'] ?? $newForm['time_in_hour']));
    $newForm['time_in_minute'] = trim((string) ($_POST['manual_time_in_minute'] ?? $newForm['time_in_minute']));
    $newForm['time_out_hour'] = trim((string) ($_POST['manual_time_out_hour'] ?? $newForm['time_out_hour']));
    $newForm['time_out_minute'] = trim((string) ($_POST['manual_time_out_minute'] ?? $newForm['time_out_minute']));
    $newForm['note'] = trim((string) ($_POST['note'] ?? ''));
    $timeInVal = app_parse_time_input($_POST, 'manual_time_in', '24h');
    $timeOutVal = app_parse_time_input($_POST, 'manual_time_out', '24h');

    if ($timeInVal === null || $timeOutVal === null) {
        $message = 'กรุณาระบุเวลาเข้าและเวลาออกให้ถูกต้อง';
        $messageType = 'danger';
    } else {
        $fullTimeIn = $today . ' ' . $timeInVal . ':00';
        $fullTimeOut = $today . ' ' . $timeOutVal . ':00';

        $tsIn = strtotime($fullTimeIn);
        $tsOut = strtotime($fullTimeOut);

        if ($tsOut < $tsIn) {
            $tsOut += 86400;
            $fullTimeOut = date('Y-m-d H:i:s', $tsOut);
        }

        $overlap = app_find_overlapping_time_log($conn, $userId, $fullTimeIn, $fullTimeOut);

        if ($overlap) {
            $messageType = 'danger';
            $message = sprintf(
                'ช่วงเวลานี้ซ้อนกับรายการวันที่ %s เวลา %s - %s กรุณาตรวจสอบก่อนบันทึก',
                date('d/m/Y', strtotime($overlap['work_date'])),
                date('H:i', strtotime($overlap['time_in'])),
                date('H:i', strtotime($overlap['time_out']))
            );
        } else {
            $hours = number_format(($tsOut - $tsIn) / 3600, 2, '.', '');

            $stmt = $conn->prepare("
                INSERT INTO time_logs (
                    user_id,
                    department_id,
                    work_date,
                    time_in,
                    time_out,
                    work_hours,
                    note,
                    status,
                    checked_by,
                    checked_at,
                    signature,
                    approval_note
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', NULL, NULL, NULL, NULL)
            ");
            $stmt->execute([$userId, $departmentId, $today, $fullTimeIn, $fullTimeOut, $hours, $newForm['note']]);
            app_sync_reviewer_queue_notifications($conn);
            $_SESSION['time_page_flash'] = 'บันทึกลงเวลาเวรเรียบร้อยแล้ว ส่งรายการเข้าคิวตรวจสอบ';
            $_SESSION['time_page_flash_type'] = 'success';

            header('Location: time.php?' . app_build_table_query($historyRedirectBase, ['p' => 1]));
            exit;
        }
    }
}

$editId = max(0, (int) ($_GET['edit_id'] ?? ($_POST['edit_id'] ?? 0)));
$editLog = null;
if ($editId > 0) {
    $editStmt = $conn->prepare("
        SELECT *
        FROM time_logs
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $editStmt->execute([$editId, $userId]);
    $editLog = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$canPrivilegedLockedEdit = app_can('can_edit_locked_time_logs');

if (isset($_POST['update_time_log']) && $editLog) {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'time_page_edit')) {
        $message = 'ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } else {
        $editNote = trim((string) ($_POST['edit_note'] ?? ''));
        $editDepartmentId = $canViewDepartmentReports ? (int) ($_POST['edit_department_id'] ?? $editLog['department_id']) : (int) $editLog['department_id'];
        $timeInVal = app_parse_time_input($_POST, 'edit_time_in', '24h');
        $timeOutVal = app_parse_time_input($_POST, 'edit_time_out', '24h');

        if (app_time_log_is_locked($editLog) && !$canPrivilegedLockedEdit) {
            $message = 'รายการนี้ได้รับการตรวจสอบแล้ว ไม่สามารถแก้ไขได้ กรุณาติดต่อผู้ดูแลระบบ';
            $messageType = 'danger';
        } elseif ($timeInVal === null || $timeOutVal === null) {
            $message = 'กรุณาระบุเวลาเข้าและเวลาออกในรูปแบบ 24 ชั่วโมง';
            $messageType = 'danger';
        } else {
            $range = app_build_time_log_range((string) $editLog['work_date'], $timeInVal, $timeOutVal);
            if ($range === null) {
                $message = 'ช่วงเวลาที่ระบุไม่ถูกต้อง';
                $messageType = 'danger';
            } else {
                $overlap = app_find_overlapping_time_log($conn, $userId, $range['time_in'], $range['time_out'], (int) $editLog['id']);
                if ($overlap) {
                    $messageType = 'danger';
                    $message = sprintf(
                        'ช่วงเวลานี้ซ้อนกับรายการวันที่ %s เวลา %s - %s กรุณาตรวจสอบก่อนบันทึก',
                        date('d/m/Y', strtotime($overlap['work_date'])),
                        date('H:i', strtotime($overlap['time_in'])),
                        date('H:i', strtotime($overlap['time_out']))
                    );
                } else {
                    $updateStmt = $conn->prepare("
                        UPDATE time_logs
                        SET department_id = ?, time_in = ?, time_out = ?, work_hours = ?, note = ?, status = 'submitted', checked_by = NULL, checked_at = NULL, signature = NULL, approval_note = NULL
                        WHERE id = ? AND user_id = ?
                    ");
                    $updateStmt->execute([$editDepartmentId, $range['time_in'], $range['time_out'], $range['hours'], $editNote, $editLog['id'], $userId]);
                    app_insert_time_log_audit(
                        $conn,
                        (int) $editLog['id'],
                        'self_service_update',
                        $editLog,
                        array_merge($editLog, [
                            'department_id' => $editDepartmentId,
                            'time_in' => $range['time_in'],
                            'time_out' => $range['time_out'],
                            'work_hours' => $range['hours'],
                            'note' => $editNote,
                            'checked_by' => null,
                            'checked_at' => null,
                            'signature' => null,
                        ]),
                        $userId,
                        (string) ($_SESSION['fullname'] ?? $fullName),
                        'แก้ไขรายการลงเวลาเวรจากหน้าลงเวลา'
                    );
                    app_sync_reviewer_queue_notifications($conn);
                    $_SESSION['time_page_flash'] = 'บันทึกการแก้ไขเรียบร้อยแล้ว ส่งรายการเข้าคิวตรวจสอบ';
                    $_SESSION['time_page_flash_type'] = 'success';
                    header('Location: time.php?' . app_build_table_query($historyRedirectBase, ['p' => max(1, $historyPageState)]));
                    exit;
                }
            }
        }
    }
}

if (isset($_POST['delete_time_log']) && $editLog) {
    if (!app_verify_csrf_token($_POST['delete_csrf'] ?? null, 'time_page_delete')) {
        $message = 'ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } elseif (app_time_log_is_locked($editLog) && !$canPrivilegedLockedEdit) {
        $message = 'รายการนี้ได้รับการตรวจสอบแล้ว ไม่สามารถลบได้';
        $messageType = 'danger';
    } else {
        $deleteStmt = $conn->prepare("DELETE FROM time_logs WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([(int) $editLog['id'], $userId]);
        app_insert_time_log_audit(
            $conn,
            (int) $editLog['id'],
            'self_service_delete',
            $editLog,
            null,
            $userId,
            (string) ($_SESSION['fullname'] ?? $fullName),
            'ลบรายการลงเวลาเวร'
        );
        app_sync_reviewer_queue_notifications($conn);
        $_SESSION['time_page_flash'] = 'ลบรายการลงเวลาเวรเรียบร้อยแล้ว';
        $_SESSION['time_page_flash_type'] = 'success';
        header('Location: time.php?' . app_build_table_query($historyRedirectBase, ['p' => max(1, $historyPageState)]));
        exit;
    }
}

$searchDate = $historyFilters['date'];
$dateFrom = $historyFilters['date_from'];
$dateTo = $historyFilters['date_to'];
$historyStatus = $historyFilters['status'];
$historyQuery = $historyFilters['query'];
$limit = $historyLimitState;
$page = $historyPageState;
$offset = ($page - 1) * $limit;

$historyScope = app_build_user_time_history_scope($historyFilters, $userId);
$totalRows = app_get_user_time_history_count($conn, $userId, $historyFilters);
$totalPages = max(1, (int) ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$totalStmt = $conn->prepare("
    SELECT COALESCE(SUM(t.work_hours), 0)
    FROM time_logs t
    LEFT JOIN departments d ON t.department_id = d.id
    {$historyScope['where_sql']}
");
$totalStmt->execute($historyScope['params']);
$totalHours = (float) $totalStmt->fetchColumn();

$monthHoursStmt = $conn->prepare("
    SELECT COALESCE(SUM(work_hours), 0)
    FROM time_logs
    WHERE user_id = ? AND YEAR(work_date) = YEAR(CURDATE()) AND MONTH(work_date) = MONTH(CURDATE())
");
$monthHoursStmt->execute([$userId]);
$monthHours = (float) $monthHoursStmt->fetchColumn();

$approvedMonthHoursStmt = $conn->prepare("
    SELECT COALESCE(SUM(work_hours), 0)
    FROM time_logs
    WHERE user_id = ? AND YEAR(work_date) = YEAR(CURDATE()) AND MONTH(work_date) = MONTH(CURDATE()) AND checked_at IS NOT NULL
");
$approvedMonthHoursStmt->execute([$userId]);
$approvedMonthHours = (float) $approvedMonthHoursStmt->fetchColumn();

$todayShiftCountStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM time_logs
    WHERE user_id = ? AND work_date = CURDATE()
");
$todayShiftCountStmt->execute([$userId]);
$todayShiftCount = (int) $todayShiftCountStmt->fetchColumn();

$todayHoursStmt = $conn->prepare("
    SELECT COALESCE(SUM(work_hours), 0)
    FROM time_logs
    WHERE user_id = ? AND work_date = CURDATE()
");
$todayHoursStmt->execute([$userId]);
$todayHours = (float) $todayHoursStmt->fetchColumn();

$latestLogStmt = $conn->prepare("
    SELECT t.*, d.department_name
    FROM time_logs t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.user_id = ?
    ORDER BY t.work_date DESC, t.id DESC
    LIMIT 1
");
$latestLogStmt->execute([$userId]);
$latestLog = $latestLogStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$latestStatusMeta = $latestLog ? app_time_log_status_meta($latestLog) : ['label' => 'ยังไม่มีรายการ', 'class' => 'info'];

$historyLogs = app_get_user_time_history_rows($conn, $userId, $historyFilters, $limit, $offset);
$historyFlags = app_get_user_time_history_flags($conn, $userId, $historyLogs);

if ($canViewDepartmentReports) {
    $departments = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $departments = [[
        'id' => $userMeta['department_id'],
        'department_name' => $userMeta['department_name'],
    ]];
}

$editModalAutoOpen = $editLog !== null;
$editModalLocked = $editLog ? app_time_log_is_locked($editLog) : false;
$selectedEditDepartmentId = $editLog ? (int) ($_POST['edit_department_id'] ?? $editLog['department_id']) : 0;
$editCsrfToken = app_csrf_token('time_page_edit');
$deleteCsrfToken = app_csrf_token('time_page_delete');
$canEditModal = $editLog ? (!$editModalLocked || $canPrivilegedLockedEdit) : false;
$canDeleteModal = $canEditModal;
$modalErrorMessage = $editLog && $messageType === 'danger' ? $message : '';
$modalErrorType = $messageType;
$todayLabel = app_format_thai_date($today, true);
$departmentLabel = trim((string) ($userMeta['department_name'] ?? '-')) ?: '-';
$notificationCount = app_get_unread_notification_count($conn, $userId);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
$monthlyTargetHours = 160.0;
$monthlyProgressPercent = $monthlyTargetHours > 0 ? min(100, (int) round(($monthHours / $monthlyTargetHours) * 100)) : 0;
$monthlyRemainingHours = max(0, $monthlyTargetHours - $monthHours);
$latestWorkDateLabel = $latestLog ? app_format_thai_date((string) $latestLog['work_date'], true) : 'ยังไม่มีข้อมูล';
$latestTimeRangeLabel = $latestLog && !empty($latestLog['time_in']) && !empty($latestLog['time_out'])
    ? date('H:i', strtotime((string) $latestLog['time_in'])) . ' - ' . date('H:i', strtotime((string) $latestLog['time_out']))
    : 'ยังไม่มีข้อมูล';
$latestLogHours = $latestLog ? number_format((float) ($latestLog['work_hours'] ?? 0), 2) : '0.00';
$todaySavedSummary = $todayShiftCount > 0 ? number_format($todayShiftCount) . ' เวร' : 'ยังไม่มีเวรวันนี้';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ลงเวลาเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell time-page-shell">
<?php render_dashboard_sidebar('time.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main time-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">ลงเวลาเวร</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาประวัติหรือเมนูที่เกี่ยวข้อง">
        </label>
        <?php render_notification_bell(); ?>

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

    <div class="time-dashboard-frame">
        <section class="time-hero-stage">
            <article class="dash-card-strong time-hero-card">
                <div class="time-hero-grid">
                    <div class="time-hero-copy-block">
                        <span class="dash-hero-badge">
                            <i class="bi bi-clock-history"></i>
                            Time Workspace
                        </span>
                        <h2 class="dash-hero-title">ลงเวลาเวรจากพื้นที่เดียว</h2>
                        <p class="dash-hero-copy">บันทึกเวลา ตรวจสอบชั่วโมง และดูประวัติย้อนหลังได้จากพื้นที่เดียว โดยคง workflow งานเวรของโรงพยาบาลให้ครบถ้วน</p>

                        <div class="dash-hero-chip-row">
                            <span class="dash-hero-chip"><i class="bi bi-person-badge"></i><?= htmlspecialchars($displayName) ?></span>
                            <span class="dash-hero-chip"><i class="bi bi-building"></i><?= htmlspecialchars($departmentLabel) ?></span>
                            <span class="dash-hero-chip"><i class="bi bi-calendar3"></i><?= htmlspecialchars($todayLabel) ?></span>
                        </div>
                    </div>

                    <div class="time-hero-divider" aria-hidden="true"></div>

                    <aside class="time-hero-realtime" aria-label="เวลาปัจจุบัน">
                        <span class="time-live-label"><i class="bi bi-clock-history"></i>เวลาปัจจุบัน</span>
                        <strong id="clock" class="time-clock">00:00:00</strong>
                        <span id="dateLabel" class="time-hero-date">-</span>
                    </aside>

                    <div class="time-hero-cta-stack">
                        <a href="#time-entry-panel" class="dash-btn dash-btn-secondary time-hero-cta-primary">
                            <i class="bi bi-pencil-square"></i>เริ่มบันทึกเวร
                        </a>
                        <a href="#time-history-panel" class="dash-btn dash-btn-on-dark time-hero-cta-secondary">
                            <i class="bi bi-clock-history"></i>ดูประวัติย้อนหลัง
                        </a>
                    </div>
                </div>
            </article>
        </section>

        <section class="time-kpi-row" aria-label="สรุปการลงเวลา">
            <article class="dash-kpi-card time-kpi-card">
                <span class="dash-icon-badge time-kpi-icon"><i class="bi bi-clock-history"></i></span>
                <div class="time-kpi-copy">
                    <p class="time-kpi-label">ชั่วโมงสะสมเดือนนี้</p>
                    <strong class="time-kpi-value"><?= number_format($monthHours, 2) ?> <span>ชั่วโมง</span></strong>
                    <p class="time-kpi-subtitle">รวมทุกเวรในเดือนปัจจุบัน</p>
                </div>
            </article>
            <article class="dash-kpi-card time-kpi-card">
                <span class="dash-icon-badge time-kpi-icon is-blue"><i class="bi bi-clock"></i></span>
                <div class="time-kpi-copy">
                    <p class="time-kpi-label">ชั่วโมงตรวจแล้วเดือนนี้</p>
                    <strong class="time-kpi-value"><?= number_format($approvedMonthHours, 2) ?> <span>ชั่วโมง</span></strong>
                    <p class="time-kpi-subtitle">เฉพาะรายการที่อนุมัติแล้ว</p>
                </div>
            </article>
            <article class="dash-kpi-card time-kpi-card">
                <span class="dash-icon-badge time-kpi-icon"><i class="bi bi-calendar2-week"></i></span>
                <div class="time-kpi-copy">
                    <p class="time-kpi-label">จำนวนเวรวันนี้</p>
                    <strong class="time-kpi-value"><?= number_format($todayShiftCount) ?> <span>เวร</span></strong>
                    <p class="time-kpi-subtitle">รายการที่บันทึกของวันนี้</p>
                </div>
            </article>
            <article class="dash-kpi-card time-kpi-card">
                <span class="dash-icon-badge time-kpi-icon is-lilac"><i class="bi bi-file-earmark-text"></i></span>
                <div class="time-kpi-copy">
                    <p class="time-kpi-label">รายการล่าสุด</p>
                    <div class="time-kpi-inline">
                        <strong class="time-kpi-value time-kpi-value-compact"><?= htmlspecialchars($latestLogHours) ?> ชม.</strong>
                        <span class="status-chip <?= htmlspecialchars($latestStatusMeta['class']) ?>"><?= htmlspecialchars($latestStatusMeta['label']) ?></span>
                    </div>
                    <p class="time-kpi-subtitle">รายการที่บันทึกของวันนี้</p>
                </div>
            </article>
        </section>

        <div id="timePageMessage" class="time-message-stack">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-0"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
        </div>

        <section class="time-workspace-grid">
            <div class="min-w-0">
                <article class="dash-card time-surface time-entry-surface" id="time-entry-panel">
                    <div class="time-surface-header">
                        <div>
                            <span class="time-surface-eyebrow">Today Entry</span>
                            <h2 class="time-surface-title">บันทึกเวรของวันนี้</h2>
                            <p class="time-surface-copy">เลือกแผนก รูปแบบเวร และตรวจสอบชั่วโมงก่อนบันทึกจริง</p>
                        </div>
                        <span class="time-date-pill"><?= htmlspecialchars(app_format_thai_date($today)) ?></span>
                    </div>

                    <?php if ($canViewDepartmentReports): ?>
                        <form method="post" class="time-inline-form">
                            <div class="time-inline-field">
                                <label class="form-label fw-semibold small text-muted">สถานที่/แผนกที่ใช้บันทึก</label>
                                <select name="department_id" class="form-select">
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= $department['id'] ?>" <?= (int) $departmentId === (int) $department['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($department['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button name="change_department" class="dash-btn dash-btn-secondary time-inline-submit">เปลี่ยนแผนก</button>
                        </form>
                    <?php else: ?>
                        <div class="time-department-card">
                            <div>
                                <div class="small text-muted">แผนกปัจจุบัน</div>
                                <div class="fw-bold"><?= htmlspecialchars($departmentLabel) ?></div>
                            </div>
                            <span class="time-chip-muted">ข้อมูลปัจจุบัน</span>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="row g-3" id="createLogForm" data-global-loading-form data-loading-message="กำลังบันทึกเวลา...">
                        <input type="hidden" name="create_time_log" value="1">
                        <input type="hidden" name="history_date" value="<?= htmlspecialchars($searchDate) ?>">
                        <input type="hidden" name="history_date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        <input type="hidden" name="history_date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        <input type="hidden" name="history_status" value="<?= htmlspecialchars($historyStatus) ?>">
                        <input type="hidden" name="history_query" value="<?= htmlspecialchars($historyQuery) ?>">
                        <input type="hidden" name="history_per_page" value="<?= (int) $limit ?>">
                        <input type="hidden" name="history_page" value="<?= (int) $page ?>">

                        <div class="col-12">
                            <div class="time-section-label">Preset Shift</div>
                            <div class="preset-wrap time-preset-wrap">
                                <?php foreach ($shiftPresets as $presetKey => $preset): ?>
                                    <button type="button" class="preset-btn" data-shift-target="create" data-shift-key="<?= htmlspecialchars($presetKey) ?>" data-time-in="<?= htmlspecialchars($preset['time_in']) ?>" data-time-out="<?= htmlspecialchars($preset['time_out']) ?>">
                                        <strong><?= htmlspecialchars($preset['label']) ?></strong>
                                        <span><?= htmlspecialchars($preset['time_in']) ?> - <?= htmlspecialchars($preset['time_out']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                                <button type="button" class="preset-btn preset-btn-custom" data-shift-custom="1">
                                    <strong>อื่นๆ</strong>
                                    <span>กำหนดเอง</span>
                                </button>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="time-input-focus-card">
                                <div class="time-section-label">เวลาปฏิบัติงาน</div>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold small text-muted">เวลาเริ่มต้น</label>
                                        <div class="time-select-grid">
                                            <select name="manual_time_in_hour" id="manual_time_in_hour" class="form-select">
                                                <?php foreach ($hourOptions as $hourOption): ?>
                                                    <option value="<?= $hourOption ?>" <?= $newForm['time_in_hour'] === $hourOption ? 'selected' : '' ?>><?= $hourOption ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="time-colon">:</div>
                                            <select name="manual_time_in_minute" id="manual_time_in_minute" class="form-select">
                                                <?php foreach ($minuteOptions as $minuteOption): ?>
                                                    <option value="<?= $minuteOption ?>" <?= $newForm['time_in_minute'] === $minuteOption ? 'selected' : '' ?>><?= $minuteOption ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold small text-muted">เวลาออก</label>
                                        <div class="time-select-grid">
                                            <select name="manual_time_out_hour" id="manual_time_out_hour" class="form-select">
                                                <?php foreach ($hourOptions as $hourOption): ?>
                                                    <option value="<?= $hourOption ?>" <?= $newForm['time_out_hour'] === $hourOption ? 'selected' : '' ?>><?= $hourOption ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="time-colon">:</div>
                                            <select name="manual_time_out_minute" id="manual_time_out_minute" class="form-select">
                                                <?php foreach ($minuteOptions as $minuteOption): ?>
                                                    <option value="<?= $minuteOption ?>" <?= $newForm['time_out_minute'] === $minuteOption ? 'selected' : '' ?>><?= $minuteOption ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="time-input-caption">ใช้รูปแบบ 24 ชั่วโมงเท่านั้น เพื่อลดความสับสนในการลงเวลาเวร</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="selection-preview">
                                <div class="small text-muted">ช่วงเวลาที่จะบันทึก</div>
                                <strong id="createTimePreview"></strong>
                                <div class="time-mini-metrics">
                                    <div class="shift-summary-card">
                                        <span class="label">ชั่วโมงโดยประมาณ</span>
                                        <span class="value" id="createEstimatedHours">8.00 ชม.</span>
                                    </div>
                                    <div class="shift-summary-card">
                                        <span class="label">พัก</span>
                                        <span class="value">0.00 ชม.</span>
                                    </div>
                                    <div class="shift-summary-card">
                                        <span class="label">ชั่วโมงสุทธิ</span>
                                        <span class="value" id="createNetHours">8.00 ชม.</span>
                                    </div>
                                </div>
                                <div class="time-selection-footnote">
                                    ช่วงเวร <span id="createShiftLabel" class="fw-semibold text-dark">เช้า</span>
                                    <span class="time-chip-muted">ชั่วโมงรวมโดยประมาณ <span id="createHoursPreview" class="fw-semibold text-dark">0.00 ชม.</span></span>
                                    <span id="createOvernightFlag" class="time-chip-muted">ไม่ข้ามวัน</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">หมายเหตุ / ภารกิจ</label>
                            <textarea name="note" class="form-control" rows="4" placeholder="ระบุรายละเอียดงาน ภารกิจ หรือหมายเหตุเพิ่มเติม"><?= htmlspecialchars($newForm['note']) ?></textarea>
                        </div>

                        <div class="col-12 d-grid">
                            <button name="save_all_time" value="1" class="dash-btn dash-btn-primary w-full">บันทึกการปฏิบัติงาน</button>
                        </div>
                    </form>
                </article>
            </div>

            <div class="min-w-0">
                <article class="dash-card time-surface time-history-surface" id="time-history-panel">
                    <div class="time-surface-header time-surface-header-wide">
                        <div>
                            <span class="time-surface-eyebrow">History & Review</span>
                            <h2 class="time-surface-title">ประวัติย้อนหลัง</h2>
                            <p class="time-surface-copy">ค้นหารายการย้อนหลังตามช่วงวันที่ สถานะ หรือคำที่เกี่ยวข้อง แล้วเปิดดูรายละเอียดหรือแก้ไขได้ทันที</p>
                        </div>
                        <div class="time-action-stack">
                            <a href="daily_schedule.php" class="dash-btn dash-btn-ghost">ดูเวรวันนี้ <i class="bi bi-arrow-right"></i></a>
                            <a href="my_reports.php" class="dash-btn dash-btn-ghost">เปิดรายงานของฉัน <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>

                    <form method="get" class="time-filter-grid" id="timeHistoryFilterForm" data-page-state-key="time_history">
                        <input type="hidden" name="p" value="<?= (int) $page ?>">
                        <div class="time-filter-field">
                            <label class="form-label fw-semibold small text-muted">ตั้งแต่วันที่</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="time-filter-field">
                            <label class="form-label fw-semibold small text-muted">ถึงวันที่</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="time-filter-field">
                            <label class="form-label fw-semibold small text-muted">สถานะ</label>
                            <select name="status" class="form-select">
                                <option value="all" <?= $historyStatus === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                                <option value="pending" <?= $historyStatus === 'pending' ? 'selected' : '' ?>>รอตรวจ</option>
                                <option value="approved" <?= $historyStatus === 'approved' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                                <option value="issue" <?= $historyStatus === 'issue' ? 'selected' : '' ?>>ต้องแก้ไข</option>
                            </select>
                        </div>
                        <div class="time-filter-field time-filter-field-wide">
                            <label class="form-label fw-semibold small text-muted">ค้นหา</label>
                            <input type="search" name="query" class="form-control" value="<?= htmlspecialchars($historyQuery) ?>" placeholder="ค้นหาแผนกหรือหมายเหตุ">
                        </div>
                        <div class="time-filter-field">
                            <label class="form-label fw-semibold small text-muted">แสดง</label>
                            <select name="per_page" class="form-select">
                                <?php foreach ([10, 20, 50, 100] as $size): ?>
                                    <option value="<?= $size ?>" <?= $limit === $size ? 'selected' : '' ?>><?= $size ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="time-filter-submit">
                            <button class="dash-btn dash-btn-secondary w-full">ค้นหา</button>
                        </div>
                    </form>

                    <div class="time-history-header-row">
                        <div class="time-history-caption">ทั้งหมด <?= number_format($totalRows) ?> รายการ<?= $historyQuery !== '' ? ' • ค้นหา "' . htmlspecialchars($historyQuery) . '"' : '' ?></div>
                        <?php if ($historyStatus !== 'all' || $dateFrom !== '' || $dateTo !== '' || $historyQuery !== ''): ?>
                            <a href="time.php" class="dash-btn dash-btn-ghost">ล้างตัวกรอง <i class="bi bi-arrow-counterclockwise"></i></a>
                        <?php endif; ?>
                    </div>

                    <div id="timeHistoryList" class="time-history-list"><?php require __DIR__ . '/../partials/time/history_list.php'; ?></div>
                </article>
            </div>
        </section>

        <section class="dash-card time-bottom-strip" aria-label="สรุปข้อมูลวันนี้">
            <div class="time-bottom-heading">
                <h3>สรุปข้อมูลวันนี้</h3>
            </div>
            <article class="time-bottom-item">
                <span class="time-bottom-label">เวรที่บันทึกแล้ว</span>
                <strong class="time-bottom-value"><?= number_format($todayShiftCount) ?> เวร</strong>
                <span class="time-bottom-meta">อัปเดตจากรายการของวันปัจจุบัน</span>
            </article>
            <article class="time-bottom-item">
                <span class="time-bottom-label">ชั่วโมงรวมวันนี้</span>
                <strong class="time-bottom-value"><?= number_format($todayHours, 2) ?> ชม.</strong>
                <span class="time-bottom-meta">รวมทุกเวรที่บันทึกของวันนี้</span>
            </article>
            <article class="time-bottom-item">
                <span class="time-bottom-label">สถานะล่าสุด</span>
                <strong class="time-bottom-value"><?= htmlspecialchars($latestStatusMeta['label']) ?></strong>
                <span class="time-bottom-meta">รวมทุกเวรที่บันทึกของวันนี้</span>
            </article>
            <article class="time-bottom-item">
                <span class="time-bottom-label">คงเหลือเป้าหมายเดือนนี้</span>
                <strong class="time-bottom-value"><?= number_format($monthlyRemainingHours, 2) ?> ชม. / <?= number_format($monthlyTargetHours, 2) ?> ชม.</strong>
                <span class="time-bottom-meta">เป้าหมายเดือนนี้ <?= $monthlyProgressPercent ?>%</span>
            </article>
            <article class="time-bottom-progress">
                <div class="time-bottom-progress-head">
                    <span class="time-bottom-label">เป้าหมายเดือนนี้</span>
                    <strong><?= $monthlyProgressPercent ?>%</strong>
                </div>
                <div class="time-progress-bar" role="progressbar" aria-valuenow="<?= $monthlyProgressPercent ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: <?= $monthlyProgressPercent ?>%;"></span>
                </div>
                <span class="time-bottom-meta"><?= number_format($monthHours, 2) ?> / <?= number_format($monthlyTargetHours, 2) ?> ชม.</span>
            </article>
        </section>
    </div>
</main>
<?php if ($editLog): ?>
    <div class="modal fade" id="editTimeLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content time-modal-surface border-0 rounded-4 shadow">
                <?php require __DIR__ . '/../partials/time/edit_modal_body.php'; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<div class="modal fade" id="ajaxEditTimeLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content time-modal-surface border-0 rounded-4 shadow" id="ajaxEditTimeLogModalContent">
            <div class="modal-body text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/time-page.js"></script>
<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('dateLabel').textContent = now.toLocaleDateString('th-TH', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        document.getElementById('clock').textContent = now.toLocaleTimeString('th-TH', { hour12: false });
    }
    updateClock();
    setInterval(updateClock, 1000);

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function resolveShiftLabel(startHour, startMinute, endHour, endMinute) {
        const start = `${pad(startHour)}:${pad(startMinute)}`;
        const end = `${pad(endHour)}:${pad(endMinute)}`;
        if (start === '08:30' && end === '16:30') return 'เช้า';
        if (start === '16:30' && end === '00:30') return 'เช้า';
        if (start === '00:30' && end === '08:30') return 'ดึก';
        return 'เช้า?';
    }

    function setActivePreset(start, end) {
        document.querySelectorAll('[data-shift-target="create"]').forEach((button) => {
            const isActive = button.dataset.timeIn === start && button.dataset.timeOut === end;
            button.classList.toggle('is-active', isActive);
        });
        document.querySelectorAll('[data-shift-custom="1"]').forEach((button) => {
            button.classList.remove('is-active');
        });
    }

    function updateCreatePreview() {
        const preview = document.getElementById('createTimePreview');
        const hoursPreview = document.getElementById('createHoursPreview');
        const shiftLabel = document.getElementById('createShiftLabel');
        const estimatedHours = document.getElementById('createEstimatedHours');
        const netHours = document.getElementById('createNetHours');
        const overnightFlag = document.getElementById('createOvernightFlag');
        if (!preview) return;
        const startHourField = document.getElementById('manual_time_in_hour');
        const startMinuteField = document.getElementById('manual_time_in_minute');
        const endHourField = document.getElementById('manual_time_out_hour');
        const endMinuteField = document.getElementById('manual_time_out_minute');
        const start = `${startHourField.value}:${startMinuteField.value}`;
        const end = `${endHourField.value}:${endMinuteField.value}`;
        preview.textContent = `${start} - ${end}`;
        const startHour = parseInt(startHourField.value, 10);
        const startMinute = parseInt(startMinuteField.value, 10);
        const endHour = parseInt(endHourField.value, 10);
        const endMinute = parseInt(endMinuteField.value, 10);
        let startTotal = (startHour * 60) + startMinute;
        let endTotal = (endHour * 60) + endMinute;
        const isOvernight = endTotal < startTotal;
        if (isOvernight) {
            endTotal += 1440;
        }
        const totalHours = ((endTotal - startTotal) / 60).toFixed(2);
        if (hoursPreview) {
            hoursPreview.textContent = `${totalHours} ชม.`;
        }
        if (estimatedHours) {
            estimatedHours.textContent = `${totalHours} ชม.`;
        }
        if (netHours) {
            netHours.textContent = `${totalHours} ชม.`;
        }
        if (overnightFlag) {
            overnightFlag.textContent = isOvernight ? 'ข้ามวัน' : 'ไม่ข้ามวัน';
        }
        if (shiftLabel) {
            shiftLabel.textContent = resolveShiftLabel(startHour, startMinute, endHour, endMinute);
        }
        setActivePreset(start, end);
    }

    document.querySelectorAll('[data-shift-target=\"create\"]').forEach((button) => {
        button.addEventListener('click', function () {
            const [inHour, inMinute] = this.dataset.timeIn.split(':');
            const [outHour, outMinute] = this.dataset.timeOut.split(':');
            document.getElementById('manual_time_in_hour').value = inHour;
            document.getElementById('manual_time_in_minute').value = inMinute;
            document.getElementById('manual_time_out_hour').value = outHour;
            document.getElementById('manual_time_out_minute').value = outMinute;
            updateCreatePreview();
        });
    });

    document.querySelectorAll('[data-shift-custom=\"1\"]').forEach((button) => {
        button.addEventListener('click', function () {
            document.querySelectorAll('[data-shift-target=\"create\"]').forEach((item) => {
                item.classList.remove('is-active');
            });
            this.classList.add('is-active');
            document.getElementById('manual_time_in_hour').focus();
            document.getElementById('createShiftLabel').textContent = 'กำหนดเอง';
        });
    });

    ['manual_time_in_hour', 'manual_time_in_minute', 'manual_time_out_hour', 'manual_time_out_minute']
        .forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', updateCreatePreview);
        });

    updateCreatePreview();
    <?php if ($editLog): ?>
    const editModalElement = document.getElementById('editTimeLogModal');
    if (editModalElement) {
        new bootstrap.Modal(editModalElement).show();
    }
    <?php endif; ?>
    TimePageAsync.init({ historyId: 'timeHistoryList', filterFormId: 'timeHistoryFilterForm', modalId: 'ajaxEditTimeLogModal', modalContentId: 'ajaxEditTimeLogModalContent', messageId: 'timePageMessage' });

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
<script src="../assets/js/notifications.js"></script>
</body>
</html>

