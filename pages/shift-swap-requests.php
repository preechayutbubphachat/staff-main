<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../includes/report_helpers.php';
require_once __DIR__ . '/../includes/profile_modal.php';
require_once __DIR__ . '/../includes/notification_helpers.php';
require_once __DIR__ . '/../includes/shift_swap_service.php';

app_require_login();
date_default_timezone_set('Asia/Bangkok');

$currentUserId = (int) ($_SESSION['id'] ?? 0);
$csrfToken = app_csrf_token('shift_swap');
$selectedAssignmentId = (int) ($_GET['assignment_id'] ?? 0);
$selectedAssignment = $selectedAssignmentId > 0 ? app_shift_swap_get_assignment($conn, $selectedAssignmentId) : null;
$targetAssignments = $selectedAssignment ? app_shift_swap_available_target_assignments($conn, $currentUserId, $selectedAssignmentId) : [];
$pageData = app_shift_swap_page_data($conn, $currentUserId);
$flash = (string) ($_SESSION['shift_swap_flash'] ?? '');
$flashType = (string) ($_SESSION['shift_swap_flash_type'] ?? 'success');
unset($_SESSION['shift_swap_flash'], $_SESSION['shift_swap_flash_type']);

$userStmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$userStmt->execute([$currentUserId]);
$userMeta = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$role = app_current_role();
$roleLabel = app_role_label($role);
$profileImageSrc = app_column_exists($conn, 'users', 'profile_image_path') ? app_resolve_user_image_url($userMeta['profile_image_path'] ?? '') : null;
$displayName = app_user_display_name($userMeta ?: ['fullname' => $_SESSION['fullname'] ?? '-']);
$dashboardCssHref = '../assets/css/dashboard-tailwind.output.css?v=' . @filemtime(__DIR__ . '/../assets/css/dashboard-tailwind.output.css');
$shiftTypes = app_shift_schedule_types();

function shift_swap_shift_label(?string $shiftType): string
{
    $types = app_shift_schedule_types();
    return (string) ($types[(string) $shiftType]['label'] ?? $shiftType ?? '-');
}

function shift_swap_request_line(array $row, string $prefix): string
{
    $date = (string) ($row[$prefix . '_date'] ?? '');
    $shift = shift_swap_shift_label((string) ($row[$prefix . '_shift_type'] ?? ''));
    $start = substr((string) ($row[$prefix . '_start_time'] ?? ''), 0, 5);
    $end = substr((string) ($row[$prefix . '_end_time'] ?? ''), 0, 5);
    return trim(app_format_thai_date($date) . ' · ' . $shift . ' · ' . $start . '-' . $end);
}

function shift_swap_fetch_full_rows(PDO $conn, array $rows): array
{
    $full = [];
    foreach ($rows as $row) {
        $request = app_shift_swap_get_request($conn, (int) $row['id']);
        if ($request) {
            $request['status_meta'] = app_shift_swap_status_meta((string) $request['status']);
            $full[] = $request;
        }
    }
    return $full;
}

$sentRows = shift_swap_fetch_full_rows($conn, $pageData['sent']);
$incomingRows = shift_swap_fetch_full_rows($conn, $pageData['incoming']);
$managerRows = shift_swap_fetch_full_rows($conn, $pageData['manager']);
$historyRows = array_values(array_filter($sentRows, static fn(array $row): bool => !in_array((string) $row['status'], ['pending_target_confirm', 'pending_manager_approval'], true)));
$activeSentRows = array_values(array_filter($sentRows, static fn(array $row): bool => in_array((string) $row['status'], ['pending_target_confirm', 'pending_manager_approval'], true)));
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>คำขอแลกเวร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssHref) ?>">
</head>
<body class="dash-shell shift-swap-page-shell">
<?php render_dashboard_sidebar('shift-swap-requests.php', $displayName, $roleLabel, $profileImageSrc); ?>

<main class="dash-main shift-swap-page-main">
    <header class="dash-topbar">
        <button type="button" class="dash-icon-button lg:hidden" data-dashboard-sidebar-open aria-label="เปิดเมนู">
            <i class="bi bi-list text-xl"></i>
        </button>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-hospital-teal">Shift Swap Workflow</p>
            <h1 class="font-prompt text-2xl font-bold tracking-[-0.03em] text-hospital-ink">คำขอแลกเวร</h1>
        </div>
        <?php render_notification_bell(); ?>
        <button type="button" class="dash-profile-button" data-profile-modal-trigger data-user-id="<?= $currentUserId ?>">
            <span class="dash-avatar">
                <?php if ($profileImageSrc): ?>
                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="h-full w-full object-cover">
                <?php else: ?>
                    <?= htmlspecialchars(mb_substr($displayName !== '-' ? $displayName : 'U', 0, 1, 'UTF-8')) ?>
                <?php endif; ?>
            </span>
            <span class="hidden text-left sm:block">
                <span class="block max-w-[8rem] truncate font-semibold text-hospital-ink"><?= htmlspecialchars($displayName) ?></span>
                <span class="block text-xs text-hospital-muted"><?= htmlspecialchars($roleLabel) ?></span>
            </span>
            <i class="bi bi-chevron-down text-xs text-hospital-muted"></i>
        </button>
    </header>

    <div class="shift-swap-frame">
        <?php if ($flash !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($flashType) ?> rounded-4 border-0 shadow-sm" role="alert">
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <section class="shift-swap-hero">
            <div>
                <span class="dash-hero-pill"><i class="bi bi-arrow-left-right"></i> Controlled Swap</span>
                <h2>ระบบแลกเวร</h2>
                <p>เจ้าหน้าที่ส่งคำขอ อีกฝ่ายยืนยัน แล้วหัวหน้าอนุมัติก่อนระบบจึงสลับ assignment จริง</p>
            </div>
            <div class="shift-swap-stats">
                <div><span>ที่ฉันส่ง</span><strong><?= number_format(count($activeSentRows)) ?></strong></div>
                <div><span>รอฉันยืนยัน</span><strong><?= number_format(count($incomingRows)) ?></strong></div>
                <div><span>รออนุมัติ</span><strong><?= number_format(count($managerRows)) ?></strong></div>
                <div><span>ประวัติ</span><strong><?= number_format(count($historyRows)) ?></strong></div>
            </div>
        </section>

        <section class="shift-swap-create-card">
            <div class="shift-swap-card-head">
                <div>
                    <h3>ขอแลกเวร</h3>
                    <p>เลือกเวรของคุณจากหน้าเวรของฉัน แล้วเลือกเวรของเจ้าหน้าที่ปลายทางในแผนกเดียวกัน</p>
                </div>
                <a class="dash-btn dash-btn-ghost" href="my-shifts.php"><i class="bi bi-calendar3"></i> กลับไปเวรของฉัน</a>
            </div>
            <?php if (!$selectedAssignment || (int) ($selectedAssignment['staff_id'] ?? 0) !== $currentUserId): ?>
                <div class="shift-swap-empty">กรุณาเลือกเวรของคุณจากหน้า “เวรของฉัน” เพื่อเริ่มคำขอแลกเวร</div>
            <?php else: ?>
                <form method="post" action="../actions/create-shift-swap-request.php" class="shift-swap-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="requester_assignment_id" value="<?= (int) $selectedAssignmentId ?>">
                    <div class="shift-swap-selected">
                        <span>เวรของฉัน</span>
                        <strong><?= htmlspecialchars(app_format_thai_date((string) $selectedAssignment['schedule_date'])) ?> · <?= htmlspecialchars($shiftTypes[(string) $selectedAssignment['shift_type']]['label'] ?? $selectedAssignment['shift_type']) ?> · <?= htmlspecialchars(substr((string) $selectedAssignment['start_time'], 0, 5)) ?>-<?= htmlspecialchars(substr((string) $selectedAssignment['end_time'], 0, 5)) ?></strong>
                    </div>
                    <label>
                        <span>เลือกเวรของเจ้าหน้าที่ปลายทาง</span>
                        <select name="target_assignment_id" class="form-select" required>
                            <option value="">เลือกเวรที่ต้องการแลก</option>
                            <?php foreach ($targetAssignments as $target): ?>
                                <option value="<?= (int) $target['assignment_id'] ?>">
                                    <?= htmlspecialchars($target['staff_name']) ?> · <?= htmlspecialchars(app_format_thai_date((string) $target['schedule_date'])) ?> · <?= htmlspecialchars($shiftTypes[(string) $target['shift_type']]['label'] ?? $target['shift_type']) ?> · <?= htmlspecialchars(substr((string) $target['start_time'], 0, 5)) ?>-<?= htmlspecialchars(substr((string) $target['end_time'], 0, 5)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="shift-swap-note">
                        <span>เหตุผลการแลกเวร</span>
                        <textarea name="reason" class="form-control" rows="3" required maxlength="1000" placeholder="ระบุเหตุผลเพื่อให้อีกฝ่ายและหัวหน้าใช้ประกอบการตัดสินใจ"></textarea>
                    </label>
                    <div class="shift-swap-actions">
                        <button type="submit" class="dash-btn dash-btn-primary" <?= !$targetAssignments ? 'disabled' : '' ?>><i class="bi bi-send"></i> ส่งคำขอแลกเวร</button>
                        <?php if (!$targetAssignments): ?><span>ยังไม่มีเวรปลายทางที่แลกได้ในแผนกเดียวกัน</span><?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="shift-swap-grid">
            <div class="shift-swap-panel" id="sent">
                <div class="shift-swap-card-head"><h3>คำขอที่ฉันส่ง</h3></div>
                <?php if (!$activeSentRows): ?>
                    <div class="shift-swap-empty">ยังไม่มีคำขอที่กำลังดำเนินการ</div>
                <?php endif; ?>
                <?php foreach ($activeSentRows as $row): ?>
                    <?php $meta = $row['status_meta']; ?>
                    <article class="shift-swap-request-card">
                        <div class="shift-swap-request-top">
                            <span class="shift-swap-status is-<?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                            <strong>#<?= (int) $row['id'] ?></strong>
                        </div>
                        <p><strong>ของฉัน:</strong> <?= htmlspecialchars(shift_swap_request_line($row, 'requester')) ?></p>
                        <p><strong>แลกกับ:</strong> <?= htmlspecialchars($row['target_name']) ?> · <?= htmlspecialchars(shift_swap_request_line($row, 'target')) ?></p>
                        <p class="shift-swap-muted"><?= htmlspecialchars((string) ($row['reason'] ?? '')) ?></p>
                        <?php if ((string) $row['status'] === 'pending_target_confirm'): ?>
                            <form method="post" action="../actions/cancel-shift-swap.php" onsubmit="return confirm('ยกเลิกคำขอแลกเวรนี้?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="swap_request_id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="dash-btn dash-btn-ghost"><i class="bi bi-x-circle"></i> ยกเลิกคำขอ</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shift-swap-panel" id="incoming">
                <div class="shift-swap-card-head"><h3>รอฉันยืนยัน</h3></div>
                <?php if (!$incomingRows): ?>
                    <div class="shift-swap-empty">ยังไม่มีคำขอที่รอคุณยืนยัน</div>
                <?php endif; ?>
                <?php foreach ($incomingRows as $row): ?>
                    <article class="shift-swap-request-card">
                        <div class="shift-swap-request-top">
                            <span class="shift-swap-status is-pending-target">รอคุณยืนยัน</span>
                            <strong>#<?= (int) $row['id'] ?></strong>
                        </div>
                        <p><strong>ผู้ขอ:</strong> <?= htmlspecialchars($row['requester_name']) ?> · <?= htmlspecialchars(shift_swap_request_line($row, 'requester')) ?></p>
                        <p><strong>เวรของคุณ:</strong> <?= htmlspecialchars(shift_swap_request_line($row, 'target')) ?></p>
                        <p class="shift-swap-muted"><?= htmlspecialchars((string) ($row['reason'] ?? '')) ?></p>
                        <form method="post" action="../actions/respond-shift-swap.php" class="shift-swap-inline-decision">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="swap_request_id" value="<?= (int) $row['id'] ?>">
                            <textarea name="note" class="form-control" rows="2" placeholder="หมายเหตุถึงผู้ขอหรือหัวหน้า"></textarea>
                            <div>
                                <button type="submit" name="decision" value="confirm" class="dash-btn dash-btn-primary"><i class="bi bi-check2-circle"></i> ยืนยัน</button>
                                <button type="submit" name="decision" value="reject" class="dash-btn dash-btn-ghost"><i class="bi bi-x-circle"></i> ปฏิเสธ</button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($managerRows): ?>
            <section class="shift-swap-panel shift-swap-manager-panel" id="manager">
                <div class="shift-swap-card-head">
                    <div>
                        <h3>คำขอแลกเวรรออนุมัติ</h3>
                        <p>หัวหน้าเห็นเฉพาะแผนกที่ตนมีสิทธิ์ และระบบจะ revalidate ก่อนสลับเวรจริง</p>
                    </div>
                </div>
                <?php foreach ($managerRows as $row): ?>
                    <article class="shift-swap-request-card">
                        <div class="shift-swap-request-top">
                            <span class="shift-swap-status is-pending-manager">รออนุมัติ</span>
                            <strong>#<?= (int) $row['id'] ?> · <?= htmlspecialchars((string) ($row['department_name'] ?? '-')) ?></strong>
                        </div>
                        <p><strong>ผู้ขอ:</strong> <?= htmlspecialchars($row['requester_name']) ?> · <?= htmlspecialchars(shift_swap_request_line($row, 'requester')) ?></p>
                        <p><strong>อีกฝ่าย:</strong> <?= htmlspecialchars($row['target_name']) ?> · <?= htmlspecialchars(shift_swap_request_line($row, 'target')) ?></p>
                        <p class="shift-swap-muted">เหตุผล: <?= htmlspecialchars((string) ($row['reason'] ?? '-')) ?></p>
                        <?php if (!empty($row['target_response_note'])): ?>
                            <p class="shift-swap-muted">หมายเหตุอีกฝ่าย: <?= htmlspecialchars((string) $row['target_response_note']) ?></p>
                        <?php endif; ?>
                        <form method="post" action="../actions/manager-shift-swap.php" class="shift-swap-inline-decision">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="swap_request_id" value="<?= (int) $row['id'] ?>">
                            <textarea name="note" class="form-control" rows="2" placeholder="หมายเหตุหัวหน้า"></textarea>
                            <div>
                                <button type="submit" name="decision" value="approve" class="dash-btn dash-btn-primary" onclick="return confirm('อนุมัติและสลับ assignment จริง?');"><i class="bi bi-check2-circle"></i> อนุมัติ</button>
                                <button type="submit" name="decision" value="reject" class="dash-btn dash-btn-ghost"><i class="bi bi-x-circle"></i> ปฏิเสธ</button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="shift-swap-panel" id="history">
            <div class="shift-swap-card-head"><h3>ประวัติคำขอของฉัน</h3></div>
            <?php if (!$historyRows): ?>
                <div class="shift-swap-empty">ยังไม่มีประวัติคำขอที่จบแล้ว</div>
            <?php endif; ?>
            <?php foreach ($historyRows as $row): ?>
                <?php $meta = $row['status_meta']; ?>
                <article class="shift-swap-history-row">
                    <span class="shift-swap-status is-<?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                    <strong>#<?= (int) $row['id'] ?></strong>
                    <span><?= htmlspecialchars($row['target_name']) ?> · <?= htmlspecialchars(shift_swap_request_line($row, 'target')) ?></span>
                    <?php if (!empty($row['target_response_note']) || !empty($row['manager_response_note'])): ?>
                        <small><?= htmlspecialchars(trim((string) ($row['target_response_note'] ?? '') . ' ' . (string) ($row['manager_response_note'] ?? ''))) ?></small>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</main>

<?php render_staff_profile_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/notifications.js"></script>
<?php render_staff_profile_modal_script(); ?>
</body>
</html>
