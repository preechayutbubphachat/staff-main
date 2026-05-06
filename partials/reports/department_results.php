<?php
$departmentTotals = $departmentTotals ?? ['staff_count' => 0, 'total_logs' => 0, 'total_hours' => 0, 'approved_logs' => 0, 'pending_logs' => 0];
$filters = $filters ?? [];
$pagedRows = $pagedRows ?? [];
$staffRows = $staffRows ?? [];
$headingContext = $headingContext ?? app_get_department_report_heading_context($filters);
$view = $view ?? ($filters['view'] ?? 'table');
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 20);
$totalRows = (int) ($totalRows ?? count($staffRows ?: $pagedRows));
$totalPages = (int) ($totalPages ?? 1);
$queryBase = $queryBase ?? [];
// Ensure status and search are always included in export/pagination query base
if (!isset($queryBase['status']) && isset($filters['status'])) {
    $queryBase['status'] = $filters['status'];
}
if (!isset($queryBase['search']) && !empty($filters['search'])) {
    $queryBase['search'] = $filters['search'];
}
$staffCount = (int) ($departmentTotals['staff_count'] ?? 0);
$totalLogs = (int) ($departmentTotals['total_logs'] ?? 0);
$totalHours = (float) ($departmentTotals['total_hours'] ?? 0);
$approvedLogs = (int) ($departmentTotals['approved_logs'] ?? 0);
$pendingLogs = (int) ($departmentTotals['pending_logs'] ?? 0);
$approvedPercent = $totalLogs > 0 ? ($approvedLogs / $totalLogs) * 100 : 0;
$pendingPercent = $totalLogs > 0 ? ($pendingLogs / $totalLogs) * 100 : 0;
$departmentLogTotals = [];
foreach (($staffRows ?: $pagedRows) as $row) {
    $departmentName = trim((string) ($row['department_name'] ?? '')) ?: '-';
    $departmentLogTotals[$departmentName] = ($departmentLogTotals[$departmentName] ?? 0) + (int) ($row['total_logs'] ?? 0);
}
arsort($departmentLogTotals);
$topDepartmentName = $departmentLogTotals ? (string) array_key_first($departmentLogTotals) : '-';
$topDepartmentLogs = $departmentLogTotals ? (int) reset($departmentLogTotals) : 0;
$topDepartmentPercent = $totalLogs > 0 ? ($topDepartmentLogs / $totalLogs) * 100 : 0;
$scopeLabel = (string) ($headingContext['department_label'] ?? 'ทุกแผนกในระบบ');
$monthYearLabel = (string) ($headingContext['month_year_label'] ?? '');
$fromRow = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$toRow = min($totalRows, $page * $perPage);
?>

<section class="department-report-summary-row" data-results-summary
    data-staff="<?= (int) $staffCount ?>"
    data-logs="<?= (int) $totalLogs ?>"
    data-hours="<?= htmlspecialchars(number_format($totalHours, 2, '.', '')) ?>"
    data-pending="<?= (int) $pendingLogs ?>">
    <article class="dash-kpi-card department-report-summary-card">
        <span class="department-report-summary-icon is-blue"><i class="bi bi-grid-3x3-gap"></i></span>
        <div>
            <p>แผนก</p>
            <strong><?= htmlspecialchars($scopeLabel) ?></strong>
            <span>ขอบเขตรายงาน</span>
        </div>
    </article>
    <article class="dash-kpi-card department-report-summary-card">
        <span class="department-report-summary-icon is-green"><i class="bi bi-calendar2-week"></i></span>
        <div>
            <p>เดือนปัจจุบัน</p>
            <strong><?= htmlspecialchars($monthYearLabel) ?></strong>
            <span>ช่วงเวลาที่เลือก</span>
        </div>
    </article>
    <article class="dash-kpi-card department-report-summary-card">
        <span class="department-report-summary-icon is-mint"><i class="bi bi-clock"></i></span>
        <div>
            <p>ชั่วโมงรวม</p>
            <strong><?= number_format($totalHours, 2) ?> ชม.</strong>
            <span>รวมเวลาปฏิบัติงานทั้งหมด</span>
        </div>
    </article>
    <article class="dash-kpi-card department-report-summary-card">
        <span class="department-report-summary-icon is-amber"><i class="bi bi-person-badge"></i></span>
        <div>
            <p>แผนกที่มีเวรสูงสุด</p>
            <strong><?= htmlspecialchars($topDepartmentName) ?></strong>
            <span><?= number_format($topDepartmentLogs) ?> เวร (<?= number_format($topDepartmentPercent, 1) ?>%)</span>
        </div>
    </article>
</section>

<?php
/* Compute export URLs from $queryBase (available on both full-page load and AJAX refresh) */
$_deptExportBase = $queryBase ?? [];
$_deptPrintQuery = app_build_table_query($_deptExportBase, ['type' => 'department']);
$_deptPdfQuery   = app_build_table_query($_deptExportBase, ['type' => 'department', 'download' => 'pdf']);
$_deptCsvQuery   = app_build_table_query($_deptExportBase, ['type' => 'department']);
?>
<section class="dash-card department-report-results-panel" id="department-report-results-panel">
    <div class="department-report-results-header">
        <div>
            <h2 class="department-report-card-title">รายการสรุปรายแผนก</h2>
            <p class="department-report-card-copy">สรุปจำนวนเวร ชั่วโมงรวม และสถานะการตรวจของเจ้าหน้าที่ในขอบเขตรายงานที่เลือก</p>
        </div>
        <div class="report-action-group">
            <div class="department-report-view-switch">
                <a class="<?= $view === 'table' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'table', 'p' => 1])) ?>" data-table-view-link><i class="bi bi-table"></i>ตาราง</a>
                <a class="<?= $view === 'cards' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'cards', 'p' => 1])) ?>" data-table-view-link><i class="bi bi-grid"></i>การ์ด</a>
            </div>
            <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="department"
               href="report_print.php?<?= htmlspecialchars($_deptPrintQuery) ?>" target="_blank" rel="noopener">
                <i class="bi bi-printer"></i>พิมพ์
            </a>
            <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="department" data-export-download="pdf"
               href="report_print.php?<?= htmlspecialchars($_deptPdfQuery) ?>" target="_blank" rel="noopener">
                <i class="bi bi-filetype-pdf"></i>PDF
            </a>
            <a class="dash-btn dash-btn-ghost" data-export-base="export_report.php" data-export-type="department"
               href="export_report.php?<?= htmlspecialchars($_deptCsvQuery) ?>">
                <i class="bi bi-filetype-csv"></i>CSV
            </a>
        </div>
    </div>

    <?php if (!$pagedRows): ?>
        <div class="department-report-empty-state">
            <i class="bi bi-folder-x"></i>
            <strong>ไม่พบข้อมูลตามเงื่อนไขที่เลือก</strong>
            <span>ลองเปลี่ยนแผนก เดือน ปี สถานะ หรือคำค้นหาอีกครั้ง</span>
        </div>
    <?php elseif ($view === 'cards'): ?>
        <div class="department-report-card-list">
            <?php foreach ($pagedRows as $index => $row): ?>
                <?php $rowPending = max(0, (int) $row['total_logs'] - (int) $row['approved_logs']); ?>
                <article class="department-report-person-card">
                    <div class="department-report-card-top">
                        <div>
                            <div class="text-xs font-bold text-hospital-muted">ลำดับที่ <?= app_table_row_number($page, $perPage, $index) ?></div>
                            <button type="button" class="department-report-staff-link" data-profile-modal-trigger data-user-id="<?= (int) ($row['id'] ?? 0) ?>">
                                <?= htmlspecialchars(app_user_display_name($row)) ?>
                            </button>
                            <p><?= htmlspecialchars($row['position_name'] ?: 'ไม่ระบุตำแหน่ง') ?> · <?= htmlspecialchars($row['department_name'] ?? '-') ?></p>
                        </div>
                        <span class="department-report-count-badge is-success"><?= (int) $row['approved_logs'] ?> ตรวจแล้ว</span>
                    </div>
                    <div class="department-report-card-stats">
                        <span><?= (int) $row['total_logs'] ?> เวร</span>
                        <span><?= number_format((float) $row['total_hours'], 2) ?> ชม.</span>
                        <span><?= $rowPending ?> รอตรวจ</span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="department-report-table-shell">
            <table class="department-report-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="form-check-input" aria-label="เลือกรายการทั้งหมด"></th>
                        <th>ลำดับ</th>
                        <th>ชื่อเจ้าหน้าที่</th>
                        <th>ตำแหน่ง</th>
                        <th>แผนก</th>
                        <th>จำนวนเวร</th>
                        <th>ชั่วโมงรวม</th>
                        <th>ตรวจแล้ว</th>
                        <th>รอตรวจ</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagedRows as $index => $row): ?>
                        <?php $rowPending = max(0, (int) $row['total_logs'] - (int) $row['approved_logs']); ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input" aria-label="เลือกรายการที่ <?= app_table_row_number($page, $perPage, $index) ?>"></td>
                            <td class="department-report-row-number"><?= app_table_row_number($page, $perPage, $index) ?></td>
                            <td>
                                <button type="button" class="department-report-staff-link" data-profile-modal-trigger data-user-id="<?= (int) ($row['id'] ?? 0) ?>">
                                    <?= htmlspecialchars(app_user_display_name($row)) ?>
                                </button>
                            </td>
                            <td><?= htmlspecialchars($row['position_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>
                            <td><?= (int) $row['total_logs'] ?></td>
                            <td><span class="department-report-hours"><?= number_format((float) $row['total_hours'], 2) ?></span></td>
                            <td><span class="department-report-count-badge is-success"><?= (int) $row['approved_logs'] ?></span></td>
                            <td><span class="department-report-count-badge is-warning"><?= $rowPending ?></span></td>
                            <td>
                                <div class="department-report-row-actions">
                                    <button type="button" class="department-report-row-btn"
                                            data-dept-report-detail-trigger
                                            data-user-id="<?= (int) ($row['id'] ?? 0) ?>"
                                            data-year="<?= (int) ($filters['year_ce'] ?? date('Y')) ?>"
                                            data-month="<?= (int) ($filters['month_number'] ?? date('n')) ?>">ดูรายละเอียด</button>
                                    <button type="button" class="department-report-row-menu" aria-label="ตัวเลือกเพิ่มเติม"><i class="bi bi-chevron-down"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="department-report-table-footer">
        <label class="department-report-page-size">
            <span>แสดง</span>
            <select name="per_page" form="departmentReportsFilterForm">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
            <span>รายการ</span>
        </label>

        <div class="department-report-page-meta"><?= $totalRows > 0 ? number_format($fromRow) . '-' . number_format($toRow) . ' จาก ' . number_format($totalRows) . ' รายการ' : 'ไม่มีรายการ' ?></div>

        <?php if ($totalPages > 1): ?>
            <nav class="department-report-pagination" aria-label="เปลี่ยนหน้ารายงานแผนก">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => $view, 'p' => $i])) ?>" data-table-page-link><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<section class="dash-card department-report-bottom-strip" data-bottom-summary aria-label="สรุปข้อมูลรายงานแผนก">
    <div class="department-report-bottom-heading">สรุปข้อมูลรายงานแผนก</div>
    <div class="department-report-bottom-item">
        <span>จำนวนเจ้าหน้าที่</span>
        <strong><?= number_format($staffCount) ?> คน</strong>
        <small>จากทั้งหมด <?= number_format($staffCount) ?> คน</small>
    </div>
    <div class="department-report-bottom-item">
        <span>จำนวนเวร</span>
        <strong><?= number_format($totalLogs) ?> เวร</strong>
        <small>จากทั้งหมด <?= number_format($totalLogs) ?> เวร</small>
    </div>
    <div class="department-report-bottom-item">
        <span>ชั่วโมงรวม</span>
        <strong><?= number_format($totalHours, 2) ?> ชม.</strong>
        <small>เฉลี่ย <?= $staffCount > 0 ? number_format($totalHours / $staffCount, 2) : '0.00' ?> ชม./คน</small>
    </div>
    <div class="department-report-bottom-item">
        <span>รอตรวจ</span>
        <strong><?= number_format($pendingLogs) ?> รายการ</strong>
        <small>คิดเป็น <?= number_format($pendingPercent, 2) ?>%</small>
    </div>
    <div class="department-report-bottom-progress">
        <div>
            <span>ความคืบหน้าการตรวจสอบ</span>
            <strong><?= number_format($approvedPercent, 1) ?>%</strong>
        </div>
        <div class="department-report-progress-track">
            <span style="width: <?= htmlspecialchars((string) min(100, max(0, $approvedPercent))) ?>%"></span>
        </div>
        <small>ตรวจแล้ว <?= number_format($approvedLogs) ?> / <?= number_format($totalLogs) ?> รายการ</small>
    </div>
</section>
