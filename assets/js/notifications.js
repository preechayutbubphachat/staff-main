(function (window, document) {
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderSection(label, items, unread) {
        if (!Array.isArray(items) || !items.length) {
            return '';
        }

        return ''
            + '<div class="notification-section-label">' + escapeHtml(label) + '</div>'
            + '<div class="notification-section">'
            + items.map(function (item) {
                const unreadClass = unread ? ' notification-item--unread' : '';
                const unreadDot = unread ? '<span class="notification-item-dot" aria-hidden="true"></span>' : '<span class="notification-read-badge">อ่านแล้ว</span>';
                const readAction = unread
                    ? '<button type="button" class="notification-mark-one" data-notification-read data-notification-id="' + escapeHtml(item.id) + '">อ่านแล้ว</button>'
                    : '';

                const itemUrl = escapeHtml(item.target_url || '');
                const itemType = escapeHtml(item.type || '');
                return ''
                    + '<div class="notification-item' + unreadClass + '" data-notification-item>'
                    + '  <button type="button" class="notification-link" data-notification-open data-notification-id="' + escapeHtml(item.id) + '" data-notification-url="' + itemUrl + '" data-notification-type="' + itemType + '">'
                    + '      <span class="notification-item-head">'
                    + '          <span class="notification-item-title" title="' + escapeHtml(item.title || '') + '">' + escapeHtml(item.title || '') + '</span>'
                    +            unreadDot
                    + '      </span>'
                    + '      <span class="notification-item-message" title="' + escapeHtml(item.message || '') + '">' + escapeHtml(item.message || '') + '</span>'
                    + '      <span class="notification-item-time">' + escapeHtml(item.created_at_label || '') + '</span>'
                    + '  </button>'
                    + readAction
                    + '</div>';
            }).join('')
            + '</div>';
    }

    function formatBadgeCount(count) {
        const safeCount = Math.max(0, Number(count || 0));
        return safeCount > 99 ? '99+' : String(safeCount);
    }

    function updateSidebarNotificationBadge(moduleKey, count) {
        if (!moduleKey) {
            return;
        }

        const safeCount = Math.max(0, Number(count || 0));
        document.querySelectorAll('[data-notification-sidebar-badge="' + moduleKey + '"]').forEach(function (badge) {
            badge.textContent = formatBadgeCount(safeCount);
            badge.hidden = safeCount <= 0;
            badge.classList.toggle('d-none', safeCount <= 0);
        });
    }

    function updateSidebarNotificationBadges(counts) {
        if (!counts || typeof counts !== 'object') {
            return;
        }

        document.querySelectorAll('[data-notification-sidebar-badge]').forEach(function (badge) {
            const moduleKey = badge.getAttribute('data-notification-sidebar-badge') || '';
            updateSidebarNotificationBadge(moduleKey, counts[moduleKey] || 0);
        });
    }

    function updateBellNotificationCount(count) {
        const safeCount = Math.max(0, Number(count || 0));
        document.querySelectorAll('[data-notification-root]').forEach(function (root) {
            const toggle = root.querySelector('[data-notification-toggle]');
            const countBadge = root.querySelector('[data-notification-count]');
            if (!toggle || !countBadge) {
                return;
            }
            countBadge.textContent = formatBadgeCount(safeCount);
            countBadge.classList.toggle('d-none', safeCount <= 0);
            countBadge.hidden = safeCount <= 0;
            toggle.classList.toggle('has-unread', safeCount > 0);
            toggle.classList.toggle('notification-bell--active', safeCount > 0);
        });
    }

    function initNotificationRoot(root, options) {
        if (!root || root.dataset.notificationReady === '1') {
            return;
        }

        const toggle = root.querySelector('[data-notification-toggle]');
        const menu = root.querySelector('[data-notification-menu]');
        const list = root.querySelector('[data-notification-list]');
        const countBadge = root.querySelector('[data-notification-count]');
        const markAllButton = root.querySelector('[data-notification-mark-all]');

        if (!toggle || !menu || !list || !countBadge) {
            return;
        }

        root.dataset.notificationReady = '1';

        const config = options || {};
        const listUrl = config.listUrl || root.dataset.listUrl || '';
        const countUrl = config.countUrl || root.dataset.countUrl || '';
        const markReadUrl = config.markReadUrl || root.dataset.markReadUrl || '';
        const markAllUrl = config.markAllUrl || root.dataset.markAllUrl || '';
        const csrfToken = config.csrfToken || root.dataset.csrfToken || '';
        const pollMs = Math.max(15000, Number(config.pollMs || root.dataset.pollMs || 20000));
        let isBusy = false;

        // Type-to-URL fallback map (mirrors app_notification_event_matrix in PHP).
        // Used when target_url is empty or not stored yet.
        const TYPE_URL_MAP = {
            time_log_approved:            'time.php',
            time_log_rejected:            'time.php',
            approval_queue_pending:       'approval_queue.php?status=pending',
            approval_queue_ready:         'approval_queue.php?status=pending',
            permission_changed:           'profile.php',
            profile_updated_by_admin:     'profile.php',
            system_notice:                'notifications.php',
            report_ready:                 'my_reports.php',
            monthly_schedule_published:   'my-shifts.php',
            swap_request_created:         'shift-swap-requests.php',
            swap_target_confirmed:        'shift-swap-requests.php',
            swap_target_rejected:         'shift-swap-requests.php',
            swap_manager_approved:        'shift-swap-requests.php',
            swap_manager_rejected:        'shift-swap-requests.php',
            swap_cancelled:               'shift-swap-requests.php',
            shift_swap_request_created:   'shift-swap-requests.php',
            shift_swap_target_confirmed:  'shift-swap-requests.php',
            shift_swap_target_rejected:   'shift-swap-requests.php',
            shift_swap_manager_approved:  'shift-swap-requests.php',
            shift_swap_manager_rejected:  'shift-swap-requests.php',
            shift_swap_cancelled:         'shift-swap-requests.php',
        };
        const FALLBACK_URL = 'notifications.php';

        function resolveNotificationUrl(url, type) {
            if (url && url.trim() !== '') {
                return url.trim();
            }
            if (type && TYPE_URL_MAP[type]) {
                return TYPE_URL_MAP[type];
            }
            return FALLBACK_URL;
        }

        function setCount(count) {
            const safeCount = Math.max(0, Number(count || 0));
            countBadge.textContent = formatBadgeCount(safeCount);
            countBadge.classList.toggle('d-none', safeCount <= 0);
            countBadge.hidden = safeCount <= 0;
            toggle.classList.toggle('has-unread', safeCount > 0);
            toggle.classList.toggle('notification-bell--active', safeCount > 0);
        }

        function setOpen(open) {
            root.classList.toggle('is-open', open);
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                fetchRecent();
            }
        }

        function renderItems(items) {
            if (!Array.isArray(items) || !items.length) {
                list.innerHTML = '<div class="notification-empty" data-notification-empty>ยังไม่มีการแจ้งเตือนใหม่</div>';
                return;
            }

            const unreadItems = items.filter(function (item) {
                return !item.is_read;
            });
            const readItems = items.filter(function (item) {
                return !!item.is_read;
            });

            list.innerHTML = ''
                + renderSection('ยังไม่ได้อ่าน', unreadItems, true)
                + renderSection('อ่านแล้ว', readItems, false);
        }

        async function fetchRecent() {
            if (!listUrl || isBusy) {
                return;
            }

            isBusy = true;
            try {
                const response = await fetch(listUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const payload = await response.json();
                if (response.ok && payload.success) {
                    renderItems(payload.items || []);
                    setCount(payload.unread_count || 0);
                    updateSidebarNotificationBadges(payload.sidebar_counts || {});
                }
            } catch (error) {
                list.innerHTML = '<div class="notification-empty notification-empty--error">ไม่สามารถโหลดการแจ้งเตือนได้</div>';
            } finally {
                isBusy = false;
            }
        }

        async function fetchCount() {
            if (!countUrl || document.hidden) {
                return;
            }

            try {
                const response = await fetch(countUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const payload = await response.json();
                if (response.ok && payload.success) {
                    setCount(payload.count || 0);
                    updateSidebarNotificationBadges(payload.sidebar_counts || {});
                }
            } catch (error) {
                // Keep the topbar quiet when polling fails.
            }
        }

        async function markRead(notificationId) {
            if (!markReadUrl || !notificationId) {
                return false;
            }

            const body = new URLSearchParams();
            body.set('id', String(notificationId));
            if (csrfToken) {
                body.set('_csrf', csrfToken);
            }

            try {
                const response = await fetch(markReadUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString(),
                    keepalive: true
                });
                const payload = await response.json();
                if (response.ok && payload.success) {
                    setCount(payload.unread_count || 0);
                    updateSidebarNotificationBadges(payload.sidebar_counts || {});
                    return true;
                }
            } catch (error) {
                return false;
            }

            return false;
        }

        async function markAllRead() {
            if (!markAllUrl) {
                return;
            }

            const previousLabel = markAllButton ? markAllButton.textContent : '';
            if (markAllButton) {
                markAllButton.disabled = true;
                markAllButton.textContent = 'กำลังอ่าน...';
            }

            try {
                const response = await fetch(markAllUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: csrfToken ? '_csrf=' + encodeURIComponent(csrfToken) : ''
                });
                const payload = await response.json();
                if (response.ok && payload.success) {
                    setCount(payload.unread_count || 0);
                    updateSidebarNotificationBadges(payload.sidebar_counts || {});
                    await fetchRecent();
                }
            } catch (error) {
                // Keep silent; the next poll will restore current state.
            } finally {
                if (markAllButton) {
                    markAllButton.disabled = false;
                    markAllButton.textContent = previousLabel || 'อ่านทั้งหมด';
                }
            }
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setOpen(!root.classList.contains('is-open'));
        });

        list.addEventListener('click', async function (event) {
            const readButton = event.target.closest('[data-notification-read]');
            if (readButton) {
                event.preventDefault();
                const ok = await markRead(readButton.getAttribute('data-notification-id'));
                if (ok) {
                    fetchRecent();
                }
                return;
            }

            const itemButton = event.target.closest('[data-notification-open]');
            if (itemButton) {
                event.preventDefault();
                const notifId  = itemButton.getAttribute('data-notification-id');
                const notifUrl = itemButton.getAttribute('data-notification-url') || '';
                const notifType = itemButton.getAttribute('data-notification-type') || '';
                const destination = resolveNotificationUrl(notifUrl, notifType);

                // Mark as read (fire-and-forget) — navigate regardless of outcome.
                markRead(notifId).then(function (ok) {
                    if (ok) {
                        // Update badge without re-rendering the dropdown (we're navigating away).
                        // fetchRecent() is skipped to avoid a flash before navigation.
                    }
                }).catch(function () {
                    // Silently ignore; user still navigates.
                }).finally(function () {
                    window.location.href = destination;
                });
            }
        });

        if (markAllButton) {
            markAllButton.addEventListener('click', function (event) {
                event.preventDefault();
                markAllRead();
            });
        }

        document.addEventListener('click', function (event) {
            if (!root.classList.contains('is-open')) {
                return;
            }

            if (!root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && root.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                fetchCount();
            }
        });

        window.setInterval(fetchCount, pollMs);
        fetchCount();
    }

    function initNotifications(options) {
        const roots = Array.from(document.querySelectorAll('[data-notification-root]'));
        roots.forEach(function (root) {
            initNotificationRoot(root, options);
        });
        initPageNotificationReadTracking();
    }

    function notificationHighlightStorageKey(moduleKey, target) {
        const targetKey = target && target.notification_id
            ? 'notification-' + target.notification_id
            : [
                target && target.target_type ? target.target_type : 'section',
                target && target.target_id ? target.target_id : (target && target.section ? target.section : 'module')
            ].join('-');

        return 'notification-focus:' + window.location.pathname + ':' + moduleKey + ':' + targetKey;
    }

    function hasShownNotificationHighlight(moduleKey, target) {
        try {
            return window.sessionStorage.getItem(notificationHighlightStorageKey(moduleKey, target)) === '1';
        } catch (error) {
            return false;
        }
    }

    function rememberNotificationHighlight(moduleKey, target) {
        try {
            window.sessionStorage.setItem(notificationHighlightStorageKey(moduleKey, target), '1');
        } catch (error) {
            // sessionStorage can be blocked; highlighting still works for this page load.
        }
    }

    function clearNotificationFocusParams() {
        const url = new URL(window.location.href);
        const params = ['highlight', 'notification_id', 'request_id', 'swap_request_id', 'focus'];
        let changed = false;
        params.forEach(function (param) {
            if (url.searchParams.has(param)) {
                url.searchParams.delete(param);
                changed = true;
            }
        });
        if (changed && window.history && typeof window.history.replaceState === 'function') {
            window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
        }
    }

    function buildTargetFromUrl(moduleKey) {
        const params = new URLSearchParams(window.location.search || '');
        const highlightId = params.get('highlight') || params.get('swap_request_id') || params.get('request_id');
        const notificationId = params.get('notification_id') || '';

        if (!highlightId && !notificationId) {
            return null;
        }

        const numericTarget = Number(highlightId || 0);
        if (moduleKey === 'shift_swaps' && numericTarget > 0) {
            return {
                notification_id: notificationId ? Number(notificationId) : 0,
                module: moduleKey,
                target_type: 'shift_swap_request',
                target_id: numericTarget,
                section: 'history',
                selector: '[data-notification-target="shift-swap-request-' + numericTarget + '"]'
            };
        }

        return {
            notification_id: notificationId ? Number(notificationId) : 0,
            module: moduleKey,
            target_type: 'module',
            target_id: null,
            section: moduleKey,
            selector: ''
        };
    }

    function findNotificationFocusElement(moduleKey, target) {
        if (!moduleKey || !target) {
            return null;
        }

        if (target.selector) {
            try {
                const bySelector = document.querySelector(target.selector);
                if (bySelector) {
                    return bySelector;
                }
            } catch (error) {
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('Invalid notification target selector.', error);
                }
            }
        }

        if (target.section) {
            const bySection = document.querySelector(
                '[data-notification-module="' + moduleKey + '"][data-notification-section="' + target.section + '"]'
            );
            if (bySection) {
                return bySection;
            }
        }

        return document.querySelector('[data-notification-module="' + moduleKey + '"]');
    }

    function highlightNotificationTarget(moduleKey, target) {
        if (!moduleKey || !target || hasShownNotificationHighlight(moduleKey, target)) {
            return false;
        }

        const element = findNotificationFocusElement(moduleKey, target);
        if (!element) {
            return false;
        }

        rememberNotificationHighlight(moduleKey, target);
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.classList.remove('is-fading');
        element.classList.add('notification-focus-highlight');
        window.setTimeout(function () {
            element.classList.add('is-fading');
            window.setTimeout(function () {
                element.classList.remove('notification-focus-highlight', 'is-fading');
            }, 700);
        }, 7800);

        return true;
    }

    async function fetchNotificationTargets(endpoint, moduleKey, csrfToken) {
        if (!endpoint || !moduleKey) {
            return [];
        }

        const body = new URLSearchParams();
        body.set('module', moduleKey);
        if (csrfToken) {
            body.set('_csrf', csrfToken);
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });
            const payload = await response.json();
            if (response.ok && payload.success) {
                updateSidebarNotificationBadges(payload.sidebar_counts || {});
                updateBellNotificationCount(payload.unread_count || 0);
                return Array.isArray(payload.targets) ? payload.targets : [];
            }
        } catch (error) {
            if (window.console && typeof window.console.warn === 'function') {
                window.console.warn('Unable to fetch notification focus targets.', error);
            }
        }

        return [];
    }

    async function initPageNotificationReadTracking() {
        const pageRoot = document.querySelector('[data-notification-page-key][data-notification-mark-module-url]');
        if (!pageRoot || pageRoot.dataset.notificationPageReadReady === '1') {
            return;
        }

        const moduleKey = pageRoot.getAttribute('data-notification-page-key') || '';
        const endpoint = pageRoot.getAttribute('data-notification-mark-module-url') || '';
        const targetsEndpoint = pageRoot.getAttribute('data-notification-targets-url') || '';
        const csrfToken = pageRoot.getAttribute('data-notification-csrf') || '';
        if (!moduleKey || !endpoint) {
            return;
        }

        pageRoot.dataset.notificationPageReadReady = '1';

        const urlTarget = buildTargetFromUrl(moduleKey);
        let highlighted = false;
        if (urlTarget) {
            highlighted = highlightNotificationTarget(moduleKey, urlTarget);
            clearNotificationFocusParams();
        }

        if (!highlighted) {
            const targets = await fetchNotificationTargets(targetsEndpoint, moduleKey, csrfToken);
            if (targets.length) {
                highlightNotificationTarget(moduleKey, targets[0]);
            }
        }

        const body = new URLSearchParams();
        body.set('module', moduleKey);
        if (csrfToken) {
            body.set('_csrf', csrfToken);
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });
            const payload = await response.json();
            if (response.ok && payload.success) {
                updateSidebarNotificationBadges(payload.sidebar_counts || {});
                updateBellNotificationCount(payload.unread_count || 0);
            }
        } catch (error) {
            if (window.console && typeof window.console.warn === 'function') {
                window.console.warn('Unable to mark page notifications as read.', error);
            }
        }
    }

    window.AppNotifications = {
        init: initNotifications,
        updateSidebarNotificationBadge: updateSidebarNotificationBadge,
        updateSidebarNotificationBadges: updateSidebarNotificationBadges,
        updateBellNotificationCount: updateBellNotificationCount
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initNotifications();
        });
    } else {
        initNotifications();
    }
})(window, document);
