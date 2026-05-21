<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shift_cover_service.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$coverRequestId = (int) ($_GET['id'] ?? 0);
$request = app_shift_cover_get_request($conn, $coverRequestId);
if (!$request || !app_shift_cover_can_view($conn, $request, $currentUserId)) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์ดูเอกสารนี้');
}
$document = app_shift_cover_get_document($conn, $coverRequestId) ?: [];
$assignment = app_shift_swap_get_assignment($conn, (int) $request['source_assignment_id']) ?: [];
$summary = $assignment ? app_shift_swap_assignment_summary($assignment) : [];
$statusMeta = app_shift_cover_status_meta((string) $request['status']);

function cover_signature_img(?string $fileName): string
{
    $fileName = trim((string) $fileName);
    if ($fileName === '') {
        return '<span class="signature-missing">ยังไม่มีลายเซ็น</span>';
    }
    return '<img src="../uploads/shift_cover_signatures/' . htmlspecialchars(rawurlencode($fileName)) . '" alt="ลายเซ็น">';
}

function cover_doc_thai_date(string $date): array
{
    $dt = new DateTimeImmutable($date !== '' ? $date : 'now');
    $months = app_get_thai_month_select_options();
    return [
        'day' => (string) (int) $dt->format('j'),
        'month' => (string) ($months[(int) $dt->format('n')] ?? ''),
        'year' => (string) ((int) $dt->format('Y') + 543),
    ];
}

$todayParts = cover_doc_thai_date(date('Y-m-d'));
$shiftParts = cover_doc_thai_date((string) ($assignment['schedule_date'] ?? date('Y-m-d')));
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แบบขอแทนเวร #<?= (int) $coverRequestId ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page{size:A4 portrait;margin:12mm}
        *{box-sizing:border-box}
        body{margin:0;background:#e9f7f8;color:#111827;font-family:Sarabun,Prompt,sans-serif;font-size:16px;line-height:1.55}
        .toolbar{display:flex;justify-content:center;gap:.75rem;padding:1rem}
        .toolbar button,.toolbar a{border:0;border-radius:999px;background:#063b4f;color:#fff;padding:.7rem 1.1rem;font-weight:800;text-decoration:none;cursor:pointer}
        .paper{width:210mm;min-height:297mm;margin:0 auto 1.5rem;background:#fff;padding:23mm 24mm 18mm;box-shadow:0 20px 50px rgba(6,59,79,.14)}
        h1{margin:0 0 14mm;text-align:center;font-family:Prompt,Sarabun,sans-serif;font-size:20px}
        .right{text-align:right}
        .line{display:inline-block;min-width:30mm;border-bottom:1px dotted #333;text-align:center;padding:0 2mm}
        .para{margin-top:7mm;text-align:left}
        .indent{text-indent:14mm}
        .status{font-weight:800;color:#075985}
        .signatures{width:64%;margin-left:auto;margin-top:20mm;display:grid;gap:13mm}
        .signature-row{display:grid;grid-template-columns:18mm 42mm 1fr;align-items:center;column-gap:3mm}
        .signature-line{height:16mm;border-bottom:1px solid #333;display:flex;align-items:center;justify-content:center}
        .signature-line img{max-width:40mm;max-height:14mm;object-fit:contain}
        .signature-role{font-weight:700;text-align:left}
        .signature-name{grid-column:2;text-align:center;margin-top:2mm}
        .signature-position{grid-column:1 / -1;margin-top:2mm;text-align:left}
        .signature-missing{font-size:12px;color:#64748b}
        @media print{body{background:#fff}.toolbar{display:none}.paper{margin:0;box-shadow:none;width:auto;min-height:auto}}
    </style>
</head>
<body>
<div class="toolbar">
    <a href="shift-cover-requests.php">ย้อนกลับ</a>
    <button type="button" onclick="window.print()">พิมพ์/ดาวน์โหลด PDF</button>
</div>
<main class="paper">
    <h1>แบบขอให้ปฏิบัติหน้าที่แทนเวร</h1>
    <p class="right">เขียนที่ โรงพยาบาลหนองพอก</p>
    <p class="right">วันที่ <span class="line"><?= htmlspecialchars($todayParts['day']) ?></span> เดือน <span class="line"><?= htmlspecialchars($todayParts['month']) ?></span> พ.ศ. <span class="line"><?= htmlspecialchars($todayParts['year']) ?></span></p>
    <p>เรียน ผู้อำนวยการโรงพยาบาลหนองพอก</p>
    <p class="para indent">
        ข้าพเจ้า <span class="line"><?= htmlspecialchars((string) ($document['requester_name_snapshot'] ?? $request['requester_name'])) ?></span>
        ตำแหน่ง <span class="line"><?= htmlspecialchars((string) ($document['requester_position_snapshot'] ?? '')) ?></span>
        แผนก/กลุ่มงาน <span class="line"><?= htmlspecialchars((string) ($document['requester_department_snapshot'] ?? $request['department_name'])) ?></span>
        ได้รับมอบหมายให้อยู่เวรในวันที่ <span class="line"><?= htmlspecialchars($shiftParts['day']) ?></span>
        เดือน <span class="line"><?= htmlspecialchars($shiftParts['month']) ?></span>
        พ.ศ. <span class="line"><?= htmlspecialchars($shiftParts['year']) ?></span>
        กะ <span class="line"><?= htmlspecialchars((string) ($summary['shift_label'] ?? '-')) ?></span>
        เวลา <span class="line"><?= htmlspecialchars((string) ($summary['time'] ?? '-')) ?></span>
    </p>
    <p class="para indent">
        เนื่องจากข้าพเจ้ามีเหตุจำเป็น ไม่สามารถปฏิบัติหน้าที่ตามเวรดังกล่าวได้
        จึงขอให้ <span class="line"><?= htmlspecialchars((string) ($document['substitute_name_snapshot'] ?? $request['substitute_name'])) ?></span>
        ตำแหน่ง <span class="line"><?= htmlspecialchars((string) ($document['substitute_position_snapshot'] ?? '')) ?></span>
        แผนก/กลุ่มงาน <span class="line"><?= htmlspecialchars((string) ($document['substitute_department_snapshot'] ?? $request['department_name'])) ?></span>
        ปฏิบัติหน้าที่แทนในวันและเวลาดังกล่าว
    </p>
    <p class="para indent">จึงเรียนมาเพื่อโปรดพิจารณา <span class="status"><?= htmlspecialchars($statusMeta['label']) ?></span></p>
    <section class="signatures">
        <div class="signature-block">
            <div class="signature-row">
                <span>(ลงชื่อ)</span><span class="signature-line"><?= cover_signature_img($document['requester_signature_path'] ?? '') ?></span><span class="signature-role">ผู้ขอแทนเวร</span>
                <div class="signature-name">(<?= htmlspecialchars((string) ($document['requester_name_snapshot'] ?? $request['requester_name'])) ?>)</div>
            </div>
        </div>
        <div class="signature-block">
            <div class="signature-row">
                <span>(ลงชื่อ)</span><span class="signature-line"><?= cover_signature_img($document['substitute_signature_path'] ?? '') ?></span><span class="signature-role">ผู้ยินยอมแทนเวร</span>
                <div class="signature-name">(<?= htmlspecialchars((string) ($document['substitute_name_snapshot'] ?? $request['substitute_name'])) ?>)</div>
            </div>
        </div>
        <div class="signature-block">
            <div class="signature-row">
                <span>(ลงชื่อ)</span><span class="signature-line"><?= cover_signature_img($document['approver_signature_path'] ?? '') ?></span><span class="signature-role">หัวหน้างานแผนก</span>
                <div class="signature-name">(<?= htmlspecialchars((string) ($document['approver_name_snapshot'] ?? '')) ?>)</div>
                <div class="signature-position">ตำแหน่ง <?= htmlspecialchars((string) ($document['approver_position_snapshot'] ?? '')) ?></div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
