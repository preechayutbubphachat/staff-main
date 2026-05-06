<?php
/**
 * Department report — per-staff detail modal.
 *
 * Include once on pages that render department report rows.
 * Populated via JS (openDeptReportDetail) calling
 * ajax/reports/get_dept_staff_detail.php.
 *
 * Reuses .shift-review-* CSS classes (already compiled into
 * dashboard-tailwind.output.css) — no extra stylesheet needed.
 */
?>
<div class="modal fade" id="deptReportDetailModal" tabindex="-1"
     aria-labelledby="deptReportDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content time-modal-surface">

            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div>
                    <p class="approval-section-eyebrow mb-1"
                       style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--hospital-teal,#0F9F95)">
                        Department summary
                    </p>
                    <h5 class="modal-title font-prompt fw-bold"
                        id="deptReportDetailModalLabel"
                        style="font-size:1.1rem;color:#082B45">
                        รายละเอียดสรุปรายบุคคล
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>

            <div class="modal-body px-4 pb-2 pt-2">

                <!-- Error state -->
                <div id="deptReportDetailError" class="alert alert-danger rounded-4 mb-3 d-none" role="alert"></div>

                <!-- Loading state -->
                <div id="deptReportDetailLoading" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    กำลังโหลดข้อมูลสรุปรายบุคคล...
                </div>

                <!-- Content -->
                <div id="deptReportDetailContent" class="shift-review-detail d-none">

                    <!-- Staff identity card -->
                    <section class="shift-review-staff-card">
                        <span class="shift-review-avatar">
                            <img id="deptReportDetailAvatarImg"
                                 alt="รูปประจำตัวเจ้าหน้าที่"
                                 class="d-none"
                                 onerror="this.classList.add('d-none');document.getElementById('deptReportDetailAvatarIcon').classList.remove('d-none')">
                            <i class="bi bi-person-badge" id="deptReportDetailAvatarIcon"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="shift-review-staff-head">
                                <div class="min-w-0">
                                    <p class="shift-review-label">ผู้ปฏิบัติงาน</p>
                                    <h3 id="deptReportDetailFullname"
                                        style="font-size:1.05rem;font-weight:700;color:#082B45;margin:0">-</h3>
                                </div>
                                <!-- Period chip -->
                                <span id="deptReportDetailPeriodChip"
                                      class="status-chip neutral"
                                      style="white-space:nowrap">-</span>
                            </div>
                            <div class="shift-review-staff-meta">
                                <span><i class="bi bi-briefcase"></i><span id="deptReportDetailPosition">-</span></span>
                                <span><i class="bi bi-building"></i><span id="deptReportDetailDepartment">-</span></span>
                            </div>
                        </div>
                    </section>

                    <!-- Monthly KPI grid -->
                    <section class="shift-review-grid" aria-label="สรุปรายเดือน" style="margin-top:1rem">
                        <div class="shift-review-field">
                            <span>ช่วงเวลา</span>
                            <strong id="deptReportDetailPeriod">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>จำนวนเวรทั้งหมด</span>
                            <strong id="deptReportDetailTotalLogs">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>ชั่วโมงรวม</span>
                            <strong id="deptReportDetailTotalHours">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>ตรวจสอบแล้ว</span>
                            <strong id="deptReportDetailApproved">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>รอตรวจสอบ</span>
                            <strong id="deptReportDetailPending">-</strong>
                        </div>
                        <div class="shift-review-field">
                            <span>แผนก</span>
                            <strong id="deptReportDetailDeptName">-</strong>
                        </div>
                    </section>

                    <!-- Progress bar: approved / total -->
                    <section style="margin-top:1.25rem" id="deptReportDetailProgressSection">
                        <p class="shift-review-label mb-1">ความคืบหน้าการตรวจสอบ</p>
                        <div class="progress rounded-pill" style="height:10px">
                            <div id="deptReportDetailProgressBar"
                                 class="progress-bar bg-success rounded-pill"
                                 role="progressbar"
                                 style="width:0%"
                                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            </div>
                        </div>
                        <p id="deptReportDetailProgressLabel"
                           class="text-muted mt-1" style="font-size:0.8rem">
                            -
                        </p>
                    </section>

                </div><!-- /#deptReportDetailContent -->

            </div><!-- /.modal-body -->

            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="dash-btn dash-btn-ghost" data-bs-dismiss="modal">ปิด</button>
            </div>

        </div>
    </div>
</div>
