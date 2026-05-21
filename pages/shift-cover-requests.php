<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/profile_modal.php';
require_once __DIR__ . '/../includes/notification_helpers.php';
require_once __DIR__ . '/../includes/shift_cover_service.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$csrfToken = app_csrf_token('shift_swap');
$pageData = app_shift_cover_page_data($conn, $currentUserId);
$flash = (string) ($_SESSION['shift_cover_flash'] ?? '');
$flashType = (string) ($_SESSION['shift_cover_flash_type'] ?? 'success');
unset($_SESSION['shift_cover_flash'], $_SESSION['shift_cover_flash_type']);

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$currentUserId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$role = app_current_role();
$roleLabel = app_role_label($role);
$profileImageSrc = app_column_exists($conn, 'users', 'profile_image_path') ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$profileSignaturePath = trim((string) ($userMeta['signature_path'] ?? ''));
$profileSignatureSrc = $profileSignaturePath !== '' ? '../uploads/signatures/' . rawurlencode($profileSignaturePath) : '';
$displayName = app_user_display_name($userMeta ?: ['fullname' => $_SESSION['fullname'] ?? '-']);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');

function shift_cover_request_line(PDO $conn, array $row): string
{
    $assignment = app_shift_swap_get_assignment($conn, (int) $row['source_assignment_id']);
    if (!$assignment) {
        return 'ไม่พบเวรต้นทาง';
    }
    $summary = app_shift_swap_assignment_summary($assignment);

    return $summary['date'] . ' · ' . $summary['shift_label'] . ' · ' . $summary['time'];
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>คำขอแทนเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell my-shifts-page-shell">
<?php render_dashboard_sidebar('shift-cover-requests.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main my-shifts-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู"><i class="bi bi-list text-xl"></i></button>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Shift Cover Requests</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">ระบบแทนเวร</h1>
        </div>
        <?php render_notification_bell(); ?>
        <button type="button" class="dash-profile-button" data-profile-modal-trigger data-user-id="<?= $currentUserId ?>">
            <span class="dash-avatar"><?= htmlspecialchars(mb_substr($displayName !== '-' ? $displayName : 'U', 0, 1, 'UTF-8')) ?></span>
            <span class="hidden text-left sm:block">
                <span class="block max-w-[8rem] truncate font-semibold text-hospital-ink"><?= htmlspecialchars($displayName) ?></span>
                <span class="block text-xs text-hospital-muted"><?= htmlspecialchars($roleLabel) ?></span>
            </span>
        </button>
    </header>

    <div class="my-shifts-frame">
        <?php if ($flash !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($flashType) ?> rounded-4 border-0 shadow-sm" role="alert"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <section class="my-shifts-hero">
            <div class="my-shifts-hero-copy">
                <span class="dash-hero-pill"><i class="bi bi-person-plus"></i> Shift Cover</span>
                <h2>คำขอแทนเวร</h2>
                <p>ผู้แทนเวรไม่ต้องมีเวรมาแลก ระบบจะเปลี่ยนเจ้าของ assignment หลังผู้แทนยืนยันและหัวหน้าอนุมัติครบขั้นตอน</p>
            </div>
            <div class="my-shifts-stats">
                <div><span>ที่ฉันส่ง</span><strong><?= count($pageData['sent']) ?></strong></div>
                <div><span>รอฉันยืนยัน</span><strong><?= count($pageData['incoming']) ?></strong></div>
                <div><span>รออนุมัติ</span><strong><?= count($pageData['manager']) ?></strong></div>
                <div><span>ประวัติ</span><strong><?= count($pageData['history']) ?></strong></div>
            </div>
        </section>

        <section class="shift-swap-grid">
            <div class="shift-swap-panel" id="incoming">
                <div class="shift-swap-card-head"><h3>รอฉันยืนยันแทนเวร</h3></div>
                <?php if (!$pageData['incoming']): ?><div class="shift-swap-empty">ยังไม่มีคำขอแทนเวรที่รอคุณยืนยัน</div><?php endif; ?>
                <?php foreach ($pageData['incoming'] as $row): ?>
                    <article class="shift-swap-request-card">
                        <div class="shift-swap-request-top">
                            <span class="shift-swap-status is-pending-target">รอผู้แทนยืนยัน</span>
                            <strong>#<?= (int) $row['id'] ?> · แทนเวร</strong>
                        </div>
                        <p><strong>ผู้ขอ:</strong> <?= htmlspecialchars($row['requester_name']) ?> · <?= htmlspecialchars(shift_cover_request_line($conn, $row)) ?></p>
                        <p class="shift-swap-muted"><?= htmlspecialchars((string) ($row['reason'] ?? '')) ?></p>
                        <a class="dash-btn dash-btn-ghost" href="shift_cover_document.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> ดูเอกสาร</a>
                        <form method="post" action="../actions/respond-shift-cover.php" class="shift-swap-inline-decision" data-cover-signature-form data-signature-role="substitute" data-signature-input="substitute_signature_data" data-global-loading-form data-loading-message="โปรดรอสักครู่..." data-loading-sub-message="กำลังบันทึกผลคำขอแทนเวร" data-loading-busy-text="กำลังบันทึก...">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="cover_request_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="substitute_signature_data" value="">
                            <input type="hidden" name="use_profile_signature" value="0">
                            <input type="hidden" name="signature_source" value="drawn">
                            <textarea name="note" class="form-control" rows="2" placeholder="หมายเหตุถึงผู้ขอหรือหัวหน้า"></textarea>
                            <div>
                                <button type="submit" name="decision" value="confirm" class="dash-btn dash-btn-primary"><i class="bi bi-clipboard-check"></i> ยินยอมแทนเวร</button>
                                <button type="submit" name="decision" value="reject" class="dash-btn dash-btn-ghost"><i class="bi bi-x-circle"></i> ปฏิเสธ</button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shift-swap-panel" id="manager">
                <div class="shift-swap-card-head"><h3>รอหัวหน้าอนุมัติแทนเวร</h3></div>
                <?php if (!$pageData['manager']): ?><div class="shift-swap-empty">ยังไม่มีคำขอแทนเวรรออนุมัติ</div><?php endif; ?>
                <?php foreach ($pageData['manager'] as $row): ?>
                    <article class="shift-swap-request-card">
                        <div class="shift-swap-request-top">
                            <span class="shift-swap-status is-pending-manager">รออนุมัติ</span>
                            <strong>#<?= (int) $row['id'] ?> · <?= htmlspecialchars((string) ($row['department_name'] ?? '-')) ?></strong>
                        </div>
                        <p><strong>ผู้ขอ:</strong> <?= htmlspecialchars($row['requester_name']) ?> · <strong>ผู้แทน:</strong> <?= htmlspecialchars($row['substitute_name']) ?></p>
                        <p><?= htmlspecialchars(shift_cover_request_line($conn, $row)) ?></p>
                        <a class="dash-btn dash-btn-ghost" href="shift_cover_document.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> ดูเอกสาร</a>
                        <form method="post" action="../actions/manager-shift-cover.php" class="shift-swap-inline-decision" data-cover-signature-form data-signature-role="approver" data-signature-input="approver_signature_data" data-global-loading-form data-loading-message="โปรดรอสักครู่..." data-loading-sub-message="กำลังบันทึกผลอนุมัติแทนเวร" data-loading-busy-text="กำลังบันทึก...">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="cover_request_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="approver_signature_data" value="">
                            <input type="hidden" name="use_profile_signature" value="0">
                            <input type="hidden" name="signature_source" value="drawn">
                            <textarea name="note" class="form-control" rows="2" placeholder="หมายเหตุหัวหน้า"></textarea>
                            <div>
                                <button type="submit" name="decision" value="approve" class="dash-btn dash-btn-primary"><i class="bi bi-check2-circle"></i> อนุมัติ</button>
                                <button type="submit" name="decision" value="reject" class="dash-btn dash-btn-ghost"><i class="bi bi-x-circle"></i> ไม่อนุมัติ</button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="shift-swap-panel" id="history">
            <div class="shift-swap-card-head"><h3>ประวัติคำขอแทนเวร</h3></div>
            <?php if (!$pageData['sent'] && !$pageData['history']): ?><div class="shift-swap-empty">ยังไม่มีประวัติคำขอแทนเวร</div><?php endif; ?>
            <?php foreach (array_merge($pageData['sent'], $pageData['history']) as $row): ?>
                <?php $meta = app_shift_cover_status_meta((string) $row['status']); ?>
                <article class="shift-swap-history-row">
                    <span class="shift-swap-status is-<?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                    <strong>#<?= (int) $row['id'] ?> · แทนเวร</strong>
                    <span><?= htmlspecialchars($row['requester_name']) ?> → <?= htmlspecialchars($row['substitute_name']) ?> · <?= htmlspecialchars(shift_cover_request_line($conn, $row)) ?></span>
                    <a class="dash-btn dash-btn-ghost" href="shift_cover_document.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> ดูเอกสาร</a>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<style>
    .swap-signature-backdrop{position:fixed;inset:0;z-index:80;display:flex;align-items:center;justify-content:center;background:rgba(6,59,79,.36);padding:1rem;backdrop-filter:blur(8px)}
    .swap-signature-backdrop[hidden]{display:none}
    .swap-signature-modal{width:min(760px,100%);max-height:92vh;overflow:auto;border-radius:1.5rem;background:#fff;box-shadow:0 28px 80px rgba(6,59,79,.28);padding:1.25rem}
    .swap-signature-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:.85rem}
    .swap-signature-head p{margin:0;color:#0f9f95;font-size:.75rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
    .swap-signature-head h3{margin:.2rem 0 0;color:#082b45;font-family:Prompt,Sarabun,sans-serif;font-size:1.35rem;font-weight:800}
    .swap-signature-body{display:grid;gap:1rem;padding:1rem 0}
    .swap-signature-paper{border:1px solid #dbeafe;border-radius:1.25rem;background:#f8fcfd;padding:1rem;color:#334155;font-size:.9rem;line-height:1.7}
    .swap-signature-pad{border:1px dashed #9bd8d4;border-radius:1rem;background:#fff;padding:.75rem}
    .swap-signature-pad canvas{display:block;width:100%;height:160px;border-radius:.8rem;background:#fff;touch-action:none}
    .swap-signature-options{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center}
    .swap-signature-profile-toggle{display:inline-flex;align-items:center;gap:.45rem;width:max-content;max-width:100%;border:1px solid #a7f3d0;border-radius:999px;background:#f0fdfa;color:#075985;padding:.55rem .85rem;font:inherit;font-size:.88rem;font-weight:800;line-height:1.2;cursor:pointer}
    .swap-signature-profile-toggle[aria-pressed="true"]{border-color:#075985;background:#063b4f;color:#fff;box-shadow:0 10px 24px rgba(6,59,79,.18)}
    .swap-signature-profile-toggle:disabled{border-color:#e2e8f0;background:#f1f5f9;color:#64748b;cursor:not-allowed;box-shadow:none}
    .swap-signature-feedback{margin:0;color:#475569;font-size:.86rem;font-weight:700}
    .swap-signature-profile-preview{display:flex;align-items:center;gap:.75rem;border:1px solid #bae6fd;border-radius:1rem;background:#f0f9ff;padding:.75rem;color:#075985;font-size:.86rem;font-weight:800}
    .swap-signature-profile-preview[hidden]{display:none}
    .swap-signature-profile-preview img{display:block;width:190px;max-width:48%;height:64px;object-fit:contain;border-radius:.7rem;background:#fff;border:1px solid #e0f2fe}
    .swap-signature-draw-area{display:grid;gap:.75rem}
    .swap-signature-draw-area[hidden]{display:none}
    .swap-signature-error{border:1px solid #fecdd3;border-radius:1rem;background:#fff1f2;color:#be123c;padding:.75rem 1rem;font-size:.875rem;font-weight:700}
    .swap-signature-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.75rem;border-top:1px solid #e2e8f0;padding-top:1rem}
</style>
<div class="swap-signature-backdrop" data-cover-signature-modal hidden>
    <div class="swap-signature-modal" role="dialog" aria-modal="true" aria-labelledby="coverSignatureTitle">
        <div class="swap-signature-head">
            <div>
                <p>Shift Cover Document</p>
                <h3 id="coverSignatureTitle">แบบขอแทนเวร</h3>
            </div>
            <button type="button" class="dash-icon-button" data-cover-signature-close aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="swap-signature-body">
            <div class="swap-signature-paper">ระบบจะบันทึกเอกสารคำขอแทนเวรพร้อม snapshot ข้อมูลเวรและลายเซ็นของผู้ดำเนินการในขั้นตอนนี้</div>
            <button type="button" class="swap-signature-profile-toggle" data-cover-use-profile-signature data-has-profile-signature="<?= $profileSignaturePath !== '' ? '1' : '0' ?>" aria-pressed="false" <?= $profileSignaturePath === '' ? 'disabled' : '' ?>>
                <i class="bi bi-person-badge"></i>
                <span data-cover-profile-toggle-label><?= $profileSignaturePath !== '' ? 'ใช้ลายเซ็นจากโปรไฟล์' : 'ยังไม่มีลายเซ็นในโปรไฟล์' ?></span>
            </button>
            <p class="swap-signature-feedback" data-cover-signature-feedback><?= $profileSignaturePath !== '' ? 'กรุณาเลือกใช้ลายเซ็นโปรไฟล์ หรือวาดลายเซ็นด้านล่าง' : 'ยังไม่มีลายเซ็นในโปรไฟล์ กรุณาวาดลายเซ็นด้านล่าง' ?></p>
            <div class="swap-signature-profile-preview" data-cover-profile-preview hidden>
                <?php if ($profileSignatureSrc !== ''): ?><img src="<?= htmlspecialchars($profileSignatureSrc) ?>" alt="ลายเซ็นจากโปรไฟล์"><?php endif; ?>
                <span>กำลังใช้ลายเซ็นจากโปรไฟล์</span>
            </div>
            <div class="swap-signature-draw-area" data-cover-draw-area>
                <div class="swap-signature-pad"><canvas data-cover-signature-canvas width="720" height="180" aria-label="พื้นที่วาดลายเซ็น"></canvas></div>
                <div class="swap-signature-options"><button type="button" class="dash-btn dash-btn-ghost" data-cover-signature-clear><i class="bi bi-eraser"></i> ล้างลายเซ็น</button><span class="text-sm text-hospital-muted">วาดลายเซ็นด้วย mouse หรือ touch</span></div>
            </div>
            <div class="swap-signature-error" data-cover-signature-error hidden></div>
        </div>
        <div class="swap-signature-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-cover-signature-close>ยกเลิก</button>
            <button type="button" class="dash-btn dash-btn-primary" data-cover-signature-confirm><i class="bi bi-check2-circle"></i> ยืนยันและดำเนินการ</button>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/notifications.js"></script>
<?php render_staff_profile_modal_script(); ?>
<script>
(() => {
    const modal = document.querySelector('[data-cover-signature-modal]');
    const canvas = document.querySelector('[data-cover-signature-canvas]');
    if (!modal || !canvas) return;
    const ctx = canvas.getContext('2d');
    const errorBox = document.querySelector('[data-cover-signature-error]');
    const profileToggle = document.querySelector('[data-cover-use-profile-signature]');
    const profileToggleLabel = document.querySelector('[data-cover-profile-toggle-label]');
    const signatureFeedback = document.querySelector('[data-cover-signature-feedback]');
    const profilePreview = document.querySelector('[data-cover-profile-preview]');
    const drawArea = document.querySelector('[data-cover-draw-area]');
    let activeForm = null;
    let activeSubmitter = null;
    let allowSubmit = false;
    let drawing = false;
    let hasStroke = false;
    let useProfileSignature = false;
    function showError(message){ if(errorBox){ errorBox.textContent = message; errorBox.hidden = false; } }
    function clearError(){ if(errorBox){ errorBox.textContent = ''; errorBox.hidden = true; } }
    function clearPad(){ ctx.clearRect(0,0,canvas.width,canvas.height); hasStroke = false; }
    function hasProfileSignature(){ return profileToggle?.dataset.hasProfileSignature === '1' && !profileToggle.disabled; }
    function setSignatureMode(useProfile){
        useProfileSignature = Boolean(useProfile && hasProfileSignature());
        profileToggle?.setAttribute('aria-pressed', useProfileSignature ? 'true' : 'false');
        if (profileToggleLabel) profileToggleLabel.textContent = !hasProfileSignature() ? 'ยังไม่มีลายเซ็นในโปรไฟล์' : (useProfileSignature ? 'กำลังใช้ลายเซ็นจากโปรไฟล์' : 'ใช้ลายเซ็นจากโปรไฟล์');
        if (signatureFeedback) signatureFeedback.textContent = !hasProfileSignature() ? 'ยังไม่มีลายเซ็นในโปรไฟล์ กรุณาวาดลายเซ็นด้านล่าง' : (useProfileSignature ? 'กำลังใช้ลายเซ็นจากโปรไฟล์' : 'กรุณาวาดลายเซ็นด้านล่าง');
        if (drawArea) drawArea.hidden = useProfileSignature;
        if (profilePreview) profilePreview.hidden = !useProfileSignature;
    }
    function point(event){ const rect = canvas.getBoundingClientRect(); const source = event.touches?.[0] || event.changedTouches?.[0] || event; return { x:(source.clientX-rect.left)*(canvas.width/rect.width), y:(source.clientY-rect.top)*(canvas.height/rect.height) }; }
    function begin(event){ if(useProfileSignature) return; drawing = true; hasStroke = true; const p = point(event); ctx.beginPath(); ctx.moveTo(p.x,p.y); event.preventDefault(); }
    function move(event){ if(!drawing || useProfileSignature) return; const p = point(event); ctx.lineWidth=2.4; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.strokeStyle='#063b4f'; ctx.lineTo(p.x,p.y); ctx.stroke(); event.preventDefault(); }
    function end(){ drawing = false; }
    function open(form, submitter){ activeForm = form; activeSubmitter = submitter; clearError(); clearPad(); setSignatureMode(false); modal.hidden = false; document.body.classList.add('overflow-hidden'); }
    function close(){ modal.hidden = true; document.body.classList.remove('overflow-hidden'); activeForm = null; activeSubmitter = null; }
    function selectedDecision(){ return activeSubmitter?.value || activeForm?.querySelector('[name="decision"]')?.value || ''; }
    function signatureRequired(){ return selectedDecision() === 'confirm' || selectedDecision() === 'approve'; }
    setSignatureMode(false);
    canvas.addEventListener('mousedown', begin); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', begin, { passive:false }); canvas.addEventListener('touchmove', move, { passive:false }); canvas.addEventListener('touchend', end);
    document.querySelector('[data-cover-signature-clear]')?.addEventListener('click', () => { setSignatureMode(false); clearPad(); clearError(); });
    profileToggle?.addEventListener('click', () => { if(!hasProfileSignature()) return; setSignatureMode(!useProfileSignature); clearError(); });
    document.querySelectorAll('[data-cover-signature-close]').forEach((button) => button.addEventListener('click', close));
    document.querySelectorAll('[data-cover-signature-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (allowSubmit) { allowSubmit = false; return; }
            event.preventDefault();
            open(form, event.submitter || document.activeElement);
        });
    });
    document.querySelector('[data-cover-signature-confirm]')?.addEventListener('click', () => {
        if (!activeForm) return;
        const signatureSource = useProfileSignature && hasProfileSignature() ? 'profile' : 'drawn';
        if (signatureRequired() && signatureSource === 'drawn' && !hasStroke) {
            showError('กรุณาลงลายเซ็นก่อนดำเนินการ');
            return;
        }
        const inputName = activeForm.dataset.signatureInput || 'substitute_signature_data';
        const signatureInput = activeForm.querySelector('input[name="' + inputName.replace(/"/g, '') + '"]');
        const useProfileInput = activeForm.querySelector('[name="use_profile_signature"]');
        const signatureSourceInput = activeForm.querySelector('[name="signature_source"]');
        if (signatureInput) signatureInput.value = signatureSource === 'profile' || !hasStroke ? '' : canvas.toDataURL('image/png');
        if (useProfileInput) useProfileInput.value = signatureSource === 'profile' ? '1' : '0';
        if (signatureSourceInput) signatureSourceInput.value = signatureSource;
        allowSubmit = true;
        const form = activeForm;
        const submitter = activeSubmitter;
        close();
        if (submitter && form?.requestSubmit) form.requestSubmit(submitter); else form?.submit();
    });
})();
</script>
</body>
</html>
