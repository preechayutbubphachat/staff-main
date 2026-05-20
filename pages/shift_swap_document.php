<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shift_swap_service.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$swapRequestId = (int) ($_GET['id'] ?? 0);
$request = $swapRequestId > 0 ? app_shift_swap_get_request($conn, $swapRequestId) : null;
if (!$request || !app_shift_swap_user_can_view_document($conn, $request, $currentUserId)) {
    http_response_code(403);
    echo 'ไม่สามารถเข้าถึงเอกสารคำขอแลกเวรนี้ได้';
    exit;
}

$document = app_shift_swap_get_document($conn, $swapRequestId) ?: [];
$types = app_shift_schedule_types();

function swap_doc_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function swap_doc_time(array $request, string $prefix): string
{
    return substr((string) ($request[$prefix . '_start_time'] ?? ''), 0, 5) . '-' . substr((string) ($request[$prefix . '_end_time'] ?? ''), 0, 5);
}

function swap_doc_shift_label(array $request, string $prefix, array $types): string
{
    $shiftType = (string) ($request[$prefix . '_shift_type'] ?? '');
    return (string) ($types[$shiftType]['label'] ?? $shiftType);
}

function swap_doc_signature_img(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '<span class="swap-doc-empty-sign">ยังไม่มีลายเซ็น</span>';
    }

    return '<img src="../' . swap_doc_h($path) . '" alt="ลายเซ็น" class="swap-doc-signature-img">';
}

function swap_doc_date_parts(string $date): array
{
    if ($date === '') {
        return ['day' => '..........', 'month' => '....................', 'year' => '..........'];
    }
    $ts = strtotime($date);
    if (!$ts) {
        return ['day' => '..........', 'month' => '....................', 'year' => '..........'];
    }
    $months = app_thai_month_names();
    return [
        'day' => (string) (int) date('j', $ts),
        'month' => $months[(int) date('n', $ts)] ?? '',
        'year' => (string) ((int) date('Y', $ts) + 543),
    ];
}

$today = swap_doc_date_parts(date('Y-m-d'));
$requesterShiftDate = swap_doc_date_parts((string) ($request['requester_date'] ?? ''));
$targetShiftDate = swap_doc_date_parts((string) ($request['target_date'] ?? ''));

$requesterName = (string) ($document['requester_name_snapshot'] ?? $request['requester_name'] ?? '');
$responderName = (string) ($document['responder_name_snapshot'] ?? $request['target_name'] ?? '');
$approverName = (string) ($document['approver_name_snapshot'] ?? $request['approver_name'] ?? '');
$requesterPosition = (string) ($document['requester_position_snapshot'] ?? $request['requester_position'] ?? '-');
$responderPosition = (string) ($document['responder_position_snapshot'] ?? $request['target_position'] ?? '-');
$approverPosition = (string) ($document['approver_position_snapshot'] ?? $request['approver_position'] ?? 'หัวหน้างาน');
$requesterDepartment = (string) ($document['requester_department_snapshot'] ?? $request['requester_department_name'] ?? $request['department_name'] ?? '-');
$responderDepartment = (string) ($document['responder_department_snapshot'] ?? $request['target_department_name'] ?? $request['department_name'] ?? '-');
$reason = trim((string) ($document['reason_snapshot'] ?? $request['reason'] ?? 'มีเหตุจำเป็น'));
$status = (string) ($request['status'] ?? '');
$allowed = in_array($status, ['applied', 'approved'], true);
$rejected = in_array($status, ['rejected_by_target', 'rejected_by_manager'], true);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แบบขอเปลี่ยนเวร #<?= (int) $swapRequestId ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eaf7f8; color: #111827; font-family: "Sarabun", sans-serif; }
        .swap-doc-toolbar { position: sticky; top: 0; z-index: 20; display: flex; justify-content: center; gap: 10px; padding: 14px; background: rgba(234,247,248,.88); backdrop-filter: blur(14px); }
        .swap-doc-btn { border: 1px solid #cfe7e9; border-radius: 999px; background: #fff; color: #063b4f; padding: 10px 16px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .swap-doc-btn.primary { background: #063b4f; color: #fff; border-color: #063b4f; }
        .swap-doc-page { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 22mm 20mm 18mm; background: #fff; box-shadow: 0 22px 70px rgba(6,59,79,.16); font-size: 16px; line-height: 1.85; }
        .swap-doc-title { text-align: center; font-size: 22px; font-weight: 700; margin: 0 0 18px; }
        .swap-doc-right { text-align: right; }
        .swap-doc-line { border-bottom: 1px dotted #111827; display: inline-block; min-width: 120px; padding: 0 8px; text-align: center; line-height: 1.45; }
        .swap-doc-line.long { min-width: 290px; }
        .swap-doc-paragraph { text-indent: 3.5rem; margin: 18px 0; }
        .swap-doc-sign-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 26px; margin-top: 28px; }
        .swap-doc-sign-box { min-height: 138px; text-align: center; }
        .swap-doc-signature-img { max-width: 210px; max-height: 72px; object-fit: contain; display: inline-block; }
        .swap-doc-empty-sign { display: inline-flex; align-items: center; justify-content: center; width: 210px; height: 72px; color: #94a3b8; border-bottom: 1px dotted #111827; }
        .swap-doc-review { display: grid; grid-template-columns: repeat(3, 1fr); border: 1px solid #111827; margin-top: 28px; }
        .swap-doc-review > div { min-height: 132px; padding: 10px; border-left: 1px solid #111827; }
        .swap-doc-review > div:first-child { border-left: 0; }
        .swap-doc-check { display: inline-block; width: 14px; height: 14px; border: 1px solid #111827; margin: 0 6px; vertical-align: middle; }
        .swap-doc-check.checked::after { content: "✓"; display: block; font-size: 14px; line-height: 12px; text-align: center; }
        .swap-doc-note { margin-top: 14px; color: #475569; font-size: 13px; }
        @media print {
            body { background: #fff; }
            .swap-doc-toolbar { display: none; }
            .swap-doc-page { margin: 0; width: 210mm; min-height: 297mm; box-shadow: none; page-break-after: avoid; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="swap-doc-toolbar">
        <a class="swap-doc-btn" href="shift-swap-requests.php?highlight=<?= (int) $swapRequestId ?>">กลับหน้าคำขอแลกเวร</a>
        <button type="button" class="swap-doc-btn primary" onclick="window.print()">พิมพ์ / ดาวน์โหลด PDF</button>
    </div>
    <main class="swap-doc-page" id="swapDocPage">
        <h1 class="swap-doc-title">แบบขอเปลี่ยนเวร</h1>
        <p class="swap-doc-right">เขียนที่ โรงพยาบาลหนองพอก</p>
        <p class="swap-doc-right">
            วันที่ <span class="swap-doc-line"><?= swap_doc_h($today['day']) ?></span>
            เดือน <span class="swap-doc-line"><?= swap_doc_h($today['month']) ?></span>
            พ.ศ. <span class="swap-doc-line"><?= swap_doc_h($today['year']) ?></span>
        </p>
        <p><strong>เรียน</strong> ผู้อำนวยการโรงพยาบาลหนองพอก</p>
        <p class="swap-doc-paragraph">
            ข้าพเจ้า <span class="swap-doc-line long"><?= swap_doc_h($requesterName) ?></span>
            ตำแหน่ง <span class="swap-doc-line long"><?= swap_doc_h($requesterPosition) ?></span>
            แผนก <span class="swap-doc-line long"><?= swap_doc_h($requesterDepartment) ?></span>
            ได้อยู่เวรในวันที่ <span class="swap-doc-line"><?= swap_doc_h($requesterShiftDate['day']) ?></span>
            เดือน <span class="swap-doc-line"><?= swap_doc_h($requesterShiftDate['month']) ?></span>
            พ.ศ. <span class="swap-doc-line"><?= swap_doc_h($requesterShiftDate['year']) ?></span>
            กะ <?= swap_doc_h(swap_doc_shift_label($request, 'requester', $types)) ?> เวลา <?= swap_doc_h(swap_doc_time($request, 'requester')) ?>
        </p>
        <p class="swap-doc-paragraph">
            เนื่องจาก <?= swap_doc_h($reason) ?> ข้าพเจ้ามีเหตุจำเป็น ไม่สามารถปฏิบัติหน้าที่ตามคำสั่งโรงพยาบาลหนองพอกได้
            จึงขอเปลี่ยนเวรกับ <span class="swap-doc-line long"><?= swap_doc_h($responderName) ?></span>
            ตำแหน่ง <span class="swap-doc-line long"><?= swap_doc_h($responderPosition) ?></span>
            สำนัก/กอง <span class="swap-doc-line long"><?= swap_doc_h($responderDepartment) ?></span>
            และจะอยู่เวรแทนในวันที่ <span class="swap-doc-line"><?= swap_doc_h($targetShiftDate['day']) ?></span>
            เดือน <span class="swap-doc-line"><?= swap_doc_h($targetShiftDate['month']) ?></span>
            พ.ศ. <span class="swap-doc-line"><?= swap_doc_h($targetShiftDate['year']) ?></span>
            กะ <?= swap_doc_h(swap_doc_shift_label($request, 'target', $types)) ?> เวลา <?= swap_doc_h(swap_doc_time($request, 'target')) ?>
        </p>
        <p class="swap-doc-paragraph">จึงเรียนมาเพื่อโปรดพิจารณา</p>

        <section class="swap-doc-sign-grid">
            <div class="swap-doc-sign-box">
                <div>(ลงชื่อ) <?= swap_doc_signature_img($document['requester_signature_path'] ?? null) ?> ผู้ขอเปลี่ยนเวร</div>
                <div>(<?= swap_doc_h($requesterName) ?>)</div>
                <div>ผู้ขอเปลี่ยนเวร</div>
            </div>
            <div class="swap-doc-sign-box">
                <div>(ลงชื่อ) <?= swap_doc_signature_img($document['responder_signature_path'] ?? null) ?> ผู้ยินยอมเปลี่ยนเวร</div>
                <div>(<?= swap_doc_h($responderName) ?>)</div>
                <div>ผู้ยินยอมเปลี่ยนเวร</div>
            </div>
        </section>

        <section class="swap-doc-sign-box" style="margin-top: 22px;">
            <div>(ลงชื่อ) <?= swap_doc_signature_img($document['approver_signature_path'] ?? null) ?> หัวหน้างานแผนก</div>
            <div>(<?= swap_doc_h($approverName !== '' ? $approverName : '................................') ?>)</div>
            <div>ตำแหน่ง <?= swap_doc_h($approverPosition) ?></div>
        </section>

        <section class="swap-doc-review">
            <div>
                หัวหน้างานได้พิจารณาแล้วสมควร<br>
                <span class="swap-doc-check <?= $allowed ? 'checked' : '' ?>"></span> อนุญาต
                <span class="swap-doc-check <?= $rejected ? 'checked' : '' ?>"></span> ไม่อนุญาต
                <p class="swap-doc-note"><?= swap_doc_h((string) ($request['manager_response_note'] ?? '')) ?></p>
            </div>
            <div>
                เห็นควร
                <span class="swap-doc-check <?= $allowed ? 'checked' : '' ?>"></span> อนุญาต
                <span class="swap-doc-check <?= $rejected ? 'checked' : '' ?>"></span> ไม่อนุญาต
            </div>
            <div>
                ผู้บริหาร/ผู้มีอำนาจอนุมัติ<br>
                <span class="swap-doc-check"></span> อนุญาต
                <span class="swap-doc-check"></span> ไม่อนุญาต
                <p class="swap-doc-note">ส่วนนี้จะแสดงเมื่อระบบมีขั้นตอนผู้บริหารเพิ่มเติม</p>
            </div>
        </section>
    </main>
</body>
</html>
