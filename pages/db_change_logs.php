<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_permission('can_manage_database');

$page = app_parse_table_page($_GET, 'page');
$perPage = app_parse_table_page_size($_GET, 20);
$search = trim((string) ($_GET['q'] ?? ''));
$rows = [];
$totalRows = 0;

if (app_table_exists($conn, 'db_admin_audit_logs')) {
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM db_admin_audit_logs
            WHERE table_name LIKE ? OR action_type LIKE ? OR actor_name_snapshot LIKE ? OR COALESCE(note, '') LIKE ?
        ");
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like, $like]);
        $totalRows = (int) $stmt->fetchColumn();
    } else {
        $totalRows = (int) $conn->query('SELECT COUNT(*) FROM db_admin_audit_logs')->fetchColumn();
    }
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

if (app_table_exists($conn, 'db_admin_audit_logs')) {
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT *
            FROM db_admin_audit_logs
            WHERE table_name LIKE ? OR action_type LIKE ? OR actor_name_snapshot LIKE ? OR COALESCE(note, '') LIKE ?
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");
        $like = '%' . $search . '%';
        $stmt->bindValue(1, $like, PDO::PARAM_STR);
        $stmt->bindValue(2, $like, PDO::PARAM_STR);
        $stmt->bindValue(3, $like, PDO::PARAM_STR);
        $stmt->bindValue(4, $like, PDO::PARAM_STR);
        $stmt->bindValue(5, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(6, $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$baseQuery = ['q' => $search, 'per_page' => $perPage];
$printQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs']);
$pdfQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs', 'download' => 'pdf']);
$csvQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>บันทึกการเปลี่ยนแปลงข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('db_change_logs.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="ops-hero mb-4">
        <div class="ops-hero-grid">
            <div>
                <div class="eyebrow mb-2">Database Audit</div>
                <h1 class="mb-2">บันทึกการเปลี่ยนแปลงข้อมูล</h1>
                <p class="mb-0 text-white-50">ใช้ตรวจสอบย้อนหลังว่ามีการสร้าง แก้ไข หรือลบข้อมูลอะไรในโมดูลจัดการระบบหลังบ้าน พร้อมค้นหาข้อมูลและส่งออกรายงานจากตัวกรองเดียวกับที่แสดงบนหน้าจอ</p>
            </div>
            <aside class="ops-hero-side">
                <div class="ops-hero-stat">
                    <span>รายการทั้งหมด</span>
                    <strong><?= number_format($totalRows) ?></strong>
                </div>
                <div class="ops-hero-stat">
                    <span>สถานะระบบ</span>
                    <strong><?= app_table_exists($conn, 'db_admin_audit_logs') ? 'พร้อมใช้งาน' : 'ยังไม่พบตาราง' ?></strong>
                </div>
            </aside>
        </div>
    </section>

    <section class="panel mb-4">
        <div class="table-toolbar">
            <div class="table-toolbar-main">
                <div class="table-toolbar-title">ตัวกรองบันทึกการเปลี่ยนแปลง</div>
                <div class="table-toolbar-help">ค้นหาจากชื่อตาราง การกระทำ ผู้ดำเนินการ หรือหมายเหตุ แล้วตารางจะรีเฟรชทันทีโดยไม่ต้องโหลดทั้งหน้าใหม่</div>
                <form method="get" id="dbAuditFilterForm" class="table-toolbar-form" data-page-state-key="db_change_logs">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <div class="toolbar-col-6">
                        <label class="form-label fw-semibold small text-muted">ค้นหา</label>
                        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาจากตาราง การกระทำ ผู้ดำเนินการ หรือหมายเหตุ">
                    </div>
                </form>
            </div>
            <div class="table-toolbar-side">
                <label class="table-page-size">
                    <span>แสดง</span>
                    <select name="per_page" form="dbAuditFilterForm">
                        <?php foreach ([10, 20, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>รายการต่อหน้า</span>
                </label>
                <div class="table-export-group">
                    <a href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener" class="btn btn-outline-dark btn-pill"><i class="bi bi-printer"></i>พิมพ์รายงาน</a>
                    <a href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener" class="btn btn-outline-dark btn-pill"><i class="bi bi-filetype-pdf"></i>ส่งออก PDF</a>
                    <a href="export_report.php?<?= htmlspecialchars($csvQuery) ?>" class="btn btn-dark btn-pill"><i class="bi bi-download"></i>ส่งออก CSV</a>
                    <a href="db_change_logs.php" class="btn btn-outline-secondary btn-pill">ล้างตัวกรอง</a>
                </div>
            </div>
        </div>

        <div id="dbAuditResults"><?php require __DIR__ . '/../partials/admin/db_change_log_results.php'; ?></div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/table-filters.js"></script>
<script>
TableFilters.init({
    formId: 'dbAuditFilterForm',
    containerId: 'dbAuditResults',
    endpoint: '../ajax/admin/audit_rows.php',
    pushBase: 'db_change_logs.php'
});
</script>
</body>
</html>
