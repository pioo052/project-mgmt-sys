/* URIMS · Sidebar collapse and mobile drawer */
(function () {
    'use strict';

    var STORAGE_KEY = 'urims-sidebar';
    var body = document.body;
    var mobileQuery = window.matchMedia('(max-width: 1024px)');
    var backdrop = document.querySelector('.sidebar-backdrop');
    var openButton = document.querySelector('[data-sidebar-open]');
    var collapseButton = document.querySelector('[data-sidebar-collapse]');

    function isMobile() {
        return mobileQuery.matches;
    }

    function storeCollapsed(collapsed) {
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? 'collapsed' : 'expanded');
        } catch (error) {
            /* ignore */
        }
    }

    function setCollapsed(collapsed) {
        body.classList.toggle('sidebar-collapsed', collapsed);
        if (collapseButton) {
            collapseButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            collapseButton.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            collapseButton.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }
        storeCollapsed(collapsed);
    }

    function setDrawer(open, returnFocus) {
        body.classList.toggle('sidebar-open', open);
        if (openButton) {
            openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (backdrop) {
            if (open) {
                backdrop.hidden = false;
                window.requestAnimationFrame(function () {
                    backdrop.classList.add('is-visible');
                });
            } else {
                backdrop.classList.remove('is-visible');
                window.setTimeout(function () {
                    if (!body.classList.contains('sidebar-open')) {
                        backdrop.hidden = true;
                    }
                }, 200);
            }
        }
        if (open) {
            var firstLink = document.querySelector('.sidebar .nav-item');
            if (firstLink) {
                firstLink.focus({ preventScroll: true });
            }
        } else if (returnFocus && openButton) {
            openButton.focus({ preventScroll: true });
        }
    }

    if (collapseButton) {
        collapseButton.addEventListener('click', function () {
            setCollapsed(!body.classList.contains('sidebar-collapsed'));
        });
    }

    if (openButton) {
        openButton.addEventListener('click', function () {
            setDrawer(!body.classList.contains('sidebar-open'));
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setDrawer(false, true);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && body.classList.contains('sidebar-open')) {
            setDrawer(false, true);
        }
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.sidebar a');
        if (link && isMobile()) {
            setDrawer(false, false);
        }
    });

    function syncViewport() {
        if (!isMobile()) {
            setDrawer(false, false);
        }
    }

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', syncViewport);
    } else if (typeof mobileQuery.addListener === 'function') {
        mobileQuery.addListener(syncViewport);
    }

    // Keep the active module visible in long navigation lists.
    var active = document.querySelector('.sidebar .nav-item.is-active');
    if (active && typeof active.scrollIntoView === 'function') {
        var nav = document.querySelector('.sidebar__nav');
        if (nav) {
            var activeRect = active.getBoundingClientRect();
            var navRect = nav.getBoundingClientRect();
            if (activeRect.top < navRect.top || activeRect.bottom > navRect.bottom) {
                active.scrollIntoView({ block: 'center' });
            }
        }
    }
})();
