<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

app_require_permission('can_view_department_reports');

$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: department_reports.php' . ($query !== '' ? '?' . $query : ''));
exit;
