<?php

/** Correlation only; this ID must never be used for authorization. */
function openvsosh_request_id(): string
{
    static $id = null;
    if ($id === null) {
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        // The public edge must overwrite this header, not append or trust a client value.
        if (is_string($incoming) && preg_match('/^[a-f0-9]{32}$/D', $incoming) === 1) {
            $id = $incoming;
        } else {
            try {
                $id = bin2hex(random_bytes(16));
            } catch (\Random\RandomException) {
                // Correlation is not a security token; logging must not block login.
                $id = substr(hash('sha256', uniqid('', true)), 0, 32);
            }
        }
    }
    if (!headers_sent()) {
        header('X-Request-ID: ' . $id);
    }
    return $id;
}

/** Only pass explicitly selected non-secret fields. */
function openvsosh_log_answer_event(string $channel, array $fields): void
{
    $entry = ['timestamp' => gmdate('Y-m-d\TH:i:s\Z'), 'request_id' => openvsosh_request_id()];
    foreach (['status', 'http_status', 'testid', 'testlogid', 'operation_id',
        'expected_version', 'result_version', 'duration_ms'] as $key) {
        if (array_key_exists($key, $fields)) {
            $entry[$key] = $fields[$key];
        }
    }
    error_log('[openvsosh.answer' . ($channel === '' ? '' : '.' . $channel) . '] '
        . json_encode($entry, JSON_INVALID_UTF8_SUBSTITUTE));
}
