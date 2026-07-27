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
    var saveButton = null;
    var saveStatus = null;
    var answerVersion = null;
    var saveActive = false;
    var changedDuringSave = false;
    var answerDirty = false;
    var formSubmitting = false;
    var ajaxBypass = false;
    var testId = '0';
    var testlogId = '0';
    var testuserId = '0';
    var reviewKey = '';

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

    function sendAnswer(data, retryCount, button) {
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
                if (response.ok && payload.status === 'saved') {
                    return payload;
                }
                var responseError = new Error(payload.status || 'error');
                responseError.retryable = response.status >= 500;
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
                return sendAnswer(data, nextRetry, button);
            });
        });
    }

    function saveCurrentAnswer() {
        if (!saveButton || !saveStatus || !answerVersion || !window.fetch) {
            return Promise.reject(new Error('unsupported'));
        }
        if (saveActive) {
            return Promise.reject(new Error('saving'));
        }

        var button = saveButton;
        var data = new FormData(form);
        var displayTime = Number((document.getElementById('display_time') || {}).value || Date.now());
        data.set('reaction_time', String(Math.max(0, Date.now() - displayTime)));
        data.set('answer_operation', operationId());
        saveActive = true;
        changedDuringSave = false;
        button.disabled = true;
        setSaveStatus('saving', button.dataset.answerSaving);

        return sendAnswer(data, 0, button).then(function (payload) {
            answerVersion.value = String(payload.version);
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
            setSaveStatus(
                'error',
                error.message === 'conflict'
                    ? button.dataset.answerConflict
                    : button.dataset.answerError
            );
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

        var review = toolbar.querySelector('[data-exam-review]');
        var reviewed = getReviewed();
        if (review) {
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
            });
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

    function bindAnswerControls() {
        saveButton = form.querySelector('[data-answer-save]');
        saveStatus = form.querySelector('#answer-save-status');
        answerVersion = form.querySelector('#answer_version');
        answerDirty = false;
        saveActive = false;
        changedDuringSave = false;

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

    function refreshQuestionState() {
        testId = (form.querySelector('#testid') || {}).value || '0';
        testlogId = (form.querySelector('#testlogid') || {}).value || '0';
        testuserId = (form.querySelector('#testuser_id') || {}).value || '0';
        reviewKey = 'tcexam:' + testId + ':' + testuserId + ':reviewed';
        bindToolbar();
        bindAnswerControls();
        resizeAnswerText();
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
                window.history.replaceState({testlogid: target}, '', url);
                formSubmitting = false;
                refreshQuestionState();
                var question = form.querySelector('#questionsection');
                if (question) {
                    question.scrollIntoView({block: 'start'});
                }
            } catch (error) {
                // The answer is already confirmed by the server. Reload the new
                // question with GET instead of submitting the old answer twice.
                window.location.assign(url);
            }
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
        if (saveActive) {
            return;
        }
        saveCurrentAnswer().then(function () {
            if (String(target) === String(testlogId)) {
                return;
            }
            return loadQuestion(target);
        }).catch(function () {
            fallbackSubmit(submitterName, submitterValue);
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

    refreshQuestionState();
}());
