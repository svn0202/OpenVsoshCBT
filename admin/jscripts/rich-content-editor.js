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
    const richEditors = new Map();

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
            ['Таблица', 'Вставить таблицу', 'insertTable'],
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

        const pasteNotice = document.createElement('div');
        pasteNotice.className = 'rich-content-editor__paste-notice';
        pasteNotice.hidden = true;
        pasteNotice.setAttribute('role', 'status');

        const imageMenu = document.createElement('div');
        imageMenu.className = 'rich-content-editor__image-menu';
        imageMenu.setAttribute('role', 'menu');
        imageMenu.hidden = true;
        imageMenu.innerHTML = '<button type="button" role="menuitem" data-image-menu-action="edit">Редактировать изображение</button>'
            + '<button type="button" role="menuitem" data-image-menu-action="delete">Удалить изображение</button>';

        const imageDialog = document.createElement('dialog');
        imageDialog.className = 'rich-content-editor__image-dialog';
        imageDialog.innerHTML = '<form method="dialog">'
            + '<h2>Свойства изображения</h2>'
            + '<label>Ширина, px <input type="number" min="1" step="1" inputmode="numeric" name="width" /></label>'
            + '<label>Высота, px <input type="number" min="1" step="1" inputmode="numeric" name="height" /></label>'
            + '<label class="rich-content-editor__image-lock" title="Автоматически изменять вторую сторону">'
            + '<input type="checkbox" name="lockAspectRatio" checked="checked" />'
            + '<span aria-hidden="true">🔒</span> Сохранять пропорции</label>'
            + '<label>Описание <input type="text" name="alt" /></label>'
            + '<div class="rich-content-editor__dialog-actions">'
            + '<button type="button" data-image-action="reset">Исходный размер</button>'
            + '<span></span><button type="submit" value="cancel">Отмена</button>'
            + '<button type="submit" value="save" class="primary">Сохранить</button></div>'
            + '</form>';
        const imageForm = imageDialog.querySelector('form');

        editor.append(toolbar, pasteNotice, imageMenu, imageDialog, surface);
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
        let imageUseOriginalSize = false;
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
            imageUseOriginalSize = false;
            imageMenu.hidden = true;
        };
        const selectImage = (image) => {
            clearImageSelection();
            selectedImage = image;
            selectedImage.classList.add('is-selected');
        };
        const openImageDialog = () => {
            if (!selectedImage) {
                return;
            }
            const width = selectedImage.getAttribute('width') || Math.round(selectedImage.getBoundingClientRect().width) || '';
            const height = selectedImage.getAttribute('height') || Math.round(selectedImage.getBoundingClientRect().height) || '';
            const naturalWidth = selectedImage.naturalWidth || Number(width);
            const naturalHeight = selectedImage.naturalHeight || Number(height);
            imageAspectRatio = naturalWidth > 0 && naturalHeight > 0 ? naturalWidth / naturalHeight : null;
            imageUseOriginalSize = false;
            imageForm.elements.width.value = width;
            imageForm.elements.height.value = height;
            imageForm.elements.alt.value = selectedImage.alt;
            imageForm.elements.lockAspectRatio.checked = true;
            imageMenu.hidden = true;
            if (typeof imageDialog.showModal === 'function') {
                imageDialog.showModal();
            } else {
                imageDialog.setAttribute('open', 'open');
            }
            imageForm.elements.width.focus();
        };

        surface.addEventListener('input', syncToSource);
        surface.addEventListener('paste', async (event) => {
            const image = Array.from(event.clipboardData?.items || [])
                .find((item) => item.kind === 'file' && item.type.startsWith('image/'));
            const file = image?.getAsFile();
            if (!file) {
                return;
            }

            event.preventDefault();
            const selection = window.getSelection();
            const range = selection?.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
            const payload = new FormData();
            payload.append('image', file, file.name || 'clipboard-image.png');
            pasteNotice.textContent = 'Загружаем изображение из буфера…';
            pasteNotice.hidden = false;

            try {
                const response = await window.fetch('tce_upload_clipboard_image.php', {
                    method: 'POST',
                    body: payload,
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (!response.ok || !result.file) {
                    throw new Error(result.error || 'Не удалось загрузить изображение.');
                }
                surface.focus();
                if (range) {
                    const currentSelection = window.getSelection();
                    currentSelection?.removeAllRanges();
                    currentSelection?.addRange(range);
                }
                const inserted = document.createElement('img');
                inserted.src = `../../cache/${String(result.file).replace(/^\/+/, '')}`;
                inserted.alt = '';
                if (Number.isInteger(result.width) && result.width > 0) {
                    inserted.width = result.width;
                }
                if (Number.isInteger(result.height) && result.height > 0) {
                    inserted.height = result.height;
                }
                document.execCommand('insertHTML', false, inserted.outerHTML);
                syncToSource();
                selectImage(surface.querySelector('img:last-of-type'));
                pasteNotice.textContent = 'Изображение добавлено в медиатеку.';
            } catch (error) {
                pasteNotice.textContent = error instanceof Error ? error.message : 'Не удалось загрузить изображение.';
            }
            window.setTimeout(() => {
                pasteNotice.hidden = true;
            }, 3500);
        });
        surface.addEventListener('click', (event) => {
            if (event.target instanceof HTMLImageElement) {
                selectImage(event.target);
            } else {
                clearImageSelection();
            }
        });
        surface.addEventListener('contextmenu', (event) => {
            if (!(event.target instanceof HTMLImageElement)) {
                clearImageSelection();
                return;
            }
            event.preventDefault();
            selectImage(event.target);
            imageMenu.style.left = `${event.clientX}px`;
            imageMenu.style.top = `${event.clientY}px`;
            imageMenu.hidden = false;
        });
        surface.addEventListener('keydown', (event) => {
            if ((event.key === 'Delete' || event.key === 'Backspace') && selectedImage) {
                event.preventDefault();
                selectedImage.remove();
                clearImageSelection();
                syncToSource();
                return;
            }
            if (event.key === 'Escape') {
                clearImageSelection();
            }
        });
        imageMenu.addEventListener('click', (event) => {
            const action = event.target.closest('[data-image-menu-action]')?.dataset.imageMenuAction;
            if (action === 'delete' && selectedImage) {
                selectedImage.remove();
                clearImageSelection();
                syncToSource();
                return;
            }
            if (action === 'edit') {
                openImageDialog();
            }
        });
        document.addEventListener('click', (event) => {
            if (!imageMenu.contains(event.target)) {
                imageMenu.hidden = true;
            }
        });
        imageDialog.addEventListener('click', (event) => {
            if (!event.target.closest('[data-image-action="reset"]') || !selectedImage) {
                return;
            }
            selectedImage.removeAttribute('width');
            selectedImage.removeAttribute('height');
            const width = selectedImage.naturalWidth || Math.round(selectedImage.getBoundingClientRect().width) || '';
            const height = selectedImage.naturalHeight || Math.round(selectedImage.getBoundingClientRect().height) || '';
            imageForm.elements.width.value = width;
            imageForm.elements.height.value = height;
            imageAspectRatio = Number(width) > 0 && Number(height) > 0 ? Number(width) / Number(height) : null;
            imageUseOriginalSize = true;
            syncToSource();
        });
        imageForm.addEventListener('input', (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement)
                || !['width', 'height'].includes(input.name)
                || !imageForm.elements.lockAspectRatio.checked
                || !imageAspectRatio) {
                return;
            }
            const value = Number(input.value);
            if (!Number.isFinite(value) || value < 1) {
                return;
            }
            if (input.name === 'width') {
                imageForm.elements.height.value = Math.max(1, Math.round(value / imageAspectRatio));
            } else {
                imageForm.elements.width.value = Math.max(1, Math.round(value * imageAspectRatio));
            }
            imageUseOriginalSize = false;
        });
        imageDialog.addEventListener('submit', (event) => {
            event.preventDefault();
            if (event.submitter?.value === 'save' && selectedImage) {
                if (!imageUseOriginalSize) {
                    ['width', 'height'].forEach((property) => {
                        const value = imageForm.elements[property].value.trim();
                        if (value === '' || Number(value) < 1) {
                            selectedImage.removeAttribute(property);
                        } else {
                            selectedImage.setAttribute(property, String(Math.round(Number(value))));
                        }
                    });
                }
                selectedImage.alt = imageForm.elements.alt.value.trim();
                syncToSource();
            }
            if (typeof imageDialog.close === 'function') {
                imageDialog.close();
            } else {
                imageDialog.removeAttribute('open');
            }
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
                const formId = textarea.form?.id || '';
                const url = `tce_select_mediafile.php?frm=${encodeURIComponent(formId)}&fld=${encodeURIComponent(textarea.id)}`;
                window.open(url, 'mediaselect', 'height=600,width=680,resizable=yes,menubar=no,scrollbars=yes,toolbar=no,status=no');
                return;
            }
            if (command === 'insertTable') {
                const rows = Number.parseInt(window.prompt('Количество строк', '2') || '', 10);
                const columns = Number.parseInt(window.prompt('Количество столбцов', '2') || '', 10);
                if (!Number.isInteger(rows) || !Number.isInteger(columns)
                    || rows < 1 || columns < 1 || rows > 20 || columns > 20) {
                    window.alert('Укажите от 1 до 20 строк и столбцов.');
                    return;
                }
                const table = document.createElement('table');
                const body = document.createElement('tbody');
                for (let row = 0; row < rows; row += 1) {
                    const tableRow = document.createElement('tr');
                    for (let column = 0; column < columns; column += 1) {
                        const cell = document.createElement(row === 0 ? 'th' : 'td');
                        cell.textContent = row === 0 ? `Заголовок ${column + 1}` : 'Текст';
                        tableRow.appendChild(cell);
                    }
                    body.appendChild(tableRow);
                }
                table.appendChild(body);
                document.execCommand('insertHTML', false, table.outerHTML);
                syncToSource();
                surface.focus();
                return;
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
            insertMedia(file, width, height, alt) {
                const path = String(file).replace(/^\/+/, '');
                if (path === '' || path.includes('..')) {
                    return;
                }
                const image = document.createElement('img');
                image.src = `../../cache/${path}`;
                image.alt = String(alt || '');
                if (/^\d{1,4}$/.test(String(width))) {
                    image.width = Number(width);
                }
                if (/^\d{1,4}$/.test(String(height))) {
                    image.height = Number(height);
                }
                surface.append(document.createElement('br'), image, document.createElement('br'));
                syncToSource();
                surface.focus();
            },
        };
    };

    window.F_rich_content_editor_insert_media = (fieldId, file, width, height, alt) => {
        const editor = richEditors.get(String(fieldId));
        if (!editor) {
            return false;
        }
        editor.insertMedia(file, width, height, alt);
        return true;
    };

    document.querySelectorAll('[data-rich-editor-for]').forEach((toggle) => {
        const textarea = document.getElementById(toggle.dataset.richEditorFor);
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }
        const editor = createEditor(textarea);
        richEditors.set(textarea.id, editor);
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
