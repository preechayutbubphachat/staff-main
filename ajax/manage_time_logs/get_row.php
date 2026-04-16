<?php
require_once __DIR__ . '/../bootstrap.php';

app_require_permission('can_manage_time_logs');

$timeLogId = max(0, (int) ($_GET['id'] ?? 0));
$row = $timeLogId > 0 ? app_get_time_log_by_id($conn, $timeLogId) : null;

if (!$row) {
    ajax_html('<div class="alert alert-warning rounded-4 m-3">ไม่พบรายการลงเวลาเวรที่ต้องการ</div>', 404);
}

if (!app_time_log_within_scope($conn, $row)) {
    ajax_html('<div class="alert alert-danger rounded-4 m-3">รายการนี้อยู่นอกขอบเขตสิทธิ์ที่จัดการได้</div>', 403);
}

if (!app_can_edit_time_log_record($row)) {
    ajax_html('<div class="alert alert-warning rounded-4 m-3">รายการนี้ถูกล็อกแล้ว และบัญชีนี้ไม่มีสิทธิ์แก้ไขรายการที่อนุมัติแล้ว</div>', 403);
}

$csrfToken = app_csrf_token('manage_time_logs_ajax');
$form = [
    'work_date' => $row['work_date'],
    'time_in' => !empty($row['time_in']) ? date('H:i', strtotime($row['time_in'])) : '',
    'time_out' => !empty($row['time_out']) ? date('H:i', strtotime($row['time_out'])) : '',
    'note' => (string) ($row['note'] ?? ''),
];

$html = ajax_capture(function () use ($row, $form, $csrfToken): void {
    ?>
    <div class="modal-header border-0 pb-0">
        <div>
            <div class="text-uppercase small fw-bold text-secondary mb-2">Back Office</div>
            <h5 class="modal-title">แก้ไขรายการลงเวลาเวร #<?= (int) $row['id'] ?></h5>
            <div class="text-muted small"><?= htmlspecialchars($row['fullname'] ?? '-') ?> · <?= htmlspecialchars($row['department_name'] ?? '-') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <form id="manageTimeLogEditForm" data-manage-edit-form>
        <div class="modal-body">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label fw-semibold">วันที่ปฏิบัติงาน</label><input type="date" name="work_date" class="form-control" value="<?= htmlspecialchars($form['work_date']) ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">เวลาเข้า</label><input type="time" name="time_in" class="form-control" value="<?= htmlspecialchars($form['time_in']) ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">เวลาออก</label><input type="time" name="time_out" class="form-control" value="<?= htmlspecialchars($form['time_out']) ?>" required></div>
                <div class="col-12"><label class="form-label fw-semibold">หมายเหตุ</label><textarea name="note" rows="4" class="form-control"><?= htmlspecialchars($form['note']) ?></textarea></div>
            </div>
        </div>
        <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary rounded-pill">บันทึกการแก้ไข</button>
        </div>
    </form>
    <?php
});

ajax_html($html);
