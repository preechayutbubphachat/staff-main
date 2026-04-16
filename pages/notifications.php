<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

app_require_login();

$status = $_GET['status'] ?? 'all';
$status = in_array($status, ['all', 'unread', 'read'], true) ? $status : 'all';
$perPage = app_parse_table_page_size($_GET, 20);
$page = app_parse_table_page($_GET);
$userId = (int) ($_SESSION['id'] ?? 0);

$initialData = app_get_notifications_page_data($conn, $userId, $status, $perPage, 0);
$totalRows = $initialData['total'];
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$notificationData = app_get_notifications_page_data($conn, $userId, $status, $perPage, $offset);
$rows = $notificationData['rows'];

$csrf = app_csrf_token('notifications_page');
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'notifications_page')) {
        $message = 'โทเค็นความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
    } elseif (($_POST['action'] ?? '') === 'mark_all_read') {
        $updatedCount = app_mark_all_notifications_read($conn, $userId);
        $message = $updatedCount > 0 ? 'อ่านการแจ้งเตือนทั้งหมดแล้ว' : 'ไม่มีรายการที่ยังไม่ได้อ่าน';
        header('Location: notifications.php?status=' . urlencode($status) . '&per_page=' . $perPage);
        exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>การแจ้งเตือน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/app-ui.css" rel="stylesheet">
</head>
<body>
<?php render_app_navigation('notifications.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="small text-uppercase fw-semibold text-secondary mb-2">การแจ้งเตือน</div>
                <h1 class="mb-2">การแจ้งเตือน</h1>
                <p class="section-subtitle mb-0">ติดตามรายการอนุมัติ งานรอตรวจสอบ และการอัปเดตที่เกี่ยวข้องกับสิทธิ์ของคุณจากที่เดียว</p>
            </div>
            <div class="badge-chip">ยังไม่ได้อ่าน <?= (int) app_get_unread_notification_count($conn, $userId) ?> รายการ</div>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="table-toolbar-head mb-3">
            <div>
                <div class="section-title h4 mb-1">รายการแจ้งเตือนทั้งหมด</div>
                <div class="section-subtitle">เลือกดูเฉพาะรายการที่ยังไม่ได้อ่านหรือย้อนหลังทั้งหมดได้</div>
            </div>
        </div>

        <div class="table-toolbar mb-3">
            <form method="get" class="table-toolbar-form d-flex flex-wrap align-items-end gap-3 flex-grow-1">
                <div>
                    <label class="form-label small text-muted">สถานะ</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>ยังไม่ได้อ่าน</option>
                        <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>อ่านแล้ว</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">แสดง</label>
                    <select name="per_page" class="form-select">
                        <?php foreach ([10, 20, 50, 100] as $option): ?>
                            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> รายการต่อหน้า</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-dark btn-pill">อัปเดตรายการ</button>
            </form>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-secondary btn-pill">อ่านทั้งหมด</button>
            </form>
        </div>

        <div class="d-grid gap-3">
            <?php if ($rows): ?>
                <?php foreach ($rows as $index => $notification): ?>
                    <article class="notification-item rounded-4 border <?= $notification['is_read'] ? '' : 'unread' ?>">
                        <a class="notification-link" href="<?= htmlspecialchars($notification['target_url'] ?: 'notifications.php') ?>">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                                    <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                </div>
                                <span class="badge rounded-pill text-bg-<?= $notification['is_read'] ? 'secondary' : 'primary' ?>">
                                    <?= $notification['is_read'] ? 'อ่านแล้ว' : 'ยังไม่ได้อ่าน' ?>
                                </span>
                            </div>
                            <div class="notification-meta mt-2"><?= htmlspecialchars($notification['created_at_label']) ?></div>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notification-empty rounded-4 border">ยังไม่มีการแจ้งเตือนตามเงื่อนไขที่เลือก</div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="pagination">
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['status' => $status, 'per_page' => $perPage, 'p' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
