<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_permission('can_approve_logs');
date_default_timezone_set('Asia/Bangkok');

$checkerId = (int) ($_SESSION['id'] ?? 0);
$checkerName = (string) ($_SESSION['fullname'] ?? '');
$csrfToken = app_csrf_token('approval_queue');
$message = '';
$messageType = 'success';

$checkerSignatureStmt = $conn->prepare('SELECT signature_path FROM users WHERE id = ?');
$checkerSignatureStmt->execute([$checkerId]);
$checkerSignature = (string) ($checkerSignatureStmt->fetchColumn() ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_approve') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'approval_queue')) {
        $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } else {
        $result = app_process_bulk_approval(
            $conn,
            is_array($_POST['selected_ids'] ?? null) ? $_POST['selected_ids'] : [],
            $checkerId,
            $checkerName,
            $checkerSignature
        );
        $message = $result['message'];
        $messageType = $result['success'] ? ($result['skipped_count'] > 0 ? 'warning' : 'success') : 'danger';
        if (!empty($result['skipped_reasons'])) {
            $message .= ': ' . implode(' | ', $result['skipped_reasons']);
        }
    }
}

$reportData = app_fetch_time_log_report_data($conn, $_GET, 'pending');
$filters = $reportData['filters'];
$summary = $reportData['summary'];
$departments = $filters['scope']['departments'];
$view = $_GET['view'] ?? 'table';
$view = in_array($view, ['cards', 'table'], true) ? $view : 'table';
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, $view === 'table' ? 20 : 12);
$totalRows = (int) ($summary['total_rows'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = array_slice($reportData['rows'], ($page - 1) * $perPage, $perPage);
$scopeLabel = $reportData['scope_label'] ?? 'ตามสิทธิ์ที่เข้าถึงได้';

function app_approval_query(array $filters, array $overrides = []): string
{
    $query = array_merge([
        'name' => $filters['name'],
        'position_name' => $filters['position_name'],
        'department' => $filters['department'],
        'date_from' => $filters['date_from'],
        'date_to' => $filters['date_to'],
        'status' => $filters['status'],
        'per_page' => $overrides['per_page'] ?? ($_GET['per_page'] ?? 20),
        'view' => $overrides['view'] ?? null,
        'p' => $overrides['p'] ?? null,
        'type' => $overrides['type'] ?? null,
        'download' => $overrides['download'] ?? null,
    ], $overrides);

    return http_build_query(array_filter($query, static fn($value) => $value !== '' && $value !== null));
}

$printQuery = app_approval_query($filters, ['type' => 'approval']);
$pdfQuery = app_approval_query($filters, ['type' => 'approval', 'download' => 'pdf']);
$csvQuery = app_approval_query($filters, ['type' => 'approval']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตรวจสอบรายการลงเวลาเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('approval_queue.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="hero ops-hero mb-4">
        <div class="ops-hero-grid">
            <div class="hero-copy">
                <div class="eyebrow mb-2">คิวตรวจสอบ</div>
                <h1>ตรวจสอบรายการลงเวลาเวร</h1>
                <p>เปิดคิวตรวจสอบแล้วเห็นรายการที่รอตรวจได้ทันทีตามสิทธิ์ที่เข้าถึงได้ ค้นหาได้จากชื่อ ตำแหน่ง แผนก และช่วงวันที่ พร้อมเลือกหลายรายการในหน้าเดียวอย่างชัดเจน</p>
            </div>
            <aside class="ops-hero-side">
                <h2 class="title">สถานะผู้ตรวจสอบ</h2>
                <div class="ops-hero-stat">
                    <div class="label">ผู้ตรวจสอบ</div>
                    <div class="value" style="font-size:1.2rem;"><?= htmlspecialchars($checkerName) ?></div>
                </div>
                <div class="ops-hero-stat">
                    <div class="label">ขอบเขตข้อมูล</div>
                    <div class="value" style="font-size:1.08rem;"><?= htmlspecialchars($scopeLabel) ?></div>
                </div>
                <div class="ops-hero-stat">
                    <div class="label">ลายเซ็นผู้ตรวจสอบ</div>
                    <div class="value" style="font-size:1.05rem; color: <?= $checkerSignature !== '' ? '#ffffff' : '#ffd4d4' ?>;"><?= $checkerSignature !== '' ? 'พร้อมใช้งาน' : 'ยังไม่ได้ตั้งค่า' ?></div>
                </div>
            </aside>
        </div>
    </section>

    <div id="approvalQueueMessage">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>

    <section class="panel mb-4">
        <div id="approvalSummary" class="mb-4"></div>

        <div class="table-toolbar table-toolbar--filters">
            <div class="table-toolbar-main">
                <div class="table-toolbar-title">ตัวกรองคิวตรวจสอบ</div>
                <div class="table-toolbar-help">ตัวกรองในส่วนนี้มีผลกับตารางด้านล่าง ปุ่มส่งออก และการเปลี่ยนจำนวนรายการต่อหน้าทันที</div>
                <form method="get" id="approvalFilterForm" class="table-toolbar-form" data-page-state-key="approval_queue">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
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
                            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>รอตรวจ</option>
                            <option value="checked" <?= $filters['status'] === 'checked' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                            <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
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
                    <select name="per_page" form="approvalFilterForm">
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
                    <a href="approval_queue.php" class="btn btn-outline-secondary btn-pill">ล้างตัวกรอง</a>
                </div>
            </div>
        </div>

        <form method="post" id="bulkApproveForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="bulk_approve">

            <section class="ops-bulk-bar mb-4" id="bulkBar">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="fw-semibold" id="selectedSummaryText">เลือกรายการ 0 รายการ</div>
                        <div class="text-muted small">ตรวจสอบสรุปรายการที่เลือกในหน้าต่างยืนยันก่อนอนุมัติจริง</div>
                    </div>
                    <div class="queue-actions">
                        <button type="button" class="btn btn-outline-secondary" id="clearSelectionBtn"><i class="bi bi-x-circle me-1"></i>ล้างการเลือก</button>
                        <button type="button" class="btn btn-primary" id="openApproveModalBtn" <?= $checkerSignature === '' ? 'disabled data-signature-required="1"' : '' ?>><i class="bi bi-patch-check me-1"></i>ตรวจสอบรายการที่เลือก</button>
                    </div>
                </div>
            </section>

            <div id="approvalResultsContainer"><?php require __DIR__ . '/../partials/approval/results_block.php'; ?></div>
        </form>
    </section>
</main>

<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">ยืนยันการตรวจสอบรายการที่เลือก</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><div class="p-3 rounded-4 bg-light"><div class="small text-muted">จำนวนรายการที่เลือก</div><div class="fs-4 fw-bold" id="modalSelectedCount">0</div></div></div>
                    <div class="col-md-4"><div class="p-3 rounded-4 bg-light"><div class="small text-muted">จำนวนเจ้าหน้าที่ไม่ซ้ำ</div><div class="fs-4 fw-bold" id="modalStaffCount">0</div></div></div>
                    <div class="col-md-4"><div class="p-3 rounded-4 bg-light"><div class="small text-muted">จำนวนแผนกไม่ซ้ำ</div><div class="fs-4 fw-bold" id="modalDepartmentCount">0</div></div></div>
                </div>
                <div class="approval-modal-table-wrap">
                    <div class="table-responsive approval-modal-table-scroll">
                        <table class="table align-middle mb-0 approval-modal-table">
                            <thead class="table-light">
                                <tr>
                                    <th>ลำดับ</th>
                                    <th>วันที่</th>
                                    <th>ชื่อ</th>
                                    <th>ตำแหน่ง</th>
                                    <th>แผนก</th>
                                    <th>เวลา</th>
                                </tr>
                            </thead>
                            <tbody id="selectedItemsTableBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการที่เลือก</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary rounded-pill" id="confirmApproveBtn">ยืนยันตรวจสอบรายการ</button>
            </div>
        </div>
    </div>
</div>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/table-filters.js"></script>
<script src="../assets/js/profile-modal.js"></script>
<script src="../assets/js/approval-queue.js"></script>
<script>
StaffProfileModal.init({ modalId: 'staffProfileModal', bodyId: 'staffProfileModalBody', endpoint: '../ajax/profile/get_staff_profile.php' });
ApprovalQueuePage.init({ formId: 'bulkApproveForm', filterFormId: 'approvalFilterForm', resultsId: 'approvalResultsContainer', summaryId: 'approvalSummary', messageId: 'approvalQueueMessage' });
if (window.TableFilters && typeof window.TableFilters.syncSummaryBlock === 'function') {
    window.TableFilters.syncSummaryBlock('approvalResultsContainer', 'approvalSummary');
}
</script>
</body>
</html>
