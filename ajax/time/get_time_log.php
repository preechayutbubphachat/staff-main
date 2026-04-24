<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_login();

$userId = (int) ($_SESSION['id'] ?? 0);
$timeLogId = max(0, (int) ($_GET['id'] ?? 0));
$editLog = null;
if ($timeLogId > 0) {
    $editStmt = $conn->prepare("SELECT * FROM time_logs WHERE id = ? AND user_id = ? LIMIT 1");
    $editStmt->execute([$timeLogId, $userId]);
    $editLog = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$editLog) {
    ajax_html('<div class="alert alert-warning rounded-4 m-3">ไม่พบรายการลงเวลาเวรที่ต้องการ</div>', 404);
}

$canPrivilegedLockedEdit = app_can('can_edit_locked_time_logs');
$canEditModal = !app_time_log_is_locked($editLog) || $canPrivilegedLockedEdit;
$canDeleteModal = $canEditModal;
$canViewDepartmentReports = app_can('can_view_department_reports');

$userStmt = $conn->prepare("
    SELECT u.department_id, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$userId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['department_id' => 1, 'department_name' => '-'];

$departments = $canViewDepartmentReports
    ? $conn->query("SELECT * FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC)
    : [[
        'id' => $userMeta['department_id'],
        'department_name' => $userMeta['department_name'],
    ]];

$hourOptions = array_map(static fn($hour) => sprintf('%02d', $hour), range(0, 23));
$minuteOptions = array_map(static fn($minute) => sprintf('%02d', $minute), range(0, 59));
$selectedEditDepartmentId = (int) ($editLog['department_id'] ?? 0);
$editCsrfToken = app_csrf_token('time_page_edit');
$deleteCsrfToken = app_csrf_token('time_page_delete');
$page = max(1, (int) ($_GET['p'] ?? 1));
$searchDate = trim((string) ($_GET['date'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$historyStatus = trim((string) ($_GET['status'] ?? 'all'));
$historyQuery = trim((string) ($_GET['query'] ?? ''));
$modalErrorMessage = '';
$modalErrorType = 'danger';

$html = ajax_capture(function () use (
    $editLog,
    $selectedEditDepartmentId,
    $canViewDepartmentReports,
    $departments,
    $hourOptions,
    $minuteOptions,
    $editCsrfToken,
    $deleteCsrfToken,
    $page,
    $searchDate,
    $dateFrom,
    $dateTo,
    $historyStatus,
    $historyQuery,
    $modalErrorMessage,
    $modalErrorType,
    $canEditModal,
    $canDeleteModal
): void {
    require __DIR__ . '/../../partials/time/edit_modal_body.php';
});

ajax_html($html);
