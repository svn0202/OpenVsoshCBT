<?php

//============================================================+
// File name   : TCExamSessionHandler.php
// Begin       : 2001-09-26
// Last Update : 2026-03-01
//
// Description : User-level session storage functions.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * User-level session storage functions.<br>
 * This script uses the session_set_save_handler() function to set the user-level session storage functions which are used for storing and retrieving data associated with a session.<br>
 * The session data is stored on a local database.
 * NOTE: This script automatically starts the user's session.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2001-09-26
 */

// PHP session settings
//ini_set('session.save_handler', 'user');
session_name('PHPSESSID');
//ini_set('session.gc_maxlifetime', K_SESSION_LIFE);
// @mago-expect lint:no-ini-set -- sessions are intentionally cookie-only across every entry point
ini_set('session.use_cookies', true);
// @mago-expect lint:no-ini-set -- reject attacker-supplied uninitialized session identifiers
ini_set('session.use_strict_mode', 'On');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => (string) ini_get('session.cookie_path'),
    'domain' => (string) ini_get('session.cookie_domain'),
    'secure' => K_COOKIE_SECURE,
    'httponly' => K_COOKIE_HTTPONLY,
    'samesite' => K_COOKIE_SAMESITE,
]);

/**
 * Return the baseline security headers used by every authenticated and public endpoint.
 * Keep the CSP deliberately narrow: the legacy UI still contains inline scripts and styles,
 * while frame-ancestors can be enforced independently without breaking those pages.
 *
 * @return array{
 *   X-Content-Type-Options:string,
 *   X-Frame-Options:string,
 *   Referrer-Policy:string,
 *   Content-Security-Policy:string,
 *   Strict-Transport-Security?:string
 * }
 */
function f_get_security_headers(): array
{
    $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "frame-ancestors 'self'",
    ];
    if (K_COOKIE_SECURE) {
        $headers['Strict-Transport-Security'] = 'max-age=31536000';
    }
    return $headers;
}

/** Send baseline security headers before any response body is emitted. */
function F_sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    foreach (f_get_security_headers() as $name => $value) {
        header($name . ': ' . $value);
    }
}

if (PHP_SAPI !== 'cli') {
    F_sendSecurityHeaders();
}

/**
 * Session Handler Class implementing SessionHandlerInterface
 * @package com.tecnick.tcexam.shared
 */
class TCExamSessionHandler implements SessionHandlerInterface
{
    /**
     * Open session.
     * @param string $path path were to store session data
     * @param string $name name of session
     * @return bool always TRUE
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Close session.<br>
     * Call garbage collector function to remove expired sessions.
     * @return bool always TRUE
     */
    public function close(): bool
    {
        $this->gc((int) ini_get('session.gc_maxlifetime'));
        return true;
    }

    /**
     * Get session data.
     * @param string $id session ID.
     * @return string|false session data or false on failure.
     */
    public function read(string $id): string|false
    {
        global $db;
        $id = F_escape_sql($db, $id);
        $sql =
            'SELECT cpsession_data
				FROM '
            . K_TABLE_SESSIONS
            . '
				WHERE cpsession_id=\''
            . $id
            . '\'
					AND cpsession_expiry>=\''
            . date(K_TIMESTAMP_FORMAT)
            . '\'
				LIMIT 1';
        if ($r = F_db_query($sql, $db)) {
            if ($m = F_db_fetch_array($r)) {
                return $m['cpsession_data'];
            }

            return '';
        }

        return '';
    }

    /**
     * Insert or Update session.
     * @param string $id session ID.
     * @param string $data session data.
     * @return bool true on success, false on failure.
     */
    public function write(string $id, string $data): bool
    {
        global $db;
        // workaround for PHP bug 41230
        if (
            (!isset($db) || !$db)
            && !($db = F_db_connect(
                K_DATABASE_HOST,
                K_DATABASE_PORT,
                K_DATABASE_USER_NAME,
                K_DATABASE_USER_PASSWORD,
                K_DATABASE_NAME,
            ))
        ) {
            return false;
        }

        $id = F_escape_sql($db, $id);
        $data = F_escape_sql($db, $data);
        $expiry = date(K_TIMESTAMP_FORMAT, time() + K_SESSION_LIFE);
        // check if this session already exist on database
        $sql = 'SELECT cpsession_id
				FROM ' . K_TABLE_SESSIONS . '
				WHERE cpsession_id=\'' . $id . '\'
				LIMIT 1';
        if ($r = F_db_query($sql, $db)) {
            if ($m = F_db_fetch_array($r)) {
                // SQL to update existing session
                $sqlup =
                    'UPDATE '
                    . K_TABLE_SESSIONS
                    . ' SET
					cpsession_expiry=\''
                    . $expiry
                    . '\',
					cpsession_data=\''
                    . $data
                    . '\'
					WHERE cpsession_id=\''
                    . $id
                    . "'";
            } else {
                // SQL to insert new session
                $sqlup =
                    'INSERT INTO '
                    . K_TABLE_SESSIONS
                    . ' (
					cpsession_id,
					cpsession_expiry,
					cpsession_data
					) VALUES (
					\''
                    . $id
                    . '\',
					\''
                    . $expiry
                    . '\',
					\''
                    . $data
                    . '\'
					)';
            }

            return F_db_query($sqlup, $db) !== false;
        }

        return false;
    }

    /**
     * Deletes the specific session.
     * @param string $id session ID of session to destroy.
     * @return bool true on success, false on failure.
     */
    public function destroy(string $id): bool
    {
        global $db;
        $id = F_escape_sql($db, $id);
        $sql = 'DELETE FROM ' . K_TABLE_SESSIONS . " WHERE cpsession_id='" . $id . "'";
        return F_db_query($sql, $db) !== false;
    }

    /**
     * Garbage collector.<br>
     * Deletes expired sessions.<br>
     * NOTE: while time() function returns a 32 bit integer, it works fine until year 2038.
     * @param int $max_lifetime max session lifetime in seconds.
     * @return int|false number of deleted sessions or false on failure.
     */
    public function gc(int $max_lifetime): int|false
    {
        global $db;
        $expiry_time = date(K_TIMESTAMP_FORMAT);
        $sql = 'DELETE FROM ' . K_TABLE_SESSIONS . " WHERE cpsession_expiry<='" . $expiry_time . "'";
        if (!($r = F_db_query($sql, $db))) {
            return false;
        }

        return F_db_affected_rows($db, $r);
    }
}

/**
 * Convert encoded session string data to array.
 * @author Nicola Asuni
 * @since 2001-10-18
 * @param string $sd input data string
 * @return array<string, mixed>
 */
function f_session_string_to_array(string $sd): array
{
    $sess_array = [];
    /** @var list<string> $vars */
    $vars = preg_split('/[;}]/', $sd);
    array_pop($vars);
    foreach ($vars as $var) {
        /** @var array{0: string, 1: string} $parts */
        $parts = explode('|', $var);
        $key = $parts[0];
        /** @var null|bool|int|float|string|array<array-key, mixed>|object $val */
        $val = unserialize($parts[1] . ';');
        $sess_array[$key] = $val;
    }

    return $sess_array;
}

/**
 * Generate a client fingerprint (unique ID for the client browser)
 * @author Nicola Asuni
 * @since 2010-10-04
 * @return string client ID
 */
function get_client_fingerprint(): string
{
    $sid = K_RANDOM_SECURITY;
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $sid .= $_SERVER['HTTP_USER_AGENT'];
    }

    if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
        $sid .= $_SERVER['HTTP_ACCEPT_ENCODING'];
    }

    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $sid .= $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    }

    if (isset($_SERVER['HTTP_DNT'])) {
        $sid .= $_SERVER['HTTP_DNT'];
    }

    return md5($sid);
}

/**
 * Return the pre-AJAX fingerprint for a one-time migration of active sessions.
 *
 * Accept and Upgrade-Insecure-Requests vary between document requests and
 * fetch(), so they must not be part of the current fingerprint.
 */
function getLegacyClientFingerprint(): string
{
    $sid = K_RANDOM_SECURITY;
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $sid .= $_SERVER['HTTP_USER_AGENT'];
    }

    if (isset($_SERVER['HTTP_ACCEPT'])) {
        $sid .= $_SERVER['HTTP_ACCEPT'];
    }

    if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
        $sid .= $_SERVER['HTTP_ACCEPT_ENCODING'];
    }

    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $sid .= $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    }

    if (isset($_SERVER['HTTP_DNT'])) {
        $sid .= $_SERVER['HTTP_DNT'];
    }

    if (isset($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'])) {
        $sid .= $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'];
    }

    return md5($sid);
}

/**
 * Generate and return a new session ID.
 * @author Nicola Asuni
 * @since 2010-10-04
 * @return string PHPSESSID
 */
function getNewSessionID(): string
{
    // The database schema stores 32 characters. Sixteen CSPRNG bytes preserve that shape while
    // providing the full 128 bits of entropy directly, without timestamp- or hash-based mixing.
    try {
        return bin2hex(random_bytes(16));
    } catch (Random\RandomException $exception) {
        // Session and verification identifiers must fail closed if the operating system CSPRNG
        // is unavailable. Error is intentionally unchecked by the analyzer and terminates safely.
        throw new Error('Secure random number generation is unavailable.', 0, $exception);
    }
}

/**
 * Hash password for Database storing.
 * @param string $password Password to hash.
 * @return string password hash
 */
function getPasswordHash(#[\SensitiveParameter] string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifies that a password matches a hash
 * @param string $password The password to verify
 * @param string $hash Password hash
 *
 * @return boolean
 */
function checkPassword(#[\SensitiveParameter] string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Returns true when K_RANDOM_SECURITY has been set to a unique per-installation secret,
 * i.e. it is not empty and not one of the values shipped in config.default.
 * Security-sensitive checks that rely on the secrecy of K_RANDOM_SECURITY (e.g. the
 * result-access token that bypasses the normal authorization check) MUST fail closed
 * when this returns false, otherwise an attacker who knows the public default can forge
 * a valid token. The installer replaces the placeholder with a random value at install time.
 * @param string|null $secret Secret to check, or null to use K_RANDOM_SECURITY.
 * @return boolean true if the seed is configured, false if it is still the shipped default.
 */
function f_is_random_security_configured(#[\SensitiveParameter] ?string $secret = null): bool
{
    // Known-insecure values: empty, the current shipped placeholder, and the historical default.
    $insecure = ['', 'CHANGE_THIS_K_RANDOM_SECURITY', 'mkTzxf8WwUxwvj6w'];
    // Default to the configured constant; an explicit $secret is accepted for testability.
    if ($secret === null) {
        $secret = defined('K_RANDOM_SECURITY') ? K_RANDOM_SECURITY : '';
    }

    return !in_array($secret, $insecure, true);
}

/**
 * Return true only for an origin-relative URI that is safe to copy into a Location header.
 * Network-path references (//host) and backslashes are rejected because browsers may interpret
 * them as an external authority even though the value begins with a slash.
 */
function f_is_safe_local_redirect_uri(string $uri): bool
{
    if (
        $uri === ''
        || !str_starts_with($uri, '/')
        || str_starts_with($uri, '//')
        || str_contains($uri, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $uri) === 1
    ) {
        return false;
    }

    $parts = parse_url($uri);
    return is_array($parts)
        && !isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'])
        && isset($parts['path'])
        && str_starts_with($parts['path'], '/');
}

/**
 * Generate unencoded CSRF token string
 *
 * @return string
 */
function getPlainCSRFToken(): string
{
    /** @var non-empty-list<non-empty-string> $inc */
    $inc = get_included_files();
    return getPlainCSRFTokenForScript($inc[0]);
}

/**
 * Generate an unencoded CSRF token for a known same-application script.
 */
function getPlainCSRFTokenForScript(string $script): string
{
    return $script . (string) session_id() . K_RANDOM_SECURITY . get_client_fingerprint();
}

/**
 * Check the CSRF token
 * @param $token (string) tocken to check
 *
 * @return boolean
 */
function checkCSRFToken(#[\SensitiveParameter] string $token): bool
{
    return checkPassword(getPlainCSRFToken(), $token);
}

/**
 * Check a CSRF token generated by another endpoint in the same workflow.
 */
function checkCSRFTokenForScript(#[\SensitiveParameter] string $token, string $script): bool
{
    return checkPassword(getPlainCSRFTokenForScript($script), $token);
}

/**
 * Generate CSRF token
 *
 * @return string
 */
function F_getCSRFToken(): string
{
    return getPasswordHash(getPlainCSRFToken());
}

// ------------------------------------------------------------

// The web session bootstrap below must not run under the CLI SAPI (e.g. PHPUnit), where there is
// no HTTP request context and the DB-backed save handler has no connection. Returning here keeps
// every function/class defined above available for unit testing. All web SAPIs — including the
// `php -S` development server (cli-server) — fall through and initialise the session as before.
if (PHP_SAPI === 'cli') {
    return;
}

// Sets user-level session storage functions using SessionHandlerInterface.
session_set_save_handler(new TCExamSessionHandler(), true);

// start user session
if (isset($_COOKIE['PHPSESSID'])) {
    // cookie takes precedence
    $_REQUEST['PHPSESSID'] = $_COOKIE['PHPSESSID'];
}

if (isset($_REQUEST['PHPSESSID'])) {
    // sanitize $PHPSESSID from get/post/cookie
    $PHPSESSID = preg_replace('/[^0-9a-f]*/', '', $_REQUEST['PHPSESSID']);
    if (strlen($PHPSESSID) !== 32) {
        // generate new ID
        $PHPSESSID = getNewSessionID();
    }
} else {
    // create new PHPSESSID
    $PHPSESSID = getNewSessionID();
}

if (!isset($_REQUEST['menu_mode']) || $_REQUEST['menu_mode'] !== 'startlongprocess') {
    // fix flush problem on long processes
    session_id($PHPSESSID); //set session id
}

session_start(); //start session
header('Cache-control: private'); // fix IE6 bug
