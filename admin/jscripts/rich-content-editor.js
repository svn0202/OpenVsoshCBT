(() => {
    'use strict';

    const allowedTags = new Set([
        'A', 'B', 'BLOCKQUOTE', 'BR', 'CODE', 'DEL', 'DIV', 'EM', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
        'HR', 'I', 'IMG', 'LI', 'MARK', 'OL', 'P', 'PRE', 'S', 'SMALL', 'SPAN', 'STRONG', 'SUB', 'SUP',
        'TABLE', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR', 'U', 'UL',
    ]);
    const droppedTags = new Set([
        'APPLET', 'EMBED', 'FORM', 'IFRAME', 'MATH', 'OBJECT', 'SCRIPT', 'STYLE', 'SVG', 'TEMPLATE',
    ]);
    const commonAttributes = new Set(['dir', 'lang', 'style', 'title']);

    const isSafeUrl = (value, image) => {
        const url = value.trim();
        if (url === '' || url.startsWith('#') || url.startsWith('/') || url.startsWith('./') || url.startsWith('../')) {
            return true;
        }
        if (image && /^data:image\/(?:gif|jpeg|png|webp);base64,/i.test(url)) {
            return true;
        }
        return /^(?:https?:|mailto:)/i.test(url);
    };

    const sanitizeFragment = (html) => {
        const template = document.createElement('template');
        template.innerHTML = html;

        const clean = (parent) => {
            Array.from(parent.childNodes).forEach((node) => {
                if (node.nodeType === Node.COMMENT_NODE) {
                    node.remove();
                    return;
                }
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }
                if (droppedTags.has(node.tagName)) {
                    node.remove();
                    return;
                }
                if (!allowedTags.has(node.tagName)) {
                    clean(node);
                    node.replaceWith(...Array.from(node.childNodes));
                    return;
                }

                const allowedAttributes = new Set(commonAttributes);
                if (node.tagName === 'A') {
                    allowedAttributes.add('href');
                    allowedAttributes.add('target');
                } else if (node.tagName === 'IMG') {
                    ['alt', 'height', 'src', 'width'].forEach((name) => allowedAttributes.add(name));
                } else if (node.tagName === 'TD' || node.tagName === 'TH') {
                    ['colspan', 'rowspan', 'scope'].forEach((name) => allowedAttributes.add(name));
                } else if (node.tagName === 'OL') {
                    allowedAttributes.add('start');
                }

                Array.from(node.attributes).forEach((attribute) => {
                    const name = attribute.name.toLowerCase();
                    if (!allowedAttributes.has(name)
                        || ((name === 'href' || name === 'src') && !isSafeUrl(attribute.value, name === 'src'))) {
                        node.removeAttribute(attribute.name);
                    }
                });
                clean(node);
            });
        };

        clean(template.content);
        return template.content;
    };

    const toolbarButton = (label, title, command, value = '') => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.title = title;
        button.dataset.command = command;
        if (value !== '') {
            button.dataset.value = value;
        }
        return button;
    };

    const toolbarGroup = (label) => {
        const group = document.createElement('span');
        group.className = 'rich-content-editor__group';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', label);
        return group;
    };

    const createEditor = (textarea) => {
        const editor = document.createElement('div');
        editor.className = 'rich-content-editor';
        editor.hidden = true;

        const toolbar = document.createElement('div');
        toolbar.className = 'rich-content-editor__toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Форматирование');

        const historyGroup = toolbarGroup('История изменений');
        [
            ['↶', 'Отменить', 'undo'],
            ['↷', 'Повторить', 'redo'],
        ].forEach((item) => historyGroup.appendChild(toolbarButton(...item)));

        const textGroup = toolbarGroup('Начертание текста');
        [
            ['Ж', 'Полужирный', 'bold'],
            ['К', 'Курсив', 'italic'],
            ['Ч', 'Подчёркнутый', 'underline'],
            ['abc', 'Зачёркнутый', 'strikeThrough'],
            ['x₂', 'Нижний индекс', 'subscript'],
            ['x²', 'Верхний индекс', 'superscript'],
        ].forEach((item) => textGroup.appendChild(toolbarButton(...item)));

        const listGroup = toolbarGroup('Списки и выравнивание');
        [
            ['•', 'Маркированный список', 'insertUnorderedList'],
            ['1.', 'Нумерованный список', 'insertOrderedList'],
            ['←', 'По левому краю', 'justifyLeft'],
            ['↔', 'По центру', 'justifyCenter'],
            ['→', 'По правому краю', 'justifyRight'],
        ].forEach((item) => listGroup.appendChild(toolbarButton(...item)));

        const actionGroup = toolbarGroup('Дополнительные действия');
        [
            ['Ссылка', 'Добавить ссылку', 'createLink'],
            ['Изображение', 'Вставить изображение по адресу', 'insertImage'],
            ['Очистить', 'Убрать форматирование', 'removeFormat'],
        ].forEach((item) => actionGroup.appendChild(toolbarButton(...item)));

        const format = document.createElement('select');
        format.title = 'Стиль абзаца';
        format.setAttribute('aria-label', 'Стиль абзаца');
        [
            ['P', 'Обычный текст'],
            ['H2', 'Заголовок 2'],
            ['H3', 'Заголовок 3'],
            ['BLOCKQUOTE', 'Цитата'],
            ['PRE', 'Форматированный текст'],
        ].forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            format.appendChild(option);
        });
        const formatGroup = toolbarGroup('Стиль абзаца и исходный код');
        formatGroup.appendChild(format);

        const sourceToggle = toolbarButton('HTML', 'Показать или скрыть исходный HTML', 'source');
        sourceToggle.setAttribute('aria-pressed', 'false');
        formatGroup.appendChild(sourceToggle);
        toolbar.append(historyGroup, textGroup, listGroup, actionGroup, formatGroup);

        const surface = document.createElement('div');
        surface.className = 'rich-content-editor__surface';
        surface.contentEditable = 'true';
        surface.setAttribute('role', 'textbox');
        surface.setAttribute('aria-multiline', 'true');
        surface.setAttribute('aria-label', textarea.title || 'Редактор');

        const imageInspector = document.createElement('div');
        imageInspector.className = 'rich-content-editor__image-inspector';
        imageInspector.hidden = true;
        imageInspector.setAttribute('aria-label', 'Свойства изображения');
        imageInspector.innerHTML = '<span class="rich-content-editor__image-title">Изображение</span>'
            + '<label>Ширина <input type="number" min="1" step="1" inputmode="numeric" data-image-property="width" /></label>'
            + '<label>Высота <input type="number" min="1" step="1" inputmode="numeric" data-image-property="height" /></label>'
            + '<label class="rich-content-editor__image-alt">Описание <input type="text" data-image-property="alt" /></label>'
            + '<button type="button" data-image-action="reset">Сбросить размер</button>';

        editor.append(toolbar, imageInspector, surface);
        textarea.insertAdjacentElement('afterend', editor);

        // The legacy TCECode buttons are kept in the markup for fields that
        // still use the old editor, but must not duplicate the visual editor.
        const legacyToolbar = Array.from(textarea.parentElement?.children || []).find((element) => (
            element.classList?.contains('tcecode-toolbar')
        ));
        if (legacyToolbar) {
            legacyToolbar.classList.add('rich-content-editor__legacy-toolbar');
            legacyToolbar.hidden = true;
        }

        let sourceMode = false;
        let selectedImage = null;
        let imageAspectRatio = null;
        const syncFromSource = () => {
            surface.replaceChildren(sanitizeFragment(textarea.value));
        };
        const syncToSource = () => {
            textarea.value = surface.innerHTML;
        };
        const clearImageSelection = () => {
            selectedImage?.classList.remove('is-selected');
            selectedImage = null;
            imageAspectRatio = null;
            imageInspector.hidden = true;
        };
        const selectImage = (image) => {
            clearImageSelection();
            selectedImage = image;
            selectedImage.classList.add('is-selected');
            const width = image.getAttribute('width') || Math.round(image.getBoundingClientRect().width) || '';
            const height = image.getAttribute('height') || Math.round(image.getBoundingClientRect().height) || '';
            imageAspectRatio = Number(width) > 0 && Number(height) > 0 ? Number(width) / Number(height) : null;
            imageInspector.querySelector('[data-image-property="width"]').value = width;
            imageInspector.querySelector('[data-image-property="height"]').value = height;
            imageInspector.querySelector('[data-image-property="alt"]').value = image.alt;
            imageInspector.hidden = false;
        };

        surface.addEventListener('input', syncToSource);
        surface.addEventListener('click', (event) => {
            if (event.target instanceof HTMLImageElement) {
                selectImage(event.target);
            } else {
                clearImageSelection();
            }
        });
        surface.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                clearImageSelection();
            }
        });
        imageInspector.addEventListener('input', (event) => {
            const input = event.target.closest('input[data-image-property]');
            if (!input || !selectedImage) {
                return;
            }
            const property = input.dataset.imageProperty;
            const value = input.value.trim();
            if (property === 'alt') {
                selectedImage.alt = value;
            } else if (value === '' || Number(value) < 1) {
                selectedImage.removeAttribute(property);
            } else {
                selectedImage.setAttribute(property, String(Math.round(Number(value))));
                if (property === 'width' && imageAspectRatio) {
                    const height = Math.max(1, Math.round(Number(value) / imageAspectRatio));
                    selectedImage.setAttribute('height', String(height));
                    imageInspector.querySelector('[data-image-property="height"]').value = height;
                }
            }
            syncToSource();
        });
        imageInspector.addEventListener('click', (event) => {
            if (!event.target.closest('[data-image-action="reset"]') || !selectedImage) {
                return;
            }
            selectedImage.removeAttribute('width');
            selectedImage.removeAttribute('height');
            imageInspector.querySelector('[data-image-property="width"]').value = '';
            imageInspector.querySelector('[data-image-property="height"]').value = '';
            imageAspectRatio = null;
            syncToSource();
        });
        textarea.form?.addEventListener('submit', () => {
            if (!sourceMode && !editor.hidden) {
                syncToSource();
            }
        });

        toolbar.addEventListener('mousedown', (event) => {
            if (event.target.closest('button')) {
                event.preventDefault();
            }
        });
        toolbar.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-command]');
            if (!button) {
                return;
            }
            const command = button.dataset.command;
            if (command === 'source') {
                clearImageSelection();
                sourceMode = !sourceMode;
                if (sourceMode) {
                    syncToSource();
                    surface.hidden = true;
                    editor.appendChild(textarea);
                    textarea.hidden = false;
                    textarea.focus();
                } else {
                    syncFromSource();
                    editor.before(textarea);
                    textarea.hidden = true;
                    surface.hidden = false;
                    surface.focus();
                }
                sourceToggle.setAttribute('aria-pressed', sourceMode ? 'true' : 'false');
                sourceToggle.classList.toggle('is-active', sourceMode);
                return;
            }
            if (sourceMode) {
                return;
            }
            let value = button.dataset.value || null;
            if (command === 'createLink') {
                value = window.prompt('Адрес ссылки', 'https://');
                if (!value || !isSafeUrl(value, false)) {
                    return;
                }
            } else if (command === 'insertImage') {
                value = window.prompt('Адрес изображения', 'https://');
                if (!value || !isSafeUrl(value, true)) {
                    return;
                }
            }
            document.execCommand(command, false, value);
            syncToSource();
            if (command === 'insertImage') {
                const images = surface.querySelectorAll('img');
                const image = images.item(images.length - 1);
                if (image) {
                    selectImage(image);
                }
            }
            surface.focus();
        });
        format.addEventListener('change', () => {
            if (!sourceMode) {
                document.execCommand('formatBlock', false, format.value);
                syncToSource();
                surface.focus();
            }
        });

        return {
            open() {
                syncFromSource();
                sourceMode = false;
                clearImageSelection();
                textarea.hidden = true;
                surface.hidden = false;
                editor.hidden = false;
                sourceToggle.setAttribute('aria-pressed', 'false');
                sourceToggle.classList.remove('is-active');
                surface.focus();
            },
            close() {
                if (!sourceMode) {
                    syncToSource();
                }
                clearImageSelection();
                if (textarea.parentElement === editor) {
                    editor.before(textarea);
                }
                editor.hidden = true;
                textarea.hidden = false;
            },
        };
    };

    document.querySelectorAll('[data-rich-editor-for]').forEach((toggle) => {
        const textarea = document.getElementById(toggle.dataset.richEditorFor);
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }
        const editor = createEditor(textarea);
        let open = false;
        toggle.addEventListener('click', () => {
            open = !open;
            if (open) {
                editor.open();
            } else {
                editor.close();
            }
            toggle.textContent = open ? toggle.dataset.closeLabel : toggle.dataset.openLabel;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();
