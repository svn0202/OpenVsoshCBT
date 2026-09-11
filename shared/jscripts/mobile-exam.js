(function () {
    'use strict';

    var form = document.getElementById('testform');
    if (!form) {
        return;
    }

    var root = document.documentElement;
    var themeToggle = document.querySelector('.tmf-theme-toggle');
    var fontKey = 'tcexam:exam-font-scale';
    var minScale = 0.85;
    var maxScale = 1.6;
    var scaleStep = 0.1;
    var maximumSaveRetries = 5;
    var retryBaseDelay = 1000;
    var saveRequestTimeout = 15000;
    var heartbeatInterval = 60000;
    var saveButton = null;
    var saveStatus = null;
    var answerVersion = null;
    var saveActive = false;
    var navigationActive = false;
    var answerConflict = false;
    var conflictVersion = null;
    var changedDuringSave = false;
    var answerDirty = false;
    var formSubmitting = false;
    var ajaxBypass = false;
    var testId = '0';
    var testlogId = '0';
    var testuserId = '0';
    var reviewKey = '';
    var heartbeatTimer = null;
    var focusLossOpen = false;
    var focusLossSending = false;
    var pendingFocusEvents = [];
    var focusWarning = null;
    var allowedSystemDialogOpen = false;

    function focusEventId() {
        return operationId();
    }

    function ensureFocusWarning() {
        if (focusWarning) {
            return focusWarning;
        }
        focusWarning = document.createElement('dialog');
        focusWarning.id = 'exam-focus-warning';
        focusWarning.className = 'exam-focus-warning';
        focusWarning.setAttribute('aria-labelledby', 'exam-focus-warning-title');
        focusWarning.innerHTML = '<div class="exam-focus-warning-icon" aria-hidden="true">!</div>'
            + '<h2 id="exam-focus-warning-title">Зафиксирована попытка переключения окна</h2>'
            + '<p>Во время тестирования нельзя переходить в другие окна или вкладки.</p>'
            + '<p class="exam-focus-warning-count" aria-live="polite"></p>'
            + '<button type="button">Вернуться к тесту</button>';
        focusWarning.querySelector('button').addEventListener('click', function () {
            document.body.classList.remove('exam-focus-obscured');
            focusWarning.close();
        });
        focusWarning.addEventListener('cancel', function (event) {
            event.preventDefault();
        });
        document.body.appendChild(focusWarning);
        return focusWarning;
    }

    function updateFocusWarning(count) {
        var warning = ensureFocusWarning();
        var status = warning.querySelector('.exam-focus-warning-count');
        if (status && count !== null && Number.isFinite(Number(count))) {
            status.textContent = 'Количество зафиксированных попыток: ' + String(count) + '.';
        }
        if (!warning.open) {
            try {
                warning.showModal();
            } catch (error) {
                warning.setAttribute('open', 'open');
            }
        }
    }

    function sendNextFocusEvent() {
        var csrf = form.querySelector('[name="csrf_token"]');
        if (focusLossSending || pendingFocusEvents.length === 0 || !window.fetch || !csrf) {
            return;
        }
        focusLossSending = true;
        var data = new FormData();
        data.set('csrf_token', csrf.value);
        data.set('testid', testId);
        data.set('testlogid', testlogId);
        data.set('event_id', pendingFocusEvents[0]);
        var recorded = false;
        window.fetch('tce_test_focus.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            keepalive: true,
            headers: {'Accept': 'application/json'}
        }).then(function (response) {
            return response.json().catch(function () {
                return {status: 'error'};
            }).then(function (payload) {
                if (!response.ok || payload.status !== 'recorded') {
                    var responseError = new Error(payload.status || 'error');
                    responseError.retryable = response.status >= 500;
                    throw responseError;
                }
                pendingFocusEvents.shift();
                recorded = true;
                updateFocusWarning(payload.count);
            });
        }).catch(function (error) {
            if (error.retryable === false) {
                pendingFocusEvents.shift();
                recorded = true;
            } else {
                window.setTimeout(sendNextFocusEvent, 3000);
            }
        }).finally(function () {
            focusLossSending = false;
            if (recorded && pendingFocusEvents.length > 0) {
                window.setTimeout(sendNextFocusEvent, 0);
            }
        });
    }

    function recordFocusLoss() {
        if (focusLossOpen || formSubmitting || allowedSystemDialogOpen) {
            return;
        }
        focusLossOpen = true;
        document.body.classList.add('exam-focus-obscured');
        pendingFocusEvents.push(focusEventId());
        updateFocusWarning(null);
        sendNextFocusEvent();
    }

    function closeFocusLossEpisode() {
        if (document.visibilityState === 'visible' && document.hasFocus()) {
            focusLossOpen = false;
            allowedSystemDialogOpen = false;
        }
    }

    function sendHeartbeat() {
        var csrf = form.querySelector('[name="csrf_token"]');
        if (!window.fetch || !csrf || testId === '0' || testlogId === '0') {
            return;
        }
        var data = new FormData();
        data.set('csrf_token', csrf.value);
        data.set('testid', testId);
        data.set('testlogid', testlogId);
        window.fetch('tce_test_heartbeat.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        }).catch(function () {
            // Monitoring tolerates temporary network failures and derives a
            // lost-connection state after the configured grace period.
        });
    }

    function readJson(key, fallback) {
        try {
            var value = window.localStorage.getItem(key);
            return value === null ? fallback : JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Non-essential display preferences remain optional.
        }
    }

    function setScale(value) {
        var scale = Math.max(minScale, Math.min(maxScale, Number(value) || 1));
        scale = Math.round(scale * 100) / 100;
        root.style.setProperty('--exam-font-scale', scale);
        writeJson(fontKey, scale);
    }

    function operationId() {
        var bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (var index = 0; index < bytes.length; index += 1) {
                bytes[index] = Math.floor(Math.random() * 256);
            }
        }
        return Array.prototype.map.call(bytes, function (value) {
            return value.toString(16).padStart(2, '0');
        }).join('');
    }

    function setSaveStatus(state, message) {
        if (!saveStatus) {
            return;
        }
        saveStatus.dataset.state = state;
        saveStatus.textContent = message;
    }

    function retryStatusMessage(button, attempt, delay) {
        return button.dataset.answerRetrying
            .replace('{attempt}', String(attempt))
            .replace('{maximum}', String(maximumSaveRetries))
            .replace('{seconds}', String(delay / 1000));
    }

    function answerFailure(payload, httpStatus) {
        var error = new Error(payload.status || 'access_denied');
        error.httpStatus = httpStatus;
        error.requestId = typeof payload.request_id === 'string' ? payload.request_id : '';
        error.retryable = httpStatus >= 500;
        return error;
    }

    function refreshAnswerToken(data, button) {
        var url = new URL(button.dataset.answerSave, window.location.href);
        url.searchParams.set('action', 'refresh_csrf');
        url.searchParams.set('testid', data.get('testid'));
        url.searchParams.set('testlogid', data.get('testlogid'));
        var controller = new AbortController();
        var timer = window.setTimeout(function () { controller.abort(); }, saveRequestTimeout);
        return window.fetch(url.href, {credentials: 'same-origin', cache: 'no-store',
            headers: {'Accept': 'application/json'}, signal: controller.signal}).then(function (response) {
            return response.json().catch(function () { return {status: 'access_denied'}; }).then(function (payload) {
                if (!payload || typeof payload !== 'object' || Array.isArray(payload)) { payload = {status: 'access_denied'}; }
                if (!response.ok || payload.status !== 'csrf_refreshed' || typeof payload.csrf_token !== 'string') {
                    throw answerFailure(payload, response.status);
                }
                data.set('csrf_token', payload.csrf_token);
                var token = form.querySelector('[name="csrf_token"]');
                if (token) { token.value = payload.csrf_token; }
            });
        }).catch(function (error) {
            // Failure to revalidate the session must never trigger automatic POST replay.
            error.retryable = false;
            error.httpStatus = error.httpStatus || 403;
            throw error;
        }).finally(function () { window.clearTimeout(timer); });
    }

    function answerFailureMessage(error, button) {
        var messages = {
            session_required: 'Сессия истекла или требует повторного входа. Войдите в другой вкладке, затем повторите сохранение здесь. Ответ остаётся в этой форме.',
            csrf_failed: 'Не удалось обновить защиту запроса. Ответ не сохранён и остаётся в форме. Повторите вход и сохранение.',
            time_expired: 'Время теста истекло. Последние изменения не сохранены; ответ остаётся в форме.',
            attempt_closed: 'Попытка уже завершена. Последние изменения не сохранены; ответ остаётся в форме.',
            attempt_blocked: 'Попытка заблокирована наблюдателем. Ответ не сохранён. Обратитесь к организатору.',
            access_denied: 'Нет доступа к сохранению этого ответа. Ответ остаётся в форме. Обратитесь к организатору.',
            invalid_request: 'Не удалось проверить данные запроса. Ответ не сохранён. Обратитесь к организатору.',
            invalid: 'Сервер отклонил данные ответа. Ответ остаётся в форме. Проверьте заполнение.'
        };
        var message = messages[error.message] || (error.httpStatus === 403
            ? 'Сервер запретил сохранение, причина не указана. Ответ остаётся в форме. Обратитесь к организатору.'
            : button.dataset.answerError);
        if (error.requestId && /^[a-f0-9]{24}$/.test(error.requestId)) { message += ' Код запроса: ' + error.requestId; }
        var login = document.getElementById('answer-login-link');
        if (login) { login.remove(); }
        if (error.message === 'session_required' || error.message === 'csrf_failed') {
            login = document.createElement('a');
            login.id = 'answer-login-link';
            login.href = 'tce_login.php';
            login.target = '_blank';
            login.rel = 'noopener';
            login.textContent = 'Войти в другой вкладке';
            saveStatus.insertAdjacentElement('afterend', login);
        }
        return message;
    }

    function sendAnswer(data, retryCount, button, csrfRetried) {
        var controller = window.AbortController ? new window.AbortController() : null;
        var timeout = controller
            ? window.setTimeout(function () {
                controller.abort();
            }, saveRequestTimeout)
            : null;
        var options = {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        };
        if (controller) {
            options.signal = controller.signal;
        }

        return window.fetch(button.dataset.answerSave, options).then(function (response) {
            return response.json().catch(function () {
                return {status: 'error'};
            }).then(function (payload) {
                if (!payload || typeof payload !== 'object' || Array.isArray(payload)) { payload = {status: 'error'}; }
                if (response.ok && payload.status === 'saved') {
                    return payload;
                }
                if (response.status === 403 && payload.status === 'csrf_failed' && !csrfRetried) {
                    csrfRetried = true;
                    return refreshAnswerToken(data, button).then(function () {
                        return sendAnswer(data, retryCount, button, true).catch(function (error) {
                            error.retryable = false;
                            throw error;
                        });
                    });
                }
                var responseError = answerFailure(payload, response.status);
                if (payload.status === 'conflict' && Number.isSafeInteger(payload.version) && payload.version >= 0) {
                    responseError.serverVersion = payload.version;
                }
                throw responseError;
            });
        }).finally(function () {
            if (timeout !== null) {
                window.clearTimeout(timeout);
            }
        }).catch(function (error) {
            var retryable = error.retryable !== false && error.message !== 'conflict';
            if (!retryable || retryCount >= maximumSaveRetries) {
                throw error;
            }

            var nextRetry = retryCount + 1;
            var delay = retryBaseDelay * Math.pow(2, retryCount);
            setSaveStatus('retrying', retryStatusMessage(button, nextRetry, delay));
            return new Promise(function (resolve) {
                window.setTimeout(resolve, delay);
            }).then(function () {
                return sendAnswer(data, nextRetry, button, csrfRetried);
            });
        });
    }

    function showAnswerConflict() {
        var previous = document.getElementById('answer-conflict-actions');
        if (previous) { previous.remove(); }
        setSaveStatus('error', 'Ответ изменён другим запросом. Ваш вариант остаётся в форме. Просмотрите сохранённый ответ или явно выберите запись своего варианта.');
        var actions = document.createElement('div');
        actions.id = 'answer-conflict-actions';
        var link = document.createElement('a');
        var url = new URL('tce_test_execute.php', window.location.href);
        url.searchParams.set('testid', testId);
        url.searchParams.set('testlogid', testlogId);
        link.href = url.href;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = 'Открыть сохранённый ответ в другой вкладке';
        actions.appendChild(link);
        if (conflictVersion !== null) {
            var overwrite = document.createElement('button');
            overwrite.type = 'button';
            overwrite.textContent = 'Сохранить мой вариант вместо серверного';
            overwrite.addEventListener('click', function () {
                if (saveActive || navigationActive) { return; }
                if (!window.confirm('Заменить сохранённый ответ вашим текущим вариантом? Если ответ на сервере снова изменился, замена будет отклонена.')) { return; }
                saveCurrentAnswer(conflictVersion).catch(function () {});
            });
            actions.appendChild(overwrite);
        }
        saveStatus.insertAdjacentElement('afterend', actions);
    }

    function saveCurrentAnswer(resolvedVersion) {
        if (!saveButton || !saveStatus || !answerVersion || !window.fetch) {
            return Promise.reject(new Error('unsupported'));
        }
        if (saveActive) {
            return Promise.reject(new Error('saving'));
        }
        if (answerConflict && (conflictVersion === null || resolvedVersion !== conflictVersion)) {
            showAnswerConflict();
            var conflict = new Error('conflict');
            conflict.httpStatus = 409;
            return Promise.reject(conflict);
        }

        var button = saveButton;
        var data = new FormData(form);
        if (answerConflict) {
            data.set('answer_version', String(resolvedVersion));
        }
        var displayTime = Number((document.getElementById('display_time') || {}).value || Date.now());
        data.set('reaction_time', String(Math.max(0, Date.now() - displayTime)));
        data.set('answer_operation', operationId());
        saveActive = true;
        changedDuringSave = false;
        button.disabled = true;
        setSaveStatus('saving', button.dataset.answerSaving);

        return sendAnswer(data, 0, button).then(function (payload) {
            answerVersion.value = String(payload.version);
            answerConflict = false;
            conflictVersion = null;
            var conflictActions = document.getElementById('answer-conflict-actions');
            if (conflictActions) { conflictActions.remove(); }
            var loginLink = document.getElementById('answer-login-link');
            if (loginLink) { loginLink.remove(); }
            var liveScore = form.querySelector('#exam-live-score span');
            if (liveScore && Object.prototype.hasOwnProperty.call(payload, 'live_score')) {
                liveScore.textContent = String(payload.live_score);
            }
            if (changedDuringSave) {
                answerDirty = true;
                setSaveStatus('dirty', button.dataset.answerUnsaved);
            } else {
                answerDirty = false;
                setSaveStatus('saved', button.dataset.answerSaved);
            }
            return payload;
        }).catch(function (error) {
            answerDirty = true;
            if (error.message === 'conflict' || error.httpStatus === 409) {
                answerConflict = true;
                conflictVersion = Number.isSafeInteger(error.serverVersion) && error.serverVersion >= 0
                    ? error.serverVersion : null;
                showAnswerConflict();
            } else {
                setSaveStatus('error', answerFailureMessage(error, button));
            }
            throw error;
        }).finally(function () {
            saveActive = false;
            button.disabled = false;
        });
    }

    function getReviewed() {
        var reviewed = readJson(reviewKey, []);
        return Array.isArray(reviewed) ? reviewed.map(String) : [];
    }

    function paintReviewed(reviewed) {
        form.querySelectorAll('.exam-question-list li[data-testlog-id]').forEach(function (item) {
            item.classList.toggle('marked-for-review', reviewed.indexOf(item.dataset.testlogId) !== -1);
        });
    }

    function bindToolbar() {
        var toolbar = form.querySelector('[data-exam-toolbar]');
        if (!toolbar) {
            return;
        }

        var review = form.querySelector('[data-exam-review]');
        var reviewed = getReviewed();
        if (review) {
            form.querySelectorAll('.exam-question-list li.marked-for-review[data-testlog-id]')
                .forEach(function (item) {
                    if (reviewed.indexOf(item.dataset.testlogId) === -1) {
                        reviewed.push(item.dataset.testlogId);
                    }
                });
            var serverReviewed = review.dataset.reviewed === '1';
            reviewed = reviewed.filter(function (id) {
                return id !== String(testlogId);
            });
            if (serverReviewed) {
                reviewed.push(String(testlogId));
            }
            writeJson(reviewKey, reviewed);
            review.checked = reviewed.indexOf(String(testlogId)) !== -1;
            paintReviewed(reviewed);
            review.addEventListener('change', function () {
                reviewed = getReviewed().filter(function (id) {
                    return id !== String(testlogId);
                });
                if (review.checked) {
                    reviewed.push(String(testlogId));
                }
                writeJson(reviewKey, reviewed);
                paintReviewed(reviewed);
                var csrf = form.querySelector('[name="csrf_token"]');
                if (window.fetch && csrf && review.dataset.reviewSave) {
                    var data = new FormData();
                    data.set('csrf_token', csrf.value);
                    data.set('testid', testId);
                    data.set('testlogid', testlogId);
                    data.set('reviewed', review.checked ? '1' : '0');
                    window.fetch(review.dataset.reviewSave, {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin',
                        headers: {'Accept': 'application/json'}
                    }).catch(function () {
                        // The local copy remains available until the server can be reached.
                    });
                }
            });
        }

        document.body.classList.toggle('exam-hide-info', toolbar.dataset.hideExamInfo === '1');
        if (toolbar.dataset.autoFullscreen === '1' && !document.fullscreenElement) {
            var enterFullscreen = function () {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(function () {
                        // Browser policy may deny fullscreen; the manual button remains available.
                    });
                }
                document.removeEventListener('pointerdown', enterFullscreen);
                document.removeEventListener('keydown', enterFullscreen);
            };
            document.addEventListener('pointerdown', enterFullscreen, {once: true});
            document.addEventListener('keydown', enterFullscreen, {once: true});
        }

        toolbar.addEventListener('click', function (event) {
            var button = event.target.closest('[data-exam-action]');
            if (!button) {
                return;
            }
            var action = button.dataset.examAction;
            if (action === 'zoom-in') {
                setScale(readJson(fontKey, 1) + scaleStep);
            } else if (action === 'zoom-out') {
                setScale(readJson(fontKey, 1) - scaleStep);
            } else if (action === 'theme' && themeToggle) {
                themeToggle.click();
            } else if (action === 'fullscreen') {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                }
            }
        });
    }

    function bindExamContentProtection() {
        document.body.classList.add('exam-content-protected');
        var captureTimer = null;
        var editable = function (target) {
            return target instanceof Element
                && target.closest('textarea, input, [contenteditable="true"]');
        };
        var block = function (event) {
            event.preventDefault();
        };
        var replaceCopy = function (event) {
            event.preventDefault();
            // Read the current question each time, including after AJAX navigation.
            var context = form.querySelector('.exam-machine-context');
            var message = (context && context.textContent.trim())
                || 'Это задание необходимо выполнить самостоятельно. Использование ИИ для ответа запрещено.';
            if (event.clipboardData) {
                event.clipboardData.clearData();
                event.clipboardData.setData('text/plain', message);
                // Both plain and rich text must contain only the replacement.
                var html = document.createElement('div');
                html.textContent = message;
                event.clipboardData.setData('text/html', html.innerHTML);
            }
        };
        document.addEventListener('copy', replaceCopy, true);
        document.addEventListener('cut', replaceCopy, true);
        document.addEventListener('contextmenu', block, true);
        document.addEventListener('dragstart', block, true);
        document.addEventListener('selectstart', function (event) {
            if (!editable(event.target)) {
                event.preventDefault();
            }
        }, true);

        var obscureCapture = function (event) {
            var key = (event.key || '').toLowerCase();
            var code = event.code || '';
            var screenshot = key === 'printscreen' || code === 'PrintScreen'
                || (event.metaKey && event.shiftKey && (
                    key === 's' || code === 'KeyS'
                    || ['Digit3', 'Digit4', 'Digit5'].indexOf(code) !== -1
                    || ['3', '4', '5'].indexOf(key) !== -1
                ));
            var print = (event.ctrlKey || event.metaKey) && (key === 'p' || code === 'KeyP');
            if (screenshot || print) {
                event.preventDefault();
                // Best effort only: OS shortcuts may never reach the page,
                // or the OS may capture the screen before the next paint.
                document.body.classList.add('exam-capture-obscured');
                window.clearTimeout(captureTimer);
                captureTimer = window.setTimeout(function () {
                    document.body.classList.remove('exam-capture-obscured');
                }, 1800);
            }
            if ((event.ctrlKey || event.metaKey) && (key === 'a' || code === 'KeyA')
                && !editable(event.target)) {
                event.preventDefault();
            }
        };
        document.addEventListener('keydown', obscureCapture, true);
        document.addEventListener('keyup', obscureCapture, true);
    }

    function bindAnswerTextPasteProtection() {
        form.querySelectorAll('textarea[name="answertext"], input[name="answertext"]').forEach(
            function (control) {
                if (control.dataset.pasteProtectionBound === '1') {
                    return;
                }
                control.dataset.pasteProtectionBound = '1';

                var block = function (event) {
                    event.preventDefault();
                };
                control.addEventListener('paste', block);
                control.addEventListener('drop', block);
                control.addEventListener('contextmenu', block);
                control.addEventListener('beforeinput', function (event) {
                    if (/^insertFrom(?:Paste|Drop)/.test(event.inputType || '')) {
                        event.preventDefault();
                    }
                });
                control.addEventListener('keydown', function (event) {
                    var key = String(event.key || '').toLowerCase();
                    if (
                        ((key === 'v' || event.code === 'KeyV') && (event.ctrlKey || event.metaKey))
                        || (key === 'insert' && event.shiftKey)
                    ) {
                        event.preventDefault();
                    }
                });
            }
        );
    }

    function bindAnswerControls() {
        saveButton = form.querySelector('[data-answer-save]');
        saveStatus = form.querySelector('#answer-save-status');
        answerVersion = form.querySelector('#answer_version');
        answerDirty = false;
        saveActive = false;
        changedDuringSave = false;

        bindAnswerTextPasteProtection();

        if (!saveButton || !saveStatus || !answerVersion || !window.fetch) {
            return;
        }

        form.querySelectorAll('[name="answertext"], [name="answpos"], [name^="answpos["]').forEach(
            function (control) {
                var markDirty = function () {
                    answerDirty = true;
                    if (saveActive) {
                        changedDuringSave = true;
                    } else {
                        setSaveStatus('dirty', saveButton.dataset.answerUnsaved);
                    }
                };
                control.addEventListener('input', markDirty);
                control.addEventListener('change', markDirty);
            }
        );
        saveButton.addEventListener('click', function () {
            saveCurrentAnswer().catch(function () {
                // The visible status contains the actionable result.
            });
        });
    }

    function bindQuestionMenu() {
        var menu = form.querySelector('.exam-question-list');
        if (!menu || menu.dataset.questionMenuBound === '1') {
            return;
        }
        menu.dataset.questionMenuBound = '1';

        function jumpFromItem(item) {
            var submitter = item
                ? item.querySelector('input[type="submit"][name^="jumpquestion_"]')
                : null;
            if (!submitter || submitter.disabled) {
                return;
            }
            if (form.requestSubmit) {
                form.requestSubmit(submitter);
            } else {
                submitter.click();
            }
        }

        menu.querySelectorAll('li[data-testlog-id]').forEach(function (item) {
            var submitter = item.querySelector('input[type="submit"][name^="jumpquestion_"]');
            if (!submitter || submitter.disabled) {
                return;
            }
            item.classList.add('question-menu-link');
            item.tabIndex = 0;
            item.setAttribute('role', 'button');
            item.setAttribute('aria-label', submitter.title || submitter.value);
        });

        menu.addEventListener('click', function (event) {
            if (event.target.closest('input, button, a, label, select, textarea')) {
                return;
            }
            jumpFromItem(event.target.closest('li.question-menu-link'));
        });
        menu.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            var item = event.target.closest('li.question-menu-link');
            if (!item || event.target !== item) {
                return;
            }
            event.preventDefault();
            jumpFromItem(item);
        });
    }

    function resizeAnswerText() {
        var answerText = form.querySelector('#answertext');
        if (!answerText) {
            return;
        }
        var resizeAnswer = function () {
            answerText.style.height = 'auto';
            answerText.style.height = Math.max(140, answerText.scrollHeight + 2) + 'px';
        };
        answerText.addEventListener('input', resizeAnswer);
        resizeAnswer();
    }

    function bindImagePreviews() {
        var toolbar = form.querySelector('[data-exam-toolbar]');
        var label = toolbar ? toolbar.dataset.imagePreviewLabel : 'Image';
        var closeLabel = toolbar ? toolbar.dataset.imagePreviewClose : 'Close';
        var dialog = document.getElementById('exam-image-preview');

        if (!dialog) {
            dialog = document.createElement('dialog');
            dialog.id = 'exam-image-preview';
            dialog.className = 'exam-image-preview';
            dialog.setAttribute('aria-label', label);
            dialog.innerHTML = '<button type="button" class="exam-image-preview-close"></button>'
                + '<img alt="" />';
            dialog.querySelector('button').textContent = closeLabel;
            document.body.appendChild(dialog);
            dialog.querySelector('button').addEventListener('click', function () {
                dialog.close();
            });
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        }

        // Answer options can be image-only.  Their label must retain the
        // native click behaviour that selects the corresponding control;
        // attaching the preview handler here would intercept that choice.
        form.querySelectorAll('.tcecontentbox img').forEach(function (source) {
            if (source.dataset.examPreviewBound === '1') {
                return;
            }
            source.dataset.examPreviewBound = '1';
            source.classList.add('exam-previewable-image');
            source.tabIndex = 0;
            source.setAttribute('role', 'button');
            source.setAttribute('aria-label', label + (source.alt ? ': ' + source.alt : ''));

            var openPreview = function () {
                var preview = dialog.querySelector('img');
                preview.src = source.currentSrc || source.src;
                preview.alt = source.alt || '';
                dialog.showModal();
            };
            source.addEventListener('click', openPreview);
            source.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openPreview();
                }
            });
        });
    }

    function bindAudioLimits() {
        var toolbar = form.querySelector('[data-exam-toolbar]');
        var limit = Number(toolbar ? toolbar.dataset.audioPlayLimit : 0);
        if (!limit || limit < 1) {
            return;
        }
        var playsLeft = toolbar.dataset.audioPlaysLeft || 'Plays remaining: {count}';
        var limitExhausted = toolbar.dataset.audioLimitExhausted || 'Audio play limit reached';

        form.querySelectorAll('.tcecontentbox audio, ol.answer audio').forEach(function (audio, index) {
            if (audio.dataset.examAudioBound === '1') {
                return;
            }
            audio.dataset.examAudioBound = '1';
            var key = 'tcexam:' + testId + ':' + testuserId + ':audio:' + testlogId + ':' + index;
            var status = document.createElement('span');
            status.className = 'audio-play-status';
            status.setAttribute('aria-live', 'polite');
            audio.insertAdjacentElement('afterend', status);

            var updateStatus = function () {
                var used = Math.max(0, Number(readJson(key, 0)) || 0);
                status.textContent = used >= limit
                    ? limitExhausted
                    : playsLeft.replace('{count}', String(limit - used));
                audio.setAttribute('aria-disabled', used >= limit ? 'true' : 'false');
            };
            audio.addEventListener('play', function () {
                if (audio.currentTime > 0.5) {
                    return;
                }
                var used = Math.max(0, Number(readJson(key, 0)) || 0);
                if (used >= limit) {
                    audio.pause();
                    audio.currentTime = 0;
                    updateStatus();
                    return;
                }
                writeJson(key, used + 1);
                updateStatus();
            });
            updateStatus();
        });
    }

    function refreshQuestionState() {
        testId = (form.querySelector('#testid') || {}).value || '0';
        testlogId = (form.querySelector('#testlogid') || {}).value || '0';
        testuserId = (form.querySelector('#testuser_id') || {}).value || '0';
        reviewKey = 'tcexam:' + testId + ':' + testuserId + ':reviewed';
        bindToolbar();
        bindAnswerControls();
        bindQuestionMenu();
        resizeAnswerText();
        bindImagePreviews();
        bindAudioLimits();
        setScale(readJson(fontKey, 1));

        var displayTime = form.querySelector('#display_time');
        if (displayTime) {
            displayTime.value = String(Date.now());
        }
        var legacyNumber = document.querySelector('#qTopBar #qNum');
        var currentNumber = form.querySelector('.exam-question-number span');
        if (legacyNumber && currentNumber) {
            legacyNumber.textContent = currentNumber.textContent;
        }
    }

    function setQuestionLoading(loading) {
        var status = document.getElementById('exam-question-loading-status');
        if (!status) {
            status = document.createElement('div');
            status.id = 'exam-question-loading-status';
            status.className = 'exam-question-loading-status';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            status.hidden = true;
            form.insertAdjacentElement('beforebegin', status);
        }
        form.classList.toggle('exam-question-loading', loading);
        form.setAttribute('aria-busy', loading ? 'true' : 'false');
        status.hidden = !loading;
        status.textContent = loading ? 'Загрузка задания…' : '';
    }

    function navigationTarget(submitter) {
        if (!submitter || !submitter.name) {
            return null;
        }
        if (submitter.name === 'confirmanswer') {
            return testlogId;
        }
        if (submitter.name === 'prevquestion') {
            return (form.querySelector('#prevquestionid') || {}).value || null;
        }
        if (submitter.name === 'nextquestion') {
            return (form.querySelector('#nextquestionid') || {}).value || null;
        }
        var jump = submitter.name.match(/^jumpquestion_(\d+)$/);
        return jump ? jump[1] : null;
    }

    function executeQuestionScripts() {
        form.querySelectorAll('script').forEach(function (oldScript) {
            var script = document.createElement('script');
            Array.prototype.forEach.call(oldScript.attributes, function (attribute) {
                script.setAttribute(attribute.name, attribute.value);
            });
            script.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(script, oldScript);
        });
    }

    function loadQuestion(target) {
        var url = 'tce_test_execute.php?testid=' + encodeURIComponent(testId)
            + '&testlogid=' + encodeURIComponent(target);
        var controller = window.AbortController ? new window.AbortController() : null;
        var timeout = controller
            ? window.setTimeout(function () {
                controller.abort();
            }, saveRequestTimeout)
            : null;
        var options = {
            method: 'GET',
            credentials: 'same-origin',
            headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}
        };
        if (controller) {
            options.signal = controller.signal;
        }

        return window.fetch(url, options).then(function (response) {
            if (!response.ok) {
                throw new Error('question_load_failed');
            }
            return response.text();
        }).then(function (html) {
            if (answerDirty || saveActive) {
                throw new Error('answer_changed');
            }
            var parsed = new window.DOMParser().parseFromString(html, 'text/html');
            var replacement = parsed.querySelector('#testform');
            if (!replacement) {
                throw new Error('question_form_missing');
            }
            if (window.tmfQuestionTimerId) {
                window.clearTimeout(window.tmfQuestionTimerId);
                window.tmfQuestionTimerId = null;
            }
            form.innerHTML = replacement.innerHTML;
            try {
                executeQuestionScripts();
                document.querySelectorAll('[data-answer-save-error]').forEach(function (message) {
                    message.remove();
                });
                window.history.replaceState({testlogid: target}, '', url);
                formSubmitting = false;
                refreshQuestionState();
                setQuestionLoading(false);
                var question = form.querySelector('#questionsection');
                if (question) {
                    question.scrollIntoView({block: 'start'});
                }
            } catch (error) {
                // The answer is already confirmed by the server. Reload the new
                // question with GET instead of submitting the old answer twice.
                window.location.assign(url);
            }
        }).catch(function (error) {
            // A failed GET must not trigger a form submission with newer edits.
            if (answerDirty || saveActive) {
                throw new Error('answer_changed');
            }
            throw error;
        }).finally(function () {
            if (timeout !== null) {
                window.clearTimeout(timeout);
            }
        });
    }

    function fallbackSubmit(submitterName, submitterValue) {
        ajaxBypass = true;
        formSubmitting = true;
        var currentSubmitter = null;
        if (submitterName) {
            currentSubmitter = form.querySelector('[name="' + submitterName.replace(/"/g, '\\"') + '"]');
        }
        if (form.requestSubmit && currentSubmitter) {
            form.requestSubmit(currentSubmitter);
            return;
        }

        var fallbackControl = document.createElement('input');
        fallbackControl.type = 'hidden';
        fallbackControl.name = submitterName;
        fallbackControl.value = submitterValue;
        form.appendChild(fallbackControl);
        form.submit();
    }

    form.addEventListener('submit', function (event) {
        if (ajaxBypass) {
            return;
        }
        var submitter = event.submitter || document.activeElement;
        var submitterName = submitter && submitter.name ? submitter.name : '';
        var submitterValue = submitter && submitter.value ? submitter.value : '';
        var target = navigationTarget(submitter);
        if (!target) {
            formSubmitting = true;
            return;
        }

        event.preventDefault();
        if (saveActive || navigationActive) {
            return;
        }
        navigationActive = true;
        if (String(target) !== String(testlogId)) {
            setQuestionLoading(true);
        }
        saveCurrentAnswer().then(function () {
            if (answerDirty) {
                setQuestionLoading(false);
                return;
            }
            if (String(target) === String(testlogId)) {
                return;
            }
            return loadQuestion(target);
        }).catch(function (error) {
            if (error.message === 'answer_changed') {
                setQuestionLoading(false);
                return;
            }
            if (error.httpStatus >= 400 && error.httpStatus < 500) {
                setQuestionLoading(false);
                return;
            }
            fallbackSubmit(submitterName, submitterValue);
        }).finally(function () {
            navigationActive = false;
        });
    });

    var nativeSubmit = form.submit.bind(form);
    form.submit = function () {
        formSubmitting = true;
        nativeSubmit();
    };
    window.addEventListener('beforeunload', function (event) {
        if (!formSubmitting && (answerDirty || saveActive)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    bindExamContentProtection();
    refreshQuestionState();
    form.addEventListener('click', function (event) {
        if (event.target.closest('input[type="file"]')) {
            allowedSystemDialogOpen = true;
        }
    }, true);
    window.addEventListener('blur', recordFocusLoss);
    window.addEventListener('focus', closeFocusLossEpisode);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            recordFocusLoss();
        } else {
            closeFocusLossEpisode();
            sendNextFocusEvent();
        }
    });
    sendHeartbeat();
    heartbeatTimer = window.setInterval(sendHeartbeat, heartbeatInterval);
    window.addEventListener('pagehide', function () {
        if (heartbeatTimer !== null) {
            window.clearInterval(heartbeatTimer);
        }
    });
}());
