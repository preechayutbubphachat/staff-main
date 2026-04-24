<?php
$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$perPage = (int) ($perPage ?? 20);
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$totalRows = (int) ($totalRows ?? count($rows));
$checkerSignature = (string) ($checkerSignature ?? '');
$cardsQuery = http_build_query(array_filter([
    'name' => $filters['name'] ?? '',
    'position_name' => $filters['position_name'] ?? '',
    'department' => $filters['department'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'status' => $filters['status'] ?? 'pending',
    'per_page' => $perPage,
    'view' => 'cards',
    'p' => 1,
], static fn($value) => $value !== '' && $value !== null));
$tableQuery = http_build_query(array_filter([
    'name' => $filters['name'] ?? '',
    'position_name' => $filters['position_name'] ?? '',
    'department' => $filters['department'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'status' => $filters['status'] ?? 'pending',
    'per_page' => $perPage,
    'view' => 'table',
    'p' => 1,
], static fn($value) => $value !== '' && $value !== null));
$thaiMonthShort = function_exists('app_thai_month_short_names') ? app_thai_month_short_names() : [];
$thaiWeekdayShort = [
    0 => 'อา.',
    1 => 'จ.',
    2 => 'อ.',
    3 => 'พ.',
    4 => 'พฤ.',
    5 => 'ศ.',
    6 => 'ส.',
];
?>
<section class="dash-card approval-review-panel" id="approval-review-panel" data-current-view="<?= htmlspecialchars($view ?? 'table') ?>" data-current-page="<?= (int) $page ?>">
    <div class="approval-review-header">
        <div>
            <p class="approval-section-eyebrow">Review queue</p>
            <h2 class="approval-card-title">รายการรอตรวจสอบ</h2>
            <p class="approval-card-copy">เลือกหลายรายการหรืออนุมัติทีละรายการได้ทันที พร้อมคงตัวกรองและการแบ่งหน้าตามบริบทที่ใช้งานอยู่</p>
        </div>
        <div class="approval-review-toolbar">
            <div class="approval-select-all">
                <label class="approval-select-all-check">
                    <input type="checkbox" class="form-check-input" id="selectAllTable">
                    <span>เลือกทั้งหมด</span>
                </label>
                <button type="button" class="dash-btn dash-btn-ghost approval-select-all-btn" data-select-all-visible>
                    <i class="bi bi-check2-square"></i>เลือกทั้งหมดในหน้าที่เห็น
                </button>
            </div>
            <div class="approval-view-switch">
                <a class="dash-btn <?= ($view ?? 'table') === 'cards' ? 'dash-btn-primary' : 'dash-btn-ghost' ?>" href="?<?= htmlspecialchars($cardsQuery) ?>" data-approval-view-link="cards">
                    <i class="bi bi-grid-3x2-gap"></i>การ์ด
                </a>
                <a class="dash-btn <?= ($view ?? 'table') === 'table' ? 'dash-btn-primary' : 'dash-btn-ghost' ?>" href="?<?= htmlspecialchars($tableQuery) ?>" data-approval-view-link="table">
                    <i class="bi bi-table"></i>ตาราง
                </a>
            </div>
        </div>
    </div>

    <div class="approval-list-caption">
        <span>ทั้งหมด <?= number_format($totalRows) ?> รายการ</span>
        <?php if ($checkerSignature === ''): ?>
            <span class="status-chip warning">ยังไม่สามารถอนุมัติได้จนกว่าจะตั้งค่าลายเซ็น</span>
        <?php endif; ?>
    </div>

    <?php if (!$rows): ?>
        <div class="ops-empty">ไม่พบรายการที่ตรงกับตัวกรองในขณะนี้</div>
    <?php else: ?>
        <div class="approval-list-shell">
            <div class="approval-list-head">
                <span></span>
                <span>วันที่</span>
                <span>ชื่อ - ตำแหน่ง</span>
                <span>แผนก</span>
                <span>เวลาเวร</span>
                <span>ชั่วโมงรวม</span>
                <span>หมายเหตุ</span>
                <span>สถานะ</span>
                <span class="text-right">จัดการ</span>
            </div>

            <div class="approval-review-list">
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $rowId = (int) ($row['id'] ?? 0);
                    $rowNumber = app_table_row_number($page, $perPage, $index);
                    $isApprovable = empty($row['checked_at']) && !empty($row['time_out']);
                    $isReturned = !empty($row['checked_by']) && empty($row['checked_at']);
                    $statusClass = !empty($row['checked_at']) ? 'success' : ($isReturned ? 'danger' : 'warning');
                    $statusLabel = !empty($row['checked_at']) ? 'อนุมัติแล้ว' : ($isReturned ? 'ตีกลับ' : 'รอตรวจ');
                    $workDate = trim((string) ($row['work_date'] ?? ''));
                    $dateTimestamp = $workDate !== '' ? strtotime($workDate) : false;
                    $dayNumber = $dateTimestamp ? date('j', $dateTimestamp) : '-';
                    $monthYearCompact = $dateTimestamp
                        ? sprintf('%s %d', $thaiMonthShort[(int) date('n', $dateTimestamp)] ?? date('M', $dateTimestamp), (int) date('Y', $dateTimestamp) + 543)
                        : '-';
                    $weekdayCompact = $dateTimestamp ? ($thaiWeekdayShort[(int) date('w', $dateTimestamp)] ?? '') : '';
                    $timeInLabel = !empty($row['time_in']) ? date('H:i', strtotime((string) $row['time_in'])) : '--:--';
                    $timeOutLabel = !empty($row['time_out']) ? date('H:i', strtotime((string) $row['time_out'])) : '--:--';
                    $detailText = trim((string) ($row['approval_note'] ?? '')) ?: trim((string) ($row['note'] ?? ''));
                    if ($detailText === '') {
                        $detailText = '-';
                    }
                    ?>
                    <article class="approval-review-row<?= $isReturned ? ' is-returned' : '' ?><?= !empty($row['checked_at']) ? ' is-approved' : '' ?>" data-select-row>
                        <div class="approval-row-check">
                            <input
                                type="checkbox"
                                class="form-check-input row-checkbox"
                                name="selected_ids[]"
                                value="<?= $rowId ?>"
                                data-fullname="<?= htmlspecialchars((string) ($row['fullname'] ?? '-'), ENT_QUOTES) ?>"
                                data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>"
                                data-department="<?= htmlspecialchars((string) ($row['department_name'] ?? '-'), ENT_QUOTES) ?>"
                                <?= $isApprovable ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="approval-date-tile">
                            <strong><?= htmlspecialchars($dayNumber) ?></strong>
                            <span><?= htmlspecialchars($monthYearCompact) ?></span>
                            <?php if ($weekdayCompact !== ''): ?><small><?= htmlspecialchars($weekdayCompact) ?></small><?php endif; ?>
                        </div>

                        <div class="approval-row-main">
                            <div class="approval-row-name-line">
                                <button type="button" class="approval-staff-link" data-profile-modal-trigger data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>">
                                    <?= htmlspecialchars((string) ($row['fullname'] ?? '-')) ?>
                                </button>
                                <span class="approval-row-index">#<?= $rowNumber ?></span>
                            </div>
                            <div class="approval-row-subline"><?= htmlspecialchars((string) ($row['position_name'] ?? '-') ?: '-') ?></div>
                        </div>

                        <div class="approval-row-department"><?= htmlspecialchars((string) ($row['department_name'] ?? '-') ?: '-') ?></div>

                        <div class="approval-row-shift"><?= htmlspecialchars($timeInLabel) ?> - <?= htmlspecialchars($timeOutLabel) ?></div>

                        <div class="approval-row-hours"><?= number_format((float) ($row['work_hours'] ?? 0), 2) ?> ชม.</div>

                        <div class="approval-row-note"><?= htmlspecialchars($detailText) ?></div>

                        <div class="approval-row-status">
                            <span class="status-chip <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                        </div>

                        <div class="approval-row-actions">
                            <button type="button" class="dash-btn dash-btn-ghost approval-row-btn" data-profile-modal-trigger data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>">
                                ดูรายละเอียด
                            </button>
                            <?php if ($isApprovable): ?>
                                <button type="button" class="dash-btn dash-btn-primary approval-row-btn is-approve" data-approve-single="<?= $rowId ?>">
                                    อนุมัติ
                                </button>
                            <?php elseif (!empty($row['checked_at'])): ?>
                                <button type="button" class="dash-btn dash-btn-ghost approval-row-btn is-disabled" disabled>อนุมัติแล้ว</button>
                            <?php else: ?>
                                <button type="button" class="dash-btn dash-btn-ghost approval-row-btn is-disabled" disabled>ตีกลับ</button>
                            <?php endif; ?>
                            <button type="button" class="dash-icon-button approval-row-menu" disabled aria-label="เมนูเพิ่มเติม">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination mb-0 time-pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php
                    $pageQuery = http_build_query(array_filter([
                        'name' => $filters['name'] ?? '',
                        'position_name' => $filters['position_name'] ?? '',
                        'department' => $filters['department'] ?? '',
                        'date_from' => $filters['date_from'] ?? '',
                        'date_to' => $filters['date_to'] ?? '',
                        'status' => $filters['status'] ?? '',
                        'per_page' => $perPage,
                        'view' => $view ?? 'table',
                        'p' => $i,
                    ], static fn($value) => $value !== '' && $value !== null));
                    ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars($pageQuery) ?>" data-approval-page-link="<?= (int) $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>
