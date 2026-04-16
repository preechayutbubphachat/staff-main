<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_approve_logs');

$reportData = app_fetch_time_log_report_data($conn, $_GET, 'pending');
$filters = $reportData['filters'];
$summary = $reportData['summary'];
$view = $_GET['view'] ?? 'table';
$view = in_array($view, ['cards', 'table'], true) ? $view : 'table';
$page = app_parse_table_page($_GET);
$perPage = app_parse_table_page_size($_GET, $view === 'table' ? 20 : 12);
$totalRows = (int) ($summary['total_rows'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$rows = array_slice($reportData['rows'], ($page - 1) * $perPage, $perPage);
$checkerId = (int) ($_SESSION['id'] ?? 0);
$checkerSignatureStmt = $conn->prepare('SELECT signature_path FROM users WHERE id = ?');
$checkerSignatureStmt->execute([$checkerId]);
$checkerSignature = (string) ($checkerSignatureStmt->fetchColumn() ?: '');

$html = ajax_capture(function () use ($filters, $summary, $view, $page, $perPage, $totalRows, $totalPages, $rows, $checkerSignature): void {
    require __DIR__ . '/../../partials/approval/results_block.php';
});

ajax_html($html);
