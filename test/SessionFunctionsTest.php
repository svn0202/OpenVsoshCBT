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

use PHPUnit\Framework\TestCase;

/**
 * @file
 * Tests for password hashing and the K_RANDOM_SECURITY fail-closed guard (H1 regression).
 * @package com.tecnick.tcexam.test
 */
final class SessionFunctionsTest extends TestCase
{
    public function testPasswordHashRoundTrip(): void
    {
        $hash = \getPasswordHash('s3cr3t-passphrase');
        $this->assertIsString($hash);
        $this->assertNotSame('s3cr3t-passphrase', $hash); // never stored in clear
        $this->assertTrue(\checkPassword('s3cr3t-passphrase', $hash));
        $this->assertFalse(\checkPassword('wrong', $hash));
    }

    /**
     * H1 regression guard: the result-access token must fail closed while K_RANDOM_SECURITY is
     * left at any shipped/insecure value, so a default install cannot be probed with forged tokens.
     */
    public function testRandomSecurityRejectsInsecureValues(): void
    {
        $this->assertFalse(\F_isRandomSecurityConfigured(''));
        $this->assertFalse(\F_isRandomSecurityConfigured('CHANGE_THIS_K_RANDOM_SECURITY'));
        $this->assertFalse(\F_isRandomSecurityConfigured('mkTzxf8WwUxwvj6w'));
    }

    public function testRandomSecurityAcceptsConfiguredSecret(): void
    {
        $this->assertTrue(\F_isRandomSecurityConfigured(\bin2hex(\random_bytes(16))));
        // the no-argument form reads the configured K_RANDOM_SECURITY from the test bootstrap
        $this->assertTrue(\F_isRandomSecurityConfigured());
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
            $documentFingerprint = \getClientFingerprint();
            $legacyDocumentFingerprint = \getLegacyClientFingerprint();

            $_SERVER['HTTP_ACCEPT'] = 'application/json';
            unset($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS']);

            $this->assertSame($documentFingerprint, \getClientFingerprint());
            $this->assertNotSame($legacyDocumentFingerprint, \getLegacyClientFingerprint());
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
}
