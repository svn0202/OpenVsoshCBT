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

    public function testPlainCsrfTokenUsesEntryScriptSessionAndFingerprint(): void
    {
        $included_files = get_included_files();
        self::assertNotEmpty($included_files);

        /** @var non-empty-list<non-empty-string> $included_files */
        self::assertSame(
            $included_files[0] . (string) session_id() . K_RANDOM_SECURITY . \get_client_fingerprint(),
            \getPlainCSRFToken(),
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
        $hash = \getPasswordHash('s3cr3t-passphrase');
        $this->assertIsString($hash);
        $this->assertNotSame('s3cr3t-passphrase', $hash); // never stored in clear
        $this->assertTrue(\checkPassword('s3cr3t-passphrase', $hash));
        $this->assertFalse(\checkPassword('wrong', $hash));
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
        $token = \getPasswordHash(\getPlainCSRFTokenForScript($script));

        $this->assertTrue(\checkCSRFTokenForScript($token, $script));
        $this->assertFalse(\checkCSRFTokenForScript($token, '/srv/tcexam/public/code/other.php'));
    }

    public function testDefaultCsrfTokenRoundTrips(): void
    {
        $plain = \getPlainCSRFToken();
        $token = \F_getCSRFToken();

        self::assertNotSame('', $plain);
        self::assertTrue(\checkCSRFToken($token));
    }
}
