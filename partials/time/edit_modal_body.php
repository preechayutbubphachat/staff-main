<?php
$editLog = $editLog ?? null;
$selectedEditDepartmentId = $selectedEditDepartmentId ?? (int) ($editLog['department_id'] ?? 0);
$editModalTimeIn24 = $editLog && !empty($editLog['time_in']) ? date('H:i', strtotime((string) $editLog['time_in'])) : '08:30';
$editModalTimeOut24 = $editLog && !empty($editLog['time_out']) ? date('H:i', strtotime((string) $editLog['time_out'])) : '16:30';
$editModalTimeInParts = app_time_to_parts($editModalTimeIn24);
$editModalTimeOutParts = app_time_to_parts($editModalTimeOut24);
$editModalLocked = $editLog ? app_time_log_is_locked($editLog) : false;
$statusMeta = $editLog ? app_time_log_status_meta($editLog) : ['label' => 'รอตรวจ', 'class' => 'warning'];
$canEditModal = $canEditModal ?? !$editModalLocked;
$canDeleteModal = $canDeleteModal ?? $canEditModal;
$editCsrfToken = $editCsrfToken ?? app_csrf_token('time_page_edit');
$deleteCsrfToken = $deleteCsrfToken ?? app_csrf_token('time_page_delete');
$helperText = $canEditModal
    ? 'สามารถแก้ไขหรือลบรายการได้เฉพาะรายการที่ยังไม่ได้รับการอนุมัติ'
    : 'รายการนี้ได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขหรือลบได้ กรุณาติดต่อผู้ดูแลระบบ หากจำเป็นต้องดำเนินการเพิ่มเติม';
$helperType = $canEditModal ? 'info' : 'warning';
$departmentName = '-';

if (!empty($departments)) {
    foreach ($departments as $department) {
        if ((int) ($department['id'] ?? 0) === $selectedEditDepartmentId) {
            $departmentName = (string) ($department['department_name'] ?? '-');
            break;
        }
    }
}
?>
<div class="modal-header border-0 pb-0">
    <div>
        <div class="text-uppercase small text-muted fw-semibold mb-1"><?= $canEditModal ? 'แก้ไขข้อมูลเดิม' : 'รายละเอียดรายการ' ?></div>
        <h2 class="h4 mb-1"><?= $canEditModal ? 'แก้ไขข้อมูลลงเวลาเวร' : 'รายละเอียดรายการลงเวลาเวร' ?></h2>
        <div class="text-muted small"><?= htmlspecialchars(app_format_thai_date((string) ($editLog['work_date'] ?? ''), true)) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
</div>
<div class="modal-body pt-3">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="status-chip <?= htmlspecialchars($statusMeta['class']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span>
        <?php if ($editModalLocked): ?>
            <span class="status-chip ink">ล็อกแล้ว</span>
        <?php endif; ?>
        <span class="time-badge-pill"><?= htmlspecialchars(app_format_thai_date((string) ($editLog['work_date'] ?? ''), true)) ?></span>
    </div>

    <div class="alert alert-<?= htmlspecialchars($helperType) ?> rounded-4 mb-3">
        <?= htmlspecialchars($helperText) ?>
    </div>

    <?php if (!empty($modalErrorMessage)): ?>
        <div class="alert alert-<?= htmlspecialchars($modalErrorType ?? 'danger') ?> rounded-4"><?= htmlspecialchars($modalErrorMessage) ?></div>
    <?php endif; ?>

    <form method="post" id="editTimeLogAjaxForm" data-ajax-edit-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($editCsrfToken) ?>">
        <input type="hidden" name="delete_csrf" value="<?= htmlspecialchars($deleteCsrfToken) ?>">
        <input type="hidden" name="id" value="<?= (int) ($editLog['id'] ?? 0) ?>">
        <input type="hidden" name="edit_id" value="<?= (int) ($editLog['id'] ?? 0) ?>">
        <input type="hidden" name="page" value="<?= (int) $page ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($searchDate) ?>">
        <input type="hidden" name="date_from" value="<?= htmlspecialchars((string) ($dateFrom ?? '')) ?>">
        <input type="hidden" name="date_to" value="<?= htmlspecialchars((string) ($dateTo ?? '')) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars((string) ($historyStatus ?? 'all')) ?>">
        <input type="hidden" name="query" value="<?= htmlspecialchars((string) ($historyQuery ?? '')) ?>">

        <?php if ($canEditModal): ?>
            <div class="row g-3">
                <?php if ($canViewDepartmentReports): ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted">แผนก</label>
                        <select name="edit_department_id" class="form-select">
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= $department['id'] ?>" <?= $selectedEditDepartmentId === (int) $department['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="<?= $canViewDepartmentReports ? 'col-md-3' : 'col-md-6' ?>">
                    <label class="form-label fw-semibold small text-muted">เวลาเข้า</label>
                    <div class="time-select-grid">
                        <select name="edit_time_in_hour" id="edit_time_in_hour" class="form-select">
                            <?php foreach ($hourOptions as $hourOption): ?>
                                <option value="<?= $hourOption ?>" <?= (($_POST['edit_time_in_hour'] ?? $editModalTimeInParts['hour']) === $hourOption) ? 'selected' : '' ?>><?= $hourOption ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="time-colon">:</div>
                        <select name="edit_time_in_minute" id="edit_time_in_minute" class="form-select">
                            <?php foreach ($minuteOptions as $minuteOption): ?>
                                <option value="<?= $minuteOption ?>" <?= (($_POST['edit_time_in_minute'] ?? $editModalTimeInParts['minute']) === $minuteOption) ? 'selected' : '' ?>><?= $minuteOption ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="<?= $canViewDepartmentReports ? 'col-md-3' : 'col-md-6' ?>">
                    <label class="form-label fw-semibold small text-muted">เวลาออก</label>
                    <div class="time-select-grid">
                        <select name="edit_time_out_hour" id="edit_time_out_hour" class="form-select">
                            <?php foreach ($hourOptions as $hourOption): ?>
                                <option value="<?= $hourOption ?>" <?= (($_POST['edit_time_out_hour'] ?? $editModalTimeOutParts['hour']) === $hourOption) ? 'selected' : '' ?>><?= $hourOption ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="time-colon">:</div>
                        <select name="edit_time_out_minute" id="edit_time_out_minute" class="form-select">
                            <?php foreach ($minuteOptions as $minuteOption): ?>
                                <option value="<?= $minuteOption ?>" <?= (($_POST['edit_time_out_minute'] ?? $editModalTimeOutParts['minute']) === $minuteOption) ? 'selected' : '' ?>><?= $minuteOption ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="small text-muted mt-2">ใช้รูปแบบ 24 ชั่วโมงเสมอ เช่น 08:30 หรือ 16:30</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small text-muted">หมายเหตุ / ภารกิจ</label>
                    <textarea name="edit_note" class="form-control" rows="3"><?= htmlspecialchars((string) ($_POST['edit_note'] ?? $editLog['note'] ?? '')) ?></textarea>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="time-modal-info-card h-100">
                        <div class="small text-muted mb-1">แผนก</div>
                        <div class="fw-semibold"><?= htmlspecialchars($departmentName) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="time-modal-info-card h-100">
                        <div class="small text-muted mb-1">เวลาเข้า</div>
                        <div class="fw-semibold"><?= htmlspecialchars($editModalTimeIn24) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="time-modal-info-card h-100">
                        <div class="small text-muted mb-1">เวลาออก</div>
                        <div class="fw-semibold"><?= htmlspecialchars($editModalTimeOut24) ?></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="time-modal-info-card">
                        <div class="small text-muted mb-1">หมายเหตุ / ภารกิจ</div>
                        <div class="fw-semibold"><?= nl2br(htmlspecialchars((string) ($editLog['note'] ?? 'ไม่มีหมายเหตุเพิ่มเติม'))) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="modal-footer border-0 px-0 pt-4 pb-0 d-flex flex-wrap justify-content-between gap-2">
            <div>
                <?php if ($canDeleteModal): ?>
                    <button
                        type="button"
                        class="dash-btn dash-btn-ghost time-modal-danger"
                        data-delete-submit
                        data-confirm-message="ยืนยันการลบรายการลงเวลาเวรนี้ใช่หรือไม่?"
                    >
                        <i class="bi bi-trash3"></i>ลบรายการ
                    </button>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="dash-btn dash-btn-ghost" data-bs-dismiss="modal"><?= $canEditModal ? 'ยกเลิก' : 'ปิด' ?></button>
                <?php if ($canEditModal): ?>
                    <button type="submit" name="update_time_log" value="1" class="dash-btn dash-btn-primary">
                        <i class="bi bi-check2-circle"></i>บันทึกการแก้ไข
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
