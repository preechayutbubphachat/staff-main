(function (window, document) {
    function initNotifications(options) {
        const config = options || {};
        const toggle = document.querySelector('[data-notification-toggle]');
        const list = document.querySelector('[data-notification-list]');
        const countBadge = document.querySelector('[data-notification-count]');
        const markAllButton = document.querySelector('[data-notification-mark-all]');

        if (!toggle || !list || !countBadge) {
            return;
        }

        const listUrl = config.listUrl || '';
        const countUrl = config.countUrl || '';
        const markReadUrl = config.markReadUrl || '';
        const markAllUrl = config.markAllUrl || '';
        const csrfToken = config.csrfToken || '';
        const pollMs = Math.max(15000, Number(config.pollMs || 20000));
        let pollTimer = null;
        let isBusy = false;

        function setCount(count) {
            const safeCount = Math.max(0, Number(count || 0));
            countBadge.textContent = safeCount > 99 ? '99+' : String(safeCount);
            countBadge.classList.toggle('d-none', safeCount <= 0);
            toggle.classList.toggle('has-unread', safeCount > 0);
            toggle.classList.toggle('notification-bell--active', safeCount > 0);
        }

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
                    const unreadDot = unread ? '<span class="notification-item-dot" aria-hidden="true"></span>' : '';
                    const readAction = unread
                        ? '<button type="button" class="btn btn-sm btn-link text-decoration-none notification-mark-one" data-notification-read data-notification-id="' + item.id + '">อ่านแล้ว</button>'
                        : '';

                    return ''
                        + '<div class="notification-item' + unreadClass + '" data-notification-item>'
                        + '  <a href="' + (item.target_url || 'notifications.php') + '" class="notification-link" data-notification-open data-notification-id="' + item.id + '">'
                        + '      <div class="notification-item-head">'
                        + '          <div class="notification-item-title" title="' + escapeHtml(item.title || '') + '">' + escapeHtml(item.title || '') + '</div>'
                        +              unreadDot
                        + '      </div>'
                        + '      <div class="notification-item-message" title="' + escapeHtml(item.message || '') + '">' + escapeHtml(item.message || '') + '</div>'
                        + '      <div class="notification-item-time">' + escapeHtml(item.created_at_label || '') + '</div>'
                        + '  </a>'
                        + readAction
                        + '</div>';
                }).join('')
                + '</div>';
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
                }
            } catch (error) {
                // keep silent to avoid noisy navbar UX
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
                }
            } catch (error) {
                // keep silent to avoid noisy navbar UX
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
                    await fetchRecent();
                }
            } catch (error) {
                // keep silent
            }
        }

        toggle.addEventListener('shown.bs.dropdown', function () {
            fetchRecent();
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

            const link = event.target.closest('[data-notification-open]');
            if (link) {
                event.preventDefault();
                const href = link.getAttribute('href') || 'notifications.php';
                await markRead(link.getAttribute('data-notification-id'));
                if (window.GlobalLoading && typeof window.GlobalLoading.showPageNavigationLoading === 'function') {
                    window.GlobalLoading.showPageNavigationLoading('กำลังเปิดการแจ้งเตือน...', { trigger: link });
                }
                window.location.href = href;
            }
        });

        if (markAllButton) {
            markAllButton.addEventListener('click', function (event) {
                event.preventDefault();
                markAllRead();
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                fetchCount();
            }
        });

        pollTimer = window.setInterval(function () {
            fetchCount();
        }, pollMs);

        fetchCount();
    }

    window.AppNotifications = { init: initNotifications };
})(window, document);
