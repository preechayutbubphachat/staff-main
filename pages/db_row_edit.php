<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_permission('can_manage_database');

$table = trim((string) ($_GET['table'] ?? $_POST['table'] ?? ''));
$config = app_db_admin_require_table_allowed($table);

if ($table === 'users') {
    $id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
    if ($id > 0) {
        header('Location: edit_user.php?id=' . $id);
        exit;
    }
}

if (empty($config['edit_allowed'])) {
    header('Location: db_table_browser.php?table=' . urlencode($table));
    exit;
}

$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$row = $id > 0 ? app_db_admin_fetch_row($conn, $config, $id) : null;
if (!$row) {
    header('Location: db_table_browser.php?table=' . urlencode($table));
    exit;
}

$actorId = (int) ($_SESSION['id'] ?? 0);
$actorName = (string) ($_SESSION['fullname'] ?? '');
$csrfToken = app_csrf_token('db_row_edit');
$message = '';
$messageType = 'success';
$form = [];
foreach ($config['editable_columns'] as $column) {
    $form[$column] = (string) ($row[$column] ?? '');
    if ($column === 'time_in' || $column === 'time_out') {
        $form[$column] = !empty($row[$column]) ? date('H:i', strtotime((string) $row[$column])) : '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'db_row_edit')) {
        $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
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
            $message = implode(' ', $validation['errors']);
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
                $message = 'บันทึกการแก้ไขเรียบร้อยแล้ว';
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $messageType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แก้ไขข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body{background:linear-gradient(180deg,#f8fbfd,#eef4f8);font-family:'Sarabun',sans-serif;color:#10243b}.panel,.meta-item{background:rgba(255,255,255,.92);border:1px solid rgba(16,36,59,.08);border-radius:28px;box-shadow:0 18px 44px rgba(16,36,59,.08)}.panel{padding:28px}.meta-item{padding:18px;height:100%}h1,h2{font-family:'Prompt',sans-serif}.form-control,.form-select{border-radius:16px;padding:12px 14px}</style>
</head>
<body>
<?php render_app_navigation('db_table_browser.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="panel mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3"><div><div class="text-uppercase small fw-bold text-secondary mb-2">Edit Row</div><h1 class="mb-2">แก้ไขข้อมูล: <?= htmlspecialchars($config['label']) ?> #<?= $id ?></h1><p class="text-secondary mb-0">โปรดตรวจสอบความถูกต้องก่อนบันทึก การเปลี่ยนแปลงทุกครั้งจะถูกบันทึกเพื่อตรวจสอบย้อนหลัง</p></div><a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill">ย้อนกลับ</a></div>
    </section>
    <?php if ($message !== ''): ?><div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <section class="row g-4">
        <div class="col-lg-4"><div class="panel h-100"><h2 class="h5 mb-3">ข้อมูลอ้างอิง</h2><div class="row g-3"><?php foreach ($config['browse_columns'] as $column): ?><div class="col-12"><div class="meta-item"><div class="small text-muted mb-1"><?= htmlspecialchars($column) ?></div><div class="fw-semibold"><?= htmlspecialchars(app_db_admin_format_value($column, $row[$column] ?? null)) ?></div></div></div><?php endforeach; ?></div></div></div>
        <div class="col-lg-8"><div class="panel"><form method="post" class="row g-3"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>"><input type="hidden" name="id" value="<?= $id ?>"><?php foreach ($config['editable_columns'] as $field): ?><?php $meta = $config['field_meta'][$field] ?? ['label' => $field, 'type' => 'text']; $type = $meta['type']; ?><div class="col-md-<?= $type === 'textarea' ? '12' : '6' ?>"><label class="form-label fw-semibold"><?= htmlspecialchars($meta['label']) ?></label><?php if ($type === 'select'): ?><?php $options = app_db_admin_field_options($conn, $meta); ?><select name="<?= htmlspecialchars($field) ?>" class="form-select" <?= !empty($meta['required']) ? 'required' : '' ?>><option value="">เลือก</option><?php foreach ($options as $option): ?><option value="<?= htmlspecialchars((string) $option['value']) ?>" <?= (string) $form[$field] === (string) $option['value'] ? 'selected' : '' ?>><?= htmlspecialchars($option['label']) ?></option><?php endforeach; ?></select><?php elseif ($type === 'textarea'): ?><textarea name="<?= htmlspecialchars($field) ?>" rows="4" class="form-control"><?= htmlspecialchars($form[$field]) ?></textarea><?php elseif ($type === 'boolean'): ?><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" id="field_<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>" value="1" <?= (int) $form[$field] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="field_<?= htmlspecialchars($field) ?>">เปิดใช้งาน</label></div><?php else: ?><input type="<?= htmlspecialchars($type) ?>" name="<?= htmlspecialchars($field) ?>" class="form-control" value="<?= htmlspecialchars($form[$field]) ?>" <?= !empty($meta['required']) ? 'required' : '' ?>><?php endif; ?></div><?php endforeach; ?><div class="col-12 d-flex flex-wrap gap-2"><button type="submit" class="btn btn-dark rounded-pill px-4">บันทึกการแก้ไข</button><a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill px-4">ยกเลิก</a></div></form></div></div>
    </section>
</main>
</body>
</html>
