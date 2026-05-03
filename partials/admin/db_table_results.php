<?php $visibleColumns = app_db_admin_visible_browse_columns($config); ?>
<section class="panel ops-results-panel">
    <div class="ops-results-header db-table-selected-results-header">
        <div>
            <h2 class="ops-results-title">รายการข้อมูลในตาราง</h2>
            <p class="ops-results-subtitle">ข้อมูลในตารางจะเปลี่ยนตามตัวกรองปัจจุบัน และการจัดการแต่ละแถวจะถูกจำกัดตามกติกาความปลอดภัยของระบบ</p>
        </div>
        <div class="ops-summary-chip db-table-selected-summary-chip">
            <i class="bi bi-table"></i>
            <span><?= number_format($totalRows) ?> รายการ</span>
        </div>
    </div>

    <div class="table-shell table-responsive">
        <table class="table align-middle ops-table mb-0">
            <?php app_render_table_colgroup('db_table_generic', count($visibleColumns)); ?>
            <thead class="table-light">
                <tr>
                    <th>ลำดับ</th>
                    <?php foreach ($visibleColumns as $column): ?>
                        <th><?= htmlspecialchars($column) ?></th>
                    <?php endforeach; ?>
                    <th class="actions-cell">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="<?= count($visibleColumns) + 2 ?>" class="ops-empty">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $rowId = (int) ($row[$config['primary_key']] ?? 0);
                    $rowNumber = app_table_row_number($page, $perPage, $index);
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= $rowNumber ?></td>
                        <?php foreach ($visibleColumns as $column): ?>
                            <td>
                                <?php if ($column === 'fullname' && (($table === 'users' && !empty($row['id'])) || ($table === 'time_logs' && !empty($row['user_id'])))): ?>
                                    <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) ($table === 'users' ? ($row['id'] ?? 0) : ($row['user_id'] ?? 0)) ?>">
                                        <?= htmlspecialchars(app_db_admin_format_value($column, $row[$column] ?? null)) ?>
                                    </button>
                                <?php else: ?>
                                    <span class="truncate d-inline-block" title="<?= htmlspecialchars(app_db_admin_format_value($column, $row[$column] ?? null)) ?>">
                                        <?= htmlspecialchars(app_db_admin_format_value($column, $row[$column] ?? null)) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="actions-cell">
                            <div class="ops-actions">
                                <?php if (!empty($config['edit_allowed'])): ?>
                                    <?php
                                    $editHref = $table === 'users'
                                        ? 'edit_user.php?id=' . $rowId
                                        : 'db_row_edit.php?table=' . urlencode($table) . '&id=' . $rowId;

                                    // Modal meta สำหรับแต่ละ table
                                    static $tableModalLabels = [
                                        'users'       => ['title' => 'แก้ไขข้อมูลผู้ใช้งาน',    'desc' => 'แก้ไขข้อมูลผู้ใช้ สิทธิ์ และแผนก ระบบจะบันทึก audit log ทุกครั้ง'],
                                        'departments' => ['title' => 'แก้ไขข้อมูลแผนก',          'desc' => 'แก้ไขชื่อและรหัสแผนก ระบบจะบันทึก audit log ทุกครั้ง'],
                                        'time_logs'   => ['title' => 'แก้ไขข้อมูลลงเวลาเวร',     'desc' => 'แก้ไขวันที่ เวลาเข้า/ออก และหมายเหตุ ระบบจะบันทึก audit log ทุกครั้ง'],
                                    ];
                                    $modalMeta = $tableModalLabels[$table] ?? [
                                        'title' => 'แก้ไขข้อมูล ' . ($config['label'] ?? $table),
                                        'desc'  => 'ระบบจะบันทึก audit log ทุกครั้งที่มีการเปลี่ยนแปลง',
                                    ];
                                    ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-dark ops-action-btn btn-edit-row"
                                        data-open-user-edit
                                        data-user-id="<?= (int) $rowId ?>"
                                        data-edit-url="<?= htmlspecialchars($editHref) ?>"
                                        data-modal-title="<?= htmlspecialchars($modalMeta['title']) ?>"
                                        data-modal-desc="<?= htmlspecialchars($modalMeta['desc']) ?>"
                                        data-modal-eyebrow="Admin Only · <?= htmlspecialchars($config['label'] ?? $table) ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                        <span>แก้ไข</span>
                                    </button>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary ops-readonly-badge">
                                        <i class="bi bi-lock"></i> อ่านอย่างเดียว
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($config['delete_allowed'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger ops-action-btn" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $rowId ?>">
                                        <i class="bi bi-trash"></i>
                                        <span>ลบ</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($config['delete_allowed'])): ?>
                                <div class="modal fade" id="deleteModal<?= $rowId ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4">
                                            <div class="modal-header">
                                                <h5 class="modal-title">ยืนยันการลบข้อมูล</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">ตาราง: <strong><?= htmlspecialchars($config['label']) ?></strong></div>
                                                <div class="mb-3">ลำดับในหน้าปัจจุบัน: <strong><?= $rowNumber ?></strong></div>
                                                <div class="mb-3 small text-muted">โปรดตรวจสอบความถูกต้องก่อนลบ การเปลี่ยนแปลงจะถูกบันทึกใน audit log ทุกครั้ง</div>
                                                <form method="post">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_row">
                                                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                                    <input type="hidden" name="row_id" value="<?= $rowId ?>">
                                                    <input type="hidden" name="return_q" value="<?= htmlspecialchars($filters['q']) ?>">
                                                    <input type="hidden" name="return_page" value="<?= (int) $page ?>">
                                                    <input type="hidden" name="return_per_page" value="<?= (int) $perPage ?>">
   