<div class="timeline-stack">
    <?php if (!$historyLogs): ?>
        <div class="ops-empty">ไม่พบข้อมูลการลงเวลาในช่วงวันที่ที่เลือก</div>
    <?php endif; ?>
    <?php foreach ($historyLogs as $index => $row): ?>
        <?php
        $isApproved = !empty($row['checked_at']);
        $isLocked = $isApproved;
        $canEditHistoryRow = !$isLocked || $canPrivilegedLockedEdit;
        $rowFlags = $historyFlags[(int) $row['id']] ?? ['incomplete' => false, 'overlap' => false];
        $cardState = $rowFlags['overlap'] ? 'warning' : ($rowFlags['incomplete'] ? 'caution' : 'normal');
        $timeInLabel = !empty($row['time_in']) ? date('H:i', strtotime((string) $row['time_in'])) : '--:--';
        $timeOutLabel = !empty($row['time_out']) ? date('H:i', strtotime((string) $row['time_out'])) : '--:--';
        $rowNumber = app_table_row_number($page, $limit, $index);
        ?>
        <article class="timeline-card <?= $cardState ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="small text-muted mb-1">ลำดับที่ <?= $rowNumber ?></div>
                    <div class="fw-bold fs-5"><?= htmlspecialchars(app_format_thai_date((string) $row['work_date'])) ?></div>
                    <div class="text-muted small mt-1"><?= htmlspecialchars($row['department_name'] ?? '-') ?></div>
                </div>
                <div class="text-md-end">
                    <div class="fw-semibold text-primary"><?= number_format((float) $row['work_hours'], 2) ?> ชม.</div>
                    <?php if ($isApproved): ?>
                        <span class="badge text-bg-success mt-2">อนุมัติแล้ว</span>
                        <div class="small text-muted mt-1">ล็อกแล้ว</div>
                    <?php else: ?>
                        <span class="badge text-bg-warning mt-2">รอตรวจ</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-3">
                <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-box-arrow-in-right me-1"></i><?= $timeInLabel ?></span>
                <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-box-arrow-left me-1"></i><?= $timeOutLabel ?></span>
                <?php if (!empty($row['checker'])): ?>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-person-check me-1"></i><?= htmlspecialchars($row['checker']) ?></span>
                <?php endif; ?>
                <?php if ($rowFlags['overlap']): ?><span class="status-chip warning"><i class="bi bi-exclamation-octagon"></i>เวลาเวรชนกับรายการอื่น</span><?php endif; ?>
                <?php if ($rowFlags['incomplete']): ?><span class="status-chip caution"><i class="bi bi-exclamation-triangle"></i>ข้อมูลเวลาไม่ครบ</span><?php endif; ?>
            </div>

            <div class="mt-3 text-muted"><?= htmlspecialchars($row['note'] ?: 'ไม่มีหมายเหตุเพิ่มเติม') ?></div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php if ($canEditHistoryRow): ?>
                    <a href="?<?= htmlspecialchars(http_build_query(array_filter(['p' => $page, 'date' => $searchDate, 'per_page' => $limit, 'edit_id' => $row['id']]))) ?>" class="btn btn-outline-dark btn-pill btn-sm" data-time-edit-link data-id="<?= (int) $row['id'] ?>">
                        <i class="bi bi-pencil-square"></i><?= $isLocked ? 'แก้ไขแบบสิทธิ์พิเศษ' : 'แก้ไขรายการ' ?>
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary btn-pill btn-sm" disabled><i class="bi bi-lock"></i>ล็อกแล้ว</button>
                <?php endif; ?>
            </div>

            <?php if ($isLocked && !$canPrivilegedLockedEdit): ?>
                <div class="small text-danger mt-2">รายการนี้ได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขได้ กรุณาติดต่อผู้ดูแลระบบ</div>
            <?php elseif ($isLocked && $canPrivilegedLockedEdit): ?>
                <div class="small text-warning mt-2">รายการนี้ได้รับการอนุมัติแล้ว การแก้ไขจะรีเซ็ตสถานะอนุมัติเดิมเพื่อให้ตรวจสอบใหม่</div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                    <a class="page-link" href="?p=<?= $i ?>&date=<?= urlencode($searchDate) ?>&per_page=<?= (int) $limit ?>" data-time-history-page="<?= (int) $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
