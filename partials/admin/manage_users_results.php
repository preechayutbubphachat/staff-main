<?php
if (!function_exists('app_manage_users_role_badge_class')) {
    function app_manage_users_role_badge_class(string $role): string
    {
        return match ($role) {
            'admin' => 'is-admin',
            'checker' => 'is-checker',
            'finance' => 'is-finance',
            default => 'is-staff',
        };
    }
}

if (!function_exists('app_manage_users_permission_state')) {
    function app_manage_users_permission_state(array $row): array
    {
        if (!empty($row['can_manage_user_permissions']) || !empty($row['can_manage_database'])) {
            return ['label' => 'อนุมัติแล้ว', 'class' => 'is-success'];
        }

        if (!empty($row['can_approve_logs']) || !empty($row['can_manage_time_logs']) || !empty($row['can_export_reports'])) {
            return ['label' => 'อนุมัติแล้ว', 'class' => 'is-success'];
        }

        return ['label' => 'รออนุมัติ', 'class' => 'is-warning'];
    }
}

if (!function_exists('app_manage_users_account_state')) {
    function app_manage_users_account_state(array $row): array
    {
        $isActive = !array_key_exists('is_active', $row) || (int) $row['is_active'] === 1;

        return $isActive
            ? ['label' => 'ใช้งานอยู่', 'class' => 'is-success']
            : ['label' => 'ระงับ', 'class' => 'is-danger'];
    }
}

$rangeStart = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$rangeEnd = min($totalRows, $page * $perPage);
$_muPrintQuery = app_manage_users_query($filters, ['type' => 'manage_users']);
$_muPdfQuery   = app_manage_users_query($filters, ['type' => 'manage_users', 'download' => 'pdf']);
$_muCsvQuery   = app_manage_users_query($filters, ['type' => 'manage_users']);
?>
<section class="admin-users-table-card admin-users-table-panel">
    <div class="admin-users-results-header">
        <div>
            <span>User List</span>
            <h2>รายการผู้ใช้งาน</h2>
            <p>จัดการบัญชีผู้ใช้ บทบาท สิทธิ์ และสถานะการใช้งาน</p>
        </div>
        <div class="admin-users-header-right">
            <div class="report-action-group">
                <a href="report_print.php?<?= htmlspecialchars($_muPrintQuery) ?>" target="_blank" rel="noopener" class="dash-btn dash-btn-ghost">
                    <i class="bi bi-printer"></i>พิมพ์
                </a>
                <a href="report_print.php?<?= htmlspecialchars($_muPdfQuery) ?>" target="_blank" rel="noopener" class="dash-btn dash-btn-ghost">
                    <i class="bi bi-filetype-pdf"></i>PDF
                </a>
                <a href="export_report.php?<?= htmlspecialchars($_muCsvQuery) ?>" class="dash-btn dash-btn-ghost">
                    <i class="bi bi-filetype-csv"></i>CSV
                </a>
            </div>
            <div class="admin-users-view-switch" aria-label="ตัวเลือกมุมมอง">
                <button type="button" class="is-active"><i class="bi bi-table"></i> ตาราง</button>
                <button type="button"><i class="bi bi-grid"></i> การ์ด</button>
            </div>
        </div>
    </div>

    <div class="admin-users-table-shell">
        <table class="admin-users-table">
            <colgroup>
                <col style="width: 44px">
                <col style="width: 58px">
                <col style="width: 190px">
                <col style="width: 124px">
                <col style="width: 140px">
                <col style="width: 130px">
                <col style="width: 136px">
                <col style="width: 132px">
                <col style="width: 128px">
                <col style="width: 190px">
            </colgroup>
            <thead>
                <tr>
                    <th><input type="checkbox" aria-label="เลือกทั้งหมด"></th>
                    <th>ลำดับ</th>
                    <th>ชื่อเจ้าหน้าที่</th>
                    <th>Username</th>
                    <th>ตำแหน่ง</th>
                    <th>แผนก</th>
                    <th>บทบาท</th>
                    <th>สถานะสิทธิ์</th>
                    <th>สถานะบัญชี</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="10">
                        <div class="admin-users-empty">
                            <i class="bi bi-search"></i>
                            <strong>ไม่พบข้อมูลตามเงื่อนไขที่เลือก</strong>
                            <span>ลองปรับคำค้นหา แผนก บทบาท หรือสถานะบัญชีอีกครั้ง</span>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($rows as $index => $row): ?>
                <?php
                $displayName = app_user_display_name($row);
                $roleKey = (string) ($row['role'] ?? 'staff');
                $permissionState = app_manage_users_permission_state($row);
                $accountState = app_manage_users_account_state($row);
                ?>
                <tr>
                    <td><input type="checkbox" aria-label="เลือก <?= htmlspecialchars($displayName) ?>"></td>
                    <td class="admin-users-index-cell"><?= app_table_row_number($page, $perPage, $index) ?></td>
                    <td class="admin-users-name-cell">
                        <button type="button" data-profile-modal-trigger data-user-id="<?= (int) $row['id'] ?>">
                            <?= htmlspecialchars($displayName) ?>
                        </button>
                        <span><?= htmlspecialchars((string) ($row['username'] ?? '-')) ?></span>
                    </td>
                    <td class="admin-users-username-cell"><?= htmlspecialchars((string) ($row['username'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['position_name'] ?: '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['department_name'] ?: '-')) ?></td>
                    <td>
                        <span class="admin-users-role-badge <?= app_manage_users_role_badge_class($roleKey) ?>">
                            <?= htmlspecialchars(app_role_label($roleKey)) ?>
                        </span>
                    </td>
                    <td>
                        <span class="admin-users-status-badge <?= htmlspecialchars($permissionState['class']) ?>">
                            <?= htmlspecialchars($permissionState['label']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="admin-users-status-badge <?= htmlspecialchars($accountState['class']) ?>">
                            <?= htmlspecialchars($accountState['label']) ?>
                        </span>
                    </td>
                    <td class="admin-users-actions-cell">
                        <button type="button" class="admin-users-row-action is-detail" data-profile-modal-trigger data-user-id="<?= (int) $row['id'] ?>">
                            ดูรายละเอียด
                        </button>
                        <a href="edit_user.php?id=<?= (int) $row['id'] ?>" class="admin-users-row-action is-edit">
                            แก้ไขข้อมูล
                        </a>
                        <button type="button" class="admin-users-more-action" aria-label="ตัวเลือกเพิ่มเติม">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-users-table-footer">
        <label class="admin-users-footer-page-size">
            <span>แสดง</span>
            <select data-users-per-page="true">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
            <span>รายการ</span>
        </label>

        <div class="admin-users-page-summary">
            <?= number_format($rangeStart) ?>-<?= number_format($rangeEnd) ?> จาก <?= number_format($totalRows) ?> รายการ
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="admin-users-pagination" aria-label="หน้า">
                <?php if ($page > 1): ?>
                    <a href="?<?= htmlspecialchars(app_manage_users_query($filters, ['p' => $page - 1, 'per_page' => $perPage])) ?>" data-table-page-link aria-label="หน้าก่อนหน้า">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $windowStart = max(1, $page - 2);
                $windowEnd = min($totalPages, $page + 2);
                for ($i = $windowStart; $i <= $windowEnd; $i++):
                ?>
                    <a class="<?= $i === $page ? 'is-active' : '' ?>" href="?<?= htmlspecialchars(app_manage_users_query($filters, ['p' => $i, 'per_page' => $perPage])) ?>" data-table-page-link>
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= htmlspecialchars(app_manage_users_query($filters, ['p' => $page + 1, 'per_page' => $perPage])) ?>" data-table-page-link aria-label="หน้าถัดไป">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
