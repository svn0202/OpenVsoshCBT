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

    const menuGroups = Array.from(menu.querySelectorAll('li')).filter((item) => {
        const trigger = item.firstElementChild;
        return trigger && trigger.nextElementSibling?.tagName === 'UL';
    });

    const setGroupExpanded = (item, expanded) => {
        const trigger = item.firstElementChild;
        item.classList.toggle('admin-menu-group-open', expanded);
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    menuGroups.forEach((item, index) => {
        const trigger = item.firstElementChild;
        const submenu = trigger.nextElementSibling;
        const submenuId = `admin-submenu-${index + 1}`;
        const initiallyExpanded = trigger.classList.contains('active')
            || trigger.getAttribute('aria-current') === 'page'
            || submenu.querySelector('[aria-current="page"]') !== null;

        item.classList.add('admin-menu-group');
        submenu.id = submenuId;
        trigger.setAttribute('aria-controls', submenuId);
        trigger.setAttribute('aria-expanded', initiallyExpanded ? 'true' : 'false');

        if (trigger.tagName !== 'A') {
            trigger.setAttribute('role', 'button');
            trigger.setAttribute('tabindex', '0');
        }

        item.classList.toggle('admin-menu-group-open', initiallyExpanded);

        const toggleGroup = (event) => {
            event.preventDefault();
            const expanded = trigger.getAttribute('aria-expanded') === 'true';
            menuGroups.forEach((otherItem) => {
                if (otherItem !== item) {
                    setGroupExpanded(otherItem, false);
                }
            });
            setGroupExpanded(item, !expanded);
        };

        trigger.addEventListener('click', toggleGroup);
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                toggleGroup(event);
            }
        });
    });

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
        const link = event.target.closest('a');
        if (mobile.matches && link && !link.hasAttribute('aria-controls')) {
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
