<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_permission('can_manage_database');

$table   = trim((string) ($_GET['table'] ?? $_POST['table'] ?? ''));
$config  = app_db_admin_require_table_allowed($table);
$isModal = isset($_GET['modal']) && $_GET['modal'] === '1';

// users ใช้ edit_user.php เสมอ
if ($table === 'users') {
    $id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
    if ($id > 0) {
        header('Location: edit_user.php?id=' . $id . ($isModal ? '&modal=1' : ''));
        exit;
    }
}

// ตาราง read-only
if (empty($config['edit_allowed'])) {
    if ($isModal) {
        header('Content-Type: text/html; charset=utf-8');
        $label = htmlspecialchars($config['label'] ?? $table);
        echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>read-only</title></head><body style='padding:32px;font-family:Sarabun,sans-serif;color:#10243b;'>";
        echo "<p><strong>read-only</strong></p><p>$label</p>";
        echo "<button onclick=\"window.parent.postMessage({type:'db-user-edit-close'},window.location.origin)\" style='padding:8px 20px;background:#10243b;color:#fff;border:none;border-radius:8px;cursor:pointer;'>close</button>";
        echo '</body></html>';
        exit;
    }
    header('Location: db_table_browser.php?table=' . urlencode($table));
    exit;
}

$id  = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$row = $id > 0 ? app_db_admin_fetch_row($conn, $config, $id) : null;
if (!$row) {
    if ($isModal) {
        echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'></head><body style='padding:32px;font-family:Sarabun,sans-serif;'>not found</body></html>";
        exit;
    }
    header('Location: db_table_browser.php?table=' . urlencode($table));
    exit;
}

$actorId    = (int) ($_SESSION['id'] ?? 0);
$actorName  = (string) ($_SESSION['fullname'] ?? '');
$csrfToken  = app_csrf_token('db_row_edit');
$message    = '';
$messageType = 'success';
$savedOk    = false;
$form       = [];

foreach ($config['editable_columns'] as $column) {
    $form[$column] = (string) ($row[$column] ?? '');
    if ($column === 'time_in' || $column === 'time_out') {
        $form[$column] = !empty($row[$column]) ? date('H:i', strtotime((string) $row[$column])) : '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'db_row_edit')) {
        $message     = 'token-error';
        $messageType = 'danger';
    } else {
        foreach ($config['editable_columns'] as $column) {
            if (($config['field_meta'][$column]['type'] ?? 'text') === 'boolean') {
                $form[$column] = isset($_POST[$column]) ? '1' : '0';
            } else {
                $form[$column] = (string) ($_POST[$column] ?? '');
            }
        }
        $validation = app_db_admin_validate_payload($conn, $config, $_POST, false);
        if ($validation['errors']) {
            $message     = implode(' ', $validation['errors']);
            $messageType = 'danger';
        } else {
            try {
                app_db_admin_update_row($conn, $table, $config, $id, $validation['payload'], $actorId, $actorName);
                $row = app_db_admin_fetch_row($conn, $config, $id);
                foreach ($config['editable_columns'] as $column) {
                    $form[$column] = (string) ($row[$column] ?? '');
                    if ($column === 'time_in' || $column === 'time_out') {
                        $form[$column] = !empty($row[$column]) ? date('H:i', strtotime((string) $row[$column])) : '';
                    }
                }
                $savedOk = true;
            } catch (Throwable $exception) {
                $message     = $exception->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

$formAction = 'db_row_edit.php?table=' . urlencode($table) . '&id=' . $id . ($isModal ? '&modal=1' : '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Row</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; color: #10243b; }
        h1,h2,h3 { font-family: 'Prompt', sans-serif; }
        .panel { background: rgba(255,255,255,.94); border:1px solid rgba(16,36,59,.08); border-radius:20px; box-shadow:0 12px 32px rgba(16,36,59,.07); padding:24px; }
        .meta-item { background:#f8fafc; border:1px solid #e9eff4; border-radius:14px; padding:12px; height:100%; }
        .form-control,.form-select { border-radius:12px; padding:10px 14px; border-color:#d1dce5; }
        .form-control:focus,.form-select:focus { border-color:#1cb8a1; box-shadow:0 0 0 3px rgba(28,184,161,.12); }

        /* === MODAL MODE === */
        body.is-modal-body { background:#fff; padding:0; margin:0; }
        .modal-shell { display:flex; flex-direction:column; height:100vh; overflow:hidden; }
        .modal-header-bar { padding:18px 24px 14px; border-bottom:1px solid rgba(16,36,59,.09); display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
        .modal-header-bar h2 { font-size:1.05rem; margin:0; }
        .modal-eyebrow { font-size:.68rem; font-weight:900; text-transform:uppercase; letter-spacing:.14em; color:#1cb8a1; margin-bottom:3px; }
        .modal-scroll-body { flex:1; overflow-y:auto; padding:20px 24px 0; }
        .modal-footer-bar { padding:14px 24px 18px; border-top:1px solid rgba(16,36,59,.09); display:flex; gap:10px; justify-content:flex-end; background:#fff; flex-shrink:0; }
        .modal-close-btn { background:none; border:none; font-size:1.1rem; cursor:pointer; color:#64748b; padding:4px 8px; border-radius:8px; }
        .modal-close-btn:hover { background:#f1f5f9; color:#10243b; }
        .modal-ref-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; margin-bottom:20px; }
        .modal-section-label { font-size:.72rem; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:#94a3b8; margin-bottom:10px; }

        /* === FULL PAGE MODE === */
        body.is-full-body { background:linear-gradient(180deg,#f8fbfd,#eef4f8); min-height:100vh; }
    </style>
</head>
<body class="<?= $isModal ? 'is-modal-body' : 'is-full-body' ?>">

<?php if ($isModal): ?>
<div class="modal-shell">
    <div class="modal-header-bar">
        <div>
            <div class="modal-eyebrow"><?= htmlspecialchars($config['label'] ?? $table) ?> &middot; #<?= $id ?></div>
            <h2>Edit record</h2>
        </div>
        <button type="button" class="modal-close-btn"
                onclick="window.parent.postMessage({type:'db-user-edit-close'}, window.location.origin)"
                aria-label="close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-0 mb-0 px-4 py-2 small"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="modal-scroll-body">
        <p class="modal-section-label">Reference data</p>
        <div class="modal-ref-grid">
            <?php foreach ($config['browse_columns'] as $col): ?>
                <div class="meta-item">
                    <div class="small text-muted mb-1"><?= htmlspecialchars($col) ?></div>
                    <div class="fw-semibold small"><?= htmlspecialchars(app_db_admin_format_value($col, $row[$col] ?? null)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="modal-section-label">Edit fields</p>
        <form method="post" action="<?= htmlspecialchars($formAction) ?>" id="rowEditForm" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <?php foreach ($config['editable_columns'] as $field): ?>
                <?php $meta = $config['field_meta'][$field] ?? ['label' => $field, 'type' => 'text']; $ftype = $meta['type'] ?? 'text'; ?>
                <div class="col-md-<?= $ftype === 'textarea' ? '12' : '6' ?>">
                    <label class="form-label fw-semibold small"><?= htmlspecialchars($meta['label'] ?? $field) ?></label>
                    <?php if ($ftype === 'select'): ?>
                        <?php $opts = app_db_admin_field_options($conn, $meta); ?>
                        <select name="<?= htmlspecialchars($field) ?>" class="form-select" <?= !empty($meta['required']) ? 'required' : '' ?>>
                            <option value="">--</option>
                            <?php foreach ($opts as $opt): ?>
                                <option value="<?= htmlspecialchars((string)$opt['value']) ?>" <?= (string)$form[$field] === (string)$opt['value'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($ftype === 'textarea'): ?>
                        <textarea name="<?= htmlspecialchars($field) ?>" rows="3" class="form-control"><?= htmlspecialchars($form[$field]) ?></textarea>
                    <?php elseif ($ftype === 'boolean'): ?>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="f_<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>" value="1" <?= (int)$form[$field] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="f_<?= htmlspecialchars($field) ?>">on</label>
                        </div>
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($ftype) ?>" name="<?= htmlspecialchars($field) ?>" class="form-control" value="<?= htmlspecialchars($form[$field]) ?>" <?= !empty($meta['required']) ? 'required' : '' ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </form>
        <div style="height:20px;"></div>
    </div>

    <div class="modal-footer-bar">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4"
                onclick="window.parent.postMessage({type:'db-user-edit-close'}, window.location.origin)">
            Cancel
        </button>
        <button type="submit" form="rowEditForm" class="btn btn-dark rounded-pill px-4">
            <i class="bi bi-floppy"></i> Save
        </button>
    </div>
</div>

<script>
<?php if ($savedOk): ?>
window.parent.postMessage({ type: 'db-user-edit-saved' }, window.location.origin);
<?php endif; ?>
</script>

<?php else: ?>
<?php render_app_navigation('db_table_browser.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="panel mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-uppercase small fw-bold text-secondary mb-2">Edit Row &middot; <?= htmlspecialchars($config['label'] ?? $table) ?></div>
                <h1 class="mb-2 h3">Edit #<?= $id ?></h1>
                <p class="text-secondary mb-0">Changes are logged in the audit trail every time.</p>
            </div>
            <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill">Back</a>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="row g-4">
        <div class="col-lg-4">
            <div class="panel h-100">
                <h2 class="h5 mb-3">Reference</h2>
                <div class="row g-3">
                    <?php foreach ($config['browse_columns'] as $col): ?>
                        <div class="col-12">
                            <div class="meta-item">
                                <div class="small text-muted mb-1"><?= htmlspecialchars($col) ?></div>
                                <div class="fw-semibold"><?= htmlspecialchars(app_db_admin_format_value($col, $row[$col] ?? null)) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="panel">
                <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="row g-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <?php foreach ($config['editable_columns'] as $field): ?>
                        <?php $meta = $config['field_meta'][$field] ?? ['label' => $field, 'type' => 'text']; $ftype = $meta['type'] ?? 'text'; ?>
                        <div class="col-md-<?= $ftype === 'textarea' ? '12' : '6' ?>">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($meta['label'] ?? $field) ?></label>
                            <?php if ($ftype === 'select'): ?>
                                <?php $opts = app_db_admin_field_options($conn, $meta); ?>
                                <select name="<?= htmlspecialchars($field) ?>" class="form-select" <?= !empty($meta['required']) ? 'required' : '' ?>>
                                    <option value="">--</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?= htmlspecialchars((string)$opt['value']) ?>" <?= (string)$form[$field] === (string)$opt['value'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($ftype === 'textarea'): ?>
                                <textarea name="<?= htmlspecialchars($field) ?>" rows="4" class="form-control"><?= htmlspecialchars($form[$field]) ?></textarea>
                            <?php elseif ($ftype === 'boolean'): ?>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="f_<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>" value="1" <?= (int)$form[$field] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="f_<?= htmlspecialchars($field) ?>">on</label>
                                </div>
                            <?php else: ?>
                                <input type="<?= htmlspecialchars($ftype) ?>" name="<?= htmlspecialchars($field) ?>" class="form-control" value="<?= htmlspecialchars($form[$field]) ?>" <?= !empty($meta['required']) ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Save</button>
                        <a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>
<?php endif; ?>
</body>
</html>
