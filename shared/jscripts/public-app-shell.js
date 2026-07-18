(function () {
    'use strict';

    var page = document.body;
    if (!page || !page.classList.contains('app-page')) {
        return;
    }

    var menuToggle = document.querySelector('.app-menu-toggle');
    var menuClose = document.querySelector('.app-menu-close');
    var menu = document.getElementById('scrollayer');
    var userToggle = document.querySelector('.tmf-user-toggle');
    var userClose = document.querySelector('.tmf-user-close');
    var scrim = document.querySelector('.app-menu-scrim');
    var themeToggle = document.querySelector('.tmf-theme-toggle');
    var text = {
        openMenu: page.dataset.openMenu || '',
        closeMenu: page.dataset.closeMenu || '',
        themeDark: page.dataset.themeDark || '',
        themeLight: page.dataset.themeLight || '',
        enableDarkTheme: page.dataset.enableDarkTheme || '',
        enableLightTheme: page.dataset.enableLightTheme || ''
    };

    function getSavedTheme() {
        try {
            return window.localStorage.getItem('openvsosh-theme') === 'dark' ? 'dark' : 'light';
        } catch (error) {
            return 'light';
        }
    }

    function applyTheme(theme) {
        var isDark = theme === 'dark';
        page.classList.toggle('theme-dark', isDark);
        page.classList.toggle('theme-light', !isDark);
        if (themeToggle) {
            themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            themeToggle.querySelector('i').textContent = isDark ? '☀' : '☾';
            themeToggle.querySelector('span').textContent = isDark ? text.themeLight : text.themeDark;
            themeToggle.title = isDark ? text.enableLightTheme : text.enableDarkTheme;
        }
    }

    applyTheme(getSavedTheme());

    function syncState() {
        var menuOpen = page.classList.contains('tmf-menu-open');
        var userOpen = page.classList.contains('tmf-user-open');
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', menuOpen ? 'true' : 'false');
            menuToggle.setAttribute('aria-label', menuOpen ? text.closeMenu : text.openMenu);
            menuToggle.setAttribute('title', menuOpen ? text.closeMenu : text.openMenu);
        }
        if (userToggle) {
            userToggle.setAttribute('aria-expanded', userOpen ? 'true' : 'false');
        }
    }

    function closeDrawers() {
        page.classList.remove('tmf-menu-open', 'tmf-user-open');
        syncState();
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            var open = !page.classList.contains('tmf-menu-open');
            closeDrawers();
            page.classList.toggle('tmf-menu-open', open);
            syncState();
        });
    }

    if (menuClose) {
        menuClose.addEventListener('click', closeDrawers);
    }

    if (menu) {
        menu.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeDrawers();
            }
        });
    }

    if (userToggle) {
        userToggle.addEventListener('click', function () {
            var open = !page.classList.contains('tmf-user-open');
            closeDrawers();
            page.classList.toggle('tmf-user-open', open);
            syncState();
        });
    }

    if (userClose) {
        userClose.addEventListener('click', closeDrawers);
    }

    if (scrim) {
        scrim.addEventListener('click', closeDrawers);
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var theme = page.classList.contains('theme-dark') ? 'light' : 'dark';
            applyTheme(theme);
            try {
                window.localStorage.setItem('openvsosh-theme', theme);
            } catch (error) {
                // The selected theme still applies for the current page.
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && (page.classList.contains('tmf-menu-open') || page.classList.contains('tmf-user-open'))) {
            closeDrawers();
            if (menuToggle) {
                menuToggle.focus();
            }
        }
    });

    syncState();
}());
