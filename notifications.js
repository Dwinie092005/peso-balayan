/**
 * notifications.js
 * Handles notification dropdown toggle, mark-as-read, and auto-refresh.
 */

(function () {
    'use strict';

    const POLL_INTERVAL_MS = 60000; // refresh every 60 seconds
    const API_COUNT_URL    = '/notifications/count';
    const API_MARK_URL     = '/notifications/mark-read';
    const API_MARK_ALL_URL = '/notifications/mark-all-read';

    const trigger   = document.getElementById('notifTrigger');
    const panel     = document.getElementById('notifPanel');
    const dropdown  = document.getElementById('notifDropdown');

    if (!trigger || !panel) return;

    /**
     * Toggle dropdown open/closed.
     */
    function togglePanel(open) {
        const isOpen = open !== undefined ? open : panel.getAttribute('aria-hidden') === 'true';
        trigger.setAttribute('aria-expanded', String(isOpen));
        panel.setAttribute('aria-hidden',    String(!isOpen));
    }

    /**
     * Close panel on outside click.
     */
    function handleOutsideClick(event) {
        if (dropdown && !dropdown.contains(event.target)) {
            togglePanel(false);
        }
    }

    /**
     * Mark single notification as read via AJAX.
     * @param {number} notifId
     * @param {Element} itemEl
     */
    function markAsRead(notifId, itemEl) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) return;

        fetch(API_MARK_URL, {
            method : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken.getAttribute('content'),
            },
            body: JSON.stringify({ id: notifId }),
        })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
        .then(function () {
            itemEl.classList.remove('notif-dropdown__item--unread');
            const dot = itemEl.querySelector('.notif-dropdown__unread-dot');
            if (dot) dot.remove();
            updateBadgeCount();
        })
        .catch(function (err) {
            console.warn('[Notifications] markAsRead failed:', err);
        });
    }

    /**
     * Fetch current unread count and update badge.
     */
    function updateBadgeCount() {
        fetch(API_COUNT_URL, { method: 'GET' })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
        .then(function (data) {
            const badge = trigger.querySelector('.notif-dropdown__badge');
            const count = parseInt(data.count, 10) || 0;

            if (count > 0) {
                if (badge) {
                    badge.textContent = count > 99 ? '99+' : count;
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.className  = 'notif-dropdown__badge';
                    newBadge.textContent = count > 99 ? '99+' : count;
                    newBadge.setAttribute('aria-label', count + ' unread notifications');
                    trigger.appendChild(newBadge);
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(function (err) {
            console.warn('[Notifications] updateBadgeCount failed:', err);
        });
    }

    /**
     * Mark all as read.
     */
    function handleMarkAllRead(event) {
        event.preventDefault();
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) return;

        fetch(API_MARK_ALL_URL, {
            method : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken.getAttribute('content'),
            },
        })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
        .then(function () {
            panel.querySelectorAll('.notif-dropdown__item--unread').forEach(function (el) {
                el.classList.remove('notif-dropdown__item--unread');
                const dot = el.querySelector('.notif-dropdown__unread-dot');
                if (dot) dot.remove();
            });
            updateBadgeCount();
        })
        .catch(function (err) {
            console.warn('[Notifications] markAllRead failed:', err);
        });
    }

    // ── EVENT BINDINGS ──────────────────────────────────

    trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        togglePanel();
    });

    document.addEventListener('click', handleOutsideClick);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            togglePanel(false);
        }
    });

    panel.addEventListener('click', function (event) {
        const item = event.target.closest('.notif-dropdown__item');
        if (item && item.classList.contains('notif-dropdown__item--unread')) {
            markAsRead(parseInt(item.dataset.id, 10), item);
        }

        const markAll = event.target.closest('[data-action="mark-all-read"]');
        if (markAll) {
            handleMarkAllRead(event);
        }
    });

    // ── POLLING ─────────────────────────────────────────
    setInterval(updateBadgeCount, POLL_INTERVAL_MS);

})();
