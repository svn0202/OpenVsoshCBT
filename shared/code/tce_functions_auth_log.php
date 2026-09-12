<?php

/** Structured authentication events sent to the configured PHP error log. */
function openvsosh_log_auth_event(string $event, string $reason = ''): void
{
    require_once __DIR__ . '/tce_functions_request_log.php';
    $request_id = openvsosh_request_id();

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
    if ($event === 'csrf.rejected' || $event === 'session.rejected') {
        // Diagnostic categories only: never emit tokens, session IDs or fingerprint hashes.
        $cookie = $_COOKIE['PHPSESSID'] ?? null;
        $entry['session_cookie_valid'] = is_string($cookie)
            && preg_match('/\A[a-f0-9]{32}\z/', $cookie) === 1;
        $active_id = session_id();
        $entry['session_id_matches_cookie'] = is_string($cookie)
            && is_string($active_id) && $active_id !== '' && hash_equals($active_id, $cookie);
        $cookie_header = $_SERVER['HTTP_COOKIE'] ?? null;
        $entry['session_cookie_count'] = is_string($cookie_header)
            ? preg_match_all('/(?:^|;)\s*PHPSESSID=/', $cookie_header) : null;
        $entry['session_read_status'] = TCExamSessionHandler::$readStatus;
        $session_hash = $_SESSION['session_hash'] ?? null;
        $entry['session_context_present'] = is_string($session_hash) && $session_hash !== '';
        $entry['session_fingerprint_matches'] = is_string($session_hash)
            && f_session_fingerprint_matches($session_hash);
        $entry['session_context_kind'] = match (true) {
            !is_string($session_hash) || $session_hash === '' => 'missing',
            hash_equals(get_stable_client_fingerprint(), $session_hash) => 'stable',
            hash_equals(get_v1_client_fingerprint(), $session_hash) => 'v1',
            hash_equals(get_legacy_client_fingerprint(), $session_hash) => 'legacy',
            default => 'unmatched',
        };
        $token = $_POST['csrf_token'] ?? null;
        $entry['csrf_format'] = match (true) {
            $token === null || $token === '' => 'missing',
            !is_string($token) => 'invalid',
            preg_match('/\Av2\.[a-f0-9]{32}\.[a-f0-9]{64}\z/', $token) === 1 => 'v2',
            preg_match('/\A\$2y\$(?:10|12)\$[.\/A-Za-z0-9]{53}\z/', $token) === 1 => 'legacy',
            default => 'invalid',
        };
        $entry['fetch_mode'] = $text($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '', 32);
        $entry['fetch_destination'] = $text($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '', 32);
    }
    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (is_string($json)) {
        error_log('[openvsosh.auth] ' . $json);
    }
}
