(function () {
    'use strict';

    var form = document.getElementById('testform');
    var toolbar = document.querySelector('[data-exam-toolbar]');
    var themeToggle = document.querySelector('.tmf-theme-toggle');

    if (!form || !toolbar) {
        return;
    }

    var root = document.documentElement;
    var testId = (document.getElementById('testid') || {}).value || '0';
    var testlogId = (document.getElementById('testlogid') || {}).value || '0';
    var testuserId = (document.getElementById('testuser_id') || {}).value || '0';
    var storagePrefix = 'tcexam:' + testId + ':' + testuserId + ':';
    var reviewKey = storagePrefix + 'reviewed';
    var fontKey = 'tcexam:exam-font-scale';
    var minScale = 0.85;
    var maxScale = 1.6;
    var scaleStep = 0.1;

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
            // The controls remain usable when private mode disables storage.
        }
    }

    function setScale(value) {
        var scale = Math.max(minScale, Math.min(maxScale, Number(value) || 1));
        scale = Math.round(scale * 100) / 100;
        root.style.setProperty('--exam-font-scale', scale);
        writeJson(fontKey, scale);
    }

    function getReviewed() {
        var reviewed = readJson(reviewKey, []);
        return Array.isArray(reviewed) ? reviewed.map(String) : [];
    }

    function paintReviewed(reviewed) {
        document.querySelectorAll('.exam-question-list li[data-testlog-id]').forEach(function (item) {
            item.classList.toggle('marked-for-review', reviewed.indexOf(item.dataset.testlogId) !== -1);
        });
    }

    var review = toolbar.querySelector('[data-exam-review]');
    var reviewed = getReviewed();
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
        } else if (action === 'theme') {
            if (themeToggle) {
                themeToggle.click();
            }
        } else if (action === 'fullscreen') {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen();
            }
        }
    });

    var answerText = document.getElementById('answertext');
    if (answerText) {
        var resizeAnswer = function () {
            answerText.style.height = 'auto';
            answerText.style.height = Math.max(140, answerText.scrollHeight + 2) + 'px';
        };
        answerText.addEventListener('input', resizeAnswer);
        resizeAnswer();
    }

    setScale(readJson(fontKey, 1));
}());
