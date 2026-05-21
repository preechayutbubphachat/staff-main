<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_permission('can_manage_database');

$table    = trim((string) ($_GET['table'] ?? ''));
$config   = app_db_admin_require_table_allowed($table);
$isModal  = isset($_GET['modal']) && $_GET['modal'] === '1';

if (empty($config['create_allowed'])) {
    if ($isModal) {
        echo '<div style="padding:24px;font-family:Sarabun,sans-serif;color:#dc3545;">ตารางนี้ไม่อนุญาตให้เพิ่มข้อมูล</div>';
        exit;
    }
    header('Location: db_table_browser.php?table=' . urlencode($table));
    exit;
}

$actorId   = (int) ($_SESSION['id'] ?? 0);
$actorName = (string) ($_SESSION['fullname'] ?? '');
$csrfToken = app_csrf_token('db_row_create');
$message   = '';
$messageType = 'success';
$savedOk   = false;

$form = [];
foreach ($config['createable_columns'] as $column) {
    $form[$column] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'db_row_create')) {
        $message     = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } else {
        foreach ($config['createable_columns'] as $column) {
            $form[$column] = (string) ($_POST[$column] ?? '');
        }
        $validation = app_db_admin_validate_payload($conn, $config, $_POST, true);
        if ($validation['errors']) {
            $message     = implode(' ', $validation['errors']);
            $messageType = 'danger';
        } else {
            try {
                $newId = app_db_admin_insert_row($conn, $table, $config, $validation['payload'], $actorId, $actorName);
                if ($isModal) {
                    $savedOk = true;
                } else {
                    $_SESSION['db_admin_flash']      = 'เพิ่มข้อมูลเรียบร้อยแล้ว';
                    $_SESSION['db_admin_flash_type'] = 'success';
                    header('Location: db_row_edit.php?table=' . urlencode($table) . '&id=' . $newId);
                    exit;
                }
            } catch (Throwable $exception) {
                $message     = $exception->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

$fieldCount  = count($config['createable_columns']);
$formAction  = 'db_row_create.php?table=' . urlencode($table) . ($isModal ? '&modal=1' : '');
$tableLabel  = htmlspecialchars($config['label'] ?? $table);
$tableDesc   = htmlspecialchars($config['description'] ?? 'เพิ่มข้อมูลใหม่ในตาราง');

$tableIcon = 'bi-table';
if ($table === 'departments') $tableIcon = 'bi-diagram-3';
if ($table === 'users')       $tableIcon = 'bi-people';
if ($table === 'time_logs')   $tableIcon = 'bi-clock-history';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เพิ่มข้อมูล</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box}
body{font-family:'Sarabun',sans-serif;color:#10243b;margin:0}
h1,h2,h3,h4{font-family:'Prompt',sans-serif}
.form-control,.form-select{border-radius:12px;padding:10px 14px;border-color:#d1dce5}
.form-control:focus,.form-select:focus{border-color:#1cb8a1;box-shadow:0 0 0 3px rgba(28,184,161,.14)}
.panel{background:rgba(255,255,255,.94);border:1px solid rgba(16,36,59,.08);border-radius:20px;box-shadow:0 12px 32px rgba(16,36,59,.07);padding:28px}
/* MODAL */
body.is-modal-body{background:#fff;padding:0}
.modal-shell{display:flex;flex-direction:column;height:100vh;overflow:hidden}
.modal-header-bar{padding:16px 24px 13px;border-bottom:1px solid rgba(16,36,59,.09);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;background:#fff}
.modal-eyebrow{font-size:.68rem;font-weight:900;text-transform:uppercase;letter-spacing:.14em;color:#1cb8a1;margin-bottom:2px}
.modal-header-bar h2{font-size:1.05rem;margin:0;color:#10243b}
.modal-close-btn{background:none;border:none;font-size:1.15rem;cursor:pointer;color:#64748b;padding:4px 8px;border-radius:8px;line-height:1}
.modal-close-btn:hover{background:#f1f5f9;color:#10243b}
.modal-scroll-body{flex:1;overflow-y:auto;padding:0}
.modal-body-inner{display:grid;grid-template-columns:220px 1fr;gap:0;min-height:100%}
@media(max-width:600px){.modal-body-inner{grid-template-columns:1fr}.modal-preview-col{border-right:none!important;border-bottom:1px solid rgba(16,36,59,.09)}}
.modal-preview-col{padding:24px 20px;border-right:1px solid rgba(16,36,59,.09);background:#f8fafc}
.modal-form-col{padding:24px}
.preview-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(28,184,161,.1);color:#0e8c7c;border:1px solid rgba(28,184,161,.25);border-radius:999px;padding:4px 12px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px}
.preview-icon-wrap{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#10243b,#1a3a5c);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#1cb8a1;margin-bottom:14px}
.preview-table-name{font-size:1rem;font-weight:700;margin-bottom:6px;color:#10243b}
.preview-table-desc{font-size:.8rem;color:#64748b;line-height:1.5;margin-bottom:16px}
.preview-meta-item{background:#fff;border:1px solid rgba(16,36,59,.08);border-radius:10px;padding:8px 12px;margin-bottom:8px;font-size:.78rem}
.preview-meta-item .lbl{color:#94a3b8;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em}
.preview-meta-item .val{font-weight:700;color:#10243b}
.section-label{font-size:.7rem;font-weight:900;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:14px}
.modal-footer-bar{padding:13px 24px 16px;border-top:1px solid rgba(16,36,59,.09);display:flex;gap:10px;justify-content:flex-end;background:#fff;flex-shrink:0}
.btn-save{background:#10243b;color:#fff;border:none;border-radius:999px;padding:9px 24px;font-weight:600;font-size:.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:7px;font-family:inherit;transition:background .15s,opacity .15s}
.btn-save:hover:not(:disabled){background:#1a3a5c}
.btn-save:disabled{opacity:.6;cursor:not-allowed}
.btn-cancel{background:transparent;border:1.5px solid #d1dce5;color:#475569;border-radius:999px;padding:9px 22px;font-weight:500;font-size:.9rem;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-cancel:hover{background:#f1f5f9}
/* FULL PAGE */
body.is-full-body{background:linear-gradient(180deg,#f8fbfd,#eef4f8);min-height:100vh}
</style>
</head>
<body class="<?= $isModal ? 'is-modal-body' : 'is-full-body' ?>">
<?php if ($isModal): ?>
<div class="modal-shell" role="dialog" aria-modal="true" aria-labelledby="createModalTitle">
<div class="modal-header-bar">
  <div>
    <div class="modal-eyebrow">Admin Only &middot; Create Row</div>
    <h2 id="createModalTitle">เพิ่มข้อมูล: <?= $tableLabel ?></h2>
  </div>
  <button type="button" class="modal-close-btn"
          onclick="window.parent.postMessage({type:'db-user-edit-close'},window.location.origin)"
          aria-label="ปิด">
    <i class="bi bi-x-lg"></i>
  </button>
</div>

<?php if ($message !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-0 mb-0 px-4 py-2 small" style="flex-shrink:0">
  <i class="bi bi-<?= $messageType === 'danger' ? 'exclamation-triangle' : 'check-circle' ?> me-1"></i>
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="modal-scroll-body">
  <div class="modal-body-inner">
    <!-- Left preview -->
    <div class="modal-preview-col">
      <div class="preview-badge"><i class="bi bi-plus-circle"></i> เพิ่มข้อมูลใหม่</div>
      <div class="preview-icon-wrap"><i class="bi <?= $tableIcon ?>"></i></div>
      <div class="preview-table-name"><?= $tableLabel ?></div>
      <div class="preview-table-desc"><?= $tableDesc ?></div>
      <div class="preview-meta-item">
        <div class="lbl">ตาราง</div>
        <div class="val"><?= htmlspecialchars($table) ?></div>
      </div>
      <div class="preview-meta-item">
        <div class="lbl">จำนวน field</div>
        <div class="val"><?= $fieldCount ?> รายการ</div>
      </div>
      <div class="preview-meta-item">
        <div class="lbl">บันทึกโดย</div>
        <div class="val"><?= htmlspecialchars($actorName ?: 'ผู้ดูแลระบบ') ?></div>
      </div>
      <div style="margin-top:16px;padding:10px 12px;background:rgba(28,184,161,.06);border:1px solid rgba(28,184,161,.2);border-radius:10px;font-size:.75rem;color:#0e8c7c;line-height:1.5">
        <i class="bi bi-shield-check me-1"></i>บันทึก audit log ทุกครั้งอัตโนมัติ
      </div>
    </div>
    <!-- Right form -->
    <div class="modal-form-col">
      <p class="section-label">กรอกข้อมูล</p>
      <form method="post" action="<?= htmlspecialchars($formAction) ?>" id="createRowForm" class="row g-3" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
        <?php foreach ($config['createable_columns'] as $field): ?>
          <?php
          $meta  = $config['field_meta'][$field] ?? ['label' => $field, 'type' => 'text'];
          $ftype = $meta['type'] ?? 'text';
          $req   = !empty($meta['required']);
          $colClass = ($ftype === 'textarea') ? 'col-12' : 'col-12 col-sm-6';
          ?>
          <div class="<?= $colClass ?>">
            <label class="form-label fw-semibold small" for="f_<?= htmlspecialchars($field) ?>">
              <?= htmlspecialchars($meta['label'] ?? $field) ?>
              <?php if ($req): ?><span class="text-danger ms-1">*</span><?php endif; ?>
            </label>
            <?php if ($ftype === 'select'): ?>
              <?php $opts = app_db_admin_field_options($conn, $meta); ?>
              <select id="f_<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>"
                      class="form-select" <?= $req ? 'required' : '' ?>>
                <option value="">— เลือก —</option>
                <?php foreach ($opts as $opt): ?>
                  <option value="<?= htmlspecialchars((string)$opt['value']) ?>"
                    <?= (string)$form[$field] === (string)$opt['value'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($ftype === 'textarea'): ?>
              <textarea id="f_<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>"
                        class="form-control" rows="4" <?= $req ? 'required' : '' ?>><?= htmlspecialchars($form[$field]) ?></textarea>
            <?php elseif ($ftype === 'boolean'): ?>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="f_<?= htmlspecialchars($field) ?>"
                       name="<?= htmlspecialchars($field) ?>" value="1"
                       <?= (int)$form[$field] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label small" for="f_<?= htmlspecialchars($field) ?>">เปิดใช้งาน</label>
              </div>
            <?php else: ?>
              <input type="<?= htmlspecialchars($ftype) ?>"
                     id="f_<?= htmlspecialchars($field) ?>"
                     name="<?= htmlspecialchars($field) ?>"
                     class="form-control"
                     value="<?= htmlspecialchars($form[$field]) ?>"
                     <?= $req ? 'required' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </form>
    </div>
  </div>
</div>

<div class="modal-footer-bar">
  <button type="button" class="btn-cancel"
          onclick="window.parent.postMessage({type:'db-user-edit-close'},window.location.origin)">
    ยกเลิก
  </button>
  <button type="submit" form="createRowForm" class="btn-save" id="createRowSubmitBtn">
    <i class="bi bi-floppy"></i> บันทึกข้อมูล
  </button>
</div>
</div>

<script>
<?php if ($savedOk): ?>
window.parent.postMessage({type:'db-user-edit-saved'},window.location.origin);
<?php endif; ?>
(function(){
  var first=document.querySelector('#createRowForm input:not([type=hidden]),#createRowForm select,#createRowForm textarea');
  if(first) first.focus();
  var form=document.getElementById('createRowForm');
  var btn=document.getElementById('createRowSubmitBtn');
  if(form&&btn){
    form.addEventListener('submit',function(){
      btn.disabled=true;
      btn.innerHTML='<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> กำลังบันทึก...';
    });
  }
})();
</script>

<?php else: ?>
<?php render_app_navigation('db_table_browser.php'); ?>
<main class="container py-4 py-lg-5">
  <section class="panel">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
      <div>
        <div class="text-uppercase small fw-bold text-secondary mb-2">Create Row</div>
        <h1 class="mb-2 h3">เพิ่มข้อมูล: <?= $tableLabel ?></h1>
        <p class="text-secondary mb-0">โปรดตรวจสอบความถูกต้องก่อนบันทึก การเปลี่ยนแปลงทุกครั้งจะถูกบันทึกเพื่อตรวจสอบย้อนหลัง</p>
      </div>
      <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill">ย้อนกลับ</a>
    </div>
    <?php if ($message !== ''): ?>
      <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post" class="row g-3">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
      <?php foreach ($config['createable_columns'] as $field): ?>
        <?php $meta=$config['field_meta'][$field]??['label'=>$field,'type'=>'text']; $type=$meta['type']??'text'; ?>
        <div class="col-md-<?= $type==='textarea'?'12':'6' ?>">
          <label class="form-label fw-semibold"><?= htmlspecialchars($meta['label']??$field) ?></label>
          <?php if($type==='select'): ?>
            <?php $options=app_db_admin_field_options($conn,$meta); ?>
            <select name="<?= htmlspecialchars($field) ?>" class="form-select" <?= !empty($meta['required'])?'required':'' ?>>
              <option value="">เลือก</option>
              <?php foreach($options as $option): ?>
                <option value="<?= htmlspecialchars((string)$option['value']) ?>"
                  <?= (string)$form[$field]===(string)$option['value']?'selected':'' ?>>
                  <?= htmlspecialchars($option['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif($type==='textarea'): ?>
            <textarea name="<?= htmlspecialchars($field) ?>" class="form-control" rows="4"
                      <?= !empty($meta['required'])?'required':'' ?>><?= htmlspecialchars($form[$field]) ?></textarea>
          <?php else: ?>
            <input type="<?= htmlspecialchars($type) ?>" name="<?= htmlspecialchars($field) ?>"
                   class="form-control" value="<?= htmlspecialchars($form[$field]) ?>"
                   <?= !empty($meta['required'])?'required':'' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-dark rounded-pill px-4">บันทึกข้อมูล</button>
        <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill px-4">ยกเลิก</a>
      </div>
    </form>
  </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
</body>
</html>
