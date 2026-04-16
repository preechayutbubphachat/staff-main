<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function app_role_labels(): array
{
    return [
        'admin' => 'ผู้ดูแลระบบ',
        'staff' => 'เจ้าหน้าที่ทั่วไป',
        'finance' => 'เจ้าหน้าที่การเงิน / ผู้มีสิทธิ์ดูข้อมูล',
        'checker' => 'ผู้ตรวจสอบ',
    ];
}

function app_default_permissions(string $role): array
{
    return match ($role) {
        'admin' => [
            'can_view_all_staff' => 1,
            'can_view_department_reports' => 1,
            'can_export_reports' => 1,
            'can_approve_logs' => 1,
            'can_manage_time_logs' => 1,
            'can_edit_locked_time_logs' => 1,
            'can_manage_user_permissions' => 1,
            'can_manage_database' => 1,
        ],
        'checker' => [
            'can_view_all_staff' => 1,
            'can_view_department_reports' => 1,
            'can_export_reports' => 1,
            'can_approve_logs' => 1,
            'can_manage_time_logs' => 0,
            'can_edit_locked_time_logs' => 0,
            'can_manage_user_permissions' => 0,
            'can_manage_database' => 0,
        ],
        'finance' => [
            'can_view_all_staff' => 1,
            'can_view_department_reports' => 1,
            'can_export_reports' => 1,
            'can_approve_logs' => 0,
            'can_manage_time_logs' => 0,
            'can_edit_locked_time_logs' => 0,
            'can_manage_user_permissions' => 0,
            'can_manage_database' => 0,
        ],
        default => [
            'can_view_all_staff' => 0,
            'can_view_department_reports' => 0,
            'can_export_reports' => 0,
            'can_approve_logs' => 0,
            'can_manage_time_logs' => 0,
            'can_edit_locked_time_logs' => 0,
            'can_manage_user_permissions' => 0,
            'can_manage_database' => 0,
        ],
    };
}

function app_normalize_role(?string $role): string
{
    $role = strtolower(trim((string) $role));
    return array_key_exists($role, app_role_labels()) ? $role : 'staff';
}

function app_user_permissions(array $user): array
{
    $role = app_normalize_role($user['role'] ?? 'staff');
    $defaults = app_default_permissions($role);

    foreach ($defaults as $key => $value) {
        if (array_key_exists($key, $user)) {
            $defaults[$key] = (int) $user[$key];
        }
    }

    return $defaults;
}

function app_set_auth_session(array $user): void
{
    $role = app_normalize_role($user['role'] ?? 'staff');
    $permissions = app_user_permissions($user);
    $displayName = app_user_display_name($user);

    $_SESSION['id'] = $user['id'];
    $_SESSION['fullname'] = $displayName;
    $_SESSION['username'] = $user['username'];
    $_SESSION['department_id'] = $user['department_id'] ?? 1;
    $_SESSION['role'] = $role;
    $_SESSION['permissions'] = $permissions;
    $_SESSION['ui_state_login_marker'] = bin2hex(random_bytes(16));
}

function app_ui_state_context(): array
{
    return [
        'user_id' => (int) ($_SESSION['id'] ?? 0),
        'login_marker' => (string) ($_SESSION['ui_state_login_marker'] ?? ''),
    ];
}

function app_require_login(): void
{
    if (empty($_SESSION['id'])) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function app_role_label(?string $role): string
{
    $role = app_normalize_role($role);
    return app_role_labels()[$role];
}

function app_role_compact_label(?string $role): string
{
    $role = app_normalize_role($role);
    $labels = [
        'admin' => 'ผู้ดูแล',
        'checker' => 'ผู้ตรวจสอบ',
        'finance' => 'การเงิน',
        'staff' => 'เจ้าหน้าที่',
    ];

    return $labels[$role] ?? app_role_label($role);
}

function app_user_display_name(array $user): string
{
    $firstName = trim((string) ($user['first_name'] ?? ''));
    $lastName = trim((string) ($user['last_name'] ?? ''));
    $fullName = trim((string) ($user['fullname'] ?? ''));

    $combined = trim($firstName . ' ' . $lastName);
    if ($combined !== '') {
        return $combined;
    }

    return $fullName !== '' ? $fullName : '-';
}

function app_compose_fullname(?string $firstName, ?string $lastName): string
{
    return trim(trim((string) $firstName) . ' ' . trim((string) $lastName));
}

function app_current_role(): string
{
    return app_normalize_role($_SESSION['role'] ?? 'staff');
}

function app_can(string $permission): bool
{
    $permissions = $_SESSION['permissions'] ?? [];
    return !empty($permissions[$permission]);
}

function app_redirect_after_login(): void
{
    header('Location: ../pages/dashboard.php');
    exit;
}

function app_redirect_to_dashboard(string $path = '../pages/dashboard.php'): void
{
    header('Location: ' . $path);
    exit;
}

function app_require_permission(string $permission, string $redirect = '../pages/dashboard.php'): void
{
    app_require_login();

    if (!app_can($permission)) {
        app_redirect_to_dashboard($redirect);
    }
}

function app_csrf_token(string $formKey = 'default'): string
{
    if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens'])) {
        $_SESSION['_csrf_tokens'] = [];
    }

    if (empty($_SESSION['_csrf_tokens'][$formKey])) {
        $_SESSION['_csrf_tokens'][$formKey] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_tokens'][$formKey];
}

function app_verify_csrf_token(?string $token, string $formKey = 'default'): bool
{
    $sessionToken = $_SESSION['_csrf_tokens'][$formKey] ?? '';
    return is_string($token) && $token !== '' && is_string($sessionToken) && hash_equals($sessionToken, $token);
}

function app_column_exists(PDO $conn, string $table, string $column): bool
{
    static $cache = [];
    $cacheKey = strtolower($table . '.' . $column);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    $cache[$cacheKey] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

    return $cache[$cacheKey];
}

function app_table_exists(PDO $conn, string $table): bool
{
    static $cache = [];
    $tableKey = strtolower($table);
    if (array_key_exists($tableKey, $cache)) {
        return $cache[$tableKey];
    }

    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    $cache[$tableKey] = (bool) $stmt->fetchColumn();

    return $cache[$tableKey];
}

function app_thai_month_names(): array
{
    return [
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
}

function app_thai_month_short_names(): array
{
    return [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.',
    ];
}

function app_format_thai_date(?string $date, bool $includeDayLabel = false): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }

    $months = app_thai_month_names();
    $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);
    $formatted = sprintf(
        '%d %s %d',
        (int) date('j', $timestamp),
        $month,
        (int) date('Y', $timestamp) + 543
    );

    return $includeDayLabel ? 'วันที่ ' . $formatted : $formatted;
}

function app_time_log_pending_condition(string $alias = 't'): string
{
    $alias = trim($alias);
    $prefix = $alias !== '' ? $alias . '.' : '';

    return "{$prefix}time_out IS NOT NULL AND {$prefix}checked_at IS NULL";
}

function app_time_log_checked_condition(string $alias = 't'): string
{
    $alias = trim($alias);
    $prefix = $alias !== '' ? $alias . '.' : '';

    return "{$prefix}time_out IS NOT NULL AND {$prefix}checked_at IS NOT NULL";
}

function app_time_log_is_pending(array $timeLog): bool
{
    return !empty($timeLog['time_out']) && empty($timeLog['checked_at']);
}

function app_format_thai_datetime(?string $dateTime, bool $includeTimeLabel = false): string
{
    if (!$dateTime) {
        return '-';
    }

    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return '-';
    }

    $date = app_format_thai_date(date('Y-m-d', $timestamp));
    $time = date('H:i', $timestamp) . ' น.';

    return $includeTimeLabel ? $date . ' เวลา ' . $time : $date . ' ' . $time;
}

function app_resolve_user_image_url(?string $fileName): ?string
{
    $fileName = trim((string) $fileName);
    if ($fileName === '') {
        return null;
    }

    $candidates = [
        ['dir' => __DIR__ . '/../uploads/avatars/', 'url' => '../uploads/avatars/'],
        ['dir' => __DIR__ . '/../uploads/profiles/', 'url' => '../uploads/profiles/'],
    ];

    foreach ($candidates as $candidate) {
        $absolutePath = $candidate['dir'] . $fileName;
        if (is_file($absolutePath)) {
            return $candidate['url'] . rawurlencode($fileName);
        }
    }

    return null;
}

