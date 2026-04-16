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
$monthlyDays = [];
$monthlyRows = [];
$footerLegendItems = [];
$signatureLabel = '';
$notesBlockText = '';
$title = '';
$subtitle = '';
$reportTypeLabel = '';

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
    $summary = $reportData['department_totals'];
    $headingContext = $reportData['heading_context'] ?? app_get_department_report_heading_context($reportData['filters']);

    $title = $headingContext['heading_text'];
    $subtitle = $headingContext['subheading_text'];
    $reportTypeLabel = 'รายงานแผนก';
    $summaryCards = [
        ['label' => 'จำนวนเจ้าหน้าที่', 'value' => (int) $summary['staff_count']],
        ['label' => 'จำนวนเวร', 'value' => (int) $summary['total_logs']],
        ['label' => 'ชั่วโมงรวม', 'value' => number_format((float) $summary['total_hours'], 2)],
        ['label' => 'รอตรวจ', 'value' => (int) $summary['pending_logs']],
    ];
    $tableHeaders = ['ลำดับ', 'ชื่อเจ้าหน้าที่', 'ตำแหน่ง', 'แผนก', 'จำนวนเวร', 'ชั่วโมงรวม', 'ตรวจแล้ว', 'รอตรวจ'];
    foreach ($reportData['staff_rows'] as $index => $row) {
        $tableRows[] = [
            $index + 1,
            $row['fullname'] ?: '-',
            $row['position_name'] ?: '-',
            $row['department_name'] ?: '-',
            (int) $row['total_logs'],
            number_format((float) $row['total_hours'], 2),
            (int) $row['approved_logs'],
            max(0, (int) $row['total_logs'] - (int) $row['approved_logs']),
        ];
    }
} elseif ($type === 'daily') {
    $reportData = app_fetch_daily_schedule_data($conn, $_GET);
    $headingContext = $reportData['heading_context'] ?? app_get_daily_schedule_heading_context($reportData);
    $dailyDepartments = app_get_daily_schedule_departments($conn)['departments'];
    $dailySelectedDepartment = trim((string) ($_GET['department'] ?? ''));
    $dailyScopeLabel = $dailySelectedDepartment !== ''
        ? '??????????????????? ' . (app_find_department_name($dailyDepartments, (int) $dailySelectedDepartment) ?: '????????')
        : '?????????????';
    $dailyMode = (string) ($reportData['mode'] ?? 'daily');

    $title = $headingContext['main_heading'] ?? $reportData['date_heading'];
    $subtitle = $dailyScopeLabel . ' | ????? ' . ($reportData['review_status_label'] ?? '???????');
    $reportTypeLabel = $dailyMode === 'monthly' ? '???????????????????' : '??????????????????????';
    $summaryCards = [
        ['label' => $dailyMode === 'monthly' ? '???????????????????' : '???????????', 'value' => (int) ($reportData['unique_staff_count'] ?? 0)],
        ['label' => $dailyMode === 'monthly' ? '???????????????' : '???????????', 'value' => (int) ($reportData['total_rows'] ?? count($reportData['logs'] ?? []))],
        ['label' => '??????????', 'value' => number_format((float) ($reportData['total_hours'] ?? 0), 2)],
        ['label' => '????????', 'value' => (int) ($reportData['approved_count'] ?? 0)],
    ];
    $footerLegendItems = [
        '? = ??????? ???? 08.30 - 16.30 ?.',
        '? = ??????? ???? 16.30 - 00.30 ?.',
        '? = ?????? ???? 00.30 - 08.30 ?.',
        'BD = ????????????????????',
    ];
    $signatureLabel = '?????????????';
    $notesBlockText = '????????????????????????????? ?????????????????????????????????????????????????????????????????';

    if ($dailyMode === 'monthly') {
        $reportLayout = 'monthly_matrix';
        $monthlyDays = $reportData['matrix_days'] ?? [];
        $monthlyRows = $reportData['matrix_rows'] ?? [];
    } else {
        $tableHeaders = ['????????', '?????', '???????????????', '???????', '????', '?????????????', '????????'];
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
        :root{--ink:#16324f;--muted:#5b6f84;--line:#d9e2ea;--soft:#f5f8fb}
        *{box-sizing:border-box}
        body{margin:0;background:linear-gradient(180deg,#eef4f8,#f8fbfd);color:var(--ink);font-family:'Sarabun',sans-serif}
        .print-actions{width:min(1120px,calc(100% - 32px));margin:20px auto 0;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
        .print-actions button,.print-actions a{border:0;border-radius:999px;padding:12px 18px;text-decoration:none;font:inherit;cursor:pointer}
        .print-actions .primary{background:var(--ink);color:#fff}
        .print-actions .secondary{background:#fff;color:var(--ink);border:1px solid var(--line)}
        .page{width:min(1120px,calc(100% - 32px));margin:18px auto 32px;background:#fff;border-radius:30px;box-shadow:0 24px 56px rgba(16,36,59,.12);overflow:hidden}
        .page-head{padding:30px 34px 24px;background:linear-gradient(135deg,#16324f,#24595f);color:#fff}
        .brand-row{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}
        .brand{display:flex;align-items:center;gap:16px}
        .brand img{width:60px;height:60px;object-fit:contain;background:rgba(255,255,255,.12);border-radius:18px;padding:8px}
        .org-name,.report-heading h1,.summary-card .value{font-family:'Prompt',sans-serif}
        .org-name{font-size:1.25rem;font-weight:600}
        .system-name,.report-subtitle{color:rgba(255,255,255,.84)}
        .report-heading{margin-top:26px;display:grid;gap:10px}
        .report-heading h1{margin:0;font-size:clamp(1.75rem,2.6vw,2.55rem);line-height:1.15}
        .report-chip{display:inline-flex;align-items:center;gap:8px;width:fit-content;border-radius:999px;padding:10px 14px;background:rgba(255,255,255,.12);font-size:.92rem}
        .body{padding:30px 34px 36px}
        .summary-grid,.meta-grid{display:grid;gap:14px}
        .summary-grid{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:22px}
        .meta-grid{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:24px}
        .summary-card,.meta-box{border:1px solid var(--line);border-radius:20px;background:var(--soft);padding:16px 18px}
        .summary-card .label,.meta-box strong{display:block;color:var(--muted);font-size:.84rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
        .summary-card .value{margin-top:8px;font-size:1.65rem}
        .meta-box span{display:block;margin-top:8px;color:var(--ink)}
        .section-head{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:14px;flex-wrap:wrap}
        .section-head h2{margin:0;font-family:'Prompt',sans-serif;font-size:1.15rem}
        .section-head p{margin:4px 0 0;color:var(--muted)}
        table{width:100%;border-collapse:collapse;border-radius:18px;overflow:hidden}
        th,td{border:1px solid var(--line);padding:11px 12px;text-align:left;vertical-align:top;font-size:.94rem}
        th{background:#edf3f7;white-space:nowrap}
        .empty-state{text-align:center;padding:36px 20px;border:1px dashed var(--line);border-radius:20px;color:var(--muted)}
        @media print{body{background:#fff}.print-actions{display:none!important}.page{width:100%;margin:0;border-radius:0;box-shadow:none}tr,td,th,.summary-card,.meta-box{break-inside:avoid}thead{display:table-header-group}}
        @media (max-width:900px){.summary-grid,.meta-grid{grid-template-columns:1fr 1fr}}
        @media (max-width:640px){.summary-grid,.meta-grid{grid-template-columns:1fr}.page-head,.body{padding:24px 20px}}
    </style>
</head>
<body>
    <div class="print-actions">
        <a class="secondary" href="javascript:window.close()">ปิดหน้าต่าง</a>
        <button class="secondary" type="button" id="downloadPdfBtn">ดาวน์โหลด PDF</button>
        <button class="primary" type="button" onclick="window.print()">พิมพ์รายงาน</button>
    </div>

    <main class="page" id="reportSurface">
        <header class="page-head">
            <div class="brand-row">
                <div class="brand">
                    <img src="../LOGO/nongphok_logo.png" alt="Logo">
                    <div>
                        <div class="org-name"><?= htmlspecialchars($organizationName) ?></div>
                        <div class="system-name"><?= htmlspecialchars($systemName) ?></div>
                    </div>
                </div>
                <div class="report-chip"><?= htmlspecialchars($reportTypeLabel) ?></div>
            </div>

            <div class="report-heading">
                <h1><?= htmlspecialchars($title) ?></h1>
                <div class="report-subtitle"><?= htmlspecialchars($subtitle) ?></div>
            </div>
        </header>

        <section class="body">
            <div class="summary-grid">
                <?php foreach ($summaryCards as $card): ?>
                    <div class="summary-card">
                        <span class="label"><?= htmlspecialchars($card['label']) ?></span>
                        <span class="value"><?= htmlspecialchars((string) $card['value']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="meta-grid">
                <div class="meta-box">
                    <strong>จัดทำโดย</strong>
                    <span><?= htmlspecialchars($generatedBy) ?></span>
                </div>
                <div class="meta-box">
                    <strong>เวลาที่สร้างรายงาน</strong>
                    <span><?= htmlspecialchars($generatedAt) ?></span>
                </div>
                <div class="meta-box">
                    <strong>หมายเหตุ</strong>
                    <span>เอกสารนี้ใช้ข้อมูลชุดเดียวกับหน้ารายงานในระบบ เพื่อให้ตัวเลขบนหน้าจอ การพิมพ์ และไฟล์ที่ดาวน์โหลดตรงกัน</span>
                </div>
            </div>

            <div class="section-head">
                <div>
                    <h2>รายละเอียดรายงาน</h2>
                    <p>เหมาะสำหรับพิมพ์เอกสารราชการและบันทึกเป็นไฟล์ PDF</p>
                </div>
            </div>

            <?php if (!$tableRows): ?>
                <div class="empty-state">ไม่พบข้อมูลสำหรับรายงานนี้</div>
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
        </section>
    </main>

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
            const pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
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
