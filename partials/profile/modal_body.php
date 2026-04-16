<?php
$assetBase = $assetBase ?? '../';
$displayName = app_user_display_name($user);
$avatarUrl = app_resolve_user_image_url($user['profile_image_path'] ?? '');
$signatureUrl = null;
$signatureName = trim((string) ($user['signature_path'] ?? ''));
if ($signatureName !== '' && is_file(__DIR__ . '/../../uploads/signatures/' . $signatureName)) {
    $signatureUrl = rtrim($assetBase, '/') . '/uploads/signatures/' . rawurlencode($signatureName);
}
?>
<div class="d-flex flex-wrap gap-3 align-items-center mb-4">
    <div style="width:96px;height:96px;border-radius:30px;overflow:hidden;position:relative;background:linear-gradient(135deg,rgba(16,36,59,.96),rgba(28,107,99,.88)),url('<?= htmlspecialchars(rtrim($assetBase, '/') . '/LOGO/nongphok_logo.png') ?>') center/54px no-repeat;">
        <?php if ($avatarUrl): ?>
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="รูปประจำตัว" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
            <div style="position:absolute;inset:0;display:grid;place-items:center;color:#fff;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,0)),linear-gradient(135deg,rgba(16,36,59,.96),rgba(28,107,99,.88));font-size:2.4rem;"><i class="bi bi-person-circle"></i></div>
        <?php endif; ?>
    </div>
    <div>
        <div class="h4 mb-1"><?= htmlspecialchars($displayName) ?></div>
        <div class="text-muted mb-2"><?= htmlspecialchars(app_role_label((string) ($user['role'] ?? 'staff'))) ?></div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($user['position_name'] ?: 'ไม่ระบุตำแหน่ง') ?></span>
            <span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($user['department_name'] ?: '-') ?></span>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="small text-muted">ชื่อผู้ใช้งาน</div>
        <div class="fw-semibold"><?= htmlspecialchars($user['username'] ?: '-') ?></div>
    </div>
    <div class="col-md-6">
        <div class="small text-muted">เบอร์โทร</div>
        <div class="fw-semibold"><?= htmlspecialchars($user['phone_number'] ?: '-') ?></div>
    </div>
    <div class="col-md-6">
        <div class="small text-muted">ชื่อ</div>
        <div class="fw-semibold"><?= htmlspecialchars(trim((string) ($user['first_name'] ?? '')) !== '' ? $user['first_name'] : '-') ?></div>
    </div>
    <div class="col-md-6">
        <div class="small text-muted">นามสกุล</div>
        <div class="fw-semibold"><?= htmlspecialchars(trim((string) ($user['last_name'] ?? '')) !== '' ? $user['last_name'] : '-') ?></div>
    </div>
    <div class="col-12">
        <div class="small text-muted mb-2">ลายเซ็นที่บันทึกไว้</div>
        <?php if ($signatureUrl): ?>
            <div class="rounded-4 border bg-light p-3">
                <img src="<?= htmlspecialchars($signatureUrl) ?>" alt="ลายเซ็น" style="max-width:100%;max-height:120px;display:block;">
            </div>
        <?php else: ?>
            <div class="rounded-4 border bg-light p-3 text-muted">ยังไม่มีลายเซ็นในระบบ</div>
        <?php endif; ?>
    </div>
</div>
