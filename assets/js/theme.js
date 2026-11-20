/* URIMS · Light / dark theme controller */
(function () {
    'use strict';

    var STORAGE_KEY = 'urims-theme';
    var root = document.documentElement;

    function preferredTheme() {
        try {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (stored === 'dark' || stored === 'light') {
                return stored;
            }
        } catch (error) {
            /* storage may be unavailable */
        }
        return 'light';
    }

    function updateControls(theme) {
        var buttons = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < buttons.length; i++) {
            var button = buttons[i];
            var next = theme === 'dark' ? 'light' : 'dark';
            button.setAttribute('aria-label', 'Switch to ' + next + ' mode');
            button.setAttribute('title', 'Switch to ' + next + ' mode');
            button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');

            var lightIcon = button.querySelector('[data-theme-icon-light]');
            var darkIcon = button.querySelector('[data-theme-icon-dark]');
            if (lightIcon) {
                lightIcon.hidden = theme === 'dark';
            }
            if (darkIcon) {
                darkIcon.hidden = theme !== 'dark';
            }
        }

        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', theme === 'dark' ? '#0F172A' : '#FFFFFF');
        }
    }

    function apply(theme, persist) {
        root.setAttribute('data-theme', theme);
        if (persist) {
            try {
                localStorage.setItem(STORAGE_KEY, theme);
            } catch (error) {
                /* ignore */
            }
        }
        updateControls(theme);
    }

    apply(preferredTheme(), false);

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-theme-toggle]');
        if (!toggle) {
            return;
        }
        var current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        apply(current === 'dark' ? 'light' : 'dark', true);
    });

    window.addEventListener('storage', function (event) {
        if (event.key === STORAGE_KEY && (event.newValue === 'dark' || event.newValue === 'light')) {
            apply(event.newValue, false);
        }
    });
})();
