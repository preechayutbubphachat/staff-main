<?php
$cardsQuery = http_build_query(array_filter([
    'name' => $filters['name'],
    'position_name' => $filters['position_name'],
    'department' => $filters['department'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'status' => $filters['status'],
    'per_page' => $perPage,
    'view' => 'cards',
    'p' => 1,
], static fn($value) => $value !== '' && $value !== null));
$tableQuery = http_build_query(array_filter([
    'name' => $filters['name'],
    'position_name' => $filters['position_name'],
    'department' => $filters['department'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'status' => $filters['status'],
    'per_page' => $perPage,
    'view' => 'table',
    'p' => 1,
], static fn($value) => $value !== '' && $value !== null));
?>
<section class="ops-results-panel" id="approvalRowsSection" data-current-view="<?= htmlspecialchars($view) ?>" data-current-page="<?= (int) $page ?>">
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
            <h2 class="ops-results-title">รายการที่รอการตรวจสอบ</h2>
            <p class="ops-results-subtitle">เลือกทั้งแถวหรือทั้งการ์ดได้ทันที จากนั้นตรวจสรุปรายการในหน้าต่างยืนยันก่อนอนุมัติจริง</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <label class="d-inline-flex align-items-center gap-2 fw-semibold">
                <input type="checkbox" class="form-check-input" id="<?= $view === 'cards' ? 'selectAllCards' : 'selectAllTable' ?>">
                <span>เลือกทั้งหมดในหน้านี้</span>
            </label>
            <div class="ops-view-switch">
                <a class="btn <?= $view === 'cards' ? 'btn-dark' : 'btn-outline-dark' ?>" href="?<?= htmlspecialchars($cardsQuery) ?>" data-approval-view-link="cards"><i class="bi bi-grid-3x2-gap me-1"></i>การ์ด</a>
                <a class="btn <?= $view === 'table' ? 'btn-dark' : 'btn-outline-dark' ?>" href="?<?= htmlspecialchars($tableQuery) ?>" data-approval-view-link="table"><i class="bi bi-table me-1"></i>ตาราง</a>
            </div>
        </div>
    </div>

    <div class="ops-selection-hint mb-3">
        <div class="ops-summary-chip">
            <i class="bi bi-info-circle"></i>
            <span>คลิกที่แถวหรือการ์ดเพื่อเลือกได้ทันที</span>
        </div>
        <?php if ($checkerSignature === ''): ?>
            <span class="badge text-bg-danger rounded-pill px-3 py-2">ต้องตั้งค่าลายเซ็นผู้ตรวจสอบก่อนอนุมัติรายการ</span>
        <?php endif; ?>
    </div>

    <?php if (!$rows): ?>
        <div class="ops-empty">ไม่พบรายการตามเงื่อนไขที่เลือก</div>
    <?php elseif ($view === 'cards'): ?>
        <div class="ops-card-grid">
            <?php foreach ($rows as $index => $row): ?>
                <?php
                $isApprovable = empty($row['checked_at']) && !empty($row['time_out']);
                $status = app_time_log_status_meta($row);
                $rowId = (int) $row['id'];
                $rowNumber = app_table_row_number($page, $perPage, $index);
                ?>
                <article class="ops-card <?= $isApprovable ? '' : 'is-disabled' ?>" data-select-row>
                    <div class="ops-card-top">
                        <label class="d-inline-flex align-items-center gap-2 fw-semibold">
                            <input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= $rowId ?>" data-fullname="<?= htmlspecialchars($row['fullname'] ?? '-', ENT_QUOTES) ?>" data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>" data-department="<?= htmlspecialchars($row['department_name'] ?? '-', ENT_QUOTES) ?>" <?= $isApprovable ? '' : 'disabled' ?>>
                            <span>ลำดับที่ <?= $rowNumber ?></span>
                        </label>
                        <span class="status-badge status-<?= $status['class'] ?>"><?= htmlspecialchars($status['label']) ?></span>
                    </div>

                    <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>">
                        <?= htmlspecialchars($row['fullname'] ?? '-') ?>
                    </button>
                    <div class="text-muted"><?= htmlspecialchars($row['position_name'] ?? '-') ?> · <?= htmlspecialchars($row['department_name'] ?? '-') ?></div>

                    <div class="ops-card-meta">
                        <span class="ops-meta-pill"><i class="bi bi-calendar3"></i><?= htmlspecialchars(app_format_thai_date((string) $row['work_date'])) ?></span>
                        <span class="ops-meta-pill"><i class="bi bi-clock"></i><?= !empty($row['time_in']) ? date('H:i', strtotime((string) $row['time_in'])) : '-' ?> - <?= !empty($row['time_out']) ? date('H:i', strtotime((string) $row['time_out'])) : '-' ?></span>
                        <span class="ops-meta-pill"><i class="bi bi-hourglass-split"></i><?= number_format((float) $row['work_hours'], 2) ?> ชม.</span>
                    </div>

                    <div class="small text-muted mb-1">หมายเหตุ</div>
                    <div><?= htmlspecialchars($row['note'] ?: '-') ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="table-shell">
            <table class="table align-middle ops-table mb-0">
                <?php app_render_table_colgroup('approval_queue'); ?>
                <thead class="table-light">
                    <tr>
                        <th class="text-center">เลือก</th>
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
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $isApprovable = empty($row['checked_at']) && !empty($row['time_out']);
                    $status = app_time_log_status_meta($row);
                    $rowId = (int) $row['id'];
                    $rowNumber = app_table_row_number($page, $perPage, $index);
                    ?>
                    <tr class="ops-table-row <?= $isApprovable ? '' : 'is-disabled' ?>" data-select-row>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input row-checkbox" name="selected_ids[]" value="<?= $rowId ?>" data-fullname="<?= htmlspecialchars($row['fullname'] ?? '-', ENT_QUOTES) ?>" data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>" data-department="<?= htmlspecialchars($row['department_name'] ?? '-', ENT_QUOTES) ?>" <?= $isApprovable ? '' : 'disabled' ?>>
                        </td>
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
                        <td><span class="status-badge status-<?= $status['class'] ?>"><?= htmlspecialchars($status['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

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
                        'view' => $view,
                        'p' => $i,
                    ], static fn($value) => $value !== '' && $value !== null)); ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars($pageQuery) ?>" data-approval-page-link="<?= (int) $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>
