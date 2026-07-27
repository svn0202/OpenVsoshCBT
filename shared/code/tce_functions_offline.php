<?php

/**
 * Signed offline exam packages and idempotent result import.
 */

const TMF_OFFLINE_FORMAT = 'OpenVsoshCBT-offline-package-v1';
const TMF_OFFLINE_RESULT_FORMAT = 'OpenVsoshCBT-offline-result-v1';
const TMF_OFFLINE_MAX_RESULT_BYTES = 5_242_880;

function F_tmf_offline_table(): string
{
    return K_TABLE_PREFIX . 'offline_packages';
}

function F_tmf_offline_payload_encode(array $payload): string
{
    return base64_encode(
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
    );
}

function F_tmf_offline_sign(string $payload_base64, string $secret): string
{
    return hash_hmac('sha256', $payload_base64, $secret);
}

function F_tmf_offline_signature_is_valid(string $payload_base64, string $signature, string $secret): bool
{
    return preg_match('/^[a-f0-9]{64}$/', $signature) === 1
        && hash_equals(F_tmf_offline_sign($payload_base64, $secret), $signature);
}

function F_tmf_offline_scalar(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_resource($value)) {
        return (string) stream_get_contents($value);
    }
    if (is_object($value) && method_exists($value, 'load')) {
        return (string) $value->load();
    }
    return (string) $value;
}

/**
 * Issue one package for an existing generated attempt.
 *
 * @return array{status:string,envelope?:array{format:string,payload_b64:string,signature:string},filename?:string}
 */
function F_tmf_offline_issue(int $testuser_id): array
{
    require_once '../config/tce_config.php';
    require_once __DIR__ . '/tce_functions_openvsosh_settings.php';
    global $db;

    if ($testuser_id <= 0 || !F_tmf_monitor_attempt_is_authorized($testuser_id)) {
        return ['status' => 'forbidden'];
    }
    $sql = 'SELECT tu.testuser_id, tu.testuser_test_id, tu.testuser_user_id, tu.testuser_status,
            t.test_name, t.test_end_time, t.test_duration_time,
            u.user_name, u.user_firstname, u.user_lastname
        FROM ' . K_TABLE_TEST_USER . ' tu
        INNER JOIN ' . K_TABLE_TESTS . ' t ON t.test_id=tu.testuser_test_id
        INNER JOIN ' . K_TABLE_USERS . ' u ON u.user_id=tu.testuser_user_id
        WHERE tu.testuser_id=' . $testuser_id . '
            AND tu.testuser_status>0
            AND tu.testuser_status<4
        LIMIT 1';
    $result = F_db_query($sql, $db);
    $attempt = $result ? F_db_fetch_array($result) : false;
    if (!is_array($attempt)) {
        return ['status' => 'invalid_state'];
    }

    $questions = [];
    $logs_sql = 'SELECT tl.testlog_id, tl.testlog_order, q.question_type, q.question_description
        FROM ' . K_TABLE_TESTS_LOGS . ' tl
        INNER JOIN ' . K_TABLE_QUESTIONS . ' q ON q.question_id=tl.testlog_question_id
        WHERE tl.testlog_testuser_id=' . $testuser_id . '
        ORDER BY tl.testlog_order, tl.testlog_id';
    $logs_result = F_db_query($logs_sql, $db);
    while ($logs_result && ($log = F_db_fetch_array($logs_result))) {
        $question = [
            'testlog_id' => (int) $log['testlog_id'],
            'order' => (int) $log['testlog_order'],
            'type' => (int) $log['question_type'],
            'description' => F_tmf_offline_scalar($log['question_description']),
            'answers' => [],
        ];
        $answers_sql = 'SELECT la.logansw_order, a.answer_description
            FROM ' . K_TABLE_LOG_ANSWER . ' la
            INNER JOIN ' . K_TABLE_ANSWERS . ' a ON a.answer_id=la.logansw_answer_id
            WHERE la.logansw_testlog_id=' . (int) $log['testlog_id'] . '
            ORDER BY la.logansw_order';
        $answers_result = F_db_query($answers_sql, $db);
        while ($answers_result && ($answer = F_db_fetch_array($answers_result))) {
            $question['answers'][] = [
                'order' => (int) $answer['logansw_order'],
                'description' => F_tmf_offline_scalar($answer['answer_description']),
            ];
        }
        $questions[] = $question;
    }

    $now = time();
    $test_end = strtotime((string) $attempt['test_end_time']);
    $duration_seconds = (int) $attempt['test_duration_time'] * K_SECONDS_IN_MINUTE;
    $duration_end = $now + ($duration_seconds > 0 ? $duration_seconds : (7 * K_SECONDS_IN_DAY));
    $expires = $test_end === false ? $duration_end : min($test_end, $duration_end);
    if ($expires <= $now) {
        return ['status' => 'expired'];
    }
    $package_id = bin2hex(random_bytes(16));
    $payload = [
        'format' => TMF_OFFLINE_FORMAT,
        'package_id' => $package_id,
        'testuser_id' => $testuser_id,
        'test_id' => (int) $attempt['testuser_test_id'],
        'user_id' => (int) $attempt['testuser_user_id'],
        'test_name' => F_tmf_offline_scalar($attempt['test_name']),
        'user_name' => F_tmf_offline_scalar($attempt['user_name']),
        'user_display_name' => trim(
            F_tmf_offline_scalar($attempt['user_lastname']) . ' '
            . F_tmf_offline_scalar($attempt['user_firstname']),
        ),
        'issued_at' => gmdate('c', $now),
        'expires_at' => gmdate('c', $expires),
        'duration_minutes' => (int) $attempt['test_duration_time'],
        'questions' => $questions,
    ];
    $payload_base64 = F_tmf_offline_payload_encode($payload);
    $payload_hash = hash('sha256', $payload_base64);
    $secret = openvsosh_get_offline_package_secret();
    $signature = F_tmf_offline_sign($payload_base64, $secret);
    $issued_at = date(K_TIMESTAMP_FORMAT, $now);
    $expires_at = date(K_TIMESTAMP_FORMAT, $expires);

    if (!F_db_query('START TRANSACTION', $db)) {
        return ['status' => 'error'];
    }
    $revoke_sql = 'UPDATE ' . F_tmf_offline_table() . "
        SET offline_status='revoked'
        WHERE offline_testuser_id=" . $testuser_id . "
            AND offline_status='issued'";
    $insert_sql = 'INSERT INTO ' . F_tmf_offline_table() . ' (
            offline_package_id, offline_testuser_id, offline_test_id, offline_user_id,
            offline_issued_at, offline_expires_at, offline_payload_hash, offline_status
        ) VALUES (
            \'' . $package_id . '\',
            ' . $testuser_id . ',
            ' . (int) $attempt['testuser_test_id'] . ',
            ' . (int) $attempt['testuser_user_id'] . ",
            '" . $issued_at . "',
            '" . $expires_at . "',
            '" . $payload_hash . "',
            'issued'
        )";
    $claim_sql = 'UPDATE ' . K_TABLE_TEST_USER . "
        SET testuser_pregenerated='0'
        WHERE testuser_id=" . $testuser_id;
    if (
        !F_db_query($revoke_sql, $db)
        || !F_db_query($insert_sql, $db)
        || !F_db_query($claim_sql, $db)
        || !F_db_query('COMMIT', $db)
    ) {
        F_db_query('ROLLBACK', $db);
        return ['status' => 'error'];
    }

    $safe_user = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $attempt['user_name']);
    return [
        'status' => 'issued',
        'envelope' => [
            'format' => TMF_OFFLINE_FORMAT,
            'payload_b64' => $payload_base64,
            'signature' => $signature,
        ],
        'filename' => 'offline-' . $safe_user . '-' . $package_id . '.html',
    ];
}

/**
 * Import and score one signed offline result.
 *
 * @return array{status:string,package_id?:string}
 */
function F_tmf_offline_import(string $result_json): array
{
    require_once '../config/tce_config.php';
    require_once __DIR__ . '/tce_functions_openvsosh_settings.php';
    global $db;

    if ($result_json === '' || strlen($result_json) > TMF_OFFLINE_MAX_RESULT_BYTES) {
        return ['status' => 'invalid'];
    }
    try {
        $result = json_decode($result_json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['status' => 'invalid'];
    }
    if (
        !is_array($result)
        || ($result['format'] ?? '') !== TMF_OFFLINE_RESULT_FORMAT
        || !is_string($result['payload_b64'] ?? null)
        || !is_string($result['signature'] ?? null)
        || !is_array($result['answers'] ?? null)
    ) {
        return ['status' => 'invalid'];
    }
    $payload_base64 = $result['payload_b64'];
    $signature = $result['signature'];
    if (!F_tmf_offline_signature_is_valid(
        $payload_base64,
        $signature,
        openvsosh_get_offline_package_secret(),
    )) {
        return ['status' => 'signature_failed'];
    }
    $payload_json = base64_decode($payload_base64, true);
    if ($payload_json === false) {
        return ['status' => 'invalid'];
    }
    try {
        $payload = json_decode($payload_json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['status' => 'invalid'];
    }
    $package_id = is_string($payload['package_id'] ?? null) ? $payload['package_id'] : '';
    $testuser_id = isset($payload['testuser_id']) ? (int) $payload['testuser_id'] : 0;
    $test_id = isset($payload['test_id']) ? (int) $payload['test_id'] : 0;
    $user_id = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;
    if (
        ($payload['format'] ?? '') !== TMF_OFFLINE_FORMAT
        || preg_match('/^[a-f0-9]{32}$/', $package_id) !== 1
        || $testuser_id <= 0
        || $test_id <= 0
        || $user_id <= 0
        || !F_tmf_monitor_attempt_is_authorized($testuser_id)
    ) {
        return ['status' => 'forbidden'];
    }
    try {
        $answers_json = json_encode(
            $result['answers'],
            JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    } catch (JsonException) {
        return ['status' => 'invalid'];
    }
    $result_hash = hash('sha256', $payload_base64 . "\n" . $answers_json);

    if (!F_db_query('START TRANSACTION', $db)) {
        return ['status' => 'error'];
    }
    try {
        $package_sql = 'SELECT *
            FROM ' . F_tmf_offline_table() . "
            WHERE offline_package_id='" . $package_id . "'
            FOR UPDATE";
        $package_result = F_db_query($package_sql, $db);
        $package = $package_result ? F_db_fetch_array($package_result) : false;
        if (!is_array($package)) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'not_found'];
        }
        if (
            (int) $package['offline_testuser_id'] !== $testuser_id
            || (int) $package['offline_test_id'] !== $test_id
            || (int) $package['offline_user_id'] !== $user_id
            || !hash_equals((string) $package['offline_payload_hash'], hash('sha256', $payload_base64))
        ) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'binding_failed'];
        }
        if ((string) $package['offline_status'] === 'imported') {
            F_db_query('ROLLBACK', $db);
            return hash_equals((string) $package['offline_result_hash'], $result_hash)
                ? ['status' => 'duplicate', 'package_id' => $package_id]
                : ['status' => 'conflict', 'package_id' => $package_id];
        }
        if (
            (string) $package['offline_status'] !== 'issued'
            || strtotime((string) $package['offline_expires_at']) < time()
        ) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'expired_or_revoked', 'package_id' => $package_id];
        }

        $allowed_logs = [];
        $logs_result = F_db_query(
            'SELECT testlog_id FROM ' . K_TABLE_TESTS_LOGS . '
            WHERE testlog_testuser_id=' . $testuser_id,
            $db,
        );
        while ($logs_result && ($log = F_db_fetch_array($logs_result))) {
            $allowed_logs[(int) $log['testlog_id']] = [];
        }
        $orders_result = F_db_query(
            'SELECT la.logansw_testlog_id, la.logansw_order
            FROM ' . K_TABLE_LOG_ANSWER . ' la
            INNER JOIN ' . K_TABLE_TESTS_LOGS . ' tl ON tl.testlog_id=la.logansw_testlog_id
            WHERE tl.testlog_testuser_id=' . $testuser_id,
            $db,
        );
        while ($orders_result && ($order = F_db_fetch_array($orders_result))) {
            $allowed_logs[(int) $order['logansw_testlog_id']][(int) $order['logansw_order']] = true;
        }
        if (count($result['answers']) !== count($allowed_logs)) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'invalid'];
        }

        $seen = [];
        foreach ($result['answers'] as $answer) {
            if (!is_array($answer)) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'invalid'];
            }
            $testlog_id = isset($answer['testlog_id']) ? (int) $answer['testlog_id'] : 0;
            $positions = $answer['positions'] ?? [];
            $text = $answer['text'] ?? '';
            $reaction_time = isset($answer['reaction_time']) ? max(0, (int) $answer['reaction_time']) : 0;
            if (
                !isset($allowed_logs[$testlog_id])
                || isset($seen[$testlog_id])
                || !is_array($positions)
                || !is_string($text)
                || strlen($text) > 1_048_576
            ) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'invalid'];
            }
            $clean_positions = [];
            foreach ($positions as $position => $value) {
                if (
                    !is_numeric($position)
                    || !is_numeric($value)
                    || !isset($allowed_logs[$testlog_id][(int) $position])
                ) {
                    F_db_query('ROLLBACK', $db);
                    return ['status' => 'invalid'];
                }
                $clean_positions[(int) $position] = (int) $value;
            }
            if (!F_updateQuestionLog($test_id, $testlog_id, $clean_positions, $text, $reaction_time)) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'error'];
            }
            $saved_at = date(K_TIMESTAMP_FORMAT);
            if (!F_db_query(
                'UPDATE ' . K_TABLE_TESTS_LOGS . "
                SET testlog_answer_version=testlog_answer_version+1,
                    testlog_answer_operation='" . $package_id . "',
                    testlog_answer_saved_at='" . $saved_at . "'
                WHERE testlog_id=" . $testlog_id,
                $db,
            )) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'error'];
            }
            $seen[$testlog_id] = true;
        }

        $now = date(K_TIMESTAMP_FORMAT);
        $attempt_sql = 'UPDATE ' . K_TABLE_TEST_USER . "
            SET testuser_status=4,
                testuser_close_reason='completed',
                testuser_last_activity='" . $now . "'
            WHERE testuser_id=" . $testuser_id . '
                AND testuser_test_id=' . $test_id . '
                AND testuser_user_id=' . $user_id . '
                AND testuser_status<4';
        $revoke_sql = 'UPDATE ' . F_tmf_offline_table() . "
            SET offline_status='revoked'
            WHERE offline_testuser_id=" . $testuser_id . "
                AND offline_package_id<>'" . $package_id . "'
                AND offline_status='issued'";
        $package_update_sql = 'UPDATE ' . F_tmf_offline_table() . "
            SET offline_status='imported',
                offline_imported_at='" . $now . "',
                offline_result_hash='" . $result_hash . "'
            WHERE offline_package_id='" . $package_id . "'
                AND offline_status='issued'";
        if (
            !F_db_query($attempt_sql, $db)
            || !F_db_query($revoke_sql, $db)
            || !F_db_query($package_update_sql, $db)
            || !F_db_query('COMMIT', $db)
        ) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error'];
        }
        return ['status' => 'imported', 'package_id' => $package_id];
    } catch (Throwable) {
        F_db_query('ROLLBACK', $db);
        return ['status' => 'error'];
    }
}

/**
 * Build a self-contained, network-free HTML exam from a signed envelope.
 */
function F_tmf_offline_html(array $envelope): string
{
    $nonce = bin2hex(random_bytes(16));
    $envelope_json = json_encode(
        $envelope,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR,
    );
    $script = <<<'JS'
(function () {
    'use strict';
    var envelope = ENVELOPE_PLACEHOLDER;
    var binary = atob(envelope.payload_b64);
    var bytes = Uint8Array.from(binary, function (char) { return char.charCodeAt(0); });
    var payload = JSON.parse(new TextDecoder().decode(bytes));
    var questions = document.getElementById('questions');
    var title = document.getElementById('exam-title');
    var participant = document.getElementById('participant');
    var timer = document.getElementById('timer');
    title.textContent = payload.test_name;
    participant.textContent = payload.user_display_name || payload.user_name;

    function addText(parent, tag, value, className) {
        var element = document.createElement(tag);
        element.textContent = value;
        if (className) { element.className = className; }
        parent.appendChild(element);
        return element;
    }

    payload.questions.forEach(function (question) {
        var section = document.createElement('section');
        section.dataset.testlogId = String(question.testlog_id);
        section.dataset.questionType = String(question.type);
        addText(section, 'h2', 'Вопрос ' + question.order);
        addText(section, 'p', question.description, 'description');
        var started = Date.now();
        section.dataset.started = String(started);
        if (question.type === 3) {
            var textarea = document.createElement('textarea');
            textarea.rows = 8;
            textarea.setAttribute('aria-label', 'Ответ на вопрос ' + question.order);
            section.appendChild(textarea);
        } else {
            var list = document.createElement('ol');
            question.answers.forEach(function (answer) {
                var item = document.createElement('li');
                var input;
                if (question.type === 4 || question.type === 5) {
                    input = document.createElement('select');
                    var empty = document.createElement('option');
                    empty.value = '0';
                    empty.textContent = '—';
                    input.appendChild(empty);
                    question.answers.forEach(function (_, index) {
                        var option = document.createElement('option');
                        option.value = String(index + 1);
                        option.textContent = String(index + 1);
                        input.appendChild(option);
                    });
                } else {
                    input = document.createElement('input');
                    input.type = question.type === 1 ? 'radio' : 'checkbox';
                    input.value = '1';
                    if (question.type === 1) {
                        input.name = 'question-' + question.testlog_id;
                    }
                }
                input.dataset.order = String(answer.order);
                var label = document.createElement('label');
                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + answer.description));
                item.appendChild(label);
                list.appendChild(item);
            });
            section.appendChild(list);
        }
        questions.appendChild(section);
    });

    function collectAnswers() {
        return Array.prototype.map.call(questions.querySelectorAll('section'), function (section) {
            var positions = {};
            section.querySelectorAll('[data-order]').forEach(function (control) {
                if (control.tagName === 'SELECT') {
                    positions[control.dataset.order] = Number(control.value);
                } else if (control.checked) {
                    positions[control.dataset.order] = 1;
                }
            });
            var textarea = section.querySelector('textarea');
            return {
                testlog_id: Number(section.dataset.testlogId),
                positions: positions,
                text: textarea ? textarea.value : '',
                reaction_time: Math.max(0, Date.now() - Number(section.dataset.started))
            };
        });
    }

    function downloadResult() {
        var result = {
            format: 'OpenVsoshCBT-offline-result-v1',
            payload_b64: envelope.payload_b64,
            signature: envelope.signature,
            submitted_at: new Date().toISOString(),
            answers: collectAnswers()
        };
        var blob = new Blob([JSON.stringify(result)], {type: 'application/json'});
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'offline-result-' + payload.user_name + '-' + payload.package_id + '.json';
        link.click();
        window.setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
    }

    document.getElementById('download').addEventListener('click', downloadResult);
    document.getElementById('restore').addEventListener('change', function (event) {
        var file = event.target.files[0];
        if (!file || file.size > 5242880) { return; }
        file.text().then(function (text) {
            var draft = JSON.parse(text);
            if (draft.payload_b64 !== envelope.payload_b64 || draft.signature !== envelope.signature) {
                throw new Error('wrong_package');
            }
            draft.answers.forEach(function (answer) {
                var section = questions.querySelector('[data-testlog-id="' + answer.testlog_id + '"]');
                if (!section) { return; }
                var textarea = section.querySelector('textarea');
                if (textarea) { textarea.value = answer.text || ''; }
                section.querySelectorAll('[data-order]').forEach(function (control) {
                    var value = answer.positions[String(control.dataset.order)];
                    if (control.tagName === 'SELECT') {
                        control.value = String(value || 0);
                    } else {
                        control.checked = Number(value || 0) === 1;
                    }
                });
            });
            document.getElementById('message').textContent = 'Черновик восстановлен.';
        }).catch(function () {
            document.getElementById('message').textContent = 'Этот черновик относится к другому пакету.';
        });
    });

    function updateTimer() {
        var seconds = Math.max(0, Math.floor((Date.parse(payload.expires_at) - Date.now()) / 1000));
        timer.textContent = String(Math.floor(seconds / 60)).padStart(2, '0')
            + ':' + String(seconds % 60).padStart(2, '0');
        if (seconds === 0) {
            document.querySelectorAll('input,select,textarea').forEach(function (control) {
                control.disabled = true;
            });
            document.getElementById('message').textContent =
                'Время истекло. Скачайте результат и передайте организатору.';
        }
    }
    updateTimer();
    window.setInterval(updateTimer, 1000);
}());
JS;
    $script = str_replace('ENVELOPE_PLACEHOLDER', $envelope_json, $script);
    return '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
        . '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; '
        . 'style-src \'unsafe-inline\'; script-src \'nonce-' . $nonce . '\';">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Автономный тест OpenVsoshCBT</title><style>'
        . 'body{font:17px system-ui,sans-serif;max-width:900px;margin:auto;padding:24px;color:#182638}'
        . 'header{position:sticky;top:0;background:#fff;padding:12px 0;border-bottom:2px solid #315f91}'
        . 'section{margin:24px 0;padding:20px;border:1px solid #ccd5df;border-radius:10px}'
        . 'li{margin:12px 0}textarea{width:100%;box-sizing:border-box;font:inherit}'
        . 'button,.file{display:inline-block;padding:10px 16px;margin:6px;border:0;border-radius:7px;'
        . 'background:#245f9c;color:#fff;font-weight:700;cursor:pointer}'
        . '#timer{font:700 1.3rem monospace}.description{white-space:pre-wrap}</style></head><body>'
        . '<header><strong id="exam-title"></strong><br>Участник: <span id="participant"></span>'
        . ' · Осталось: <span id="timer"></span></header><main id="questions"></main>'
        . '<p id="message" role="status"></p><button type="button" id="download">'
        . 'Скачать черновик / результат</button>'
        . '<label class="file">Восстановить черновик<input id="restore" type="file" '
        . 'accept="application/json" hidden></label>'
        . '<p>Файл результата передайте организатору. Повторный импорт того же файла безопасен.</p>'
        . '<script nonce="' . $nonce . '">' . $script . '</script></body></html>';
}
