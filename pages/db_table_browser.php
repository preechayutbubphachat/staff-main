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
$actorId = $currentUserId;
$actorName = (string) ($_SESSION['fullname'] ?? '');
$csrfToken = app_csrf_token('db_table_browser');
$message = $_SESSION['db_admin_flash'] ?? '';
$messageType = $_SESSION['db_admin_flash_type'] ?? 'success';
unset($_SESSION['db_admin_flash'], $_SESSION['db_admin_flash_type']);

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
$auditTableExists = app_table_exists($conn, 'db_admin_audit_logs');
$recentLogs = $auditTableExists
    ? $conn->query("SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$latestLogsByTable = [];
$auditTodayCount = 0;
if ($auditTableExists) {
    $todayStmt = $conn->prepare("SELECT COUNT(*) FROM db_admin_audit_logs WHERE DATE(created_at) = CURDATE()");
    $todayStmt->execute();
    $auditTodayCount = (int) $todayStmt->fetchColumn();

    $latestStmt = $conn->query("SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT 80");
    foreach ($latestStmt->fetchAll(PDO::FETCH_ASSOC) as $logRow) {
        $logTable = (string) ($logRow['table_name'] ?? '');
        if ($logTable !== '' && !isset($latestLogsByTable[$logTable])) {
            $latestLogsByTable[$logTable] = $logRow;
        }
    }
}

$tableSearch = trim((string) ($_GET['table_q'] ?? ''));
$tableType = trim((string) ($_GET['table_type'] ?? ''));
$tableStatus = trim((string) ($_GET['table_status'] ?? ''));
$tableOwner = trim((string) ($_GET['table_owner'] ?? ''));
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
foreach ($tableConfigs as $tableName => $tableConfig) {
    $label = (string) ($tableConfig['label'] ?? $tableName);
    $description = (string) ($tableConfig['description'] ?? '');
    $category = $tableCategoryMap[$tableName] ?? 'master';
    $latestLog = $latestLogsByTable[$tableName] ?? null;
    $latestCreatedAt = (string) ($latestLog['created_at'] ?? '');
    $haystack = mb_strtolower($tableName . ' ' . $label . ' ' . $description, 'UTF-8');

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

    $visibleTables[$tableName] = $tableConfig;
}

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

$tableTotal = count($tableConfigs);
$readyTableTotal = $tableTotal;
$visibleTableTotal = count($visibleTables);
$totalRecordCount = array_sum(array_map('intval', $tableCounts));
$recentLogTotal = count($recentLogs);
$periodLabel = app_format_thai_month_year(date('Y-m'));
$latestLog = $recentLogs[0] ?? null;
$latestSyncLabel = !empty($latestLog['created_at']) ? app_format_thai_datetime((string) $latestLog['created_at'], true) : app_format_thai_datetime(date('Y-m-d H:i:s'), true);
$latestSyncShort = !empty($latestLog['created_at']) ? date('H:i', strtotime((string) $latestLog['created_at'])) . ' น.' : date('H:i') . ' น.';
$latestEditor = '-';
foreach ($recentLogs as $log) {
    $candidate = trim((string) ($log['actor_name_snapshot'] ?? ''));
    if ($candidate !== '') {
        $latestEditor = $candidate;
        break;
    }
}
$integrityPercent = $tableTotal > 0 ? 100 : 0;

$printQuery = $config ? app_db_admin_query_string($filters, ['type' => 'db_table', 'table' => $table]) : '';
$pdfQuery = $config ? app_db_admin_query_string($filters, ['type' => 'db_table', 'table' => $table, 'download' => 'pdf']) : '';
$csvQuery = $config ? app_db_admin_query_string($filters, ['type' => 'db_table', 'table' => $table]) : '';
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการตารางข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell db-table-page-shell">
<?php render_dashboard_sidebar('db_table_browser.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main db-table-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">จัดการตารางข้อมูล</h1>
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

    <div class="db-table-dashboard-frame">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-0"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <section class="db-table-hero">
            <div class="db-table-hero-copy">
                <span class="db-table-hero-pill"><i class="bi bi-sliders"></i> Table Management</span>
                <h2>จัดการตารางข้อมูล</h2>
                <p>จัดการตารางข้อมูลที่อนุญาตในระบบ ตรวจสอบโครงสร้างตาราง จำนวนข้อมูล และควบคุมการเข้าถึงอย่างปลอดภัย</p>
                <div class="db-table-hero-chips">
                    <span><i class="bi bi-calendar3"></i>เดือนปัจจุบัน: <?= htmlspecialchars($periodLabel) ?></span>
                    <span><i class="bi bi-lock"></i>ตารางที่อนุญาต: <?= number_format($tableTotal) ?> ตาราง</span>
                    <span><i class="bi bi-arrow-repeat"></i>ซิงค์ล่าสุด: <?= htmlspecialchars($latestSyncShort) ?></span>
                </div>
            </div>

            <div class="db-table-hero-metrics">
                <div class="db-table-hero-metric">
                    <span class="db-table-metric-icon is-blue"><i class="bi bi-database"></i></span>
                    <strong><?= number_format($tableTotal) ?></strong>
                    <span>จำนวนตาราง</span>
                    <em>ตาราง</em>
                </div>
                <div class="db-table-hero-metric">
                    <span class="db-table-metric-icon is-green"><i class="bi bi-check-circle"></i></span>
                    <strong><?= number_format($readyTableTotal) ?></strong>
                    <span>พร้อมใช้งาน</span>
                    <em>ตาราง</em>
                </div>
                <div class="db-table-hero-metric">
                    <span class="db-table-metric-icon is-amber"><i class="bi bi-file-earmark-text"></i></span>
                    <strong><?= number_format($totalRecordCount) ?></strong>
                    <span>รายการข้อมูลรวม</span>
                    <em>รายการ</em>
                </div>
                <div class="db-table-hero-metric">
                    <span class="db-table-metric-icon is-purple"><i class="bi bi-clock"></i></span>
                    <strong>วันนี้</strong>
                    <span>อัปเดตล่าสุด</span>
                    <em><?= htmlspecialchars($latestSyncShort) ?></em>
                </div>
            </div>

            <div class="db-table-hero-actions">
                <a href="#dbTableList" class="db-table-hero-action is-primary">
                    <i class="bi bi-plus-circle"></i>
                    สร้างตารางใหม่
                </a>
                <a href="db_change_logs.php" class="db-table-hero-action is-secondary">
                    <i class="bi bi-clock-history"></i>
                    ดูประวัติการแก้ไข
                </a>
            </div>
        </section>

        <section class="db-table-kpi-grid" aria-label="สรุปตารางข้อมูล">
            <article class="db-table-kpi-card">
                <span class="db-table-kpi-icon is-blue"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                <div>
                    <small>ตารางทั้งหมดในระบบ</small>
                    <strong>จัดการและควบคุมได้ <?= number_format($tableTotal) ?> ตาราง</strong>
                    <span>ครอบคลุมตารางที่ได้รับอนุญาต</span>
                </div>
            </article>
            <article class="db-table-kpi-card">
                <span class="db-table-kpi-icon is-green"><i class="bi bi-calendar3"></i></span>
                <div>
                    <small><?= htmlspecialchars($periodLabel) ?></small>
                    <strong>ช่วงเวลาที่เลือก</strong>
                    <span>ใช้สำหรับรายงานและ audit ล่าสุด</span>
                </div>
            </article>
            <article class="db-table-kpi-card">
                <span class="db-table-kpi-icon is-teal"><i class="bi bi-shield-check"></i></span>
                <div>
                    <small>พร้อมใช้งาน</small>
                    <strong>ระบบทำงานปกติ</strong>
                    <span><?= number_format($readyTableTotal) ?> ตารางพร้อมจัดการ</span>
                </div>
            </article>
            <article class="db-table-kpi-card">
                <span class="db-table-kpi-icon is-purple"><i class="bi bi-person"></i></span>
                <div>
                    <small>ผู้แก้ไขล่าสุด</small>
                    <strong><?= htmlspecialchars($latestEditor) ?></strong>
                    <span>แก้ไขล่าสุด <?= htmlspecialchars($latestSyncLabel) ?></span>
                </div>
            </article>
        </section>

        <section class="db-table-content-grid" id="dbTableList">
            <aside class="db-table-filter-card">
                <div class="db-table-card-heading">
                    <span>Data Filters</span>
                    <h2>ตัวกรองข้อมูลและเครื่องมือ</h2>
                </div>

                <form method="get" class="db-table-filter-form">
                    <label class="db-table-field is-wide">
                        <span>ค้นหาตาราง</span>
                        <span class="db-table-search-field">
                            <i class="bi bi-search"></i>
                            <input type="text" name="table_q" value="<?= htmlspecialchars($tableSearch) ?>" placeholder="ค้นหาชื่อตาราง / คำอธิบาย">
                        </span>
                    </label>

                    <label class="db-table-field">
                        <span>ประเภทข้อมูล</span>
                        <select name="table_type">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($tableCategories as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $tableType === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="db-table-field">
                        <span>สถานะ</span>
                        <select name="table_status">
                            <option value="">ทั้งหมด</option>
                            <option value="ready" <?= $tableStatus === 'ready' ? 'selected' : '' ?>>พร้อมใช้งาน</option>
                        </select>
                    </label>

                    <label class="db-table-field">
                        <span>เจ้าของตาราง</span>
                        <select name="table_owner">
                            <option value="">ทั้งหมด</option>
                            <option value="admin" <?= $tableOwner === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                        </select>
                    </label>

                    <label class="db-table-field">
                        <span>วันที่แก้ไขล่าสุด</span>
                        <input type="date" name="latest_date" value="<?= htmlspecialchars($latestDate) ?>">
                    </label>

                    <div class="db-table-filter-actions is-wide">
                        <a href="db_table_browser.php" class="db-table-button is-ghost">
                            <i class="bi bi-arrow-clockwise"></i>
                            ล้างตัวกรอง
                        </a>
                        <button type="submit" class="db-table-button is-primary">
                            <i class="bi bi-search"></i>
                            ค้นหา
                        </button>
                    </div>
                </form>

                <div class="db-table-tools-card">
                    <h3>จัดการรายงาน</h3>
                    <div class="db-table-tool-grid">
                        <a href="report_print.php?type=db_change_logs" target="_blank" rel="noopener" class="db-table-tool-button"><i class="bi bi-printer"></i>พิมพ์รายงาน</a>
                        <a href="report_print.php?type=db_change_logs&amp;download=pdf" target="_blank" rel="noopener" class="db-table-tool-button"><i class="bi bi-filetype-pdf"></i>ส่งออก PDF</a>
                        <a href="export_report.php?type=db_change_logs" class="db-table-tool-button"><i class="bi bi-filetype-csv"></i>ส่งออก CSV</a>
                    </div>
                </div>
            </aside>

            <section class="db-table-table-card">
                <div class="db-table-results-header">
                    <div>
                        <h2>รายการตารางที่จัดการได้</h2>
                        <p>เปิดดูรายละเอียด เข้าใช้งานตาราง และติดตามสถานะจากข้อมูลจริงในระบบ</p>
                    </div>
                    <div class="db-table-view-switch" aria-label="ตัวเลือกมุมมอง">
                        <button type="button" class="active"><i class="bi bi-table"></i>ตาราง</button>
                        <button type="button"><i class="bi bi-grid"></i>การ์ด</button>
                    </div>
                </div>

                <div class="db-table-table-shell">
                    <table class="db-table-management-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" aria-label="เลือกทั้งหมด"></th>
                                <th>ลำดับ</th>
                                <th>ชื่อตาราง</th>
                                <th>คำอธิบาย</th>
                                <th>จำนวนคอลัมน์</th>
                                <th>จำนวนข้อมูล</th>
                                <th>ผู้รับผิดชอบ</th>
                                <th>สถานะ</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$visibleTables): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="db-table-empty-state">
                                            <i class="bi bi-search"></i>
                                            <strong>ไม่พบตารางตามตัวกรอง</strong>
                                            <span>ลองล้างตัวกรองหรือค้นหาด้วยคำอื่น</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php $rowNumber = 1; ?>
                            <?php foreach ($visibleTables as $tableName => $tableConfig): ?>
                                <?php
                                $columnCount = count($tableConfig['browse_columns'] ?? []);
                                $rowCount = (int) ($tableCounts[$tableName] ?? 0);
                                $statusClass = 'is-ready';
                                $statusLabel = 'พร้อมใช้งาน';
                                ?>
                                <tr>
                                    <td><input type="checkbox" aria-label="เลือก <?= htmlspecialchars($tableConfig['label'] ?? $tableName) ?>"></td>
                                    <td class="db-table-index-cell"><?= $rowNumber++ ?></td>
                                    <td>
                                        <div class="db-table-name-cell">
                                            <span><i class="bi <?= htmlspecialchars($tableIconMap[$tableName] ?? 'bi-table') ?>"></i></span>
                                            <div>
                                                <strong><?= htmlspecialchars($tableConfig['label'] ?? $tableName) ?></strong>
                                                <em><?= htmlspecialchars($tableName) ?></em>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="db-table-description"><?= htmlspecialchars($tableConfig['description'] ?? '-') ?></span></td>
                                    <td><?= number_format($columnCount) ?></td>
                                    <td><span class="db-table-count"><?= number_format($rowCount) ?></span></td>
                                    <td>ผู้ดูแลระบบ</td>
                                    <td><span class="db-table-status <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                                    <td>
                                        <div class="db-table-row-actions">
                                            <a href="db_table_browser.php?table=<?= urlencode($tableName) ?>" class="db-table-row-btn">ดูรายละเอียด</a>
                                            <a href="db_table_browser.php?table=<?= urlencode($tableName) ?>" class="db-table-row-btn is-edit">แก้ไข</a>
                                            <button type="button" class="db-table-row-menu" aria-label="ตัวเลือกเพิ่มเติม"><i class="bi bi-three-dots"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <section class="db-table-bottom-strip" aria-label="สรุปข้อมูลตาราง">
            <div class="db-table-bottom-item is-title">สรุปข้อมูลตาราง</div>
            <div class="db-table-bottom-item">
                <span>ตารางทั้งหมด</span>
                <strong><?= number_format($tableTotal) ?></strong>
                <em>ตาราง</em>
            </div>
            <div class="db-table-bottom-item">
                <span>พร้อมใช้งาน</span>
                <strong><?= number_format($readyTableTotal) ?></strong>
                <em>ตาราง</em>
            </div>
            <div class="db-table-bottom-item">
                <span>จำนวนแถวรวม</span>
                <strong><?= number_format($totalRecordCount) ?></strong>
                <em>รายการ</em>
            </div>
            <div class="db-table-bottom-item">
                <span>logs วันนี้</span>
                <strong><?= number_format($auditTodayCount) ?></strong>
                <em>รายการ</em>
            </div>
            <div class="db-table-bottom-progress">
                <div>
                    <span>ความสมบูรณ์ของข้อมูล</span>
                    <strong><?= number_format($integrityPercent) ?>%</strong>
                </div>
                <div class="db-table-progress-track"><span style="width: <?= min(100, max(0, $integrityPercent)) ?>%"></span></div>
                <em>ตรวจสอบ <?= number_format($readyTableTotal) ?> จาก <?= number_format($tableTotal) ?> ตาราง</em>
            </div>
        </section>

        <?php if ($config): ?>
            <section class="db-table-selected-panel">
                <div class="db-table-selected-toolbar">
                    <div>
                        <span>Selected Table</span>
                        <h2><?= htmlspecialchars($config['label']) ?></h2>
                        <p><?= htmlspecialchars($config['description']) ?></p>
                    </div>
                    <div class="db-table-selected-actions">
                        <form method="get" id="dbTableFilterForm" class="db-table-selected-search" data-page-state-key="db_table_browser">
                            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                            <input type="hidden" name="page" value="<?= (int) $page ?>">
                            <label>
                                <i class="bi bi-search"></i>
                                <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="ค้นหาข้อมูลในตารางที่เลือก">
                            </label>
                            <select name="per_page" form="dbTableFilterForm">
                                <?php foreach ([10, 20, 50, 100] as $size): ?>
                                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> รายการ</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="db-table-button is-primary">ค้นหา</button>
                        </form>
                        <div class="db-table-selected-export">
                            <a href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener">พิมพ์รายงาน</a>
                            <a href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener">PDF</a>
                            <a href="export_report.php?<?= htmlspecialchars($csvQuery) ?>">CSV</a>
                            <?php if (!empty($config['create_allowed'])): ?>
                                <a href="db_row_create.php?table=<?= urlencode($table) ?>">เพิ่มข้อมูล</a>
                            <?php endif; ?>
                            <a href="db_table_browser.php?table=<?= urlencode($table) ?>">ล้างตัวกรอง</a>
                        </div>
                    </div>
                </div>
                <div id="dbTableResults"><?php require __DIR__ . '/../partials/admin/db_table_results.php'; ?></div>
            </section>
        <?php endif; ?>
    </div>
</main>

<div id="userEditModal" class="db-user-modal-overlay" hidden aria-hidden="true">
    <div class="db-user-modal-card" role="dialog" aria-modal="true" aria-labelledby="userEditModalTitle">
        <div class="db-user-modal-header">
            <div>
                <p class="db-user-modal-eyebrow">Admin Only</p>
                <h2 id="userEditModalTitle">แก้ไขข้อมูลผู้ใช้งาน</h2>
                <p>แก้ไขข้อมูลผู้ใช้บนหน้าเดิม ระบบยังใช้ฟอร์มและสิทธิ์เดิมของหน้าแก้ไขผู้ใช้งาน</p>
            </div>
            <button type="button" class="db-user-modal-close" data-user-modal-close aria-label="ปิดหน้าต่างแก้ไข">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="db-user-modal-body">
            <div class="db-user-modal-loading" id="userEditModalLoading">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>กำลังโหลดข้อมูลผู้ใช้งาน...</span>
            </div>
            <iframe
                id="userEditModalFrame"
                class="db-user-modal-frame"
                title="แก้ไขข้อมูลผู้ใช้งาน"
                loading="lazy"
                src="about:blank"
            ></iframe>
        </div>
    </div>
</div>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<?php if ($config): ?>
<script src="../assets/js/table-filters.js"></script>
<script>
TableFilters.init({
    formId: 'dbTableFilterForm',
    containerId: 'dbTableResults',
    endpoint: '../ajax/admin/db_table_rows.php',
    pushBase: 'db_table_browser.php'
});

(function () {
    const modal = document.getElementById('userEditModal');
    const frame = document.getElementById('userEditModalFrame');
    const loading = document.getElementById('userEditModalLoading');
    if (!modal || !frame || !loading) {
        return;
    }

    let lastTrigger = null;
    const body = document.body;

    function openUserEditModal(url, trigger) {
        if (!url) return;
        const frameUrl = new URL(url, document.baseURI || window.location.href);
        frameUrl.searchParams.set('modal', '1');
        lastTrigger = trigger || null;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        loading.hidden = false;
        body.classList.add('modal-open');
        frame.src = frameUrl.toString();
        const closeButton = modal.querySelector('[data-user-modal-close]');
        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeUserEditModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        frame.src = 'about:blank';
        loading.hidden = false;
        body.classList.remove('modal-open');
        if (lastTrigger && typeof lastTrigger.focus === 'function') {
            lastTrigger.focus();
        }
    }

    frame.addEventListener('load', function () {
        loading.hidden = true;
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal || event.target.closest('[data-user-modal-close]')) {
            closeUserEditModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeUserEditModal();
        }
    });

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-open-user-edit]');
        if (!trigger) return;
        event.preventDefault();
        const editUrl = trigger.getAttribute('data-edit-url');
        openUserEditModal(editUrl, trigger);
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data || typeof event.data !== 'object') {
            return;
        }

        if (event.data.type === 'db-user-edit-close') {
            closeUserEditModal();
            return;
        }

        if (event.data.type === 'db-user-edit-saved') {
            closeUserEditModal();
            window.location.reload();
        }
    });
})();
</script>
<?php endif; ?>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
