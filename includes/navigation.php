<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notification_helpers.php';

function app_nav_items(): array
{
    $items = [
        [
            'href' => 'dashboard.php',
            'label' => 'แดชบอร์ด',
            'icon' => 'bi-grid-1x2',
            'show' => true,
        ],
        [
            'href' => 'time.php',
            'label' => 'ลงเวลาเวร',
            'icon' => 'bi-clock-history',
            'show' => true,
        ],
        [
            'href' => 'approval_queue.php',
            'label' => 'ตรวจสอบเวร',
            'icon' => 'bi-patch-check',
            'show' => app_can('can_approve_logs'),
        ],
        [
            'href' => 'daily_schedule.php',
            'label' => 'เวรวันนี้',
            'icon' => 'bi-calendar-week',
            'show' => true,
        ],
        [
            'href' => 'my_reports.php',
            'label' => 'รายงานของฉัน',
            'icon' => 'bi-bar-chart-line',
            'show' => true,
        ],
        [
            'href' => 'department_reports.php',
            'label' => 'รายงานแผนก',
            'icon' => 'bi-building',
            'show' => app_can('can_view_department_reports'),
        ],
        [
            'href' => 'manage_time_logs.php',
            'label' => 'จัดการลงเวลาเวร',
            'icon' => 'bi-pencil-square',
            'show' => app_can('can_manage_time_logs'),
        ],
        [
            'href' => 'manage_users.php',
            'label' => 'จัดการผู้ใช้งาน',
            'icon' => 'bi-shield-lock',
            'show' => app_can('can_manage_user_permissions'),
        ],
        [
            'href' => 'profile.php',
            'label' => 'โปรไฟล์',
            'icon' => 'bi-person-circle',
            'show' => true,
        ],
    ];

    return array_values(array_filter($items, static fn($item) => !empty($item['show'])));
}

function app_nav_group_items(): array
{
    $allItems = app_nav_items();
    $indexed = [];
    foreach ($allItems as $item) {
        $indexed[$item['href']] = $item;
    }

    $groups = [];

    if (isset($indexed['dashboard.php'])) {
        $groups[] = [
            'type' => 'link',
            'href' => 'dashboard.php',
            'label' => 'แดชบอร์ด',
            'icon' => 'bi-grid-1x2',
        ];
    }

    if (isset($indexed['time.php'])) {
        $groups[] = [
            'type' => 'link',
            'href' => 'time.php',
            'label' => 'ลงเวลาเวร',
            'icon' => 'bi-clock-history',
        ];
    }

    if (isset($indexed['approval_queue.php'])) {
        $groups[] = [
            'type' => 'link',
            'href' => 'approval_queue.php',
            'label' => 'ตรวจสอบเวร',
            'icon' => 'bi-patch-check',
        ];
    }

    if (isset($indexed['daily_schedule.php'])) {
        $groups[] = [
            'type' => 'link',
            'href' => 'daily_schedule.php',
            'label' => 'เวรวันนี้',
            'icon' => 'bi-calendar-week',
        ];
    }

    $reportItems = [];
    foreach (array_values(array_filter([
        $indexed['my_reports.php'] ?? null,
        $indexed['department_reports.php'] ?? null,
    ])) as $item) {
        $reportItems[] = [
            'type' => 'link',
            'item' => $item,
        ];
    }
    if ($reportItems) {
        $groups[] = [
            'type' => 'dropdown',
            'key' => 'reports',
            'label' => 'รายงาน',
            'icon' => 'bi-bar-chart-line',
            'items' => $reportItems,
        ];
    }

    $personalItems = array_values(array_filter([
        $indexed['profile.php'] ?? null,
    ]));

    $operationsItems = array_values(array_filter([
        $indexed['manage_time_logs.php'] ?? null,
        $indexed['manage_users.php'] ?? null,
    ]));

    $moreItems = [];
    if ($personalItems) {
        $moreItems[] = [
            'type' => 'header',
            'label' => 'เกี่ยวกับฉัน',
        ];
        foreach ($personalItems as $item) {
            $moreItems[] = [
                'type' => 'link',
                'item' => $item,
            ];
        }
    }

    if ($operationsItems) {
        if ($moreItems) {
            $moreItems[] = ['type' => 'divider'];
        }

        $moreItems[] = [
            'type' => 'header',
            'label' => 'งานปฏิบัติการ',
        ];
        foreach ($operationsItems as $item) {
            $moreItems[] = [
                'type' => 'link',
                'item' => $item,
            ];
        }
    }

    $adminGroupItems = app_nav_admin_group_items();
    if ($adminGroupItems) {
        if ($moreItems) {
            $moreItems[] = ['type' => 'divider'];
        }

        $moreItems[] = [
            'type' => 'header',
            'label' => 'หลังบ้าน',
        ];
        foreach ($adminGroupItems as $item) {
            $moreItems[] = [
                'type' => 'link',
                'item' => $item,
            ];
        }
    }

    if ($moreItems) {
        $groups[] = [
            'type' => 'dropdown',
            'key' => 'more',
            'label' => 'และอื่นๆ',
            'icon' => 'bi-three-dots-circle',
            'items' => $moreItems,
        ];
    }

    return $groups;
}

function app_nav_admin_group_items(): array
{
    if (!app_can('can_manage_database')) {
        return [];
    }

    return [
        [
            'href' => 'db_admin_dashboard.php',
            'label' => 'จัดการข้อมูลฐานข้อมูล',
            'icon' => 'bi-database-gear',
        ],
        [
            'href' => 'db_table_browser.php',
            'label' => 'จัดการตารางข้อมูล',
            'icon' => 'bi-table',
        ],
        [
            'href' => 'db_change_logs.php',
            'label' => 'บันทึกการเปลี่ยนแปลงข้อมูล',
            'icon' => 'bi-clock-history',
        ],
    ];
}

function app_nav_resolve_href(string $href): string
{
    if ($href === 'approval_queue.php') {
        return 'approval_queue.php?status=pending';
    }

    return $href;
}

function render_app_navigation(string $currentPage = ''): void
{
    $roleLabel = app_role_label(app_current_role());
    $roleCompactLabel = app_role_compact_label(app_current_role());
    $groups = app_nav_group_items();
    $uiStateContext = app_ui_state_context();
    global $conn;
    if (isset($conn) && app_can('can_approve_logs')) {
        app_sync_reviewer_queue_notifications($conn);
    }
    $notificationCount = isset($conn) ? app_get_unread_notification_count($conn, (int) $uiStateContext['user_id']) : 0;
    $recentNotifications = isset($conn) ? app_get_recent_notifications($conn, (int) $uiStateContext['user_id'], 6) : [];
    $notificationCsrf = app_csrf_token('notifications_ajax');
    ?>
    <nav
        class="navbar navbar-expand-xl app-navbar sticky-top"
        data-ui-user-id="<?= (int) $uiStateContext['user_id'] ?>"
        data-ui-login-marker="<?= htmlspecialchars($uiStateContext['login_marker']) ?>"
    >
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center fw-semibold" href="dashboard.php">
                <img src="../LOGO/nongphok_logo.png" alt="Logo" class="brand-mark">
                <span class="brand-copy">
                    <span class="brand-title">ระบบลงเวลาเวร</span>
                    <span class="brand-subtitle">Nong Phok Hospital Attendance</span>
                </span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNav" aria-controls="appNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="appNav">
                <div class="app-navbar-main">
                    <ul class="navbar-nav app-navbar-links ms-lg-4 me-auto mb-2 mb-lg-0">
                        <?php foreach ($groups as $group): ?>
                            <?php if ($group['type'] === 'link'): ?>
                                <li class="nav-item">
                                    <a class="nav-link px-3 <?= $currentPage === $group['href'] ? 'active fw-semibold' : '' ?>" href="<?= htmlspecialchars(app_nav_resolve_href($group['href'])) ?>">
                                        <i class="bi <?= htmlspecialchars($group['icon']) ?> me-1"></i><?= htmlspecialchars($group['label']) ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <?php
                                $groupActive = false;
                                foreach ($group['items'] as $menuItem) {
                                    if (($menuItem['type'] ?? '') === 'link' && ($menuItem['item']['href'] ?? '') === $currentPage) {
                                        $groupActive = true;
                                        break;
                                    }
                                }
                                ?>
                                <li class="nav-item dropdown">
                                    <button class="nav-link dropdown-toggle px-3 <?= $groupActive ? 'active fw-semibold' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi <?= htmlspecialchars($group['icon']) ?> me-1"></i><?= htmlspecialchars($group['label']) ?>
                                    </button>
                                    <ul class="dropdown-menu <?= ($group['key'] ?? '') === 'more' ? 'dropdown-menu-lg-end app-more-menu' : '' ?>">
                                        <?php foreach ($group['items'] as $menuItem): ?>
                                            <?php if (($menuItem['type'] ?? '') === 'header'): ?>
                                                <li><h6 class="dropdown-header"><?= htmlspecialchars($menuItem['label']) ?></h6></li>
                                            <?php elseif (($menuItem['type'] ?? '') === 'divider'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                            <?php elseif (($menuItem['type'] ?? '') === 'link' && !empty($menuItem['item'])): ?>
                                                <?php $item = $menuItem['item']; ?>
                                                <li>
                                                    <a class="dropdown-item <?= $currentPage === $item['href'] ? 'active' : '' ?>" href="<?= htmlspecialchars(app_nav_resolve_href($item['href'])) ?>">
                                                        <i class="bi <?= htmlspecialchars($item['icon']) ?> me-2"></i><?= htmlspecialchars($item['label']) ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>

                    <div class="d-flex align-items-center gap-3 nav-actions">
                        <div class="dropdown notification-dropdown">
                        <button
                            class="btn btn-light rounded-pill position-relative notification-bell <?= $notificationCount > 0 ? 'has-unread notification-bell--active' : '' ?>"
                            type="button"
                            data-bs-toggle="dropdown"
                            data-notification-toggle
                            aria-expanded="false"
                        >
                            <i class="bi bi-bell"></i>
                            <?php if ($notificationCount > 0): ?>
                                <span class="notification-count-badge" data-notification-count><?= (int) min($notificationCount, 99) ?></span>
                            <?php else: ?>
                                <span class="notification-count-badge d-none" data-notification-count>0</span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-menu p-0">
                            <div class="notification-menu-header">
                                <div>
                                    <div class="fw-semibold">การแจ้งเตือน</div>
                                    <div class="small text-muted">อัปเดตงานล่าสุดตามสิทธิ์ของคุณ</div>
                                </div>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" data-notification-mark-all>
                                    อ่านทั้งหมด
                                </button>
                            </div>
                            <div class="notification-menu-body" data-notification-list>
                                <?php if ($recentNotifications): ?>
                                    <?php
                                    $unreadNotifications = array_values(array_filter($recentNotifications, static fn($item) => empty($item['is_read'])));
                                    $readNotifications = array_values(array_filter($recentNotifications, static fn($item) => !empty($item['is_read'])));
                                    ?>
                                    <?php if ($unreadNotifications): ?>
                                        <div class="notification-section-label">ยังไม่ได้อ่าน</div>
                                        <div class="notification-section">
                                            <?php foreach ($unreadNotifications as $notification): ?>
                                                <div class="notification-item notification-item--unread" data-notification-item>
                                                    <a href="<?= htmlspecialchars($notification['target_url'] ?: 'notifications.php') ?>" class="notification-link" data-notification-open data-notification-id="<?= (int) $notification['id'] ?>">
                                                        <div class="notification-item-head">
                                                            <div class="notification-item-title" title="<?= htmlspecialchars($notification['title']) ?>"><?= htmlspecialchars($notification['title']) ?></div>
                                                            <span class="notification-item-dot" aria-hidden="true"></span>
                                                        </div>
                                                        <div class="notification-item-message" title="<?= htmlspecialchars($notification['message']) ?>"><?= htmlspecialchars($notification['message']) ?></div>
                                                        <div class="notification-item-time"><?= htmlspecialchars($notification['created_at_label']) ?></div>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-link text-decoration-none notification-mark-one" data-notification-read data-notification-id="<?= (int) $notification['id'] ?>">อ่านแล้ว</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($readNotifications): ?>
                                        <div class="notification-section-label">อ่านแล้ว</div>
                                        <div class="notification-section">
                                            <?php foreach ($readNotifications as $notification): ?>
                                                <div class="notification-item" data-notification-item>
                                                    <a href="<?= htmlspecialchars($notification['target_url'] ?: 'notifications.php') ?>" class="notification-link" data-notification-open data-notification-id="<?= (int) $notification['id'] ?>">
                                                        <div class="notification-item-head">
                                                            <div class="notification-item-title" title="<?= htmlspecialchars($notification['title']) ?>"><?= htmlspecialchars($notification['title']) ?></div>
                                                        </div>
                                                        <div class="notification-item-message" title="<?= htmlspecialchars($notification['message']) ?>"><?= htmlspecialchars($notification['message']) ?></div>
                                                        <div class="notification-item-time"><?= htmlspecialchars($notification['created_at_label']) ?></div>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="notification-empty" data-notification-empty>ยังไม่มีการแจ้งเตือนใหม่</div>
                                <?php endif; ?>
                            </div>
                            <div class="notification-menu-footer">
                                <a href="notifications.php" class="btn btn-outline-secondary btn-sm rounded-pill" data-global-loading-nav data-loading-message="กำลังเปิดหน้าการแจ้งเตือน...">ดูทั้งหมด</a>
                            </div>
                        </div>
                        </div>
                        <div class="text-end role-badge">
                            <div class="small text-muted">บทบาท</div>
                            <div class="fw-semibold" title="<?= htmlspecialchars($roleLabel) ?>"><?= htmlspecialchars($roleCompactLabel) ?></div>
                        </div>
                        <a href="../auth/logout.php" class="btn btn-outline-dark rounded-pill px-3 logout-btn" data-global-loading-nav data-loading-message="กำลังออกจากระบบ...">
                            <i class="bi bi-box-arrow-right me-1"></i>ออกจากระบบ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <link rel="stylesheet" href="../assets/css/loading-overlay.css">
    <script src="../assets/js/page-state.js"></script>
    <script src="../assets/js/global-loading.js"></script>
    <script src="../assets/js/navbar-auto-hide.js"></script>
    <script src="../assets/js/thai-date-ui.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        window.PageState && window.PageState.setContext({
            userId: <?= (int) $uiStateContext['user_id'] ?>,
            loginMarker: <?= json_encode((string) $uiStateContext['login_marker'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        });
        window.GlobalLoading && window.GlobalLoading.init();
        window.NavbarAutoHide && window.NavbarAutoHide.init({ selector: '.app-navbar' });
        window.ThaiDateUI && window.ThaiDateUI.init(document);
        window.AppNotifications && window.AppNotifications.init({
            listUrl: '../ajax/notifications/list_recent.php',
            countUrl: '../ajax/notifications/get_unread_count.php',
            markReadUrl: '../ajax/notifications/mark_read.php',
            markAllUrl: '../ajax/notifications/mark_all_read.php',
            csrfToken: <?= json_encode($notificationCsrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            pollMs: 20000
        });
    </script>
    <?php if (!in_array($currentPage, ['dashboard.php', 'index.php'], true)): ?>
        <?php render_app_back_button('dashboard.php'); ?>
    <?php endif; ?>
    <?php
}

function render_app_back_button(string $fallbackHref = 'dashboard.php', string $label = 'ย้อนกลับ'): void
{
    $fallbackHref = trim($fallbackHref) !== '' ? $fallbackHref : 'dashboard.php';
    ?>
    <div class="app-backbar">
        <div class="container">
            <button
                type="button"
                class="btn app-back-button"
                data-app-back-button
                data-fallback-href="<?= htmlspecialchars($fallbackHref) ?>"
            >
                <i class="bi bi-arrow-left"></i>
                <span><?= htmlspecialchars($label) ?></span>
            </button>
        </div>
    </div>

    <script>
        (function () {
            const button = document.querySelector('[data-app-back-button]');
            if (!button || button.dataset.bound === '1') {
                return;
            }

            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                const fallbackHref = button.getAttribute('data-fallback-href') || 'dashboard.php';
                const hasHistory = window.history.length > 1 && document.referrer;

                if (hasHistory) {
                    window.GlobalLoading && window.GlobalLoading.showPageNavigationLoading('กำลังย้อนกลับ...');
                    window.history.back();
                    return;
                }

                window.GlobalLoading && window.GlobalLoading.showPageNavigationLoading('กำลังย้อนกลับ...');
                window.location.href = fallbackHref;
            });
        })();
    </script>
    <?php
}

