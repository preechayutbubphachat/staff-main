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
$reviewStatusLabel = $schedule['review_status_label'] ?? 'ทั้งหมด';
?>
<section class="ops-results-panel">
    <section class="row g-4 mb-4" data-results-summary>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label"><?= $mode === 'monthly' ? 'จำนวนเจ้าหน้าที่' : 'จำนวนเจ้าหน้าที่' ?></div><div class="report-stat-value"><?= (int) ($schedule['unique_staff_count'] ?? 0) ?></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label"><?= $mode === 'monthly' ? 'จำนวนรายการเวร' : 'จำนวนรายการ' ?></div><div class="report-stat-value"><?= number_format((int) ($schedule['total_rows'] ?? count($schedule['logs'] ?? []))) ?></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label">จำนวนแผนก</div><div class="report-stat-value"><?= (int) ($schedule['department_count'] ?? 0) ?></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label">ชั่วโมงรวม</div><div class="report-stat-value"><?= number_format((float) ($schedule['total_hours'] ?? 0), 2) ?></div></div></div>
    </section>

    <div class="ops-results-header ops-results-header--stack">
        <div>
            <div class="ops-results-kicker"><?= htmlspecialchars($tableContextLabel) ?></div>
            <h2 class="ops-results-title ops-results-title--large"><?= htmlspecialchars($dateHeading) ?></h2>
            <p class="ops-results-subtitle">
                <?php if ($mode === 'monthly'): ?>
                    แสดงผลในรูปแบบตารางรายบุคคล โดยใช้รหัสเวร ช / บ / ด / BD ตามรายการที่ตรงกับเงื่อนไขอย่างปลอดภัย
                <?php else: ?>
                    จัดกลุ่มตามช่วงเวรเพื่อสแกนรายชื่อได้เร็วขึ้น พร้อมเบอร์โทรศัพท์สำหรับติดต่อและสถานะการตรวจสอบที่ใช้เฉพาะในตัวกรอง
                <?php endif; ?>
            </p>
        </div>
        <?php if ($mode === 'daily'): ?>
            <div class="table-toolbar-side">
                <div class="nav nav-pills view-switch">
                    <a class="nav-link <?= $view === 'table' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'table', 'p' => 1])) ?>" data-table-view-link>ตาราง</a>
                    <a class="nav-link <?= $view === 'cards' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'cards', 'p' => 1])) ?>" data-table-view-link>การ์ด</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="ops-selection-hint">
        <div class="selection-chip">
            <span>ขอบเขตข้อมูล</span>
            <strong><?= htmlspecialchars($scopeLabel) ?></strong>
        </div>
        <div class="selection-chip">
            <span><?= $mode === 'monthly' ? 'ช่วงเดือน' : 'สถานะที่เลือก' ?></span>
            <strong><?= htmlspecialchars($mode === 'monthly' ? $periodLabel : $reviewStatusLabel) ?></strong>
        </div>
    </div>

    <?php if ($mode === 'monthly'): ?>
        <?php if (!$pagedMatrixRows): ?>
            <div class="ops-empty">ไม่พบข้อมูลเวรที่ตรงกับตัวกรองสำหรับสรุปรายเดือนในช่วงเวลานี้</div>
        <?php else: ?>
            <div class="table-shell table-responsive monthly-matrix-shell">
                <table class="table align-middle mb-0 monthly-matrix-table">
                    <thead class="table-light">
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
                                <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>">
                                    <?= htmlspecialchars($row['fullname'] ?: '-') ?>
                                </button>
                            </td>
                            <td><?= htmlspecialchars($row['position_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['department_name'] ?: '-') ?></td>
                            <?php foreach ($matrixDays as $dayMeta): ?>
                                <?php $cellCode = $row['day_cells'][(int) $dayMeta['day']] ?? ''; ?>
                                <td class="text-center <?= !empty($dayMeta['is_future']) ? 'monthly-matrix-future' : '' ?>"><?= htmlspecialchars($cellCode) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="monthly-legend mt-3">
                <span><strong>ช</strong> = เวรเช้า 08.30 - 16.30 น.</span>
                <span><strong>บ</strong> = เวรบ่าย 16.30 - 00.30 น.</span>
                <span><strong>ด</strong> = เวรดึก 00.30 - 08.30 น.</span>
                <span><strong>BD</strong> = เวรบ่ายนอกเวลาราชการ</span>
            </div>
        <?php endif; ?>
    <?php elseif (!$pagedLogs): ?>
        <div class="ops-empty">ไม่พบรายการเวรประจำวันที่ตรงกับตัวกรองในขณะนี้</div>
    <?php elseif ($view === 'table'): ?>
        <?php $rowOffset = 0; ?>
        <div class="daily-roster-groups">
            <?php foreach ($pagedGroups as $group): ?>
                <section class="shift-group-card <?= htmlspecialchars($group['class']) ?>">
                    <div class="shift-group-header">
                        <div class="shift-group-title-wrap">
                            <strong><?= htmlspecialchars($group['heading_text'] ?? ($group['label'] . ' / ' . count($group['rows']) . ' รายการ')) ?></strong>
                        </div>
                    </div>
                    <div class="table-shell table-responsive">
                        <table class="table align-middle mb-0 daily-roster-table">
                            <?php app_render_table_colgroup('daily_roster'); ?>
                            <thead class="table-light">
                                <tr>
                                    <th>ลำดับ</th>
                                    <th>ชื่อเจ้าหน้าที่</th>
                                    <th>ตำแหน่ง</th>
                                    <th>แผนก</th>
                                    <th>เบอร์โทรศัพท์</th>
                                    <th>หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($group['rows'] as $index => $log): ?>
                                <?php
                                $displayName = app_user_display_name($log);
                                $rowNumber = app_table_row_number($page, $perPage, $rowOffset + $index);
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= $rowNumber ?></td>
                                    <td>
                                        <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) ($log['user_id'] ?? 0) ?>">
                                            <?= htmlspecialchars($displayName) ?>
                                        </button>
                                    </td>
                                    <td><span class="truncate" title="<?= htmlspecialchars($log['position_name'] ?: '-') ?>"><?= htmlspecialchars($log['position_name'] ?: '-') ?></span></td>
                                    <td><span class="truncate" title="<?= htmlspecialchars($log['department_name'] ?: '-') ?>"><?= htmlspecialchars($log['department_name'] ?: '-') ?></span></td>
                                    <td class="daily-roster-phone"><?= htmlspecialchars($log['phone_number'] ?: '-') ?></td>
                                    <td class="daily-roster-note"><?= htmlspecialchars($log['note'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php $rowOffset += count($group['rows']); ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?php $rowOffset = 0; ?>
        <div class="daily-roster-groups daily-roster-groups--cards">
            <?php foreach ($pagedGroups as $group): ?>
                <section class="shift-group-card <?= htmlspecialchars($group['class']) ?>">
                    <div class="shift-group-header">
                        <div class="shift-group-title-wrap">
                            <strong><?= htmlspecialchars($group['heading_text'] ?? ($group['label'] . ' / ' . count($group['rows']) . ' รายการ')) ?></strong>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($group['rows'] as $index => $log): ?>
                            <?php $displayName = app_user_display_name($log); ?>
                            <div class="col-lg-6">
                                <article class="schedule-card daily-roster-card">
                                    <div class="daily-roster-card-top">
                                        <div>
                                            <div class="small text-muted mb-2">ลำดับ <?= app_table_row_number($page, $perPage, $rowOffset + $index) ?></div>
                                            <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) ($log['user_id'] ?? 0) ?>"><?= htmlspecialchars($displayName) ?></button>
                                            <div class="text-muted"><?= htmlspecialchars($log['position_name'] ?: '-') ?> • <?= htmlspecialchars($log['department_name'] ?: '-') ?></div>
                                        </div>
                                        <span class="meta-pill"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($log['phone_number'] ?: '-') ?></span>
                                    </div>
                                    <div class="daily-roster-note mt-3"><?= htmlspecialchars($log['note'] ?: 'ไม่มีหมายเหตุเพิ่มเติม') ?></div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php $rowOffset += count($group['rows']); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => $view, 'p' => $i])) ?>" data-table-page-link><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>
