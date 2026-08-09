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

    const interactiveSelector = 'a, button, input, select, textarea, label';
    document.querySelectorAll('[data-record-href]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest(interactiveSelector) || window.getSelection()?.toString()) {
                return;
            }
            window.location.assign(row.dataset.recordHref);
        });
    });

    document.querySelectorAll('[data-select-all]').forEach((toggle) => {
        const prefix = toggle.dataset.selectAll;
        const table = toggle.closest('table');
        const form = toggle.closest('form');
        const toolbar = form?.querySelector('[data-bulk-toolbar]');
        const counter = toolbar?.querySelector('[data-selected-count]');
        const checkboxes = Array.from(table?.querySelectorAll(`tbody input[type="checkbox"][name^="${prefix}"]`) || []);
        const updateSelection = () => {
            const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
            toggle.checked = checkboxes.length > 0 && selected === checkboxes.length;
            toggle.indeterminate = selected > 0 && selected < checkboxes.length;
            if (counter) {
                counter.textContent = String(selected);
            }
            toolbar?.classList.toggle('has-selection', selected > 0);
        };
        toggle.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = toggle.checked;
            });
            updateSelection();
        });
        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));
        updateSelection();
    });

    const editor = document.getElementById('form_testeditor');
    const editorState = editor?.querySelector('[data-editor-save-state]');
    if (editor && editorState) {
        let dirty = false;
        const setDirty = () => {
            dirty = true;
            editorState.textContent = 'Есть несохранённые изменения';
            editorState.dataset.state = 'dirty';
        };
        editor.addEventListener('input', setDirty);
        editor.addEventListener('change', setDirty);
        editor.addEventListener('submit', () => {
            dirty = false;
            editorState.textContent = 'Сохранение…';
            editorState.dataset.state = 'saving';
        });
        window.addEventListener('beforeunload', (event) => {
            if (!dirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        });
    }

    document.querySelectorAll('[data-relative-time]').forEach((element) => {
        const updateRelativeTime = () => {
            const timestamp = new Date(element.dateTime.replace(' ', 'T')).getTime();
            if (!Number.isFinite(timestamp)) {
                return;
            }
            const seconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000));
            if (seconds < 60) {
                element.textContent = `${seconds} сек. назад`;
            } else if (seconds < 3600) {
                element.textContent = `${Math.floor(seconds / 60)} мин. назад`;
            } else {
                element.textContent = `${Math.floor(seconds / 3600)} ч. назад`;
            }
        };
        updateRelativeTime();
        window.setInterval(updateRelativeTime, 15000);
    });

    const liveMonitor = document.querySelector('[data-monitor-refresh]');
    if (liveMonitor) {
        const monitorBody = document.querySelector('.monitor-table tbody');
        if (monitorBody) {
            Array.from(monitorBody.querySelectorAll('[data-monitor-priority]'))
                .sort((left, right) => Number(left.dataset.monitorPriority) - Number(right.dataset.monitorPriority))
                .forEach((row) => monitorBody.appendChild(row));
        }
        const countdown = liveMonitor.querySelector('[data-refresh-countdown]');
        const refreshNow = liveMonitor.querySelector('[data-refresh-now]');
        let remaining = Number(liveMonitor.dataset.monitorRefresh) || 30;
        const refresh = () => {
            if (document.visibilityState === 'visible' && !document.activeElement?.closest('.monitor-actions')) {
                window.location.reload();
            } else {
                remaining = 10;
            }
        };
        refreshNow?.addEventListener('click', () => window.location.reload());
        window.setInterval(() => {
            remaining -= 1;
            if (countdown) {
                countdown.textContent = `Следующее обновление через ${Math.max(0, remaining)} сек.`;
            }
            if (remaining <= 0) {
                refresh();
            }
        }, 1000);
    }
})();
