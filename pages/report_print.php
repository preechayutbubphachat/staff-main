<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_login();

$type = $_GET['type'] ?? 'my';
$downloadMode = $_GET['download'] ?? '';
$allowedTypes = ['my', 'department', 'daily', 'approval', 'manage', 'manage_users', 'db_change_logs', 'db_table'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid report type');
}

$generatedAt = date('d/m/Y H:i');
$generatedBy = (string) ($_SESSION['fullname'] ?? '-');
$organizationName = 'โรงพยาบาลหนองพอก';
$systemName = 'ระบบบันทึกเวลาและรายงานการปฏิบัติงาน';

$summaryCards = [];
$tableHeaders = [];
$tableRows = [];
$reportLayout = 'table';
$monthlyMatrixShowTailColumns = false;
$monthlyDays = [];
$monthlyRows = [];
$monthlyRowPages = [];
$footerLegendItems = [];
$signatureLabel = '';
$signatureName = '';
$notesBlockText = '';
$title = '';
$subtitle = '';
$reportTypeLabel = '';
$isDepartmentOfficialReport = false;
$departmentOfficialHeaderLines = [];
$departmentOfficialPeriodLines = [];

function app_db_table_print_headers(array $config): array
{
    $labels = [];
    foreach (app_db_admin_visible_browse_columns($config) as $column) {
        $metaLabel = $config['field_meta'][$column]['label'] ?? null;
        $labels[] = $metaLabel ?: $column;
    }

    return $labels;
}

if ($type === 'my') {
    $reportData = app_fetch_my_report_data($conn, (int) $_SESSION['id'], $_GET);
    $filters = $reportData['filters'];
    $summary = $reportData['summary'];

    $title = 'รายงานเวลาปฏิบัติงานส่วนบุคคล';
    $subtitle = $filters['title_range'];
    $reportTypeLabel = 'รายงานส่วนบุคคล';
    $summaryCards = [
        ['label' => 'จำนวนเวร', 'value' => (int) $summary['total_logs']],
        ['label' => 'ชั่วโมงรวม', 'value' => number_format((float) $summary['total_hours'], 2)],
        ['label' => 'ตรวจแล้ว', 'value' => (int) $summary['approved_logs']],
        ['label' => 'รอตรวจ', 'value' => (int) $summary['pending_logs']],
    ];
    $tableHeaders = ['ลำดับ', 'วันที่', 'แผนก', 'เวลาเข้า', 'เวลาออก', 'ชั่วโมงรวม', 'หมายเหตุ', 'ผู้ตรวจ', 'เวลาตรวจ'];
    foreach ($reportData['logs'] as $index => $row) {
        $tableRows[] = [
            $index + 1,
            $row['work_date'] ? date('d/m/Y', strtotime($row['work_date'])) : '-',
            $row['department_name'] ?: '-',
            $row['time_in'] ? date('d/m/Y H:i', strtotime($row['time_in'])) : '-',
            $row['time_out'] ? date('d/m/Y H:i', strtotime($row['time_out'])) : '-',
            number_format((float) $row['work_hours'], 2),
            $row['note'] ?: '-',
            $row['checker_name'] ?: '-',
            $row['checked_at'] ? date('d/m/Y H:i', strtotime($row['checked_at'])) : '-',
        ];
    }
} elseif ($type === 'department') {
    app_require_permission('can_view_department_reports');
    $reportData = app_fetch_department_report_data($conn, $_GET);
    $headingContext = $reportData['heading_context'] ?? app_get_department_report_heading_context($reportData['filters']);
    $filters = $reportData['filters'];
    $matrixData = app_fetch_department_monthly_shift_matrix($conn, $filters, $reportData['staff_rows']);
    $thaiMonthName = (string) ($filters['month_label_th'] ?? '');
    $thaiYear = (int) ($filters['year_be'] ?? ((int) date('Y') + 543));
    $yearCe = (int) ($filters['year_ce'] ?? max(1, $thaiYear - 543));
    $monthNumber = (int) ($filters['month_number'] ?? date('n'));
    $daysInReportMonth = cal_days_in_month(CAL_GREGORIAN, $monthNumber, $yearCe);
    $thaiMonths = app_thai_month_names();
    $runtimeNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $runtimeMonthName = $thaiMonths[(int) $runtimeNow->format('n')] ?? $runtimeNow->format('F');
    $runtimeYearBe = (int) $runtimeNow->format('Y') + 543;

    $title = 'ที่ 159/' . $thaiYear;
    $subtitle = 'ประจำเดือน ' . $thaiMonthName . ' พ.ศ. ' . $thaiYear;
    $reportTypeLabel = 'รายงานแผนกประจำเดือน';
    $reportLayout = 'monthly_matrix';
    $isDepartmentOfficialReport = true;
    $monthlyMatrixShowTailColumns = true;
    $monthlyDays = $matrixData['days'] ?? [];
    $monthlyRows = $matrixData['rows'] ?? [];
    $monthlyRowPages = array_chunk($monthlyRows, 15);
    $footerLegendItems = [
        'ช = เวรเช้า เวลา 08.30 - 16.30 น.',
        'บ = เวรบ่าย เวลา 16.30 - 00.30 น.',
        'ด = เวรดึก เวลา 00.30 - 08.30 น.',
        'BD = เวรนอกเวลาราชการ',
    ];
    $signatureLabel = 'ผู้ตรวจสอบเวร';
    $notesBlockText = '';
    $departmentOfficialHeaderLines = [
        'ที่ 159/' . $thaiYear,
        'เรื่อง ให้เจ้าหน้าที่ปฏิบัติงานตามตามเวลาราชการนอกเวลาราชการและวันหยุดราชการ (IM)',
        'ประจำเดือน ' . $thaiMonthName . ' พ.ศ. ' . $thaiYear,
        'ด้วยโรงพยาบาลหนองพอกเป็นสถานบริการสาธารณสุขที่ให้บริการสนับสนุนหรือร่วมบริการด้านการรักษาพยาบาลหรือบริการด้านการรักษาพยาบาลหรือบริการสาธารณสุขที่เป็นบริการอันเป็นสาธารณประโยชน์จึงจำเป็นต้องให้บริการตลอดเวลา',
        'ฉะนั้น เพื่อมิให้บริการเกิดความเสียหายแก่ทางราชการและประชาชนผู้มาขอรับบริการสามารถได้รับบริการตลอดเวลาจึงให้เจ้าหน้าที่ปฏิบัติงานนอกเวลาราชการและวันหยุดราชกาชโดยให้อยู่ในความควบคุมของ',
        'หัวหน้าหน่วยงาน โดยให้เบิกค่าตอบแทนตามข้อบังคับกระทรวงสาธารณสุข ตามประกาศคณะกรรมการพิจารณาค่าตอบแทนจังหวัดร้อยเอ็ด ฉบับที่ 54 ลงวันที่ 22 กันยายน 2565',
        'และประกาศคณะกรรมการพิจารณาค่าตอบแทนจังหวัดร้อยเอ็ด (ฉบับที่ 64) ลงวันที่ 28 กุมภาพันธ์ 2566 ดังมีรายชื่อต่อไปนี้',
    ];
    $departmentOfficialPeriodLines = [
        sprintf('ทั้งนี้ตั้งแต่วันที่ 1 %s %d ถึงวันที่ %d %s %d', $thaiMonthName, $thaiYear, $daysInReportMonth, $thaiMonthName, $thaiYear),
        sprintf('สั่ง ณ วันที่ %d เดือน %s %d', (int) $runtimeNow->format('j'), $runtimeMonthName, $runtimeYearBe),
    ];
} elseif ($type === 'daily') {
    $reportData = app_fetch_daily_schedule_data($conn, $_GET);
    $headingContext = $reportData['heading_context'] ?? app_get_daily_schedule_heading_context($reportData);
    $dailyDepartments = app_get_daily_schedule_departments($conn)['departments'];
    $dailySelectedDepartment = trim((string) ($_GET['department'] ?? ''));
    $dailyScopeLabel = $dailySelectedDepartment !== ''
        ? 'แสดงข้อมูลเฉพาะแผนก ' . (app_find_department_name($dailyDepartments, (int) $dailySelectedDepartment) ?: 'ไม่ระบุแผนก')
        : 'ทุกแผนกในระบบ';
    $dailyMode = (string) ($reportData['mode'] ?? 'daily');

    $title = $headingContext['main_heading'] ?? $reportData['date_heading'];
    $subtitle = $dailyScopeLabel . ' | สถานะ ' . ($reportData['review_status_label'] ?? 'ทั้งหมด');
    $reportTypeLabel = $dailyMode === 'monthly' ? 'รายงานเวรประจำเดือน' : 'รายงานเวรประจำวัน';
    $summaryCards = [
        ['label' => $dailyMode === 'monthly' ? 'จำนวนเจ้าหน้าที่' : 'จำนวนกะ', 'value' => (int) ($reportData['unique_staff_count'] ?? 0)],
        ['label' => $dailyMode === 'monthly' ? 'จำนวนรายการเวร' : 'จำนวนรายการ', 'value' => (int) ($reportData['total_rows'] ?? count($reportData['logs'] ?? []))],
        ['label' => 'ชั่วโมงรวม', 'value' => number_format((float) ($reportData['total_hours'] ?? 0), 2)],
        ['label' => 'อนุมัติแล้ว', 'value' => (int) ($reportData['approved_count'] ?? 0)],
    ];
    $footerLegendItems = [
        'ช = เวรเช้า เวลา 08.30 - 16.30 น.',
        'บ = เวรบ่าย เวลา 16.30 - 00.30 น.',
        'ด = เวรดึก เวลา 00.30 - 08.30 น.',
        'BD = เวรบ่ายนอกเวลาราชการ',
    ];
    $signatureLabel = 'ผู้ตรวจสอบเวร';
    $notesBlockText = 'รายงานนี้แสดงตารางเวรตามตัวกรองปัจจุบัน และจัดรูปแบบให้เหมาะสำหรับพิมพ์เอกสารทางการ';

    if ($dailyMode === 'monthly') {
        $reportLayout = 'monthly_matrix';
        $monthlyDays = $reportData['matrix_days'] ?? [];
        $monthlyRows = $reportData['matrix_rows'] ?? [];
    } else {
        $tableHeaders = ['กลุ่มเวร', 'ลำดับ', 'ชื่อเจ้าหน้าที่', 'ตำแหน่ง', 'แผนก', 'เบอร์โทรศัพท์', 'หมายเหตุ'];
        foreach (($reportData['grouped_logs'] ?? []) as $group) {
            foreach (($group['rows'] ?? []) as $index => $row) {
                $tableRows[] = [
                    $group['heading_text'] ?? $group['label'],
                    $index + 1,
                    $row['fullname'] ?: '-',
                    $row['position_name'] ?: '-',
                    $row['department_name'] ?: '-',
                    $row['phone_number'] ?: '-',
                    $row['note'] ?: '-',
                ];
            }
        }
    }
} elseif ($type === 'approval') {
    app_require_permission('can_approve_logs');
    $reportData = app_fetch_time_log_report_data($conn, $_GET, 'pending');
    $summary = $reportData['summary'];

    $title = 'รายงานคิวตรวจสอบรายการลงเวลา';
    $subtitle = $reportData['scope_label'];
    $reportTypeLabel = 'คิวตรวจสอบ';
    $summaryCards = [
        ['label' => 'จำนวนรายการ', 'value' => (int) $summary['total_rows']],
        ['label' => 'เจ้าหน้าที่', 'value' => (int) $summary['unique_staff_count']],
        ['label' => 'แผนก', 'value' => (int) $summary['unique_department_count']],
        ['label' => 'ชั่วโมงรวม', 'value' => number_format((float) $summary['total_hours'], 2)],
    ];
    $tableHeaders = ['ลำดับ', 'วันที่', 'ชื่อเจ้าหน้าที่', 'ตำแหน่ง', 'แผนก', 'เวลาเข้า', 'เวลาออก', 'ชั่วโมงรวม', 'สถานะ', 'หมายเหตุ'];
    foreach ($reportData['rows'] as $index => $row) {
        $status = app_time_log_status_meta($row);
        $tableRows[] = [
            $index + 1,
            $row['work_date'] ? date('d/m/Y', strtotime($row['work_date'])) : '-',
            $row['fullname'] ?: '-',
            $row['position_name'] ?: '-',
            $row['department_name'] ?: '-',
            $row['time_in'] ? date('H:i', strtotime($row['time_in'])) : '-',
            $row['time_out'] ? date('H:i', strtotime($row['time_out'])) : '-',
            number_format((float) $row['work_hours'], 2),
            $status['label'],
            $row['note'] ?: '-',
        ];
    }
} elseif ($type === 'manage') {
    app_require_permission('can_manage_time_logs');
    $reportData = app_fetch_time_log_report_data($conn, $_GET, 'all');
    $summary = $reportData['summary'];

    $title = 'รายงานจัดการลงเวลาเวร';
    $subtitle = $reportData['scope_label'];
    $reportTypeLabel = 'จัดการลงเวลาเวร';
    $summaryCards = [
        ['label' => 'จำนวนรายการ', 'value' => (int) $summary['total_rows']],
        ['label' => 'เจ้าหน้าที่', 'value' => (int) $summary['unique_staff_count']],
        ['label' => 'อนุมัติแล้ว', 'value' => (int) $summary['checked_count']],
        ['label' => 'รอตรวจ', 'value' => (int) $summary['pending_count']],
    ];
    $tableHeaders = ['ลำดับ', 'วันที่', 'ชื่อเจ้าหน้าที่', 'ตำแหน่ง', 'แผนก', 'เวลาเข้า', 'เวลาออก', 'ชั่วโมงรวม', 'สถานะ', 'ตรวจโดย', 'ตรวจเมื่อ'];
    foreach ($reportData['rows'] as $index => $row) {
        $status = app_time_log_status_meta($row);
        $tableRows[] = [
            $index + 1,
            $row['work_date'] ? date('d/m/Y', strtotime($row['work_date'])) : '-',
            $row['fullname'] ?: '-',
            $row['position_name'] ?: '-',
            $row['department_name'] ?: '-',
            $row['time_in'] ? date('H:i', strtotime($row['time_in'])) : '-',
            $row['time_out'] ? date('H:i', strtotime($row['time_out'])) : '-',
            number_format((float) $row['work_hours'], 2),
            $status['label'],
            $row['checker_name'] ?: '-',
            $row['checked_at'] ? date('d/m/Y H:i', strtotime($row['checked_at'])) : '-',
        ];
    }
} elseif ($type === 'manage_users') {
    app_require_permission('can_manage_user_permissions');
    $filters = app_build_manageable_user_filters($_GET);
    $rows = app_get_manageable_users_all($conn, $filters);

    $title = 'รายงานข้อมูลผู้ใช้งาน';
    $subtitle = 'รายการผู้ใช้งานตามตัวกรองที่เลือก';
    $reportTypeLabel = 'จัดการผู้ใช้งาน';
    $summaryCards = [
        ['label' => 'จำนวนผู้ใช้งาน', 'value' => count($rows)],
        ['label' => 'ผู้มีสิทธิ์อนุมัติ', 'value' => count(array_filter($rows, static fn($row) => !empty($row['can_approve_logs'])))],
        ['label' => 'ผู้จัดการเวลาเวร', 'value' => count(array_filter($rows, static fn($row) => !empty($row['can_manage_time_logs'])))],
        ['label' => 'ผู้ดูแลระบบ', 'value' => count(array_filter($rows, static fn($row) => ($row['role'] ?? '') === 'admin'))],
    ];
    $tableHeaders = ['ลำดับ', 'ชื่อเจ้าหน้าที่', 'Username', 'ตำแหน่ง', 'แผนก', 'บทบาท', 'สถานะบัญชี'];
    foreach ($rows as $index => $row) {
        $tableRows[] = [
            $index + 1,
            app_user_display_name($row),
            $row['username'] ?? '-',
            $row['position_name'] ?: '-',
            $row['department_name'] ?: '-',
            app_role_label((string) ($row['role'] ?? 'staff')),
            !empty($row['is_active']) ? 'เปิดใช้งาน' : 'ปิดใช้งาน',
        ];
    }
} elseif ($type === 'db_change_logs') {
    app_require_permission('can_manage_database');
    $rows = app_table_exists($conn, 'db_admin_audit_logs')
        ? $conn->query('SELECT * FROM db_admin_audit_logs ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $tableConfigs = app_db_admin_tables();

    $title = 'รายงานบันทึกการเปลี่ยนแปลงข้อมูล';
    $subtitle = 'ประวัติการเปลี่ยนแปลงจากโมดูลจัดการระบบหลังบ้าน';
    $reportTypeLabel = 'บันทึกการเปลี่ยนแปลงข้อมูล';
    $summaryCards = [
        ['label' => 'จำนวนรายการ', 'value' => count($rows)],
        ['label' => 'สร้างข้อมูล', 'value' => count(array_filter($rows, static fn($row) => ($row['action_type'] ?? '') === 'create'))],
        ['label' => 'แก้ไขข้อมูล', 'value' => count(array_filter($rows, static fn($row) => ($row['action_type'] ?? '') === 'update'))],
        ['label' => 'ลบข้อมูล', 'value' => count(array_filter($rows, static fn($row) => ($row['action_type'] ?? '') === 'delete'))],
    ];
    $tableHeaders = ['ลำดับ', 'เวลา', 'ตาราง', 'การกระทำ', 'ผู้ดำเนินการ', 'หมายเหตุ'];
    foreach ($rows as $index => $row) {
        $tableRows[] = [
            $index + 1,
            app_format_thai_datetime((string) ($row['created_at'] ?? '')),
            $tableConfigs[$row['table_name']]['label'] ?? ($row['table_name'] ?? '-'),
            $row['action_type'] ?? '-',
            $row['actor_name_snapshot'] ?? '-',
            $row['note'] ?: '-',
        ];
    }
} else {
    app_require_permission('can_manage_database');
    $table = trim((string) ($_GET['table'] ?? ''));
    $config = app_db_admin_require_table_allowed($table);
    $filters = app_db_admin_build_filters($table, $_GET, $config);
    $rows = app_table_exists($conn, $table) ? app_db_admin_fetch_all_rows($conn, $config, $filters) : [];

    $title = 'รายงานรายการข้อมูลในตาราง';
    $subtitle = $config['label'] . ($filters['q'] !== '' ? ' | คำค้นหา: ' . $filters['q'] : '');
    $reportTypeLabel = 'ตาราง ' . $config['label'];
    $summaryCards = [
        ['label' => 'จำนวนแถว', 'value' => count($rows)],
        ['label' => 'ตาราง', 'value' => $config['label']],
        ['label' => 'คำค้นหา', 'value' => $filters['q'] !== '' ? $filters['q'] : 'ทั้งหมด'],
        ['label' => 'รูปแบบข้อมูล', 'value' => 'Allowlist'],
    ];
    $tableHeaders = array_merge(['ลำดับ'], app_db_table_print_headers($config));
    $visibleColumns = app_db_admin_visible_browse_columns($config);
    foreach ($rows as $index => $row) {
        $printRow = [$index + 1];
        foreach ($visibleColumns as $column) {
            $printRow[] = app_db_admin_format_value($column, $row[$column] ?? null);
        }
        $tableRows[] = $printRow;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#12263f;--muted:#52657c;--line:#cdd9e5;--soft:#f5f8fb;--accent:#1f5f8b}
        *{box-sizing:border-box}
        @page{size:A4 landscape;margin:8mm}
        body{margin:0;background:#eef3f7;color:var(--ink);font-family:'Sarabun',sans-serif}
        .print-actions{width:min(1560px,calc(100% - 32px));margin:18px auto 0;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
        .print-actions button,.print-actions a{border:0;border-radius:999px;padding:11px 18px;text-decoration:none;font:inherit;cursor:pointer}
        .print-actions .primary{background:var(--ink);color:#fff}
        .print-actions .secondary{background:#fff;color:var(--ink);border:1px solid var(--line)}
        .print-hint{width:297mm;margin:10px auto 0;color:#334155;font-size:.86rem;text-align:right}
        .page{width:min(1560px,calc(100% - 32px));margin:16px auto 28px;background:#fff;box-shadow:0 24px 56px rgba(18,38,63,.12)}
        .page-head{padding:18px 24px 16px;border-bottom:2px solid var(--ink)}
        .doc-topline{display:grid;grid-template-columns:1fr auto 1fr;align-items:start;gap:16px}
        .doc-brand{font-size:.94rem;line-height:1.55}
        .doc-brand strong{display:block;font-family:'Prompt',sans-serif;font-size:1.15rem}
        .doc-title{text-align:center}
        .doc-title h1{margin:0;font-family:'Prompt',sans-serif;font-size:1.55rem;line-height:1.25}
        .doc-title .subtitle{margin-top:8px;color:var(--muted);font-size:.98rem}
        .doc-meta{text-align:right;font-size:.92rem;line-height:1.55}
        .doc-meta strong{font-weight:600}
        .summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:14px 24px 0}
        .summary-card{border:1px solid var(--line);padding:12px 14px;background:#fff}
        .summary-card .label{display:block;color:var(--muted);font-size:.82rem;font-weight:600}
        .summary-card .value{display:block;margin-top:6px;font-family:'Prompt',sans-serif;font-size:1.35rem}
        .body{padding:16px 24px 20px}
        .section-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:12px}
        .section-head h2{margin:0;font-family:'Prompt',sans-serif;font-size:1.05rem}
        .section-head p{margin:4px 0 0;color:var(--muted);font-size:.92rem}
        table{width:100%;border-collapse:collapse}
        th,td{border:1px solid #4a5f73;padding:7px 8px;font-size:.88rem;vertical-align:middle}
        th{background:#eef3f7;font-weight:700;white-space:nowrap}
        .empty-state{text-align:center;padding:26px 16px;border:1px dashed var(--line);color:var(--muted)}
        .monthly-matrix-table{table-layout:fixed}
        .monthly-matrix-table th,.monthly-matrix-table td{font-size:.64rem;padding:3px 2px;text-align:center}
        .monthly-matrix-table .monthly-col-no{width:34px}
        .monthly-matrix-table .monthly-col-name{width:132px;text-align:left}
        .monthly-matrix-table .monthly-col-position{width:80px;text-align:left}
        .monthly-matrix-table .monthly-col-department{width:72px;text-align:left}
        .monthly-matrix-table .monthly-col-day{width:22px;min-width:22px}
        .monthly-matrix-table .monthly-col-total{width:34px}
        .monthly-matrix-table .monthly-col-hours{width:38px}
        .monthly-matrix-table .monthly-col-ot{width:30px}
        .monthly-matrix-table .monthly-col-remark{width:76px;text-align:left}
        .report-matrix-header-vertical{padding:0!important;vertical-align:bottom}
        .report-matrix-header-vertical > span{display:inline-flex;align-items:center;justify-content:center;writing-mode:vertical-rl;text-orientation:mixed;transform:rotate(180deg);min-height:74px;line-height:1;letter-spacing:.02em;padding:4px 0}
        .monthly-matrix-future{background:#f5f7fa}
        .footer-block{margin-top:18px;border-top:1.5px solid var(--ink);padding-top:12px;display:grid;grid-template-columns:2fr 1fr;gap:18px}
        .footer-title{font-family:'Prompt',sans-serif;font-size:1rem;margin:0 0 8px}
        .legend-list{margin:0;padding-left:18px;font-size:.9rem;line-height:1.6}
        .notes-text{margin-top:8px;font-size:.9rem;color:var(--muted)}
        .signature-box{min-height:120px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;text-align:center}
        .signature-line{width:82%;border-bottom:1px solid var(--ink);height:42px;margin-bottom:8px}
        .signature-role{color:var(--muted);font-size:.92rem}
        .report-paper,.official-report-page{width:297mm;min-height:210mm;padding:8mm 9mm 8mm;margin:16px auto 28px;page-break-after:always}
        .official-report-page:last-child{page-break-after:auto}
        .official-header{text-align:center;margin:0 auto 3px;font-size:.67rem;line-height:1.26;color:#111827}
        .official-header-line{margin:0}
        .official-header-line.is-ref{margin-bottom:1px;font-size:.72rem}
        .official-header-line.is-subject{font-weight:600;font-size:.74rem;line-height:1.22}
        .official-header-line.is-period{margin-bottom:3px;font-size:.72rem}
        .official-header-line.is-body{text-align:center}
        .official-table{table-layout:fixed;width:96.5%;margin-left:auto;margin-right:auto;border-collapse:collapse}
        .official-table th,.official-table td{border:1px solid #1f2937;color:#111827;font-size:8px;padding:1.5px 2px;text-align:center;line-height:1.22;vertical-align:middle;overflow:hidden}
        .official-table th{background:#fff;font-weight:700;white-space:normal}
        .official-table .monthly-col-no,.official-table .monthly-col-name,.official-table .monthly-col-position,.official-table .monthly-col-day,.official-table .monthly-col-total,.official-table .monthly-col-hours,.official-table .monthly-col-ot,.official-table .monthly-col-remark{width:auto!important;min-width:0}
        .official-table td.monthly-col-name,.official-table td.monthly-col-position,.official-table td.monthly-col-remark{text-align:left}
        .official-table td.monthly-col-name,.official-table td.monthly-col-position{font-size:8.8px;font-weight:500}
        .official-table th.monthly-col-name,.official-table th.monthly-col-position,.official-table th.monthly-col-remark{text-align:center}
        .official-table .monthly-col-day{font-size:6.8px;padding-left:0;padding-right:0;white-space:nowrap}
        .official-table .official-day-heading{text-align:center;font-size:7.6px;letter-spacing:0}
        .official-period{width:96.5%;margin:8px auto 5px;color:#111827;font-size:.76rem;line-height:1.52}
        .official-period p{margin:0}
        .official-footer{width:96.5%;display:grid;grid-template-columns:1fr 60mm;gap:12mm;margin:7px auto 0;align-items:start;color:#111827}
        .official-footer.legend-only{grid-template-columns:1fr}
        .official-legend-title{margin:0 0 3px;font-weight:700;font-size:.78rem}
        .official-legend-list{margin:0;padding-left:14px;font-size:.72rem;line-height:1.58}
        .official-signature{--signature-label-width:10mm;--signature-line-width:46mm;display:grid;grid-template-columns:var(--signature-label-width) var(--signature-line-width);column-gap:1.5mm;justify-content:end;padding-top:10px;text-align:left;font-size:.78rem;line-height:1.45;justify-self:end;width:60mm}
        .official-sign-row{display:contents}
        .official-sign-label{grid-column:1;text-align:right;white-space:nowrap}
        .official-sign-dots{grid-column:2;display:block;width:100%;border-bottom:1px dotted #111827;height:1em}
        .official-sign-name{grid-column:2;display:flex;align-items:flex-end;margin-top:3px;width:100%;line-height:1.4;text-align:center}
        .official-sign-name-paren{flex:0 0 auto}
        .official-sign-name-dots{display:block;flex:1 1 auto;border-bottom:1px dotted #111827;min-height:1em;text-align:center}
        .official-signature-role{grid-column:2;width:100%;font-weight:700;margin-top:5px;text-align:center}
        @media print{html,body{width:297mm;min-height:210mm;background:#fff}.print-actions,.print-hint{display:none!important}.page{width:100%;margin:0;box-shadow:none}tr,td,th,.summary-card{break-inside:avoid}thead{display:table-header-group}.report-paper,.official-report-page{width:100%;min-height:auto;margin:0;padding:0;box-shadow:none;break-after:page}.official-report-page:last-child{break-after:auto}}
    </style>
</head>
<body>
    <div class="print-actions">
        <a class="secondary" href="javascript:window.close()">ปิดหน้าต่าง</a>
        <button class="secondary" type="button" id="downloadPdfBtn">ดาวน์โหลด PDF</button>
        <button class="primary" type="button" onclick="window.print()">พิมพ์รายงาน</button>
    </div>
    <div class="print-hint">เพื่อให้เอกสารถูกต้อง กรุณาปิดตัวเลือก Headers and footers ในหน้าต่างพิมพ์ของเบราว์เซอร์</div>

    <div id="reportSurface">
    <?php if ($isDepartmentOfficialReport): ?>
        <?php if (!$monthlyRows): ?>
            <main class="page official-report-page report-paper">
                <header class="official-header">
                    <?php foreach ($departmentOfficialHeaderLines as $lineIndex => $line): ?>
                        <p class="official-header-line <?= $lineIndex === 0 ? 'is-ref' : ($lineIndex === 1 ? 'is-subject' : ($lineIndex === 2 ? 'is-period' : 'is-body')) ?>"><?= htmlspecialchars($line) ?></p>
                    <?php endforeach; ?>
                </header>
                <div class="empty-state">ไม่พบข้อมูลสำหรับจัดทำรายงานในช่วงเวลาหรือขอบเขตที่เลือก</div>
                <?php if ($departmentOfficialPeriodLines): ?>
                    <div class="official-period">
                        <?php foreach ($departmentOfficialPeriodLines as $periodLine): ?>
                            <p><?= htmlspecialchars($periodLine) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <footer class="official-footer has-signature">
                    <div>
                        <p class="official-legend-title">หมายเหตุ</p>
                        <ul class="official-legend-list">
                            <?php foreach ($footerLegendItems as $legendItem): ?>
                                <li><?= htmlspecialchars($legendItem) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="official-signature">
                        <div class="official-sign-row">
                            <span class="official-sign-label">ลงชื่อ</span>
                            <span class="official-sign-dots"></span>
                        </div>
                        <div class="official-sign-name"><span class="official-sign-name-paren">(</span><span class="official-sign-name-dots"><?= htmlspecialchars($signatureName) ?></span><span class="official-sign-name-paren">)</span></div>
                        <div class="official-signature-role"><?= htmlspecialchars($signatureLabel !== '' ? $signatureLabel : 'ผู้รับผิดชอบรายงาน') ?></div>
                    </div>
                </footer>
            </main>
        <?php else: ?>
            <?php $totalOfficialPages = count($monthlyRowPages); ?>
            <?php foreach ($monthlyRowPages as $pageIndex => $rowsForPage): ?>
                <?php $isLastOfficialPage = $pageIndex === $totalOfficialPages - 1; ?>
                <main class="page official-report-page report-paper">
                    <header class="official-header">
                        <?php foreach ($departmentOfficialHeaderLines as $lineIndex => $line): ?>
                            <p class="official-header-line <?= $lineIndex === 0 ? 'is-ref' : ($lineIndex === 1 ? 'is-subject' : ($lineIndex === 2 ? 'is-period' : 'is-body')) ?>"><?= htmlspecialchars($line) ?></p>
                        <?php endforeach; ?>
                    </header>
                    <table class="monthly-matrix-table official-table">
                        <?php $officialDayColumnWidth = count($monthlyDays) > 0 ? 56 / count($monthlyDays) : 0; ?>
                        <colgroup>
                            <col style="width:3.5%">
                            <col style="width:13%">
                            <col style="width:11%">
                            <?php foreach ($monthlyDays as $_dayMeta): ?>
                                <col style="width:<?= htmlspecialchars(number_format($officialDayColumnWidth, 4, '.', '')) ?>%">
                            <?php endforeach; ?>
                            <col style="width:4.5%">
                            <col style="width:5%">
                            <col style="width:2.5%">
                            <col style="width:4.5%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="monthly-col-no" rowspan="2">ลำดับที่</th>
                                <th class="monthly-col-name" rowspan="2">ชื่อ-สกุล</th>
                                <th class="monthly-col-position" rowspan="2">ตำแหน่ง</th>
                                <th class="monthly-col-day official-day-heading" colspan="<?= count($monthlyDays) ?>">วันที่ปฏิบัติงาน</th>
                                <th class="monthly-col-total" rowspan="2">จำนวนเวร</th>
                                <th class="monthly-col-hours" rowspan="2">จำนวนชั่วโมง</th>
                                <th class="monthly-col-ot" rowspan="2">OT</th>
                                <th class="monthly-col-remark" rowspan="2">หมายเหตุ</th>
                            </tr>
                            <tr>
                                <?php foreach ($monthlyDays as $dayMeta): ?>
                                    <th class="monthly-col-day"><?= (int) $dayMeta['day'] ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rowsForPage as $row): ?>
                                <tr>
                                    <td class="monthly-col-no"><?= (int) ($row['row_number'] ?? 0) ?></td>
                                    <td class="monthly-col-name"><?= htmlspecialchars((string) ($row['fullname'] ?? '-')) ?></td>
                                    <td class="monthly-col-position"><?= htmlspecialchars((string) ($row['position_name'] ?? '-')) ?></td>
                                    <?php foreach ($monthlyDays as $dayMeta): ?>
                                        <?php $cellCode = $row['day_cells'][(int) $dayMeta['day']] ?? ''; ?>
                                        <td class="monthly-col-day <?= !empty($dayMeta['is_future']) ? 'monthly-matrix-future' : '' ?>"><?= htmlspecialchars((string) $cellCode) ?></td>
                                    <?php endforeach; ?>
                                    <td class="monthly-col-total"><?= (int) ($row['total_shifts'] ?? 0) ?></td>
                                    <td class="monthly-col-hours"><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?></td>
                                    <td class="monthly-col-ot"><?= htmlspecialchars((string) ($row['ot_value'] ?? '')) ?></td>
                                    <td class="monthly-col-remark"><?= htmlspecialchars((string) ($row['remark'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($isLastOfficialPage): ?>
                        <?php if ($departmentOfficialPeriodLines): ?>
                            <div class="official-period">
                                <?php foreach ($departmentOfficialPeriodLines as $periodLine): ?>
                                    <p><?= htmlspecialchars($periodLine) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <footer class="official-footer <?= $isLastOfficialPage ? 'has-signature' : 'legend-only' ?>">
                        <div>
                            <p class="official-legend-title">หมายเหตุ</p>
                            <ul class="official-legend-list">
                                <?php foreach ($footerLegendItems as $legendItem): ?>
                                    <li><?= htmlspecialchars($legendItem) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php if ($isLastOfficialPage): ?>
                            <div class="official-signature">
                                <div class="official-sign-row">
                                    <span class="official-sign-label">ลงชื่อ</span>
                                    <span class="official-sign-dots"></span>
                                </div>
                                <div class="official-sign-name"><span class="official-sign-name-paren">(</span><span class="official-sign-name-dots"><?= htmlspecialchars($signatureName) ?></span><span class="official-sign-name-paren">)</span></div>
                                <div class="official-signature-role"><?= htmlspecialchars($signatureLabel !== '' ? $signatureLabel : 'ผู้รับผิดชอบรายงาน') ?></div>
                            </div>
                        <?php endif; ?>
                    </footer>
                </main>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
    <main class="page">
        <header class="page-head">
            <div class="doc-topline">
                <div class="doc-brand">
                    <strong><?= htmlspecialchars($organizationName) ?></strong>
                    <div><?= htmlspecialchars($systemName) ?></div>
                </div>
                <div class="doc-title">
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <div class="subtitle"><?= htmlspecialchars($subtitle) ?></div>
                </div>
                <div class="doc-meta">
                    <div><strong>จัดทำโดย:</strong> <?= htmlspecialchars($generatedBy) ?></div>
                    <div><strong>พิมพ์เมื่อ:</strong> <?= htmlspecialchars(app_format_thai_datetime(date('Y-m-d H:i:s'))) ?></div>
                    <div><strong>รูปแบบ:</strong> แนวนอน A4</div>
                </div>
            </div>
        </header>

        <?php if ($summaryCards): ?>
        <section class="summary-grid">
            <?php foreach ($summaryCards as $card): ?>
                <div class="summary-card">
                    <span class="label"><?= htmlspecialchars($card['label']) ?></span>
                    <span class="value"><?= htmlspecialchars((string) $card['value']) ?></span>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <section class="body">
            <div class="section-head">
                <div>
                    <h2>รายละเอียดรายงาน</h2>
                    <p>ออกแบบสำหรับพิมพ์เป็นเอกสารทางการ โดยใช้ข้อมูลชุดเดียวกับหน้ารายงานในระบบ</p>
                </div>
            </div>

            <?php if ($reportLayout === 'monthly_matrix'): ?>
                <?php if (!$monthlyRows): ?>
                    <div class="empty-state">ไม่พบข้อมูลสำหรับจัดทำรายงานในช่วงเวลาหรือขอบเขตที่เลือก</div>
                <?php else: ?>
                    <table class="monthly-matrix-table">
                        <thead>
                            <tr>
                                <th class="monthly-col-no">ลำดับ</th>
                                <th class="monthly-col-name">ชื่อ-สกุล</th>
                                <th class="monthly-col-position">ตำแหน่ง</th>
                                <th class="monthly-col-department">แผนก</th>
                                <?php foreach ($monthlyDays as $dayMeta): ?>
                                    <th class="monthly-col-day"><?= (int) $dayMeta['day'] ?></th>
                                <?php endforeach; ?>
                                <?php if ($monthlyMatrixShowTailColumns): ?>
                                    <th class="monthly-col-total report-matrix-header-vertical"><span>จำนวนเวร</span></th>
                                    <th class="monthly-col-hours report-matrix-header-vertical"><span>ชั่วโมงรวม</span></th>
                                    <th class="monthly-col-ot report-matrix-header-vertical"><span>OT</span></th>
                                    <th class="monthly-col-remark">หมายเหตุ</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyRows as $row): ?>
                                <tr>
                                    <td class="monthly-col-no"><?= (int) ($row['row_number'] ?? 0) ?></td>
                                    <td class="monthly-col-name"><?= htmlspecialchars((string) ($row['fullname'] ?? '-')) ?></td>
                                    <td class="monthly-col-position"><?= htmlspecialchars((string) ($row['position_name'] ?? '-')) ?></td>
                                    <td class="monthly-col-department"><?= htmlspecialchars((string) ($row['department_name'] ?? '-')) ?></td>
                                    <?php foreach ($monthlyDays as $dayMeta): ?>
                                        <?php $cellCode = $row['day_cells'][(int) $dayMeta['day']] ?? ''; ?>
                                        <td class="monthly-col-day <?= !empty($dayMeta['is_future']) ? 'monthly-matrix-future' : '' ?>"><?= htmlspecialchars((string) $cellCode) ?></td>
                                    <?php endforeach; ?>
                                    <?php if ($monthlyMatrixShowTailColumns): ?>
                                        <td class="monthly-col-total"><?= (int) ($row['total_shifts'] ?? 0) ?></td>
                                        <td class="monthly-col-hours"><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?></td>
                                        <td class="monthly-col-ot"><?= htmlspecialchars((string) ($row['ot_value'] ?? '')) ?></td>
                                        <td class="monthly-col-remark"><?= htmlspecialchars((string) ($row['remark'] ?? '')) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php elseif (!$tableRows): ?>
                <div class="empty-state">ไม่พบข้อมูลสำหรับแสดงในรายงานนี้</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($tableHeaders as $header): ?>
                                <th><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableRows as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= htmlspecialchars((string) $cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($footerLegendItems || $signatureLabel !== ''): ?>
                <div class="footer-block">
                    <div>
                        <h3 class="footer-title">หมายเหตุ</h3>
                        <?php if ($footerLegendItems): ?>
                            <ul class="legend-list">
                                <?php foreach ($footerLegendItems as $legendItem): ?>
                                    <li><?= htmlspecialchars($legendItem) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($notesBlockText !== ''): ?>
                            <div class="notes-text"><?= htmlspecialchars($notesBlockText) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div>ลงชื่อ ...............................................................</div>
                        <div>(<?= htmlspecialchars($signatureName !== '' ? $signatureName : '...............................................................') ?>)</div>
                        <div class="signature-role"><?= htmlspecialchars($signatureLabel !== '' ? $signatureLabel : 'ผู้รับผิดชอบรายงาน') ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script>
        async function downloadPdf() {
            const surface = document.getElementById('reportSurface');
            const canvas = await html2canvas(surface, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff'
            });

            const imageData = canvas.toDataURL('image/png');
            const pdf = new window.jspdf.jsPDF('l', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const imageWidth = pageWidth;
            const imageHeight = (canvas.height * imageWidth) / canvas.width;

            let heightLeft = imageHeight;
            let position = 0;
            pdf.addImage(imageData, 'PNG', 0, position, imageWidth, imageHeight);
            heightLeft -= pageHeight;

            while (heightLeft > 0) {
                position = heightLeft - imageHeight;
                pdf.addPage();
                pdf.addImage(imageData, 'PNG', 0, position, imageWidth, imageHeight);
                heightLeft -= pageHeight;
            }

            pdf.save('report_<?= htmlspecialchars($type) ?>_<?= date('Ymd_His') ?>.pdf');
        }

        document.getElementById('downloadPdfBtn').addEventListener('click', downloadPdf);

        <?php if ($downloadMode === 'pdf'): ?>
        window.addEventListener('load', () => {
            setTimeout(downloadPdf, 350);
        });
        <?php endif; ?>
    </script>
</body>
</html>
