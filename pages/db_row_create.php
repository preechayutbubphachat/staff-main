<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/db_admin_helpers.php';

app_require_permission('can_manage_database');

$table = trim((string) ($_GET['table'] ?? ''));
$config = app_db_admin_require_table_allowed($table);
if (empty($config['create_allowed'])) {
    header('Location: db_table_browser.php?table=' . urlencode($table));
    exit;
}

$actorId = (int) ($_SESSION['id'] ?? 0);
$actorName = (string) ($_SESSION['fullname'] ?? '');
$csrfToken = app_csrf_token('db_row_create');
$message = '';
$messageType = 'success';
$form = [];
foreach ($config['createable_columns'] as $column) {
    $form[$column] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'db_row_create')) {
        $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } else {
        foreach ($config['createable_columns'] as $column) {
            $form[$column] = (string) ($_POST[$column] ?? '');
        }
        $validation = app_db_admin_validate_payload($conn, $config, $_POST, true);
        if ($validation['errors']) {
            $message = implode(' ', $validation['errors']);
            $messageType = 'danger';
        } else {
            try {
                $newId = app_db_admin_insert_row($conn, $table, $config, $validation['payload'], $actorId, $actorName);
                $_SESSION['db_admin_flash'] = 'เพิ่มข้อมูลเรียบร้อยแล้ว';
                $_SESSION['db_admin_flash_type'] = 'success';
                header('Location: db_row_edit.php?table=' . urlencode($table) . '&id=' . $newId);
                exit;
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
    <title>เพิ่มข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body{background:linear-gradient(180deg,#f8fbfd,#eef4f8);font-family:'Sarabun',sans-serif;color:#10243b}.panel{background:rgba(255,255,255,.92);border:1px solid rgba(16,36,59,.08);border-radius:28px;box-shadow:0 18px 44px rgba(16,36,59,.08);padding:28px}h1{font-family:'Prompt',sans-serif}.form-control,.form-select{border-radius:16px;padding:12px 14px}</style>
</head>
<body>
<?php render_app_navigation('db_table_browser.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="panel">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><div class="text-uppercase small fw-bold text-secondary mb-2">Create Row</div><h1 class="mb-2">เพิ่มข้อมูล: <?= htmlspecialchars($config['label']) ?></h1><p class="text-secondary mb-0">โปรดตรวจสอบความถูกต้องก่อนบันทึก การเปลี่ยนแปลงทุกครั้งจะถูกบันทึกเพื่อตรวจสอบย้อนหลัง</p></div><a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill">ย้อนกลับ</a></div>
        <?php if ($message !== ''): ?><div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="post" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php foreach ($config['createable_columns'] as $field): ?>
                <?php $meta = $config['field_meta'][$field] ?? ['label' => $field, 'type' => 'text']; $type = $meta['type']; ?>
                <div class="col-md-<?= $type === 'textarea' ? '12' : '6' ?>">
                    <label class="form-label fw-semibold"><?= htmlspecialchars($meta['label']) ?></label>
                    <?php if ($type === 'select'): ?>
                        <?php $options = app_db_admin_field_options($conn, $meta); ?>
                        <select name="<?= htmlspecialchars($field) ?>" class="form-select" <?= !empty($meta['required']) ? 'required' : '' ?>>
                            <option value="">เลือก</option>
                            <?php foreach ($options as $option): ?><option value="<?= htmlspecialchars((string) $option['value']) ?>" <?= (string) $form[$field] === (string) $option['value'] ? 'selected' : '' ?>><?= htmlspecialchars($option['label']) ?></option><?php endforeach; ?>
                        </select>
                    <?php elseif ($type === 'textarea'): ?>
                        <textarea name="<?= htmlspecialchars($field) ?>" class="form-control" rows="4" <?= !empty($meta['required']) ? 'required' : '' ?>><?= htmlspecialchars($form[$field]) ?></textarea>
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($type) ?>" name="<?= htmlspecialchars($field) ?>" class="form-control" value="<?= htmlspecialchars($form[$field]) ?>" <?= !empty($meta['required']) ? 'required' : '' ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="col-12 d-flex flex-wrap gap-2"><button type="submit" class="btn btn-dark rounded-pill px-4">บันทึกข้อมูล</button><a href="db_table_browser.php?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary rounded-pill px-4">ยกเลิก</a></div>
        </form>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
