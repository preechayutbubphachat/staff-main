<?php
/**
 * Department report — per-staff detail endpoint.
 *
 * Returns a monthly summary for one staff member: total shifts,
 * total hours, approved count, pending count, plus profile image URL.
 *
 * Permission: requires can_view_department_reports (same gate as the page).
 * Scope:      the requested user must belong to a department the caller
 *             can access (via app_get_accessible_departments()).
 *
 * GET params:
 *   user_id  (int, required)
 *   year     (int, required) – CE year, e.g. 2026
 *   month    (int, required) – month number 1–12
 *
 * Response: JSON { success, record }
 */
require_once __DIR__ . '/../bootstrap.php';

app_require_login();
ajax_require_method('GET');

// ── Permission gate ───────────────────────────────────────────────────────────
if (!app_can('can_view_department_reports')) {
    ajax_json(['success' => false, 'message' => 'ไม่มีสิทธิ์ดูรายงานแผนก'], 403);
}

// ── Input validation ──────────────────────────────────────────────────────────
$userId  = (int) ($_GET['user_id'] ?? 0);
$yearCe  = (int) ($_GET['year']    ?? 0);
$month   = (int) ($_GET['month']   ?? 0);

if ($userId <= 0 || $yearCe < 2000 || $yearCe > 2100 || $month < 1 || $month > 12) {
    ajax_json(['success' => false, 'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง'], 400);
}

// ── Department scope check ────────────────────────────────────────────────────
// Fetch the user's department_id, then verify it is in the caller's scope.
$userStmt = $conn->prepare("
    SELECT u.id, u.fullname, COALESCE(u.position_name, '') AS position_name,
           u.profile_image_path, u.department_id, d.department_name
    FROM   users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE  u.id = ?
    LIMIT 1
");
$userStmt->execute([$userId]);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    ajax_json(['success' => false, 'message' => 'ไม่พบข้อมูลเจ้าหน้าที่'], 404);
}

$scope = app_get_accessible_departments($conn);
if (!in_array((int) $userRow['department_id'], $scope['ids'], true)) {
    ajax_json(['success' => false, 'message' => 'ไม่มีสิทธิ์ดูข้อมูลเจ้าหน้าที่นี้'], 403);
}

// ── Monthly summary query ─────────────────────────────────────────────────────
$summaryStmt = $conn->prepare("
    SELECT
        COUNT(t.id)                                                     AS total_logs,
        COALESCE(SUM(t.work_hours), 0)                                  AS total_hours,
        SUM(CASE WHEN t.checked_at IS NOT NULL THEN 1 ELSE 0 END)       AS approved_logs,
        SUM(CASE WHEN t.checked_at IS     NULL THEN 1 ELSE 0 END)       AS pending_logs,
        MIN(t.work_date)                                                AS first_date,
        MAX(t.work_date)                                                AS last_date
    FROM time_logs t
    WHERE t.user_id = ?
      AND YEAR(t.work_date)  = ?
      AND MONTH(t.work_date) = ?
");
$summaryStmt->execute([$userId, $yearCe, $month]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalLogs    = (int)   ($summary['total_logs']    ?? 0);
$totalHours   = (float) ($summary['total_hours']   ?? 0);
$approvedLogs = (int)   ($summary['approved_logs'] ?? 0);
$pendingLogs  = (int)   ($summary['pending_logs']  ?? 0);

// ── Period label ──────────────────────────────────────────────────────────────
$thaiMonths = app_thai_month_names();
$periodLabel = ($thaiMonths[$month] ?? sprintf('เดือน %02d', $month)) . ' ' . ($yearCe + 543);

// ── Profile image ─────────────────────────────────────────────────────────────
$profileImageUrl = app_resolve_user_image_url($userRow['profile_image_path'] ?? '');

// ── Response ──────────────────────────────────────────────────────────────────
ajax_json([
    'success' => true,
    'record'  => [
        'user_id'           => (int)    $userRow['id'],
        'fullname'          => (string) ($userRow['fullname']        ?: '-'),
        'position_name'     => (string) ($userRow['position_name']   ?: '-'),
        'department_name'   => (string) ($userRow['department_name'] ?: '-'),
        'profile_image_url' => $profileImageUrl,
        'period_label'      => $periodLabel,
        'year_ce'           => $yearCe,
        'month'             => $month,
        'total_logs'        => $totalLogs,
        'total_hours'       => number_format($totalHours, 2),
        'total_hours_raw'   => $totalHours,
        'approved_logs'     => $approvedLogs,
        'pending_logs'      => $pendingLogs,
        'first_date'        => (string) ($summary['first_date'] ?? ''),
        'last_date'         => (string) ($summary['last_date']  ?? ''),
    ],
]);
