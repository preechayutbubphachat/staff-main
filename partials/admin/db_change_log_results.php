<section class="panel ops-results-panel">
    <div class="ops-results-header">
        <div>
            <h2 class="ops-results-title">รายการล่าสุด</h2>
            <p class="ops-results-subtitle">บันทึกทุกการสร้าง แก้ไข และลบจากโมดูลหลังบ้าน โดยตารางด้านล่างจะแสดงผลตามตัวกรองปัจจุบัน</p>
        </div>
        <div class="ops-summary-chip">
            <i class="bi bi-journal-text"></i>
            <span><?= number_format($totalRows) ?> รายการ</span>
        </div>
    </div>

    <div class="table-shell table-responsive">
        <table class="table align-middle ops-table mb-0">
            <?php app_render_table_colgroup('db_change_logs'); ?>
            <thead class="table-light">
                <tr>
                    <th>ลำดับ</th>
                    <th>เวลา</th>
                    <th>ตาราง</th>
                    <th>การกระทำ</th>
                    <th>ผู้ดำเนินการ</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="ops-empty">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td class="fw-semibold"><?= app_table_row_number($page, $perPage, $index) ?></td>
                        <td><?= htmlspecialchars(app_format_thai_datetime((string) $row['created_at'])) ?></td>
                        <td><?= htmlspecialchars($tableConfigs[$row['table_name']]['label'] ?? $row['table_name']) ?></td>
                        <td><?= htmlspecialchars((string) $row['action_type']) ?></td>
                        <td><?= htmlspecialchars((string) $row['actor_name_snapshot']) ?></td>
                        <td><span class="truncate d-inline-block" title="<?= htmlspecialchars((string) ($row['note'] ?: '-')) ?>"><?= htmlspecialchars((string) ($row['note'] ?: '-')) ?></span></td>
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
                        <a class="page-link" href="?<?= htmlspecialchars(app_build_table_query(['q' => $search, 'per_page' => $perPage], ['page' => $i])) ?>" data-table-page-link><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>
