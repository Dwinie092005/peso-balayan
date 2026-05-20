/**
 * sidebar.js
 * Handles sidebar toggle on mobile, submenu accordion, and active link detection.
 */

(function () {
    'use strict';

    const sidebar       = document.getElementById('sidebar');
    const toggleBtn     = document.getElementById('sidebarToggle');
    const overlay       = document.getElementById('sidebarOverlay');
    const subMenuLinks  = document.querySelectorAll('.sidebar__nav-item--has-sub > .sidebar__nav-link');

    if (!sidebar) return;

    /**
     * Open or close the sidebar (mobile only).
     * @param {boolean} open
     */
    function setSidebarOpen(open) {
        sidebar.classList.toggle('sidebar--open', open);
        if (overlay) overlay.classList.toggle('sidebar-overlay--visible', open);
        document.body.classList.toggle('sidebar-is-open', open);

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', String(open));
        }
    }

    /**
     * Toggle a submenu open/closed.
     * @param {Element} linkEl - The parent nav link
     */
    function toggleSubMenu(linkEl) {
        const parent  = linkEl.closest('.sidebar__nav-item--has-sub');
        const subMenu = parent ? parent.querySelector('.sidebar__sub-menu') : null;

        if (!subMenu) return;

        const isOpen = parent.classList.contains('sidebar__nav-item--expanded');

        // Collapse all other open submenus
        document.querySelectorAll('.sidebar__nav-item--has-sub.sidebar__nav-item--expanded').forEach(function (el) {
            if (el !== parent) {
                el.classList.remove('sidebar__nav-item--expanded');
                const sub = el.querySelector('.sidebar__sub-menu');
                if (sub) sub.style.maxHeight = '0';
            }
        });

        // Toggle current
        parent.classList.toggle('sidebar__nav-item--expanded', !isOpen);
        subMenu.style.maxHeight = !isOpen ? subMenu.scrollHeight + 'px' : '0';
    }

    /**
     * Mark active sidebar links based on current path.
     */
    function setActiveLinks() {
        const currentPath = window.location.pathname;

        document.querySelectorAll('.sidebar__nav-link, .sidebar__sub-link').forEach(function (link) {
            const href = link.getAttribute('href');
            if (!href) return;

            const isActive = href === currentPath || (href !== '/' && currentPath.startsWith(href));
            link.classList.toggle('active', isActive);

            if (isActive) {
                const parentItem = link.closest('.sidebar__nav-item--has-sub');
                if (parentItem) {
                    parentItem.classList.add('sidebar__nav-item--expanded');
                    const sub = parentItem.querySelector('.sidebar__sub-menu');
                    if (sub) sub.style.maxHeight = sub.scrollHeight + 'px';
                }
            }
        });
    }

    // ── EVENT BINDINGS ──────────────────────────────────

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isOpen = sidebar.classList.contains('sidebar--open');
            setSidebarOpen(!isOpen);
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            setSidebarOpen(false);
        });
    }

    subMenuLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            toggleSubMenu(link);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
            setSidebarOpen(false);
        }
    });

    // ── INIT ─────────────────────────────────────────────
    setActiveLinks();

})();
