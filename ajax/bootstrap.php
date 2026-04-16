<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/time_entry_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

date_default_timezone_set('Asia/Bangkok');

function ajax_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        ajax_json([
            'success' => false,
            'message' => 'วิธีเรียกข้อมูลไม่ถูกต้อง',
        ], 405);
    }
}

function ajax_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ajax_html(string $html, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

function ajax_capture(callable $renderer): string
{
    ob_start();
    $renderer();
    return (string) ob_get_clean();
}

function ajax_verify_csrf_or_fail(string $formKey, ?string $token): void
{
    if (!app_verify_csrf_token($token, $formKey)) {
        ajax_json([
            'success' => false,
            'message' => 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
        ], 419);
    }
}
