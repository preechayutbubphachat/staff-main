<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_permission('can_manage_database');

$actorId = (int) ($_SESSION['id'] ?? 0);
$actorName = (string) ($_SESSION['fullname'] ?? '');
$csrfToken = app_csrf_token('db_table_browser');
$message = $_SESSION['db_admin_flash'] ?? '';
$messageType = $_SESSION['db_admin_flash_type'] ?? 'success';
unset($_SESSION['db_admin_flash'], $_SESSION['db_admin_flash_type']);

$table = trim((string) ($_GET['table'] ?? ''));
$tableConfigs = app_db_admin_tables();
$config = $table !== '' ? app_db_admin_get_table_config($table) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_row') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'db_table_browser')) {
        $_SESSION['db_admin_flash'] = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $_SESSION['db_admin_flash_type'] = 'danger';
    } else {
        $deleteTable = (string) ($_POST['table'] ?? '');
        $deleteConfig = app_db_admin_get_table_config($deleteTable);
        $rowId = max(0, (int) ($_POST['row_id'] ?? 0));
        $confirmToken = trim((string) ($_POST['confirm_delete_text'] ?? ''));
        if (!$deleteConfig || $rowId <= 0) {
            $_SESSION['db_admin_flash'] = 'ไม่พบข้อมูลที่ต้องการลบ';
            $_SESSION['db_admin_flash_type'] = 'danger';
        } elseif ($confirmToken !== 'DELETE') {
            $_SESSION['db_admin_flash'] = 'กรุณาพิมพ์ DELETE เพื่อยืนยันการลบข้อมูล';
            $_SESSION['db_admin_flash_type'] = 'danger';
        } else {
            try {
                app_db_admin_delete_row($conn, $deleteTable, $deleteConfig, $rowId, $actorId, $actorName);
                $_SESSION['db_admin_flash'] = 'ลบข้อมูลเรียบร้อยแล้ว';
                $_SESSION['db_admin_flash_type'] = 'success';
            } catch (Throwable $exception) {
                $_SESSION['db_admin_flash'] = $exception->getMessage();
                $_SESSION['db_admin_flash_type'] = 'danger';
            }
        }
    }

    $redirectFilters = [
        'table' => (string) ($_POST['table'] ?? ''),
        'q' => (string) ($_POST['return_q'] ?? ''),
        'page' => (int) ($_POST['return_page'] ?? 1),
        'per_page' => (int) ($_POST['return_per_page'] ?? 20),
    ];
    header('Location: db_table_browser.php?' . app_db_admin_query_string($redirectFilters));
    exit;
}

$tableCounts = app_db_admin_table_counts($conn);
$filters = $config ? app_db_admin_build_filters($table, $_GET, $config) : ['table' => '', 'q' => '', 'page' => 1, 'per_page' => 20, 'sort' => '', 'dir' => 'DESC'];
$rows = [];
$totalRows = 0;
$totalPages = 1;
$page = 1;
$perPage = (int) ($filters['per_page'] ?? 20);

if ($config) {
    if (!app_table_exists($conn, $table)) {
        $message = 'ยังไม่พบตารางนี้ในฐานข้อมูล กรุณารัน migration ที่เกี่ยวข้องก่อน';
        $messageType = 'warning';
    } else {
        $page = max(1, (int) $filters['page']);
        $totalRows = app_db_admin_count_rows($conn, $config, $filters);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $rows = app_db_admin_fetch_rows($conn, $config, array_merge($filters, ['page' => $page]), $perPage, ($page - 1) * $perPage);
    }
}

$printQuery = $config ? app_db_admin_query_string($filters, ['type' => 'db_table', 'table' => $table]) : '';
$pdfQuery = $config ? app_db_admin_query_string($filters, ['type' => 'db_table', 'table' => $table, 'download' => 'pdf']) : '';
$csvQuery = $config ? app_db_admin_query_string($filters, ['type' => 'db_table', 'table' => $table]) : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายการข้อมูลในตาราง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('db_table_browser.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="ops-hero mb-4">
        <div class="ops-hero-grid">
            <div>
                <div class="eyebrow mb-2">Database Admin</div>
                <h1 class="mb-2">รายการข้อมูลในตาราง</h1>
                <p class="mb-0 text-white-50">เปิดดู ค้นหา และจัดการข้อมูลเฉพาะตารางที่ระบบอนุญาต โดยทุกการเปลี่ยนแปลงจะถูกบันทึกเพื่อตรวจสอบย้อนหลัง และยังคงข้อจำกัดด้านความปลอดภัยของระบบ</p>
            </div>
            <aside class="ops-hero-side">
                <div class="ops-hero-stat">
                    <span>ตารางที่อนุญาต</span>
                    <strong><?= number_format(count($tableConfigs)) ?></strong>
                </div>
                <div class="ops-hero-stat">
                    <span>ตารางที่กำลังเปิด</span>
                    <strong><?= htmlspecialchars($config['label'] ?? 'ยังไม่ได้เลือก') ?></strong>
                </div>
            </aside>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="panel mb-4">
        <div class="row g-3">
            <?php foreach ($tableConfigs as $tableName => $tableConfig): ?>
                <div class="col-md-6 col-xl-4">
                    <a href="db_table_browser.php?table=<?= urlencode($tableName) ?>" class="text-decoration-none text-reset">
                        <div class="border rounded-4 p-4 h-100 bg-white <?= $table === $tableName ? 'border-dark' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="h5 mb-1"><?= htmlspecialchars($tableConfig['label']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($tableConfig['description']) ?></div>
                                </div>
                                <span class="badge text-bg-light border"><?= number_format($tableCounts[$tableName] ?? 0) ?></span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($config): ?>
        <section class="panel mb-4">
            <div class="table-toolbar">
                <div class="table-toolbar-main">
                    <div class="table-toolbar-title"><?= htmlspecialchars($config['label']) ?></div>
                    <div class="table-toolbar-help"><?= htmlspecialchars($config['description']) ?></div>
                    <form method="get" id="dbTableFilterForm" class="table-toolbar-form" data-page-state-key="db_table_browser">
                        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                        <input type="hidden" name="page" value="<?= (int) $page ?>">
                        <div class="toolbar-col-6">
                            <label class="form-label fw-semibold small text-muted">ค้นหาข้อมูล</label>
                            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="ค้นหาตามคีย์ที่กำหนดของตารางนี้">
                        </div>
                    </form>
                </div>
                <div class="table-toolbar-side">
                    <label class="table-page-size">
                        <span>แสดง</span>
                        <select name="per_page" form="dbTableFilterForm">
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
                        <?php if (!empty($config['create_allowed'])): ?>
                            <a href="db_row_create.php?table=<?= urlencode($table) ?>" class="btn btn-outline-dark btn-pill"><i class="bi bi-plus-circle"></i>เพิ่มข้อมูล</a>
                        <?php endif; ?>
                        <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary btn-pill">ล้างตัวกรอง</a>
                    </div>
                </div>
            </div>

            <div id="dbTableResults"><?php require __DIR__ . '/../partials/admin/db_table_results.php'; ?></div>
        </section>
    <?php endif; ?>
</main>
<?php if ($config): render_staff_profile_modal(); endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($config): render_staff_profile_modal_script(); endif; ?>
<?php if ($config): ?>
<script src="../assets/js/table-filters.js"></script>
<script>
TableFilters.init({
    formId: 'dbTableFilterForm',
    containerId: 'dbTableResults',
    endpoint: '../ajax/admin/db_table_rows.php',
    pushBase: 'db_table_browser.php'
});
</script>
<?php endif; ?>
</body>
</html>
