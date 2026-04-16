<section class="ops-results-panel">
    <section class="row g-4 mb-4" data-results-summary>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label">รายการทั้งหมด</div><div class="report-stat-value"><?= (int) ($summary['total_logs'] ?? 0) ?></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label">ชั่วโมงรวม</div><div class="report-stat-value"><?= number_format((float) ($summary['total_hours'] ?? 0), 2) ?></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label">ตรวจแล้ว</div><div class="report-stat-value"><?= (int) ($summary['approved_logs'] ?? 0) ?></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="report-stat-card"><div class="report-stat-label">รอตรวจ</div><div class="report-stat-value"><?= (int) ($summary['pending_logs'] ?? 0) ?></div></div></div>
    </section>

    <div class="ops-results-header">
        <div>
            <h2 class="ops-results-title">รายการลงเวลาเวรของฉัน</h2>
            <p class="ops-results-subtitle">ข้อมูลด้านล่างจะแสดงตามช่วงเวลาที่เลือก และใช้ชุดตัวกรองเดียวกันกับการพิมพ์หรือส่งออก</p>
        </div>
        <div class="ops-summary-chip">
            <i class="bi bi-person-badge"></i>
            <span><?= htmlspecialchars($_SESSION['fullname']) ?></span>
        </div>
    </div>

    <div class="table-responsive table-shell">
        <table class="table align-middle ops-table">
            <?php app_render_table_colgroup('my_reports'); ?>
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>วันที่</th>
                    <th>แผนก</th>
                    <th>เวลาเข้า - ออก</th>
                    <th>ชั่วโมงรวม</th>
                    <th>หมายเหตุ</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pagedLogs): ?>
                    <tr><td colspan="7" class="ops-empty">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
                <?php endif; ?>
                <?php foreach ($pagedLogs as $index => $log): ?>
                    <tr>
                        <td class="fw-semibold"><?= app_table_row_number($page, $perPage, $index) ?></td>
                        <td><?= htmlspecialchars(app_format_thai_date((string) $log['work_date'])) ?></td>
                        <td><?= htmlspecialchars($log['department_name'] ?? '-') ?></td>
                        <td><?= !empty($log['time_in']) ? date('H:i', strtotime((string) $log['time_in'])) : '-' ?> - <?= !empty($log['time_out']) ? date('H:i', strtotime((string) $log['time_out'])) : '-' ?></td>
                        <td class="fw-semibold text-primary"><?= number_format((float) $log['work_hours'], 2) ?></td>
                        <td class="note-cell"><?= htmlspecialchars($log['note'] ?: '-') ?></td>
                        <td>
                            <?php if (!empty($log['checked_at'])): ?>
                                <span class="badge text-bg-success">ตรวจแล้ว</span>
                                <div class="small text-muted mt-1"><?= htmlspecialchars($log['checker_name'] ?? '-') ?></div>
                            <?php else: ?>
                                <span class="badge text-bg-warning">รอตรวจ</span>
                            <?php endif; ?>
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
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['p' => $i])) ?>" data-table-page-link><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>
