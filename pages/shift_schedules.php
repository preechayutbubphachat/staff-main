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
$defaultDepartmentId = (int) ($scope['ids'][0] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            app_create_or_update_schedule(
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
            $message = 'บันทึก draft ตารางเวรเรียบร้อย';
            $messageType = 'success';
        } elseif ($action === 'publish_month') {
            $result = app_publish_monthly_schedule($conn, $selectedDepartmentId, $selectedMonth, $selectedYear, $currentUserId);
            $message = $result['message'];
            $messageType = 'success';
        } elseif ($action === 'cancel_assignment') {
            app_cancel_shift_assignment($conn, (int) ($_POST['assignment_id'] ?? 0), $currentUserId);
            $message = 'ยกเลิกเจ้าหน้าที่จากเวรเรียบร้อย';
            $messageType = 'success';
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
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
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 border-0 shadow-sm" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
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
            <form method="get" class="shift-filter-form">
                <label>
                    <span>เดือน</span>
                    <select name="month" class="form-select">
                        <?php foreach ($monthOptions as $value => $label): ?>
                            <option value="<?= (int) $value ?>" <?= (int) $value === $selectedMonth ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>ปี พ.ศ.</span>
                    <input type="number" name="year_be" class="form-control" min="2543" max="2643" value="<?= (int) $selectedYearBe ?>">
                </label>
                <label>
                    <span>แผนก</span>
                    <select name="department_id" class="form-select" <?= $scope['is_global'] ? '' : 'disabled' ?>>
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
                <button type="submit" class="dash-btn dash-btn-primary"><i class="bi bi-search"></i> โหลดตาราง</button>
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
                        <button type="button" class="shift-add-button" data-shift-open data-date="<?= htmlspecialchars($date) ?>" aria-label="เพิ่มเวรวันที่ <?= (int) $day ?>">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <?php if (!$daySchedules): ?>
                        <div class="shift-empty">ยังไม่มีเวร</div>
                    <?php endif; ?>
                    <?php foreach ($daySchedules as $schedule): ?>
                        <div class="shift-slot">
                            <div class="shift-slot-head">
                                <span><?= htmlspecialchars($shiftTypes[$schedule['shift_type']]['label'] ?? $schedule['shift_type']) ?></span>
                                <span class="shift-status is-<?= htmlspecialchars($schedule['status']) ?>"><?= htmlspecialchars($schedule['status']) ?></span>
                            </div>
                            <div class="shift-time"><?= htmlspecialchars($schedule['start_time']) ?>-<?= htmlspecialchars($schedule['end_time']) ?> · <?= number_format((float) $schedule['planned_hours'], 2) ?> ชม.</div>
                            <div class="shift-staff-list">
                                <?php foreach ($schedule['assignments'] as $assignment): ?>
                                    <span class="shift-staff-badge">
                                        <?= htmlspecialchars($assignment['staff_name']) ?>
                                        <form method="post" class="shift-inline-form" onsubmit="return confirm('ยกเลิกเจ้าหน้าที่คนนี้จากเวร?');">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="cancel_assignment">
                                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                            <input type="hidden" name="department_id" value="<?= (int) $selectedDepartmentId ?>">
                                            <input type="hidden" name="month" value="<?= (int) $selectedMonth ?>">
                                            <input type="hidden" name="year_be" value="<?= (int) $selectedYearBe ?>">
                                            <button type="submit" aria-label="ยกเลิก <?= htmlspecialchars($assignment['staff_name']) ?>"><i class="bi bi-x"></i></button>
                                        </form>
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
                        <label class="shift-staff-option" data-staff-option data-search="<?= htmlspecialchars(mb_strtolower(($staff['fullname'] ?? '') . ' ' . ($staff['position_name'] ?? ''), 'UTF-8')) ?>">
                            <input type="checkbox" name="staff_ids[]" value="<?= (int) $staff['id'] ?>">
                            <span>
                                <strong><?= htmlspecialchars($staff['fullname']) ?></strong>
                                <small><?= htmlspecialchars($staff['position_name'] ?: app_role_label($staff['role'] ?? 'staff')) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="shift-modal-actions">
                <button type="button" class="dash-btn dash-btn-ghost" data-shift-close>ยกเลิก</button>
                <button type="submit" class="dash-btn dash-btn-primary"><i class="bi bi-save"></i> บันทึก draft</button>
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
    const modal = document.querySelector('[data-shift-modal]');
    const dateInput = document.querySelector('[data-shift-date]');
    const shiftType = document.querySelector('[data-shift-type]');
    const startInput = document.querySelector('[data-shift-start]');
    const endInput = document.querySelector('[data-shift-end]');
    const countLabel = document.querySelector('[data-selected-count]');
    const search = document.querySelector('[data-staff-search]');
    const options = Array.from(document.querySelectorAll('[data-staff-option]'));
    const checkboxes = Array.from(document.querySelectorAll('.shift-staff-option input[type="checkbox"]'));

    const updateCount = () => {
        const count = checkboxes.filter((input) => input.checked).length;
        if (countLabel) countLabel.textContent = `${count} คน`;
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
    search?.addEventListener('input', () => {
        const keyword = search.value.trim().toLowerCase();
        options.forEach((option) => {
            option.hidden = keyword !== '' && !(option.dataset.search || '').toLowerCase().includes(keyword);
        });
    });
    applyShiftTime();
})();
</script>
</body>
</html>
  