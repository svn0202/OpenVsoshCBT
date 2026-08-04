(() => {
    'use strict';

    const preview = document.getElementById('appearance-preview');
    if (!preview) {
        return;
    }

    const palette = document.getElementById('admin_palette');
    const font = document.getElementById('ui_font');
    const density = document.getElementById('admin_density');
    const position = document.getElementById('login_background_position');
    const size = document.getElementById('login_background_size');
    const overlay = document.getElementById('login_background_overlay');
    const overlayOutput = document.querySelector('output[for="login_background_overlay"]');
    const loginPreview = preview.querySelector('.settings-preview-login');
    const backgroundInput = document.getElementById('site_background');
    const logoInput = document.getElementById('site_logo');
    const siteName = document.getElementById('site_name');
    const previewTitle = loginPreview?.querySelector('strong');

    const update = () => {
        preview.dataset.palette = palette?.value || 'ocean';
        preview.dataset.font = font?.value || 'system';
        preview.dataset.density = density?.value || 'comfortable';
        ['ocean', 'slate', 'forest', 'berry'].forEach((name) => {
            document.body.classList.toggle(`admin-palette-${name}`, palette?.value === name);
        });
        ['comfortable', 'compact'].forEach((name) => {
            document.body.classList.toggle(`admin-density-${name}`, density?.value === name);
        });
        ['system', 'humanist', 'serif'].forEach((name) => {
            document.body.classList.toggle(`ui-font-${name}`, font?.value === name);
        });
        if (loginPreview) {
            loginPreview.style.setProperty('--preview-position', position?.value || 'center');
            loginPreview.style.setProperty('--preview-size', size?.value === 'auto' ? 'none' : (size?.value || 'cover'));
            loginPreview.style.setProperty('--preview-overlay', String(Number(overlay?.value || 0) / 100));
        }
        if (overlayOutput) {
            overlayOutput.value = `${overlay?.value || 0}%`;
            overlayOutput.textContent = overlayOutput.value;
        }
        if (previewTitle && siteName) {
            previewTitle.textContent = siteName.value.trim() || 'OpenVsoshCBT';
        }
    };

    [palette, font, density, position, size, overlay, siteName].forEach((control) => {
        control?.addEventListener('input', update);
        control?.addEventListener('change', update);
    });

    const previewUpload = (input, target) => {
        input?.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file || !file.type.startsWith('image/')) {
                return;
            }
            const url = URL.createObjectURL(file);
            if (target === 'background' && loginPreview) {
                let image = loginPreview.querySelector('img');
                if (!image) {
                    image = document.createElement('img');
                    image.alt = '';
                    loginPreview.prepend(image);
                }
                image.src = url;
            } else if (target === 'logo') {
                const navigationLogo = document.querySelector('.admin-nav-heading img');
                if (navigationLogo) {
                    navigationLogo.src = url;
                }
            }
        });
    };

    previewUpload(backgroundInput, 'background');
    previewUpload(logoInput, 'logo');
    update();
})();
