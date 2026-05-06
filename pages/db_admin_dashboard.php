<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

app_require_permission('can_manage_database');
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
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

$tableConfigs = app_db_admin_tables();
$tableCounts = app_db_admin_table_counts($conn);
$auditTableExists = app_table_exists($conn, 'db_admin_audit_logs');
$recentLogs = $auditTableExists
    ? $conn->query("SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$auditTodayCount = 0;
$latestLogsByTable = [];
if ($auditTableExists) {
    $todayStmt = $conn->prepare("SELECT COUNT(*) FROM db_admin_audit_logs WHERE DATE(created_at) = CURDATE()");
    $todayStmt->execute();
    $auditTodayCount = (int) $todayStmt->fetchColumn();

    $latestStmt = $conn->query("SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT 80");
    foreach ($latestStmt->fetchAll(PDO::FETCH_ASSOC) as $logRow) {
        $tableName = (string) ($logRow['table_name'] ?? '');
        if ($tableName !== '' && !isset($latestLogsByTable[$tableName])) {
            $latestLogsByTable[$tableName] = $logRow;
        }
    }
}

$tableSearch = trim((string) ($_GET['table_q'] ?? ''));
$tableType = trim((string) ($_GET['table_type'] ?? ''));
$tableStatus = trim((string) ($_GET['status'] ?? ''));
$tableOwner = trim((string) ($_GET['owner'] ?? ''));
$latestDate = trim((string) ($_GET['latest_date'] ?? ''));

$tableCategories = [
    'master' => 'ข้อมูลหลัก',
    'attendance' => 'ข้อมูลลงเวลา',
    'audit' => 'บันทึกตรวจสอบ',
];
$tableCategoryMap = [
    'users' => 'master',
    'departments' => 'master',
    'time_logs' => 'attendance',
    'time_log_audit_trails' => 'audit',
    'user_permission_audit_trails' => 'audit',
    'db_admin_audit_logs' => 'audit',
];
$tableIconMap = [
    'users' => 'bi-people',
    'departments' => 'bi-building',
    'time_logs' => 'bi-clock-history',
    'time_log_audit_trails' => 'bi-journal-check',
    'user_permission_audit_trails' => 'bi-shield-check',
    'db_admin_audit_logs' => 'bi-database-check',
];

$visibleTables = [];
foreach ($tableConfigs as $table => $config) {
    $label = (string) ($config['label'] ?? $table);
    $description = (string) ($config['description'] ?? '');
    $category = $tableCategoryMap[$table] ?? 'master';
    $latestLog = $latestLogsByTable[$table] ?? null;
    $latestCreatedAt = (string) ($latestLog['created_at'] ?? '');
    $haystack = mb_strtolower($table . ' ' . $label . ' ' . $description, 'UTF-8');

    if ($tableSearch !== '' && !str_contains($haystack, mb_strtolower($tableSearch, 'UTF-8'))) {
        continue;
    }
    if ($tableType !== '' && $category !== $tableType) {
        continue;
    }
    if ($tableStatus !== '' && $tableStatus !== 'ready') {
        continue;
    }
    if ($tableOwner !== '' && $tableOwner !== 'admin') {
        continue;
    }
    if ($latestDate !== '' && ($latestCreatedAt === '' || date('Y-m-d', strtotime($latestCreatedAt)) !== $latestDate)) {
        continue;
    }

    $visibleTables[$table] = $config;
}

$tableTotal = count($tableConfigs);
$visibleTableTotal = count($visibleTables);
$recentLogTotal = count($recentLogs);
$readyTableTotal = $tableTotal;
$auditCoveragePercent = $tableTotal > 0 ? 100 : 0;
$periodLabel = app_format_thai_month_year(date('Y-m'));
$latestLabel = app_format_thai_datetime(date('Y-m-d H:i:s'), true);
$latestEditor = '-';
foreach ($recentLogs as $log) {
    $candidate = trim((string) ($log['actor_name_snapshot'] ?? ''));
    if ($candidate !== '') {
        $latestEditor = $candidate;
        break;
    }
}

$auditPrintQuery = http_build_query(['type' => 'db_change_logs']);
$auditPdfQuery = http_build_query(['type' => 'db_change_logs', 'download' => 'pdf']);
$auditCsvQuery = http_build_query(['type' => 'db_change_logs']);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการข้อมูลฐานข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell db-admin-page-shell">
<?php render_dashboard_sidebar('db_admin_dashboard.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main db-admin-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">จัดการข้อมูลฐานข้อมูล</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาชื่อ, ตาราง, โมดูล หรือคำอธิบาย">
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

    <div class="db-admin-dashboard-frame">
        <section class="db-admin-hero-card">
            <div class="db-admin-hero-copy">
                <span class="dash-hero-pill"><i class="bi bi-database-gear"></i> Database Admin</span>
                <h2>จัดการข้อมูลฐานข้อมูล</h2>
                <p>สำหรับผู้ดูแลระบบเท่านั้น ใช้จัดการตารางข้อมูลที่อนุญาต ตรวจสอบการเปลี่ยนแปลง และติดตามประวัติการทำงานอย่างปลอดภัย</p>
                <div class="dash-hero-chips">
                    <span class="dash-hero-chip"><i class="bi bi-table"></i>ตารางที่จัดการได้ <?= number_format($tableTotal) ?> ตาราง</span>
                    <span class="dash-hero-chip"><i class="bi bi-clock-history"></i>รายการเปลี่ยนล่าสุด <?= number_format($recentLogTotal) ?> รายการ</span>
                    <span class="dash-hero-chip"><i class="bi bi-shield-check"></i>audit logs วันนี้ <?= number_format($auditTodayCount) ?> รายการ</span>
                </div>
            </div>

            <div class="db-admin-hero-metrics" aria-label="สรุประบบฐานข้อมูล">
                <div class="db-admin-hero-metric">
                    <span class="db-admin-hero-icon is-blue"><i class="bi bi-table"></i></span>
                    <strong><?= number_format($tableTotal) ?></strong>
                    <span>จำนวนตารางข้อมูล</span>
                </div>
                <div class="db-admin-hero-metric">
                    <span class="db-admin-hero-icon is-green"><i class="bi bi-clock-history"></i></span>
                    <strong><?= number_format($recentLogTotal) ?></strong>
                    <span>รายการเปลี่ยนล่าสุด</span>
                </div>
                <div class="db-admin-hero-metric">
                    <span class="db-admin-hero-icon is-amber"><i class="bi bi-clipboard-data"></i></span>
                    <strong><?= number_format($auditTodayCount) ?></strong>
                    <span>audit logs วันนี้</span>
                </div>
                <div class="db-admin-hero-metric">
                    <span class="db-admin-hero-icon is-purple"><i class="bi bi-shield-lock"></i></span>
                    <strong>ปลอดภัย</strong>
                    <span>สถานะสำรองข้อมูล</span>
                </div>
            </div>

            <div class="db-admin-hero-actions">
                <a href="db_table_browser.php" class="dash-btn dash-btn-primary db-admin-hero-primary">
                    <i class="bi bi-folder2-open"></i>
                    เปิดรายการตาราง
                </a>
                <a href="db_change_logs.php" class="dash-btn dash-btn-secondary db-admin-hero-secondary">
                    <i class="bi bi-journal-text"></i>
                    บันทึกการเปลี่ยนแปลงข้อมูล
                </a>
            </div>
        </section>

        <section class="db-admin-summary-row" aria-label="สรุปข้อมูลฐานข้อมูล">
            <article class="db-admin-summary-card">
                <span class="db-admin-summary-icon is-blue"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                <div>
                    <p>ขอบเขตข้อมูล</p>
                    <strong>ตารางที่อนุญาตทั้งหมด</strong>
                    <span>จัดการได้ทั้งหมด <?= number_format($tableTotal) ?> ตาราง</span>
                </div>
            </article>
            <article class="db-admin-summary-card">
                <span class="db-admin-summary-icon is-green"><i class="bi bi-calendar3"></i></span>
                <div>
                    <p>เดือนปัจจุบัน</p>
                    <strong><?= htmlspecialchars($periodLabel) ?></strong>
                    <span>ช่วงเวลาที่เลือก</span>
                </div>
            </article>
            <article class="db-admin-summary-card">
                <span class="db-admin-summary-icon is-amber"><i class="bi bi-database-check"></i></span>
                <div>
                    <p>ตารางพร้อมใช้งาน</p>
                    <strong><?= number_format($readyTableTotal) ?> ตาราง</strong>
                    <span>สถานะพร้อมใช้งาน</span>
                </div>
            </article>
            <article class="db-admin-summary-card">
                <span class="db-admin-summary-icon is-purple"><i class="bi bi-person"></i></span>
                <div>
                    <p>ผู้แก้ไขล่าสุด</p>
                    <strong><?= htmlspecialchars($latestEditor) ?></strong>
                    <span>แก้ไขล่าสุดจาก audit log</span>
                </div>
            </article>
        </section>

        <section class="db-admin-content-grid">
            <aside class="db-admin-filter-card">
                <div class="db-admin-section-eyebrow">Data Filters</div>
                <h2 class="db-admin-card-title">ตัวกรองข้อมูลและเครื่องมือ</h2>
                <p class="db-admin-card-copy">ค้นหาและคัดกรองตารางที่ผู้ดูแลระบบสามารถจัดการได้</p>

                <form method="get" class="db-admin-filter-form">
                    <label class="db-admin-field is-wide">
                        <span>ค้นหาชื่อตาราง / โมดูล</span>
                        <span class="db-admin-search-field">
                            <i class="bi bi-search"></i>
                            <input type="text" name="table_q" value="<?= htmlspecialchars($tableSearch) ?>" placeholder="ค้นหาชื่อตาราง หรือคำอธิบาย">
                        </span>
                    </label>

                    <label class="db-admin-field">
                        <span>ประเภทข้อมูล</span>
                        <select name="table_type">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($tableCategories as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $tableType === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="db-admin-field">
                        <span>ผู้รับผิดชอบ</span>
                        <select name="owner">
                            <option value="">ทั้งหมด</option>
                            <option value="admin" <?= $tableOwner === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                        </select>
                    </label>

                    <label class="db-admin-field">
                        <span>สถานะ</span>
                        <select name="status">
                            <option value="">ทั้งหมด</option>
                            <option value="ready" <?= $tableStatus === 'ready' ? 'selected' : '' ?>>พร้อมใช้งาน</option>
                        </select>
                    </label>

                    <label class="db-admin-field">
                        <span>วันที่แก้ไขล่าสุด</span>
                        <input type="date" name="latest_date" value="<?= htmlspecialchars($latestDate) ?>">
                    </label>

                    <div class="db-admin-filter-actions is-wide">
                        <a href="db_admin_dashboard.php" class="dash-btn dash-btn-ghost db-admin-action-btn">
                            <i class="bi bi-arrow-clockwise"></i>
                            ล้างตัวกรอง
                        </a>
                        <button type="submit" class="dash-btn dash-btn-primary db-admin-action-btn">
                            <i class="bi bi-search"></i>
                            ค้นหา
                        </button>
                    </div>
                </form>

                <div class="db-admin-tools-card">
                    <h3>จัดการรายงาน</h3>
                    <div class="db-admin-tool-grid">
                        <a href="report_print.php?<?= htmlspecialchars($auditPrintQuery) ?>" target="_blank" rel="noopener" class="db-admin-tool-btn">
                            <i class="bi bi-printer"></i>
                            พิมพ์รายงาน
                        </a>
                        <a href="report_print.php?<?= htmlspecialchars($auditPdfQuery) ?>" target="_blank" rel="noopener" class="db-admin-tool-btn">
                            <i class="bi bi-filetype-pdf"></i>
                            ส่งออก PDF
                        </a>
                        <a href="export_report.php?<?= htmlspecialchars($auditCsvQuery) ?>" class="db-admin-tool-btn">
                            <i class="bi bi-filetype-csv"></i>
                            ส่งออก CSV
                        </a>
                    </div>
                </div>
            </aside>

            <section class="db-admin-table-card">
                <div class="db-admin-results-header">
                    <div>
                        <h2 class="db-admin-card-title">รายการตารางที่จัดการได้</h2>
                        <p class="db-admin-card-copy">เปิดดูรายละเอียด เข้าใช้งานตาราง และติดตามสถานะจากข้อมูลจริงในระบบ</p>
                    </div>
                    <div class="db-admin-view-switch" aria-label="ตัวเลือกมุมมอง">
                        <button type="button" class="active"><i class="bi bi-table"></i>ตาราง</button>
                        <button type="button"><i class="bi bi-grid"></i>การ์ด</button>
                    </div>
                </div>

                <div class="db-admin-table-shell">
                    <table class="db-admin-table">
                        <thead>
                            <tr>
                                <th>ลำดับ</th>
                                <th>ชื่อตาราง</th>
                                <th>คำอธิบาย</th>
                                <th>จำนวนรายการ</th>
                                <th>แก้ไขล่าสุด</th>
                                <th>ผู้ดำเนินการล่าสุด</th>
                                <th>สถานะ</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$visibleTables): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="db-admin-empty-state">
                                            <i class="bi bi-search"></i>
                                            <strong>ไม่พบตารางตามตัวกรอง</strong>
                                            <span>ลองล้างตัวกรองหรือค้นหาด้วยคำอื่น</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php $rowNumber = 1; ?>
                            <?php foreach ($visibleTables as $table => $config): ?>
                                <?php
                                $latestLog = $latestLogsByTable[$table] ?? null;
                                $latestLogLabel = !empty($latestLog['created_at']) ? app_format_thai_datetime((string) $latestLog['created_at'], true) : '-';
                                $latestActor = trim((string) ($latestLog['actor_name_snapshot'] ?? '')) ?: '-';
                                ?>
                                <tr>
                                    <td class="db-admin-index-cell"><?= $rowNumber++ ?></td>
                                    <td>
                                        <div class="db-admin-table-name">
                                            <span><i class="bi <?= htmlspecialchars($tableIconMap[$table] ?? 'bi-table') ?>"></i></span>
                                            <div>
                                                <strong><?= htmlspecialchars($config['label']) ?></strong>
                                                <em><?= htmlspecialchars($table) ?></em>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="db-admin-description"><?= htmlspecialchars($config['description']) ?></span></td>
                                    <td><span class="db-admin-count"><?= number_format($tableCounts[$table] ?? 0) ?></span></td>
                                    <td><?= htmlspecialchars($latestLogLabel) ?></td>
                                    <td><?= htmlspecialchars($latestActor) ?></td>
                                    <td><span class="db-admin-status">พร้อมใช้งาน</span></td>
                                    <td>
                                        <div class="db-admin-row-actions">
                                            <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="db-admin-row-btn">ดูรายละเอียด</a>
                                            <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="db-admin-open-btn">เปิดตาราง</a>
                                            <button type="button" class="db-admin-row-menu" aria-label="ตัวเลือกเพิ่มเติม"><i class="bi bi-three-dots"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="db-admin-table-actions">
                    <span><i class="bi bi-list-ul"></i> <?= number_format($visibleTableTotal) ?> จาก <?= number_format($tableTotal) ?> ตาราง</span>
                    <div>
                        <a href="report_print.php?<?= htmlspecialchars($auditPrintQuery) ?>" target="_blank" rel="noopener" class="db-admin-table-tool"><i class="bi bi-printer"></i>พิมพ์รายงาน</a>
                        <a href="report_print.php?<?= htmlspecialchars($auditPdfQuery) ?>" target="_blank" rel="noopener" class="db-admin-table-tool"><i class="bi bi-filetype-pdf"></i>ส่งออก PDF</a>
                        <a href="export_report.php?<?= htmlspecialchars($auditCsvQuery) ?>" class="db-admin-table-tool"><i class="bi bi-filetype-csv"></i>ส่งออก CSV</a>
                        <a href="db_table_browser.php" class="db-admin-table-tool"><i class="bi bi-eye"></i>ดูทั้งหมด</a>
                    </div>
                </div>
            </section>
        </section>

        <section class="db-admin-audit-card">
            <div class="db-admin-audit-header">
                <div>
                    <h2 class="db-admin-card-title">บันทึกการเปลี่ยนแปลงล่าสุด</h2>
                    <p class="db-admin-card-copy">ตรวจสอบ audit log ล่าสุดจากโมดูลจัดการข้อมูลฐานข้อมูล</p>
                </div>
                <div class="db-admin-audit-actions">
                    <a href="report_print.php?<?= htmlspecialchars($auditPrintQuery) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i>พิมพ์รายงาน</a>
                    <a href="report_print.php?<?= htmlspecialchars($auditPdfQuery) ?>" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i>ส่งออก PDF</a>
                    <a href="export_report.php?<?= htmlspecialchars($auditCsvQuery) ?>"><i class="bi bi-filetype-csv"></i>ส่งออก CSV</a>
                    <a href="db_change_logs.php"><i class="bi bi-clock-history"></i>ดูทั้งหมด</a>
                </div>
            </div>
            <div class="db-admin-audit-table-shell">
                <table class="db-admin-audit-table">
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>เวลา</th>
                            <th>ตาราง</th>
                            <th>การกระทำ</th>
                            <th>ผู้ดำเนินการ</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentLogs): ?>
                            <tr><td colspan="6" class="db-admin-empty-cell">ยังไม่มีบันทึกการเปลี่ยนแปลง</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentLogs as $index => $log): ?>
                            <tr>
                                <td class="db-admin-index-cell"><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars(app_format_thai_datetime($log['created_at'])) ?></td>
                                <td><?= htmlspecialchars($tableConfigs[$log['table_name']]['label'] ?? $log['table_name']) ?></td>
                                <td><?= htmlspecialchars($log['action_type']) ?></td>
                                <td><?= htmlspecialchars($log['actor_name_snapshot']) ?></td>
                                <td><?= htmlspecialchars($log['note'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="db-admin-bottom-strip" aria-label="สรุปข้อมูลฐานข้อมูล">
            <div class="db-admin-bottom-item is-title">สรุปข้อมูลฐานข้อมูล</div>
            <div class="db-admin-bottom-item">
                <span>ตารางทั้งหมด</span>
                <strong><?= number_format($tableTotal) ?></strong>
                <em>ตารางที่จัดการได้</em>
            </div>
            <div class="db-admin-bottom-item">
                <span>รายการล่าสุด</span>
                <strong><?= number_format($recentLogTotal) ?></strong>
                <em>รายการเปลี่ยนแปลงล่าสุด</em>
            </div>
            <div class="db-admin-bottom-item">
                <span>logs วันนี้</span>
                <strong><?= number_format($auditTodayCount) ?></strong>
                <em>รายการ audit logs วันนี้</em>
            </div>
            <div class="db-admin-bottom-item">
                <span>สถานะสำรองข้อมูล</span>
                <strong>ปลอดภัย</strong>
                <em>ระบบสำรองข้อมูลปกติ</em>
            </div>
            <div class="db-admin-bottom-progress">
                <div>
                    <span>ความครอบคลุม audit</span>
                    <strong><?= number_format($auditCoveragePercent, 0) ?>%</strong>
                </div>
                <div class="db-admin-progress-track"><span style="width: <?= min(100, max(0, $auditCoveragePercent)) ?>%"></span></div>
                <em>ตรวจสอบ <?= number_format($readyTableTotal) ?> จาก <?= number_format($tableTotal) ?> ตาราง</em>
            </div>
        </section>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
