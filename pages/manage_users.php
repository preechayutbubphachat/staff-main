<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

app_require_permission('can_manage_user_permissions');
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

$filters = app_build_manageable_user_filters($_GET);
$departments = app_fetch_departments($conn);
$roleLabels = app_role_labels();
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, 20);
$totalRows = app_count_manageable_users($conn, $filters);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = app_get_manageable_users($conn, $filters, $perPage, ($page - 1) * $perPage);
$allRows = app_get_manageable_users_all($conn, $filters);

function app_manage_users_query(array $filters, array $extra = []): string
{
    return app_build_table_query([
        'fullname' => $filters['fullname'],
        'username' => $filters['username'],
        'position_name' => $filters['position_name'],
        'department' => $filters['department'],
        'role' => $filters['role'],
        'account_status' => $filters['account_status'],
        'per_page' => $extra['per_page'] ?? ($_GET['per_page'] ?? 20),
    ], $extra);
}

$totalUsers = (int) $totalRows;
$activeUsers = 0;
$inactiveUsers = 0;
$adminUsers = 0;
$roleKeysInResult = [];
foreach ($allRows as $row) {
    $isActive = !array_key_exists('is_active', $row) || (int) $row['is_active'] === 1;
    $activeUsers += $isActive ? 1 : 0;
    $inactiveUsers += $isActive ? 0 : 1;
    $roleKey = (string) ($row['role'] ?? 'staff');
    $roleKeysInResult[$roleKey] = true;
    if ($roleKey === 'admin') {
        $adminUsers++;
    }
}
$roleCount = count($roleKeysInResult) > 0 ? count($roleKeysInResult) : count($roleLabels);
$pendingOrSuspended = $inactiveUsers;
$activePercent = $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0;
$pendingPercent = $totalUsers > 0 ? ($pendingOrSuspended / $totalUsers) * 100 : 0;
$readinessPercent = $activePercent;
$periodLabel = app_format_thai_month_year(date('Y-m'));
$latestLabel = app_format_thai_datetime(date('Y-m-d H:i:s'), true);
$departmentScope = 'ทุกแผนกในระบบ';
if ($filters['department'] !== '') {
    foreach ($departments as $department) {
        if ((string) $department['id'] === (string) $filters['department']) {
            $departmentScope = (string) $department['department_name'];
            break;
        }
    }
}
$highestPrivilegeLabel = $adminUsers > 0 ? app_role_label('admin') : ($roleLabels[array_key_first($roleLabels)] ?? '-');
$printQuery = app_manage_users_query($filters, ['type' => 'manage_users']);
$pdfQuery = app_manage_users_query($filters, ['type' => 'manage_users', 'download' => 'pdf']);
$csvQuery = app_manage_users_query($filters, ['type' => 'manage_users']);
$createUserHref = app_can('can_manage_database') ? 'db_row_create.php?table=users' : 'manage_users.php';
$permissionHistoryHref = app_can('can_manage_database') ? 'db_change_logs.php' : 'manage_users.php';
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell admin-users-page-shell">
<?php render_dashboard_sidebar('manage_users.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main admin-users-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">จัดการผู้ใช้งาน</h1>
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

    <div class="admin-users-dashboard-frame panel">
        <section class="admin-users-hero">
            <div class="admin-users-hero-copy">
                <span class="admin-users-hero-pill"><i class="bi bi-person-gear"></i> Admin Workspace</span>
                <h2>จัดการผู้ใช้งาน</h2>
                <p>จัดการบัญชีผู้ใช้ บทบาท สิทธิ์การเข้าถึง และสถานะการใช้งานในระบบ เพื่อให้การบริหารจัดการปลอดภัยและมีประสิทธิภาพสูงสุด</p>
                <div class="admin-users-hero-chips">
                    <span><i class="bi bi-people"></i> ผู้ใช้ทั้งหมด <?= number_format($totalUsers) ?> คน</span>
                    <span><i class="bi bi-shield-check"></i> บทบาทในระบบ <?= number_format($roleCount) ?></span>
                    <span><i class="bi bi-clock-history"></i> อัปเดตล่าสุด <?= htmlspecialchars($latestLabel) ?></span>
                </div>
            </div>

            <div class="admin-users-hero-metrics" aria-label="สรุปผู้ใช้งาน">
                <div class="admin-users-hero-metric">
                    <span class="admin-users-hero-icon is-blue"><i class="bi bi-people-fill"></i></span>
                    <strong><?= number_format($totalUsers) ?></strong>
                    <span>จำนวนผู้ใช้ทั้งหมด</span>
                </div>
                <div class="admin-users-hero-metric">
                    <span class="admin-users-hero-icon is-green"><i class="bi bi-shield-fill-check"></i></span>
                    <strong><?= number_format($roleCount) ?></strong>
                    <span>บทบาทในระบบ</span>
                </div>
                <div class="admin-users-hero-metric">
                    <span class="admin-users-hero-icon is-teal"><i class="bi bi-person-check-fill"></i></span>
                    <strong><?= number_format($activeUsers) ?></strong>
                    <span>ใช้งานอยู่</span>
                </div>
                <div class="admin-users-hero-metric">
                    <span class="admin-users-hero-icon is-amber"><i class="bi bi-clock-history"></i></span>
                    <strong><?= number_format($pendingOrSuspended) ?></strong>
                    <span>รออนุมัติ/ระงับ</span>
                </div>
            </div>

            <div class="admin-users-hero-actions">
                <a href="<?= htmlspecialchars($createUserHref) ?>" class="admin-users-hero-action is-primary">
                    <i class="bi bi-person-plus"></i>
                    <span>เพิ่มผู้ใช้งาน</span>
                </a>
                <a href="<?= htmlspecialchars($permissionHistoryHref) ?>" class="admin-users-hero-action is-secondary">
                    <i class="bi bi-clock-history"></i>
                    <span>ดูประวัติสิทธิ์</span>
                </a>
            </div>
        </section>

        <section class="admin-users-kpi-grid" aria-label="สรุปภาพรวมผู้ใช้งาน">
            <article class="admin-users-kpi-card">
                <span class="admin-users-kpi-icon is-blue"><i class="bi bi-grid-3x3-gap"></i></span>
                <div>
                    <small>ขอบเขตผู้ใช้งาน</small>
                    <strong><?= htmlspecialchars($departmentScope) ?></strong>
                    <span>ครอบคลุมทั้งหมด</span>
                </div>
            </article>
            <article class="admin-users-kpi-card">
                <span class="admin-users-kpi-icon is-green"><i class="bi bi-calendar3"></i></span>
                <div>
                    <small>เดือนปัจจุบัน</small>
                    <strong><?= htmlspecialchars($periodLabel) ?></strong>
                    <span>ช่วงเวลาที่เลือก</span>
                </div>
            </article>
            <article class="admin-users-kpi-card">
                <span class="admin-users-kpi-icon is-teal"><i class="bi bi-person-check"></i></span>
                <div>
                    <small>ผู้ใช้งานใช้งานอยู่</small>
                    <strong><?= number_format($activeUsers) ?> บัญชี</strong>
                    <span>คิดเป็น <?= number_format($activePercent, 2) ?>%</span>
                </div>
            </article>
            <article class="admin-users-kpi-card">
                <span class="admin-users-kpi-icon is-purple"><i class="bi bi-shield-lock"></i></span>
                <div>
                    <small>สิทธิ์สูงสุด</small>
                    <strong><?= htmlspecialchars($highestPrivilegeLabel) ?></strong>
                    <span>สิทธิ์เต็มรูปแบบ</span>
                </div>
            </article>
        </section>

        <section class="admin-users-content-grid">
            <aside class="admin-users-filter-card">
                <div class="admin-users-card-heading">
                    <span>User Filters</span>
                    <h2>ตัวกรองรายชื่อและเครื่องมือ</h2>
                    <p>ค้นหารายชื่อผู้ใช้</p>
                </div>

                <form method="get" id="manageUsersFilterForm" class="admin-users-filter-form" data-page-state-key="manage_users">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">

                    <label class="admin-users-field is-wide">
                        <span>ค้นหา</span>
                        <span class="admin-users-input-wrap">
                            <i class="bi bi-search"></i>
                            <input type="search" name="fullname" value="<?= htmlspecialchars($filters['fullname']) ?>" placeholder="พิมพ์ชื่อ, ตำแหน่ง, แผนก หรือ Username">
                        </span>
                    </label>

                    <label class="admin-users-field">
                        <span>Username</span>
                        <input type="text" name="username" value="<?= htmlspecialchars($filters['username']) ?>" placeholder="ระบุ Username">
                    </label>

                    <label class="admin-users-field">
                        <span>ตำแหน่ง</span>
                        <input type="text" name="position_name" value="<?= htmlspecialchars($filters['position_name']) ?>" placeholder="เลือกตำแหน่ง">
                    </label>

                    <label class="admin-users-field">
                        <span>แผนก</span>
                        <select name="department">
                            <option value="">เลือกแผนก</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= (string) $filters['department'] === (string) $department['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="admin-users-field">
                        <span>บทบาท</span>
                        <select name="role">
                            <option value="">เลือกบทบาท</option>
                            <?php foreach ($roleLabels as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="admin-users-field is-wide">
                        <span>สถานะบัญชี</span>
                        <select name="account_status">
                            <option value="">เลือกสถานะบัญชี</option>
                            <option value="active" <?= $filters['account_status'] === 'active' ? 'selected' : '' ?>>ใช้งานอยู่</option>
                            <option value="inactive" <?= $filters['account_status'] === 'inactive' ? 'selected' : '' ?>>ระงับ</option>
                        </select>
                    </label>

                    <div class="admin-users-filter-actions is-wide">
                        <a href="manage_users.php" class="admin-users-button is-ghost"><i class="bi bi-arrow-clockwise"></i> ล้างตัวกรอง</a>
                        <button type="submit" class="admin-users-button is-primary"><i class="bi bi-search"></i> ค้นหา</button>
                    </div>
                </form>

                <div class="admin-users-toolbox">
                    <h3>จัดการรายงาน</h3>
                    <div class="admin-users-tool-grid">
                        <a href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener" data-export-base="report_print.php" data-export-type="manage_users" class="admin-users-tool-button">
                            <i class="bi bi-printer"></i> พิมพ์รายงาน
                        </a>
                        <a href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener" data-export-base="report_print.php" data-export-type="manage_users" data-export-download="pdf" class="admin-users-tool-button">
                            <i class="bi bi-filetype-pdf"></i> ส่งออก PDF
                        </a>
                        <a href="export_report.php?<?= htmlspecialchars($csvQuery) ?>" data-export-base="export_report.php" data-export-type="manage_users" class="admin-users-tool-button">
                            <i class="bi bi-filetype-csv"></i> ส่งออก CSV
                        </a>
                    </div>
                </div>
            </aside>

            <div id="manageUsersResults" class="min-w-0">
                <?php require __DIR__ . '/../partials/admin/manage_users_results.php'; ?>
            </div>
        </section>

        <section class="admin-users-bottom-strip" aria-label="สรุปข้อมูลผู้ใช้งาน">
            <div class="admin-users-bottom-title">สรุปข้อมูลผู้ใช้งาน</div>
            <div class="admin-users-bottom-item">
                <span>จำนวนผู้ใช้ทั้งหมด</span>
                <strong><?= number_format($totalUsers) ?> <small>คน</small></strong>
                <em>จากทั้งหมด <?= number_format($totalUsers) ?> คน</em>
            </div>
            <div class="admin-users-bottom-item">
                <span>ใช้งานอยู่</span>
                <strong><?= number_format($activeUsers) ?> <small>บัญชี</small></strong>
                <em>คิดเป็น <?= number_format($activePercent, 2) ?>%</em>
            </div>
            <div class="admin-users-bottom-item">
                <span>บทบาททั้งหมด</span>
                <strong><?= number_format($roleCount) ?> <small>บทบาท</small></strong>
                <em>ในระบบ</em>
            </div>
            <div class="admin-users-bottom-item">
                <span>รออนุมัติ/ระงับ</span>
                <strong><?= number_format($pendingOrSuspended) ?> <small>บัญชี</small></strong>
                <em>คิดเป็น <?= number_format($pendingPercent, 2) ?>%</em>
            </div>
            <div class="admin-users-progress-block">
                <div class="admin-users-progress-head">
                    <span>ความพร้อมของบัญชี</span>
                    <strong><?= number_format($readinessPercent, 0) ?>%</strong>
                </div>
                <div class="admin-users-progress-track">
                    <span style="width: <?= min(100, max(0, $readinessPercent)) ?>%"></span>
                </div>
                <em>ใช้งานได้ <?= number_format($activeUsers) ?> / <?= number_format($totalUsers) ?> บัญชี</em>
            </div>
        </section>
    </div>
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
    pushBase: 'manage_users.php',
    scopeSelector: '.admin-users-dashboard-frame'
});

document.addEventListener('change', function (event) {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement) || target.dataset.usersPerPage !== 'true') {
        return;
    }

    const form = document.getElementById('manageUsersFilterForm');
    if (!form) {
        return;
    }

    const perPageInput = form.querySelector('input[name="per_page"]');
    const pageInput = form.querySelector('input[name="p"]');
    if (perPageInput) {
        perPageInput.value = target.value;
    }
    if (pageInput) {
        pageInput.value = '1';
    }
    form.requestSubmit();
});
</script>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
