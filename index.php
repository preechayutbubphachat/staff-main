<?php
session_start();

if (!empty($_SESSION['id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/config/db.php';

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/staff-main/index.php')), '/');
$basePath = $basePath === '' ? '' : $basePath;
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$nowSql = date('Y-m-d H:i:s');
$nowTime = date('H:i:s');
$hospitalImagePath = __DIR__ . '/images/hopital.png';
$hospitalImageUrl = is_file($hospitalImagePath) ? $basePath . '/images/hopital.png' : '';
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

function home_query_value(PDO $conn, string $sql, array $params = [], $fallback = 0)
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

function home_query_rows(PDO $conn, string $sql, array $params = []): array
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function home_thai_date(string $date, bool $withWeekday = false): string
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

function home_time_label(?string $time): string
{
    if (!$time) {
        return '-';
    }

    $timestamp = strtotime($time);
    return $timestamp ? date('H:i', $timestamp) . ' น.' : '-';
}

function home_shift_label(?string $timeIn, ?string $timeOut): string
{
    if (!$timeIn || !$timeOut) {
        return '-';
    }

    return date('H:i', strtotime($timeIn)) . ' - ' . date('H:i', strtotime($timeOut));
}

function home_profile_image_url(string $basePath, ?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    $normalizedPath = str_replace('\\', '/', $path);
    $relativeCandidates = [];

    if (str_contains($normalizedPath, '/')) {
        $relativeCandidates[] = ltrim($normalizedPath, '/');
    } else {
        $relativeCandidates[] = 'uploads/avatars/' . $normalizedPath;
        $relativeCandidates[] = 'uploads/profiles/' . $normalizedPath;
    }

    foreach ($relativeCandidates as $relativePath) {
        $absolutePath = __DIR__ . '/' . $relativePath;
        if (is_file($absolutePath)) {
            $segments = array_map('rawurlencode', explode('/', $relativePath));
            return rtrim($basePath, '/') . '/' . implode('/', $segments);
        }
    }

    return '';
}

function home_user_initial(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '' || $name === '-') {
        return '-';
    }

    return function_exists('mb_substr')
        ? mb_substr($name, 0, 1, 'UTF-8')
        : substr($name, 0, 1);
}

function home_user_avatar_html(string $basePath, array $row, string $sizeClass = 'active-user-avatar'): string
{
    $name = (string) ($row['fullname'] ?? '-');
    $avatarUrl = home_profile_image_url($basePath, $row['profile_image_path'] ?? '');

    if ($avatarUrl !== '') {
        return '<img class="' . htmlspecialchars($sizeClass) . ' avatar profile-avatar export-hide print-hide" data-export-hidden="true" src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($name) . '" loading="lazy">';
    }

    return '<span class="' . htmlspecialchars($sizeClass) . '">' . htmlspecialchars(home_user_initial($name)) . '</span>';
}

function home_export_user_rows(array $rows, string $statusLabel): array
{
    return array_map(static function (array $row, int $index) use ($statusLabel): array {
        return [
            'no' => $index + 1,
            'fullName' => $row['fullname'] ?? '-',
            'position' => $row['position_name'] ?? '-',
            'department' => $row['department_name'] ?? '-',
            'shift' => home_shift_label($row['time_in'] ?? null, $row['time_out'] ?? null),
            'startTime' => home_time_label($row['time_in'] ?? null),
            'endTime' => home_time_label($row['time_out'] ?? null),
            'status' => $statusLabel,
        ];
    }, array_values($rows), array_keys(array_values($rows)));
}

function home_export_department_rows(array $rows): array
{
    return array_map(static function (array $row, int $index): array {
        $count = (int) ($row['staff_count'] ?? 0);
        $status = home_department_status($count);

        return [
            'no' => $index + 1,
            'department' => $row['department_name'] ?? '-',
            'staffCount' => number_format($count) . ' คน',
            'status' => $status['label'] ?? '-',
        ];
    }, array_values($rows), array_keys(array_values($rows)));
}

function home_department_status(int $count): array
{
    if ($count >= 5) {
        return ['label' => 'เพียงพอ', 'class' => 'is-ok'];
    }

    if ($count > 0) {
        return ['label' => 'ต่ำกว่าเป้า', 'class' => 'is-warning'];
    }

    return ['label' => 'รอข้อมูล', 'class' => 'is-muted'];
}

$activeUsersNow = home_query_rows(
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

$todayAttendanceRows = home_query_rows(
    $conn,
    "SELECT
        COALESCE(u.fullname, '-') AS fullname,
        COALESCE(u.position_name, '-') AS position_name,
        COALESCE(d.department_name, '-') AS department_name,
        t.time_in,
        t.time_out,
        COALESCE(t.status, 'submitted') AS status
     FROM time_logs t
     LEFT JOIN users u ON t.user_id = u.id
     LEFT JOIN departments d ON t.department_id = d.id
     WHERE t.work_date = ?
     ORDER BY t.time_in ASC, u.fullname ASC",
    [$today]
);

$activeDepartments = home_query_rows(
    $conn,
    "SELECT
        COALESCE(d.department_name, '-') AS department_name,
        COUNT(DISTINCT t.user_id) AS staff_count
     FROM time_logs t
     LEFT JOIN departments d ON t.department_id = d.id
     WHERE {$activeShiftCondition}
     GROUP BY d.id, d.department_name
     ORDER BY staff_count DESC, d.department_name ASC",
    $activeShiftParams
);

$todayAttendanceCount = (int) home_query_value($conn, 'SELECT COUNT(*) FROM time_logs WHERE work_date = ?', [$today]);
$expectedTodayShiftCount = (int) home_query_value($conn, 'SELECT COUNT(*) FROM users WHERE COALESCE(is_active, 1) = 1');
$attendanceRate = $expectedTodayShiftCount > 0 ? min(100, (int) round(($todayAttendanceCount / $expectedTodayShiftCount) * 100)) : 0;
$activeUsersCount = count($activeUsersNow);
$activeDepartmentsCount = count($activeDepartments);
$latestUpdateLabel = date('H:i') . ' น.';

$departmentTableRows = array_values(array_filter($activeDepartments, static fn ($row) => (int) ($row['staff_count'] ?? 0) > 0));
$topDepartmentsByCount = $departmentTableRows;
$homeExportPayload = [
    'activeUsers' => home_export_user_rows($activeUsersNow, 'ปฏิบัติงาน'),
    'departments' => home_export_department_rows($departmentTableRows),
    'attendance' => home_export_user_rows($todayAttendanceRows, 'บันทึกแล้ว'),
];
$homeInitialUsers = array_map(static function (array $row) use ($basePath): array {
    return [
        'fullname'        => $row['fullname'] ?? '-',
        'position_name'   => $row['position_name'] ?? '-',
        'department_name' => $row['department_name'] ?? '-',
        'shift_label'     => home_shift_label($row['time_in'] ?? null, $row['time_out'] ?? null),
        'time_in_label'   => home_time_label($row['time_in'] ?? null),
        'time_out_label'  => home_time_label($row['time_out'] ?? null),
        'status_label'    => 'ปฏิบัติงาน',
        'avatar_url'      => home_profile_image_url($basePath, $row['profile_image_path'] ?? ''),
        'initial'         => home_user_initial($row['fullname'] ?? ''),
    ];
}, array_values($activeUsersNow));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าแรกระบบ Over Time | โรงพยาบาลหนองพอก</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/index-tailwind.css">
    <style>
        /* ── Preview list: show 3 rows full without clipping ── */
        .active-preview-list {
            min-height: 130px;
            max-height: 162px !important;
        }
        /* ── Pagination controls ── */
        .ot-pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 0.625rem;
            border: 1px solid rgba(6,59,79,.12);
            background: #fff;
            color: #063b5a;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(6,59,79,.06);
        }
        .ot-pagination-btn:hover:not(:disabled) {
            background: #ddf8f3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(15,159,149,.15);
        }
        .ot-pagination-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
            transform: none;
        }
        .ot-pagination-select {
            appearance: none;
            -webkit-appearance: none;
            border: 1px solid rgba(6,59,79,.12);
            border-radius: 0.75rem;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23063b5a' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 0.55rem center;
            padding: 0.3rem 1.75rem 0.3rem 0.75rem;
            font-size: 0.75rem;
            font-family: Sarabun, sans-serif;
            font-weight: 700;
            color: #063b5a;
            cursor: pointer;
            min-height: 2rem;
            box-shadow: 0 2px 6px rgba(6,59,79,.06);
        }
        .ot-pagination-select:focus {
            outline: 2px solid #0f9f95;
            outline-offset: 2px;
        }
        .ot-pagination-info {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
        }
        #activeUsersTableFooter {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="home-shell">
    <main class="relative isolate min-h-screen py-4 sm:py-5">
        <div class="ambient-orb left-[-10rem] top-[-10rem] h-[30rem] w-[30rem] bg-hospital-aqua/40"></div>
        <div class="ambient-orb right-[-9rem] top-0 h-[34rem] w-[34rem] bg-hospital-mint/80"></div>

        <header class="home-frame relative z-20">
            <div class="glass-nav flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
                <a href="<?= htmlspecialchars($basePath) ?>/index.php" class="group flex min-w-0 items-center gap-4 text-hospital-ink no-underline" aria-label="หน้าแรก Over Time">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full border-2 border-hospital-teal bg-white text-3xl text-hospital-teal shadow-soft transition group-hover:-translate-y-0.5 sm:h-16 sm:w-16">
                        <i class="bi bi-clock-history"></i>
                    </span>
                    <span class="grid leading-tight">
                        <span class="font-prompt text-2xl font-extrabold tracking-[-0.04em] text-hospital-teal sm:text-4xl">Over Time</span>
                        <span class="text-xs font-semibold text-hospital-muted sm:text-sm">ระบบลงเวลางานสำหรับการโรงพยาบาล</span>
                    </span>
                </a>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <span class="date-pill hidden md:inline-flex"><i class="bi bi-calendar3"></i><?= htmlspecialchars(home_thai_date($today)) ?></span>
                    <a class="nav-action nav-action-outline" href="<?= htmlspecialchars($basePath) ?>/auth/register.php">
                        สมัครใช้งาน
                    </a>
                    <a class="nav-action nav-action-primary bg-hospital-teal hover:bg-emerald-700" href="<?= htmlspecialchars($basePath) ?>/auth/login.php">
                        เข้าสู่ระบบ
                    </a>
                </div>
            </div>
        </header>

        <div class="home-frame relative z-10 mt-5">
            <section class="overtime-hero-grid entrance-soft" aria-label="ภาพรวมหน้าแรก Over Time">
                <article class="ot-hero-card lg:col-span-5">
                    <div class="relative z-10 max-w-xl">
                        <span class="hero-kicker">
                            <i class="bi bi-bar-chart-fill text-hospital-teal"></i>
                            ภาพรวมการใช้งาน
                        </span>
                        <h1 class="mt-5 font-prompt text-3xl font-extrabold leading-tight tracking-[-0.035em] text-hospital-ink sm:text-4xl">
                            หน้าแรกระบบ Over Time
                        </h1>
                        <p class="mt-4 text-base leading-7 text-hospital-muted">
                            ติดตามสถานะการลงเวลางานของบุคลากรและแผนกต่าง ๆ แบบเรียลไทม์ เพื่อการบริหารที่มีประสิทธิภาพ
                        </p>

                        <div class="mt-6 inline-flex items-center gap-3 rounded-[1.25rem] border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-hospital-teal text-white">
                                <i class="bi bi-check-lg"></i>
                            </span>
                            <div>
                                <p class="text-sm font-extrabold text-hospital-teal">ระบบพร้อมใช้งาน</p>
                                <p class="text-xs font-semibold text-hospital-muted">อัปเดตข้อมูลล่าสุดเมื่อ <span data-last-updated><?= htmlspecialchars($latestUpdateLabel) ?></span></p>
                            </div>
                        </div>
                    </div>

                    <?php if ($hospitalImageUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($hospitalImageUrl) ?>" alt="ภาพประกอบโรงพยาบาล" class="ot-hospital-illustration">
                    <?php else: ?>
                        <div class="ot-hospital-fallback" aria-hidden="true"><i class="bi bi-hospital"></i></div>
                    <?php endif; ?>
                </article>

                <article class="ot-realtime-card lg:col-span-7">
                    <div class="relative z-10 flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <div class="inline-flex items-center gap-3 text-white">
                                <i class="bi bi-bar-chart-line-fill text-2xl"></i>
                                <h2 class="font-prompt text-2xl font-extrabold tracking-[-0.03em]">ภาพรวมวันนี้</h2>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 text-right text-white">
                            <i class="bi bi-geo-alt-fill text-2xl"></i>
                            <div>
                                <p class="font-extrabold">โรงพยาบาลหนองพอก</p>
                                <p class="text-sm font-semibold text-white/75">อ.หนองพอก จ.ร้อยเอ็ด</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 mt-5 grid gap-4 xl:grid-cols-[0.72fr_1.28fr]">
                        <div class="ot-clock-card">
                            <p class="inline-flex items-center gap-2 text-sm font-bold text-white/80">
                                <i class="bi bi-clock"></i>
                                เวลาปัจจุบัน
                            </p>
                            <strong id="homeLiveClock" class="mt-3 block font-prompt text-5xl font-extrabold tracking-[-0.06em] sm:text-6xl"><?= date('H:i:s') ?></strong>
                            <p id="homeLiveDate" class="mt-4 text-base font-semibold text-white/85"><?= htmlspecialchars(home_thai_date($today, true)) ?></p>
                        </div>

                        <div class="ot-status-panel">
                            <p class="mb-4 text-base font-extrabold text-white">สรุปสถานะการลงเวลาวันนี้</p>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="ot-status-metric">
                                    <i class="bi bi-people-fill"></i>
                                    <strong id="homeOverviewActiveUsers"><?= number_format($activeUsersCount) ?></strong>
                                    <span>บุคลากร<br>พร้อมทำงานที่ตอนนี้</span>
                                </div>
                                <div class="ot-status-metric">
                                    <i class="bi bi-buildings-fill"></i>
                                    <strong id="homeOverviewActiveDepartments"><?= number_format($activeDepartmentsCount) ?></strong>
                                    <span>แผนก<br>ลงเวรตอนนี้</span>
                                </div>
                                <div class="ot-status-metric">
                                    <i class="bi bi-clipboard2-check-fill"></i>
                                    <strong id="homeOverviewTodayAttendance"><?= number_format($todayAttendanceCount) ?></strong>
                                    <span>รายการ<br>ลงเวลาวันนี้</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="ot-metric-row mt-4" aria-label="Realtime cards">
                <article class="ot-summary-card">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-prompt text-[22px] font-extrabold leading-tight text-hospital-ink">Active Staff</h2>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-hospital-mint px-3 py-2 text-xs font-extrabold text-hospital-teal">
                            <span class="live-dot"></span> อัปเดตอัตโนมัติ
                        </span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-[142px_1fr]">
                        <div class="flex items-center gap-4">
                            <span class="stat-icon-badge"><i class="bi bi-people-fill"></i></span>
                            <div>
                                <strong id="homeCardActiveUsers" class="font-prompt text-5xl font-extrabold text-hospital-teal"><?= number_format($activeUsersCount) ?></strong>
                                <span class="ml-1 font-bold text-hospital-teal">คน</span>
                            </div>
                        </div>
                        <div id="homeActiveUsersPreview" class="active-preview-list rounded-[1.15rem] border border-hospital-navy/10 bg-white/70 p-2.5">
                            <?php if ($activeUsersNow): ?>
                                <?php foreach ($activeUsersNow as $row): ?>
                                    <div class="active-user-row">
                                        <?= home_user_avatar_html($basePath, $row) ?>
                                        <span class="min-w-0">
                                            <span class="block truncate text-[13px] font-extrabold leading-snug text-hospital-ink"><?= htmlspecialchars($row['fullname']) ?></span>
                                            <span class="block truncate text-[11px] font-semibold leading-snug text-hospital-muted"><?= htmlspecialchars($row['position_name']) ?></span>
                                        </span>
                                        <span class="max-w-[92px] truncate text-[11px] font-semibold text-hospital-muted"><?= htmlspecialchars($row['department_name']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="ot-empty-state">ยังไม่มีบุคลากรที่อยู่ในช่วงเวร ณ เวลานี้</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ot-compact-actions">
                        <button type="button" class="ot-tool-button" data-print-target="activeUsersModal"><i class="bi bi-printer"></i>Print</button>
                        <button type="button" class="ot-tool-button" data-print-target="activeUsersModal"><i class="bi bi-file-earmark-pdf-fill text-red-500"></i>PDF</button>
                        <button type="button" class="ot-tool-button" data-csv-target="activeUsersTable" data-filename="active-users-now.csv"><i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i>CSV</button>
                    </div>
                </article>

                <article class="ot-summary-card">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-prompt text-2xl font-extrabold text-hospital-ink">Active Departments</h2>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-hospital-mint px-3 py-2 text-xs font-extrabold text-hospital-teal">
                            <span class="live-dot"></span> อัปเดตอัตโนมัติ
                        </span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-[142px_1fr]">
                        <div class="flex items-center gap-4">
                            <span class="stat-icon-badge"><i class="bi bi-buildings-fill"></i></span>
                            <div>
                                <strong id="homeCardActiveDepartments" class="font-prompt text-5xl font-extrabold text-hospital-teal"><?= number_format($activeDepartmentsCount) ?></strong>
                                <span class="ml-1 font-bold text-hospital-teal">แผนก</span>
                            </div>
                        </div>
                        <div id="homeDepartmentsPreview" class="active-preview-list rounded-[1.15rem] border border-hospital-navy/10 bg-white/70 p-3">
                            <?php if ($topDepartmentsByCount): ?>
                                <?php foreach ($topDepartmentsByCount as $row): ?>
                                    <div class="flex items-center justify-between border-b border-hospital-navy/5 py-2 last:border-b-0">
                                        <span class="font-bold text-hospital-ink"><?= htmlspecialchars($row['department_name']) ?></span>
                                        <span class="font-semibold text-hospital-muted"><?= number_format((int) $row['staff_count']) ?> คน</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="ot-empty-state">ยังไม่มีบุคลากรที่อยู่ในช่วงเวร ณ เวลานี้</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ot-compact-actions">
                        <button type="button" class="ot-tool-button" data-print-target="departmentsModal"><i class="bi bi-printer"></i>Print</button>
                        <button type="button" class="ot-tool-button" data-print-target="departmentsModal"><i class="bi bi-file-earmark-pdf-fill text-red-500"></i>PDF</button>
                        <button type="button" class="ot-tool-button" data-csv-target="departmentsTable" data-filename="active-departments.csv"><i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i>CSV</button>
                    </div>
                </article>

                <article class="ot-summary-card">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-prompt text-2xl font-extrabold text-hospital-ink">Today Attendance</h2>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-hospital-mint px-3 py-2 text-xs font-extrabold text-hospital-teal">
                            <span class="live-dot"></span> อัปเดตอัตโนมัติ
                        </span>
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-[150px_1fr]">
                        <div class="flex items-center gap-4">
                            <span class="stat-icon-badge"><i class="bi bi-calendar2-check-fill"></i></span>
                            <div>
                                <strong id="homeCardTodayAttendance" class="font-prompt text-6xl font-extrabold text-hospital-teal"><?= number_format($todayAttendanceCount) ?></strong>
                                <p class="font-bold text-hospital-teal">รายการ</p>
                            </div>
                        </div>
                        <div class="rounded-[1.15rem] border border-hospital-navy/10 bg-white/70 p-5">
                            <p class="text-sm font-semibold text-hospital-muted">อัตราการลงเวลาภาพรวม</p>
                            <strong id="homeAttendanceRate" class="mt-1 block font-prompt text-4xl font-extrabold text-hospital-ink"><?= number_format($attendanceRate) ?>%</strong>
                            <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-200">
                                <span id="homeAttendanceRateBar" class="block h-full rounded-full bg-gradient-to-r from-hospital-teal to-emerald-400" style="width: <?= (int) $attendanceRate ?>%"></span>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-hospital-muted">เสร็จสิ้น <span id="homeAttendanceCompleted"><?= number_format($todayAttendanceCount) ?></span> จาก <span id="homeAttendanceExpected"><?= number_format(max($expectedTodayShiftCount, 1)) ?></span> เวร</p>
                        </div>
                    </div>
                    <div class="ot-compact-actions">
                        <button type="button" class="ot-tool-button" data-print-target="attendanceModal"><i class="bi bi-printer"></i>Print</button>
                        <button type="button" class="ot-tool-button" data-print-target="attendanceModal"><i class="bi bi-file-earmark-pdf-fill text-red-500"></i>PDF</button>
                        <button type="button" class="ot-tool-button" data-csv-target="attendanceTable" data-filename="today-attendance.csv"><i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i>CSV</button>
                    </div>
                </article>
            </section>

            <section id="operations-table" class="mt-4 grid gap-5 lg:grid-cols-[1.18fr_0.82fr]">
                <article class="ot-table-card">
                    <div class="ot-card-heading">
                        <div>
                            <span class="ot-section-icon"><i class="bi bi-people-fill"></i></span>
                            <h2>รายการบุคลากรที่ลงเวรตอนนี้</h2>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="ot-tool-button" data-csv-target="activeUsersTable" data-filename="active-users-now.csv"><i class="bi bi-download"></i>Export</button>
                            <button type="button" class="ot-tool-button" data-print-target="activeUsersModal"><i class="bi bi-printer"></i>Print</button>
                            <button type="button" class="ot-tool-button" data-print-target="activeUsersModal"><i class="bi bi-file-earmark-pdf-fill text-red-500"></i>PDF</button>
                            <button type="button" class="ot-tool-button" data-csv-target="activeUsersTable" data-filename="active-users-now.csv"><i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i>CSV</button>
                        </div>
                    </div>
                    <div class="ot-table-scroll">
                        <table class="ot-data-table" id="activeUsersTable">
                            <thead>
                                <tr>
                                    <th>ชื่อ</th>
                                    <th>ตำแหน่ง</th>
                                    <th>แผนก</th>
                                    <th>เวร</th>
                                    <th>เวลาเริ่ม</th>
                                    <th>สถานะ</th>
                                    <th class="text-right">เมนู</th>
                                </tr>
                            </thead>
                            <tbody id="activeUsersTableBody">
                                <?php if ($activeUsersNow): ?>
                                    <?php foreach (array_slice($activeUsersNow, 0, 10) as $row): ?>
                                        <tr>
                                            <td>
                                                <div class="active-table-person">
                                                    <?= home_user_avatar_html($basePath, $row, 'active-table-avatar') ?>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-[13px] font-extrabold text-hospital-ink"><?= htmlspecialchars($row['fullname']) ?></span>
                                                        <span class="block truncate text-[11px] font-semibold text-hospital-muted"><?= htmlspecialchars($row['position_name']) ?></span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($row['position_name']) ?></td>
                                            <td><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td><?= htmlspecialchars(home_shift_label($row['time_in'], $row['time_out'])) ?></td>
                                            <td><?= htmlspecialchars(home_time_label($row['time_in'])) ?></td>
                                            <td><span class="ot-status-badge"><span></span>ปฏิบัติงาน</span></td>
                                            <td class="text-right"><button type="button" class="ot-row-link" data-modal-open="activeUsersModal">⋮</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="py-10 text-center">ยังไม่มีบุคลากรที่อยู่ในช่วงเวร ณ เวลานี้</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="activeUsersTableFooter"></div>
                </article>

                <article class="ot-table-card">
                    <div class="ot-card-heading">
                        <div>
                            <span class="ot-section-icon"><i class="bi bi-buildings-fill"></i></span>
                            <h2>แผนกที่กำลังปฏิบัติงาน</h2>
                        </div>
                    </div>
                    <div class="ot-table-scroll">
                        <table class="ot-data-table" id="departmentsTable">
                            <thead>
                                <tr>
                                    <th>แผนก</th>
                                    <th>บุคลากรที่ปฏิบัติงาน</th>
                                    <th>สถานะ</th>
                                    <th>รายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody id="departmentsTableBody">
                                <?php if ($departmentTableRows): ?>
                                    <?php foreach ($departmentTableRows as $row): ?>
                                        <tr>
                                            <td class="font-extrabold text-hospital-ink"><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td><?= number_format((int) $row['staff_count']) ?> คน</td>
                                            <td><span class="ot-status-badge"><span></span>กำลังปฏิบัติงาน</span></td>
                                            <td><button type="button" class="ot-row-link" data-modal-open="departmentsModal">รายละเอียด <i class="bi bi-chevron-right"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="py-10 text-center">ยังไม่มีบุคลากรที่อยู่ในช่วงเวร ณ เวลานี้</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>
    </main>

    <dialog class="ot-modal" id="activeUsersModal">
        <div class="ot-modal-card">
            <header class="ot-modal-head">
                <div>
                    <p>User Active</p>
                    <h2>บุคลากรที่กำลังปฏิบัติงานตอนนี้</h2>
                </div>
                <button type="button" class="ot-modal-close" data-modal-close aria-label="ปิดหน้าต่าง"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="ot-modal-body">
                <div class="ot-modal-actions">
                    <button type="button" class="ot-tool-button" data-print-target="activeUsersModal"><i class="bi bi-printer"></i>Print</button>
                    <button type="button" class="ot-tool-button" data-print-target="activeUsersModal"><i class="bi bi-file-earmark-pdf-fill text-red-500"></i>PDF</button>
                    <button type="button" class="ot-tool-button" data-csv-target="activeUsersModalTable" data-filename="active-users-now.csv"><i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i>CSV</button>
                </div>
                <div class="ot-table-scroll">
                    <table class="ot-data-table" id="activeUsersModalTable">
                        <thead>
                            <tr>
                                <th>ชื่อ</th>
                                <th>ตำแหน่ง</th>
                                <th>แผนก</th>
                                <th>เวร</th>
                                <th>เวลาเริ่ม</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="activeUsersModalTableBody">
                            <?php if ($activeUsersNow): ?>
                                <?php foreach ($activeUsersNow as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="active-table-person">
                                                <?= home_user_avatar_html($basePath, $row, 'active-table-avatar') ?>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-[13px] font-extrabold text-hospital-ink"><?= htmlspecialchars($row['fullname']) ?></span>
                                                    <span class="block truncate text-[11px] font-semibold text-hospital-muted"><?= htmlspecialchars($row['position_name']) ?></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['position_name']) ?></td>
                                        <td><?= htmlspecialchars($row['department_name']) ?></td>
                                        <td><?= htmlspecialchars(home_shift_label($row['time_in'], $row['time_out'])) ?></td>
                                        <td><?= htmlspecialchars(home_time_label($row['time_in'])) ?></td>
                                        <td><span class="ot-status-badge"><span></span>ปฏิบัติงาน</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="py-10 text-center">ยังไม่มีบุคลากรที่อยู่ในช่วงเวร ณ เวลานี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </dialog>

    <dialog class="ot-modal" id="departmentsModal">
        <div class="ot-modal-card">
            <header class="ot-modal-head">
                <div>
                    <p>Department Active</p>
                    <h2>แผนกทั้งหมดที่กำลังปฏิบัติงาน</h2>
                </div>
                <button type="button" class="ot-modal-close" data-modal-close aria-label="ปิดหน้าต่าง"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="ot-modal-body">
                <div class="ot-modal-actions">
                    <button type="button" class="ot-tool-button" data-print-target="departmentsModal"><i class="bi bi-printer"></i>Print</button>
                    <button type="button" class="ot-tool-button" data-print-target="departmentsModal"><i class="bi bi-file-earmark-pdf-fill text-red-500"></i>PDF</button>
                    <button type="button" class="ot-tool-button" data-csv-target="departmentsModalTable" data-filename="active-departments.csv"><i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i>CSV</button>
                </div>
                <div class="ot-table-scroll">
                    <table class="ot-data-table" id="departmentsModalTable">
                        <thead>
                            <tr>
                                <th>แผนก</th>
                                <th>จำนวนบุคลากรที่ปฏิบัติงาน</th>
                                <th>สถานะ</th>
                                <th>รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody id="departmentsModalTableBody">
                            <?php if ($departmentTableRows): ?>
                                <?php foreach ($departmentTableRows as $row): ?>
                                    <tr>
                                        <td class="font-extrabold text-hospital-ink"><?= htmlspecialchars($row['department_name']) ?></td>
                                        <td><?= number_format((int) $row['staff_count']) ?> คน</td>
                                        <td><span class="ot-status-badge"><span></span>กำลังปฏิบัติงาน</span></td>
                                        <td>ข้อมูลจากช่วงเวลาปัจจุบัน</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="py-10 text-center">ยังไม่มีบุคลากรที่อยู่ในช่วงเวร ณ เวลานี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </dialog>

    <dialog class="ot-modal" id="attendanceModal">
        <div class="ot-modal-card">
            <header class="ot-modal-head">
                <div>
                    <p>Today Attendance</p>
                    <h2>รายการลงเวลาวันนี้</h2>
                </div>
                <button type="button" class="ot-modal-close" data-modal-close aria-label="ปิดหน้าต่าง"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="ot-modal-body">
                <div class="ot-modal-actions">
                    <button type="button" class="ot-tool-button" data-print-target="attendanceModal"><i class="bi bi-printer"></i>Print</button>
                    <button type="button" class="ot-tool-button" data-print-target="attendanceModal"><i class="bi bi-file-earmark-pdf-fill text-red-500"></i>PDF</button>
                    <button type="button" class="ot-tool-button" data-csv-target="attendanceTable" data-filename="today-attendance.csv"><i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i>CSV</button>
                </div>
                <div class="ot-table-scroll">
                    <table class="ot-data-table" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>ชื่อ</th>
                                <th>ตำแหน่ง</th>
                                <th>แผนก</th>
                                <th>เวร</th>
                                <th>เวลาเริ่ม</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($todayAttendanceRows): ?>
                                <?php foreach ($todayAttendanceRows as $row): ?>
                                    <tr>
                                        <td class="font-extrabold text-hospital-ink"><?= htmlspecialchars($row['fullname']) ?></td>
                                        <td><?= htmlspecialchars($row['position_name']) ?></td>
                                        <td><?= htmlspecialchars($row['department_name']) ?></td>
                                        <td><?= htmlspecialchars(home_shift_label($row['time_in'], $row['time_out'])) ?></td>
                                        <td><?= htmlspecialchars(home_time_label($row['time_in'])) ?></td>
                                        <td><span class="ot-status-badge"><span></span>บันทึกแล้ว</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="py-10 text-center">ยังไม่มีรายการลงเวลาวันนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </dialog>

    <script>
        (() => {
            const clock = document.getElementById('homeLiveClock');
            const dateLabel = document.getElementById('homeLiveDate');
            const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            const thaiWeekdays = ['วันอาทิตย์', 'วันจันทร์', 'วันอังคาร', 'วันพุธ', 'วันพฤหัสบดี', 'วันศุกร์', 'วันเสาร์'];

            const pad = (value) => String(value).padStart(2, '0');
            const tick = () => {
                const now = new Date();
                if (clock) {
                    clock.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
                }
                if (dateLabel) {
                    dateLabel.textContent = `${thaiWeekdays[now.getDay()]}ที่ ${now.getDate()} ${thaiMonths[now.getMonth()]} ${now.getFullYear() + 543}`;
                }
            };

            tick();
            window.setInterval(tick, 1000);

            const realtimeEndpoint = '<?= htmlspecialchars($basePath) ?>/api/public/home/realtime.php';
            const homeExportData = <?= json_encode($homeExportPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const homeInitialUsers = <?= json_encode($homeInitialUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const paginationState = { allUsers: homeInitialUsers, currentPage: 1, perPage: 10 };
            const homeReportDefinitions = {
                activeUsers: {
                    title: 'รายการบุคลากรที่ลงเวรตอนนี้',
                    filename: 'active-users-now.csv',
                    columns: [['no', 'ลำดับ'], ['fullName', 'ชื่อ'], ['position', 'ตำแหน่ง'], ['department', 'แผนก'], ['shift', 'เวร'], ['startTime', 'เวลาเริ่ม'], ['endTime', 'เวลาสิ้นสุด'], ['status', 'สถานะ']],
                },
                departments: {
                    title: 'แผนกที่กำลังปฏิบัติงาน',
                    filename: 'active-departments.csv',
                    columns: [['no', 'ลำดับ'], ['department', 'แผนก'], ['staffCount', 'จำนวนบุคลากรที่ปฏิบัติงาน'], ['status', 'สถานะ']],
                },
                attendance: {
                    title: 'รายการลงเวลาวันนี้',
                    filename: 'today-attendance.csv',
                    columns: [['no', 'ลำดับ'], ['fullName', 'ชื่อ'], ['position', 'ตำแหน่ง'], ['department', 'แผนก'], ['shift', 'เวร'], ['startTime', 'เวลาเริ่ม'], ['endTime', 'เวลาสิ้นสุด'], ['status', 'สถานะ']],
                },
            };
            const emptyActiveMessage = 'ยังไม่มีบุคลากรที่อยู่ในช่วงเวร ณ เวลานี้';
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));
            const formatNumber = (value) => new Intl.NumberFormat('th-TH').format(Number(value || 0));
            const normalizeExportValue = (value) => {
                const text = String(value ?? '-').replace(/\s+/g, ' ').trim();
                if (!text || /^data:image\//i.test(text) || /^https?:\/\/.*\.(png|jpe?g|webp|gif|svg)(\?|#|$)/i.test(text)) {
                    return '-';
                }
                return text;
            };
            const activeUsersToExportRows = (users, statusLabel = 'ปฏิบัติงาน') => users.map((user, index) => ({
                no: index + 1,
                fullName: normalizeExportValue(user.fullname || user.full_name || user.name),
                position: normalizeExportValue(user.position_name || user.position),
                department: normalizeExportValue(user.department_name),
                shift: normalizeExportValue(user.shift_label || user.shift_name),
                startTime: normalizeExportValue(user.time_in_label || user.start_time),
                endTime: normalizeExportValue(user.time_out_label || user.end_time),
                status: normalizeExportValue(user.status_label || user.status || statusLabel),
            }));
            const departmentsToExportRows = (departments) => departments.map((department, index) => ({
                no: index + 1,
                department: normalizeExportValue(department.department_name || department.department),
                staffCount: `${formatNumber(department.staff_count)} คน`,
                status: normalizeExportValue(department.status_label || department.status || 'กำลังปฏิบัติงาน'),
            }));
            const getReportKeyFromButton = (button) => {
                const target = button.dataset.csvTarget || button.dataset.printTarget || '';
                if (target.includes('department')) return 'departments';
                if (target.includes('attendance')) return 'attendance';
                return 'activeUsers';
            };
            const getReportRows = (key) => Array.isArray(homeExportData[key]) ? homeExportData[key] : [];
            const rowsToCsv = (definition, rows) => {
                const headers = definition.columns.map(([, label]) => label);
                const bodyRows = rows.map((row) => definition.columns.map(([field]) => normalizeExportValue(row[field])));
                return [headers, ...bodyRows]
                    .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
                    .join('\n');
            };
            const buildReportHtml = (definition, rows) => {
                const generatedAt = new Date().toLocaleString('th-TH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
                const head = definition.columns.map(([, label]) => `<th>${escapeHtml(label)}</th>`).join('');
                const body = rows.length
                    ? rows.map((row) => `<tr>${definition.columns.map(([field]) => `<td>${escapeHtml(normalizeExportValue(row[field]))}</td>`).join('')}</tr>`).join('')
                    : `<tr><td colspan="${definition.columns.length}" class="empty">ไม่มีข้อมูลสำหรับส่งออก</td></tr>`;

                return `<section class="report-page"><h1>${escapeHtml(definition.title)}</h1><p class="meta">วันที่/เวลาส่งออก: ${escapeHtml(generatedAt)}</p><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table><p class="summary">จำนวนรายการรวม: ${escapeHtml(formatNumber(rows.length))} รายการ</p></section>`;
            };
            const avatarHtml = (user, className = 'active-user-avatar') => {
                const name = user.fullname || '-';
                if (user.avatar_url) {
                    return `<img class="${className} avatar profile-avatar export-hide print-hide" data-export-hidden="true" src="${escapeHtml(user.avatar_url)}" alt="${escapeHtml(name)}" loading="lazy">`;
                }
                return `<span class="${className}">${escapeHtml(user.initial || name.slice(0, 1) || '-')}</span>`;
            };
            const setText = (selector, value) => {
                document.querySelectorAll(selector).forEach((node) => {
                    node.textContent = value;
                });
            };
            const setById = (id, value) => {
                const node = document.getElementById(id);
                if (node) node.textContent = value;
            };
            const renderEmptyRow = (colspan) => `<tr><td colspan="${colspan}" class="py-10 text-center">${emptyActiveMessage}</td></tr>`;
            const renderActiveUsersPreview = (users) => {
                if (!users.length) {
                    return `<p class="ot-empty-state">${emptyActiveMessage}</p>`;
                }

                return users.map((user) => `
                    <div class="active-user-row">
                        ${avatarHtml(user)}
                        <span class="min-w-0">
                            <span class="block truncate text-[13px] font-extrabold leading-snug text-hospital-ink">${escapeHtml(user.fullname)}</span>
                            <span class="block truncate text-[11px] font-semibold leading-snug text-hospital-muted">${escapeHtml(user.position_name)}</span>
                        </span>
                        <span class="max-w-[92px] truncate text-[11px] font-semibold text-hospital-muted">${escapeHtml(user.department_name)}</span>
                    </div>
                `).join('');
            };
            const renderDepartmentsPreview = (departments) => {
                if (!departments.length) {
                    return `<p class="ot-empty-state">${emptyActiveMessage}</p>`;
                }

                return departments.map((department) => `
                    <div class="flex items-center justify-between border-b border-hospital-navy/5 py-2 last:border-b-0">
                        <span class="font-bold text-hospital-ink">${escapeHtml(department.department_name)}</span>
                        <span class="font-semibold text-hospital-muted">${formatNumber(department.staff_count)} คน</span>
                    </div>
                `).join('');
            };
            const renderActiveUsersTable = (users, includeMenu = true) => {
                if (!users.length) {
                    return renderEmptyRow(includeMenu ? 7 : 6);
                }

                return users.map((user) => `
                    <tr>
                        <td>
                            <div class="active-table-person">
                                ${avatarHtml(user, 'active-table-avatar')}
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-extrabold text-hospital-ink">${escapeHtml(user.fullname)}</span>
                                    <span class="block truncate text-[11px] font-semibold text-hospital-muted">${escapeHtml(user.position_name)}</span>
                                </span>
                            </div>
                        </td>
                        <td>${escapeHtml(user.position_name)}</td>
                        <td>${escapeHtml(user.department_name)}</td>
                        <td>${escapeHtml(user.shift_label)}</td>
                        <td>${escapeHtml(user.time_in_label)}</td>
                        <td><span class="ot-status-badge"><span></span>${escapeHtml(user.status_label || 'ปฏิบัติงาน')}</span></td>
                        ${includeMenu ? '<td class="text-right"><button type="button" class="ot-row-link" data-modal-open="activeUsersModal">⋮</button></td>' : ''}
                    </tr>
                `).join('');
            };
            const renderDepartmentsTable = (departments, includeDetails = true) => {
                if (!departments.length) {
                    return renderEmptyRow(4);
                }

                return departments.map((department) => `
                    <tr>
                        <td class="font-extrabold text-hospital-ink">${escapeHtml(department.department_name)}</td>
                        <td>${formatNumber(department.staff_count)} คน</td>
                        <td><span class="ot-status-badge"><span></span>${escapeHtml(department.status_label || 'กำลังปฏิบัติงาน')}</span></td>
                        <td>${includeDetails ? '<button type="button" class="ot-row-link" data-modal-open="departmentsModal">รายละเอียด <i class="bi bi-chevron-right"></i></button>' : 'ข้อมูลจากช่วงเวลาปัจจุบัน'}</td>
                    </tr>
                `).join('');
            };
            /* ── Pagination ── */
            const buildPaginationFooterHtml = (total, curPage, totalPages, start, end) => {
                const startDisplay = total > 0 ? start + 1 : 0;
                const prevDisabled = curPage <= 1 ? 'disabled' : '';
                const nextDisabled = curPage >= totalPages ? 'disabled' : '';
                const perPageOptions = [10, 20, 50]
                    .map((v) => `<option value="${v}"${paginationState.perPage === v ? ' selected' : ''}>${v} / หน้า</option>`)
                    .join('');
                return `
                    <div class="ot-pagination-info">แสดง ${formatNumber(startDisplay)}–${formatNumber(end)} จาก ${formatNumber(total)} รายการ</div>
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                        <select id="perPageSelect" class="ot-pagination-select">${perPageOptions}</select>
                        <span class="ot-pagination-info">หน้า ${formatNumber(curPage)} / ${formatNumber(totalPages)}</span>
                        <button type="button" id="prevPageBtn" class="ot-pagination-btn" ${prevDisabled} aria-label="ก่อนหน้า"><i class="bi bi-chevron-left"></i></button>
                        <button type="button" id="nextPageBtn" class="ot-pagination-btn" ${nextDisabled} aria-label="ถัดไป"><i class="bi bi-chevron-right"></i></button>
                    </div>`;
            };
            const bindPaginationEvents = () => {
                const perPageSelect = document.getElementById('perPageSelect');
                const prevBtn = document.getElementById('prevPageBtn');
                const nextBtn = document.getElementById('nextPageBtn');
                if (perPageSelect) {
                    perPageSelect.addEventListener('change', (e) => {
                        paginationState.perPage = parseInt(e.target.value, 10);
                        paginationState.currentPage = 1;
                        renderPaginatedTable();
                    });
                }
                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        if (paginationState.currentPage > 1) {
                            paginationState.currentPage--;
                            renderPaginatedTable();
                        }
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        const tp = Math.max(1, Math.ceil(paginationState.allUsers.length / paginationState.perPage));
                        if (paginationState.currentPage < tp) {
                            paginationState.currentPage++;
                            renderPaginatedTable();
                        }
                    });
                }
            };
            const renderPaginatedTable = () => {
                const { allUsers, perPage } = paginationState;
                const total = allUsers.length;
                const totalPages = Math.max(1, Math.ceil(total / perPage));
                paginationState.currentPage = Math.min(paginationState.currentPage, totalPages);
                const curPage = paginationState.currentPage;
                const start = (curPage - 1) * perPage;
                const pageUsers = allUsers.slice(start, start + perPage);
                const end = Math.min(start + perPage, total);

                const tableBody = document.getElementById('activeUsersTableBody');
                if (tableBody) tableBody.innerHTML = renderActiveUsersTable(pageUsers, true);

                const footer = document.getElementById('activeUsersTableFooter');
                if (footer) {
                    if (total === 0) {
                        footer.innerHTML = `<span class="ot-pagination-info">ไม่มีรายการ</span>`;
                    } else {
                        footer.innerHTML = buildPaginationFooterHtml(total, curPage, totalPages, start, end);
                        bindPaginationEvents();
                    }
                }
            };
            /* ── end Pagination ── */

            const updateActionAvailability = (usersCount, departmentsCount) => {
                document.querySelectorAll('[data-csv-target="activeUsersTable"], [data-csv-target="activeUsersModalTable"], [data-print-target="activeUsersModal"]').forEach((button) => {
                    button.toggleAttribute('disabled', usersCount === 0);
                    button.classList.toggle('is-disabled', usersCount === 0);
                    button.title = usersCount === 0 ? 'ไม่มีข้อมูลสำหรับส่งออก' : '';
                });
                document.querySelectorAll('[data-csv-target="departmentsTable"], [data-csv-target="departmentsModalTable"], [data-print-target="departmentsModal"]').forEach((button) => {
                    button.toggleAttribute('disabled', departmentsCount === 0);
                    button.classList.toggle('is-disabled', departmentsCount === 0);
                    button.title = departmentsCount === 0 ? 'ไม่มีข้อมูลสำหรับส่งออก' : '';
                });
            };
            const updateRealtimeUi = (payload) => {
                if (!payload || !payload.success) return;
                const metrics = payload.metrics || {};
                const activeUsers = Array.isArray(payload.active_users) ? payload.active_users : [];
                const activeDepartments = Array.isArray(payload.active_departments) ? payload.active_departments : [];
                const usersCount = Number(metrics.active_users_count || activeUsers.length);
                const departmentsCount = Number(metrics.active_departments_count || activeDepartments.length);
                const todayCount = Number(metrics.today_attendance_count || 0);
                const expectedCount = Number(metrics.expected_today_shift_count || 0);
                const rate = Number(metrics.attendance_rate || 0);

                setById('homeOverviewActiveUsers', formatNumber(usersCount));
                setById('homeOverviewActiveDepartments', formatNumber(departmentsCount));
                setById('homeOverviewTodayAttendance', formatNumber(todayCount));
                setById('homeCardActiveUsers', formatNumber(usersCount));
                setById('homeCardActiveDepartments', formatNumber(departmentsCount));
                setById('homeCardTodayAttendance', formatNumber(todayCount));
                setById('homeAttendanceRate', `${formatNumber(rate)}%`);
                setById('homeAttendanceCompleted', formatNumber(todayCount));
                setById('homeAttendanceExpected', formatNumber(Math.max(expectedCount, 1)));
                const rateBar = document.getElementById('homeAttendanceRateBar');
                if (rateBar) rateBar.style.width = `${Math.max(0, Math.min(100, rate))}%`;
                if (payload.last_updated_at) setText('[data-last-updated]', payload.last_updated_at);
                homeExportData.activeUsers = activeUsersToExportRows(activeUsers, 'ปฏิบัติงาน');
                homeExportData.departments = departmentsToExportRows(activeDepartments);

                const usersPreview = document.getElementById('homeActiveUsersPreview');
                if (usersPreview) usersPreview.innerHTML = renderActiveUsersPreview(activeUsers);
                const departmentsPreview = document.getElementById('homeDepartmentsPreview');
                if (departmentsPreview) departmentsPreview.innerHTML = renderDepartmentsPreview(activeDepartments);
                /* update paginated main table */
                paginationState.allUsers = activeUsers;
                paginationState.currentPage = 1;
                renderPaginatedTable();
                /* update modal table (shows all rows) */
                const activeUsersModalTableBody = document.getElementById('activeUsersModalTableBody');
                if (activeUsersModalTableBody) activeUsersModalTableBody.innerHTML = renderActiveUsersTable(activeUsers, false);
                const departmentsTableBody = document.getElementById('departmentsTableBody');
                if (departmentsTableBody) departmentsTableBody.innerHTML = renderDepartmentsTable(activeDepartments, true);
                const departmentsModalTableBody = document.getElementById('departmentsModalTableBody');
                if (departmentsModalTableBody) departmentsModalTableBody.innerHTML = renderDepartmentsTable(activeDepartments, false);
                updateActionAvailability(usersCount, departmentsCount);
            };
            const fetchRealtime = async () => {
                try {
                    const response = await fetch(`${realtimeEndpoint}?_=${Date.now()}`, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    });
                    if (!response.ok) return;
                    updateRealtimeUi(await response.json());
                } catch (error) {
                    // Keep current UI if the near-real-time endpoint is temporarily unavailable.
                }
            };

            updateActionAvailability(<?= (int) $activeUsersCount ?>, <?= (int) $activeDepartmentsCount ?>);
            renderPaginatedTable();
            fetchRealtime();
            window.setInterval(fetchRealtime, 15000);

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-modal-open]');
                if (!button) return;
                const modal = document.getElementById(button.dataset.modalOpen);
                if (modal && typeof modal.showModal === 'function') {
                    modal.showModal();
                }
            });

            document.querySelectorAll('[data-modal-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    const modal = button.closest('dialog');
                    if (modal) modal.close();
                });
            });

            document.querySelectorAll('.ot-modal').forEach((modal) => {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) modal.close();
                });
            });

            document.querySelectorAll('[data-csv-target]').forEach((button) => {
                button.addEventListener('click', () => {
                    const key = getReportKeyFromButton(button);
                    const definition = homeReportDefinitions[key];
                    if (!definition) return;
                    const blob = new Blob(['\ufeff' + rowsToCsv(definition, getReportRows(key))], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = button.dataset.filename || definition.filename || 'export.csv';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                });
            });

            document.querySelectorAll('[data-print-target]').forEach((button) => {
                button.addEventListener('click', () => {
                    const key = getReportKeyFromButton(button);
                    const definition = homeReportDefinitions[key];
                    if (!definition) return;
                    const content = buildReportHtml(definition, getReportRows(key));
                    const printWindow = window.open('', '_blank', 'width=1100,height=720');
                    if (!printWindow) return;
                    printWindow.document.write(`<!doctype html><html lang="th"><head><meta charset="utf-8"><title>${escapeHtml(definition.title)}</title><style>@page{size:A4 portrait;margin:12mm}*{box-sizing:border-box}body{font-family:Sarabun,Arial,sans-serif;margin:0;padding:0;color:#10243b;background:#fff;font-size:11px}.report-page{width:100%}h1{margin:0 0 4px;font-size:18px;line-height:1.35}.meta,.summary{margin:0 0 10px;color:#53677f;font-size:11px}.summary{margin-top:10px;font-weight:700}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #d9e7ec;padding:6px 7px;text-align:left;vertical-align:top;word-break:break-word}th{background:#eef8f8;color:#10243b;font-weight:700}.empty{text-align:center;color:#53677f}.export-hide,.print-hide,.avatar,.profile-avatar,img[data-export-hidden="true"],img,svg,canvas,video{display:none!important}</style></head><body>${content}</body></html>`);
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                });
            });
        })();
    </script>
</body>
</html>
