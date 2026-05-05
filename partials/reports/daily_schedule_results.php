<?php
$headingContext = $headingContext ?? ($schedule['heading_context'] ?? app_get_daily_schedule_heading_context($schedule));
$mode = $schedule['mode'] ?? 'daily';
$dateHeading = $dateHeading ?? ($headingContext['main_heading'] ?? ($schedule['date_heading'] ?? 'รายงานเวรประจำวัน'));
$scopeLabel = $scopeLabel ?? ($headingContext['scope_label'] ?? ($schedule['scope_label'] ?? 'ทุกแผนกในระบบ'));
$tableContextLabel = $headingContext['table_context_label'] ?? ($mode === 'monthly' ? 'ตารางสรุปเวรประจำเดือน' : 'ตารางเวรประจำวันที่เลือก');
$periodLabel = $headingContext['period_label'] ?? ($schedule['heading_month_year_th'] ?? ($schedule['date_label'] ?? ''));
$matrixDays = $matrixDays ?? ($schedule['matrix_days'] ?? []);
$pagedMatrixRows = $pagedMatrixRows ?? [];
$pagedGroups = $pagedGroups ?? [];
$pagedLogs = $pagedLogs ?? [];
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 20);
$totalRows = (int) ($totalRows ?? ($schedule['total_rows'] ?? count($schedule['logs'] ?? [])));
$totalPages = (int) ($totalPages ?? 1);
$queryBase = $queryBase ?? [
    'mode' => $mode,
    'date' => $schedule['selected_date'] ?? date('Y-m-d'),
    'month' => $schedule['month_number'] ?? date('n'),
    'year_be' => $schedule['year_be'] ?? ((int) date('Y') + 543),
    'department' => $schedule['selected_department'] ?? '',
    'name' => $schedule['name'] ?? '',
    'review_status' => $schedule['review_status'] ?? 'all',
    'per_page' => $perPage,
];
$scheduleStats = app_daily_schedule_derived_stats($schedule);
$shiftStats = $scheduleStats['shift_stats'];

function daily_schedule_date_tile(?string $date): array
{
    $timestamp = $date ? strtotime(substr($date, 0, 10)) : false;
    $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $days = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

    if (!$timestamp) {
        return ['day' => '-', 'month_year' => '-', 'weekday' => '-'];
    }

    return [
        'day' => date('j', $timestamp),
        'month_year' => ($months[(int) date('n', $timestamp)] ?? '') . ' ' . ((int) date('Y', $timestamp) + 543),
        'weekday' => $days[(int) date('w', $timestamp)] ?? '',
    ];
}

function daily_schedule_row_time_label(array $row): string
{
    $timeIn = app_daily_schedule_clock_value($row, 'time_in');
    $timeOut = app_daily_schedule_clock_value($row, 'time_out');

    if ($timeIn === '' && $timeOut === '') {
        return '-';
    }

    return ($timeIn !== '' ? $timeIn : '--:--') . ' - ' . ($timeOut !== '' ? $timeOut : '--:--');
}

function daily_schedule_hours_label(array $row): string
{
    $hours = $row['work_hours'] ?? null;
    if ($hours === null || $hours === '') {
        return '-';
    }

    return number_format((float) $hours, 2) . ' ชม.';
}
?>

<section class="daily-kpi-row" data-results-summary
    data-total="<?= (int) $scheduleStats['total'] ?>"
    data-active="<?= (int) $scheduleStats['active'] ?>"
    data-completed="<?= (int) $scheduleStats['completed'] ?>"
    data-pending="<?= (int) $scheduleStats['pending'] ?>"
    data-updated="<?= htmlspecialchars($scheduleStats['updated_time_label']) ?>">
    <?php foreach (['morning', 'afternoon', 'night'] as $bucket): ?>
        <?php $shift = $shiftStats[$bucket]; ?>
        <article class="dash-kpi-card daily-kpi-card">
            <span class="daily-kpi-icon is-<?= htmlspecialchars($shift['tone']) ?>"><i class="bi <?= htmlspecialchars($shift['icon']) ?>"></i></span>
            <div>
                <p class="daily-kpi-label"><?= htmlspecialchars($shift['label']) ?></p>
                <div class="daily-kpi-value-line">
                    <strong class="daily-kpi-value"><?= number_format((int) $shift['count']) ?></strong>
                    <span>เวร</span>
                    <em><?= number_format((int) $shift['percent']) ?>%</em>
                </div>
                <p class="daily-kpi-subtitle">กำลังปฏิบัติงาน <?= number_format((int) $shift['active']) ?> รายการ</p>
            </div>
        </article>
    <?php endforeach; ?>
    <article class="dash-kpi-card daily-kpi-card">
        <span class="daily-kpi-icon is-blue"><i class="bi bi-people"></i></span>
        <div>
            <p class="daily-kpi-label">แผนกที่มีเวรสูงสุด</p>
            <div class="daily-kpi-value-line">
                <strong class="daily-kpi-value"><?= htmlspecialchars($scheduleStats['top_department_name']) ?></strong>
            </div>
            <p class="daily-kpi-subtitle"><?= number_format((int) $scheduleStats['top_department_count']) ?> เวร (<?= number_format((float) $scheduleStats['top_department_percent'], 1) ?>%)</p>
        </div>
        <i class="bi bi-chevron-right daily-kpi-arrow"></i>
    </article>
</section>

<?php
/* Compute export URLs from $queryBase (available on both full-page load and AJAX refresh) */
$_dailyExportBase = $queryBase ?? [];
$_dailyPrintQuery = app_build_table_query($_dailyExportBase, ['type' => 'daily']);
$_dailyPdfQuery   = app_build_table_query($_dailyExportBase, ['type' => 'daily', 'download' => 'pdf']);
$_dailyCsvQuery   = app_build_table_query($_dailyExportBase, ['type' => 'daily']);
?>
<section class="dash-card daily-results-panel" id="daily-schedule-results-panel">
    <div class="daily-results-header">
        <div>
            <p class="daily-section-eyebrow">Shift roster</p>
            <h2 class="daily-card-title"><?= $mode === 'monthly' ? 'ตารางสรุปเวรประจำเดือน' : 'รายการเวรวันนี้' ?></h2>
            <p class="daily-card-copy"><?= htmlspecialchars($tableContextLabel) ?> · <?= htmlspecialchars($scopeLabel) ?></p>
        </div>
        <div class="daily-results-toolbar">
            <?php if ($mode === 'daily'): ?>
                <a class="daily-view-pill <?= $view === 'cards' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'cards', 'p' => 1])) ?>" data-table-view-link><i class="bi bi-grid"></i>การ์ด</a>
                <a class="daily-view-pill <?= $view === 'table' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'table', 'p' => 1])) ?>" data-table-view-link><i class="bi bi-table"></i>ตาราง</a>
            <?php endif; ?>
            <div class="report-action-group">
                <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="daily"
                   href="report_print.php?<?= htmlspecialchars($_dailyPrintQuery) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-printer"></i>พิมพ์
                </a>
                <a class="dash-btn dash-btn-ghost" data-export-base="report_print.php" data-export-type="daily" data-export-download="pdf"
                   href="report_print.php?<?= htmlspecialchars($_dailyPdfQuery) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-filetype-pdf"></i>PDF
                </a>
                <a class="dash-btn dash-btn-ghost" data-export-base="export_report.php" data-export-type="daily"
                   href="export_report.php?<?= htmlspecialchars($_dailyCsvQuery) ?>">
                    <i class="bi bi-filetype-csv"></i>CSV
                </a>
            </div>
        </div>
    </div>

    <?php if ($mode === 'monthly'): ?>
        <?php if (!$pagedMatrixRows): ?>
            <div class="daily-empty-state">
                <i class="bi bi-calendar-x"></i>
                <strong>ไม่พบข้อมูลเวรรายเดือน</strong>
                <span>ลองเปลี่ยนเดือน แผนก หรือเงื่อนไขการค้นหาอีกครั้ง</span>
            </div>
        <?php else: ?>
            <div class="daily-matrix-shell">
                <table class="table align-middle mb-0 daily-matrix-table">
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>ชื่อ-สกุล</th>
                            <th>ตำแหน่ง</th>
                            <th>แผนก</th>
                            <?php foreach ($matrixDays as $dayMeta): ?>
                                <th class="text-center"><?= (int) $dayMeta['day'] ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pagedMatrixRows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= (int) $row['row_number'] ?></td>
                            <td>
                                <button type="button" class="daily-staff-link" data-profile-modal-trigger data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>">
                                    <?= htmlspecialchars($row['fullname'] ?: '-') ?>
                                </button>
                            </td>
                            <td><?= htmlspecialchars($row['position_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['department_name'] ?: '-') ?></td>
                            <?php foreach ($matrixDays as $dayMeta): ?>
                                <?php $cellCode = $row['day_cells'][(int) $dayMeta['day']] ?? ''; ?>
                                <td class="text-center <?= !empty($dayMeta['is_future']) ? 'daily-matrix-future' : '' ?>"><?= htmlspecialchars($cellCode) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php elseif (!$pagedLogs): ?>
        <div class="daily-empty-state">
            <i class="bi bi-calendar2-x"></i>
            <strong>ไม่พบรายการเวรประจำวันที่ตรงกับตัวกรอง</strong>
            <span>ข้อมูลจะปรากฏที่นี่เมื่อมีรายการเวรจากเงื่อนไขที่เลือก</span>
        </div>
    <?php else: ?>
        <div class="daily-roster-shell <?= $view === 'cards' ? 'daily-roster-shell--cards' : '' ?>">
            <?php if ($view === 'table'): ?>
                <div class="daily-roster-head" aria-hidden="true">
                    <span>วันที่</span>
                    <span>ชื่อเจ้าหน้าที่</span>
                    <span>ตำแหน่ง</span>
                    <span>แผนก</span>
                    <span>เวลาเวร</span>
                    <span>ชั่วโมงรวม</span>
                    <span>หมายเหตุ</span>
                    <span>สถานะ</span>
                    <span>การจัดการ</span>
                </div>
            <?php endif; ?>

            <div class="<?= $view === 'cards' ? 'daily-card-list' : 'daily-roster-list' ?>">
                <?php foreach ($pagedLogs as $index => $log): ?>
                    <?php
                    $displayName = app_user_display_name($log);
                    $dateTile = daily_schedule_date_tile($log['work_date'] ?? '');
                    $statusMeta = app_daily_schedule_runtime_status($log);
                    $phone = trim((string) ($log['phone_number'] ?? ''));
                    $note = trim((string) ($log['note'] ?? ''));
                    ?>
                    <article class="<?= $view === 'cards' ? 'daily-schedule-card' : 'daily-roster-row' ?>">
                        <div class="daily-date-tile">
                            <strong><?= htmlspecialchars($dateTile['day']) ?></strong>
                            <span><?= htmlspecialchars($dateTile['month_year']) ?></span>
                            <small><?= htmlspecialchars($dateTile['weekday']) ?></small>
                        </div>
                        <div class="daily-row-person">
                            <button type="button" class="daily-staff-link" data-profile-modal-trigger data-user-id="<?= (int) ($log['user_id'] ?? 0) ?>">
                                <?= htmlspecialchars($displayName) ?>
                            </button>
                            <span><?= htmlspecialchars($log['position_name'] ?: '-') ?></span>
                        </div>
                        <div class="daily-row-muted"><?= htmlspecialchars($log['position_name'] ?: '-') ?></div>
                        <div class="daily-row-department"><?= htmlspecialchars($log['department_name'] ?: '-') ?></div>
                        <div class="daily-row-shift"><?= htmlspecialchars(daily_schedule_row_time_label($log)) ?></div>
                        <div class="daily-row-hours"><?= htmlspecialchars(daily_schedule_hours_label($log)) ?></div>
                        <div class="daily-row-note" title="<?= htmlspecialchars($note ?: '-') ?>"><?= htmlspecialchars($note !== '' ? $note : '-') ?></div>
                        <div>
                            <span class="daily-status-chip is-<?= htmlspecialchars($statusMeta['class']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span>
                        </div>
                        <div class="daily-row-actions">
                            <button type="button" class="daily-row-btn" data-time-log-detail-trigger data-time-log-id="<?= (int) ($log['id'] ?? 0) ?>">ดูรายละเอียด</button>
                            <?php if ($phone !== ''): ?>
                                <a class="daily-row-btn is-ghost" href="tel:<?= htmlspecialchars($phone) ?>">ติดต่อ</a>
                            <?php else: ?>
                                <button type="button" class="daily-row-btn is-ghost" disabled>ติดต่อ</button>
                            <?php endif; ?>
                            <button type="button" class="daily-row-menu" aria-label="ตัวเลือกเพิ่มเติม"><i class="bi bi-chevron-down"></i></button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="daily-pagination" aria-label="เปลี่ยนหน้ารายการเวร">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="<?= $page === $i ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => $view, 'p' => $i])) ?>" data-table-page-link><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>

<section class="dash-card daily-bottom-strip" data-bottom-summary aria-label="สรุปข้อมูลวันนี้">
    <div class="daily-bottom-heading">
        <h3>สรุปข้อมูลวันนี้</h3>
    </div>
    <div class="daily-bottom-item">
        <span class="daily-bottom-label">เวรวันนี้ทั้งหมด</span>
        <strong class="daily-bottom-value"><?= number_format((int) $scheduleStats['total']) ?> <span>รายการ</span></strong>
        <span class="daily-bottom-meta">จากรายการที่ตรงตัวกรอง</span>
    </div>
    <div class="daily-bottom-item">
        <span class="daily-bottom-label">กำลังปฏิบัติงาน</span>
        <strong class="daily-bottom-value"><?= number_format((int) $scheduleStats['active']) ?> <span>รายการ</span></strong>
        <span class="daily-bottom-meta">คิดเป็น <?= $scheduleStats['total'] > 0 ? number_format(($scheduleStats['active'] / $scheduleStats['total']) * 100, 1) : '0.0' ?>%</span>
    </div>
    <div class="daily-bottom-item">
        <span class="daily-bottom-label">ครบเวรแล้ว</span>
        <strong class="daily-bottom-value"><?= number_format((int) $scheduleStats['completed']) ?> <span>รายการ</span></strong>
        <span class="daily-bottom-meta">คิดเป็น <?= $scheduleStats['total'] > 0 ? number_format(($scheduleStats['completed'] / $scheduleStats['total']) * 100, 1) : '0.0' ?>%</span>
    </div>
    <div class="daily-bottom-item">
        <span class="daily-bottom-label">เปลี่ยนเวร / รอจัดสรร</span>
        <strong class="daily-bottom-value"><?= number_format((int) $scheduleStats['pending']) ?> <span>รายการ</span></strong>
        <span class="daily-bottom-meta">ต้องติดตามต่อ</span>
    </div>
    <div class="daily-bottom-item">
        <span class="daily-bottom-label">ล่าสุด</span>
        <strong class="daily-bottom-value"><?= htmlspecialchars($scheduleStats['latest_time_label']) ?></strong>
        <span class="daily-bottom-meta">อัปเดตล่าสุด</span>
    </div>
    <div class="daily-bottom-progress">
        <div class="daily-bottom-progress-head">
            <span>ความคืบหน้าประจำวัน</span>
            <strong><?= number_format((float) $scheduleStats['progress'], 1) ?>%</strong>
        </div>
        <div class="dash-progress-track"><span style="width: <?= min(100, max(0, (float) $scheduleStats['progress'])) ?>%"></span></div>
        <div class="daily-bottom-meta">ดำเนินการแล้ว <?= number_format((int) ($scheduleStats['active'] + $scheduleStats['completed'])) ?> / <?= number_format((int) $scheduleStats['total']) ?> รายการ</div>
    </div>
</section>
