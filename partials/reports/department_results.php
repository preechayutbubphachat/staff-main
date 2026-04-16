<section class="ops-results-panel">
    <section class="row g-4 mb-4" data-results-summary>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">จำนวนเจ้าหน้าที่</div>
                <div class="report-stat-value"><?= number_format((int) ($departmentTotals['staff_count'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">จำนวนเวร</div>
                <div class="report-stat-value"><?= number_format((int) ($departmentTotals['total_logs'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">ชั่วโมงรวม</div>
                <div class="report-stat-value"><?= number_format((float) ($departmentTotals['total_hours'] ?? 0), 2) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="report-stat-card">
                <div class="report-stat-label">รอตรวจ</div>
                <div class="report-stat-value"><?= number_format((int) ($departmentTotals['pending_logs'] ?? 0)) ?></div>
            </div>
        </div>
    </section>

    <div class="mb-4">
        <div class="small text-uppercase fw-semibold text-secondary mb-2">หัวข้อรายงานปัจจุบัน</div>
        <h2 class="ops-results-title mb-2"><?= htmlspecialchars($headingContext['heading_text'] ?? 'รายงานสรุปแผนก') ?></h2>
        <p class="ops-results-subtitle mb-0"><?= htmlspecialchars($headingContext['subheading_text'] ?? 'ข้อมูลสรุปตามตัวกรองที่เลือก') ?></p>
    </div>

    <div class="ops-results-header">
        <div>
            <h2 class="ops-results-title">สรุปรายบุคคล</h2>
            <p class="ops-results-subtitle">คลิกชื่อเจ้าหน้าที่เพื่อดูโปรไฟล์ และใช้ตัวสลับมุมมองเพื่อดูแบบการ์ดหรือแบบตารางจากชุดข้อมูลเดียวกัน</p>
        </div>
        <div class="table-toolbar-side">
            <div class="nav nav-pills view-switch">
                <a class="nav-link <?= $view === 'cards' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'cards', 'p' => 1])) ?>" data-table-view-link>การ์ด</a>
                <a class="nav-link <?= $view === 'table' ? 'active' : '' ?>" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => 'table', 'p' => 1])) ?>" data-table-view-link>ตาราง</a>
            </div>
        </div>
    </div>

    <?php if (!$pagedRows): ?>
        <div class="ops-empty">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</div>
    <?php elseif ($view === 'cards'): ?>
        <div class="report-card-grid">
            <?php foreach ($pagedRows as $index => $row): ?>
                <?php $pendingLogs = max(0, (int) $row['total_logs'] - (int) $row['approved_logs']); ?>
                <article class="report-card">
                    <div class="report-card-top">
                        <div>
                            <div class="small text-muted mb-2">ลำดับที่ <?= app_table_row_number($page, $perPage, $index) ?></div>
                            <button type="button" class="staff-link btn btn-link p-0 text-start fs-5" data-profile-modal-trigger data-user-id="<?= (int) ($row['id'] ?? 0) ?>">
                                <?= htmlspecialchars(app_user_display_name($row)) ?>
                            </button>
                            <div class="text-muted"><?= htmlspecialchars($row['position_name'] ?: 'ไม่ระบุตำแหน่ง') ?></div>
                            <div class="text-muted small mt-1"><?= htmlspecialchars($row['department_name'] ?? '-') ?></div>
                        </div>
                        <span class="badge text-bg-success"><?= (int) $row['approved_logs'] ?> ตรวจแล้ว</span>
                    </div>
                    <div class="report-card-meta">
                        <span class="badge text-bg-light border"><?= (int) $row['total_logs'] ?> เวร</span>
                        <span class="badge text-bg-light border"><?= number_format((float) $row['total_hours'], 2) ?> ชั่วโมง</span>
                        <span class="badge text-bg-warning"><?= $pendingLogs ?> รอตรวจ</span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive table-shell">
            <table class="table align-middle ops-table">
                <?php app_render_table_colgroup('department_summary'); ?>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อเจ้าหน้าที่</th>
                        <th>ตำแหน่ง</th>
                        <th>แผนก</th>
                        <th>จำนวนเวร</th>
                        <th>ชั่วโมงรวม</th>
                        <th>ตรวจแล้ว</th>
                        <th>รอตรวจ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagedRows as $index => $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= app_table_row_number($page, $perPage, $index) ?></td>
                            <td class="name-cell fw-semibold">
                                <button type="button" class="staff-link btn btn-link p-0 text-start" data-profile-modal-trigger data-user-id="<?= (int) ($row['id'] ?? 0) ?>">
                                    <?= htmlspecialchars(app_user_display_name($row)) ?>
                                </button>
                            </td>
                            <td class="position-cell"><?= htmlspecialchars($row['position_name'] ?: '-') ?></td>
                            <td class="department-cell"><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>
                            <td><?= (int) $row['total_logs'] ?></td>
                            <td class="fw-semibold text-primary"><?= number_format((float) $row['total_hours'], 2) ?></td>
                            <td><span class="badge text-bg-success"><?= (int) $row['approved_logs'] ?></span></td>
                            <td><span class="badge text-bg-warning"><?= max(0, (int) $row['total_logs'] - (int) $row['approved_logs']) ?></span></td>
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
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(app_build_table_query($queryBase, ['view' => $view, 'p' => $i])) ?>" data-table-page-link><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</section>
