<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';

app_require_permission('can_manage_user_permissions');

$filters = app_build_manageable_user_filters($_GET);
$departments = app_fetch_departments($conn);
$roleLabels = app_role_labels();
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, 20);
$totalRows = app_count_manageable_users($conn, $filters);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = app_get_manageable_users($conn, $filters, $perPage, ($page - 1) * $perPage);

function app_manage_users_query(array $filters, array $extra = []): string
{
    return app_build_table_query([
        'fullname' => $filters['fullname'],
        'username' => $filters['username'],
        'position_name' => $filters['position_name'],
        'department' => $filters['department'],
        'role' => $filters['role'],
        'per_page' => $extra['per_page'] ?? ($_GET['per_page'] ?? 20),
    ], $extra);
}

$printQuery = app_manage_users_query($filters, ['type' => 'manage_users']);
$pdfQuery = app_manage_users_query($filters, ['type' => 'manage_users', 'download' => 'pdf']);
$csvQuery = app_manage_users_query($filters, ['type' => 'manage_users']);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('manage_users.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="ops-hero mb-4">
        <div class="ops-hero-grid">
            <div>
                <div class="eyebrow mb-2">Admin Only</div>
                <h1 class="mb-2">จัดการผู้ใช้งาน</h1>
                <p class="mb-0 text-white-50">ค้นหา เปิดดูโปรไฟล์ และเข้าสู่หน้าแก้ไขผู้ใช้งานจากตารางเดียวกัน โดยคงสิทธิ์และข้อมูลส่งออกให้ตรงกับชุดตัวกรองที่ใช้งานอยู่</p>
            </div>
            <aside class="ops-hero-side">
                <div class="ops-hero-stat">
                    <span>จำนวนผู้ใช้ในผลลัพธ์</span>
                    <strong><?= number_format($totalRows) ?></strong>
                </div>
                <div class="ops-hero-stat">
                    <span>บทบาทที่ดูแลได้</span>
                    <strong>ทุกบทบาทในระบบ</strong>
                </div>
            </aside>
        </div>
    </section>

    <section class="panel mb-4">
        <div class="table-toolbar">
            <div class="table-toolbar-main">
                <div class="table-toolbar-title">ตัวกรองรายชื่อผู้ใช้งาน</div>
                <div class="table-toolbar-help">เปลี่ยนตัวกรองแล้วตารางจะรีเฟรชทันที สามารถคลิกชื่อเพื่อดูข้อมูลเจ้าหน้าที่ และกดแก้ไขข้อมูลจากแถวเดียวกันได้</div>
                <form method="get" id="manageUsersFilterForm" class="table-toolbar-form" data-page-state-key="manage_users">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <div class="toolbar-col-3">
                        <label class="form-label fw-semibold small text-muted">ชื่อเจ้าหน้าที่</label>
                        <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($filters['fullname']) ?>" placeholder="ค้นหาจากชื่อเจ้าหน้าที่">
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($filters['username']) ?>" placeholder="ชื่อผู้ใช้">
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">ตำแหน่ง</label>
                        <input type="text" name="position_name" class="form-control" value="<?= htmlspecialchars($filters['position_name']) ?>" placeholder="เช่น พยาบาลวิชาชีพ">
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">แผนก</label>
                        <select name="department" class="form-select">
                            <option value="">ทุกแผนก</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= (string) $filters['department'] === (string) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="toolbar-col-2">
                        <label class="form-label fw-semibold small text-muted">บทบาท</label>
                        <select name="role" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($roleLabels as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="table-toolbar-side">
                <label class="table-page-size">
                    <span>แสดง</span>
                    <select name="per_page" form="manageUsersFilterForm">
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
                    <a href="manage_users.php" class="btn btn-outline-secondary btn-pill">ล้างตัวกรอง</a>
                </div>
            </div>
        </div>

        <div id="manageUsersResults"><?php require __DIR__ . '/../partials/admin/manage_users_results.php'; ?></div>
    </section>
</main>
<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/table-filters.js"></script>
<script>
TableFilters.init({
    formId: 'manageUsersFilterForm',
    containerId: 'manageUsersResults',
    endpoint: '../ajax/admin/users_rows.php',
    pushBase: 'manage_users.php'
});
</script>
</body>
</html>
