<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';

app_require_permission('can_manage_time_logs');
date_default_timezone_set('Asia/Bangkok');

$timeLogId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $timeLogId > 0 ? app_get_time_log_by_id($conn, $timeLogId) : null;

if (!$row) {
    header('Location: manage_time_logs.php');
    exit;
}

if (!app_time_log_within_scope($conn, $row)) {
    $_SESSION['flash_manage_logs_error'] = 'รายการนี้อยู่นอกขอบเขตสิทธิ์ที่จัดการได้';
    header('Location: manage_time_logs.php');
    exit;
}

if (!app_can_edit_time_log_record($row)) {
    $_SESSION['flash_manage_logs_error'] = 'รายการนี้ถูกอนุมัติและล็อกแล้ว ต้องใช้สิทธิ์ผู้ดูแลระบบเพื่อแก้ไข';
    header('Location: manage_time_logs.php');
    exit;
}

$csrfToken = app_csrf_token('edit_time_log');
$message = '';
$messageType = 'success';
$status = app_time_log_status_meta($row);

$form = [
    'work_date' => $row['work_date'],
    'time_in' => !empty($row['time_in']) ? date('H:i', strtotime($row['time_in'])) : '',
    'time_out' => !empty($row['time_out']) ? date('H:i', strtotime($row['time_out'])) : '',
    'note' => (string) ($row['note'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf_token($_POST['_csrf'] ?? null, 'edit_time_log')) {
        $message = 'โทเคนความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่';
        $messageType = 'danger';
    } else {
        $form['work_date'] = trim((string) ($_POST['work_date'] ?? ''));
        $form['time_in'] = trim((string) ($_POST['time_in'] ?? ''));
        $form['time_out'] = trim((string) ($_POST['time_out'] ?? ''));
        $form['note'] = trim((string) ($_POST['note'] ?? ''));

        if ($form['work_date'] === '' || $form['time_in'] === '' || $form['time_out'] === '') {
            $message = 'กรุณากรอกวันที่ เวลาเข้า และเวลาออกให้ครบถ้วน';
            $messageType = 'danger';
        } else {
            $fullTimeIn = $form['work_date'] . ' ' . $form['time_in'] . ':00';
            $fullTimeOut = $form['work_date'] . ' ' . $form['time_out'] . ':00';
            $tsIn = strtotime($fullTimeIn);
            $tsOut = strtotime($fullTimeOut);

            if ($tsIn === false || $tsOut === false) {
                $message = 'รูปแบบวันที่หรือเวลาไม่ถูกต้อง';
                $messageType = 'danger';
            } else {
                if ($tsOut < $tsIn) {
                    $tsOut += 86400;
                    $fullTimeOut = date('Y-m-d H:i:s', $tsOut);
                }

                $overlap = app_find_overlapping_time_log($conn, (int) $row['user_id'], $fullTimeIn, $fullTimeOut, (int) $row['id']);
                if ($overlap) {
                    $message = sprintf(
                        'เวลาที่แก้ไขชนกับรายการเดิมวันที่ %s เวลา %s - %s',
                        date('d/m/Y', strtotime($overlap['work_date'])),
                        date('H:i', strtotime($overlap['time_in'])),
                        date('H:i', strtotime($overlap['time_out']))
                    );
                    $messageType = 'danger';
                } else {
                    $hours = number_format(($tsOut - $tsIn) / 3600, 2, '.', '');
                    $beforeRow = $row;
                    // การแก้ไขจาก back-office ต้องยกเลิกการอนุมัติเดิม เพื่อให้ตรวจสอบข้อมูลชุดใหม่อีกครั้ง
                    $stmt = $conn->prepare("
                        UPDATE time_logs
                        SET work_date = ?, time_in = ?, time_out = ?, work_hours = ?, note = ?, checked_by = NULL, checked_at = NULL, signature = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $form['work_date'],
                        date('Y-m-d H:i:s', $tsIn),
                        $fullTimeOut,
                        $hours,
                        $form['note'],
                        $timeLogId,
                    ]);

                    $afterRow = app_get_time_log_by_id($conn, $timeLogId);
                    app_insert_time_log_audit(
                        $conn,
                        $timeLogId,
                        'admin_edit',
                        $beforeRow,
                        $afterRow,
                        (int) $_SESSION['id'],
                        (string) ($_SESSION['fullname'] ?? ''),
                        'แก้ไขข้อมูลลงเวลาโดยผู้ดูแลระบบ และรีเซ็ตสถานะการอนุมัติ'
                    );

                    $message = 'บันทึกการแก้ไขเรียบร้อยแล้ว และระบบรีเซ็ตสถานะการอนุมัติเดิมให้ตรวจสอบใหม่';
                    $row = $afterRow;
                    $status = app_time_log_status_meta($row);
                }
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
    <title>แก้ไขรายการลงเวลาเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(180deg, #f8fbfd, #eef4f8);
            font-family: 'Sarabun', sans-serif;
            color: #10243b;
        }

        .panel,
        .meta-item {
            background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(16, 36, 59, .08);
            border-radius: 28px;
            box-shadow: 0 18px 44px rgba(16, 36, 59, .08);
        }

        .panel {
            padding: 28px;
        }

        h1,
        h2 {
            font-family: 'Prompt', sans-serif;
        }

        .form-control {
            border-radius: 16px;
            padding: 12px 14px;
        }

        .meta-item {
            padding: 18px;
            height: 100%;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: .84rem;
            font-weight: 700;
        }

        .status-warning { background: rgba(211, 154, 40, .15); color: #8d6113; }
        .status-success { background: rgba(28, 107, 99, .12); color: #225f57; }
    </style>
</head>
<body>
<?php render_app_navigation('manage_time_logs.php'); ?>
<main class="container py-4 py-lg-5">
    <section class="panel mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div>
                <div class="text-uppercase small fw-bold text-secondary mb-2">Back Office</div>
                <h1 class="mb-2">แก้ไขรายการลงเวลาเวร #<?= (int) $row['id'] ?></h1>
                <p class="text-secondary mb-0">การแก้ไขจากส่วนกลางจะคำนวณชั่วโมงใหม่ ตรวจสอบเวลาเวรชนกันอีกครั้ง และรีเซ็ตสถานะการอนุมัติเดิมเพื่อให้ตรวจสอบตามข้อมูลล่าสุด</p>
            </div>
            <div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
                <span class="status-badge status-<?= htmlspecialchars($status['class']) ?>"><?= htmlspecialchars($status['label']) ?></span>
                <a href="manage_time_logs.php" class="btn btn-outline-secondary rounded-pill">กลับหน้าจัดการลงเวลาเวร</a>
            </div>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> rounded-4 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="row g-4">
        <div class="col-lg-4">
            <div class="panel h-100">
                <h2 class="h5 mb-3">ข้อมูลเจ้าหน้าที่</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="meta-item">
                            <div class="small text-muted mb-1">ชื่อเจ้าหน้าที่</div>
                            <div class="fw-semibold"><?= htmlspecialchars($row['fullname'] ?? '-') ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="meta-item">
                            <div class="small text-muted mb-1">ตำแหน่ง</div>
                            <div class="fw-semibold"><?= htmlspecialchars($row['position_name'] ?? '-') ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="meta-item">
                            <div class="small text-muted mb-1">แผนก</div>
                            <div class="fw-semibold"><?= htmlspecialchars($row['department_name'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="panel">
                <form method="post" class="row g-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">วันที่ปฏิบัติงาน</label>
                        <input type="date" name="work_date" class="form-control" value="<?= htmlspecialchars($form['work_date']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">เวลาเข้า</label>
                        <input type="time" name="time_in" class="form-control" value="<?= htmlspecialchars($form['time_in']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">เวลาออก</label>
                        <input type="time" name="time_out" class="form-control" value="<?= htmlspecialchars($form['time_out']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">หมายเหตุ</label>
                        <textarea name="note" rows="4" class="form-control"><?= htmlspecialchars($form['note']) ?></textarea>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">บันทึกการแก้ไข</button>
                        <a href="manage_time_logs.php" class="btn btn-outline-secondary rounded-pill px-4">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
