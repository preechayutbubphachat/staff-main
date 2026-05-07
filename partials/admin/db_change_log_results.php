<div class="audit-log-results">
    <div class="audit-log-results-header">
        <div>
            <span>Audit Log</span>
            <h2>รายการล่าสุด</h2>
            <p>บันทึกการสร้าง แก้ไข และลบจากโมดูลต่างๆ พร้อมรายละเอียดการเปลี่ยนแปลง</p>
        </div>
        <div class="audit-log-header-actions">
            <div class="audit-log-report-actions" aria-label="เครื่องมือรายงาน">
                <a href="report_print.php?<?= htmlspecialchars($printQuery ?? app_build_table_query(['type' => 'db_change_logs'])) ?>" target="_blank" rel="noopener" class="audit-log-tool-button"><i class="bi bi-printer"></i>พิมพ์รายงาน</a>
                <a href="report_print.php?<?= htmlspecialchars($pdfQuery ?? app_build_table_query(['type' => 'db_change_logs', 'download' => 'pdf'])) ?>" target="_blank" rel="noopener" class="audit-log-tool-button"><i class="bi bi-filetype-pdf"></i>ส่งออก PDF</a>
                <a href="export_report.php?<?= htmlspecialchars($csvQuery ?? app_build_table_query(['type' => 'db_change_logs'])) ?>" class="audit-log-tool-button"><i class="bi bi-filetype-csv"></i>ส่งออก CSV</a>
            </div>
            <div class="audit-log-view-switch" aria-label="ตัวเลือกมุมมอง">
                <button type="button" class="active" data-audit-view-button="table" aria-pressed="true"><i class="bi bi-table"></i>ตาราง</button>
                <button type="button" data-audit-view-button="timeline" aria-pressed="false"><i class="bi bi-sliders"></i>ไทม์ไลน์</button>
            </div>
        </div>
    </div>

    <div class="audit-log-table-shell" data-audit-view-panel="table">
        <table class="audit-log-table">
            <?php app_render_table_colgroup('db_change_logs'); ?>
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>เวลา</th>
                    <th>ตาราง</th>
                    <th>การกระทำ</th>
                    <th>ผู้ดำเนินการ</th>
                    <th>หมายเหตุ</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="7">
                            <div class="audit-log-empty-state">
                                <i class="bi bi-search"></i>
                                <strong>ไม่พบบันทึกตามเงื่อนไข</strong>
                                <span>ลองล้างตัวกรองหรือค้นหาด้วยคำอื่น</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $tableName = (string) ($row['table_name'] ?? '');
                    $noteText = (string) ($row['note'] ?: '-');
                    ?>
                    <tr>
                        <td class="audit-log-index-cell"><?= app_table_row_number($page, $perPage, $index) ?></td>
                        <td><?= htmlspecialchars(app_format_thai_datetime((string) $row['created_at'])) ?></td>
                        <td>
                            <span class="audit-log-table-name">
                                <?= htmlspecialchars($tableConfigs[$tableName]['label'] ?? $tableName ?: '-') ?>
                                <?php if ($tableName !== ''): ?>
                                    <em><?= htmlspecialchars($tableName) ?></em>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><span class="audit-log-action-pill"><?= htmlspecialchars((string) $row['action_type']) ?></span></td>
                        <td><?= htmlspecialchars((string) ($row['actor_name_snapshot'] ?: '-')) ?></td>
                        <td><span class="audit-log-note" title="<?= htmlspecialchars($noteText) ?>"><?= htmlspecialchars($noteText) ?></span></td>
                        <td><span class="audit-log-status is-saved">บันทึกแล้ว</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="audit-log-timeline-shell" data-audit-view-panel="timeline" hidden>
        <?php if (!$rows): ?>
            <div class="audit-log-empty-state audit-log-timeline-empty">
                <i class="bi bi-search"></i>
                <strong>ไม่พบบันทึกตามเงื่อนไข</strong>
                <span>ลองล้างตัวกรองหรือค้นหาด้วยคำอื่น</span>
            </div>
        <?php else: ?>
            <div class="audit-log-timeline-list">
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $tableName = (string) ($row['table_name'] ?? '');
                    $actionType = strtolower(trim((string) ($row['action_type'] ?? '')));
                    $actionClass = in_array($actionType, ['create', 'insert'], true)
                        ? 'is-create'
                        : (in_array($actionType, ['delete', 'remove'], true) ? 'is-delete' : ($actionType === 'update' ? 'is-update' : 'is-default'));
                    $noteText = trim((string) ($row['note'] ?? '')) !== '' ? (string) $row['note'] : '-';
                    ?>
                    <article class="audit-log-timeline-item <?= htmlspecialchars($actionClass) ?>">
                        <div class="audit-log-timeline-marker" aria-hidden="true">
                            <span><i class="bi bi-clock-history"></i></span>
                        </div>
                        <div class="audit-log-timeline-card">
                            <div class="audit-log-timeline-main">
                                <div class="audit-log-timeline-meta">
                                    <span class="audit-log-timeline-index">#<?= app_table_row_number($page, $perPage, $index) ?></span>
                                    <span><i class="bi bi-calendar3"></i><?= htmlspecialchars(app_format_thai_datetime((string) ($row['created_at'] ?? ''))) ?></span>
                                </div>
                                <h3>
                                    <?= htmlspecialchars($tableConfigs[$tableName]['label'] ?? $tableName ?: '-') ?>
                                    <?php if ($tableName !== ''): ?>
                                        <em><?= htmlspecialchars($tableName) ?></em>
                                    <?php endif; ?>
                                </h3>
                                <p><?= htmlspecialchars($noteText) ?></p>
                            </div>
                            <div class="audit-log-timeline-side">
                                <span class="audit-log-action-pill <?= htmlspecialchars($actionClass) ?>"><?= htmlspecialchars((string) ($row['action_type'] ?? '-')) ?></span>
                                <span class="audit-log-timeline-actor"><i class="bi bi-person"></i><?= htmlspecialchars((string) ($row['actor_name_snapshot'] ?: '-')) ?></span>
                                <span class="audit-log-status is-saved">บันทึกแล้ว</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="audit-log-table-footer">
        <div class="audit-log-pagination-meta">
            แสดง <?= number_format(count($rows)) ?> จาก <?= number_format($totalRows) ?> รายการ
        </div>
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(app_build_table_query([
                                'q' => $search,
                                'table_name' => $tableFilter ?? '',
                                'action_type' => $actionFilter ?? '',
                                'actor' => $actorFilter ?? '',
                                'log_date' => $dateFilter ?? '',
                                'per_page' => $perPage,
                            ], ['page' => $i])) ?>" data-table-page-link><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

</div>
