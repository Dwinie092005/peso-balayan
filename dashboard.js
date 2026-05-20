/**
 * PESO Balayan IMIS — Dashboard JS
 * File: public/js/dashboard.js
 *
 * Handles:
 *  - Mobile sidebar open / close
 *  - Sidebar overlay click-to-close
 *  - Alert auto-dismiss
 *  - Active nav-link highlighting
 */

(function () {
    'use strict';

    /* ── Elements ──────────────────────────────────────────── */
    var sidebar       = document.getElementById('sidebar');
    var overlay       = document.getElementById('sidebar-overlay');
    var menuToggleBtn = document.getElementById('menu-toggle');

    /* ── Mobile sidebar ────────────────────────────────────── */
    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) {
            overlay.classList.add('visible');
            overlay.setAttribute('aria-hidden', 'false');
        }
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) {
            overlay.classList.remove('visible');
            overlay.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
    }

    if (menuToggleBtn) {
        menuToggleBtn.addEventListener('click', openSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    /* Close sidebar on ESC */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });

    /* ── Active nav link ───────────────────────────────────── */
    var currentPath = window.location.pathname;
    var navLinks    = document.querySelectorAll('.nav-link');

    navLinks.forEach(function (link) {
        var href = link.getAttribute('href') || '';
        if (href && href !== '#' && currentPath.indexOf(href) === 0) {
            link.classList.add('active');
        }
    });

    /* ── Alert auto-dismiss ────────────────────────────────── */
    var alerts = document.querySelectorAll('.alert[id]');
    alerts.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity    = '0';
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 420);
        }, 5000);
    });

    /* ── Sidebar overlay CSS (injected to avoid extra CSS file) */
    var style = document.createElement('style');
    style.textContent = [
        '.sidebar-overlay{',
        '  display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:150;',
        '}',
        '.sidebar-overlay.visible{display:block;}',
        '@media(min-width:901px){.sidebar-overlay{display:none!important;}}',
    ].join('');
    document.head.appendChild(style);

})();
