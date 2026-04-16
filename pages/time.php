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
$fullName = $_SESSION['fullname'];
$canViewDepartmentReports = app_can('can_view_department_reports');

$userStmt = $conn->prepare("
    SELECT u.department_id, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$userId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['department_id' => 1, 'department_name' => '-'];

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
$historyLimitState = app_parse_table_page_size([
    'per_page' => $_POST['history_per_page'] ?? ($historyStateInput['per_page'] ?? 20),
], 20);
$historyPageState = app_parse_table_page([
    'p' => $_POST['history_page'] ?? ($historyStateInput['p'] ?? 1),
]);
$historyRedirectBase = [
    'per_page' => $historyLimitState,
];
if ($historyDateState !== '') {
    $historyRedirectBase['date'] = $historyDateState;
}
$shiftPresets = [
    'morning' => ['label' => 'เวรเช้า', 'time_in' => '08:30', 'time_out' => '16:30'],
    'afternoon' => ['label' => 'เวรบ่าย', 'time_in' => '16:30', 'time_out' => '00:30'],
    'night' => ['label' => 'เวรดึก', 'time_in' => '00:30', 'time_out' => '08:30'],
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
    $message = 'เปลี่ยนแผนกสำหรับการลงเวลารอบนี้เรียบร้อยแล้ว';
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
        $message = 'กรุณาระบุเวลาเข้าและเวลาออกให้ครบถ้วน';
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
                'ช่วงเวลานี้ชนกับรายการเดิมวันที่ %s เวลา %s - %s กรุณาตรวจสอบก่อนบันทึกอีกครั้ง',
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
            $_SESSION['time_page_flash'] = 'บันทึกรายการลงเวลาเวรเรียบร้อยแล้ว และส่งเข้าคิวตรวจสอบแล้ว';
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
        $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } else {
        $editNote = trim((string) ($_POST['edit_note'] ?? ''));
        $editDepartmentId = $canViewDepartmentReports ? (int) ($_POST['edit_department_id'] ?? $editLog['department_id']) : (int) $editLog['department_id'];
        $timeInVal = app_parse_time_input($_POST, 'edit_time_in', '24h');
        $timeOutVal = app_parse_time_input($_POST, 'edit_time_out', '24h');

        if (app_time_log_is_locked($editLog) && !$canPrivilegedLockedEdit) {
            $message = 'รายการนี้ได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขได้ กรุณาติดต่อผู้ดูแลระบบ หากจำเป็นต้องดำเนินการเพิ่มเติม';
            $messageType = 'danger';
        } elseif ($timeInVal === null || $timeOutVal === null) {
            $message = 'กรุณาระบุเวลาเข้าและเวลาออกในรูปแบบ 24 ชั่วโมงให้ถูกต้อง';
            $messageType = 'danger';
        } else {
            $range = app_build_time_log_range((string) $editLog['work_date'], $timeInVal, $timeOutVal);
            if ($range === null) {
                $message = 'ไม่สามารถคำนวณช่วงเวลาได้';
                $messageType = 'danger';
            } else {
                $overlap = app_find_overlapping_time_log($conn, $userId, $range['time_in'], $range['time_out'], (int) $editLog['id']);
                if ($overlap) {
                    $messageType = 'danger';
                    $message = sprintf(
                        'การแก้ไขนี้ชนกับรายการเดิมวันที่ %s เวลา %s - %s กรุณาตรวจสอบก่อนบันทึกอีกครั้ง',
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
                        'แก้ไขจากหน้าลงเวลาเวร'
                    );
                    app_sync_reviewer_queue_notifications($conn);
                    $_SESSION['time_page_flash'] = 'แก้ไขรายการลงเวลาเวรเรียบร้อยแล้ว และส่งกลับเข้าคิวตรวจสอบแล้ว';
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
        $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } elseif (app_time_log_is_locked($editLog) && !$canPrivilegedLockedEdit) {
        $message = 'รายการนี้ได้รับการอนุมัติแล้ว ไม่สามารถลบได้';
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
            'ลบจากหน้าลงเวลาเวร'
        );
        app_sync_reviewer_queue_notifications($conn);
        $_SESSION['time_page_flash'] = 'ลบรายการลงเวลาเวรเรียบร้อยแล้ว';
        $_SESSION['time_page_flash_type'] = 'success';
        header('Location: time.php?' . app_build_table_query($historyRedirectBase, ['p' => max(1, $historyPageState)]));
        exit;
    }
}

$searchDate = $historyDateState;
$limit = $historyLimitState;
$page = $historyPageState;
$offset = ($page - 1) * $limit;

$params = [$userId];
$whereClause = "WHERE t.user_id = ?";
if ($searchDate !== '') {
    $whereClause .= " AND t.work_date = ?";
    $params[] = $searchDate;
}

$totalRows = app_get_user_time_history_count($conn, $userId, $searchDate);
$totalPages = max(1, (int) ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$totalStmt = $conn->prepare("SELECT COALESCE(SUM(work_hours), 0) FROM time_logs t $whereClause");
$totalStmt->execute($params);
$totalHours = (float) $totalStmt->fetchColumn();

$monthHoursStmt = $conn->prepare("
    SELECT COALESCE(SUM(work_hours), 0)
    FROM time_logs
    WHERE user_id = ? AND YEAR(work_date) = YEAR(CURDATE()) AND MONTH(work_date) = MONTH(CURDATE())
");
$monthHoursStmt->execute([$userId]);
$monthHours = (float) $monthHoursStmt->fetchColumn();

$historyLogs = app_get_user_time_history_rows($conn, $userId, $searchDate, $limit, $offset);
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
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('time.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="ops-hero mb-4">
        <div class="ops-hero-grid">
            <div>
                <div class="eyebrow mb-2">Time Workspace</div>
                <h1 class="mb-2">ลงเวลาเวร</h1>
                <p class="mb-4 text-white-50">บันทึกเวลาเข้าออกของวันนี้ ตรวจสอบชั่วโมงเวร และย้อนดูรายการย้อนหลังได้จากพื้นที่เดียว ระบบจะเตือนทันทีหากช่วงเวลาที่กรอกชนกับรายการเดิมของบุคคลเดียวกัน</p>
                <div class="pill-stack">
                    <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= htmlspecialchars($fullName) ?></span>
                    <span class="badge rounded-pill badge-soft px-3 py-2"><?= htmlspecialchars($userMeta['department_name'] ?? '-') ?></span>
                </div>
            </div>
            <aside class="ops-hero-side">
                <div class="ops-hero-stat">
                    <span id="dateLabel">-</span>
                    <strong>วันนี้</strong>
                </div>
                <div class="ops-hero-stat">
                    <span>เวลาปัจจุบัน</span>
                    <strong id="clock" class="time-clock">00:00:00</strong>
                </div>
            </aside>
        </div>
    </section>

    <div id="timePageMessage"><?php if ($message !== ''): ?><div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?></div>

    <section class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-4"><div class="report-stat-card"><div class="report-stat-label">ชั่วโมงเดือนนี้</div><div class="report-stat-value"><?= number_format($monthHours, 2) ?></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="report-stat-card"><div class="report-stat-label">ชั่วโมงสะสมตามตัวกรอง</div><div class="report-stat-value"><?= number_format($totalHours, 2) ?></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="report-stat-card"><div class="report-stat-label">จำนวนประวัติย้อนหลัง</div><div class="report-stat-value"><?= $totalRows ?></div></div></div>
    </section>

    <section class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="section-title h4 mb-0">บันทึกเวรของวันนี้</h2>
                    <span class="badge rounded-pill text-bg-primary px-3 py-2"><?= htmlspecialchars(app_format_thai_date($today)) ?></span>
                </div>

                <?php if ($canViewDepartmentReports): ?>
                    <form method="post" class="mb-4">
                        <label class="form-label fw-semibold small text-muted">แผนกที่ใช้บันทึก</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <select name="department_id" class="form-select">
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= $department['id'] ?>" <?= (int) $departmentId === (int) $department['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($department['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4 d-grid">
                                <button name="change_department" class="btn btn-outline-dark btn-pill">เปลี่ยน</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="rounded-4 border bg-light px-4 py-3 mb-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">แผนกที่ใช้งาน</div>
                            <div class="fw-bold"><?= htmlspecialchars($userMeta['department_name'] ?? '-') ?></div>
                        </div>
                        <span class="badge rounded-pill text-bg-dark px-3 py-2">สิทธิ์ส่วนตัว</span>
                    </div>
                <?php endif; ?>

                    <form method="post" class="row g-3" id="createLogForm" data-global-loading-form data-loading-message="กำลังบันทึกข้อมูล...">
                    <input type="hidden" name="create_time_log" value="1">
                    <input type="hidden" name="history_date" value="<?= htmlspecialchars($searchDate) ?>">
                    <input type="hidden" name="history_per_page" value="<?= (int) $limit ?>">
                    <input type="hidden" name="history_page" value="<?= (int) $page ?>">
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">ปุ่มลัดตามเวร</label>
                        <div class="preset-wrap mb-1">
                            <?php foreach ($shiftPresets as $presetKey => $preset): ?>
                                <button type="button" class="preset-btn" data-shift-target="create" data-shift-key="<?= htmlspecialchars($presetKey) ?>" data-time-in="<?= htmlspecialchars($preset['time_in']) ?>" data-time-out="<?= htmlspecialchars($preset['time_out']) ?>">
                                    <strong><?= htmlspecialchars($preset['label']) ?></strong>
                                    <span><?= htmlspecialchars($preset['time_in']) ?> - <?= htmlspecialchars($preset['time_out']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="rounded-4 border bg-light px-4 py-3">
                            <div class="small text-muted">รูปแบบเวลา</div>
                            <div class="fw-semibold">ใช้รูปแบบ 24 ชั่วโมงเท่านั้น เพื่อลดความสับสนในการลงเวลาเวร</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small text-muted">เวลาเข้า</label>
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
                    <div class="col-6">
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
                    <div class="col-12">
                        <div class="selection-preview">
                            <div class="small text-muted">เวลาที่จะบันทึก</div>
                            <strong id="createTimePreview"></strong>
                            <div class="small text-muted mt-2">ชั่วโมงรวมโดยประมาณ <span id="createHoursPreview" class="fw-semibold text-dark">0.00 ชม.</span></div>
                            <div class="shift-summary-grid">
                                <div class="shift-summary-card">
                                    <span class="label">เวรที่เลือก</span>
                                    <span class="value" id="createShiftLabel">เวรเช้า</span>
                                </div>
                                <div class="shift-summary-card">
                                    <span class="label">ชั่วโมงรวม</span>
                                    <span class="value" id="createShiftHours">8.00 ชม.</span>
                                </div>
                                <div class="shift-summary-card">
                                    <span class="label">ข้ามวัน</span>
                                    <span class="value" id="createShiftOvernight">ไม่ข้ามวัน</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted">หมายเหตุ / ภารกิจ</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="ระบุรายละเอียดงานหรือหมายเหตุเพิ่มเติม"><?= htmlspecialchars($newForm['note']) ?></textarea>
                    </div>
                    <div class="col-12 d-grid">
                        <button name="save_all_time" value="1" class="btn btn-dark btn-pill">บันทึกการปฏิบัติงาน</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel h-100">
                    <div class="history-toolbar mb-3">
                        <h2 class="section-title h4 mb-0">ประวัติย้อนหลัง</h2>
                        <div class="action-stack">
                            <a href="daily_schedule.php" class="btn btn-outline-dark btn-pill btn-sm">ดูเวรวันนี้</a>
                            <a href="my_reports.php" class="btn btn-outline-dark btn-pill btn-sm">เปิดรายงานของฉัน</a>
                        </div>
                </div>
                <form method="get" class="row g-3 align-items-end mb-4" id="timeHistoryFilterForm" data-page-state-key="time_history">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small text-muted">วันที่</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($searchDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small text-muted">แสดง</label>
                        <select name="per_page" class="form-select">
                            <?php foreach ([10, 20, 50, 100] as $size): ?>
                                <option value="<?= $size ?>" <?= $limit === $size ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-dark btn-pill">ค้นหา</button>
                    </div>
                </form>

                <div id="timeHistoryList"><?php require __DIR__ . '/../partials/time/history_list.php'; ?></div>
            </div>
        </div>
    </section>
</main>
<?php if ($editLog): ?>
    <div class="modal fade" id="editTimeLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <?php require __DIR__ . '/../partials/time/edit_modal_body.php'; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<div class="modal fade" id="ajaxEditTimeLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow" id="ajaxEditTimeLogModalContent">
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
        if (start === '08:30' && end === '16:30') return 'เวรเช้า';
        if (start === '16:30' && end === '00:30') return 'เวรบ่าย';
        if (start === '00:30' && end === '08:30') return 'เวรดึก';
        return 'กำหนดเอง';
    }

    function updateCreatePreview() {
        const preview = document.getElementById('createTimePreview');
        const hoursPreview = document.getElementById('createHoursPreview');
        const shiftLabel = document.getElementById('createShiftLabel');
        const shiftHours = document.getElementById('createShiftHours');
        const shiftOvernight = document.getElementById('createShiftOvernight');
        if (!preview) return;
        preview.textContent = `${document.getElementById('manual_time_in_hour').value}:${document.getElementById('manual_time_in_minute').value} - ${document.getElementById('manual_time_out_hour').value}:${document.getElementById('manual_time_out_minute').value}`;
        const startHour = parseInt(document.getElementById('manual_time_in_hour').value, 10);
        const startMinute = parseInt(document.getElementById('manual_time_in_minute').value, 10);
        const endHour = parseInt(document.getElementById('manual_time_out_hour').value, 10);
        const endMinute = parseInt(document.getElementById('manual_time_out_minute').value, 10);
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
        if (shiftHours) {
            shiftHours.textContent = `${totalHours} ชม.`;
        }
        if (shiftOvernight) {
            shiftOvernight.textContent = isOvernight ? 'ข้ามวัน' : 'ไม่ข้ามวัน';
        }
        if (shiftLabel) {
            shiftLabel.textContent = resolveShiftLabel(startHour, startMinute, endHour, endMinute);
        }
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
</script>
</body>
</html>

