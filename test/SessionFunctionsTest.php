<?php

//============================================================+
// File name   : SessionFunctionsTest.php
// Begin       : 2026-06-22
//
// Description : Unit tests for the auth/security helpers in
//               shared/code/tce_functions_session.php
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @file
 * Tests for password hashing and the K_RANDOM_SECURITY fail-closed guard (H1 regression).
 * @package com.tecnick.tcexam.test
 */
final class SessionFunctionsTest extends TestCase
{
    public function testSecurityHeadersAreSentInOrderUsingHeaderDefaults(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["calls"] = []; '
                    . 'function header(...$arguments) { $GLOBALS["calls"][] = $arguments; } '
                    . 'function f_get_security_headers() { return ["X-First" => "one", "X-Second" => "two"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (F_sendSecurityHeaders|f_send_security_headers)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualifiedName = __NAMESPACE__ . "\\\\" . $name; $qualifiedName(); '
                    . 'echo json_encode($GLOBALS["calls"]);',
                dirname(__DIR__) . '/shared/code/TCExamSessionHandler.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [['X-First: one'], ['X-Second: two']],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testSessionCookieConfigurationMatchesApplicationConstants(): void
    {
        self::assertSame('PHPSESSID', session_name());
        self::assertSame('1', ini_get('session.use_cookies'));
        self::assertSame('On', ini_get('session.use_strict_mode'));

        $params = session_get_cookie_params();
        self::assertSame(0, $params['lifetime']);
        self::assertSame(K_COOKIE_SECURE, $params['secure']);
        self::assertSame(K_COOKIE_HTTPONLY, $params['httponly']);
        self::assertSame(K_COOKIE_SAMESITE, $params['samesite']);
    }

    public function testSessionClosePassesConfiguredLifetimeAsInteger(): void
    {
        $handler = new class extends \TCExamSessionHandler {
            public ?int $received_lifetime = null;

            public function gc(int $max_lifetime): int|false
            {
                $this->received_lifetime = $max_lifetime;
                return 0;
            }
        };

        self::assertTrue($handler->close());
        self::assertSame((int) ini_get('session.gc_maxlifetime'), $handler->received_lifetime);
    }

    public function testDatabaseSessionHandlerPreservesReadWriteDestroyAndGarbageCollection(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_COOKIE_SECURE", false); define("K_COOKIE_HTTPONLY", true); '
                    . 'define("K_COOKIE_SAMESITE", "Lax"); define("K_TABLE_SESSIONS", "sessions"); '
                    . 'define("K_TIMESTAMP_FORMAT", "Y-m-d H:i:s"); define("K_SESSION_LIFE", 3600); '
                    . 'define("K_RANDOM_SECURITY", "secret"); $GLOBALS["db"] = "db-link"; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["query_results"] = []; $GLOBALS["rows"] = []; '
                    . 'function F_escape_sql($db, $value) { return addslashes($value); } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_db_affected_rows($db, $result) { '
                    . '$GLOBALS["affected"] = [$db, $result]; return 3; } '
                    . 'function F_db_connect(...$arguments) { $GLOBALS["connect"] = $arguments; return "connected"; } '
                    . 'require $argv[1]; $handler = new TCExamSessionHandler(); '
                    . '$GLOBALS["query_results"] = ["read-result"]; '
                    . '$GLOBALS["rows"] = [["cpsession_data" => "stored"]]; $read = $handler->read("sid"); '
                    . '$GLOBALS["query_results"] = ["read-result"]; $GLOBALS["rows"] = [false]; '
                    . '$missing = $handler->read("missing"); '
                    . '$GLOBALS["query_results"] = [false]; $failed = $handler->read("failed"); '
                    . '$GLOBALS["query_results"] = ["select-result", true]; $GLOBALS["rows"] = [["cpsession_id" => "sid"]]; '
                    . '$updated = $handler->write("sid\'", "payload\'"); '
                    . '$GLOBALS["query_results"] = ["select-result", true]; $GLOBALS["rows"] = [false]; '
                    . '$inserted = $handler->write("new", "data"); '
                    . '$GLOBALS["query_results"] = [true]; $destroyed = $handler->destroy("old"); '
                    . '$GLOBALS["query_results"] = ["delete-result"]; $collected = $handler->gc(1); '
                    . 'echo json_encode([[$read, $missing, $failed, $updated, $inserted, $destroyed, $collected], '
                    . '$GLOBALS["queries"], $GLOBALS["affected"], isset($GLOBALS["connect"])]);',
                dirname(__DIR__) . '/shared/code/TCExamSessionHandler.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: array{string, string, string, bool, bool, bool, int},
         *     1: array{string, string, string, string, string, string, string, string, string},
         *     2: array{string, string},
         *     3: bool
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['stored', '', '', true, true, true, 3], $decoded[0]);
        self::assertStringContainsString("WHERE cpsession_id='sid'", $decoded[1][0]);
        self::assertStringContainsString("WHERE cpsession_id='missing'", $decoded[1][1]);
        self::assertStringContainsString("WHERE cpsession_id='failed'", $decoded[1][2]);
        self::assertStringContainsString("WHERE cpsession_id='sid\\''", $decoded[1][3]);
        self::assertStringContainsString("UPDATE sessions SET", $decoded[1][4]);
        self::assertStringContainsString("cpsession_data='payload\\''", $decoded[1][4]);
        self::assertStringContainsString("WHERE cpsession_id='new'", $decoded[1][5]);
        self::assertStringContainsString('INSERT INTO sessions', $decoded[1][6]);
        self::assertSame("DELETE FROM sessions WHERE cpsession_id='old'", $decoded[1][7]);
        self::assertStringContainsString('DELETE FROM sessions WHERE cpsession_expiry<=', $decoded[1][8]);
        self::assertSame(['db-link', 'delete-result'], $decoded[2]);
        self::assertFalse($decoded[3]);
    }

    public function testPlainCsrfTokenUsesEntryScriptSessionAndFingerprint(): void
    {
        $included_files = get_included_files();
        self::assertNotEmpty($included_files);

        /** @var non-empty-list<non-empty-string> $included_files */
        self::assertSame(
            $included_files[0] . (string) session_id() . K_RANDOM_SECURITY . \get_client_fingerprint(),
            \get_plain_csrf_token(),
        );
    }

    public function testSessionStringDecoderPreservesKeysAndValueTypes(): void
    {
        self::assertSame(
            ['name' => 'Alice', 'attempts' => 2, 'enabled' => true],
            \F_session_string_to_array('name|s:5:"Alice";attempts|i:2;enabled|b:1;'),
        );
    }

    public function testSessionStringDecoderAcceptsEmptyData(): void
    {
        self::assertSame([], \F_session_string_to_array(''));
    }

    public function testSessionStringDecoderPreservesEmptyNullAndNegativeValues(): void
    {
        self::assertSame(
            ['empty' => '', 'nothing' => null, 'negative' => -3, 'ratio' => 1.5],
            \F_session_string_to_array('empty|s:0:"";nothing|N;negative|i:-3;ratio|d:1.5;'),
        );
    }

    public function testPasswordHashRoundTrip(): void
    {
        $hash = \get_password_hash('s3cr3t-passphrase');
        $this->assertIsString($hash);
        $this->assertNotSame('s3cr3t-passphrase', $hash); // never stored in clear
        $this->assertTrue(\check_password('s3cr3t-passphrase', $hash));
        $this->assertFalse(\check_password('wrong', $hash));
    }

    public function testNewSessionIdUsesSchemaCompatibleCSPRNGValue(): void
    {
        $first = \get_new_session_id();
        $second = \get_new_session_id();

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $first);
        $this->assertNotSame($first, $second);
    }

    public function testSecurityHeadersIncludeClickjackingAndTransportProtections(): void
    {
        $headers = \f_get_security_headers();

        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertSame("frame-ancestors 'self'", $headers['Content-Security-Policy']);
        $this->assertSame('max-age=31536000', $headers['Strict-Transport-Security'] ?? null);
    }

    #[DataProvider('localRedirectProvider')]
    public function testLocalRedirectValidation(string $uri, bool $expected): void
    {
        $this->assertSame($expected, \f_is_safe_local_redirect_uri($uri));
    }

    /** @return array<string,array{string,bool}> */
    public static function localRedirectProvider(): array
    {
        return [
            'ordinary path' => ['/public/code/index.php?lang=rus', true],
            'empty' => ['', false],
            'absolute URL' => ['https://evil.example/', false],
            'network-path reference' => ['//evil.example/', false],
            'backslash authority' => ['/\\evil.example/', false],
            'header injection' => ["/safe\r\nLocation: https://evil.example/", false],
        ];
    }

    /**
     * H1 regression guard: the result-access token must fail closed while K_RANDOM_SECURITY is
     * left at any shipped/insecure value, so a default install cannot be probed with forged tokens.
     */
    public function testRandomSecurityRejectsInsecureValues(): void
    {
        $this->assertFalse(\f_is_random_security_configured(''));
        $this->assertFalse(\f_is_random_security_configured('CHANGE_THIS_K_RANDOM_SECURITY'));
        $this->assertFalse(\f_is_random_security_configured('mkTzxf8WwUxwvj6w'));
    }

    /** @throws \Random\RandomException */
    public function testRandomSecurityAcceptsConfiguredSecret(): void
    {
        $this->assertTrue(\f_is_random_security_configured(\bin2hex(\random_bytes(16))));
        // the no-argument form reads the configured K_RANDOM_SECURITY from the test bootstrap
        $this->assertTrue(\f_is_random_security_configured());
        $this->assertSame(\f_is_random_security_configured(), \f_is_random_security_configured(null));
    }

    public function testClientFingerprintIsStableAcrossDocumentAndFetchHeaders(): void
    {
        $original = $_SERVER;
        try {
            $_SERVER['HTTP_USER_AGENT'] = 'Browser/1.0';
            $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, br';
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ru-RU';
            $_SERVER['HTTP_DNT'] = '1';
            $_SERVER['HTTP_ACCEPT'] = 'text/html';
            $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] = '1';
            $documentFingerprint = \get_client_fingerprint();
            $legacyDocumentFingerprint = \get_legacy_client_fingerprint();

            $_SERVER['HTTP_ACCEPT'] = 'application/json';
            unset($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS']);

            $this->assertSame($documentFingerprint, \get_client_fingerprint());
            $this->assertNotSame($legacyDocumentFingerprint, \get_legacy_client_fingerprint());
        } finally {
            $_SERVER = $original;
        }
    }

    public function testScriptScopedCsrfTokenCanBeCheckedByWorkflowEndpoint(): void
    {
        $script = '/srv/tcexam/public/code/tce_test_execute.php';
        $token = \get_password_hash(\get_plain_csrf_token_for_script($script));

        $this->assertTrue(\check_csrf_token_for_script($token, $script));
        $this->assertFalse(\check_csrf_token_for_script($token, '/srv/tcexam/public/code/other.php'));
    }

    public function testDefaultCsrfTokenRoundTrips(): void
    {
        $plain = \get_plain_csrf_token();
        $token = \f_get_csrf_token();

        self::assertNotSame('', $plain);
        self::assertTrue(\check_csrf_token($token));
    }
}
