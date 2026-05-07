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
$auditTableExists = app_table_exists($conn, 'db_admin_audit_logs');
$page = app_parse_table_page($_GET, 'page');
$perPage = app_parse_table_page_size($_GET, 20);
$search = trim((string) ($_GET['q'] ?? ''));
$tableFilter = trim((string) ($_GET['table_name'] ?? ''));
$actionFilter = trim((string) ($_GET['action_type'] ?? ''));
$actorFilter = trim((string) ($_GET['actor'] ?? ''));
$dateFilter = trim((string) ($_GET['log_date'] ?? ''));
$rows = [];
$totalRows = 0;
$allRowsTotal = 0;
$todayRowsTotal = 0;
$uniqueActorTotal = 0;
$latestLog = null;
$tableOptions = [];
$actionOptions = [];
$actorOptions = [];

if ($auditTableExists) {
    $allRowsTotal = (int) $conn->query('SELECT COUNT(*) FROM db_admin_audit_logs')->fetchColumn();

    $todayStmt = $conn->prepare('SELECT COUNT(*) FROM db_admin_audit_logs WHERE DATE(created_at) = CURDATE()');
    $todayStmt->execute();
    $todayRowsTotal = (int) $todayStmt->fetchColumn();

    $uniqueActorTotal = (int) $conn->query("SELECT COUNT(DISTINCT NULLIF(actor_name_snapshot, '')) FROM db_admin_audit_logs")->fetchColumn();
    $latestLog = $conn->query('SELECT * FROM db_admin_audit_logs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;

    $tableOptions = $conn->query("SELECT DISTINCT table_name FROM db_admin_audit_logs WHERE table_name IS NOT NULL AND table_name <> '' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    $actionOptions = $conn->query("SELECT DISTINCT action_type FROM db_admin_audit_logs WHERE action_type IS NOT NULL AND action_type <> '' ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
    $actorOptions = $conn->query("SELECT DISTINCT actor_name_snapshot FROM db_admin_audit_logs WHERE actor_name_snapshot IS NOT NULL AND actor_name_snapshot <> '' ORDER BY actor_name_snapshot")->fetchAll(PDO::FETCH_COLUMN);

    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "(table_name LIKE ? OR action_type LIKE ? OR actor_name_snapshot LIKE ? OR COALESCE(note, '') LIKE ?)";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($tableFilter !== '') {
        $where[] = 'table_name = ?';
        $params[] = $tableFilter;
    }
    if ($actionFilter !== '') {
        $where[] = 'action_type = ?';
        $params[] = $actionFilter;
    }
    if ($actorFilter !== '') {
        $where[] = 'actor_name_snapshot = ?';
        $params[] = $actorFilter;
    }
    if ($dateFilter !== '') {
        $where[] = 'DATE(created_at) = ?';
        $params[] = $dateFilter;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM db_admin_audit_logs' . $whereSql);
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare('SELECT * FROM db_admin_audit_logs' . $whereSql . ' ORDER BY id DESC LIMIT ? OFFSET ?');
    $bindIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($bindIndex++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $totalPages = 1;
}

$latestLabel = !empty($latestLog['created_at']) ? app_format_thai_datetime((string) $latestLog['created_at'], true) : app_format_thai_datetime(date('Y-m-d H:i:s'), true);
$latestMonth = !empty($latestLog['created_at']) ? app_format_thai_month_year(date('Y-m', strtotime((string) $latestLog['created_at']))) : app_format_thai_month_year(date('Y-m'));
$primaryActor = trim((string) ($latestLog['actor_name_snapshot'] ?? '')) ?: $displayName;
$systemStatus = $auditTableExists ? 'พร้อมใช้งาน' : 'ยังไม่พบตาราง';
$auditPercent = $auditTableExists ? 100 : 0;

$baseQuery = [
    'q' => $search,
    'table_name' => $tableFilter,
    'action_type' => $actionFilter,
    'actor' => $actorFilter,
    'log_date' => $dateFilter,
    'per_page' => $perPage,
];
$printQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs']);
$pdfQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs', 'download' => 'pdf']);
$csvQuery = app_build_table_query($baseQuery, ['type' => 'db_change_logs']);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>บันทึกการเปลี่ยนแปลงข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell audit-log-page-shell">
<?php render_dashboard_sidebar('db_change_logs.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main audit-log-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">บันทึกการเปลี่ยนแปลงข้อมูล</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาตาราง, การกระทำ, ผู้ดำเนินการ หรือหมายเหตุ">
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

    <div class="audit-log-dashboard-frame">
        <section class="audit-log-hero">
            <div class="audit-log-hero-copy">
                <span class="audit-log-hero-pill"><i class="bi bi-shield-check"></i> Database Audit</span>
                <h2>บันทึกการเปลี่ยนแปลงข้อมูล</h2>
                <p>ติดตาม ตรวจสอบ และทบทวนการเปลี่ยนแปลงข้อมูลในระบบ เพื่อความปลอดภัย ความถูกต้อง และการตรวจสอบย้อนหลังได้</p>
                <div class="audit-log-hero-chips">
                    <span><i class="bi bi-calendar3"></i> ข้อมูลล่าสุด: <?= htmlspecialchars($latestMonth) ?></span>
                    <span><i class="bi bi-list-check"></i> ติดตามได้ <?= number_format($allRowsTotal) ?> รายการ</span>
                    <span><i class="bi bi-clock"></i> อัปเดตล่าสุด: <?= htmlspecialchars($latestLabel) ?></span>
                </div>
            </div>

            <div class="audit-log-hero-metrics">
                <div class="audit-log-hero-metric">
                    <span class="audit-log-metric-icon is-blue"><i class="bi bi-journal-text"></i></span>
                    <strong><?= number_format($allRowsTotal) ?></strong>
                    <span>รายการทั้งหมด</span>
                    <em>รายการ</em>
                </div>
                <div class="audit-log-hero-metric">
                    <span class="audit-log-metric-icon is-green"><i class="bi bi-calendar-check"></i></span>
                    <strong><?= number_format($todayRowsTotal) ?></strong>
                    <span>วันนี้</span>
                    <em>รายการ</em>
                </div>
                <div class="audit-log-hero-metric">
                    <span class="audit-log-metric-icon is-amber"><i class="bi bi-person"></i></span>
                    <strong><?= number_format($uniqueActorTotal) ?></strong>
                    <span>ผู้ดำเนินการ</span>
                    <em>คน</em>
                </div>
                <div class="audit-log-hero-metric">
                    <span class="audit-log-metric-icon is-purple"><i class="bi bi-shield-lock"></i></span>
                    <strong><?= htmlspecialchars($systemStatus) ?></strong>
                    <span>สถานะระบบ</span>
                    <em>ระบบปกติ</em>
                </div>
            </div>

            <div class="audit-log-hero-actions">
                <a href="export_report.php?<?= htmlspecialchars($csvQuery) ?>" class="audit-log-hero-action is-primary">
                    <i class="bi bi-download"></i>
                    ส่งออกบันทึก
                </a>
                <a href="#auditLogTable" class="audit-log-hero-action is-secondary">
                    <i class="bi bi-clock-history"></i>
                    ดูประวัติย้อนหลัง
                </a>
            </div>
        </section>

        <section class="audit-log-kpi-grid" aria-label="สรุปบันทึกการเปลี่ยนแปลง">
            <article class="audit-log-kpi-card">
                <span class="audit-log-kpi-icon is-blue"><i class="bi bi-file-earmark-lock"></i></span>
                <div>
                    <small>ขอบเขตข้อมูล</small>
                    <strong>ติดตามทุกตารางที่อนุญาต</strong>
                    <span>ครอบคลุมฐานข้อมูล <?= number_format(count($tableConfigs)) ?> ตาราง</span>
                </div>
            </article>
            <article class="audit-log-kpi-card">
                <span class="audit-log-kpi-icon is-green"><i class="bi bi-calendar3"></i></span>
                <div>
                    <small>เดือนปัจจุบัน</small>
                    <strong><?= htmlspecialchars(app_format_thai_month_year(date('Y-m'))) ?></strong>
                    <span>ช่วงเวลาที่เลือก</span>
                </div>
            </article>
            <article class="audit-log-kpi-card">
                <span class="audit-log-kpi-icon is-amber"><i class="bi bi-person-badge"></i></span>
                <div>
                    <small>ผู้ดำเนินการหลัก</small>
                    <strong><?= htmlspecialchars($primaryActor) ?></strong>
                    <span>ผู้ดำเนินการล่าสุดของระบบ</span>
                </div>
            </article>
            <article class="audit-log-kpi-card">
                <span class="audit-log-kpi-icon is-purple"><i class="bi bi-shield-check"></i></span>
                <div>
                    <small>สถานะการติดตาม</small>
                    <strong><?= htmlspecialchars($systemStatus) ?></strong>
                    <span>ระบบทำงานปกติ</span>
                </div>
            </article>
        </section>

        <section class="audit-log-main-grid" id="auditLogTable">
            <aside class="audit-log-filter-card">
                <div class="audit-log-card-heading">
                    <span>Audit Filters</span>
                    <h2>ตัวกรองบันทึกการเปลี่ยนแปลง</h2>
                    <p>ค้นหาและกรองรายการเปลี่ยนแปลงที่ต้องตรวจสอบ</p>
                </div>

                <form method="get" id="dbAuditFilterForm" class="audit-log-filter-form" data-page-state-key="db_change_logs">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">

                    <label class="audit-log-field is-wide">
                        <span>ค้นหา</span>
                        <span class="audit-log-search-field">
                            <i class="bi bi-search"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาตาราง, การกระทำ, ผู้ดำเนินการ หรือหมายเหตุ">
                        </span>
                    </label>

                    <label class="audit-log-field">
                        <span>ตาราง</span>
                        <select name="table_name">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($tableOptions as $option): ?>
                                <option value="<?= htmlspecialchars((string) $option) ?>" <?= $tableFilter === (string) $option ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tableConfigs[$option]['label'] ?? (string) $option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="audit-log-field">
                        <span>การกระทำ</span>
                        <select name="action_type">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($actionOptions as $option): ?>
                                <option value="<?= htmlspecialchars((string) $option) ?>" <?= $actionFilter === (string) $option ? 'selected' : '' ?>><?= htmlspecialchars((string) $option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="audit-log-field">
                        <span>ผู้ดำเนินการ</span>
                        <select name="actor">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($actorOptions as $option): ?>
                                <option value="<?= htmlspecialchars((string) $option) ?>" <?= $actorFilter === (string) $option ? 'selected' : '' ?>><?= htmlspecialchars((string) $option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="audit-log-field">
                        <span>ช่วงวันที่</span>
                        <input type="date" name="log_date" value="<?= htmlspecialchars($dateFilter) ?>">
                    </label>

                    <label class="audit-log-field is-wide">
                        <span>แสดง</span>
                        <select name="per_page">
                            <?php foreach ([10, 20, 50, 100] as $size): ?>
                                <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> รายการต่อหน้า</option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="audit-log-filter-actions is-wide">
                        <a href="db_change_logs.php" class="audit-log-button is-ghost">
                            <i class="bi bi-arrow-clockwise"></i>
                            ล้างตัวกรอง
                        </a>
                        <button type="submit" class="audit-log-button is-primary">
                            <i class="bi bi-search"></i>
                            ค้นหา
                        </button>
                    </div>
                </form>

            </aside>

            <section class="audit-log-table-card">
                <div id="dbAuditResults"><?php require __DIR__ . '/../partials/admin/db_change_log_results.php'; ?></div>
            </section>
        </section>

        <section class="audit-log-bottom-strip" aria-label="สรุปบันทึกการเปลี่ยนแปลง">
            <div class="audit-log-bottom-item is-title">สรุปบันทึกข้อมูล</div>
            <div class="audit-log-bottom-item">
                <span>รายการทั้งหมด</span>
                <strong><?= number_format($allRowsTotal) ?></strong>
                <em>รายการในระบบ</em>
            </div>
            <div class="audit-log-bottom-item">
                <span>วันนี้</span>
                <strong><?= number_format($todayRowsTotal) ?></strong>
                <em>รายการ</em>
            </div>
            <div class="audit-log-bottom-item">
                <span>ผู้ดำเนินการ</span>
                <strong><?= number_format($uniqueActorTotal) ?></strong>
                <em>ผู้ดำเนินการ</em>
            </div>
            <div class="audit-log-bottom-item">
                <span>สถานะระบบ</span>
                <strong><?= htmlspecialchars($systemStatus) ?></strong>
                <em>ระบบทำงานปกติ</em>
            </div>
            <div class="audit-log-bottom-progress">
                <div>
                    <span>ความสมบูรณ์ audit</span>
                    <strong><?= number_format($auditPercent) ?>%</strong>
                </div>
                <div class="audit-log-progress-track"><span style="width: <?= min(100, max(0, $auditPercent)) ?>%"></span></div>
                <em>ตรวจสอบข้อมูลครบถ้วน</em>
            </div>
        </section>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script src="../assets/js/table-filters.js"></script>
<script src="../assets/js/audit-log-view-toggle.js"></script>
<script>
TableFilters.init({
    formId: 'dbAuditFilterForm',
    containerId: 'dbAuditResults',
    endpoint: '../ajax/admin/audit_rows.php',
    pushBase: 'db_change_logs.php',
    onRefresh: function (context) {
        if (window.AuditLogViewToggle) {
            window.AuditLogViewToggle.init(context.container);
        }
    }
});
</script>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
