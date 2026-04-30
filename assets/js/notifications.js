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

                return ''
                    + '<div class="notification-item' + unreadClass + '" data-notification-item>'
                    + '  <button type="button" class="notification-link" data-notification-open data-notification-id="' + escapeHtml(item.id) + '">'
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

        function setCount(count) {
            const safeCount = Math.max(0, Number(count || 0));
            countBadge.textContent = safeCount > 99 ? '99+' : String(safeCount);
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
                const ok = await markRead(itemButton.getAttribute('data-notification-id'));
                if (ok) {
                    fetchRecent();
                }
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
    }

    window.AppNotifications = { init: initNotifications };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initNotifications();
        });
    } else {
        initNotifications();
    }
})(window, document);
