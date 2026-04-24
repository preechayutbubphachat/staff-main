<?php
$historyDate = (string) ($historyDate ?? ($searchDate ?? ''));
$dateFrom = (string) ($dateFrom ?? '');
$dateTo = (string) ($dateTo ?? '');
$historyStatus = (string) ($historyStatus ?? 'all');
$historyQuery = (string) ($historyQuery ?? '');
$limit = (int) ($limit ?? 20);
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$baseQuery = [
    'date' => $historyDate,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'status' => $historyStatus,
    'query' => $historyQuery,
    'per_page' => $limit,
];
?>

<div class="timeline-stack">
    <?php if (!$historyLogs): ?>
        <div class="ops-empty">
            ไม่พบข้อมูลการลงเวลาในช่วงที่เลือก ลองขยายช่วงวันที่หรือเปลี่ยนสถานะที่ต้องการค้นหา
        </div>
    <?php endif; ?>

    <?php foreach ($historyLogs as $index => $row): ?>
        <?php
        $statusMeta = app_time_log_status_meta($row);
        $isLocked = (bool) ($statusMeta['is_locked'] ?? false);
        $canEditHistoryRow = !$isLocked || $canPrivilegedLockedEdit;
        $rowFlags = $historyFlags[(int) $row['id']] ?? ['incomplete' => false, 'overlap' => false];
        $timeInLabel = !empty($row['time_in']) ? date('H:i', strtotime((string) $row['time_in'])) : '--:--';
        $timeOutLabel = !empty($row['time_out']) ? date('H:i', strtotime((string) $row['time_out'])) : '--:--';
        $detailQuery = app_build_table_query($baseQuery, [
            'p' => $page,
            'edit_id' => (int) $row['id'],
        ]);
        $dateTimestamp = !empty($row['work_date']) ? strtotime((string) $row['work_date']) : false;
        $dayNumber = $dateTimestamp ? date('j', $dateTimestamp) : '-';
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
        $monthYearCompact = '-';
        $weekdayCompact = '';
        if ($dateTimestamp) {
            $monthIndex = (int) date('n', $dateTimestamp);
            $monthYearCompact = sprintf(
                '%s %d',
                $thaiMonthShort[$monthIndex] ?? date('M', $dateTimestamp),
                (int) date('Y', $dateTimestamp) + 543
            );
            $weekdayCompact = $thaiWeekdayShort[(int) date('w', $dateTimestamp)] ?? '';
        }
        $noteText = trim((string) ($row['note'] ?? ''));
        ?>
        <article class="timeline-row<?= $rowFlags['overlap'] ? ' is-warning' : '' ?><?= $rowFlags['incomplete'] ? ' is-caution' : '' ?>">
            <div class="timeline-date-col">
                <strong class="timeline-date-number"><?= htmlspecialchars($dayNumber) ?></strong>
                <span class="timeline-date-meta"><?= htmlspecialchars($monthYearCompact) ?></span>
                <?php if ($weekdayCompact !== ''): ?>
                    <span class="timeline-date-weekday"><?= htmlspecialchars($weekdayCompact) ?></span>
                <?php endif; ?>
            </div>

            <div class="timeline-main-col">
                <div class="timeline-main-head">
                    <strong class="timeline-main-department"><?= htmlspecialchars((string) ($row['department_name'] ?? '-')) ?></strong>
                    <span class="timeline-main-time"><?= htmlspecialchars($timeInLabel) ?> - <?= htmlspecialchars($timeOutLabel) ?></span>
                </div>
                <div class="timeline-main-note"><?= htmlspecialchars($noteText !== '' ? $noteText : 'ไม่มีหมายเหตุเพิ่มเติม') ?></div>
            </div>

            <div class="timeline-status-col">
                <strong class="timeline-hours"><?= number_format((float) $row['work_hours'], 2) ?> ชม.</strong>
                <span class="status-chip <?= htmlspecialchars((string) $statusMeta['class']) ?>"><?= htmlspecialchars((string) $statusMeta['label']) ?></span>
                <?php if ($rowFlags['overlap']): ?>
                    <span class="status-chip warning">เวลาซ้อนทับ</span>
                <?php elseif ($rowFlags['incomplete']): ?>
                    <span class="status-chip caution">ข้อมูลไม่ครบ</span>
                <?php endif; ?>
            </div>

            <div class="timeline-action-col">
                <?php if ($canEditHistoryRow): ?>
                    <a
                        href="?<?= htmlspecialchars($detailQuery) ?>"
                        class="dash-btn dash-btn-ghost timeline-action-btn"
                        data-time-edit-link
                        data-id="<?= (int) $row['id'] ?>"
                    >
                        ดูรายละเอียด
                    </a>
                <?php else: ?>
                    <button type="button" class="dash-btn dash-btn-ghost timeline-action-btn is-disabled" disabled>ล็อกแล้ว</button>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center mb-0 time-pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                    <a
                        class="page-link"
                        href="?<?= htmlspecialchars(app_build_table_query($baseQuery, ['p' => $i])) ?>"
                        data-time-history-page="<?= (int) $i ?>"
                    >
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
