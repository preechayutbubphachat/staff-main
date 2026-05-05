<?php
function app_manage_logs_status_label(array $row): array
{
    if ((float) ($row['work_hours'] ?? 0) <= 0 || empty($row['time_in']) || empty($row['time_out'])) {
        return [
            'label' => 'ต้องแก้ไข',
            'badge' => 'danger',
            'lock' => '',
        ];
    }

    $locked = !empty($row['checked_at']);

    return [
        'label' => $locked ? 'อนุมัติแล้ว' : 'รอตรวจ',
        'badge' => $locked ? 'success' : 'warning',
        'lock' => $locked ? 'ล็อกแล้ว' : '',
    ];
}

$summary = $summary ?? [];
$filters = $filters ?? [];
$rows = $rows ?? [];
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 20);
$totalRows = (int) ($totalRows ?? count($rows));
$totalPages = (int) ($totalPages ?? 1);
$csrfToken = $csrfToken ?? app_csrf_token('manage_time_logs');

$totalLogs = (int) ($summary['total_rows'] ?? $totalRows);
$uniqueStaff = (int) ($summary['unique_staff_count'] ?? 0);
$uniqueDepartments = (int) ($summary['unique_department_count'] ?? 0);
$checkedCount = (int) ($summary['checked_count'] ?? 0);
$pendingCount = (int) ($summary['pending_count'] ?? 0);
$totalHours = (float) ($summary['total_hours'] ?? 0);
$issueCount = max(0, $totalLogs - $checkedCount - $pendingCount);
$checkedPercent = $totalLogs > 0 ? ($checkedCount / $totalLogs) * 100 : 0;
$pendingPercent = $totalLogs > 0 ? ($pendingCount / $totalLogs) * 100 : 0;
$issuePercent = $totalLogs > 0 ? ($issueCount / $totalLogs) * 100 : 0;
$fromRow = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$toRow = min($totalRows, $page * $perPage);
?>

<section class="manage-time-summary-row" data-results-summary>
    <article class="dash-kpi-card manage-time-summary-card">
        <span class="manage-time-summary-icon is-blue"><i class="bi bi-calendar3"></i></span>
        <div>
            <p>ช่วงข้อมูล</p>
            <strong><?= htmlspecialchars($periodLabel ?? app_format_thai_month_year(date('Y-m'))) ?></strong>
            <span>ช่วงเวลาที่เลือก</span>
        </div>
    </article>
    <article class="dash-kpi-card manage-time-summary-card">
        <span class="manage-time-summary-icon is-green"><i class="bi bi-check-circle"></i></span>
        <div>
            <p>ลงเวลาแล้ว</p>
            <strong><?= number_format($checkedCount) ?> รายการ</strong>
            <span>คิดเป็น <?= number_format($checkedPercent, 2) ?>%</span>
        </div>
    </article>
    <article class="dash-kpi-card manage-time-summary-card">
        <span class="manage-time-summary-icon is-amber"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <p>รอตรวจสอบ</p>
            <strong><?= number_format($pendingCount) ?> รายการ</strong>
            <span>คิดเป็น <?= number_format($pendingPercent, 2) ?>%</span>
        </div>
    </article>
    <article class="dash-kpi-card manage-time-summary-card">
        <span class="manage-time-summary-icon is-danger"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <p>ต้องแก้ไข</p>
            <strong><?= number_format($issueCount) ?> รายการ</strong>
            <span>คิดเป็น <?= number_format($issuePercent, 2) ?>%</span>
        </div>
    </article>
</section>

<?php
/* Build export query from $filters (available on both full-page load and AJAX refresh).
   $queryBase may not be passed in the AJAX closure for this partial, so we derive it here. */
$_mtExportBase = isset($queryBase) ? $queryBase : [
    'name'          => $filters['name']          ?? '',
    'position_name' => $filters['position_name'] ?? '',
    'department'    => $filters['department']    ?? '',
    'date_from'     => $filters['date_from']     ?? '',
    'date_to'       => $filters['date_to']       ?? '',
    'status'        => $filters['status']        ?? 'all',
    'per_page'      => $perPage,
];
$_mtPrintQuery = app_build_table_query($_mtExportBase, ['type' => 'manage']);
$_mtPdfQuery   = app_build_table_query($_mtExportBase, ['type' => 'manage', 'download' => 'pdf']);
$_mtCsvQuery   = app_build_table_query($_mtExportBase, ['type' => 'manage']);
?>
<section class="dash-card manage-time-results-panel" id="manage-time-results-panel">
    <div class="manage-time-results-header">
        <div>
            <h2 class="manage-time-card-title">รายการลงเวลาเวรที่จัดการได้</h2>
            <p class="manage-time-card-copy">เปิดดูรายละเอียด แก้ไข และจัดการรายการลงเวลาตามสิทธิ์ของผู้ดูแลระบบ</p>
        </div>
        <div class="report-action-group">
            <div class="manage-time-view-switch" aria-label="ตัวเลือกมุมมอง">
                <button type="button"><i class="bi bi-grid-3x3-gap"></i>ตัวเลือกคอลัมน์</button>
                <button type="button" class="active"><i class="bi bi-table"></i>มุมมองตาราง</button>
            </div>
            <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="manage"
               href="report_print.php?<?= htmlspecialchars($_mtPrintQuery) ?>" target="_blank" rel="noopener">
                <i class="bi bi-printer"></i>พิมพ์
            </a>
            <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="manage" data-export-download="pdf"
               href="report_print.php?<?= htmlspecialchars($_mtPdfQuery) ?>" target="_blank" rel="noopener">
                <i class="bi bi-filetype-pdf"></i>PDF
            </a>
            <a class="dash-btn dash-btn-ghost" data-export-base="export_report.php" data-export-type="manage"
               href="export_report.php?<?= htmlspecialchars($_mtCsvQuery) ?>">
                <i class="bi bi-filetype-csv"></i>CSV
            </a>
        </div>
    </div>

    <div class="manage-time-table-shell">
        <table class="manage-time-table">
            <?php app_render_table_colgroup('manage_time_logs'); ?>
            <thead>
                <tr>
                    <th><input type="checkbox" class="form-check-input" aria-label="เลือกรายการทั้งหมด"></th>
                    <th>ลำดับ</th>
                    <th>วันที่</th>
                    <th>ชื่อพนักงาน</th>
                    <th>ตำแหน่ง</th>
                    <th>แผนก</th>
                    <th>เวลาเข้า</th>
                    <th>เวลาออก</th>
                    <th>ชั่วโมงรวม</th>
                    <th>หมายเหตุ</th>
                    <th>สถานะ</th>
                    <th>ผู้ตรวจสอบ</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="13">
                            <div class="manage-time-empty-state">
                                <i class="bi bi-folder-x"></i>
                                <strong>ไม่พบรายการตามเงื่อนไขที่เลือก</strong>
                                <span>ลองเปลี่ยนคำค้นหา แผนก สถานะ หรือช่วงวันที่อีกครั้ง</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $status = app_manage_logs_status_label($row);
                    $canEditRow = app_can_edit_time_log_record($row);
                    $canResetApproval = !empty($row['checked_at']) && app_can('can_edit_locked_time_logs');
                    $rowNumber = app_table_row_number($page, $perPage, $index);
                    $workDate = (string) ($row['work_date'] ?? '');
                    $dayNumber = $workDate !== '' ? date('j', strtotime($workDate)) : '-';
                    $monthYear = $workDate !== '' ? app_format_thai_date($workDate) : '-';
                    ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" aria-label="เลือกรายการลำดับ <?= (int) $rowNumber ?>"></td>
                        <td class="manage-time-row-number"><?= $rowNumber ?></td>
                        <td>
                            <span class="manage-time-date-tile">
                                <strong><?= htmlspecialchars((string) $dayNumber) ?></strong>
                                <span><?= htmlspecialchars($monthYear) ?></span>
                            </span>
                        </td>
                        <td class="manage-time-name-cell">
                            <button type="button" class="manage-time-staff-link" data-profile-modal-trigger data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>" title="<?= htmlspecialchars($row['fullname'] ?? '-') ?>">
                                <?= htmlspecialchars($row['fullname'] ?? '-') ?>
                            </button>
                        </td>
                        <td><span class="manage-time-muted-text" title="<?= htmlspecialchars($row['position_name'] ?? '-') ?>"><?= htmlspecialchars($row['position_name'] ?: '-') ?></span></td>
                        <td><span class="manage-time-department-pill"><?= htmlspecialchars($row['department_name'] ?? '-') ?></span></td>
                        <td><?= !empty($row['time_in']) ? date('H:i', strtotime((string) $row['time_in'])) : '-' ?></td>
                        <td><?= !empty($row['time_out']) ? date('H:i', strtotime((string) $row['time_out'])) : '-' ?></td>
                        <td><span class="manage-time-hours"><?= number_format((float) ($row['work_hours'] ?? 0), 2) ?></span></td>
                        <td><span class="manage-time-note" title="<?= htmlspecialchars($row['note'] ?: '-') ?>"><?= htmlspecialchars($row['note'] ?: '-') ?></span></td>
                        <td>
                            <span class="manage-time-status is-<?= htmlspecialchars($status['badge']) ?>"><?= htmlspecialchars($status['label']) ?></span>
                            <?php if ($status['lock'] !== ''): ?>
                                <span class="manage-time-status-detail"><?= htmlspecialchars($status['lock']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="manage-time-muted-text" title="<?= htmlspecialchars($row['checker_name'] ?? '-') ?>"><?= htmlspecialchars($row['checker_name'] ?: '-') ?></span></td>
                        <td>
                            <div class="manage-time-row-actions">
                                <button type="button" class="manage-time-row-btn" data-bs-toggle="modal" data-bs-target="#detailModal<?= (int) $row['id'] ?>">
                                    ดูรายละเอียด
                                </button>
                                <?php if ($canEditRow): ?>
                                    <a href="edit_time_log.php?id=<?= (int) $row['id'] ?>" class="manage-time-edit-btn" data-manage-edit-link data-id="<?= (int) $row['id'] ?>">
                                        แก้ไข
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="manage-time-edit-btn is-disabled" disabled>ล็อก</button>
                                <?php endif; ?>

                                <?php if ($canResetApproval): ?>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="reset_approval">
                                        <input type="hidden" name="time_log_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="manage-time-row-menu is-danger" aria-label="รีเซ็ตสถานะ" onclick="return confirm('ยืนยันการรีเซ็ตสถานะการอนุมัติรายการนี้?')">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="manage-time-row-menu" aria-label="ตัวเลือกเพิ่มเติม">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="manage-time-table-footer">
        <label class="manage-time-page-size">
            <span>แสดง</span>
            <select name="per_page" form="manageTimeLogsFilterForm">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
            <span>รายการ</span>
        </label>

        <div class="manage-time-page-meta"><?= number_format($fromRow) ?>-<?= number_format($toRow) ?> จาก <?= number_format($totalRows) ?> รายการ</div>

        <?php if ($totalPages > 1): ?>
            <nav class="manage-time-pagination" aria-label="เปลี่ยนหน้ารายการลงเวลาเวร">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $pageQuery = http_build_query(array_filter([
                        'name' => $filters['name'] ?? '',
                        'position_name' => $filters['position_name'] ?? '',
                        'department' => $filters['department'] ?? '',
                        'date_from' => $filters['date_from'] ?? '',
                        'date_to' => $filters['date_to'] ?? '',
                        'status' => $filters['status'] ?? 'all',
                        'per_page' => $perPage,
                        'p' => $i,
                    ], static fn($value) => $value !== '' && $value !== null)); ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= htmlspecialchars($pageQuery) ?>" data-manage-page-link="<?= (int) $i ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php foreach ($rows as $index => $row): ?>
    <div class="modal fade" id="detailModal<?= (int) $row['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-muted">Time log detail</div>
                        <h5 class="modal-title fw-bold">รายละเอียดรายการลำดับ <?= app_table_row_number($page, $perPage, $index) ?></h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="manage-time-detail-grid">
                        <div class="manage-time-detail-card"><strong>ชื่อพนักงาน</strong><span><?= htmlspecialchars($row['fullname'] ?? '-') ?></span></div>
                        <div class="manage-time-detail-card"><strong>ตำแหน่ง</strong><span><?= htmlspecialchars($row['position_name'] ?? '-') ?></span></div>
                        <div class="manage-time-detail-card"><strong>แผนก</strong><span><?= htmlspecialchars($row['department_name'] ?? '-') ?></span></div>
                        <div class="manage-time-detail-card"><strong>วันที่ปฏิบัติงาน</strong><span><?= htmlspecialchars(app_format_thai_date((string) $row['work_date'])) ?></span></div>
                        <div class="manage-time-detail-card"><strong>เวลาเข้า</strong><span><?= !empty($row['time_in']) ? htmlspecialchars(app_format_thai_datetime((string) $row['time_in'])) : '-' ?></span></div>
                        <div class="manage-time-detail-card"><strong>เวลาออก</strong><span><?= !empty($row['time_out']) ? htmlspecialchars(app_format_thai_datetime((string) $row['time_out'])) : '-' ?></span></div>
                        <div class="manage-time-detail-card"><strong>ชั่วโมงรวม</strong><span><?= number_format((float) ($row['work_hours'] ?? 0), 2) ?> ชม.</span></div>
                        <div class="manage-time-detail-card"><strong>สถานะ</strong><span><?= htmlspecialchars(app_manage_logs_status_label($row)['label']) ?></span></div>
                        <div class="manage-time-detail-card is-wide"><strong>หมายเหตุ</strong><span><?= htmlspecialchars($row['note'] ?: '-') ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
