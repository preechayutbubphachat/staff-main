<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

app_require_permission('can_manage_time_logs');
date_default_timezone_set('Asia/Bangkok');

$actorId = (int) ($_SESSION['id'] ?? 0);
$actorName = (string) ($_SESSION['fullname'] ?? '');
$currentUserId = $actorId;
$csrfToken = app_csrf_token('manage_time_logs');
$message = $_SESSION['flash_manage_logs_error'] ?? '';
$messageType = $message !== '' ? 'danger' : 'success';
unset($_SESSION['flash_manage_logs_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_approval') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'manage_time_logs')) {
        $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } elseif (!app_can('can_edit_locked_time_logs')) {
        $message = 'คุณไม่มีสิทธิ์รีเซ็ตสถานะรายการที่อนุมัติแล้ว';
        $messageType = 'danger';
    } else {
        $timeLogId = (int) ($_POST['time_log_id'] ?? 0);
        $beforeRow = app_get_time_log_by_id($conn, $timeLogId);
        if (!$beforeRow) {
            $message = 'ไม่พบรายการลงเวลาที่ต้องการรีเซ็ตสถานะ';
            $messageType = 'danger';
        } elseif (!app_time_log_within_scope($conn, $beforeRow)) {
            $message = 'รายการนี้อยู่นอกขอบเขตสิทธิ์ที่จัดการได้';
            $messageType = 'danger';
        } elseif (empty($beforeRow['checked_at']) && empty($beforeRow['checked_by'])) {
            $message = 'รายการนี้ยังอยู่ในสถานะรอตรวจอยู่แล้ว';
            $messageType = 'warning';
        } else {
            $resetStmt = $conn->prepare('UPDATE time_logs SET checked_by = NULL, checked_at = NULL, signature = NULL WHERE id = ?');
            $resetStmt->execute([$timeLogId]);
            $afterRow = app_get_time_log_by_id($conn, $timeLogId);
            if ($afterRow) {
                app_notify_log_returned($conn, $afterRow, $actorId);
            }
            app_sync_reviewer_queue_notifications($conn);
            app_insert_time_log_audit($conn, $timeLogId, 'reset_approval', $beforeRow, $afterRow, $actorId, $actorName, 'รีเซ็ตสถานะการอนุมัติจากหน้าจัดการลงเวลาเวร');
            $message = 'รีเซ็ตสถานะการอนุมัติเรียบร้อยแล้ว';
            $messageType = 'success';
        }
    }
}

$reportData = app_fetch_time_log_report_data($conn, $_GET, 'all');
$filters = $reportData['filters'];
$summary = $reportData['summary'];
$departments = $filters['scope']['departments'];
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, 10);
$totalRows = (int) ($summary['total_rows'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = array_slice($reportData['rows'], ($page - 1) * $perPage, $perPage);
$scopeLabel = $reportData['scope_label'] ?? 'ตามสิทธิ์ที่เข้าถึงได้';
$printQuery = http_build_query(array_filter([
    'name' => $filters['name'],
    'position_name' => $filters['position_name'],
    'department' => $filters['department'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'status' => $filters['status'],
    'per_page' => $perPage,
    'type' => 'manage',
], static fn($value) => $value !== '' && $value !== null));
$pdfQuery = http_build_query(array_filter([
    'name' => $filters['name'],
    'position_name' => $filters['position_name'],
    'department' => $filters['department'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'status' => $filters['status'],
    'per_page' => $perPage,
    'type' => 'manage',
    'download' => 'pdf',
], static fn($value) => $value !== '' && $value !== null));
$csvQuery = http_build_query(array_filter([
    'name' => $filters['name'],
    'position_name' => $filters['position_name'],
    'department' => $filters['department'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'status' => $filters['status'],
    'per_page' => $perPage,
    'type' => 'manage',
], static fn($value) => $value !== '' && $value !== null));

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
$notificationCount = app_get_unread_notification_count($conn, $currentUserId);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');

$totalLogs = (int) ($summary['total_rows'] ?? 0);
$uniqueStaff = (int) ($summary['unique_staff_count'] ?? 0);
$uniqueDepartments = (int) ($summary['unique_department_count'] ?? 0);
$checkedCount = (int) ($summary['checked_count'] ?? 0);
$pendingCount = (int) ($summary['pending_count'] ?? 0);
$totalHours = (float) ($summary['total_hours'] ?? 0);
$issueCount = max(0, $totalLogs - $checkedCount - $pendingCount);
$completedPercent = $totalLogs > 0 ? ($checkedCount / $totalLogs) * 100 : 0;
$pendingPercent = $totalLogs > 0 ? ($pendingCount / $totalLogs) * 100 : 0;
$issuePercent = $totalLogs > 0 ? ($issueCount / $totalLogs) * 100 : 0;
$dataCompleteness = $totalLogs > 0 ? (($totalLogs - $issueCount) / $totalLogs) * 100 : 0;
$averageHoursPerLog = $totalLogs > 0 ? ($totalHours / $totalLogs) : 0;
$dataCompletenessClamped = is_finite($dataCompleteness) ? min(100, max(0, $dataCompleteness)) : 0;

$selectedDateFrom = trim((string) ($filters['date_from'] ?? ''));
$selectedDateTo = trim((string) ($filters['date_to'] ?? ''));
$periodMonth = date('Y-m');
if ($selectedDateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateFrom)) {
    $periodMonth = substr($selectedDateFrom, 0, 7);
} elseif ($selectedDateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateTo)) {
    $periodMonth = substr($selectedDateTo, 0, 7);
}
$periodLabel = app_format_thai_month_year($periodMonth);
$latestLabel = app_format_thai_datetime(date('Y-m-d H:i:s'), true);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการลงเวลาเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell manage-time-page-shell">
<?php render_dashboard_sidebar('manage_time_logs.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main manage-time-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">จัดการลงเวลาเวร</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาชื่อ, ตำแหน่ง, แผนก หรือสถานะ">
        </label>
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

    <div class="manage-time-dashboard-frame panel">
        <section class="manage-time-hero-stage">
            <article class="dash-card-strong manage-time-hero-card">
                <div class="manage-time-hero-grid">
                    <div class="manage-time-hero-copy">
                        <span class="dash-hero-pill"><i class="bi bi-sliders"></i> Shift Management</span>
                        <h2 class="dash-hero-title manage-time-hero-title">จัดการลงเวลาเวร</h2>
                        <p class="dash-hero-copy">
                            ตรวจสอบ แก้ไข และจัดการรายการลงเวลาเข้าออกของเจ้าหน้าที่ในแต่ละเวรให้ถูกต้องและเป็นปัจจุบัน
                        </p>
                        <div class="dash-hero-chips">
                            <span class="dash-hero-chip"><i class="bi bi-calendar3"></i>เดือน <?= htmlspecialchars($periodLabel) ?></span>
                            <span class="dash-hero-chip"><i class="bi bi-clock-history"></i>อัปเดตล่าสุด <?= htmlspecialchars($latestLabel) ?></span>
                        </div>
                    </div>

                    <div class="manage-time-hero-metrics" aria-label="สรุปจัดการลงเวลาเวร">
                        <div class="manage-time-hero-metric">
                            <span class="manage-time-hero-icon is-blue"><i class="bi bi-list-ul"></i></span>
                            <small>จำนวนรายการ</small>
                            <strong><?= number_format($totalLogs) ?></strong>
                            <span>รายการทั้งหมด</span>
                        </div>
                        <div class="manage-time-hero-metric">
                            <span class="manage-time-hero-icon is-green"><i class="bi bi-person-fill"></i></span>
                            <small>จำนวนพนักงาน</small>
                            <strong><?= number_format($uniqueStaff) ?></strong>
                            <span>คน</span>
                        </div>
                        <div class="manage-time-hero-metric">
                            <span class="manage-time-hero-icon is-purple"><i class="bi bi-building"></i></span>
                            <small>จำนวนแผนก</small>
                            <strong><?= number_format($uniqueDepartments) ?></strong>
                            <span>แผนก</span>
                        </div>
                        <div class="manage-time-hero-metric">
                            <span class="manage-time-hero-icon is-amber"><i class="bi bi-clock"></i></span>
                            <small>ชั่วโมงรวม</small>
                            <strong><?= number_format($totalHours, 2) ?></strong>
                            <span>ชั่วโมง</span>
                        </div>
                    </div>

                    <div class="manage-time-hero-actions">
                        <a href="#manage-time-results-panel" class="dash-btn dash-btn-secondary">
                            <i class="bi bi-calendar2-week"></i>เปิดตารางเวร
                        </a>
                        <a href="db_change_logs.php" class="dash-btn dash-btn-on-dark">
                            <i class="bi bi-clock-history"></i>ประวัติการแก้ไข
                        </a>
                    </div>
                </div>
            </article>
        </section>

        <div id="manageTimeLogsMessage">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-3"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
        </div>

        <div id="manageTimeLogsSummary">
            <section class="manage-time-summary-row" data-static-summary>
                <article class="dash-kpi-card manage-time-summary-card">
                    <span class="manage-time-summary-icon is-blue"><i class="bi bi-calendar3"></i></span>
                    <div>
                        <p>ช่วงข้อมูล</p>
                        <strong><?= htmlspecialchars($periodLabel) ?></strong>
                        <span>ช่วงเวลาที่เลือก</span>
                    </div>
                </article>
                <article class="dash-kpi-card manage-time-summary-card">
                    <span class="manage-time-summary-icon is-green"><i class="bi bi-check-circle"></i></span>
                    <div>
                        <p>ลงเวลาแล้ว</p>
                        <strong><?= number_format($checkedCount) ?> รายการ</strong>
                        <span>คิดเป็น <?= number_format($completedPercent, 2) ?>%</span>
                    </div>
                </article>
                <article class="dash-kpi-card manage-time-summary-card">
                    <span class="manage-time-summary-icon is-amber"><i class="bi bi-hourglass-split"></i></span>
                    <div>
                        <p>รอตรวจสอบ</p>
                        <strong><?= number_format($pendingCount) ?> รายการ</strong>
                        <span>คิดเป็น <?= number_format($pendingPercent, 2) ?>%</span>
                    </div>
                </article>
                <article class="dash-kpi-card manage-time-summary-card">
                    <span class="manage-time-summary-icon is-danger"><i class="bi bi-exclamation-triangle"></i></span>
                    <div>
                        <p>ต้องแก้ไข</p>
                        <strong><?= number_format($issueCount) ?> รายการ</strong>
                        <span>คิดเป็น <?= number_format($issuePercent, 2) ?>%</span>
                    </div>
                </article>
            </section>
        </div>

        <section class="manage-time-workspace-grid">
            <aside class="dash-card manage-time-filter-card">
                <div>
                    <p class="manage-time-section-eyebrow">Time log filters</p>
                    <h2 class="manage-time-card-title">ตัวกรองการลงเวลาเวร</h2>
                    <p class="manage-time-card-copy">ค้นหาและจัดการรายการตามขอบเขตสิทธิ์ของผู้ดูแลระบบ</p>
                </div>

                <form method="get" id="manageTimeLogsFilterForm" class="manage-time-filter-form" data-page-state-key="manage_time_logs">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">

                    <div class="manage-time-filter-field is-wide">
                        <label class="manage-time-field-label">ค้นหาชื่อพนักงาน</label>
                        <label class="manage-time-search-field">
                            <input type="text" name="name" value="<?= htmlspecialchars($filters['name']) ?>" placeholder="ค้นหาชื่อพนักงาน">
                            <i class="bi bi-search"></i>
                        </label>
                    </div>

                    <div class="manage-time-filter-grid">
                        <div class="manage-time-filter-field">
                            <label class="manage-time-field-label">ตำแหน่ง</label>
                            <input type="text" name="position_name" class="form-control" value="<?= htmlspecialchars($filters['position_name']) ?>" placeholder="ทั้งหมด">
                        </div>
                        <div class="manage-time-filter-field">
                            <label class="manage-time-field-label">แผนก</label>
                            <select name="department" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= (string) $filters['department'] === (string) $department['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="manage-time-filter-field">
                            <label class="manage-time-field-label">สถานะ</label>
                            <select name="status" class="form-select">
                                <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>รอตรวจสอบ</option>
                                <option value="checked" <?= $filters['status'] === 'checked' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                            </select>
                        </div>
                        <div class="manage-time-filter-field">
                            <label class="manage-time-field-label">แสดง</label>
                            <select name="per_page" class="form-select">
                                <?php foreach ([10, 20, 50, 100] as $size): ?>
                                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> รายการ</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="manage-time-filter-field">
                            <label class="manage-time-field-label">วันที่เริ่มต้น</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div class="manage-time-filter-field">
                            <label class="manage-time-field-label">วันที่สิ้นสุด</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                    </div>

                    <div class="manage-time-filter-actions">
                        <a class="dash-btn dash-btn-ghost manage-time-action-btn" href="manage_time_logs.php">
                            <i class="bi bi-arrow-clockwise"></i>ล้างตัวกรอง
                        </a>
                        <button type="submit" class="dash-btn dash-btn-primary manage-time-action-btn">
                            <i class="bi bi-search"></i>ค้นหา
                        </button>
                    </div>
                </form>

                <div class="manage-time-filter-total">
                    <i class="bi bi-list-check"></i>
                    <span><?= number_format($totalLogs) ?> รายการทั้งหมด</span>
                </div>
            </aside>

            <div id="manageTimeLogsResults" class="min-w-0">
                <?php require __DIR__ . '/../partials/manage_time_logs/results_block.php'; ?>
            </div>
        </section>

        <section class="dash-card manage-time-bottom-strip shift-summary-footer-card" aria-label="สรุปข้อมูลจัดการลงเวลาเวร">
            <!-- Metric 1: รายการทั้งหมด -->
            <div class="mtbs-item shift-summary-metric">
                <span class="mtbs-icon shift-summary-icon mtbs-icon--blue" aria-hidden="true">
                    <i class="bi bi-list-check"></i>
                </span>
                <div class="mtbs-content shift-summary-content">
                    <p class="mtbs-label">รายการทั้งหมด</p>
                    <strong class="mtbs-value"><?= number_format($totalLogs) ?> รายการ</strong>
                    <span class="mtbs-sub">จากทั้งหมด <?= number_format($totalLogs) ?> รายการ</span>
                </div>
            </div>
            <div class="mtbs-divider shift-summary-divider" aria-hidden="true"></div>
            <!-- Metric 2: พนักงาน -->
            <div class="mtbs-item shift-summary-metric">
                <span class="mtbs-icon shift-summary-icon mtbs-icon--green" aria-hidden="true">
                    <i class="bi bi-people-fill"></i>
                </span>
                <div class="mtbs-content shift-summary-content">
                    <p class="mtbs-label">พนักงาน</p>
                    <strong class="mtbs-value"><?= number_format($uniqueStaff) ?> คน</strong>
                    <span class="mtbs-sub">จากทั้งหมด <?= number_format($uniqueStaff) ?> คน</span>
                </div>
            </div>
            <div class="mtbs-divider shift-summary-divider" aria-hidden="true"></div>
            <!-- Metric 3: แผนก -->
            <div class="mtbs-item shift-summary-metric">
                <span class="mtbs-icon shift-summary-icon mtbs-icon--purple" aria-hidden="true">
                    <i class="bi bi-building"></i>
                </span>
                <div class="mtbs-content shift-summary-content">
                    <p class="mtbs-label">แผนก</p>
                    <strong class="mtbs-value"><?= number_format($uniqueDepartments) ?> แผนก</strong>
                    <span class="mtbs-sub">จากทั้งหมด <?= number_format($uniqueDepartments) ?> แผนก</span>
                </div>
            </div>
            <div class="mtbs-divider shift-summary-divider" aria-hidden="true"></div>
            <!-- Metric 4: ชั่วโมงรวม -->
            <div class="mtbs-item shift-summary-metric">
                <span class="mtbs-icon shift-summary-icon mtbs-icon--amber" aria-hidden="true">
                    <i class="bi bi-clock-history"></i>
                </span>
                <div class="mtbs-content shift-summary-content">
                    <p class="mtbs-label">ชั่วโมงรวม</p>
                    <strong class="mtbs-value"><?= number_format($totalHours, 2) ?> ชม.</strong>
                    <span class="mtbs-sub">เฉลี่ย <?= number_format($averageHoursPerLog, 2) ?> ชม./รายการ</span>
                </div>
            </div>
            <div class="mtbs-divider shift-summary-divider" aria-hidden="true"></div>
            <!-- Metric 5: ความสมบูรณ์ของข้อมูล -->
            <div class="mtbs-item mtbs-item--wide shift-summary-metric shift-summary-progress">
                <span class="mtbs-icon shift-summary-icon mtbs-icon--teal" aria-hidden="true">
                    <i class="bi bi-graph-up-arrow"></i>
                </span>
                <div class="mtbs-content mtbs-content--progress shift-summary-content">
                    <div class="mtbs-progress-header">
                        <p class="mtbs-label">ความสมบูรณ์ของข้อมูล</p>
                        <strong class="mtbs-percent"><?= number_format($dataCompletenessClamped, 2) ?>%</strong>
                    </div>
                    <div class="mtbs-progress-track" role="progressbar"
                         aria-valuenow="<?= (int) round($dataCompletenessClamped) ?>"
                         aria-valuemin="0" aria-valuemax="100"
                         aria-label="ความสมบูรณ์ข้อมูล <?= number_format($dataCompletenessClamped, 2) ?>%">
                        <span class="mtbs-progress-fill"
                              style="width:<?= htmlspecialchars((string) round($dataCompletenessClamped, 2)) ?>%"></span>
                    </div>
                    <span class="mtbs-sub">ลงเวลาแล้ว <?= number_format($checkedCount) ?> / <?= number_format($totalLogs) ?> รายการ</span>
                </div>
            </div>
        </section>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<div class="modal fade" id="manageTimeLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow" id="manageTimeLogModalContent">
            <div class="modal-body text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/table-filters.js"></script>
<script src="../assets/js/profile-modal.js"></script>
<script src="../assets/js/manage-time-logs.js"></script>
<script>
StaffProfileModal.init({ modalId: 'staffProfileModal', bodyId: 'staffProfileModalBody', endpoint: '../ajax/profile/get_staff_profile.php' });
ManageTimeLogsPage.init({ filterFormId: 'manageTimeLogsFilterForm', resultsId: 'manageTimeLogsResults', summaryId: 'manageTimeLogsSummary', modalId: 'manageTimeLogModal', modalContentId: 'manageTimeLogModalContent', messageId: 'manageTimeLogsMessage' });
</script>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
