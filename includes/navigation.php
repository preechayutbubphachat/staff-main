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
        // Hidden from primary navigation to reduce duplicate admin workflow.
        // Feature remains accessible via direct route and internal links.
        // $indexed['manage_users.php'] ?? null,
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
        // Hidden from sidebar navigation to reduce duplicate admin workflow.
        // Feature remains accessible via direct route and internal links.
        // [
        //     'href' => 'db_admin_dashboard.php',
        //     'label' => 'จัดการข้อมูลฐานข้อมูล',
        //     'icon' => 'bi-database-gear',
        // ],
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

function app_notification_ui_state(?int $userId = null, int $limit = 6): array
{
    global $conn;

    $context = app_ui_state_context();
    $safeUserId = $userId ?? (int) ($context['user_id'] ?? 0);
    $safeLimit = max(1, min(12, $limit));

    if (!isset($conn) || !$conn instanceof PDO || $safeUserId <= 0) {
        return [
            'unread_count' => 0,
            'items' => [],
            'csrf' => app_csrf_token('notifications_ajax'),
        ];
    }

    if (app_can('can_approve_logs')) {
        app_sync_reviewer_queue_notifications($conn);
    }

    return [
        'unread_count' => app_get_unread_notification_count($conn, $safeUserId),
        'items' => app_get_recent_notifications($conn, $safeUserId, $safeLimit),
        'csrf' => app_csrf_token('notifications_ajax'),
    ];
}

function render_notification_dropdown_items(array $notifications): void
{
    if (!$notifications) {
        ?>
        <div class="notification-empty" data-notification-empty>ยังไม่มีการแจ้งเตือนใหม่</div>
        <?php
        return;
    }

    $unreadNotifications = array_values(array_filter($notifications, static fn($item) => empty($item['is_read'])));
    $readNotifications = array_values(array_filter($notifications, static fn($item) => !empty($item['is_read'])));
    $sections = [
        ['label' => 'ยังไม่ได้อ่าน', 'items' => $unreadNotifications, 'unread' => true],
        ['label' => 'อ่านแล้ว', 'items' => $readNotifications, 'unread' => false],
    ];

    foreach ($sections as $section) {
        if (!$section['items']) {
            continue;
        }
        ?>
        <div class="notification-section-label"><?= htmlspecialchars($section['label']) ?></div>
        <div class="notification-section">
            <?php foreach ($section['items'] as $notification): ?>
                <?php $isUnread = !empty($section['unread']); ?>
                <div class="notification-item <?= $isUnread ? 'notification-item--unread' : '' ?>" data-notification-item>
                    <button
                        type="button"
                        class="notification-link"
                        data-notification-open
                        data-notification-id="<?= (int) $notification['id'] ?>"
                    >
                        <span class="notification-item-head">
                            <span class="notification-item-title" title="<?= htmlspecialchars($notification['title']) ?>"><?= htmlspecialchars($notification['title']) ?></span>
                            <?php if ($isUnread): ?>
                                <span class="notification-item-dot" aria-hidden="true"></span>
                            <?php else: ?>
                                <span class="notification-read-badge">อ่านแล้ว</span>
                            <?php endif; ?>
                        </span>
                        <span class="notification-item-message" title="<?= htmlspecialchars($notification['message']) ?>"><?= htmlspecialchars($notification['message']) ?></span>
                        <span class="notification-item-time"><?= htmlspecialchars($notification['created_at_label']) ?></span>
                    </button>
                    <?php if ($isUnread): ?>
                        <button type="button" class="notification-mark-one" data-notification-read data-notification-id="<?= (int) $notification['id'] ?>">อ่านแล้ว</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

function render_notification_bell(?array $state = null, string $buttonClass = 'dash-icon-button', string $ariaLabel = 'เปิดการแจ้งเตือน'): void
{
    $state = $state ?? app_notification_ui_state();
    $notificationCount = (int) ($state['unread_count'] ?? 0);
    $recentNotifications = is_array($state['items'] ?? null) ? $state['items'] : [];
    $csrf = (string) ($state['csrf'] ?? app_csrf_token('notifications_ajax'));
    $hasUnreadClass = $notificationCount > 0 ? ' has-unread notification-bell--active' : '';
    ?>
    <div
        class="notification-popover-root"
        data-notification-root
        data-list-url="../ajax/notifications/list_recent.php"
        data-count-url="../ajax/notifications/get_unread_count.php"
        data-mark-read-url="../ajax/notifications/mark_read.php"
        data-mark-all-url="../ajax/notifications/mark_all_read.php"
        data-csrf-token="<?= htmlspecialchars($csrf) ?>"
    >
        <button
            type="button"
            class="<?= htmlspecialchars(trim($buttonClass . ' notification-bell' . $hasUnreadClass)) ?>"
            aria-label="<?= htmlspecialchars($ariaLabel) ?>"
            aria-expanded="false"
            aria-haspopup="dialog"
            data-notification-toggle
        >
            <i class="bi bi-bell"></i>
            <span class="notification-count-badge <?= $notificationCount > 0 ? '' : 'd-none' ?>" data-notification-count>
                <?= $notificationCount > 99 ? '99+' : (int) $notificationCount ?>
            </span>
        </button>
        <div class="notification-menu notification-popover" role="dialog" aria-label="การแจ้งเตือน" data-notification-menu hidden>
            <div class="notification-menu-header">
                <div>
                    <div class="notification-menu-title">การแจ้งเตือน</div>
                    <div class="notification-menu-subtitle">อัปเดตล่าสุดตามสิทธิ์ของคุณ</div>
                </div>
                <button type="button" class="notification-mark-all-button" data-notification-mark-all>อ่านทั้งหมด</button>
            </div>
            <div class="notification-menu-body" data-notification-list>
                <?php render_notification_dropdown_items($recentNotifications); ?>
            </div>
            <div class="notification-menu-footer">
                <a href="notifications.php" class="notification-view-all" data-global-loading-nav data-loading-message="กำลังเปิดหน้าการแจ้งเตือน...">ดูทั้งหมด</a>
            </div>
        </div>
    </div>
    <?php
}

function app_dashboard_sidebar_sections(): array
{
    $items = [];
    foreach (app_nav_items() as $item) {
        $items[$item['href']] = $item;
    }

    $sectionItems = static function (array $hrefs) use ($items): array {
        $result = [];
        foreach ($hrefs as $href) {
            if (isset($items[$href])) {
                $result[] = $items[$href];
            }
        }
        return $result;
    };

    $sections = [
        [
            'label' => 'เมนูหลัก',
            'items' => $sectionItems(['dashboard.php', 'time.php', 'approval_queue.php', 'daily_schedule.php']),
        ],
        [
            'label' => 'รายงาน',
            'items' => $sectionItems(['my_reports.php', 'department_reports.php']),
        ],
        [
            'label' => 'และอื่นๆ',
            'items' => $sectionItems([
                'profile.php',
                'manage_time_logs.php',
                // Hidden from sidebar navigation to reduce duplicate admin workflow.
                // 'manage_users.php',
            ]),
        ],
    ];

    $adminItems = app_nav_admin_group_items();
    if ($adminItems) {
        $sections[] = [
            'label' => 'ผู้ดูแล',
            'items' => $adminItems,
        ];
    }

    return array_values(array_filter($sections, static fn($section) => !empty($section['items'])));
}

function render_dashboard_sidebar_links(string $currentPage): void
{
    foreach (app_dashboard_sidebar_sections() as $section) {
        ?>
        <div class="dash-nav-section">
            <div class="dash-section-label"><?= htmlspecialchars($section['label']) ?></div>
            <div class="dash-nav-list">
                <?php foreach ($section['items'] as $item): ?>
                    <?php $isActive = $currentPage === $item['href']; ?>
                    <a
                        class="dash-nav-link <?= $isActive ? 'active' : '' ?>"
                        href="<?= htmlspecialchars(app_nav_resolve_href($item['href'])) ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <span class="dash-nav-icon"><i class="bi <?= htmlspecialchars($item['icon']) ?>"></i></span>
                        <span class="dash-nav-text"><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

function render_dashboard_sidebar(string $currentPage, string $displayName, string $roleLabel, ?string $profileImageSrc = null): void
{
    ?>
    <aside class="dash-sidebar" aria-label="เมนูหลัก">
        <div class="dash-sidebar-panel">
            <a href="dashboard.php" class="dash-sidebar-brand">
                <span class="dash-sidebar-logo">
                    <img src="../LOGO/nongphok_logo.png" alt="Logo" class="dash-sidebar-logo-image">
                </span>
                <span class="dash-sidebar-brand-copy">
                    <span class="dash-sidebar-title">ระบบลงเวลา</span>
                    <span class="dash-sidebar-subtitle">โรงพยาบาลหนองพอก</span>
                </span>
            </a>

            <nav class="dash-sidebar-menu">
                <?php render_dashboard_sidebar_links($currentPage); ?>
            </nav>

            <div class="dash-sidebar-user-card">
                <div class="dash-sidebar-user-row">
                    <span class="dash-sidebar-avatar">
                        <?php if ($profileImageSrc): ?>
                            <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="รูปโปรไฟล์">
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </span>
                    <span class="dash-sidebar-user-copy">
                        <span class="dash-sidebar-user-name"><?= htmlspecialchars($displayName) ?></span>
                        <span class="dash-sidebar-user-role"><?= htmlspecialchars($roleLabel) ?></span>
                    </span>
                </div>
                <a href="../auth/logout.php" class="dash-sidebar-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    ออกจากระบบ
                </a>
            </div>
        </div>
    </aside>

    <div class="dash-mobile-backdrop" data-dashboard-sidebar-backdrop></div>
    <aside class="dash-mobile-drawer" data-dashboard-sidebar-drawer aria-label="เมนูมือถือ">
        <div class="dash-sidebar-panel">
            <div class="dash-mobile-sidebar-head">
                <a href="dashboard.php" class="dash-sidebar-brand">
                    <span class="dash-sidebar-logo">
                        <img src="../LOGO/nongphok_logo.png" alt="Logo" class="dash-sidebar-logo-image">
                    </span>
                    <span class="dash-sidebar-brand-copy">
                        <span class="dash-sidebar-title">ระบบลงเวลา</span>
                        <span class="dash-sidebar-subtitle">โรงพยาบาลหนองพอก</span>
                    </span>
                </a>
                <button type="button" class="dash-icon-button !h-10 !w-10 bg-hospital-mist" data-dashboard-sidebar-close aria-label="ปิดเมนู">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <nav class="dash-sidebar-menu">
                <?php render_dashboard_sidebar_links($currentPage); ?>
            </nav>

            <a href="../auth/logout.php" class="dash-sidebar-logout is-mobile">
                <i class="bi bi-box-arrow-right"></i>
                ออกจากระบบ
            </a>
        </div>
    </aside>
    <?php
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
                        <?php render_notification_bell([
                            'unread_count' => $notificationCount,
                            'items' => $recentNotifications,
                            'csrf' => $notificationCsrf,
                        ], 'btn btn-light rounded-pill position-relative'); ?>
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

