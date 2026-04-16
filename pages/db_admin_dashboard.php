<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';
require_once __DIR__ . '/../includes/report_helpers.php';

app_require_permission('can_manage_database');

$tableConfigs = app_db_admin_tables();
$tableCounts = app_db_admin_table_counts($conn);
$recentLogs = app_table_exists($conn, 'db_admin_audit_logs')
    ? $conn->query("SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$auditPrintQuery = http_build_query(['type' => 'db_change_logs']);
$auditPdfQuery = http_build_query(['type' => 'db_change_logs', 'download' => 'pdf']);
$auditCsvQuery = http_build_query(['type' => 'db_change_logs']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการข้อมูลฐานข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body{background:linear-gradient(180deg,#f8fbfd,#eef4f8);font-family:'Sarabun',sans-serif;color:#10243b}.hero,.panel,.stat-card{background:rgba(255,255,255,.92);border:1px solid rgba(16,36,59,.08);border-radius:28px;box-shadow:0 18px 44px rgba(16,36,59,.08)}.hero,.panel{padding:28px}.hero h1,.stat-value,.card-title{font-family:'Prompt',sans-serif}.stat-card{padding:22px;height:100%}.stat-value{font-size:2rem}.table td,.table th{vertical-align:middle}
    </style>
    <link rel="stylesheet" href="../assets/css/app-ui.css">
</head>
<body class="app-ui">
<?php render_app_navigation('db_admin_dashboard.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="hero mb-4">
        <div class="text-uppercase small fw-bold text-secondary mb-2">Database Admin</div>
        <h1 class="mb-2">จัดการข้อมูลฐานข้อมูล</h1>
        <p class="text-secondary mb-0">สำหรับผู้ดูแลระบบเท่านั้น การเปลี่ยนแปลงทุกครั้งจะถูกบันทึกเพื่อตรวจสอบย้อนหลัง โมดูลนี้รองรับเฉพาะตารางที่กำหนดไว้ล่วงหน้าและไม่เปิดให้รันคำสั่ง SQL โดยตรง</p>
    </section>
    <section class="row g-4 mb-4">
        <div class="col-md-4"><div class="stat-card"><div class="small text-muted text-uppercase fw-bold">ตารางที่จัดการได้</div><div class="stat-value mt-2"><?= count($tableConfigs) ?></div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="small text-muted text-uppercase fw-bold">รายการเปลี่ยนล่าสุด</div><div class="stat-value mt-2"><?= count($recentLogs) ?></div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="small text-muted text-uppercase fw-bold">ข้อเตือน</div><div class="mt-2 fw-semibold">ทุกการเปลี่ยนแปลงมี audit</div></div></div>
    </section>
    <section class="panel mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h2 class="h4 mb-0">ตารางข้อมูลที่อนุญาตให้จัดการ</h2>
            <div class="d-flex gap-2"><a href="db_table_browser.php" class="btn btn-dark rounded-pill">เปิดรายการตาราง</a><a href="db_change_logs.php" class="btn btn-outline-dark rounded-pill">บันทึกการเปลี่ยนแปลงข้อมูล</a></div>
        </div>
        <div class="row g-3">
            <?php foreach ($tableConfigs as $table => $config): ?>
                <div class="col-lg-6">
                    <div class="border rounded-4 p-4 h-100 bg-white">
                        <div class="d-flex justify-content-between gap-3 align-items-start">
                            <div>
                                <div class="card-title h5 mb-1"><?= htmlspecialchars($config['label']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($config['description']) ?></div>
                            </div>
                            <span class="badge text-bg-light border"><?= number_format($tableCounts[$table] ?? 0) ?> รายการ</span>
                        </div>
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-sm btn-dark rounded-pill">เปิดตาราง</a>
                            <?php if (!empty($config['create_allowed'])): ?><a href="db_row_create.php?table=<?= urlencode($table) ?>" class="btn btn-sm btn-outline-dark rounded-pill">เพิ่มข้อมูล</a><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3"><div><h2 class="h4 mb-0">บันทึกการเปลี่ยนแปลงล่าสุด</h2><div class="small text-muted mt-1">สามารถพิมพ์หรือส่งออกข้อมูลจากรายการตรวจสอบย้อนหลังนี้ได้ทันที</div></div><div class="d-flex flex-wrap gap-2"><a href="report_print.php?<?= htmlspecialchars($auditPrintQuery) ?>" target="_blank" rel="noopener" class="btn btn-outline-dark rounded-pill"><i class="bi bi-printer me-1"></i>พิมพ์รายงาน</a><a href="report_print.php?<?= htmlspecialchars($auditPdfQuery) ?>" target="_blank" rel="noopener" class="btn btn-outline-dark rounded-pill"><i class="bi bi-filetype-pdf me-1"></i>ส่งออก PDF</a><a href="export_report.php?<?= htmlspecialchars($auditCsvQuery) ?>" class="btn btn-dark rounded-pill"><i class="bi bi-filetype-csv me-1"></i>ส่งออก CSV</a><a href="db_change_logs.php" class="btn btn-outline-secondary rounded-pill">ดูทั้งหมด</a></div></div>
        <div class="table-responsive table-shell">
            <table class="table align-middle ops-table mb-0">
                <?php app_render_table_colgroup('db_change_logs'); ?>
                <thead class="table-light"><tr><th>ลำดับ</th><th>เวลา</th><th>ตาราง</th><th>การกระทำ</th><th>ผู้ดำเนินการ</th><th>หมายเหตุ</th></tr></thead>
                <tbody>
                    <?php if (!$recentLogs): ?><tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีบันทึกการเปลี่ยนแปลง</td></tr><?php endif; ?>
                    <?php foreach ($recentLogs as $index => $log): ?><tr><td class="fw-semibold"><?= $index + 1 ?></td><td><?= htmlspecialchars(app_format_thai_datetime($log['created_at'])) ?></td><td><?= htmlspecialchars($tableConfigs[$log['table_name']]['label'] ?? $log['table_name']) ?></td><td><?= htmlspecialchars($log['action_type']) ?></td><td><?= htmlspecialchars($log['actor_name_snapshot']) ?></td><td><?= htmlspecialchars($log['note'] ?: '-') ?></td></tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
