<?php

/** Structured authentication events sent to the configured PHP error log. */
function openvsosh_log_auth_event(string $event, string $reason = ''): void
{
    static $request_id = null;
    if ($request_id === null) {
        try {
            $request_id = bin2hex(random_bytes(16));
        } catch (\Random\RandomException) {
            // Correlation only: an unavailable entropy source must not break login.
            $request_id = uniqid('auth-', true);
        }
    }

    // Explicit allowlist: never serialize POST, cookies, session contents or headers wholesale.
    $text = static function (mixed $value, int $limit): string {
        return is_string($value) ? substr($value, 0, $limit) : '';
    };
    $entry = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'event' => $event,
        'reason' => $reason,
        'request_id' => $request_id,
        'method' => $text($_SERVER['REQUEST_METHOD'] ?? '', 16),
        'path' => $text($_SERVER['SCRIPT_NAME'] ?? '', 512),
        // REMOTE_ADDR is normalized by the trusted web-server proxy configuration.
        'ip' => $text($_SERVER['REMOTE_ADDR'] ?? '', 64),
        'user_agent' => $text($_SERVER['HTTP_USER_AGENT'] ?? '', 512),
        'login' => $text($_POST['xuser_name'] ?? '', 255),
        'session_cookie_present' => isset($_COOKIE['PHPSESSID']),
    ];
    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (is_string($json)) {
        error_log('[openvsosh.auth] ' . $json);
    }
}
