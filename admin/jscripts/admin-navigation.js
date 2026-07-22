(() => {
    'use strict';

    const menu = document.getElementById('scrollayer');
    const toggle = document.querySelector('.admin-menu-toggle');
    const backdrop = document.querySelector('.admin-nav-backdrop');

    if (!menu || !toggle || !backdrop) {
        return;
    }

    const mobile = window.matchMedia('(max-width: 900px)');
    document.documentElement.classList.add('admin-menu-ready');

    const setExpanded = (expanded) => {
        document.body.classList.toggle('admin-nav-open', expanded);
        if (!mobile.matches) {
            document.body.classList.toggle('admin-nav-collapsed', !expanded);
        }
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    const menuIsExpanded = () => mobile.matches
        ? document.body.classList.contains('admin-nav-open')
        : !document.body.classList.contains('admin-nav-collapsed');

    toggle.addEventListener('click', () => setExpanded(!menuIsExpanded()));
    backdrop.addEventListener('click', () => setExpanded(false));

    menu.addEventListener('click', (event) => {
        if (mobile.matches && event.target.closest('a')) {
            setExpanded(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches && menuIsExpanded()) {
            setExpanded(false);
            toggle.focus();
        }
    });

    mobile.addEventListener('change', () => {
        document.body.classList.remove('admin-nav-open', 'admin-nav-collapsed');
        toggle.setAttribute('aria-expanded', mobile.matches ? 'false' : 'true');
    });

    if (mobile.matches) {
        setExpanded(false);
    }
})();
