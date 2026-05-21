<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../../config/db.php';

$basePath = rtrim(str_replace('\\', '/', dirname(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/staff-main/api/public/home/realtime.php'))))), '/');
$basePath = $basePath === '' ? '' : $basePath;
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$nowTime = date('H:i:s');

function realtime_query_rows(PDO $conn, string $sql, array $params = []): array
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function realtime_query_value(PDO $conn, string $sql, array $params = [], $fallback = 0)
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? $fallback : $value;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function realtime_thai_date(string $date, bool $withWeekday = false): string
{
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];
    $weekdays = [
        0 => 'วันอาทิตย์',
        1 => 'วันจันทร์',
        2 => 'วันอังคาร',
        3 => 'วันพุธ',
        4 => 'วันพฤหัสบดี',
        5 => 'วันศุกร์',
        6 => 'วันเสาร์',
    ];

    $timestamp = strtotime($date) ?: time();
    $label = (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
    return $withWeekday ? $weekdays[(int) date('w', $timestamp)] . 'ที่ ' . $label : $label;
}

function realtime_time_label(?string $time): string
{
    if (!$time) {
        return '-';
    }

    $timestamp = strtotime($time);
    return $timestamp ? date('H:i', $timestamp) . ' น.' : '-';
}

function realtime_shift_label(?string $timeIn, ?string $timeOut): string
{
    if (!$timeIn || !$timeOut) {
        return '-';
    }

    return date('H:i', strtotime($timeIn)) . ' - ' . date('H:i', strtotime($timeOut));
}

function realtime_profile_image_url(string $basePath, ?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    $normalizedPath = str_replace('\\', '/', $path);
    $relativeCandidates = str_contains($normalizedPath, '/')
        ? [ltrim($normalizedPath, '/')]
        : ['uploads/avatars/' . $normalizedPath, 'uploads/profiles/' . $normalizedPath];

    foreach ($relativeCandidates as $relativePath) {
        if (is_file(__DIR__ . '/../../../' . $relativePath)) {
            $segments = array_map('rawurlencode', explode('/', $relativePath));
            return rtrim($basePath, '/') . '/' . implode('/', $segments);
        }
    }

    return '';
}

function realtime_user_initial(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '' || $name === '-') {
        return '-';
    }

    return function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
}

$activeShiftCondition = "
       t.time_in IS NOT NULL
       AND t.time_out IS NOT NULL
       AND COALESCE(t.status, 'submitted') <> 'draft'
       AND (
           (
               t.work_date = ?
               AND TIME(t.time_in) <= TIME(t.time_out)
               AND TIME(t.time_in) <= ?
               AND TIME(t.time_out) >= ?
           )
           OR (
               t.work_date = ?
               AND TIME(t.time_in) > TIME(t.time_out)
               AND TIME(t.time_in) <= ?
           )
           OR (
               t.work_date = ?
               AND TIME(t.time_in) > TIME(t.time_out)
               AND TIME(t.time_out) >= ?
           )
       )";
$activeShiftParams = [$today, $nowTime, $nowTime, $today, $nowTime, $yesterday, $nowTime];

$activeUsers = realtime_query_rows(
    $conn,
    "SELECT
        COALESCE(u.fullname, '-') AS fullname,
        COALESCE(u.position_name, '-') AS position_name,
        COALESCE(u.profile_image_path, '') AS profile_image_path,
        COALESCE(d.department_name, '-') AS department_name,
        t.time_in,
        t.time_out,
        COALESCE(t.status, 'submitted') AS status
     FROM time_logs t
     LEFT JOIN users u ON t.user_id = u.id
     LEFT JOIN departments d ON t.department_id = d.id
     WHERE {$activeShiftCondition}
     ORDER BY t.time_in ASC, u.fullname ASC",
    $activeShiftParams
);

$activeDepartments = realtime_query_rows(
    $conn,
    "SELECT
        COALESCE(d.department_name, '-') AS department_name,
        COUNT(DISTINCT t.user_id) AS staff_count
     FROM time_logs t
     LEFT JOIN departments d ON t.department_id = d.id
     WHERE {$activeShiftCondition}
     GROUP BY d.id, d.department_name
     HAVING staff_count > 0
     ORDER BY staff_count DESC, d.department_name ASC",
    $activeShiftParams
);

$todayAttendanceCount = (int) realtime_query_value($conn, 'SELECT COUNT(*) FROM time_logs WHERE work_date = ?', [$today]);
$expectedTodayShiftCount = (int) realtime_query_value($conn, 'SELECT COUNT(*) FROM users WHERE COALESCE(is_active, 1) = 1');
$attendanceRate = $expectedTodayShiftCount > 0 ? min(100, (int) round(($todayAttendanceCount / $expectedTodayShiftCount) * 100)) : 0;

$activeUsersPayload = array_map(static function (array $row) use ($basePath): array {
    return [
        'fullname' => (string) ($row['fullname'] ?? '-'),
        'position_name' => (string) ($row['position_name'] ?? '-'),
        'department_name' => (string) ($row['department_name'] ?? '-'),
        'avatar_url' => realtime_profile_image_url($basePath, $row['profile_image_path'] ?? ''),
        'initial' => realtime_user_initial($row['fullname'] ?? '-'),
        'shift_label' => realtime_shift_label($row['time_in'] ?? null, $row['time_out'] ?? null),
        'time_in_label' => realtime_time_label($row['time_in'] ?? null),
        'status_label' => 'ปฏิบัติงาน',
    ];
}, $activeUsers);

$activeDepartmentsPayload = array_map(static function (array $row): array {
    return [
        'department_name' => (string) ($row['department_name'] ?? '-'),
        'staff_count' => (int) ($row['staff_count'] ?? 0),
        'status_label' => 'กำลังปฏิบัติงาน',
    ];
}, $activeDepartments);

echo json_encode([
    'success' => true,
    'current_time' => date('H:i:s'),
    'current_date' => realtime_thai_date($today, true),
    'last_updated_at' => date('H:i') . ' น.',
    'metrics' => [
        'active_users_count' => count($activeUsersPayload),
        'active_departments_count' => count($activeDepartmentsPayload),
        'today_attendance_count' => $todayAttendanceCount,
        'expected_today_shift_count' => $expectedTodayShiftCount,
        'attendance_rate' => $attendanceRate,
    ],
    'active_users' => $activeUsersPayload,
    'active_departments' => $activeDepartmentsPayload,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
