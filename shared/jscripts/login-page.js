(function () {
    'use strict';

    var menuToggle = document.querySelector('.login-menu-toggle');
    var menu = document.getElementById('scrollayer');
    var body = document.body;
    var text = {
        openMenu: body.dataset.openMenu || '',
        closeMenu: body.dataset.closeMenu || '',
        showPassword: body.dataset.showPassword || '',
        hidePassword: body.dataset.hidePassword || ''
    };

    function setMenuOpen(open) {
        if (!menuToggle || !menu) {
            return;
        }
        body.classList.toggle('login-menu-open', open);
        menuToggle.querySelector('[aria-hidden="true"]').textContent = open ? '×' : '☰';
        menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuToggle.setAttribute('aria-label', open ? text.closeMenu : text.openMenu);
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    if (menuToggle && menu) {
        menu.setAttribute('aria-hidden', 'true');
        menuToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            setMenuOpen(!body.classList.contains('login-menu-open'));
        });
        document.addEventListener('click', function (event) {
            if (body.classList.contains('login-menu-open')
                && !menu.contains(event.target)
                && !menuToggle.contains(event.target)) {
                setMenuOpen(false);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && body.classList.contains('login-menu-open')) {
                setMenuOpen(false);
                menuToggle.focus();
            }
        });
    }

    var toggle = document.querySelector('.password-toggle');
    var password = document.getElementById('xuser_password');
    if (toggle && password) {
        toggle.addEventListener('click', function () {
            var show = password.type === 'password';
            password.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? text.hidePassword : text.showPassword);
            toggle.textContent = show ? '⊘' : '◉';
        });

        // Safari may keep an autofilled password in its private field state after
        // the input type is switched to text. Restore the password type and
        // explicitly commit the visible value before the form is submitted.
        if (password.form) {
            password.form.addEventListener('submit', function () {
                var value = password.value;
                password.type = 'password';
                password.value = value;
            });
        }
    }
}());
