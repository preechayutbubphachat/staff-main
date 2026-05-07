<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_login();

$type = $_GET['type'] ?? 'my';
$allowedTypes = ['my', 'department', 'daily', 'approval', 'manage', 'manage_users', 'db_change_logs', 'db_table'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid report type');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

if ($type === 'my') {
    $userId = (int) $_SESSION['id'];
    $reportData = app_fetch_my_report_data($conn, $userId, $_GET);

    fputcsv($output, ['ลำดับ', 'วันที่', 'แผนก', 'เวลาเข้า', 'เวลาออก', 'ชั่วโมงรวม', 'หมายเหตุ', 'ผู้ตรวจ', 'เวลาตรวจ']);
    foreach ($reportData['logs'] as $index => $row) {
        fputcsv($output, [
            $index + 1,
            $row['work_date'],
            $row['department_name'] ?? '',
            $row['time_in'] ?? '',
            $row['time_out'] ?? '',
            $row['work_hours'] ?? '0.00',
            $row['note'] ?? '',
            $row['checker_name'] ?? '',
            $row['checked_at'] ?? '',
        ]);
    }
} elseif ($type === 'department') {
    app_require_permission('can_view_department_reports');
    $reportData = app_fetch_department_report_data($conn, $_GET);
    $headingContext = $reportData['heading_context'] ?? app_get_department_report_heading_context($reportData['filters']);
    $filters = $reportData['filters'];
    $matrixData = app_fetch_department_monthly_shift_matrix($conn, $filters, $reportData['staff_rows']);
    $thaiMonthName = (string) ($filters['month_label_th'] ?? '');
    $thaiYear = (int) ($filters['year_be'] ?? ((int) date('Y') + 543));
    fputcsv($output, ['ที่ 159/' . $thaiYear]);
    fputcsv($output, ['เรื่อง ให้เจ้าหน้าที่ปฏิบัติงานตามตามเวลาราชการนอกเวลาราชการและวันหยุดราชการ (IM)']);
    fputcsv($output, ['ประจำเดือน ' . $thaiMonthName . ' พ.ศ. ' . $thaiYear]);
    fputcsv($output, [$headingContext['department_label'] ?? 'แผนกทั้งหมด']);
    fputcsv($output, []);

    $headers = ['ลำดับ', 'ชื่อ-สกุล', 'ตำแหน่ง', 'แผนก'];
    foreach (($matrixData['days'] ?? []) as $dayMeta) {
        $headers[] = (int) $dayMeta['day'];
    }
    $headers[] = 'จำนวนเวร';
    $headers[] = 'ชั่วโมงรวม';
    $headers[] = 'OT';
    $headers[] = 'หมายเหตุ';
    fputcsv($output, $headers);

    foreach (($matrixData['rows'] ?? []) as $row) {
        $csvRow = [
            (int) ($row['row_number'] ?? 0),
            $row['fullname'] ?? '',
            $row['position_name'] ?? '',
            $row['department_name'] ?? '',
        ];
        foreach (($matrixData['days'] ?? []) as $dayMeta) {
            $csvRow[] = $row['day_cells'][(int) $dayMeta['day']] ?? '';
        }
        $csvRow[] = (int) ($row['total_shifts'] ?? 0);
        $csvRow[] = number_format((float) ($row['total_hours'] ?? 0), 2, '.', '');
        $csvRow[] = $row['ot_value'] ?? '';
        $csvRow[] = $row['remark'] ?? '';
        fputcsv($output, $csvRow);
    }
} elseif ($type === 'daily') {
    $reportData = app_fetch_daily_schedule_data($conn, $_GET);
    $dailyDepartments = app_get_daily_schedule_departments($conn)['departments'];
    $dailySelectedDepartment = trim((string) ($_GET['department'] ?? ''));
    $dailyScopeLabel = $dailySelectedDepartment !== ''
        ? 'แสดงข้อมูลเฉพาะแผนก ' . (app_find_department_name($dailyDepartments, (int) $dailySelectedDepartment) ?: 'ไม่ระบุแผนก')
        : 'ทุกแผนกในระบบ';
    $dailyMode = (string) ($reportData['mode'] ?? 'daily');

    fputcsv($output, [$reportData['date_heading']]);
    fputcsv($output, [$dailyScopeLabel . ' | สถานะ ' . ($reportData['review_status_label'] ?? 'ทั้งหมด')]);
    fputcsv($output, []);

    if ($dailyMode === 'monthly') {
        $headers = ['ลำดับ', 'ชื่อ-สกุล', 'ตำแหน่ง', 'แผนก'];
        foreach (($reportData['matrix_days'] ?? []) as $dayMeta) {
            $headers[] = (int) $dayMeta['day'];
        }
        fputcsv($output, $headers);

        foreach (($reportData['matrix_rows'] ?? []) as $row) {
            $csvRow = [
                (int) ($row['row_number'] ?? 0),
                $row['fullname'] ?? '',
                $row['position_name'] ?? '',
                $row['department_name'] ?? '',
            ];
            foreach (($reportData['matrix_days'] ?? []) as $dayMeta) {
                $csvRow[] = $row['day_cells'][(int) $dayMeta['day']] ?? '';
            }
            fputcsv($output, $csvRow);
        }
    } else {
        fputcsv($output, ['กลุ่มเวร', 'ลำดับ', 'ชื่อเจ้าหน้าที่', 'ตำแหน่ง', 'แผนก', 'เบอร์โทรศัพท์', 'หมายเหตุ']);
        foreach (($reportData['grouped_logs'] ?? []) as $group) {
            foreach (($group['rows'] ?? []) as $index => $row) {
                fputcsv($output, [
                    $group['heading_text'] ?? $group['label'],
                    $index + 1,
                    $row['fullname'] ?? '',
                    $row['position_name'] ?? '',
                    $row['department_name'] ?? '',
                    $row['phone_number'] ?? '',
                    $row['note'] ?? '',
                ]);
            }
        }
    }
} elseif ($type === 'approval') {
    app_require_permission('can_approve_logs');
    $reportData = app_fetch_time_log_report_data($conn, $_GET, 'pending');

    fputcsv($output, ['ลำดับ', 'วันที่', 'ชื่อเจ้าหน้าที่', 'ตำแหน่ง', 'แผนก', 'เวลาเข้า', 'เวลาออก', 'ชั่วโมงรวม', 'หมายเหตุ', 'สถานะ']);
    foreach ($reportData['rows'] as $index => $row) {
        $status = app_time_log_status_meta($row);
        fputcsv($output, [
            $index + 1,
            $row['work_date'] ?? '',
            $row['fullname'] ?? '',
            $row['position_name'] ?? '',
            $row['department_name'] ?? '',
            $row['time_in'] ?? '',
            $row['time_out'] ?? '',
            number_format((float) ($row['work_hours'] ?? 0), 2, '.', ''),
            $row['note'] ?? '',
            $status['label'],
        ]);
    }
} elseif ($type === 'manage') {
    app_require_permission('can_manage_time_logs');
    $reportData = app_fetch_time_log_report_data($conn, $_GET, 'all');

    fputcsv($output, ['ลำดับ', 'วันที่', 'ชื่อเจ้าหน้าที่', 'ตำแหน่ง', 'แผนก', 'เวลาเข้า', 'เวลาออก', 'ชั่วโมงรวม', 'หมายเหตุ', 'สถานะ', 'ตรวจโดย', 'ตรวจเมื่อ']);
    foreach ($reportData['rows'] as $index => $row) {
        $status = app_time_log_status_meta($row);
        fputcsv($output, [
            $index + 1,
            $row['work_date'] ?? '',
            $row['fullname'] ?? '',
            $row['position_name'] ?? '',
            $row['department_name'] ?? '',
            $row['time_in'] ?? '',
            $row['time_out'] ?? '',
            number_format((float) ($row['work_hours'] ?? 0), 2, '.', ''),
            $row['note'] ?? '',
            $status['label'],
            $row['checker_name'] ?? '',
            $row['checked_at'] ?? '',
        ]);
    }
} elseif ($type === 'manage_users') {
    app_require_permission('can_manage_user_permissions');
    $filters = app_build_manageable_user_filters($_GET);
    $rows = app_get_manageable_users_all($conn, $filters);

    fputcsv($output, ['ลำดับ', 'ชื่อเจ้าหน้าที่', 'Username', 'ตำแหน่ง', 'แผนก', 'บทบาท', 'สถานะบัญชี']);
    foreach ($rows as $index => $row) {
        fputcsv($output, [
            $index + 1,
            app_user_display_name($row),
            $row['username'] ?? '',
            $row['position_name'] ?? '',
            $row['department_name'] ?? '',
            app_role_label((string) ($row['role'] ?? 'staff')),
            !empty($row['is_active']) ? 'เปิดใช้งาน' : 'ปิดใช้งาน',
        ]);
    }
} elseif ($type === 'db_change_logs') {
    app_require_permission('can_manage_database');
    $tableConfigs = app_db_admin_tables();
    $rows = app_fetch_db_change_log_rows_for_report($conn, $_GET);

    fputcsv($output, ['ลำดับ', 'เวลา', 'ตาราง', 'การกระทำ', 'ผู้ดำเนินการ', 'หมายเหตุ']);
    foreach ($rows as $index => $row) {
        fputcsv($output, [
            $index + 1,
            $row['created_at'] ?? '',
            $tableConfigs[$row['table_name']]['label'] ?? ($row['table_name'] ?? ''),
            $row['action_type'] ?? '',
            $row['actor_name_snapshot'] ?? '',
            $row['note'] ?? '',
        ]);
    }
} else {
    app_require_permission('can_manage_database');
    $table = trim((string) ($_GET['table'] ?? ''));
    $config = app_db_admin_require_table_allowed($table);
    $filters = app_db_admin_build_filters($table, $_GET, $config);
    $rows = app_table_exists($conn, $table) ? app_db_admin_fetch_all_rows($conn, $config, $filters) : [];
    $visibleColumns = app_db_admin_visible_browse_columns($config);

    $headers = ['ลำดับ'];
    foreach ($visibleColumns as $column) {
        $headers[] = $config['field_meta'][$column]['label'] ?? $column;
    }
    fputcsv($output, $headers);
    foreach ($rows as $index => $row) {
        $csvRow = [$index + 1];
        foreach ($visibleColumns as $column) {
            $csvRow[] = app_db_admin_format_value($column, $row[$column] ?? null);
        }
        fputcsv($output, $csvRow);
    }
}

fclose($output);
exit;
