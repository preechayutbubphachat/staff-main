<?php
$summary = $summary ?? ['total_logs' => 0, 'total_hours' => 0, 'approved_logs' => 0, 'pending_logs' => 0];
$pagedLogs = $pagedLogs ?? [];
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 20);
$totalRows = (int) ($totalRows ?? count($pagedLogs));
$totalPages = (int) ($totalPages ?? 1);
$queryBase = $queryBase ?? [];
$filters = $filters ?? [];
$totalLogs = (int) ($summary['total_logs'] ?? 0);
$totalHours = (float) ($summary['total_hours'] ?? 0);
$approvedLogs = (int) ($summary['approved_logs'] ?? 0);
$pendingLogs = (int) ($summary['pending_logs'] ?? 0);
$approvedPercent = $totalLogs > 0 ? ($approvedLogs / $totalLogs) * 100 : 0;
$pendingPercent = $totalLogs > 0 ? ($pendingLogs / $totalLogs) * 100 : 0;
$latestLog = $logs[0] ?? ($pagedLogs[0] ?? null);
$latestDateLabel = $latestLog ? app_format_thai_date((string) ($latestLog['work_date'] ?? '')) : '-';
$latestTimeLabel = $latestLog && !empty($latestLog['time_in']) ? date('H:i', strtotime((string) $latestLog['time_in'])) . ' น.' : '-';
$periodLabel = trim((string) ($filters['heading_month_year_th'] ?? '')) !== '' ? (string) $filters['heading_month_year_th'] : ($filters['title_range'] ?? '-');
$fromRow = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$toRow = min($totalRows, $page * $perPage);

if (!function_exists('my_report_time_label')) {
    function my_report_time_label(array $row): string
    {
        $timeIn = !empty($row['time_in']) ? date('H:i', strtotime((string) $row['time_in'])) : '';
        $timeOut = !empty($row['time_out']) ? date('H:i', strtotime((string) $row['time_out'])) : '';

        if ($timeIn === '' && $timeOut === '') {
            return '-';
        }

        return ($timeIn !== '' ? $timeIn : '--:--') . ' - ' . ($timeOut !== '' ? $timeOut : '--:--');
    }
}

if (!function_exists('my_report_status_meta')) {
    function my_report_status_meta(array $row): array
    {
        if (!empty($row['checked_at'])) {
            return [
                'label' => 'ตรวจแล้ว',
                'class' => 'success',
                'detail' => trim((string) ($row['checker_name'] ?? '')) !== '' ? (string) $row['checker_name'] : '-',
            ];
        }

        return [
            'label' => 'รอตรวจ',
            'class' => 'warning',
            'detail' => '',
        ];
    }
}
?>

<section class="my-report-summary-row" data-results-summary
    data-total="<?= (int) $totalLogs ?>"
    data-hours="<?= htmlspecialchars(number_format($totalHours, 2, '.', '')) ?>"
    data-approved="<?= (int) $approvedLogs ?>"
    data-pending="<?= (int) $pendingLogs ?>">
    <article class="dash-kpi-card my-report-summary-card">
        <span class="my-report-summary-icon is-blue"><i class="bi bi-calendar2-week"></i></span>
        <div>
            <p>เดือนปัจจุบัน</p>
            <strong><?= htmlspecialchars($periodLabel) ?></strong>
            <span>ช่วงเวลาที่เลือก</span>
        </div>
    </article>
    <article class="dash-kpi-card my-report-summary-card">
        <span class="my-report-summary-icon is-green"><i class="bi bi-clock"></i></span>
        <div>
            <p>ชั่วโมงรวม</p>
            <strong><?= number_format($totalHours, 2) ?> ชม.</strong>
            <span>รวมเวลาปฏิบัติงาน</span>
        </div>
    </article>
    <article class="dash-kpi-card my-report-summary-card">
        <span class="my-report-summary-icon is-mint"><i class="bi bi-check-lg"></i></span>
        <div>
            <p>ตรวจแล้ว</p>
            <strong><?= number_format($approvedLogs) ?> รายการ</strong>
            <span>คิดเป็น <?= number_format($approvedPercent, 2) ?>%</span>
        </div>
    </article>
    <article class="dash-kpi-card my-report-summary-card">
        <span class="my-report-summary-icon is-amber"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <p>รอตรวจ</p>
            <strong><?= number_format($pendingLogs) ?> รายการ</strong>
            <span>คิดเป็น <?= number_format($pendingPercent, 2) ?>%</span>
        </div>
    </article>
</section>

<?php
/* Compute export URLs from $queryBase (available on both full-page load and AJAX refresh) */
$_myExportBase = $queryBase ?? [];
$_myPrintQuery = app_build_table_query($_myExportBase, ['type' => 'my']);
$_myPdfQuery   = app_build_table_query($_myExportBase, ['type' => 'my', 'download' => 'pdf']);
$_myCsvQuery   = app_build_table_query($_myExportBase, ['type' => 'my']);
?>
<section class="dash-card my-report-results-panel" id="my-report-results-panel">
    <div class="my-report-results-header">
        <div>
            <h2 class="my-report-card-title">รายการรายงานของฉัน</h2>
            <p class="my-report-card-copy">ข้อมูลส่วนตัวจะแสดงตามช่วงเวลาที่เลือก และใช้ชุดตัวกรองเดียวกันกับการพิมพ์หรือส่งออก</p>
        </div>
        <div class="report-action-group">
            <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="my"
               href="report_print.php?<?= htmlspecialchars($_myPrintQuery) ?>" target="_blank" rel="noopener">
                <i class="bi bi-printer"></i>พิมพ์
            </a>
            <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="my" data-export-download="pdf"
               href="report_print.php?<?= htmlspecialchars($_myPdfQuery) ?>" target="_blank" rel="noopener">
                <i class="bi bi-filetype-pdf"></i>PDF
            </a>
            <a class="dash-btn dash-btn-ghost" data-export-base="export_report.php" data-export-type="my"
               href="export_report.php?<?= htmlspecialchars($_myCsvQuery) ?>">
                <i class="bi bi-filetype-csv"></i>CSV
            </a>
        </div>
    </div>

    <div class="my-report-table-shell">
        <table class="my-report-table">
            <thead>
                <tr>
                    <th><input type="checkbox" class="form-check-input" aria-label="เลือกรายการทั้งหมด"></th>
                    <th>ลำดับ</th>
                    <th>วันที่</th>
                    <th>แผนก</th>
                    <th>เวลาเข้า - ออก</th>
                    <th>ชั่วโมงรวม</th>
                    <th>หมายเหตุ</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pagedLogs): ?>
                    <tr>
                        <td colspan="9" class="my-report-empty-state">
                            <i class="bi bi-folder-x"></i>
                            <strong>ไม่พบข้อมูลตามเงื่อนไขที่เลือก</strong>
                            <span>ลองเปลี่ยนช่วงเวลา เดือน ปี หรือรูปแบบรายงานอีกครั้ง</span>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($pagedLogs as $index => $log): ?>
                    <?php
                    $statusMeta = my_report_status_meta($log);
                    $note = trim((string) ($log['note'] ?? ''));
                    ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" aria-label="เลือกรายการที่ <?= app_table_row_number($page, $perPage, $index) ?>"></td>
                        <td class="my-report-row-number"><?= app_table_row_number($page, $perPage, $index) ?></td>
                        <td><?= htmlspecialchars(app_format_thai_date((string) $log['work_date'])) ?></td>
                        <td><?= htmlspecialchars($log['department_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(my_report_time_label($log)) ?></td>
                        <td><span class="my-report-hours"><?= number_format((float) ($log['work_hours'] ?? 0), 2) ?></span></td>
                        <td class="my-report-note" title="<?= htmlspecialchars($note !== '' ? $note : '-') ?>"><?= htmlspecialchars($note !== '' ? $note : '-') ?></td>
                        <td>
                            <span class="my-report-status is-<?= htmlspecialchars($statusMeta['class']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span>
                            <?php if ($statusMeta['detail'] !== ''): ?>
                                <span class="my-report-status-detail"><?= htmlspecialchars($statusMeta['detail']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="my-report-row-actions">
                                <button type="button" class="my-report-row-btn" data-time-log-detail-trigger data-time-log-id="<?= (int) ($log['id'] ?? 0) ?>">ดูรายละเอียด</button>
                                <button type="button" class="my-report-row-menu" aria-label="ตัวเลือกเพิ่มเติม"><i class="bi bi-chevron-down"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="my-report-table-footer">
        <label class="my-report-page-size">
            <span>แสดง</span>
            <select name="per_page" form="myReportsFilterForm">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
            <span>รายการ</span>
        </label>

        <div class="my-report-page-meta"><?= number_format($fromRow) ?>-<?= number_format($toRow) ?> จาก <?= number_format($totalRows) ?> รายการ</div>

        <?php if ($totalPages > 1): ?>
            <nav class="my-report-pagination" aria-label="เปลี่ยนหน้ารายงาน">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['p' => $i])) ?>" data-table-page-link><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<section class="dash-card my-report-bottom-strip" data-bottom-summary aria-label="สรุปข้อมูลรายงานของฉัน">
    <div class="my-report-bottom-heading">สรุปข้อมูลรายงานของฉัน</div>
    <div class="my-report-bottom-item">
        <span>รายการทั้งหมด</span>
        <strong><?= number_format($totalLogs) ?> รายการ</strong>
        <small>จากทั้งหมด <?= number_format($totalLogs) ?> รายการ</small>
    </div>
    <div class="my-report-bottom-item">
        <span>ชั่วโมงรวม</span>
        <strong><?= number_format($totalHours, 2) ?> ชม.</strong>
        <small>เฉลี่ย <?= $totalLogs > 0 ? number_format($totalHours / $totalLogs, 2) : '0.00' ?> ชม./รายการ</small>
    </div>
    <div class="my-report-bottom-item">
        <span>ตรวจแล้ว</span>
        <strong><?= number_format($approvedLogs) ?> รายการ</strong>
        <small>คิดเป็น <?= number_format($approvedPercent, 2) ?>%</small>
    </div>
    <div class="my-report-bottom-item">
        <span>อัปเดตล่าสุด</span>
        <strong><?= htmlspecialchars($latestDateLabel) ?></strong>
        <small><?= htmlspecialchars($latestTimeLabel) ?></small>
    </div>
    <div class="my-report-bottom-progress">
        <div>
            <span>ความคืบหน้าการตรวจสอบ</span>
            <strong><?= number_format($approvedPercent, 2) ?>%</strong>
        </div>
        <div class="my-report-progress-track">
            <span style="width: <?= htmlspecialchars((string) min(100, max(0, $approvedPercent))) ?>%"></span>
        </div>
        <small>ตรวจแล้ว <?= number_format($approvedLogs) ?> / <?= number_format($totalLogs) ?> รายการ</small>
    </div>
</section>
