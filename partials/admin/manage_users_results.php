<section class="panel ops-results-panel">
    <div class="ops-results-header">
        <div>
            <h2 class="ops-results-title">รายชื่อผู้ใช้งาน</h2>
            <p class="ops-results-subtitle">ข้อมูลในตารางตรงกับตัวกรองปัจจุบัน และสามารถคลิกชื่อเพื่อดูข้อมูลเจ้าหน้าที่ได้ทันที</p>
        </div>
        <div class="ops-summary-chip">
            <i class="bi bi-people"></i>
            <span><?= number_format($totalRows) ?> รายการ</span>
        </div>
    </div>

    <div class="table-shell table-responsive">
        <table class="table align-middle ops-table mb-0">
            <?php app_render_table_colgroup('manage_users'); ?>
            <thead class="table-light">
                <tr>
                    <th>ลำดับ</th>
                    <th>เจ้าหน้าที่</th>
                    <th>ตำแหน่ง</th>
                    <th>แผนก</th>
                    <th>บทบาท</th>
                    <th>สถานะสิทธิ์</th>
                    <th class="actions-cell">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="7" class="ops-empty">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $index => $row): ?>
                <?php $displayName = app_user_display_name($row); ?>
                <tr>
                    <td class="fw-semibold"><?= app_table_row_number($page, $perPage, $index) ?></td>
                    <td class="name-cell">
                        <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) $row['id'] ?>">
                            <span class="truncate d-block fw-semibold"><?= htmlspecialchars($displayName) ?></span>
                        </button>
                        <div class="small text-muted"><?= htmlspecialchars($row['username']) ?></div>
                    </td>
                    <td class="position-cell"><span class="truncate" title="<?= htmlspecialchars($row['position_name'] ?: '-') ?>"><?= htmlspecialchars($row['position_name'] ?: '-') ?></span></td>
                    <td class="department-cell"><span class="truncate" title="<?= htmlspecialchars($row['department_name'] ?: '-') ?>"><?= htmlspecialchars($row['department_name'] ?: '-') ?></span></td>
                    <td><span class="status-badge status-neutral"><?= htmlspecialchars(app_role_label((string) ($row['role'] ?? 'staff'))) ?></span></td>
                    <td>
                        <div class="small">อนุมัติ: <?= !empty($row['can_approve_logs']) ? 'ได้' : 'ไม่ได้' ?></div>
                        <div class="small">จัดการเวร: <?= !empty($row['can_manage_time_logs']) ? 'ได้' : 'ไม่ได้' ?></div>
                    </td>
                    <td class="actions-cell">
                        <a href="edit_user.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-dark ops-action-btn">
                            <i class="bi bi-pencil-square"></i>
                            <span>แก้ไขข้อมูล</span>
                        </a>
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
                        <a class="page-link" href="?<?= htmlspecialchars(app_manage_users_query($filters, ['p' => $i, 'per_page' => $perPage])) ?>" data-table-page-link><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>
