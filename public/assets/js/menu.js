/* menu.js — Sidebar toggle (desktop collapse + mobile open/close) */

document.addEventListener('DOMContentLoaded', function () {

    var sidebar   = document.getElementById('sidebar');
    var toggle    = document.getElementById('sidebarCollapse');       // desktop
    var mobileBtn = document.getElementById('sidebarCollapseMobile'); // mobile (top-header)
    var overlay   = document.getElementById('sidebarOverlay');

    if (!sidebar) return;

    /* ── Desktop: collapse / expand ─────────────────── */
    var saved = localStorage.getItem('sidebarCollapsed');
    if (saved === '1') sidebar.classList.add('collapsed');

    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed',
                sidebar.classList.contains('collapsed') ? '1' : '0');
        });
    }

    /* ── Mobile: open / close ───────────────────────── */
    function openMenu() {
        sidebar.classList.add('mobile-open');
        if (overlay) overlay.classList.add('active');
        document.body.classList.add('menu-open');
    }

    function closeMenu() {
        sidebar.classList.remove('mobile-open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('menu-open');
    }

    if (mobileBtn) mobileBtn.addEventListener('click', openMenu);
    if (overlay)   overlay.addEventListener('click', closeMenu);

    function isDesktopCollapsed() {
        return window.innerWidth >= 768 && sidebar.classList.contains('collapsed');
    }

    function closeHoverSubmenus() {
        sidebar.querySelectorAll('.menu-item.has-submenu.hover-open').forEach(function (item) {
            item.classList.remove('hover-open');
        });
    }

    function expandSidebarAndOpenSubmenu(toggleLink) {
        closeHoverSubmenus();

        sidebar.classList.remove('collapsed');
        localStorage.setItem('sidebarCollapsed', '0');

        var targetSelector = toggleLink.getAttribute('href');
        if (!targetSelector) return;

        var targetEl = document.querySelector(targetSelector);
        if (targetEl && window.bootstrap && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(targetEl, { toggle: false }).show();
        }
    }

    sidebar.querySelectorAll('.menu-item.has-submenu').forEach(function (item) {
        var toggleLink = item.querySelector('.submenu-toggle');

        if (toggleLink) {
            toggleLink.addEventListener('click', function (event) {
                if (!isDesktopCollapsed()) return;

                event.preventDefault();
                expandSidebarAndOpenSubmenu(toggleLink);
            });
        }

        item.addEventListener('mouseenter', function () {
            if (!isDesktopCollapsed() || !toggleLink) return;
            expandSidebarAndOpenSubmenu(toggleLink);
        });

        item.addEventListener('mouseleave', function () {
            item.classList.remove('hover-open');
        });

        item.addEventListener('focusin', function () {
            if (!isDesktopCollapsed() || !toggleLink) return;
            expandSidebarAndOpenSubmenu(toggleLink);
        });

        item.addEventListener('focusout', function (event) {
            if (item.contains(event.relatedTarget)) return;
            item.classList.remove('hover-open');
        });
    });

    sidebar.addEventListener('mouseleave', closeHoverSubmenus);

    window.addEventListener('resize', function () {
        if (!isDesktopCollapsed()) closeHoverSubmenus();
    });

    /* ── Close on ESC ───────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenu();
            closeHoverSubmenus();
        }
    });

    /* ── Auto-close mobile menu on link click ─────── */
    sidebar.querySelectorAll('.submenu-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth < 768) closeMenu();
        });
    });
});
