<?php
/**
 * Reusable time log detail modal.
 *
 * Include once per page that needs a "ดูรายละเอียด" button on time log rows.
 * Populated via JS (openTimeLogDetail) calling ajax/time/get_time_log_detail.php.
 *
 * CSS is already in dashboard-tailwind.output.css under .shift-review-* classes
 * (reused from approval_queue). No extra stylesheet needed.
 */
?>
<div class="modal fade" id="timeLogDetailModal" tabindex="-1" aria-labelledby="timeLogDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content time-modal-surface">

            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div>
                    <p class="approval-section-eyebrow mb-1" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--hospital-teal,#0F9F95)">Shift detail</p>
                    <h5 class="modal-title font-prompt fw-bold" id="timeLogDetailModalLabel" style="font-size:1.1rem;color:#082B45">รายละเอียดรายการลงเวลาเวร</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>

            <div class="modal-body px-4 pb-2 pt-2">

                <!-- Error state -->
                <div id="timeLogDetailError" class="alert alert-danger rounded-4 mb-3 d-none" role="alert"></div>

                <!-- Loading state -->
                <div id="timeLogDetailLoading" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    กำลังโหลดข้อมูลรายการลงเวลาเวร...
                </div>

                <!-- Content -->
                <div id="timeLogDetailContent" class="shift-review-detail d-none">

                    <!-- Staff summary card -->
                    <section class="shift-review-staff-card" id="timeLogDetailRoot">
                        <span class="shift-review-avatar" id="timeLogDetailAvatar">
                            <img id="timeLogDetailAvatarImg" alt="รูปประจำตัวเจ้าหน้าที่" class="d-none"
                                 onerror="this.classList.add('d-none');document.getElementById('timeLogDetailAvatarIcon').classList.remove('d-none')">
                            <i class="bi bi-person-badge" id="timeLogDetailAvatarIcon"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="shift-review-staff-head">
                                <div class="min-w-0">
                                    <p class="shift-review-label">ผู้ปฏิบัติงาน</p>
                                    <h3 id="timeLogDetailFullname" style="font-size:1.05rem;font-weight:700;color:#082B45;margin:0">-</h3>
                                </div>
                                <span id="timeLogDetailStatusBadge" class="status-chip">-</span>
                            </div>
                            <div class="shift-review-staff-meta">
                                <span><i class="bi bi-briefcase"></i><span id="timeLogDetailPosition">-</span></span>
                                <span><i class="bi bi-building"></i><span id="timeLogDetailDepartment">-</span></span>
                                <span><i class="bi bi-hash"></i>รายการ #<span id="timeLogDetailRecordId">-</span></span>
                            </div>
                        </div>
                    </section>

                    <!-- Shift detail grid -->
                    <section class="shift-review-grid" aria-label="ข้อมูลการลงเวลาเวร">
                        <div class="shift-review-field">
                            <span>วันที่เวร</span>
                            <strong id="timeLogDetailWorkDate">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>เวลาเข้างาน</span>
                            <strong id="timeLogDetailTimeIn">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>เวลาออกงาน</span>
                            <strong id="timeLogDetailTimeOut">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>ชั่วโมงรวม</span>
                            <strong id="timeLogDetailHours">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>ประเภทเวร/กะ</span>
                            <strong id="timeLogDetailShiftType">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>แผนกที่ลงเวร</span>
                            <strong id="timeLogDetailWorkDepartment">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>สถานะ</span>
                            <strong id="timeLogDetailStatusText">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>ผู้ตรวจสอบ</span>
                            <strong id="timeLogDetailChecker">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>บันทึกเมื่อ</span>
                            <strong id="timeLogDetailCreatedAt">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>แก้ไขล่าสุด</span>
                            <strong id="timeLogDetailUpdatedAt">-</strong>
                        </div>
                        <div class="shift-review-field span-2">
                            <span>หมายเหตุ</span>
                            <strong id="timeLogDetailNote">-</strong>
                        </div>
                        <div class="shift-review-field span-2" id="timeLogDetailApprovalNoteRow">
                            <span>เหตุผลการตีกลับ</span>
                            <strong id="timeLogDetailApprovalNote">-</strong>
                        </div>
                    </section>

                    <!-- Audit trail (shown only if records exist) -->
                    <section class="shift-review-audit" id="timeLogDetailAuditSection" style="display:none">
                        <div class="shift-review-audit-head">
                            <p class="shift-review-label">ประวัติการดำเนินการ</p>
                            <span>อ้างอิง time_logs.id: <strong id="timeLogDetailRawId">-</strong></span>
                        </div>
                        <div id="timeLogDetailAuditList" class="shift-review-audit-list"></div>
                    </section>

                </div><!-- /#timeLogDetailContent -->

            </div><!-- /.modal-body -->

            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="dash-btn dash-btn-ghost" data-bs-dismiss="modal">ปิด</button>
            </div>

        </div>
    </div>
</div>
