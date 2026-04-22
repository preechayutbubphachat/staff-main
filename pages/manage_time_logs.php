<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_permission('can_manage_time_logs');
date_default_timezone_set('Asia/Bangkok');

$actorId = (int) ($_SESSION['id'] ?? 0);
$actorName = (string) ($_SESSION['fullname'] ?? '');
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
$perPage = app_parse_table_page_size($_GET, 20);
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
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('manage_time_logs.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="hero ops-hero mb-4">
        <div class="ops-hero-grid">
            <div class="hero-copy">
                <div class="eyebrow mb-2">จัดการหลังบ้าน</div>
                <h1>จัดการลงเวลาเวร</h1>
                <p>ค้นหาและจัดการรายการลงเวลาเวรทั้งหมดในขอบเขตที่ได้รับสิทธิ์ เปิดดูรายละเอียด แก้ไขรายการ หรือรีเซ็ตสถานะอนุมัติได้จากพื้นที่เดียว โดยให้ความสำคัญกับการอ่านตารางและการทำงานต่อเนื่องของเจ้าหน้าที่หลังบ้าน</p>
            </div>
            <aside class="ops-hero-side">
                <h2 class="title">ขอบเขตการจัดการ</h2>
                <div class="ops-hero-stat">
                    <div class="label">ขอบเขตข้อมูล</div>
                    <div class="value" style="font-size:1.08rem;"><?= htmlspecialchars($scopeLabel) ?></div>
                </div>
                <div class="ops-hero-stat">
                    <div class="label">สิทธิ์รีเซ็ตการอนุมัติ</div>
                    <div class="value" style="font-size:1.08rem;"><?= app_can('can_edit_locked_time_logs') ? 'มีสิทธิ์' : 'ไม่มีสิทธิ์' ?></div>
                </div>
            </aside>
        </div>
    </section>

    <div id="manageTimeLogsMessage">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>

    <section class="panel mb-4">
        <div id="manageTimeLogsSummary" class="mb-4"></div>

        <div class="table-toolbar table-toolbar--filters">
            <div class="table-toolbar-main">
                <div class="table-toolbar-title">ตัวกรองรายการลงเวลาเวร</div>
                <div class="table-toolbar-help">ตัวกรองและปุ่มส่งออกอ้างอิงข้อมูลชุดเดียวกัน</div>
                <form method="get" id="manageTimeLogsFilterForm" class="table-toolbar-form" data-page-state-key="manage_time_logs">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">ค้นหา</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($filters['name']) ?>" placeholder="ค้นหาชื่อเจ้าหน้าที่">
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">ตำแหน่ง</label>
                        <input type="text" name="position_name" class="form-control" value="<?= htmlspecialchars($filters['position_name']) ?>" placeholder="เช่น พยาบาลวิชาชีพ">
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">แผนก</label>
                        <select name="department" class="form-select">
                            <option value="">ทั้งหมดตามสิทธิ์</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= (string) $filters['department'] === (string) $department['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">สถานะ</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>รอตรวจ</option>
                            <option value="checked" <?= $filters['status'] === 'checked' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                        </select>
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">จากวันที่</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">ถึงวันที่</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                </form>
            </div>
        </div>

        <div class="table-toolbar table-toolbar--actions">
            <div class="table-toolbar-side">
                <label class="table-page-size">
                    <span>แสดง</span>
                    <select name="per_page" form="manageTimeLogsFilterForm">
                        <?php foreach ([10, 20, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>รายการต่อหน้า</span>
                </label>
                <div class="table-export-group">
                    <a class="btn btn-outline-dark btn-pill" href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i>พิมพ์รายงาน</a>
                    <a class="btn btn-outline-dark btn-pill" href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i>ส่งออก PDF</a>
                    <a class="btn btn-dark btn-pill" href="export_report.php?<?= htmlspecialchars($csvQuery) ?>"><i class="bi bi-filetype-csv"></i>ส่งออก CSV</a>
                    <a href="manage_time_logs.php" class="btn btn-outline-secondary btn-pill">ล้างตัวกรอง</a>
                </div>
            </div>
        </div>

        <div id="manageTimeLogsResults"><?php require __DIR__ . '/../partials/manage_time_logs/results_block.php'; ?></div>
    </section>
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
<script src="../assets/js/table-filters.js"></script>
<script src="../assets/js/profile-modal.js"></script>
<script src="../assets/js/manage-time-logs.js"></script>
<script>
StaffProfileModal.init({ modalId: 'staffProfileModal', bodyId: 'staffProfileModalBody', endpoint: '../ajax/profile/get_staff_profile.php' });
ManageTimeLogsPage.init({ filterFormId: 'manageTimeLogsFilterForm', resultsId: 'manageTimeLogsResults', summaryId: 'manageTimeLogsSummary', modalId: 'manageTimeLogModal', modalContentId: 'manageTimeLogModalContent', messageId: 'manageTimeLogsMessage' });
if (window.TableFilters && typeof window.TableFilters.syncSummaryBlock === 'function') {
    window.TableFilters.syncSummaryBlock('manageTimeLogsResults', 'manageTimeLogsSummary');
}
</script>
</body>
</html>
