<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';
require_once __DIR__ . '/../includes/notification_helpers.php';
require_once __DIR__ . '/../includes/shift_schedule_service.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

if (!app_can_manage_shift_schedules()) {
    app_redirect_to_dashboard('dashboard.php');
}

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$message = '';
$messageType = 'success';
$scope = app_shift_access_scope($conn);
$departmentOptions = $scope['departments'];
if ($scope['is_global']) {
    // Admin / global-access users: default to their own department, fallback to first in list
    $userDeptId = app_get_current_user_department_id($conn);
    $defaultDepartmentId = ($userDeptId > 0 && in_array($userDeptId, $scope['ids'], true))
        ? $userDeptId
        : (int) ($scope['ids'][0] ?? 0);
} else {
    // Dept-scoped users: only one dept available
    $defaultDepartmentId = (int) ($scope['ids'][0] ?? 0);
}
$selectedDepartmentId = (int) ($_REQUEST['department_id'] ?? $defaultDepartmentId);
if (!in_array($selectedDepartmentId, $scope['ids'], true)) {
    $selectedDepartmentId = $defaultDepartmentId;
}

$selectedMonth = (int) ($_REQUEST['month'] ?? date('n'));
$selectedYearBe = (int) ($_REQUEST['year_be'] ?? ((int) date('Y') + 543));
$selectedYear = $selectedYearBe > 2400 ? $selectedYearBe - 543 : $selectedYearBe;
$selectedMonth = max(1, min(12, $selectedMonth));
$selectedYear = max(2000, min(2100, $selectedYear));
$selectedYearBe = $selectedYear + 543;
$csrfToken = app_csrf_token('shift_schedules');

function app_shift_is_ajax_request(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function app_shift_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_shift_public_error_message(Throwable $e): string
{
    $message = $e->getMessage();
    if ($e instanceof PDOException || stripos($message, 'SQLSTATE') !== false) {
        return 'ไม่สามารถบันทึกข้อมูลตารางเวรได้ กรุณารีเฟรชหน้าแล้วลองอีกครั้ง';
    }

    return $message;
}

function app_shift_schedule_page_url(int $departmentId, int $month, int $yearBe): string
{
    return 'shift_schedules.php?' . http_build_query([
        'department_id' => $departmentId,
        'month' => $month,
        'year_be' => $yearBe,
    ]);
}

function app_shift_set_flash_message(string $message, string $type = 'success'): void
{
    $_SESSION['shift_schedule_flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjaxRequest = app_shift_is_ajax_request();
    try {
        if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'shift_schedules')) {
            throw new RuntimeException('โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_draft') {
            $selectedDepartmentId = (int) ($_POST['department_id'] ?? $selectedDepartmentId);
            $selectedMonth = (int) ($_POST['month'] ?? $selectedMonth);
            $selectedYearBe = (int) ($_POST['year_be'] ?? $selectedYearBe);
            $selectedYear = $selectedYearBe > 2400 ? $selectedYearBe - 543 : $selectedYearBe;
            $result = app_create_or_update_schedule(
                $conn,
                $selectedDepartmentId,
                (string) ($_POST['schedule_date'] ?? ''),
                (string) ($_POST['shift_type'] ?? ''),
                (string) ($_POST['start_time'] ?? ''),
                (string) ($_POST['end_time'] ?? ''),
                $_POST['staff_ids'] ?? [],
                (string) ($_POST['note'] ?? ''),
                $currentUserId
            );
            if ($isAjaxRequest) {
                app_shift_json_response(['success' => true] + $result);
            }
            $message = (string) ($result['message'] ?? 'บันทึก draft ตารางเวรเรียบร้อย');
            $messageType = 'success';
            app_shift_set_flash_message($message, $messageType);
            header('Location: ' . app_shift_schedule_page_url($selectedDepartmentId, $selectedMonth, $selectedYearBe));
            exit;
        } elseif ($action === 'publish_month') {
            $result = app_publish_monthly_schedule($conn, $selectedDepartmentId, $selectedMonth, $selectedYear, $currentUserId);
            $message = $result['message'];
            $messageType = 'success';
            app_shift_set_flash_message($message, $messageType);
            header('Location: ' . app_shift_schedule_page_url($selectedDepartmentId, $selectedMonth, $selectedYearBe));
            exit;
        } elseif ($action === 'cancel_assignment') {
            $result = app_cancel_shift_assignment($conn, (int) ($_POST['assignment_id'] ?? 0), $currentUserId);
            if ($isAjaxRequest) {
                app_shift_json_response(['success' => true] + $result);
            }
            $message = (string) ($result['message'] ?? 'ยกเลิกเจ้าหน้าที่จากเวรเรียบร้อย');
            $messageType = 'success';
            app_shift_set_flash_message($message, $messageType);
            header('Location: ' . app_shift_schedule_page_url($selectedDepartmentId, $selectedMonth, $selectedYearBe));
            exit;
        } elseif ($action === 'delete_draft') {
            $result = app_delete_shift_schedule_draft($conn, (int) ($_POST['schedule_id'] ?? 0), $currentUserId);
            if ($isAjaxRequest) {
                app_shift_json_response($result);
            }
            $message = (string) ($result['message'] ?? 'ลบดราฟเรียบร้อยแล้ว');
            $messageType = 'success';
            app_shift_set_flash_message($message, $messageType);
            header('Location: ' . app_shift_schedule_page_url($selectedDepartmentId, $selectedMonth, $selectedYearBe));
            exit;
        }
    } catch (Throwable $e) {
        $publicError = app_shift_public_error_message($e);
        if ($isAjaxRequest ?? false) {
            app_shift_json_response([
                'success' => false,
                'error' => $publicError,
            ], 400);
        }
        $message = $publicError;
        $messageType = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['shift_schedule_flash']) && is_array($_SESSION['shift_schedule_flash'])) {
    $message = (string) ($_SESSION['shift_schedule_flash']['message'] ?? '');
    $messageType = (string) ($_SESSION['shift_schedule_flash']['type'] ?? 'success');
    unset($_SESSION['shift_schedule_flash']);
}

$monthOptions = app_get_thai_month_select_options();
$shiftTypes = app_shift_schedule_types();
$schedules = $selectedDepartmentId > 0 ? app_get_monthly_schedules($conn, $selectedDepartmentId, $selectedMonth, $selectedYear) : [];
$stats = app_shift_schedule_stats($schedules);
$staffRows = $selectedDepartmentId > 0 ? app_shift_fetch_staff($conn, $selectedDepartmentId) : [];

$schedulesByDate = [];
foreach ($schedules as $schedule) {
    $schedulesByDate[$schedule['schedule_date']][] = $schedule;
}

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth));
$daysInMonth = (int) $firstDay->format('t');
$startOffset = (int) $firstDay->format('N') - 1;
$selectedDepartmentName = '-';
foreach ($departmentOptions as $department) {
    if ((int) $department['id'] === $selectedDepartmentId) {
        $selectedDepartmentName = (string) $department['department_name'];
        break;
    }
}
$monthLabel = (string) ($monthOptions[$selectedMonth] ?? '');
$publishSummary = sprintf(
    'เผยแพร่ตารางเวร %s %d แผนก %s จำนวนเวร %d รายการ เจ้าหน้าที่ %d คน',
    $monthLabel,
    $selectedYearBe,
    $selectedDepartmentName,
    $stats['draft_count'],
    $stats['staff_count']
);

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

function app_shift_render_staff_avatar(?string $imageUrl, string $name, string $initials, string $className = 'shift-staff-avatar'): string
{
    $safeName = htmlspecialchars($name !== '' ? $name : 'staff', ENT_QUOTES, 'UTF-8');
    $safeInitials = htmlspecialchars($initials !== '' ? $initials : mb_substr($name !== '' ? $name : 'U', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $safeClass = htmlspecialchars($className, ENT_QUOTES, 'UTF-8');
    $html = '<span class="' . $safeClass . '" aria-hidden="true"><span class="shift-staff-avatar-fallback">' . $safeInitials . '</span>';
    if ($imageUrl) {
        $html .= '<img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="Profile image ' . $safeName . '" loading="lazy" onerror="this.remove();">';
    }
    $html .= '</span>';

    return $html;
}

function app_shift_detail_shift_class(string $shiftType): string
{
    return match ($shiftType) {
        'morning' => 'morning',
        'afternoon', 'evening' => 'afternoon',
        'night' => 'night',
        default => 'other',
    };
}

function app_shift_detail_status_label(string $status): string
{
    return match ($status) {
        'published' => 'PUBLISHED',
        'draft' => 'DRAFT',
        default => strtoupper($status !== '' ? $status : 'UNKNOWN'),
    };
}

function app_shift_day_detail_payload(string $date, string $departmentName, array $daySchedules, array $shiftTypes): string
{
    $schedules = [];
    $uniqueStaffIds = [];
    $draftCount = 0;
    $publishedCount = 0;

    foreach ($daySchedules as $schedule) {
        $status = (string) ($schedule['status'] ?? '');
        if ($status === 'draft') {
            $draftCount++;
        } elseif ($status === 'published') {
            $publishedCount++;
        }

        $shiftType = (string) ($schedule['shift_type'] ?? 'other');
        $assignments = [];
        foreach (($schedule['assignments'] ?? []) as $assignment) {
            $staffId = (int) ($assignment['staff_id'] ?? 0);
            if ($staffId > 0) {
                $uniqueStaffIds[$staffId] = true;
            }

            $staffName = (string) ($assignment['staff_name'] ?? '');
            $assignments[] = [
                'assignmentId' => (int) ($assignment['id'] ?? 0),
                'staffId' => $staffId,
                'name' => $staffName !== '' ? $staffName : '-',
                'position' => (string) ($assignment['staff_position'] ?? ''),
                'department' => (string) ($schedule['department_name'] ?? $departmentName),
                'avatarUrl' => $assignment['staff_profile_image_url'] ?? null,
                'initials' => (string) ($assignment['staff_initials'] ?? mb_substr($staffName !== '' ? $staffName : 'U', 0, 1, 'UTF-8')),
            ];
        }

        $schedules[] = [
            'scheduleId' => (int) ($schedule['id'] ?? 0),
            'shiftType' => $shiftType,
            'shiftClass' => app_shift_detail_shift_class($shiftType),
            'shiftLabel' => (string) ($shiftTypes[$shiftType]['label'] ?? $shiftType),
            'startTime' => (string) ($schedule['start_time'] ?? ''),
            'endTime' => (string) ($schedule['end_time'] ?? ''),
            'plannedHours' => (float) ($schedule['planned_hours'] ?? 0),
            'status' => $status,
            'statusLabel' => app_shift_detail_status_label($status),
            'department' => (string) ($schedule['department_name'] ?? $departmentName),
            'staffCount' => count($assignments),
            'staff' => $assignments,
        ];
    }

    $payload = [
        'date' => $date,
        'dateLabel' => app_format_thai_date($date, true),
        'department' => $departmentName,
        'summary' => [
            'scheduleCount' => count($schedules),
            'staffCount' => count($uniqueStaffIds),
            'draftCount' => $draftCount,
            'publishedCount' => $publishedCount,
        ],
        'schedules' => $schedules,
    ];

    return htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดตารางเวรรายเดือน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell shift-schedules-page-shell">
<?php render_dashboard_sidebar('shift_schedules.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main shift-schedules-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Schedule Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">จัดตารางเวรรายเดือน</h1>
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

    <div class="shift-schedule-frame">
        <?php if ($message !== ''): ?>
            <div
                data-shift-initial-toast
                data-toast-type="<?= htmlspecialchars($messageType) ?>"
                data-toast-message="<?= htmlspecialchars($message) ?>"
                hidden
            ></div>
        <?php endif; ?>

        <section class="shift-schedule-hero">
            <div>
                <span class="dash-hero-pill"><i class="bi bi-calendar2-plus"></i> Monthly Shift Plan</span>
                <h2>จัดตารางเวรรายเดือน</h2>
                <p>วางแผนเวรล่วงหน้าสำหรับเจ้าหน้าที่ในแผนก โดยแยกแผนเวรออกจากรายการลงเวรจริง</p>
            </div>
            <div class="shift-schedule-stats">
                <div><span>เวรในเดือน</span><strong><?= number_format($stats['schedule_count']) ?></strong></div>
                <div><span>เจ้าหน้าที่</span><strong><?= number_format($stats['staff_count']) ?></strong></div>
                <div><span>Draft</span><strong><?= number_format($stats['draft_count']) ?></strong></div>
                <div><span>Published</span><strong><?= number_format($stats['published_count']) ?></strong></div>
            </div>
        </section>

        <section class="shift-schedule-toolbar">
            <form method="get" class="shift-filter-form" data-filter-form>
                <label>
                    <span>เดือน</span>
                    <select name="month" class="form-select" data-filter-select>
                        <?php foreach ($monthOptions as $value => $label): ?>
                            <option value="<?= (int) $value ?>" <?= (int) $value === $selectedMonth ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>ปี พ.ศ.</span>
                    <input type="number" name="year_be" class="form-control" min="2543" max="2643" value="<?= (int) $selectedYearBe ?>" data-filter-year>
                </label>
                <label>
                    <span>แผนก</span>
                    <select name="department_id" class="form-select" <?= $scope['is_global'] ? 'data-filter-select' : 'disabled' ?>>
                        <?php foreach ($departmentOptions as $department): ?>
                            <option value="<?= (int) $department['id'] ?>" <?= (int) $department['id'] === $selectedDepartmentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($department['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$scope['is_global']): ?>
                        <input type="hidden" name="department_id" value="<?= (int) $selectedDepartmentId ?>">
                    <?php endif; ?>
                </label>
                <button type="submit" class="dash-btn dash-btn-ghost" title="รีเฟรชตาราง">
                    <i class="bi bi-arrow-clockwise"></i> รีเฟรช
                </button>
            </form>
            <form method="post" onsubmit="return confirm(<?= htmlspecialchars(json_encode($publishSummary, JSON_UNESCAPED_UNICODE)) ?>);">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="publish_month">
                <input type="hidden" name="department_id" value="<?= (int) $selectedDepartmentId ?>">
                <input type="hidden" name="month" value="<?= (int) $selectedMonth ?>">
                <input type="hidden" name="year_be" value="<?= (int) $selectedYearBe ?>">
                <button type="submit" class="dash-btn dash-btn-secondary" <?= $stats['draft_count'] <= 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-megaphone"></i> เผยแพร่ตารางเวรประจำเดือน
                </button>
            </form>
        </section>

        <section class="shift-calendar" aria-label="ตารางเวรรายเดือน">
            <?php foreach (['จันทร์', 'อังคาร', 'พุธ', 'พฤหัส', 'ศุกร์', 'เสาร์', 'อาทิตย์'] as $weekday): ?>
                <div class="shift-calendar-weekday"><?= htmlspecialchars($weekday) ?></div>
            <?php endforeach; ?>
            <?php for ($blank = 0; $blank < $startOffset; $blank++): ?>
                <div class="shift-day-cell is-empty"></div>
            <?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                $date = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
                $daySchedules = $schedulesByDate[$date] ?? [];
                ?>
                <article class="shift-day-cell">
                    <div class="shift-day-head">
                        <strong><?= (int) $day ?></strong>
                        <div class="shift-day-actions">
                            <?php if ($daySchedules): ?>
                                <button type="button" class="shift-day-detail-button" data-shift-day-detail-open data-day-detail='<?= app_shift_day_detail_payload($date, $selectedDepartmentName, $daySchedules, $shiftTypes) ?>' aria-label="ดูรายละเอียดเวรวันที่ <?= (int) $day ?>">
                                    รายละเอียด
                                </button>
                            <?php endif; ?>
                            <button type="button" class="shift-add-button" data-shift-open data-date="<?= htmlspecialchars($date) ?>" aria-label="เพิ่มเวรวันที่ <?= (int) $day ?>">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>
                    <?php if (!$daySchedules): ?>
                        <div class="shift-empty">ยังไม่มีเวร</div>
                    <?php endif; ?>
                    <?php foreach ($daySchedules as $schedule): ?>
                        <div class="shift-slot" data-shift-slot data-schedule-id="<?= (int) $schedule['id'] ?>" data-status="<?= htmlspecialchars($schedule['status']) ?>">
                            <div class="shift-slot-head">
                                <span class="shift-slot-title"><?= htmlspecialchars($shiftTypes[$schedule['shift_type']]['label'] ?? $schedule['shift_type']) ?></span>
                                <span class="shift-status is-<?= htmlspecialchars($schedule['status']) ?>"><?= htmlspecialchars($schedule['status']) ?></span>
                                <?php if (($schedule['status'] ?? '') === 'draft'): ?>
                                    <form method="post" class="shift-draft-delete-form" data-draft-delete-form>
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_draft">
                                        <input type="hidden" name="schedule_id" value="<?= (int) $schedule['id'] ?>">
                                        <input type="hidden" name="department_id" value="<?= (int) $selectedDepartmentId ?>">
                                        <input type="hidden" name="month" value="<?= (int) $selectedMonth ?>">
                                        <input type="hidden" name="year_be" value="<?= (int) $selectedYearBe ?>">
                                        <button type="submit" class="shift-draft-delete-button" title="ลบดราฟนี้" aria-label="ลบดราฟนี้"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="shift-time"><?= htmlspecialchars($schedule['start_time']) ?>-<?= htmlspecialchars($schedule['end_time']) ?> · <?= number_format((float) $schedule['planned_hours'], 2) ?> ชม.</div>
                            <div class="shift-staff-list">
                                <?php foreach ($schedule['assignments'] as $assignment): ?>
                                    <span class="shift-staff-badge" data-assignment-id="<?= (int) $assignment['id'] ?>">
                                        <?= app_shift_render_staff_avatar($assignment['staff_profile_image_url'] ?? null, (string) ($assignment['staff_name'] ?? ''), (string) ($assignment['staff_initials'] ?? ''), 'shift-staff-avatar shift-staff-avatar-mini') ?>
                                        <span class="shift-staff-badge-name"><?= htmlspecialchars($assignment['staff_name']) ?></span>
                                        <?php if (($schedule['status'] ?? '') === 'draft'): ?>
                                            <form method="post" class="shift-inline-form" data-assignment-delete-form>
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="cancel_assignment">
                                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                                <input type="hidden" name="department_id" value="<?= (int) $selectedDepartmentId ?>">
                                                <input type="hidden" name="month" value="<?= (int) $selectedMonth ?>">
                                                <input type="hidden" name="year_be" value="<?= (int) $selectedYearBe ?>">
                                                <button type="submit" title="ลบ <?= htmlspecialchars($assignment['staff_name']) ?> ออกจากดราฟ" aria-label="ลบ <?= htmlspecialchars($assignment['staff_name']) ?> ออกจากดราฟ"><i class="bi bi-x"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </article>
            <?php endfor; ?>
        </section>
    </div>
</main>

<div class="shift-modal-backdrop" data-shift-modal hidden>
    <div class="shift-modal" role="dialog" aria-modal="true" aria-labelledby="shiftModalTitle">
        <form method="post" class="shift-modal-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="save_draft">
            <input type="hidden" name="department_id" value="<?= (int) $selectedDepartmentId ?>">
            <input type="hidden" name="month" value="<?= (int) $selectedMonth ?>">
            <input type="hidden" name="year_be" value="<?= (int) $selectedYearBe ?>">
            <div class="shift-modal-head">
                <div>
                    <p>แผนก <?= htmlspecialchars($selectedDepartmentName) ?></p>
                    <h3 id="shiftModalTitle">เพิ่มเวรประจำวัน</h3>
                </div>
                <button type="button" class="dash-icon-button" data-shift-close aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="shift-modal-grid">
                <label>
                    <span>วันที่</span>
                    <input type="date" name="schedule_date" class="form-control" required data-shift-date>
                </label>
                <label>
                    <span>กะ</span>
                    <select name="shift_type" class="form-select" required data-shift-type>
                        <?php foreach ($shiftTypes as $key => $type): ?>
                            <option value="<?= htmlspecialchars($key) ?>" data-start="<?= htmlspecialchars($type['start']) ?>" data-end="<?= htmlspecialchars($type['end']) ?>">
                                <?= htmlspecialchars($type['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>เวลาเข้า</span>
                    <input type="time" name="start_time" class="form-control" value="08:30" required data-shift-start>
                </label>
                <label>
                    <span>เวลาออก</span>
                    <input type="time" name="end_time" class="form-control" value="16:30" required data-shift-end>
                </label>
            </div>
            <label class="shift-note-field">
                <span>หมายเหตุ</span>
                <textarea name="note" rows="2" class="form-control" placeholder="รายละเอียดเพิ่มเติมถ้ามี"></textarea>
            </label>
            <div class="shift-staff-picker">
                <div class="shift-staff-picker-head">
                    <div>
                        <strong>เลือกเจ้าหน้าที่</strong>
                        <span>เลือกได้หลายคนในกะเดียวกัน</span>
                    </div>
                    <span data-selected-count>0 คน</span>
                </div>
                <input type="search" class="form-control" placeholder="ค้นหาเจ้าหน้าที่" data-staff-search>
                <div class="shift-staff-options">
                    <?php foreach ($staffRows as $staff): ?>
                        <?php
                        $staffName = (string) ($staff['fullname'] ?? '-');
                        $staffPosition = (string) ($staff['position_name'] ?: app_role_label($staff['role'] ?? 'staff'));
                        $staffDepartment = (string) ($staff['department_name'] ?? $selectedDepartmentName);
                        $staffMeta = trim($staffPosition . ' / ' . $staffDepartment, ' /');
                        $staffInitials = (string) ($staff['initials'] ?? app_shift_staff_initials($staffName));
                        $staffAvatarUrl = $staff['profile_image_url'] ?? null;
                        ?>
                        <label class="shift-staff-option" data-staff-option data-staff-name="<?= htmlspecialchars($staffName) ?>" data-staff-meta="<?= htmlspecialchars($staffMeta) ?>" data-staff-initials="<?= htmlspecialchars($staffInitials) ?>" data-staff-avatar="<?= htmlspecialchars((string) $staffAvatarUrl) ?>" data-search="<?= htmlspecialchars(mb_strtolower($staffName . ' ' . $staffPosition . ' ' . $staffDepartment, 'UTF-8')) ?>">
                            <input type="checkbox" name="staff_ids[]" value="<?= (int) $staff['id'] ?>">
                            <?= app_shift_render_staff_avatar($staffAvatarUrl, $staffName, $staffInitials) ?>
                            <span class="shift-staff-option-copy">
                                <strong><?= htmlspecialchars($staffName) ?></strong>
                                <small><?= htmlspecialchars($staffMeta) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="shift-selected-staff" data-selected-staff hidden></div>
            </div>
            <div class="shift-modal-actions">
                <button type="button" class="dash-btn dash-btn-ghost" data-shift-close>ยกเลิก</button>
                <button type="submit" class="dash-btn dash-btn-primary"><i class="bi bi-save"></i> บันทึก draft</button>
            </div>
        </form>
    </div>
</div>

<div class="my-shift-day-modal-backdrop shift-day-detail-modal-backdrop" data-shift-day-detail-modal hidden>
    <div class="my-shift-day-modal shift-day-detail-modal" role="dialog" aria-modal="true" aria-labelledby="shiftDayDetailTitle">
        <div class="my-shift-modal-head">
            <div>
                <p>Daily Shift Overview</p>
                <h3 id="shiftDayDetailTitle">รายละเอียดเวรประจำวัน</h3>
                <span class="my-shift-day-modal-date" data-shift-day-detail-date>-</span>
            </div>
            <button type="button" class="dash-icon-button" data-shift-day-detail-close aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="my-shift-day-modal-meta shift-day-detail-meta">
            <span>แผนก <strong data-shift-day-detail-department>-</strong></span>
        </div>
        <div class="shift-day-detail-summary" data-shift-day-detail-summary></div>
        <div class="my-shift-day-modal-body" data-shift-day-detail-body>
            <div class="shift-day-detail-loading">กำลังโหลดรายละเอียดเวร...</div>
        </div>
        <div class="shift-day-detail-footer">
            <button type="button" class="dash-btn dash-btn-ghost" data-shift-day-detail-close>ปิด</button>
        </div>
    </div>
</div>

<?php render_staff_profile_modal(); ?>
<div class="shift-loading-overlay" data-shift-loading hidden aria-live="assertive" aria-busy="true">
    <div class="shift-loading-card" role="status">
        <span class="shift-loading-spinner" aria-hidden="true"></span>
        <strong data-shift-loading-title>โปรดรอสักครู่...</strong>
        <small data-shift-loading-detail>กำลังบันทึกและอัปเดตตารางเวร</small>
    </div>
</div>
<div class="shift-toast-region" data-shift-toast-region aria-live="polite" aria-atomic="true"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/notifications.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script>
(() => {
    const modal = document.querySelector('[data-shift-modal]');
    const dateInput = document.querySelector('[data-shift-date]');
    const shiftType = document.querySelector('[data-shift-type]');
    const startInput = document.querySelector('[data-shift-start]');
    const endInput = document.querySelector('[data-shift-end]');
    const countLabel = document.querySelector('[data-selected-count]');
    const selectedStaff = document.querySelector('[data-selected-staff]');
    const search = document.querySelector('[data-staff-search]');
    const options = Array.from(document.querySelectorAll('[data-staff-option]'));
    const checkboxes = Array.from(document.querySelectorAll('.shift-staff-option input[type="checkbox"]'));

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const staffAvatarHtml = (option, className = 'shift-staff-avatar shift-staff-avatar-chip') => {
        const name = option?.dataset.staffName || 'staff';
        const initials = option?.dataset.staffInitials || name.slice(0, 1) || 'U';
        const avatar = option?.dataset.staffAvatar || '';
        const fallback = `<span class="shift-staff-avatar-fallback">${escapeHtml(initials)}</span>`;
        const image = avatar
            ? `<img src="${escapeHtml(avatar)}" alt="Profile image ${escapeHtml(name)}" loading="lazy" onerror="this.remove();">`
            : '';
        return `<span class="${escapeHtml(className)}" aria-hidden="true">${fallback}${image}</span>`;
    };

    const renderSelectedStaff = () => {
        if (!selectedStaff) return;
        const selected = checkboxes.filter((input) => input.checked);
        selectedStaff.hidden = selected.length === 0;
        selectedStaff.innerHTML = selected.map((input) => {
            const option = input.closest('[data-staff-option]');
            const name = option?.dataset.staffName || input.value;
            const meta = option?.dataset.staffMeta || '';
            return `
                <button type="button" class="shift-selected-chip" data-selected-remove="${escapeHtml(input.value)}" title="Remove ${escapeHtml(name)} from shift" aria-label="Remove ${escapeHtml(name)} from shift">
                    ${staffAvatarHtml(option)}
                    <span><strong>${escapeHtml(name)}</strong><small>${escapeHtml(meta)}</small></span>
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            `;
        }).join('');
    };

    const updateCount = () => {
        const count = checkboxes.filter((input) => input.checked).length;
        if (countLabel) countLabel.textContent = `${count} คน`;
        checkboxes.forEach((input) => {
            input.closest('[data-staff-option]')?.classList.toggle('is-selected', input.checked);
        });
        renderSelectedStaff();
    };

    const applyShiftTime = () => {
        const selected = shiftType?.selectedOptions?.[0];
        if (!selected || !startInput || !endInput) return;
        startInput.value = selected.dataset.start || '08:30';
        endInput.value = selected.dataset.end || '16:30';
        const isCustom = shiftType.value === 'custom';
        startInput.readOnly = !isCustom;
        endInput.readOnly = !isCustom;
    };

    document.querySelectorAll('[data-shift-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal || !dateInput) return;
            dateInput.value = button.dataset.date || '';
            checkboxes.forEach((input) => { input.checked = false; });
            updateCount();
            applyShiftTime();
            modal.hidden = false;
            document.body.classList.add('overflow-hidden');
        });
    });

    document.querySelectorAll('[data-shift-close]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal) return;
            modal.hidden = true;
            document.body.classList.remove('overflow-hidden');
        });
    });

    shiftType?.addEventListener('change', applyShiftTime);
    checkboxes.forEach((input) => input.addEventListener('change', updateCount));
    selectedStaff?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-selected-remove]');
        if (!button) return;
        const input = checkboxes.find((checkbox) => checkbox.value === button.dataset.selectedRemove);
        if (input) {
            input.checked = false;
            updateCount();
        }
    });
    search?.addEventListener('input', () => {
        const keyword = search.value.trim().toLowerCase();
        options.forEach((option) => {
            option.hidden = keyword !== '' && !(option.dataset.search || '').toLowerCase().includes(keyword);
        });
    });
    applyShiftTime();
})();

(() => {
    const modal = document.querySelector('[data-shift-day-detail-modal]');
    if (!modal) return;

    const modalDate = modal.querySelector('[data-shift-day-detail-date]');
    const modalDepartment = modal.querySelector('[data-shift-day-detail-department]');
    const modalSummary = modal.querySelector('[data-shift-day-detail-summary]');
    const modalBody = modal.querySelector('[data-shift-day-detail-body]');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const statusLabel = (status) => {
        const normalized = String(status || '').toLowerCase();
        if (normalized === 'published') return 'PUBLISHED';
        if (normalized === 'draft') return 'DRAFT';
        return normalized ? normalized.toUpperCase() : '-';
    };

    const staffAvatarHtml = (staff) => {
        const name = staff?.name || '-';
        const initials = staff?.initials || name.slice(0, 1) || 'U';
        if (staff?.avatarUrl) {
            return `<span class="my-shift-day-avatar" data-initials="${escapeHtml(initials)}"><img src="${escapeHtml(staff.avatarUrl)}" alt="รูปโปรไฟล์ ${escapeHtml(name)}" onerror="this.parentElement.textContent=this.parentElement.dataset.initials||'U';"></span>`;
        }
        return `<span class="my-shift-day-avatar">${escapeHtml(initials)}</span>`;
    };

    const renderSummary = (summary = {}) => {
        if (!modalSummary) return;
        modalSummary.innerHTML = [
            ['เวรทั้งหมด', summary.scheduleCount ?? 0],
            ['เจ้าหน้าที่', summary.staffCount ?? 0],
            ['Draft', summary.draftCount ?? 0],
            ['Published', summary.publishedCount ?? 0],
        ].map(([label, value]) => `
            <div class="shift-day-detail-stat">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
            </div>
        `).join('');
    };

    const renderSchedule = (schedule) => {
        const status = String(schedule.status || '').toLowerCase();
        const staff = Array.isArray(schedule.staff) ? schedule.staff : [];
        const timeRange = `${schedule.startTime || '-'}-${schedule.endTime || '-'}`;
        const staffList = staff.length > 0
            ? staff.map((person) => `
                <article class="my-shift-day-staff">
                    ${staffAvatarHtml(person)}
                    <div class="my-shift-day-staff-main">
                        <strong>${escapeHtml(person.name || '-')}</strong>
                        <span>${escapeHtml(person.position || 'ไม่ระบุตำแหน่ง')}</span>
                    </div>
                    <div class="my-shift-day-staff-meta">
                        <span>${escapeHtml(person.department || schedule.department || '-')}</span>
                        <small><span class="shift-day-detail-status is-${escapeHtml(status || 'unknown')}">${escapeHtml(statusLabel(status))}</span></small>
                    </div>
                </article>
            `).join('')
            : '<div class="my-shift-day-empty-state">ยังไม่มีเจ้าหน้าที่ในเวรนี้</div>';

        return `
            <section class="my-shift-day-section is-shift-${escapeHtml(schedule.shiftClass || 'other')}">
                <div class="my-shift-day-section-head shift-day-detail-section-head">
                    <div>
                        <strong>${escapeHtml(schedule.shiftLabel || schedule.shiftType || 'เวรอื่น ๆ')}</strong>
                        <small>${escapeHtml(timeRange)} · ${escapeHtml(schedule.plannedHours || 0)} ชม.</small>
                    </div>
                    <div class="shift-day-detail-section-badges">
                        <span class="shift-day-detail-status is-${escapeHtml(status || 'unknown')}">${escapeHtml(statusLabel(status))}</span>
                        <span>${escapeHtml(staff.length)} คน</span>
                    </div>
                </div>
                <div class="my-shift-day-staff-list">
                    ${staffList}
                </div>
            </section>
        `;
    };

    const renderDetail = (payload) => {
        if (modalDate) modalDate.textContent = payload?.dateLabel || payload?.date || '-';
        if (modalDepartment) modalDepartment.textContent = payload?.department || '-';
        renderSummary(payload?.summary || {});

        const schedules = Array.isArray(payload?.schedules) ? payload.schedules : [];
        if (!modalBody) return;
        if (schedules.length === 0) {
            modalBody.innerHTML = '<div class="my-shift-day-empty-state">ยังไม่มีเวรในวันนี้</div>';
            return;
        }
        modalBody.innerHTML = schedules.map(renderSchedule).join('');
    };

    const openDetail = (payload) => {
        if (modalBody) {
            modalBody.innerHTML = '<div class="shift-day-detail-loading">กำลังโหลดรายละเอียดเวร...</div>';
        }
        modal.hidden = false;
        document.body.classList.add('overflow-hidden');
        window.requestAnimationFrame(() => renderDetail(payload));
    };

    const closeDetail = () => {
        modal.hidden = true;
        document.body.classList.remove('overflow-hidden');
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-shift-day-detail-open]');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        try {
            openDetail(JSON.parse(button.dataset.dayDetail || '{}'));
        } catch (error) {
            openDetail({ schedules: [], summary: {}, department: '-', dateLabel: 'ไม่สามารถโหลดรายละเอียดเวรได้' });
        }
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeDetail();
    });
    modal.querySelectorAll('[data-shift-day-detail-close]').forEach((button) => {
        button.addEventListener('click', closeDetail);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeDetail();
        }
    });
})();

(() => {
    const toastRegion = document.querySelector('[data-shift-toast-region]');
    const loadingOverlay = document.querySelector('[data-shift-loading]');
    const loadingTitle = document.querySelector('[data-shift-loading-title]');
    const loadingDetail = document.querySelector('[data-shift-loading-detail]');
    const toastTimers = new WeakMap();
    let actionInProgress = false;

    const escapeToastHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const showShiftToast = (message, type = 'success') => {
        if (!toastRegion || !message) return;
        const normalizedType = type === 'danger' || type === 'error' ? 'danger' : 'success';
        const toast = document.createElement('div');
        toast.className = `shift-toast is-${normalizedType}`;
        toast.setAttribute('role', normalizedType === 'danger' ? 'alert' : 'status');
        toast.innerHTML = `
            <span class="shift-toast-icon" aria-hidden="true">
                <i class="bi ${normalizedType === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-check2-circle'}"></i>
            </span>
            <span class="shift-toast-message">${escapeToastHtml(message)}</span>
            <button type="button" class="shift-toast-close" aria-label="ปิดการแจ้งเตือน">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        `;
        const closeToast = () => {
            const timer = toastTimers.get(toast);
            if (timer) window.clearTimeout(timer);
            toast.classList.add('is-hiding');
            window.setTimeout(() => toast.remove(), 180);
        };
        toast.querySelector('.shift-toast-close')?.addEventListener('click', closeToast);
        toastRegion.appendChild(toast);
        toastTimers.set(toast, window.setTimeout(closeToast, 5000));
    };

    const setShiftLoading = (visible, title = 'โปรดรอสักครู่...', detail = 'กำลังบันทึกและอัปเดตตารางเวร') => {
        if (!loadingOverlay) return;
        if (loadingTitle) loadingTitle.textContent = title;
        if (loadingDetail) loadingDetail.textContent = detail;
        loadingOverlay.hidden = !visible;
        if (visible) {
            document.body.classList.add('overflow-hidden');
        } else if (document.querySelector('[data-shift-modal]')?.hidden !== false) {
            document.body.classList.remove('overflow-hidden');
        }
    };

    const buildScheduleUrl = (form) => {
        const url = new URL(window.location.href);
        const formData = new FormData(form);
        ['department_id', 'month', 'year_be'].forEach((key) => {
            const value = formData.get(key);
            if (value !== null && value !== '') {
                url.searchParams.set(key, String(value));
            }
        });
        url.hash = '';
        return url.toString();
    };

    const storeToastForNextPage = (message, type = 'success') => {
        try {
            window.sessionStorage.setItem('shiftScheduleToast', JSON.stringify({ message, type }));
        } catch (error) {
            // Ignore storage failures; the action itself has already completed.
        }
    };

    try {
        const storedToast = window.sessionStorage.getItem('shiftScheduleToast');
        if (storedToast) {
            window.sessionStorage.removeItem('shiftScheduleToast');
            const parsedToast = JSON.parse(storedToast);
            showShiftToast(parsedToast.message || '', parsedToast.type || 'success');
        }
    } catch (error) {
        window.sessionStorage.removeItem('shiftScheduleToast');
    }

    document.querySelectorAll('[data-shift-initial-toast]').forEach((node) => {
        showShiftToast(node.dataset.toastMessage || '', node.dataset.toastType || 'success');
        node.remove();
    });

    const postScheduleAction = async (form) => {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: new FormData(form),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
            throw new Error(payload.error || 'ไม่สามารถดำเนินการได้ กรุณาลองใหม่');
        }
        return payload;
    };

    document.querySelector('.shift-modal-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (actionInProgress) return;

        const form = event.currentTarget;
        const submitButton = form.querySelector('button[type="submit"]');
        actionInProgress = true;
        submitButton?.setAttribute('disabled', 'disabled');
        setShiftLoading(true, 'โปรดรอสักครู่...', 'กำลังบันทึก draft และโหลดตารางเวรใหม่');
        try {
            const payload = await postScheduleAction(form);
            storeToastForNextPage(payload.message || 'บันทึก draft ตารางเวรเรียบร้อย', 'success');
            setShiftLoading(true, 'โปรดรอสักครู่...', 'กำลังโหลดตารางเวรล่าสุด');
            window.location.href = buildScheduleUrl(form);
        } catch (error) {
            actionInProgress = false;
            submitButton?.removeAttribute('disabled');
            setShiftLoading(false);
            showShiftToast(error.message || 'ไม่สามารถบันทึก draft ตารางเวรได้', 'danger');
        }
    });

    document.addEventListener('submit', async (event) => {
        const assignmentForm = event.target.closest('[data-assignment-delete-form]');
        const draftForm = event.target.closest('[data-draft-delete-form]');
        const form = assignmentForm || draftForm;
        if (!form) return;

        event.preventDefault();
        if (actionInProgress) return;
        const confirmMessage = assignmentForm
            ? 'ยกเลิกเจ้าหน้าที่คนนี้จากเวร?'
            : 'ต้องการลบดราฟเวรนี้ทั้งหมดหรือไม่? รายชื่อเจ้าหน้าที่ในดราฟนี้จะถูกลบทั้งหมด';
        if (!confirm(confirmMessage)) return;

        const button = form.querySelector('button[type="submit"]');
        actionInProgress = true;
        button?.setAttribute('disabled', 'disabled');
        setShiftLoading(true, 'โปรดรอสักครู่...', assignmentForm ? 'กำลังลบเจ้าหน้าที่และอัปเดตตารางเวร' : 'กำลังลบดราฟและอัปเดตตารางเวร');
        try {
            const payload = await postScheduleAction(form);
            storeToastForNextPage(payload.message || (assignmentForm ? 'ลบเจ้าหน้าที่ออกจากดราฟเรียบร้อยแล้ว' : 'ลบดราฟเรียบร้อยแล้ว'), 'success');
            setShiftLoading(true, 'โปรดรอสักครู่...', 'กำลังโหลดตารางเวรล่าสุด');
            window.location.href = buildScheduleUrl(form);
        } catch (error) {
            actionInProgress = false;
            showShiftToast(error.message || (assignmentForm ? 'ไม่สามารถลบเจ้าหน้าที่จากดราฟได้' : 'ไม่สามารถลบดราฟนี้ได้'), 'danger');
            button?.removeAttribute('disabled');
            setShiftLoading(false);
        }
    });
})();

// Auto-submit filter on change
(() => {
    const filterForm = document.querySelector('[data-filter-form]');
    if (!filterForm) return;

    // month + department selects: submit immediately on change
    filterForm.querySelectorAll('[data-filter-select]').forEach((sel) => {
        sel.addEventListener('change', () => filterForm.submit());
    });

    // year_be input: submit on blur or Enter (with validation)
    const yearInput = filterForm.querySelector('[data-filter-year]');
    if (yearInput) {
        let yearTimer = null;
        const trySubmitYear = () => {
            clearTimeout(yearTimer);
            yearTimer = setTimeout(() => {
                const val = parseInt(yearInput.value, 10);
                if (!isNaN(val) && val >= 2543 && val <= 2643) {
                    filterForm.submit();
                }
            }, 0);
        };
        yearInput.addEventListener('blur', trySubmitYear);
        yearInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                trySubmitYear();
            }
        });
    }
})();
</script>
</body>
</html>
