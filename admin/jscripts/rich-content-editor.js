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

    const createEditor = (textarea) => {
        const editor = document.createElement('div');
        editor.className = 'rich-content-editor';
        editor.hidden = true;

        const toolbar = document.createElement('div');
        toolbar.className = 'rich-content-editor__toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Форматирование');

        [
            ['↶', 'Отменить', 'undo'],
            ['↷', 'Повторить', 'redo'],
            ['Ж', 'Полужирный', 'bold'],
            ['К', 'Курсив', 'italic'],
            ['Ч', 'Подчёркнутый', 'underline'],
            ['abc', 'Зачёркнутый', 'strikeThrough'],
            ['x₂', 'Нижний индекс', 'subscript'],
            ['x²', 'Верхний индекс', 'superscript'],
            ['•', 'Маркированный список', 'insertUnorderedList'],
            ['1.', 'Нумерованный список', 'insertOrderedList'],
            ['←', 'По левому краю', 'justifyLeft'],
            ['↔', 'По центру', 'justifyCenter'],
            ['→', 'По правому краю', 'justifyRight'],
            ['Ссылка', 'Добавить ссылку', 'createLink'],
            ['Очистить', 'Убрать форматирование', 'removeFormat'],
        ].forEach((item) => toolbar.appendChild(toolbarButton(...item)));

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
        toolbar.appendChild(format);

        const sourceToggle = toolbarButton('HTML', 'Показать или скрыть исходный HTML', 'source');
        toolbar.appendChild(sourceToggle);

        const surface = document.createElement('div');
        surface.className = 'rich-content-editor__surface';
        surface.contentEditable = 'true';
        surface.setAttribute('role', 'textbox');
        surface.setAttribute('aria-multiline', 'true');
        surface.setAttribute('aria-label', textarea.title || 'Редактор');

        editor.append(toolbar, surface);
        textarea.insertAdjacentElement('afterend', editor);

        let sourceMode = false;
        const syncFromSource = () => {
            surface.replaceChildren(sanitizeFragment(textarea.value));
        };
        const syncToSource = () => {
            textarea.value = surface.innerHTML;
        };

        surface.addEventListener('input', syncToSource);
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
                sourceMode = !sourceMode;
                if (sourceMode) {
                    syncToSource();
                    surface.hidden = true;
                    textarea.hidden = false;
                } else {
                    syncFromSource();
                    textarea.hidden = true;
                    surface.hidden = false;
                    surface.focus();
                }
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
            }
            document.execCommand(command, false, value);
            syncToSource();
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
                textarea.hidden = true;
                surface.hidden = false;
                editor.hidden = false;
                surface.focus();
            },
            close() {
                if (!sourceMode) {
                    syncToSource();
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
