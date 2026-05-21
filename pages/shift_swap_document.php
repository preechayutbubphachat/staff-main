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

function swap_doc_approver_status_label(array $request, array $document): string
{
    $requestStatus = (string) ($request['status'] ?? '');
    $documentStatus = (string) ($document['document_status'] ?? '');

    if (in_array($requestStatus, ['approved', 'applied'], true) || $documentStatus === 'complete') {
        return 'อนุมัติ';
    }

    if ($requestStatus === 'rejected_by_manager' || $documentStatus === 'approver_rejected') {
        return 'ไม่อนุมัติ';
    }

    if (in_array($requestStatus, ['pending_target_confirm', 'pending_manager_approval'], true)
        || in_array($documentStatus, ['requester_signed', 'responder_signed'], true)) {
        return 'รอพิจารณา';
    }

    return '';
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
$approverPosition = trim($approverPosition) !== '' ? trim($approverPosition) : 'หัวหน้างาน';
$requesterDepartment = (string) ($document['requester_department_snapshot'] ?? $request['requester_department_name'] ?? $request['department_name'] ?? '-');
$responderDepartment = (string) ($document['responder_department_snapshot'] ?? $request['target_department_name'] ?? $request['department_name'] ?? '-');
$approverStatusLabel = swap_doc_approver_status_label($request, $document);
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
        .swap-doc-page { width: 203.7mm; min-height: 282mm; margin: 16px auto; padding: 18mm 17mm 13mm; background: #fff; box-shadow: 0 22px 70px rgba(6,59,79,.16); font-size: 15.4px; line-height: 1.58; }
        .swap-doc-title { text-align: center; font-size: 21px; font-weight: 700; margin: 0 0 14px; }
        .swap-doc-right { text-align: right; }
        .swap-doc-line { border-bottom: 1px dotted #111827; display: inline-block; min-width: 84px; padding: 0 6px; text-align: center; line-height: 1.35; vertical-align: baseline; }
        .swap-doc-line.short { min-width: 64px; }
        .swap-doc-line.mid { min-width: 140px; }
        .swap-doc-line.long { min-width: 210px; }
        .swap-doc-line.month { min-width: 112px; }
        .swap-doc-line.year { min-width: 72px; }
        .swap-doc-paragraph { text-indent: 3.2rem; margin: 13px 0; }
        .swap-doc-paragraph-line { display: block; text-indent: 0; margin-top: 3px; }
        .swap-doc-date-run,
        .swap-doc-shift-run { display: inline-flex; align-items: baseline; gap: 4px; white-space: nowrap; }
        .swap-doc-shift-run { margin-left: 4px; }
        .swap-doc-sign-stack { display: grid; gap: 13px; width: 63%; margin-top: 42px; margin-left: auto; }
        .swap-doc-sign-box { min-height: 70px; text-align: center; }
        .swap-doc-approver-sign-box { margin-top: 26px; }
        .swap-doc-sign-row { display: grid; grid-template-columns: 54px 185px minmax(112px, 1fr); align-items: center; column-gap: 8px; white-space: nowrap; }
        .swap-doc-sign-label { text-align: left; }
        .swap-doc-sign-role { text-align: left; }
        .swap-doc-signature-img { width: 185px; max-height: 42px; object-fit: contain; display: inline-block; justify-self: center; }
        .swap-doc-empty-sign { display: inline-flex; align-items: center; justify-content: center; width: 185px; height: 40px; color: #94a3b8; border-bottom: 1px dotted #111827; font-size: 13px; justify-self: center; }
        .swap-doc-name-line { width: 185px; margin: 3px 0 0 62px; }
        .swap-doc-approver-meta-line { display: grid; grid-template-columns: 54px 185px minmax(112px, 1fr); align-items: baseline; column-gap: 8px; margin-top: 2px; font-size: 14.4px; }
        .swap-doc-approver-meta-label { text-align: left; white-space: nowrap; }
        .swap-doc-approver-meta-value { grid-column: 2 / 4; text-align: left; }
        @media print {
            body { background: #fff; }
            .swap-doc-toolbar { display: none; }
            .swap-doc-page { margin: 0; width: 100%; min-height: auto; padding: 9mm 10mm 8mm; box-shadow: none; page-break-after: avoid; }
            @page { size: A4 portrait; margin: 8mm 10mm; }
        }
    </style>
    <script>
        function closeSwapDocumentPage() {
            window.close();
            window.setTimeout(function () {
                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = 'shift-swap-requests.php?highlight=<?= (int) $swapRequestId ?>';
                }
            }, 180);
        }
    </script>
</head>
<body>
    <div class="swap-doc-toolbar">
        <button type="button" class="swap-doc-btn" onclick="closeSwapDocumentPage()">ปิดหน้านี้</button>
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
            ตำแหน่ง <span class="swap-doc-line long"><?= swap_doc_h($requesterPosition) ?></span><br>
            แผนก <span class="swap-doc-line long"><?= swap_doc_h($requesterDepartment) ?></span>
            ได้อยู่เวรในวันที่ <span class="swap-doc-line short"><?= swap_doc_h($requesterShiftDate['day']) ?></span>
            เดือน <span class="swap-doc-line mid"><?= swap_doc_h($requesterShiftDate['month']) ?></span>
            พ.ศ. <span class="swap-doc-line"><?= swap_doc_h($requesterShiftDate['year']) ?></span>
            กะ <?= swap_doc_h(swap_doc_shift_label($request, 'requester', $types)) ?> เวลา <?= swap_doc_h(swap_doc_time($request, 'requester')) ?>
        </p>
        <p class="swap-doc-paragraph">
            เนื่องจาก ข้าพเจ้ามีเหตุจำเป็น ไม่สามารถปฏิบัติหน้าที่ตามคำสั่งโรงพยาบาลหนองพอกได้
            จึงขอเปลี่ยนเวรกับ <span class="swap-doc-line long"><?= swap_doc_h($responderName) ?></span>
            ตำแหน่ง <span class="swap-doc-line mid"><?= swap_doc_h($responderPosition) ?></span>
            <span class="swap-doc-paragraph-line">
                แผนก/กลุ่มงาน <span class="swap-doc-line mid"><?= swap_doc_h($responderDepartment) ?></span>
                <span class="swap-doc-date-run">และจะอยู่เวรแทนในวันที่ <span class="swap-doc-line short"><?= swap_doc_h($targetShiftDate['day']) ?></span>
                เดือน <span class="swap-doc-line month"><?= swap_doc_h($targetShiftDate['month']) ?></span>
                พ.ศ. <span class="swap-doc-line year"><?= swap_doc_h($targetShiftDate['year']) ?></span></span>
                <span class="swap-doc-shift-run">กะ <?= swap_doc_h(swap_doc_shift_label($request, 'target', $types)) ?> เวลา <?= swap_doc_h(swap_doc_time($request, 'target')) ?></span>
            </span>
        </p>
        <p class="swap-doc-paragraph">จึงเรียนมาเพื่อโปรดพิจารณา</p>

        <section class="swap-doc-sign-stack">
            <div class="swap-doc-sign-box">
                <div class="swap-doc-sign-row"><span class="swap-doc-sign-label">(ลงชื่อ)</span> <?= swap_doc_signature_img($document['requester_signature_path'] ?? null) ?> <span class="swap-doc-sign-role">ผู้ขอเปลี่ยนเวร</span></div>
                <div class="swap-doc-name-line">(<?= swap_doc_h($requesterName) ?>)</div>
            </div>
            <div class="swap-doc-sign-box">
                <div class="swap-doc-sign-row"><span class="swap-doc-sign-label">(ลงชื่อ)</span> <?= swap_doc_signature_img($document['responder_signature_path'] ?? null) ?> <span class="swap-doc-sign-role">ผู้ยินยอมเปลี่ยนเวร</span></div>
                <div class="swap-doc-name-line">(<?= swap_doc_h($responderName) ?>)</div>
            </div>
            <div class="swap-doc-sign-box swap-doc-approver-sign-box">
                <div class="swap-doc-sign-row"><span class="swap-doc-sign-label">(ลงชื่อ)</span> <?= swap_doc_signature_img($document['approver_signature_path'] ?? null) ?> <span class="swap-doc-sign-role">หัวหน้างานแผนก</span></div>
                <div class="swap-doc-name-line">(<?= swap_doc_h($approverName !== '' ? $approverName : '................................') ?>)</div>
                <?php if ($approverStatusLabel !== ''): ?>
                    <div class="swap-doc-approver-meta-line"><span class="swap-doc-approver-meta-label">(สถานะ)</span><span class="swap-doc-approver-meta-value"><?= swap_doc_h($approverStatusLabel) ?></span></div>
                <?php endif; ?>
                <div class="swap-doc-approver-meta-line"><span class="swap-doc-approver-meta-label">ตำแหน่ง</span><span class="swap-doc-approver-meta-value"><?= swap_doc_h($approverPosition) ?></span></div>
            </div>
        </section>
    </main>
</body>
</html>
