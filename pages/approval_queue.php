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

function approval_scope_sql(array $departmentIds, string $alias = 't'): array
{
    $departmentIds = array_values(array_unique(array_map('intval', $departmentIds)));
    if (!$departmentIds) {
        return ['1 = 0', []];
    }

    $placeholders = implode(', ', array_fill(0, count($departmentIds), '?'));

    return [
        "{$alias}.department_id IN ({$placeholders})",
        $departmentIds,
    ];
}

function approval_scalar(PDO $conn, string $sql, array $params = [], $fallback = 0)
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();

    return $value === false ? $fallback : $value;
}

$printQuery = app_approval_query($filters, ['type' => 'approval']);
$pdfQuery = app_approval_query($filters, ['type' => 'approval', 'download' => 'pdf']);
$csvQuery = app_approval_query($filters, ['type' => 'approval']);
$historyQuery = app_approval_query($filters, ['status' => 'all', 'p' => 1]);
$scopeIds = $filters['scope']['ids'] ?? [];
[$scopeWhereSql, $scopeParams] = approval_scope_sql($scopeIds, 't');
$today = date('Y-m-d');
$todayLabel = app_format_thai_date($today, true);
$nowLabel = date('j/n/Y H:i');
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');

$pendingOverall = (int) approval_scalar(
    $conn,
    "SELECT COUNT(*) FROM time_logs t WHERE {$scopeWhereSql} AND " . app_time_log_pending_condition('t'),
    $scopeParams
);
$approvedTodayCount = (int) approval_scalar(
    $conn,
    "SELECT COUNT(*) FROM time_logs t WHERE {$scopeWhereSql} AND DATE(t.checked_at) = ?",
    array_merge($scopeParams, [$today])
);
$returnedTodayCount = (int) approval_scalar(
    $conn,
    "SELECT COUNT(*) FROM time_logs t WHERE {$scopeWhereSql} AND t.work_date = ? AND t.checked_by IS NOT NULL AND t.checked_at IS NULL",
    array_merge($scopeParams, [$today])
);
$todayTotalCount = (int) approval_scalar(
    $conn,
    "SELECT COUNT(*) FROM time_logs t WHERE {$scopeWhereSql} AND t.work_date = ?",
    array_merge($scopeParams, [$today])
);
$latestReviewedAtRaw = (string) approval_scalar(
    $conn,
    "SELECT MAX(t.checked_at) FROM time_logs t WHERE {$scopeWhereSql} AND t.checked_at IS NOT NULL",
    $scopeParams,
    ''
);
$latestReviewedAt = $latestReviewedAtRaw !== '' ? strtotime($latestReviewedAtRaw) : false;
$latestReviewedTimeLabel = $latestReviewedAt ? date('H:i', $latestReviewedAt) . ' น.' : date('H:i') . ' น.';
$latestReviewedDateLabel = $latestReviewedAt ? app_format_thai_date(date('Y-m-d', $latestReviewedAt)) : $todayLabel;

$monthReviewedCount = (int) approval_scalar(
    $conn,
    "SELECT COUNT(*) FROM time_logs t WHERE {$scopeWhereSql} AND t.checked_at IS NOT NULL AND t.work_date BETWEEN ? AND ?",
    array_merge($scopeParams, [$currentMonthStart, $currentMonthEnd])
);
$monthTotalCount = (int) approval_scalar(
    $conn,
    "SELECT COUNT(*) FROM time_logs t WHERE {$scopeWhereSql} AND t.work_date BETWEEN ? AND ?",
    array_merge($scopeParams, [$currentMonthStart, $currentMonthEnd])
);
$monthProgressPercent = $monthTotalCount > 0 ? min(100, round(($monthReviewedCount / $monthTotalCount) * 100, 1)) : 0;

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$checkerId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['department_name' => '-'];
$role = app_current_role();
$roleLabel = app_role_label($role);
$hasProfileImageColumn = app_column_exists($conn, 'users', 'profile_image_path');
$profileImageSrc = $hasProfileImageColumn ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($userMeta);
if ($displayName !== '-') {
    $checkerName = $displayName;
}

$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
$notificationCount = app_get_unread_notification_count($conn, $checkerId);
$scopeBadgeLabel = count($scopeIds) > 0 && count($scopeIds) !== count($departments) ? $scopeLabel : 'ทุกแผนก';
$reviewStatCards = [
    [
        'icon' => 'bi-clipboard-check',
        'icon_class' => 'time-kpi-icon is-blue',
        'label' => 'รอตรวจสอบ',
        'value' => number_format($pendingOverall),
        'unit' => 'รายการ',
        'subtitle' => 'รายการคงค้างในคิวตรวจสอบ',
    ],
    [
        'icon' => 'bi-patch-check',
        'icon_class' => 'time-kpi-icon',
        'label' => 'อนุมัติแล้ววันนี้',
        'value' => number_format($approvedTodayCount),
        'unit' => 'รายการ',
        'subtitle' => $todayTotalCount > 0 ? 'คิดเป็น ' . round(($approvedTodayCount / max(1, $todayTotalCount)) * 100) . '%' : 'ยังไม่มีรายการอนุมัติวันนี้',
    ],
    [
        'icon' => 'bi-exclamation-triangle',
        'icon_class' => 'time-kpi-icon is-amber',
        'label' => 'ตีกลับแก้ไข',
        'value' => number_format($returnedTodayCount),
        'unit' => 'รายการ',
        'subtitle' => 'รายการที่ยังรอการแก้ไข',
    ],
    [
        'icon' => 'bi-clock-history',
        'icon_class' => 'time-kpi-icon is-lilac',
        'label' => 'รายการล่าสุด',
        'value' => $latestReviewedTimeLabel,
        'unit' => '',
        'subtitle' => 'ล่าสุด ' . $latestReviewedDateLabel,
    ],
];
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
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell approval-page-shell">
<?php render_dashboard_sidebar('approval_queue.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main approval-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Time Workspace</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">ตรวจสอบเวร</h1>
        </div>

        <label class="dash-search-control">
            <i class="bi bi-search"></i>
            <input type="search" class="w-full bg-transparent outline-none placeholder:text-hospital-muted/70" placeholder="ค้นหาชื่อ, ตำแหน่ง, แผนก หรือสถานะ">
        </label>

        <a href="notifications.php" class="dash-icon-button" aria-label="การแจ้งเตือน">
            <i class="bi bi-bell"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="absolute -right-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1 text-[0.68rem] font-bold text-white"><?= (int) min($notificationCount, 99) ?></span>
            <?php endif; ?>
        </a>

        <a href="profile.php" class="hidden cursor-pointer items-center gap-3 rounded-2xl bg-white px-3 py-2 text-hospital-ink no-underline shadow-soft transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-glass focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hospital-teal focus-visible:ring-offset-2 active:translate-y-0 sm:flex">
            <span class="grid h-9 w-9 overflow-hidden rounded-xl bg-hospital-mist text-hospital-teal">
                <?php if ($profileImageSrc): ?>
                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="รูปโปรไฟล์" class="h-full w-full object-cover">
                <?php else: ?>
                    <span class="grid h-full w-full place-items-center"><i class="bi bi-person-fill"></i></span>
                <?php endif; ?>
            </span>
            <span class="grid leading-tight">
                <span class="max-w-[150px] truncate text-sm font-bold"><?= htmlspecialchars($displayName) ?></span>
                <span class="text-xs font-semibold text-hospital-muted"><?= htmlspecialchars($roleLabel) ?></span>
            </span>
        </a>
    </header>

    <div class="approval-dashboard-frame">
        <section class="approval-hero-stage">
            <article class="dash-card-strong approval-hero-card">
                <div class="approval-hero-grid">
                    <div class="approval-hero-copy">
                        <span class="dash-hero-badge">
                            <i class="bi bi-eye"></i>
                            Review Workspace
                        </span>
                        <h2 class="dash-hero-title approval-hero-title">ตรวจสอบรายการลงเวลาเวร</h2>
                        <p class="dash-hero-copy approval-hero-copy">ตรวจสอบและอนุมัติรายการลงเวลาเวรของบุคลากร เพื่อให้การบันทึกเวลาทำงานถูกต้องครบถ้วนตามนโยบาย</p>
                        <div class="dash-hero-chip-row">
                            <span class="dash-hero-chip"><i class="bi bi-person-badge"></i>บทบาท: <?= htmlspecialchars($roleLabel) ?></span>
                            <span class="dash-hero-chip"><i class="bi bi-building"></i>แผนก: <?= htmlspecialchars($scopeBadgeLabel) ?></span>
                            <span class="dash-hero-chip"><i class="bi bi-clock-history"></i>ข้อมูลล่าสุด: <?= htmlspecialchars($nowLabel) ?></span>
                        </div>
                    </div>

                    <div class="approval-hero-divider" aria-hidden="true"></div>

                    <div class="approval-hero-metrics" aria-label="สรุปสถานะการตรวจสอบ">
                        <p class="approval-hero-metrics-title">สรุปสถานะการตรวจสอบ</p>
                        <div class="approval-hero-metric-grid">
                            <div class="approval-hero-metric">
                                <span class="approval-hero-metric-icon is-blue"><i class="bi bi-hourglass-split"></i></span>
                                <div>
                                    <strong><?= number_format($pendingOverall) ?></strong>
                                    <span>รอตรวจสอบ</span>
                                </div>
                            </div>
                            <div class="approval-hero-metric">
                                <span class="approval-hero-metric-icon is-green"><i class="bi bi-check2-circle"></i></span>
                                <div>
                                    <strong><?= number_format($approvedTodayCount) ?></strong>
                                    <span>อนุมัติแล้ววันนี้</span>
                                </div>
                            </div>
                            <div class="approval-hero-metric">
                                <span class="approval-hero-metric-icon is-amber"><i class="bi bi-exclamation-triangle"></i></span>
                                <div>
                                    <strong><?= number_format($returnedTodayCount) ?></strong>
                                    <span>ตีกลับแก้ไข</span>
                                </div>
                            </div>
                            <div class="approval-hero-metric">
                                <span class="approval-hero-metric-icon is-violet"><i class="bi bi-list-task"></i></span>
                                <div>
                                    <strong><?= number_format($todayTotalCount) ?></strong>
                                    <span>ทั้งหมดวันนี้</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="approval-hero-actions">
                        <a href="#approval-review-panel" class="dash-btn dash-btn-secondary approval-hero-cta-primary">
                            <i class="bi bi-clipboard-check"></i>เปิดรายการตรวจ
                        </a>
                        <a href="approval_queue.php?<?= htmlspecialchars($historyQuery) ?>" class="dash-btn dash-btn-on-dark approval-hero-cta-secondary">
                            <i class="bi bi-clock-history"></i>ดูประวัติการตรวจ
                        </a>
                    </div>
                </div>
            </article>
        </section>

        <section class="approval-kpi-row" aria-label="สรุปตัวชี้วัด">
            <?php foreach ($reviewStatCards as $card): ?>
                <article class="dash-kpi-card approval-kpi-card">
                    <span class="dash-icon-badge <?= htmlspecialchars($card['icon_class']) ?>"><i class="bi <?= htmlspecialchars($card['icon']) ?>"></i></span>
                    <div class="time-kpi-copy">
                        <p class="time-kpi-label"><?= htmlspecialchars($card['label']) ?></p>
                        <strong class="time-kpi-value approval-kpi-value">
                            <?= htmlspecialchars($card['value']) ?>
                            <?php if ($card['unit'] !== ''): ?><span><?= htmlspecialchars($card['unit']) ?></span><?php endif; ?>
                        </strong>
                        <p class="time-kpi-subtitle"><?= htmlspecialchars($card['subtitle']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <div id="approvalQueueMessage">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
        </div>

        <section class="approval-workspace-grid">
            <article class="dash-card approval-filter-card">
                <div class="approval-card-head">
                    <div>
                        <p class="approval-section-eyebrow">Review filters</p>
                        <h2 class="approval-card-title">ตัวกรองและเครื่องมือ</h2>
                    </div>
                    <span class="time-chip-muted"><?= htmlspecialchars($scopeLabel) ?></span>
                </div>

                <form method="get" id="approvalFilterForm" class="approval-filter-form" data-page-state-key="approval_queue">
                    <input type="hidden" name="p" value="<?= (int) $page ?>">
                    <input type="hidden" name="view" value="table">

                    <div class="approval-filter-group">
                        <label class="approval-field-label" for="approvalSearchName">ค้นหา</label>
                        <label class="approval-search-field" for="approvalSearchName">
                            <i class="bi bi-search"></i>
                            <input id="approvalSearchName" type="search" name="name" class="form-control border-0 bg-transparent p-0 shadow-none" value="<?= htmlspecialchars($filters['name']) ?>" placeholder="ค้นหาชื่อ, ตำแหน่ง หรือแผนก">
                        </label>
                    </div>

                    <div class="approval-filter-grid">
                        <div class="approval-filter-field">
                            <label class="approval-field-label" for="approvalPosition">ตำแหน่ง</label>
                            <input id="approvalPosition" type="text" name="position_name" class="form-control" value="<?= htmlspecialchars($filters['position_name']) ?>" placeholder="ทั้งหมดตำแหน่ง">
                        </div>
                        <div class="approval-filter-field">
                            <label class="approval-field-label" for="approvalDepartment">แผนก</label>
                            <select id="approvalDepartment" name="department" class="form-select">
                                <option value="">ทั้งหมดแผนก</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= (string) $filters['department'] === (string) $department['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="approval-filter-field">
                            <label class="approval-field-label" for="approvalStatus">สถานะ</label>
                            <select id="approvalStatus" name="status" class="form-select">
                                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>รอตรวจสอบ</option>
                                <option value="checked" <?= $filters['status'] === 'checked' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                                <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                            </select>
                        </div>
                        <div class="approval-filter-field">
                            <label class="approval-field-label" for="approvalDateFrom">วันที่เริ่มต้น</label>
                            <input id="approvalDateFrom" type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div class="approval-filter-field">
                            <label class="approval-field-label" for="approvalDateTo">วันที่สิ้นสุด</label>
                            <input id="approvalDateTo" type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        <div class="approval-filter-field">
                            <label class="approval-field-label" for="approvalPerPage">แสดง</label>
                            <select id="approvalPerPage" name="per_page" class="form-select">
                                <?php foreach ([10, 20, 50, 100] as $size): ?>
                                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> รายการ</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="approval-filter-actions">
                        <a href="approval_queue.php" class="dash-btn dash-btn-ghost">ล้างตัวกรอง <i class="bi bi-arrow-counterclockwise"></i></a>
                        <button type="submit" class="dash-btn dash-btn-primary">
                            <i class="bi bi-search"></i>ค้นหา
                        </button>
                    </div>

                    <div class="approval-bulk-tools" id="bulkBar">
                        <div class="approval-bulk-head">
                            <div>
                                <p class="approval-field-label !mb-1">จัดการหลายรายการ</p>
                                <p class="approval-bulk-copy" id="selectedSummaryText">เลือกรายการ 0 รายการ</p>
                            </div>
                            <?php if ($checkerSignature === ''): ?>
                                <span class="status-chip warning">ยังไม่ได้ตั้งค่าลายเซ็นผู้ตรวจสอบ</span>
                            <?php endif; ?>
                        </div>

                        <div class="approval-bulk-action-grid">
                            <button type="button" class="dash-btn dash-btn-primary approval-tool-btn" id="openApproveModalBtn" <?= $checkerSignature === '' ? 'disabled data-signature-required="1"' : '' ?>>
                                <i class="bi bi-patch-check"></i>อนุมัติทั้งหมด
                            </button>
                            <button type="button" class="dash-btn dash-btn-ghost approval-tool-btn is-disabled" disabled title="ยังไม่เปิดใช้ workflow ตีกลับแบบกลุ่ม">
                                <i class="bi bi-arrow-counterclockwise"></i>ตีกลับทั้งหมด
                            </button>
                            <a class="dash-btn dash-btn-ghost approval-tool-btn" data-export-base="report_print.php" data-export-type="approval" data-export-download="pdf" href="report_print.php?<?= htmlspecialchars($pdfQuery) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-filetype-pdf"></i>ส่งออก PDF
                            </a>
                            <a class="dash-btn dash-btn-ghost approval-tool-btn" data-export-base="export_report.php" data-export-type="approval" href="export_report.php?<?= htmlspecialchars($csvQuery) ?>">
                                <i class="bi bi-filetype-csv"></i>ส่งออก CSV
                            </a>
                            <a class="dash-btn dash-btn-ghost approval-tool-btn" data-export-base="report_print.php" data-export-type="approval" href="report_print.php?<?= htmlspecialchars($printQuery) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-printer"></i>พิมพ์รายงาน
                            </a>
                            <button type="button" class="dash-btn dash-btn-ghost approval-tool-btn" id="clearSelectionBtn">
                                <i class="bi bi-x-circle"></i>ล้างการเลือก
                            </button>
                        </div>
                    </div>
                </form>
            </article>

            <form method="post" id="bulkApproveForm" class="approval-review-stage">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="bulk_approve">
                <div id="approvalResultsContainer"><?php require __DIR__ . '/../partials/approval/results_block.php'; ?></div>
            </form>
        </section>

        <section class="dash-card approval-bottom-strip" aria-label="สรุปข้อมูลวันนี้">
            <div class="approval-bottom-heading">
                <h3>สรุปข้อมูลวันนี้</h3>
            </div>
            <div class="approval-bottom-item">
                <span class="approval-bottom-label">ตรวจสอบแล้ววันนี้</span>
                <strong class="approval-bottom-value"><?= number_format($approvedTodayCount) ?> <span>รายการ</span></strong>
                <span class="approval-bottom-meta">จากทั้งหมด <?= number_format($todayTotalCount) ?> รายการ</span>
            </div>
            <div class="approval-bottom-item">
                <span class="approval-bottom-label">รอตรวจสอบ</span>
                <strong class="approval-bottom-value"><?= number_format($pendingOverall) ?> <span>รายการ</span></strong>
                <span class="approval-bottom-meta">รายการค้างในคิวปัจจุบัน</span>
            </div>
            <div class="approval-bottom-item">
                <span class="approval-bottom-label">ตีกลับแก้ไข</span>
                <strong class="approval-bottom-value"><?= number_format($returnedTodayCount) ?> <span>รายการ</span></strong>
                <span class="approval-bottom-meta">คิดเป็น <?= $todayTotalCount > 0 ? number_format(($returnedTodayCount / $todayTotalCount) * 100, 1) : '0.0' ?>%</span>
            </div>
            <div class="approval-bottom-item">
                <span class="approval-bottom-label">ล่าสุด</span>
                <strong class="approval-bottom-value"><?= htmlspecialchars($latestReviewedTimeLabel) ?></strong>
                <span class="approval-bottom-meta">ตรวจล่าสุด <?= htmlspecialchars($latestReviewedDateLabel) ?></span>
            </div>
            <div class="approval-bottom-progress">
                <div class="approval-bottom-progress-head">
                    <strong>ความคืบหน้าประจำเดือน</strong>
                    <span><?= number_format($monthProgressPercent, 1) ?>%</span>
                </div>
                <div class="time-progress-bar"><span style="width: <?= htmlspecialchars((string) $monthProgressPercent) ?>%"></span></div>
                <div class="approval-bottom-meta">ตรวจแล้ว <?= number_format($monthReviewedCount) ?> / <?= number_format($monthTotalCount) ?> รายการ</div>
            </div>
        </section>
    </div>
</main>

<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content time-modal-surface">
            <div class="modal-header border-0 px-4 pt-4">
                <div>
                    <p class="approval-section-eyebrow mb-1">Approval review</p>
                    <h5 class="modal-title font-prompt text-xl font-bold text-hospital-ink">ยืนยันการตรวจสอบรายการที่เลือก</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-2">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="time-modal-info-card">
                        <div class="small text-muted">จำนวนรายการที่เลือก</div>
                        <div class="mt-1 font-prompt text-2xl font-bold text-hospital-navy" id="modalSelectedCount">0</div>
                    </div>
                    <div class="time-modal-info-card">
                        <div class="small text-muted">จำนวนเจ้าหน้าที่ไม่ซ้ำ</div>
                        <div class="mt-1 font-prompt text-2xl font-bold text-hospital-navy" id="modalStaffCount">0</div>
                    </div>
                    <div class="time-modal-info-card">
                        <div class="small text-muted">จำนวนแผนกไม่ซ้ำ</div>
                        <div class="mt-1 font-prompt text-2xl font-bold text-hospital-navy" id="modalDepartmentCount">0</div>
                    </div>
                </div>
                <div class="approval-modal-shell mt-4">
                    <div class="table-responsive approval-modal-table-scroll">
                        <table class="table align-middle mb-0 approval-modal-table">
                            <thead>
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
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="dash-btn dash-btn-ghost" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="dash-btn dash-btn-primary" id="confirmApproveBtn">ยืนยันการตรวจสอบ</button>
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
ApprovalQueuePage.init({
    formId: 'bulkApproveForm',
    filterFormId: 'approvalFilterForm',
    resultsId: 'approvalResultsContainer',
    summaryId: 'approvalSummaryDynamic',
    messageId: 'approvalQueueMessage'
});

(function () {
    const openButton = document.querySelector('[data-dashboard-sidebar-open]');
    const closeButton = document.querySelector('[data-dashboard-sidebar-close]');
    const drawer = document.querySelector('[data-dashboard-sidebar-drawer]');
    const backdrop = document.querySelector('[data-dashboard-sidebar-backdrop]');

    if (!openButton || !closeButton || !drawer || !backdrop) {
        return;
    }

    const setOpen = function (isOpen) {
        drawer.classList.toggle('is-open', isOpen);
        backdrop.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen);
    };

    openButton.addEventListener('click', function () { setOpen(true); });
    closeButton.addEventListener('click', function () { setOpen(false); });
    backdrop.addEventListener('click', function () { setOpen(false); });
})();
</script>
</body>
</html>
