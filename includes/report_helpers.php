<?php

require_once __DIR__ . '/notification_helpers.php';

function app_parse_table_page_size(array $input, int $default = 20): int
{
    $allowed = [10, 20, 50, 100];
    $requested = (int) ($input['per_page'] ?? $default);

    return in_array($requested, $allowed, true) ? $requested : $default;
}

function app_parse_table_page(array $input, string $key = 'p'): int
{
    return max(1, (int) ($input[$key] ?? 1));
}

function app_build_table_query(array $base, array $overrides = []): string
{
    $query = array_merge($base, $overrides);

    return http_build_query(array_filter($query, static fn($value) => $value !== '' && $value !== null));
}

function app_get_table_layout_widths(string $layout, int $dynamicColumnCount = 0): array
{
    $layouts = [
        'my_reports' => [8, 16, 14, 18, 10, 20, 14],
        'department_summary' => [8, 24, 16, 14, 10, 10, 9, 9],
        'daily_roster' => [8, 29, 18, 16, 14, 15],
        'approval_queue' => [6, 6, 10, 18, 12, 10, 10, 8, 10, 10],
        'manage_time_logs' => [4, 8, 14, 9, 8, 6, 6, 6, 10, 7, 7, 7, 8],
        'manage_users' => [6, 24, 14, 14, 12, 14, 16],
        'db_change_logs' => [6, 16, 16, 12, 16, 34],
        'load_staff' => [45, 30, 25],
    ];

    if ($layout === 'db_table_generic') {
        $dynamicColumnCount = max(1, $dynamicColumnCount);
        $numberWidth = 7;
        $actionWidth = 13;
        $remainingWidth = 100 - $numberWidth - $actionWidth;
        $baseWidth = (int) floor($remainingWidth / $dynamicColumnCount);
        $widths = [$numberWidth];
        for ($index = 0; $index < $dynamicColumnCount; $index++) {
            $widths[] = $baseWidth;
        }
        $widths[count($widths) - 1] += $remainingWidth - ($baseWidth * $dynamicColumnCount);
        $widths[] = $actionWidth;

        return $widths;
    }

    return $layouts[$layout] ?? [];
}

function app_render_table_colgroup(string $layout, int $dynamicColumnCount = 0): void
{
    $widths = app_get_table_layout_widths($layout, $dynamicColumnCount);
    if (!$widths) {
        return;
    }

    echo '<colgroup>';
    foreach ($widths as $width) {
        echo '<col style="width: ' . number_format((float) $width, 2, '.', '') . '%;">';
    }
    echo '</colgroup>';
}

function app_table_row_number(int $page, int $pageSize, int $indexWithinPage): int
{
    return max(1, (($page - 1) * $pageSize) + $indexWithinPage + 1);
}

function app_parse_be_year($input, ?int $defaultYearCe = null): array
{
    $defaultYearCe = $defaultYearCe ?: (int) date('Y');
    $yearBe = is_scalar($input) ? (int) $input : 0;

    if ($yearBe < 2400 || $yearBe > 2800) {
        $yearBe = $defaultYearCe + 543;
    }

    return [
        'year_be' => $yearBe,
        'year_ce' => $yearBe - 543,
    ];
}

function app_parse_month_year_filter(array $input, string $monthKey = 'month', string $yearBeKey = 'year_be'): array
{
    $currentMonth = (int) date('n');
    $currentYearCe = (int) date('Y');
    $legacyMonth = trim((string) ($input[$monthKey] ?? ''));
    $monthNumber = is_numeric($legacyMonth) ? (int) $legacyMonth : 0;
    $yearBeRaw = $input[$yearBeKey] ?? null;

    if ((!$monthNumber || $monthNumber < 1 || $monthNumber > 12) && preg_match('/^(\d{4})-(\d{2})$/', $legacyMonth, $matches)) {
        $monthNumber = (int) $matches[2];
        if ($yearBeRaw === null || $yearBeRaw === '') {
            $yearBeRaw = ((int) $matches[1]) + 543;
        }
    }

    if ($monthNumber < 1 || $monthNumber > 12) {
        $monthNumber = $currentMonth;
    }

    $yearData = app_parse_be_year($yearBeRaw, $currentYearCe);
    $thaiMonths = app_thai_month_names();
    $monthLabel = $thaiMonths[$monthNumber] ?? '';
    $monthValue = sprintf('%04d-%02d', $yearData['year_ce'], $monthNumber);

    return [
        'month' => $monthNumber,
        'month_number' => $monthNumber,
        'month_value' => $monthValue,
        'year_be' => $yearData['year_be'],
        'year_ce' => $yearData['year_ce'],
        'month_label_th' => $monthLabel,
        'heading_month_year_th' => trim($monthLabel . ' ' . $yearData['year_be']),
    ];
}

function app_get_thai_month_select_options(bool $short = false): array
{
    if ($short) {
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

    return app_thai_month_names();
}

function app_normalize_my_report_filters(array $input): array
{
    $period = $input['period'] ?? 'month';
    $period = in_array($period, ['week', 'month', 'year', 'custom'], true) ? $period : 'month';
    $monthFilter = app_parse_month_year_filter($input);
    $month = $monthFilter['month_value'];
    $year = (int) ($input['year'] ?? date('Y'));
    $dateFrom = $input['date_from'] ?? date('Y-m-01');
    $dateTo = $input['date_to'] ?? date('Y-m-t');

    $where = ['t.user_id = ?'];
    $params = [];
    $titleRange = '';

    switch ($period) {
        case 'week':
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            $where[] = 't.work_date BETWEEN ? AND ?';
            $params[] = $weekStart;
            $params[] = $weekEnd;
            $titleRange = 'สัปดาห์ ' . app_format_thai_date($weekStart) . ' - ' . app_format_thai_date($weekEnd);
            break;
        case 'year':
            $where[] = 'YEAR(t.work_date) = ?';
            $params[] = $year;
            $titleRange = 'ปี ' . ($year + 543);
            break;
        case 'custom':
            $where[] = 't.work_date BETWEEN ? AND ?';
            $params[] = $dateFrom;
            $params[] = $dateTo;
            $titleRange = 'ช่วงวันที่ ' . app_format_thai_date($dateFrom) . ' - ' . app_format_thai_date($dateTo);
            break;
        default:
            $where[] = 'YEAR(t.work_date) = ? AND MONTH(t.work_date) = ?';
            $params[] = (int) $monthFilter['year_ce'];
            $params[] = (int) $monthFilter['month_number'];
            $titleRange = 'เดือน ' . app_format_thai_month_year($month);
            break;
    }

    return [
        'period' => $period,
        'month' => $month,
        'month_number' => $monthFilter['month_number'],
        'year_be' => $monthFilter['year_be'],
        'year_ce' => $monthFilter['year_ce'],
        'month_label_th' => $monthFilter['month_label_th'],
        'heading_month_year_th' => $monthFilter['heading_month_year_th'],
        'year' => $year,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'where_sql' => implode(' AND ', $where),
        'params' => $params,
        'title_range' => $titleRange,
    ];
}

function app_fetch_my_report_data(PDO $conn, int $userId, array $input): array
{
    $filters = app_normalize_my_report_filters($input);
    $params = array_merge([$userId], $filters['params']);

    $summaryStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_logs,
            COALESCE(SUM(work_hours), 0) AS total_hours,
            SUM(CASE WHEN checked_at IS NOT NULL THEN 1 ELSE 0 END) AS approved_logs,
            SUM(CASE WHEN " . app_time_log_pending_condition('t') . " THEN 1 ELSE 0 END) AS pending_logs
        FROM time_logs t
        WHERE {$filters['where_sql']}
    ");
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_logs' => 0,
        'total_hours' => 0,
        'approved_logs' => 0,
        'pending_logs' => 0,
    ];

    $logsStmt = $conn->prepare("
        SELECT t.*, d.department_name, u.fullname AS checker_name
        FROM time_logs t
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users u ON t.checked_by = u.id
        WHERE {$filters['where_sql']}
        ORDER BY t.work_date DESC, t.id DESC
    ");
    $logsStmt->execute($params);

    return [
        'filters' => $filters,
        'summary' => $summary,
        'logs' => $logsStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function app_fetch_departments(PDO $conn): array
{
    return $conn->query("SELECT id, department_name FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
}

function app_find_department_name(array $departments, int $departmentId): string
{
    foreach ($departments as $department) {
        if ((int) $department['id'] === $departmentId) {
            return (string) $department['department_name'];
        }
    }

    return '';
}

function app_get_current_user_department_id(PDO $conn): int
{
    $sessionDepartmentId = (int) ($_SESSION['department_id'] ?? 0);
    if ($sessionDepartmentId > 0) {
        return $sessionDepartmentId;
    }

    $userId = (int) ($_SESSION['id'] ?? 0);
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT department_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $departmentId = (int) $stmt->fetchColumn();
    if ($departmentId > 0) {
        $_SESSION['department_id'] = $departmentId;
    }

    return $departmentId;
}

function app_get_accessible_departments(PDO $conn): array
{
    $departments = app_fetch_departments($conn);
    if (app_can('can_view_all_staff') || app_can('can_manage_user_permissions')) {
        return [
            'departments' => $departments,
            'ids' => array_map(static fn(array $department): int => (int) $department['id'], $departments),
            'scope_label' => 'ทุกแผนกตามสิทธิ์ที่เข้าถึงได้',
            'is_global' => true,
        ];
    }

    $departmentId = app_get_current_user_department_id($conn);
    $filteredDepartments = array_values(array_filter(
        $departments,
        static fn(array $department): bool => (int) $department['id'] === $departmentId
    ));

    return [
        'departments' => $filteredDepartments,
        'ids' => $departmentId > 0 ? [$departmentId] : [],
        'scope_label' => $filteredDepartments
            ? 'แสดงข้อมูลในแผนก ' . $filteredDepartments[0]['department_name']
            : 'ไม่พบขอบเขตแผนกที่เข้าถึงได้',
        'is_global' => false,
    ];
}

function app_get_daily_schedule_departments(PDO $conn): array
{
    $departments = app_fetch_departments($conn);

    return [
        'departments' => $departments,
        'ids' => array_map(static fn(array $department): int => (int) $department['id'], $departments),
        'scope_label' => 'ทุกแผนกในระบบ',
        'is_global' => true,
        'is_operational_cross_department' => true,
    ];
}

function app_normalize_department_report_filters(PDO $conn, array $input): array
{
    $scope = app_get_accessible_departments($conn);
    $monthFilter = app_parse_month_year_filter($input);
    $month = $monthFilter['month_value'];

    $selectedDepartmentId = (int) ($input['department_id'] ?? 0);
    if ($selectedDepartmentId > 0 && !in_array($selectedDepartmentId, $scope['ids'], true)) {
        $selectedDepartmentId = 0;
    }

    $view = trim((string) ($input['view'] ?? 'table'));
    $view = in_array($view, ['cards', 'table'], true) ? $view : 'table';

    return [
        'month' => $month,
        'month_number' => $monthFilter['month_number'],
        'year_be' => $monthFilter['year_be'],
        'year_ce' => $monthFilter['year_ce'],
        'month_label_th' => $monthFilter['month_label_th'],
        'heading_month_year_th' => $monthFilter['heading_month_year_th'],
        'selected_department_id' => $selectedDepartmentId,
        'selected_department_name' => $selectedDepartmentId > 0
            ? app_find_department_name($scope['departments'], $selectedDepartmentId)
            : '',
        'view' => $view,
        'scope' => $scope,
        'scope_helper' => 'ค่าเริ่มต้นจะแสดงสรุปรายบุคคลทั้งหมดตามสิทธิ์ที่เข้าถึงได้',
    ];
}

function app_format_thai_month_year(string $month): string
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }

    [$yearNum, $monthNum] = array_map('intval', explode('-', $month));
    $months = app_thai_month_names();
    $monthName = $months[$monthNum] ?? date('F', strtotime($month . '-01'));

    return $monthName . ' ' . ($yearNum + 543);
}

function app_get_thai_month_options(?string $selectedMonth = null, int $monthsBack = 24, int $monthsForward = 3): array
{
    $selectedMonth = is_string($selectedMonth) && preg_match('/^\d{4}-\d{2}$/', $selectedMonth)
        ? $selectedMonth
        : date('Y-m');

    $baseMonth = DateTimeImmutable::createFromFormat('!Y-m', $selectedMonth) ?: new DateTimeImmutable(date('Y-m-01'));
    $months = [];

    for ($offset = -$monthsForward; $offset <= $monthsBack; $offset++) {
        $optionMonth = $baseMonth->modify(sprintf('%+d month', -$offset));
        if (!$optionMonth) {
            continue;
        }

        $value = $optionMonth->format('Y-m');
        $months[$value] = app_format_thai_month_year($value);
    }

    if (!isset($months[$selectedMonth])) {
        $months[$selectedMonth] = app_format_thai_month_year($selectedMonth);
    }

    krsort($months);

    return $months;
}

function app_get_department_report_heading_context(array $filters): array
{
    $selectedDepartmentName = trim((string) ($filters['selected_department_name'] ?? ''));
    $isSpecificDepartment = (int) ($filters['selected_department_id'] ?? 0) > 0 && $selectedDepartmentName !== '';
    $departmentLabel = $isSpecificDepartment ? 'แผนก ' . $selectedDepartmentName : 'แผนกทั้งหมด';
    $monthYearLabel = (string) ($filters['heading_month_year_th'] ?? app_format_thai_month_year((string) ($filters['month'] ?? date('Y-m'))));

    return [
        'department_label' => $departmentLabel,
        'month_year_label' => $monthYearLabel,
        'heading_text' => 'รายงานสรุป' . $departmentLabel . ' ประจำเดือน ' . $monthYearLabel,
        'subheading_text' => $isSpecificDepartment
            ? 'ข้อมูลสรุปของแผนกที่เลือกตามตัวกรองปัจจุบัน'
            : 'ข้อมูลสรุปของทุกแผนกตามสิทธิ์ที่เข้าถึงได้ในช่วงเวลาที่เลือก',
        'is_all_departments' => !$isSpecificDepartment,
    ];
}

function app_fetch_department_report_data(PDO $conn, array $input): array
{
    $filters = app_normalize_department_report_filters($conn, $input);
    $headingContext = app_get_department_report_heading_context($filters);
    $yearNum = (int) ($filters['year_ce'] ?? date('Y'));
    $monthNum = (int) ($filters['month_number'] ?? date('n'));
    $departmentIds = $filters['selected_department_id'] > 0
        ? [$filters['selected_department_id']]
        : $filters['scope']['ids'];

    if (!$departmentIds) {
        return [
            'filters' => $filters,
            'staff_rows' => [],
            'department_totals' => [
                'staff_count' => 0,
                'total_logs' => 0,
                'total_hours' => 0,
                'approved_logs' => 0,
                'pending_logs' => 0,
            ],
            'month_label' => $headingContext['month_year_label'],
            'scope_label' => $headingContext['department_label'],
            'heading_context' => $headingContext,
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($departmentIds), '?'));
    $params = array_merge([(int) $yearNum, (int) $monthNum], $departmentIds);

    $summaryStmt = $conn->prepare("
        SELECT
            u.id,
            u.fullname,
            COALESCE(u.position_name, '') AS position_name,
            d.department_name,
            COUNT(t.id) AS total_logs,
            COALESCE(SUM(t.work_hours), 0) AS total_hours,
            SUM(CASE WHEN t.checked_at IS NOT NULL THEN 1 ELSE 0 END) AS approved_logs
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN time_logs t
            ON t.user_id = u.id
            AND YEAR(t.work_date) = ?
            AND MONTH(t.work_date) = ?
        WHERE u.department_id IN ($placeholders)
        GROUP BY u.id, u.fullname, u.position_name, d.department_name
        ORDER BY u.fullname ASC
    ");
    $summaryStmt->execute($params);
    $staffRows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    $departmentTotals = [
        'staff_count' => count($staffRows),
        'total_logs' => 0,
        'total_hours' => 0.0,
        'approved_logs' => 0,
        'pending_logs' => 0,
    ];

    foreach ($staffRows as $row) {
        $totalLogs = (int) ($row['total_logs'] ?? 0);
        $approvedLogs = (int) ($row['approved_logs'] ?? 0);
        $departmentTotals['total_logs'] += $totalLogs;
        $departmentTotals['total_hours'] += (float) ($row['total_hours'] ?? 0);
        $departmentTotals['approved_logs'] += $approvedLogs;
        $departmentTotals['pending_logs'] += max(0, $totalLogs - $approvedLogs);
    }

    $scopeLabel = $filters['selected_department_id'] > 0 && $filters['selected_department_name'] !== ''
        ? 'แผนก ' . $filters['selected_department_name']
        : $filters['scope']['scope_label'];

    return [
        'filters' => $filters,
        'staff_rows' => $staffRows,
        'department_totals' => $departmentTotals,
        'month_label' => $headingContext['month_year_label'],
        'scope_label' => $headingContext['department_label'],
        'heading_context' => $headingContext,
    ];
}

function app_fetch_department_monthly_shift_matrix(PDO $conn, array $filters): array
{
    $yearCe = (int) ($filters['year_ce'] ?? date('Y'));
    $monthNumber = (int) ($filters['month_number'] ?? date('n'));
    $departmentIds = (int) ($filters['selected_department_id'] ?? 0) > 0
        ? [(int) $filters['selected_department_id']]
        : ($filters['scope']['ids'] ?? []);

    if (!$departmentIds) {
        return [
            'days' => app_build_daily_schedule_matrix_days($yearCe, $monthNumber),
            'rows' => [],
        ];
    }

    $days = app_build_daily_schedule_matrix_days($yearCe, $monthNumber);
    $placeholders = implode(', ', array_fill(0, count($departmentIds), '?'));

    $staffStmt = $conn->prepare("
        SELECT
            u.id AS user_id,
            u.fullname,
            COALESCE(u.position_name, '') AS position_name,
            d.department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.department_id IN ($placeholders)
        ORDER BY d.department_name ASC, u.fullname ASC
    ");
    $staffStmt->execute($departmentIds);
    $staffRows = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

    $logParams = array_merge([$yearCe, $monthNumber], $departmentIds);
    $logStmt = $conn->prepare("
        SELECT
            t.id,
            t.user_id,
            t.work_date,
            t.time_in,
            t.time_out,
            t.work_hours,
            t.note,
            t.approval_note,
            t.checked_by,
            t.checked_at,
            t.created_at,
            t.updated_at
        FROM time_logs t
        WHERE YEAR(t.work_date) = ?
          AND MONTH(t.work_date) = ?
          AND t.department_id IN ($placeholders)
          AND t.checked_at IS NOT NULL
        ORDER BY t.work_date ASC, t.time_in ASC, t.id ASC
    ");
    $logStmt->execute($logParams);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

    $logsByUserDay = [];
    foreach ($logs as $log) {
        $userId = (int) ($log['user_id'] ?? 0);
        $dayNumber = (int) date('j', strtotime((string) ($log['work_date'] ?? '')));
        if ($userId > 0 && $dayNumber > 0) {
            $logsByUserDay[$userId][$dayNumber][] = $log;
        }
    }

    $rows = [];
    foreach ($staffRows as $index => $staff) {
        $userId = (int) ($staff['user_id'] ?? 0);
        $dayCells = [];
        $rowShiftCount = 0;
        $rowTotalHours = 0.0;

        foreach ($days as $dayMeta) {
            $dayNumber = (int) $dayMeta['day'];
            if (!empty($dayMeta['is_future'])) {
                $dayCells[$dayNumber] = '';
                continue;
            }

            $dayLogs = $logsByUserDay[$userId][$dayNumber] ?? [];
            $dayResolution = app_get_department_monthly_matrix_day_resolution($dayLogs);
            $dayCells[$dayNumber] = $dayResolution['code'];
            $resolvedShiftCount = (int) ($dayResolution['counted_shifts'] ?? 0);
            $resolvedHours = (float) ($dayResolution['counted_hours'] ?? 0);
            $rowShiftCount += $resolvedShiftCount;
            $rowTotalHours += $resolvedHours;
        }

        $rows[] = [
            'row_number' => $index + 1,
            'user_id' => $userId,
            'fullname' => (string) ($staff['fullname'] ?? '-'),
            'position_name' => (string) ($staff['position_name'] ?? '-'),
            'department_name' => (string) ($staff['department_name'] ?? '-'),
            'day_cells' => $dayCells,
            'total_shifts' => (int) ($rowShiftCount ?? 0),
            'total_hours' => (float) ($rowTotalHours ?? 0),
            'ot_value' => '',
            'remark' => '',
        ];
    }

    return [
        'days' => $days,
        'rows' => $rows,
    ];
}

function app_normalize_shift_time(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/(\d{2}):(\d{2})/', $value, $matches)) {
        return $matches[1] . ':' . $matches[2];
    }

    return '';
}

function app_department_monthly_matrix_note_markers(array $row): array
{
    return [
        trim((string) ($row['note'] ?? '')),
        trim((string) ($row['approval_note'] ?? '')),
    ];
}

function app_is_bd_shift(array $row): bool
{
    foreach (app_department_monthly_matrix_note_markers($row) as $noteText) {
        if ($noteText === '') {
            continue;
        }

        $normalized = mb_strtolower($noteText, 'UTF-8');
        if ($normalized === 'bd' || preg_match('/\bbd\b/i', $noteText)) {
            return true;
        }

        if (str_contains($normalized, 'เวรบ่ายนอกเวลาราชการ')) {
            return true;
        }
    }

    return false;
}

function app_match_shift_abbreviation(array $row): string
{
    if (!empty($row['checked_at']) === false) {
        return '';
    }

    if (app_is_bd_shift($row)) {
        return 'BD';
    }

    $timeIn = app_normalize_shift_time($row['time_in'] ?? null);
    $timeOut = app_normalize_shift_time($row['time_out'] ?? null);

    return match ($timeIn . '|' . $timeOut) {
        '08:30|16:30' => 'ช',
        '16:30|00:30' => 'บ',
        '00:30|08:30' => 'ด',
        default => '',
    };
}

function app_get_department_monthly_matrix_day_code(array $recordsForOneDay): string
{
    return app_get_department_monthly_matrix_day_resolution($recordsForOneDay)['code'];
}

function app_get_department_monthly_matrix_day_resolution(array $recordsForOneDay): array
{
    if (!$recordsForOneDay) {
        return [
            'code' => '',
            'counted_shifts' => 0,
            'counted_hours' => 0.0,
            'remark' => '',
        ];
    }

    $hasExplicitBd = false;
    $bdHours = 0.0;
    $standardMatches = [];

    foreach ($recordsForOneDay as $row) {
        if (empty($row['checked_at'])) {
            continue;
        }

        if (app_is_bd_shift($row)) {
            $hasExplicitBd = true;
            $bdHours += (float) ($row['work_hours'] ?? 0);
            continue;
        }

        $code = app_match_shift_abbreviation($row);
        if (in_array($code, ['ช', 'บ', 'ด'], true)) {
            $standardMatches[] = [
                'code' => $code,
                'hours' => (float) ($row['work_hours'] ?? 0),
            ];
        }
    }

    if ($hasExplicitBd) {
        return [
            'code' => 'BD',
            'counted_shifts' => 1,
            'counted_hours' => $bdHours > 0 ? $bdHours : 0.0,
            'remark' => '',
        ];
    }

    $uniqueStandardCodes = array_values(array_unique(array_column($standardMatches, 'code')));
    if (count($uniqueStandardCodes) === 1) {
        return [
            'code' => $uniqueStandardCodes[0],
            'counted_shifts' => 1,
            'counted_hours' => array_sum(array_column($standardMatches, 'hours')),
            'remark' => '',
        ];
    }

    return [
        'code' => '',
        'counted_shifts' => 0,
        'counted_hours' => 0.0,
        'remark' => '',
    ];
}

function app_build_scoped_time_log_filters(PDO $conn, array $input, string $defaultStatus = 'pending'): array
{
    $filters = app_build_time_log_filters($input, $defaultStatus);
    $scope = app_get_accessible_departments($conn);

    if (!$scope['ids']) {
        $filters['where_sql'] .= ' AND 1 = 0';
        $filters['scope'] = $scope;
        return $filters;
    }

    if ($filters['department'] !== '') {
        $selectedDepartmentId = (int) $filters['department'];
        if (!in_array($selectedDepartmentId, $scope['ids'], true)) {
            $filters['where_sql'] .= ' AND 1 = 0';
        }
    } else {
        $placeholders = implode(', ', array_fill(0, count($scope['ids']), '?'));
        $filters['where_sql'] .= " AND t.department_id IN ($placeholders)";
        foreach ($scope['ids'] as $departmentId) {
            $filters['params'][] = $departmentId;
        }
    }

    $filters['scope'] = $scope;

    return $filters;
}

function app_time_log_status_meta(array $timeLog): array
{
    $isLocked = app_time_log_is_locked($timeLog);

    return [
        'is_locked' => $isLocked,
        'label' => $isLocked ? 'อนุมัติแล้ว' : 'รอตรวจ',
        'class' => $isLocked ? 'success' : 'warning',
        'lock_label' => $isLocked ? 'ล็อกแล้ว' : '',
    ];
}

function app_build_approval_query_string(array $filters, array $overrides = []): string
{
    $query = array_merge([
        'name' => $filters['name'] ?? '',
        'position_name' => $filters['position_name'] ?? '',
        'department' => $filters['department'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'status' => $filters['status'] ?? 'pending',
        'view' => $overrides['view'] ?? null,
        'p' => $overrides['p'] ?? null,
        'type' => $overrides['type'] ?? null,
        'download' => $overrides['download'] ?? null,
    ], $overrides);

    return http_build_query(array_filter($query, static fn($value) => $value !== '' && $value !== null));
}

function app_manage_time_logs_status_meta(array $row): array
{
    $locked = !empty($row['checked_at']);

    return [
        'label' => $locked ? 'อนุมัติแล้ว' : 'รอตรวจ',
        'badge' => $locked ? 'success' : 'warning',
        'lock' => $locked ? 'ล็อกแล้ว' : '',
    ];
}

function app_build_manage_time_logs_query_string(array $filters, array $overrides = []): string
{
    $query = array_merge([
        'name' => $filters['name'] ?? '',
        'position_name' => $filters['position_name'] ?? '',
        'department' => $filters['department'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'status' => $filters['status'] ?? 'all',
        'p' => $overrides['p'] ?? null,
        'type' => $overrides['type'] ?? null,
        'download' => $overrides['download'] ?? null,
    ], $overrides);

    return http_build_query(array_filter($query, static fn($value) => $value !== '' && $value !== null));
}

function app_daily_schedule_status_options(): array
{
    return [
        'all' => 'ทั้งหมด',
        'checked' => 'อนุมัติแล้ว',
        'pending' => 'รอตรวจ',
        'other' => 'เวลาอื่นๆ/สถานะอื่น',
    ];
}

function app_daily_schedule_status_label(string $status): string
{
    $options = app_daily_schedule_status_options();

    return $options[$status] ?? $options['all'];
}

function app_daily_schedule_mode_options(): array
{
    return [
        'daily' => 'รายวัน',
        'monthly' => 'รายเดือน',
    ];
}

function app_daily_shift_groups(): array
{
    return [
        'morning' => ['label' => 'เวรเช้า', 'class' => 'shift-group-morning', 'time_in' => '08:30', 'time_out' => '16:30'],
        'afternoon' => ['label' => 'เวรบ่าย', 'class' => 'shift-group-afternoon', 'time_in' => '16:30', 'time_out' => '00:30'],
        'night' => ['label' => 'เวรดึก', 'class' => 'shift-group-night', 'time_in' => '00:30', 'time_out' => '08:30'],
        'other' => ['label' => 'เวลาอื่นๆ', 'class' => 'shift-group-other', 'time_in' => null, 'time_out' => null],
    ];
}

function app_format_daily_shift_time_label(?string $time): ?string
{
    if ($time === null || $time === '') {
        return null;
    }

    return str_replace(':', '.', $time) . ' น.';
}

function app_get_daily_schedule_heading_context(array $schedule): array
{
    $mode = (string) ($schedule['mode'] ?? 'daily');
    $selectedDate = (string) ($schedule['selected_date'] ?? date('Y-m-d'));
    $selectedDepartment = trim((string) ($schedule['selected_department'] ?? ''));
    $departmentOptions = $schedule['department_options'] ?? [];
    $departmentName = $selectedDepartment !== ''
        ? app_find_department_name($departmentOptions, (int) $selectedDepartment)
        : '';
    $departmentLabel = $departmentName !== '' ? 'แผนก ' . $departmentName : 'ทุกแผนก';
    $periodLabel = $mode === 'monthly'
        ? (string) ($schedule['heading_month_year_th'] ?? app_format_thai_month_year(sprintf(
            '%04d-%02d',
            (int) ($schedule['year_ce'] ?? date('Y')),
            (int) ($schedule['month_number'] ?? date('n'))
        )))
        : app_format_thai_date($selectedDate);
    $mainHeading = $mode === 'monthly'
        ? 'รายงานเวรประจำเดือน ' . $departmentLabel . ' ประจำเดือน ' . $periodLabel
        : 'รายงานเวรประจำวัน ' . $departmentLabel . ' ประจำวันที่ ' . $periodLabel;
    $tableContextLabel = $mode === 'monthly'
        ? 'ตารางสรุปเวรประจำเดือน ' . $departmentLabel
        : 'ตารางเวรประจำวันที่เลือก ' . $departmentLabel;

    return [
        'mode' => $mode,
        'selected_date' => $selectedDate,
        'date_heading_th' => $periodLabel,
        'department_name' => $departmentName,
        'department_label' => $departmentLabel,
        'scope_label' => $departmentName !== '' ? 'แสดงข้อมูลเฉพาะแผนก ' . $departmentName : 'ทุกแผนกในระบบ',
        'main_heading' => $mainHeading,
        'table_context_label' => $tableContextLabel,
        'period_label' => $periodLabel,
    ];
}

function app_get_daily_shift_group_display_meta(string $groupKey, int $itemCount): array
{
    $groups = app_daily_shift_groups();
    $meta = $groups[$groupKey] ?? $groups['other'];
    $timeRangeLabel = null;

    if (!empty($meta['time_in']) && !empty($meta['time_out'])) {
        $timeRangeLabel = 'เวลา ' . app_format_daily_shift_time_label($meta['time_in']) . ' - ' . app_format_daily_shift_time_label($meta['time_out']);
    }

    $headingParts = [$meta['label'], $itemCount . ' รายการ'];
    if ($timeRangeLabel !== null) {
        $headingParts[] = $timeRangeLabel;
    }

    return [
        'label' => $meta['label'],
        'class' => $meta['class'],
        'item_count' => $itemCount,
        'time_range_label' => $timeRangeLabel,
        'heading_text' => implode(' / ', $headingParts),
    ];
}

function app_time_log_clock_value(?string $dateTime): string
{
    if (!$dateTime) {
        return '';
    }

    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return '';
    }

    return date('H:i', $timestamp);
}

function app_match_daily_shift_group(array $row): string
{
    $timeIn = app_time_log_clock_value((string) ($row['time_in'] ?? ''));
    $timeOut = app_time_log_clock_value((string) ($row['time_out'] ?? ''));

    foreach (['morning', 'afternoon', 'night'] as $groupKey) {
        $group = app_daily_shift_groups()[$groupKey];
        if ($timeIn === $group['time_in'] && $timeOut === $group['time_out']) {
            return $groupKey;
        }
    }

    return 'other';
}

function app_group_daily_schedule_rows_by_shift(array $rows): array
{
    $groups = app_daily_shift_groups();
    $grouped = [];

    foreach ($groups as $key => $meta) {
        $grouped[$key] = [
            'key' => $key,
            'label' => $meta['label'],
            'class' => $meta['class'],
            'rows' => [],
        ];
    }

    foreach ($rows as $row) {
        $groupKey = app_match_daily_shift_group($row);
        $grouped[$groupKey]['rows'][] = $row;
    }

    $visibleGroups = array_values(array_filter($grouped, static fn(array $group): bool => !empty($group['rows'])));

    foreach ($visibleGroups as &$group) {
        $displayMeta = app_get_daily_shift_group_display_meta((string) $group['key'], count($group['rows']));
        $group['item_count'] = $displayMeta['item_count'];
        $group['time_range_label'] = $displayMeta['time_range_label'];
        $group['heading_text'] = $displayMeta['heading_text'];
    }
    unset($group);

    return $visibleGroups;
}

function app_daily_schedule_clock_value(array $row, string $key): string
{
    return app_time_log_clock_value((string) ($row[$key] ?? ''));
}

function app_daily_schedule_time_window(array $row): array
{
    $date = substr((string) ($row['work_date'] ?? ''), 0, 10);
    $timeIn = app_daily_schedule_clock_value($row, 'time_in');
    $timeOut = app_daily_schedule_clock_value($row, 'time_out');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $timeIn === '' || $timeOut === '') {
        return [null, null];
    }

    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Bangkok');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $timeIn, $timezone) ?: null;
    $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $timeOut, $timezone) ?: null;

    if (!$start || !$end) {
        return [null, null];
    }

    if ($end <= $start) {
        $end = $end->modify('+1 day');
    }

    return [$start, $end];
}

function app_daily_schedule_runtime_status(array $row, ?DateTimeImmutable $now = null): array
{
    [$start, $end] = app_daily_schedule_time_window($row);
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Bangkok');
    $now = $now ?? new DateTimeImmutable('now', $timezone);

    if (!$start || !$end) {
        return [
            'key' => 'pending',
            'label' => 'รอจัดสรร',
            'class' => 'warning',
        ];
    }

    if ($now < $start) {
        return [
            'key' => 'upcoming',
            'label' => 'กำลังจะเริ่ม',
            'class' => 'warning',
        ];
    }

    if ($now >= $start && $now < $end) {
        return [
            'key' => 'active',
            'label' => 'ปฏิบัติงานอยู่',
            'class' => 'success',
        ];
    }

    return [
        'key' => 'completed',
        'label' => 'ครบเวรแล้ว',
        'class' => 'complete',
    ];
}

function app_daily_schedule_shift_bucket(array $row): string
{
    $matched = app_match_daily_shift_group($row);
    if (in_array($matched, ['morning', 'afternoon', 'night'], true)) {
        return $matched;
    }

    $timeIn = app_daily_schedule_clock_value($row, 'time_in');
    if ($timeIn === '') {
        return 'other';
    }

    $hour = (int) substr($timeIn, 0, 2);
    if ($hour >= 5 && $hour < 12) {
        return 'morning';
    }

    if ($hour >= 12 && $hour < 22) {
        return 'afternoon';
    }

    return 'night';
}

function app_daily_schedule_derived_stats(array $schedule): array
{
    $rows = array_values($schedule['logs'] ?? []);
    $total = count($rows);
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Bangkok');
    $now = new DateTimeImmutable('now', $timezone);
    $shiftStats = [
        'morning' => ['label' => 'เช้า (08:00 - 16:00)', 'count' => 0, 'active' => 0, 'icon' => 'bi-sun', 'tone' => 'green'],
        'afternoon' => ['label' => 'บ่าย (16:00 - 00:00)', 'count' => 0, 'active' => 0, 'icon' => 'bi-sun-fill', 'tone' => 'amber'],
        'night' => ['label' => 'ดึก (00:00 - 08:00)', 'count' => 0, 'active' => 0, 'icon' => 'bi-moon-stars', 'tone' => 'violet'],
    ];
    $statusCounts = [
        'active' => 0,
        'completed' => 0,
        'upcoming' => 0,
        'pending' => 0,
    ];
    $departmentCounts = [];
    $latestStart = null;

    foreach ($rows as $row) {
        $status = app_daily_schedule_runtime_status($row, $now);
        $statusKey = (string) ($status['key'] ?? 'pending');
        if (!isset($statusCounts[$statusKey])) {
            $statusKey = 'pending';
        }
        $statusCounts[$statusKey]++;

        $bucket = app_daily_schedule_shift_bucket($row);
        if (isset($shiftStats[$bucket])) {
            $shiftStats[$bucket]['count']++;
            if ($statusKey === 'active') {
                $shiftStats[$bucket]['active']++;
            }
        }

        $departmentName = trim((string) ($row['department_name'] ?? ''));
        if ($departmentName !== '') {
            $departmentCounts[$departmentName] = ($departmentCounts[$departmentName] ?? 0) + 1;
        }

        [$start] = app_daily_schedule_time_window($row);
        if ($start && (!$latestStart || $start > $latestStart)) {
            $latestStart = $start;
        }
    }

    arsort($departmentCounts);
    $topDepartmentName = $departmentCounts ? (string) array_key_first($departmentCounts) : '-';
    $topDepartmentCount = $departmentCounts ? (int) reset($departmentCounts) : 0;
    $startedOrDone = $statusCounts['active'] + $statusCounts['completed'];
    $progress = $total > 0 ? min(100, round(($startedOrDone / $total) * 100, 1)) : 0.0;

    foreach ($shiftStats as &$shift) {
        $shift['percent'] = $shift['count'] > 0 ? round(($shift['active'] / $shift['count']) * 100) : 0;
    }
    unset($shift);

    return [
        'total' => $total,
        'active' => $statusCounts['active'],
        'completed' => $statusCounts['completed'],
        'pending' => $statusCounts['upcoming'] + $statusCounts['pending'],
        'upcoming' => $statusCounts['upcoming'],
        'unassigned' => $statusCounts['pending'],
        'shift_stats' => $shiftStats,
        'top_department_name' => $topDepartmentName,
        'top_department_count' => $topDepartmentCount,
        'top_department_percent' => $total > 0 ? round(($topDepartmentCount / $total) * 100, 1) : 0.0,
        'latest_time_label' => $latestStart ? $latestStart->format('H:i') . ' น.' : $now->format('H:i') . ' น.',
        'updated_time_label' => $now->format('H:i') . ' น.',
        'progress' => $progress,
    ];
}

function app_daily_schedule_scope_filter_sql(string $selectedDepartment, array $scope, array &$params, string $departmentColumn = 't.department_id'): string
{
    if (!$scope['ids']) {
        return ' AND 1 = 0';
    }

    if ($selectedDepartment !== '') {
        if (in_array((int) $selectedDepartment, $scope['ids'], true)) {
            $params[] = (int) $selectedDepartment;

            return " AND {$departmentColumn} = ?";
        }

        return ' AND 1 = 0';
    }

    $placeholders = implode(', ', array_fill(0, count($scope['ids']), '?'));
    foreach ($scope['ids'] as $departmentId) {
        $params[] = (int) $departmentId;
    }

    return " AND {$departmentColumn} IN ({$placeholders})";
}

function app_daily_schedule_row_matches_review_status(array $row, string $reviewStatus): bool
{
    if ($reviewStatus === 'checked') {
        return !empty($row['checked_at']);
    }

    if ($reviewStatus === 'pending') {
        return empty($row['checked_at']) && empty($row['checked_by']);
    }

    if ($reviewStatus === 'other') {
        return !empty($row['checked_by']) && empty($row['checked_at']);
    }

    return true;
}

function app_get_daily_schedule_shift_code(array $row): string
{
    $noteParts = [
        mb_strtolower(trim((string) ($row['note'] ?? '')), 'UTF-8'),
        mb_strtolower(trim((string) ($row['approval_note'] ?? '')), 'UTF-8'),
    ];

    foreach ($noteParts as $noteText) {
        if ($noteText === '') {
            continue;
        }

        if (str_contains($noteText, 'bd') || str_contains($noteText, 'เวรบ่ายนอกเวลาราชการ')) {
            return 'BD';
        }
    }

    return match (app_match_daily_shift_group($row)) {
        'morning' => 'ช',
        'afternoon' => 'บ',
        'night' => 'ด',
        default => '',
    };
}

function app_daily_schedule_shift_code_priority(string $code): int
{
    return match ($code) {
        'BD' => 40,
        'ช' => 30,
        'บ' => 20,
        'ด' => 10,
        default => 0,
    };
}

function app_resolve_daily_schedule_shift_codes(array $codes): string
{
    $codes = array_values(array_unique(array_filter(array_map(
        static fn($code): string => trim((string) $code),
        $codes
    ))));

    if (!$codes) {
        return '';
    }

    usort($codes, static function (string $left, string $right): int {
        return app_daily_schedule_shift_code_priority($right) <=> app_daily_schedule_shift_code_priority($left);
    });

    return implode('/', $codes);
}

function app_build_daily_schedule_matrix_days(int $yearCe, int $monthNumber): array
{
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNumber, $yearCe);
    $todayYearCe = (int) date('Y');
    $todayMonth = (int) date('n');
    $todayDay = (int) date('j');
    $isCurrentMonth = $todayYearCe === $yearCe && $todayMonth === $monthNumber;
    $days = [];

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $days[] = [
            'day' => $day,
            'date' => sprintf('%04d-%02d-%02d', $yearCe, $monthNumber, $day),
            'is_future' => $isCurrentMonth && $day > $todayDay,
        ];
    }

    return $days;
}

function app_fetch_daily_schedule_monthly_matrix(PDO $conn, array $baseSchedule, array $scope): array
{
    $yearCe = (int) ($baseSchedule['year_ce'] ?? date('Y'));
    $monthNumber = (int) ($baseSchedule['month_number'] ?? date('n'));
    $selectedDepartment = (string) ($baseSchedule['selected_department'] ?? '');
    $name = trim((string) ($baseSchedule['name'] ?? ''));
    $reviewStatus = (string) ($baseSchedule['review_status'] ?? 'all');
    $days = app_build_daily_schedule_matrix_days($yearCe, $monthNumber);

    $staffSql = "
        SELECT
            u.id AS user_id,
            u.fullname,
            COALESCE(u.position_name, '') AS position_name,
            COALESCE(u.phone_number, '') AS phone_number,
            d.department_name,
            d.id AS department_id
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE 1 = 1
    ";
    $staffParams = [];
    $staffSql .= app_daily_schedule_scope_filter_sql($selectedDepartment, $scope, $staffParams, 'u.department_id');

    if ($name !== '') {
        $staffSql .= " AND u.fullname LIKE ?";
        $staffParams[] = '%' . $name . '%';
    }

    $staffSql .= " ORDER BY d.department_name ASC, u.fullname ASC";
    $staffStmt = $conn->prepare($staffSql);
    $staffStmt->execute($staffParams);
    $staffRows = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

    $logSql = "
        SELECT
            t.id,
            t.user_id,
            t.work_date,
            t.time_in,
            t.time_out,
            t.work_hours,
            t.note,
            t.approval_note,
            t.checked_by,
            t.checked_at,
            t.status,
            COALESCE(u.fullname, '') AS fullname,
            COALESCE(u.position_name, '') AS position_name,
            COALESCE(u.phone_number, '') AS phone_number,
            d.department_name,
            d.id AS department_id
        FROM time_logs t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE YEAR(t.work_date) = ?
          AND MONTH(t.work_date) = ?
    ";
    $logParams = [$yearCe, $monthNumber];
    $logSql .= app_daily_schedule_scope_filter_sql($selectedDepartment, $scope, $logParams, 't.department_id');

    if ($name !== '') {
        $logSql .= " AND u.fullname LIKE ?";
        $logParams[] = '%' . $name . '%';
    }

    if ($reviewStatus === 'checked') {
        $logSql .= " AND " . app_time_log_checked_condition('t');
    } elseif ($reviewStatus === 'pending') {
        $logSql .= " AND " . app_time_log_pending_condition('t');
    } elseif ($reviewStatus === 'other') {
        $logSql .= " AND NOT (" . app_time_log_checked_condition('t') . ") AND NOT (" . app_time_log_pending_condition('t') . ")";
    }

    $logSql .= " ORDER BY t.work_date ASC, t.time_in ASC, t.id ASC";
    $logStmt = $conn->prepare($logSql);
    $logStmt->execute($logParams);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

    $logsByUserDay = [];
    $approvedCount = 0;
    $totalHours = 0.0;
    $departments = [];

    foreach ($logs as $log) {
        $workDate = (string) ($log['work_date'] ?? '');
        $dayNumber = (int) date('j', strtotime($workDate));
        $userId = (int) ($log['user_id'] ?? 0);
        if ($userId > 0 && $dayNumber > 0) {
            $logsByUserDay[$userId][$dayNumber][] = $log;
        }

        if (!empty($log['checked_at'])) {
            $approvedCount++;
        }

        $totalHours += (float) ($log['work_hours'] ?? 0);

        if (!empty($log['department_name'])) {
            $departments[(string) $log['department_name']] = true;
        }
    }

    if ($reviewStatus !== 'all') {
        $eligibleUserIds = array_fill_keys(array_map('intval', array_keys($logsByUserDay)), true);
        $staffRows = array_values(array_filter($staffRows, static function (array $staff) use ($eligibleUserIds): bool {
            return isset($eligibleUserIds[(int) ($staff['user_id'] ?? 0)]);
        }));
    }

    $matrixRows = [];

    foreach ($staffRows as $index => $staff) {
        $userId = (int) ($staff['user_id'] ?? 0);
        $dayCells = [];

        foreach ($days as $dayMeta) {
            $dayNumber = (int) $dayMeta['day'];
            if (!empty($dayMeta['is_future'])) {
                $dayCells[$dayNumber] = '';
                continue;
            }

            $dayLogs = $logsByUserDay[$userId][$dayNumber] ?? [];
            $codes = [];
            foreach ($dayLogs as $dayLog) {
                if (!app_daily_schedule_row_matches_review_status($dayLog, $reviewStatus)) {
                    continue;
                }

                $codes[] = app_get_daily_schedule_shift_code($dayLog);
            }

            $dayCells[$dayNumber] = app_resolve_daily_schedule_shift_codes($codes);
        }

        $matrixRows[] = [
            'row_number' => $index + 1,
            'user_id' => $userId,
            'fullname' => (string) ($staff['fullname'] ?? '-'),
            'position_name' => (string) ($staff['position_name'] ?? '-'),
            'department_name' => (string) ($staff['department_name'] ?? '-'),
            'phone_number' => (string) ($staff['phone_number'] ?? ''),
            'day_cells' => $dayCells,
        ];

        if (!empty($staff['department_name'])) {
            $departments[(string) $staff['department_name']] = true;
        }
    }

    return [
        'logs' => $logs,
        'grouped_logs' => [],
        'matrix_rows' => $matrixRows,
        'matrix_days' => $days,
        'total_rows' => count($logs),
        'unique_staff_count' => count($staffRows),
        'department_count' => count($departments),
        'approved_count' => $approvedCount,
        'total_hours' => $totalHours,
    ];
}

function app_fetch_daily_schedule_data(PDO $conn, array $input): array
{
    $scope = app_get_daily_schedule_departments($conn);
    $modeOptions = app_daily_schedule_mode_options();
    $mode = trim((string) ($input['mode'] ?? 'daily'));
    $mode = array_key_exists($mode, $modeOptions) ? $mode : 'daily';
    $selectedDate = $input['date'] ?? date('Y-m-d');
    $selectedDepartment = trim((string) ($input['department'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $reviewStatus = trim((string) ($input['review_status'] ?? 'all'));
    $reviewStatus = array_key_exists($reviewStatus, app_daily_schedule_status_options()) ? $reviewStatus : 'all';
    $monthFilter = app_parse_month_year_filter($input);

    $baseSchedule = [
        'mode' => $mode,
        'mode_label' => $modeOptions[$mode],
        'selected_date' => $selectedDate,
        'selected_department' => $selectedDepartment,
        'department_options' => $scope['departments'],
        'name' => $name,
        'review_status' => $reviewStatus,
        'review_status_label' => app_daily_schedule_status_label($reviewStatus),
        'month_number' => $monthFilter['month_number'],
        'month' => $monthFilter['month_value'],
        'year_be' => $monthFilter['year_be'],
        'year_ce' => $monthFilter['year_ce'],
        'month_label_th' => $monthFilter['month_label_th'],
        'heading_month_year_th' => $monthFilter['heading_month_year_th'],
        'date_label' => app_format_thai_date($selectedDate, true),
    ];

    if ($mode === 'monthly') {
        $monthlyData = app_fetch_daily_schedule_monthly_matrix($conn, $baseSchedule, $scope);
        $schedule = array_merge($baseSchedule, $monthlyData);
        $headingContext = app_get_daily_schedule_heading_context($schedule);

        return $schedule + [
            'heading_context' => $headingContext,
            'date_heading' => $headingContext['main_heading'],
            'scope_label' => $headingContext['scope_label'],
        ];
    }

    $sql = "
        SELECT
            t.id,
            t.work_date,
            t.time_in,
            t.time_out,
            t.work_hours,
            t.note,
            t.status,
            t.checked_by,
            t.checked_at,
            t.approval_note,
            t.user_id,
            u.fullname,
            COALESCE(u.position_name, '') AS position_name,
            COALESCE(u.phone_number, '') AS phone_number,
            d.department_name,
            d.id AS department_id
        FROM time_logs t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE t.work_date = ?
    ";
    $params = [$selectedDate];
    $sql .= app_daily_schedule_scope_filter_sql($selectedDepartment, $scope, $params);

    if ($name !== '') {
        $sql .= " AND u.fullname LIKE ?";
        $params[] = '%' . $name . '%';
    }

    if ($reviewStatus === 'checked') {
        $sql .= " AND " . app_time_log_checked_condition('t');
    } elseif ($reviewStatus === 'pending') {
        $sql .= " AND " . app_time_log_pending_condition('t');
    } elseif ($reviewStatus === 'other') {
        $sql .= " AND NOT (" . app_time_log_checked_condition('t') . ") AND NOT (" . app_time_log_pending_condition('t') . ")";
    }

    $sql .= "
        ORDER BY
            CASE WHEN t.time_in IS NULL THEN 1 ELSE 0 END,
            t.time_in ASC,
            u.fullname ASC
    ";

    $logsStmt = $conn->prepare($sql);
    $logsStmt->execute($params);
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

    $uniqueStaff = [];
    $departments = [];
    $approvedCount = 0;
    $totalHours = 0.0;

    foreach ($logs as $log) {
        $uniqueStaff[$log['fullname'] ?? ''] = true;
        if (!empty($log['department_name'])) {
            $departments[$log['department_name']] = true;
        }
        if (!empty($log['checked_at'])) {
            $approvedCount++;
        }
        $totalHours += (float) ($log['work_hours'] ?? 0);
    }

    $dailySchedule = [
        'logs' => $logs,
        'total_rows' => count($logs),
        'grouped_logs' => app_group_daily_schedule_rows_by_shift($logs),
        'matrix_rows' => [],
        'matrix_days' => [],
        'unique_staff_count' => count($uniqueStaff),
        'department_count' => count($departments),
        'approved_count' => $approvedCount,
        'total_hours' => $totalHours,
    ];
    $schedule = array_merge($baseSchedule, $dailySchedule);
    $headingContext = app_get_daily_schedule_heading_context($schedule);

    return $schedule + [
        'heading_context' => $headingContext,
        'date_heading' => $headingContext['main_heading'],
        'scope_label' => $headingContext['scope_label'],
    ];
}

function app_find_overlapping_time_log(PDO $conn, int $userId, string $startDateTime, string $endDateTime, ?int $excludeId = null): ?array
{
    $sql = "
        SELECT id, work_date, time_in, time_out, work_hours, note
        FROM time_logs
        WHERE user_id = ?
          AND time_in IS NOT NULL
          AND time_out IS NOT NULL
          AND time_out > ?
          AND time_in < ?
    ";

    $params = [$userId, $startDateTime, $endDateTime];
    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }

    $sql .= " ORDER BY time_in ASC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_collect_user_time_flags(array $logs): array
{
    $flags = [];
    $sortedLogs = $logs;

    usort($sortedLogs, static function (array $a, array $b): int {
        $aTime = !empty($a['time_in']) ? strtotime((string) $a['time_in']) : PHP_INT_MAX;
        $bTime = !empty($b['time_in']) ? strtotime((string) $b['time_in']) : PHP_INT_MAX;

        return $aTime <=> $bTime;
    });

    $activeRanges = [];

    foreach ($sortedLogs as $row) {
        $id = (int) ($row['id'] ?? 0);
        $timeIn = $row['time_in'] ?? null;
        $timeOut = $row['time_out'] ?? null;

        $flags[$id] = [
            'incomplete' => empty($timeIn) || empty($timeOut),
            'overlap' => false,
        ];

        if (empty($timeIn) || empty($timeOut)) {
            continue;
        }

        $start = strtotime((string) $timeIn);
        $end = strtotime((string) $timeOut);
        if ($end !== false && $start !== false && $end < $start) {
            $end = strtotime('+1 day', $end);
        }

        foreach ($activeRanges as $range) {
            if ($start < $range['end'] && $end > $range['start']) {
                $flags[$id]['overlap'] = true;
                $flags[$range['id']]['overlap'] = true;
            }
        }

        $activeRanges[] = [
            'id' => $id,
            'start' => $start,
            'end' => $end,
        ];
    }

    return $flags;
}

function app_build_time_log_filters(array $input, string $defaultStatus = 'pending'): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $position = trim((string) ($input['position_name'] ?? ''));
    $department = trim((string) ($input['department'] ?? ''));
    $dateFrom = trim((string) ($input['date_from'] ?? ''));
    $dateTo = trim((string) ($input['date_to'] ?? ''));
    $status = trim((string) ($input['status'] ?? $defaultStatus));
    $allowedStatuses = ['pending', 'checked', 'all'];
    $status = in_array($status, $allowedStatuses, true) ? $status : $defaultStatus;

    $where = ['t.time_out IS NOT NULL'];
    $params = [];

    if ($name !== '') {
        $where[] = 'u.fullname LIKE ?';
        $params[] = '%' . $name . '%';
    }

    if ($position !== '') {
        $where[] = 'COALESCE(u.position_name, \'\') LIKE ?';
        $params[] = '%' . $position . '%';
    }

    if ($department !== '') {
        $where[] = 't.department_id = ?';
        $params[] = (int) $department;
    }

    if ($dateFrom !== '') {
        $where[] = 't.work_date >= ?';
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $where[] = 't.work_date <= ?';
        $params[] = $dateTo;
    }

    if ($status === 'pending') {
        $where[] = app_time_log_pending_condition('t');
    } elseif ($status === 'checked') {
        $where[] = app_time_log_checked_condition('t');
    }

    return [
        'name' => $name,
        'position_name' => $position,
        'department' => $department,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'status' => $status,
        'where_sql' => implode(' AND ', $where),
        'params' => $params,
    ];
}

function app_get_manage_time_logs(PDO $conn, array $filters, int $limit, int $offset): array
{
    $sql = "
        SELECT
            t.*,
            u.fullname,
            u.position_name,
            d.department_name,
            c.fullname AS checker_name
        FROM time_logs t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users c ON t.checked_by = c.id
        WHERE {$filters['where_sql']}
        ORDER BY t.work_date DESC, t.id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $index = 1;
    foreach ($filters['params'] as $param) {
        $stmt->bindValue($index, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $index++;
    }
    $stmt->bindValue($index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($index, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_count_manage_time_logs(PDO $conn, array $filters): int
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM time_logs t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE {$filters['where_sql']}
    ");
    $stmt->execute($filters['params']);

    return (int) $stmt->fetchColumn();
}

function app_get_approval_queue_rows(PDO $conn, array $filters, int $limit, int $offset): array
{
    return app_get_manage_time_logs($conn, $filters, $limit, $offset);
}

function app_get_manage_time_logs_all(PDO $conn, array $filters): array
{
    $sql = "
        SELECT
            t.*,
            u.fullname,
            u.position_name,
            d.department_name,
            c.fullname AS checker_name
        FROM time_logs t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users c ON t.checked_by = c.id
        WHERE {$filters['where_sql']}
        ORDER BY t.work_date DESC, t.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($filters['params']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_summarize_time_log_rows(array $rows): array
{
    $uniqueStaff = [];
    $uniqueDepartments = [];
    $pendingCount = 0;
    $checkedCount = 0;
    $totalHours = 0.0;

    foreach ($rows as $row) {
        $status = app_time_log_status_meta($row);
        $totalHours += (float) ($row['work_hours'] ?? 0);

        if (!empty($row['fullname'])) {
            $uniqueStaff[(string) $row['fullname']] = true;
        }
        if (!empty($row['department_name'])) {
            $uniqueDepartments[(string) $row['department_name']] = true;
        }

        if ($status['is_locked']) {
            $checkedCount++;
        } else {
            $pendingCount++;
        }
    }

    return [
        'total_rows' => count($rows),
        'unique_staff_count' => count($uniqueStaff),
        'unique_department_count' => count($uniqueDepartments),
        'pending_count' => $pendingCount,
        'checked_count' => $checkedCount,
        'total_hours' => $totalHours,
        'staff_names' => array_keys($uniqueStaff),
        'department_names' => array_keys($uniqueDepartments),
    ];
}

function app_fetch_time_log_report_data(PDO $conn, array $input, string $defaultStatus = 'all'): array
{
    $filters = app_build_scoped_time_log_filters($conn, $input, $defaultStatus);
    $rows = app_get_manage_time_logs_all($conn, $filters);

    return [
        'filters' => $filters,
        'rows' => $rows,
        'summary' => app_summarize_time_log_rows($rows),
        'scope_label' => $filters['scope']['scope_label'] ?? 'ตามสิทธิ์ที่เข้าถึงได้',
    ];
}

function app_get_time_log_by_id(PDO $conn, int $timeLogId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            t.*,
            u.fullname,
            u.position_name,
            d.department_name,
            c.fullname AS checker_name
        FROM time_logs t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users c ON t.checked_by = c.id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->execute([$timeLogId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_time_log_within_scope(PDO $conn, array $timeLog): bool
{
    $scope = app_get_accessible_departments($conn);
    $departmentId = (int) ($timeLog['department_id'] ?? 0);

    return in_array($departmentId, $scope['ids'], true);
}

function app_get_selected_time_log_summary(PDO $conn, array $selectedIds, bool $pendingOnly = false, array $allowedDepartmentIds = []): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $selectedIds), static fn($id) => $id > 0)));
    if (!$ids) {
        return [
            'rows' => [],
            'count' => 0,
            'staff_names' => [],
            'departments' => [],
            'ids' => [],
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $where = "t.id IN ($placeholders)";
    $params = $ids;
    if ($pendingOnly) {
        $where .= " AND " . app_time_log_pending_condition('t');
    }
    if ($allowedDepartmentIds) {
        $departmentPlaceholders = implode(', ', array_fill(0, count($allowedDepartmentIds), '?'));
        $where .= " AND t.department_id IN ($departmentPlaceholders)";
        foreach ($allowedDepartmentIds as $departmentId) {
            $params[] = (int) $departmentId;
        }
    }

    $stmt = $conn->prepare("
        SELECT
            t.id,
            t.user_id,
            t.work_date,
            t.time_in,
            t.time_out,
            u.fullname,
            u.position_name,
            d.department_name
        FROM time_logs t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE $where
        ORDER BY t.work_date DESC, t.id DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $staffNames = [];
    $departments = [];
    $rowIds = [];
    foreach ($rows as $row) {
        $rowIds[] = (int) $row['id'];
        if (!empty($row['fullname'])) {
            $staffNames[$row['fullname']] = true;
        }
        if (!empty($row['department_name'])) {
            $departments[$row['department_name']] = true;
        }
    }

    return [
        'rows' => $rows,
        'count' => count($rows),
        'staff_names' => array_keys($staffNames),
        'departments' => array_keys($departments),
        'ids' => $rowIds,
    ];
}

function app_process_bulk_approval(PDO $conn, array $selectedIds, int $checkerId, string $checkerName, string $checkerSignature): array
{
    if ($checkerSignature === '') {
        return [
            'success' => false,
            'message' => 'ยังไม่สามารถอนุมัติรายการได้ เนื่องจากยังไม่ได้ตั้งค่าลายเซ็นผู้ตรวจสอบ',
            'updated_count' => 0,
            'skipped_count' => 0,
            'skipped_reasons' => [],
        ];
    }

    $allowedDepartmentIds = app_get_accessible_departments($conn)['ids'];
    $summary = app_get_selected_time_log_summary($conn, $selectedIds, true, $allowedDepartmentIds);
    if ($summary['count'] === 0) {
        return [
            'success' => false,
            'message' => 'ไม่พบรายการที่พร้อมอนุมัติ หรือรายการที่เลือกถูกอนุมัติไปแล้ว',
            'updated_count' => 0,
            'skipped_count' => 0,
            'skipped_reasons' => [],
        ];
    }

    $successCount = 0;
    $skipped = [];
    $signaturePath = 'uploads/signatures/' . $checkerSignature;
    $now = date('Y-m-d H:i:s');

    try {
        $conn->beginTransaction();
          foreach ($summary['rows'] as $selectedRow) {
            $timeLogId = (int) $selectedRow['id'];
            $beforeRow = app_get_time_log_by_id($conn, $timeLogId);
            if (!$beforeRow) {
                $skipped[] = "ไม่พบรายการ #{$timeLogId}";
                continue;
            }
            if (!app_time_log_within_scope($conn, $beforeRow)) {
                $skipped[] = "รายการ #{$timeLogId} อยู่นอกขอบเขตสิทธิ์";
                continue;
            }
            if (!app_time_log_is_pending($beforeRow)) {
                $skipped[] = "รายการ #{$timeLogId} ไม่อยู่ในสถานะที่อนุมัติได้";
                continue;
            }

            $updateStmt = $conn->prepare('UPDATE time_logs SET checked_by = ?, checked_at = ?, signature = ? WHERE id = ? AND ' . app_time_log_pending_condition(''));
            $updateStmt->execute([$checkerId, $now, $signaturePath, $timeLogId]);
            if ($updateStmt->rowCount() !== 1) {
                $skipped[] = "รายการ #{$timeLogId} ถูกเปลี่ยนสถานะระหว่างดำเนินการ";
                continue;
            }

              $afterRow = app_get_time_log_by_id($conn, $timeLogId);
              app_insert_time_log_audit($conn, $timeLogId, 'bulk_approve', $beforeRow, $afterRow, $checkerId, $checkerName, 'อนุมัติรายการจากหน้าคิวตรวจสอบแบบเลือกหลายรายการ');
              if ($afterRow) {
                  app_create_approval_completed_notification($conn, $afterRow, $checkerName);
              }
              $successCount++;
          }
          app_sync_reviewer_queue_notifications($conn);
          $conn->commit();
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        return [
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดระหว่างอนุมัติรายการแบบกลุ่ม กรุณาลองใหม่อีกครั้ง',
            'updated_count' => 0,
            'skipped_count' => 0,
            'skipped_reasons' => [],
        ];
    }

    $message = "อนุมัติสำเร็จ {$successCount} รายการ";
    if ($skipped) {
        $message .= ' และข้าม ' . count($skipped) . ' รายการ';
    }

    return [
        'success' => $successCount > 0,
        'message' => $message,
        'updated_count' => $successCount,
        'skipped_count' => count($skipped),
        'skipped_reasons' => $skipped,
    ];
}

function app_insert_time_log_audit(
    PDO $conn,
    int $timeLogId,
    string $actionType,
    ?array $oldValues,
    ?array $newValues,
    int $actorUserId,
    string $actorName,
    ?string $note = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO time_log_audit_trails (
            time_log_id,
            action_type,
            old_values_json,
            new_values_json,
            actor_user_id,
            actor_name_snapshot,
            note
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $timeLogId,
        $actionType,
        $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $actorUserId,
        $actorName,
        $note,
    ]);
}

function app_time_log_is_locked(array $timeLog): bool
{
    return !empty($timeLog['checked_at']);
}

function app_can_edit_time_log_record(array $timeLog): bool
{
    if (!app_can('can_manage_time_logs')) {
        return false;
    }

    if (!app_time_log_is_locked($timeLog)) {
        return true;
    }

    return app_can('can_edit_locked_time_logs');
}

function app_normalize_user_time_history_filters(array|string $filters = []): array
{
    if (is_string($filters)) {
        $filters = ['date' => $filters];
    }

    $date = trim((string) ($filters['date'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $query = trim((string) ($filters['query'] ?? ''));
    $status = trim((string) ($filters['status'] ?? 'all'));
    $allowedStatuses = ['all', 'pending', 'approved', 'issue'];

    if ($date !== '') {
        $dateFrom = $date;
        $dateTo = $date;
    }

    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    return [
        'date' => $date,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'query' => $query,
        'status' => in_array($status, $allowedStatuses, true) ? $status : 'all',
    ];
}

function app_build_user_time_history_scope(array|string $filters, int $userId): array
{
    $normalized = app_normalize_user_time_history_filters($filters);
    $params = [$userId];
    $where = ['t.user_id = ?'];

    if ($normalized['date_from'] !== '') {
        $where[] = 't.work_date >= ?';
        $params[] = $normalized['date_from'];
    }

    if ($normalized['date_to'] !== '') {
        $where[] = 't.work_date <= ?';
        $params[] = $normalized['date_to'];
    }

    if ($normalized['status'] === 'approved') {
        $where[] = app_time_log_checked_condition('t');
    } elseif ($normalized['status'] === 'pending') {
        $where[] = app_time_log_pending_condition('t');
    } elseif ($normalized['status'] === 'issue') {
        $where[] = '(t.time_in IS NULL OR t.time_out IS NULL OR COALESCE(t.work_hours, 0) <= 0)';
    }

    if ($normalized['query'] !== '') {
        $where[] = '(COALESCE(d.department_name, \'\') LIKE ? OR COALESCE(t.note, \'\') LIKE ?)';
        $params[] = '%' . $normalized['query'] . '%';
        $params[] = '%' . $normalized['query'] . '%';
    }

    return [
        'filters' => $normalized,
        'where_sql' => 'WHERE ' . implode(' AND ', $where),
        'params' => $params,
    ];
}

function app_get_user_time_history_count(PDO $conn, int $userId, array|string $filters = []): int
{
    $scope = app_build_user_time_history_scope($filters, $userId);
    $countStmt = $conn->prepare("
        SELECT COUNT(*)
        FROM time_logs t
        LEFT JOIN departments d ON t.department_id = d.id
        {$scope['where_sql']}
    ");
    $countStmt->execute($scope['params']);

    return (int) $countStmt->fetchColumn();
}

function app_get_user_time_history_rows(PDO $conn, int $userId, array|string $filters, int $limit, int $offset): array
{
    $scope = app_build_user_time_history_scope($filters, $userId);

    $historyStmt = $conn->prepare("
        SELECT t.*, d.department_name, u.fullname AS checker
        FROM time_logs t
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users u ON t.checked_by = u.id
        {$scope['where_sql']}
        ORDER BY t.work_date DESC, t.id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $historyStmt->execute($scope['params']);

    return $historyStmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_get_user_time_history_flags(PDO $conn, int $userId, array $historyRows): array
{
    $flags = [];
    foreach ($historyRows as $historyRow) {
        $isIncomplete = empty($historyRow['time_in']) || empty($historyRow['time_out']);
        $hasOverlap = false;
        if (!$isIncomplete) {
            $hasOverlap = app_find_overlapping_time_log(
                $conn,
                $userId,
                (string) $historyRow['time_in'],
                (string) $historyRow['time_out'],
                (int) $historyRow['id']
            ) !== null;
        }

        $flags[(int) $historyRow['id']] = [
            'incomplete' => $isIncomplete,
            'overlap' => $hasOverlap,
        ];
    }

    return $flags;
}

function app_build_manageable_user_filters(array $input): array
{
    $fullname = trim((string) ($input['fullname'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $positionName = trim((string) ($input['position_name'] ?? ''));
    $department = trim((string) ($input['department'] ?? ''));
    $role = trim((string) ($input['role'] ?? ''));
    $accountStatus = trim((string) ($input['account_status'] ?? ''));
    $allowedRoles = ['admin', 'checker', 'finance', 'staff'];
    $role = in_array($role, $allowedRoles, true) ? $role : '';
    $accountStatus = in_array($accountStatus, ['active', 'inactive'], true) ? $accountStatus : '';

    $where = ['1 = 1'];
    $params = [];

    if ($fullname !== '') {
        $where[] = 'u.fullname LIKE ?';
        $params[] = '%' . $fullname . '%';
    }
    if ($username !== '') {
        $where[] = 'u.username LIKE ?';
        $params[] = '%' . $username . '%';
    }
    if ($positionName !== '') {
        $where[] = 'COALESCE(u.position_name, \'\') LIKE ?';
        $params[] = '%' . $positionName . '%';
    }
    if ($department !== '') {
        $where[] = 'u.department_id = ?';
        $params[] = (int) $department;
    }
    if ($role !== '') {
        $where[] = 'u.role = ?';
        $params[] = $role;
    }
    if ($accountStatus !== '') {
        $where[] = 'u.is_active = ?';
        $params[] = $accountStatus === 'active' ? 1 : 0;
    }

    return [
        'fullname' => $fullname,
        'username' => $username,
        'position_name' => $positionName,
        'department' => $department,
        'role' => $role,
        'account_status' => $accountStatus,
        'where_sql' => implode(' AND ', $where),
        'params' => $params,
    ];
}

function app_get_manageable_users(PDO $conn, array $filters, int $limit, int $offset): array
{
    $stmt = $conn->prepare("
        SELECT
            u.*,
            d.department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE {$filters['where_sql']}
        ORDER BY u.fullname ASC, u.id ASC
        LIMIT ? OFFSET ?
    ");

    $index = 1;
    foreach ($filters['params'] as $param) {
        $stmt->bindValue($index, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $index++;
    }
    $stmt->bindValue($index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($index, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_get_manageable_users_all(PDO $conn, array $filters): array
{
    $sql = "
        SELECT
            u.*,
            d.department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE {$filters['where_sql']}
        ORDER BY u.fullname ASC, u.id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($filters['params']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_count_manageable_users(PDO $conn, array $filters): int
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE {$filters['where_sql']}
    ");
    $stmt->execute($filters['params']);

    return (int) $stmt->fetchColumn();
}

function app_insert_user_permission_audit(
    PDO $conn,
    int $targetUserId,
    string $actionType,
    ?array $oldValues,
    ?array $newValues,
    int $actorUserId,
    string $actorName,
    ?string $note = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO user_permission_audit_trails (
            target_user_id,
            action_type,
            old_values_json,
            new_values_json,
            actor_user_id,
            actor_name_snapshot,
            note
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $targetUserId,
        $actionType,
        $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $actorUserId,
        $actorName,
        $note,
    ]);
}
