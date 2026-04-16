<?php
function app_manage_logs_status_label(array $row): array
{
    $locked = !empty($row['checked_at']);
    return [
        'label' => $locked ? 'อนุมัติแล้ว' : 'รอตรวจ',
        'badge' => $locked ? 'success' : 'warning',
        'lock' => $locked ? 'ล็อกแล้ว' : '',
    ];
}
?>
<section class="ops-results-panel" id="manageTimeLogsRowsSection">
    <section class="row g-4 mb-4" data-results-summary>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">จำนวนรายการ</div>
                <div class="report-stat-value"><?= number_format((int) ($summary['total_rows'] ?? $totalRows ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">จำนวนเจ้าหน้าที่</div>
                <div class="report-stat-value"><?= number_format((int) ($summary['unique_staff_count'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">จำนวนแผนก</div>
                <div class="report-stat-value"><?= number_format((int) ($summary['unique_department_count'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">ชั่วโมงรวม</div>
                <div class="report-stat-value"><?= number_format((float) ($summary['total_hours'] ?? 0), 2) ?></div>
            </div>
        </div>
    </section>

    <div class="ops-results-header">
        <div>
            <h2 class="ops-results-title">รายการลงเวลาเวรที่จัดการได้</h2>
            <p class="ops-results-subtitle">เปิดดูรายละเอียด แก้ไขรายการ หรือรีเซ็ตสถานะอนุมัติจากตารางเดียว โดยยังคงขอบเขตข้อมูลตามสิทธิ์ที่ได้รับ</p>
        </div>
        <div class="ops-summary-chip">
            <i class="bi bi-diagram-3"></i>
            <span><?= number_format($totalRows) ?> รายการในขอบเขตนี้</span>
        </div>
    </div>

    <div class="table-toolbar compact mb-3">
        <div class="table-toolbar-main">
            <div class="table-toolbar-help">เปลี่ยนตัวกรองแล้วตารางจะรีเฟรชอัตโนมัติ พร้อมคงการแบ่งหน้าและสิทธิ์การเข้าถึงข้อมูลให้ตรงกับสิ่งที่เห็นอยู่</div>
        </div>
    </div>

    <div class="table-shell">
        <table class="table align-middle ops-table mb-0">
            <?php app_render_table_colgroup('manage_time_logs'); ?>
            <thead class="table-light">
                <tr>
                    <th>ลำดับ</th>
                    <th>วันที่</th>
                    <th>ชื่อเจ้าหน้าที่</th>
                    <th>ตำแหน่ง</th>
                    <th>แผนก</th>
                    <th>เวลาเข้า</th>
                    <th>เวลาออก</th>
                    <th>ชั่วโมงรวม</th>
                    <th>หมายเหตุ</th>
                    <th>สถานะ</th>
                    <th>ตรวจโดย</th>
                    <th>ตรวจเมื่อ</th>
                    <th class="actions-cell">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="13" class="ops-empty">ไม่พบรายการตามเงื่อนไขที่เลือก</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $status = app_manage_logs_status_label($row);
                    $canEditRow = app_can_edit_time_log_record($row);
                    $canResetApproval = !empty($row['checked_at']) && app_can('can_edit_locked_time_logs');
                    $rowNumber = app_table_row_number($page, $perPage, $index);
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= $rowNumber ?></td>
                        <td><?= htmlspecialchars(app_format_thai_date((string) $row['work_date'])) ?></td>
                        <td class="name-cell">
                            <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>" title="<?= htmlspecialchars($row['fullname'] ?? '-') ?>">
                                <span class="truncate"><?= htmlspecialchars($row['fullname'] ?? '-') ?></span>
                            </button>
                        </td>
                        <td class="position-cell"><span class="truncate" title="<?= htmlspecialchars($row['position_name'] ?? '-') ?>"><?= htmlspecialchars($row['position_name'] ?? '-') ?></span></td>
                        <td class="department-cell"><span class="truncate" title="<?= htmlspecialchars($row['department_name'] ?? '-') ?>"><?= htmlspecialchars($row['department_name'] ?? '-') ?></span></td>
                        <td><?= !empty($row['time_in']) ? date('H:i', strtotime((string) $row['time_in'])) : '-' ?></td>
                        <td><?= !empty($row['time_out']) ? date('H:i', strtotime((string) $row['time_out'])) : '-' ?></td>
                        <td><?= number_format((float) $row['work_hours'], 2) ?></td>
                        <td class="note-cell"><span class="truncate" title="<?= htmlspecialchars($row['note'] ?: '-') ?>"><?= htmlspecialchars($row['note'] ?: '-') ?></span></td>
                        <td>
                            <span class="status-badge text-bg-<?= $status['badge'] ?>"><?= htmlspecialchars($status['label']) ?></span>
                            <?php if ($status['lock'] !== ''): ?>
                                <span class="ops-status-note"><?= htmlspecialchars($status['lock']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="name-cell"><span class="truncate" title="<?= htmlspecialchars($row['checker_name'] ?? '-') ?>"><?= htmlspecialchars($row['checker_name'] ?? '-') ?></span></td>
                        <td><?= !empty($row['checked_at']) ? htmlspecialchars(app_format_thai_datetime((string) $row['checked_at'])) : '-' ?></td>
                        <td class="actions-cell">
                            <div class="ops-actions">
                                <?php if ($canEditRow): ?>
                                    <a href="edit_time_log.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary ops-action-btn" data-manage-edit-link data-id="<?= (int) $row['id'] ?>" title="<?= !empty($row['checked_at']) ? 'แก้ไขแบบสิทธิ์พิเศษ' : 'แก้ไขรายการ' ?>">
                                        <i class="bi bi-pencil-square"></i>
                                        <span><?= !empty($row['checked_at']) ? 'แก้ไขพิเศษ' : 'แก้ไข' ?></span>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ops-action-btn" disabled title="รายการนี้ถูกล็อกแล้ว">
                                        <i class="bi bi-lock"></i>
                                        <span>ล็อก</span>
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="btn btn-sm btn-outline-dark ops-action-btn" data-bs-toggle="modal" data-bs-target="#detailModal<?= (int) $row['id'] ?>" title="ดูรายละเอียด">
                                    <i class="bi bi-eye"></i>
                                    <span>รายละเอียด</span>
                                </button>

                                <?php if ($canResetApproval): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="reset_approval">
                                        <input type="hidden" name="time_log_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger ops-action-btn" title="รีเซ็ตการอนุมัติ" onclick="return confirm('ยืนยันการรีเซ็ตสถานะการอนุมัติรายการนี้?')">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            <span>รีเซ็ต</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $pageQuery = http_build_query(array_filter([
                        'name' => $filters['name'],
                        'position_name' => $filters['position_name'],
                        'department' => $filters['department'],
                        'date_from' => $filters['date_from'],
                        'date_to' => $filters['date_to'],
                        'status' => $filters['status'],
                        'per_page' => $perPage,
                        'p' => $i,
                    ], static fn($value) => $value !== '' && $value !== null)); ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars($pageQuery) ?>" data-manage-page-link="<?= (int) $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>

<?php foreach ($rows as $index => $row): ?>
    <div class="modal fade" id="detailModal<?= (int) $row['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title">รายละเอียดรายการ ลำดับ <?= app_table_row_number($page, $perPage, $index) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ops-detail-grid">
                        <div class="ops-detail-card"><strong>ชื่อเจ้าหน้าที่</strong><?= htmlspecialchars($row['fullname'] ?? '-') ?></div>
                        <div class="ops-detail-card"><strong>ตำแหน่ง</strong><?= htmlspecialchars($row['position_name'] ?? '-') ?></div>
                        <div class="ops-detail-card"><strong>แผนก</strong><?= htmlspecialchars($row['department_name'] ?? '-') ?></div>
                        <div class="ops-detail-card"><strong>วันที่ปฏิบัติงาน</strong><?= htmlspecialchars(app_format_thai_date((string) $row['work_date'])) ?></div>
                        <div class="ops-detail-card"><strong>เวลาเข้า</strong><?= !empty($row['time_in']) ? htmlspecialchars(app_format_thai_datetime((string) $row['time_in'])) : '-' ?></div>
                        <div class="ops-detail-card"><strong>เวลาออก</strong><?= !empty($row['time_out']) ? htmlspecialchars(app_format_thai_datetime((string) $row['time_out'])) : '-' ?></div>
                        <div class="ops-detail-card"><strong>ชั่วโมงรวม</strong><?= number_format((float) $row['work_hours'], 2) ?></div>
                        <div class="ops-detail-card"><strong>สถานะ</strong><?= htmlspecialchars(app_manage_logs_status_label($row)['label']) ?></div>
                        <div class="ops-detail-card" style="grid-column:1 / -1;"><strong>หมายเหตุ</strong><?= htmlspecialchars($row['note'] ?: '-') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
