<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AuthLogTest extends TestCase
{
    public function testCookieDiagnosticsDistinguishDuplicatesWithoutLoggingValues(): void
    {
        require_once dirname(__DIR__) . '/shared/code/tce_functions_auth_log.php';
        $file = tempnam(sys_get_temp_dir(), 'auth-cookie-');
        self::assertIsString($file);
        $previous = ini_get('error_log');
        $before = [$_SERVER, $_POST, $_COOKIE, $_SESSION ?? [], \TCExamSessionHandler::$readStatus,
            \TCExamSessionHandler::$cookieRecovered];
        try {
            ini_set('error_log', $file);
            $_POST = ['csrf_token' => 'private-csrf'];
            $_COOKIE = ['PHPSESSID' => 'invalid-cookie-secret'];
            $_SERVER['HTTP_COOKIE'] = 'PHPSESSID=invalid-cookie-secret; PHPSESSID=other-secret';
            $_SESSION = [];
            \TCExamSessionHandler::$readStatus = 'missing';
            \TCExamSessionHandler::$cookieRecovered = false;
            \openvsosh_log_auth_event('csrf.rejected', 'token_mismatch');
            $_COOKIE = ['PHPSESSID' => str_repeat('a', 32)];
            $_SERVER['HTTP_COOKIE'] = 'PHPSESSID=' . $_COOKIE['PHPSESSID'];
            $_SESSION = ['session_hash' => \get_stable_client_fingerprint()];
            \TCExamSessionHandler::$readStatus = 'loaded';
            \TCExamSessionHandler::$cookieRecovered = true;
            \openvsosh_log_auth_event('csrf.rejected', 'token_mismatch');
            $output = (string) file_get_contents($file);
            self::assertStringNotContainsString('cookie-secret', $output);
            self::assertStringNotContainsString('other-secret', $output);
            self::assertStringNotContainsString('private-csrf', $output);
            self::assertStringNotContainsString(str_repeat('a', 32), $output);
            self::assertStringNotContainsString(\get_stable_client_fingerprint(), $output);
            $entries = [];
            foreach (explode("\n", trim($output)) as $line) {
                $entries[] = json_decode(substr($line, (int) strpos($line, '{')), true, 512, JSON_THROW_ON_ERROR);
            }
            self::assertCount(2, $entries);
            self::assertIsArray($entries[0]);
            self::assertIsArray($entries[1]);
            self::assertFalse($entries[0]['session_cookie_valid']);
            self::assertFalse($entries[0]['session_cookie_recovered']);
            self::assertFalse($entries[0]['session_id_matches_cookie']);
            self::assertSame(2, $entries[0]['session_cookie_count']);
            self::assertSame('missing', $entries[0]['session_read_status']);
            self::assertSame('missing', $entries[0]['session_context_kind']);
            self::assertTrue($entries[1]['session_cookie_valid']);
            self::assertTrue($entries[1]['session_cookie_recovered']);
            self::assertSame(1, $entries[1]['session_cookie_count']);
            self::assertSame('loaded', $entries[1]['session_read_status']);
            self::assertSame('stable', $entries[1]['session_context_kind']);
        } finally {
            ini_set('error_log', (string) $previous);
            [$_SERVER, $_POST, $_COOKIE, $_SESSION, \TCExamSessionHandler::$readStatus,
                \TCExamSessionHandler::$cookieRecovered] = $before;
            unlink($file);
        }
    }

    public function testRejectedContextCategoriesDoNotExposeSessionOrTokens(): void
    {
        require_once dirname(__DIR__) . '/shared/code/tce_functions_auth_log.php';
        $file = tempnam(sys_get_temp_dir(), 'auth-context-');
        self::assertIsString($file);
        $previous = ini_get('error_log');
        $session = $_SESSION ?? [];
        $post = $_POST;
        try {
            ini_set('error_log', $file);
            $cases = [
                ['v2.' . str_repeat('a', 32) . '.' . str_repeat('b', 64), 'v2', true],
                ['$2y$10$' . str_repeat('a', 53), 'legacy', false],
                ['secret-invalid-token', 'invalid', false],
                [[], 'invalid', false],
                ['', 'missing', false],
            ];
            foreach ($cases as [$token, $format, $matches]) {
                $_POST = ['csrf_token' => $token];
                $_SESSION = $matches ? ['session_hash' => \get_client_fingerprint()] : [];
                \openvsosh_log_auth_event('csrf.rejected', 'token_mismatch');
            }
            $output = (string) file_get_contents($file);
            self::assertStringNotContainsString('secret-invalid-token', $output);
            self::assertStringNotContainsString(\get_client_fingerprint(), $output);
            self::assertStringNotContainsString(str_repeat('b', 64), $output);
            $lines = explode("\n", trim($output));
            self::assertCount(count($cases), $lines);
            foreach ($lines as $i => $line) {
                /** @var array{csrf_format:string,session_context_present:bool,session_fingerprint_matches:bool} $entry */
                $entry = json_decode(substr($line, (int) strpos($line, '{')), true, 512, JSON_THROW_ON_ERROR);
                $case = $cases[$i] ?? null;
                self::assertNotNull($case);
                self::assertSame($case[1], $entry['csrf_format']);
                self::assertSame($case[2], $entry['session_context_present']);
                self::assertSame($case[2], $entry['session_fingerprint_matches']);
            }
        } finally {
            ini_set('error_log', (string) $previous);
            $_SESSION = $session;
            $_POST = $post;
            unlink($file);
        }
    }

    public function testEventsAreCorrelatedAndDoNotExposeSecrets(): void
    {
        require_once dirname(__DIR__) . '/shared/code/tce_functions_auth_log.php';
        $file = tempnam(sys_get_temp_dir(), 'auth-log-');
        self::assertIsString($file);
        $previous = ini_get('error_log');
        $server = $_SERVER;
        $post = $_POST;
        $cookies = $_COOKIE;
        try {
            ini_set('error_log', $file);
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'SCRIPT_NAME' => '/public/code/index.php',
                'REMOTE_ADDR' => '192.0.2.1',
                'HTTP_USER_AGENT' => "Browser\r\nforged-entry",
                'HTTP_AUTHORIZATION' => 'Bearer secret-bearer',
                'QUERY_STRING' => 'password=secret-query',
            ];
            $_POST = [
                'xuser_name' => 'st980251',
                'xuser_password' => 'secret-password',
                'csrf_token' => 'secret-csrf',
                'xuser_otpcode' => 'secret-otp',
            ];
            $_COOKIE = ['PHPSESSID' => 'secret-session'];
            \openvsosh_log_auth_event('login.attempt');
            \openvsosh_log_auth_event('login.rejected', 'authentication_failed');
            $output = file_get_contents($file);
            self::assertIsString($output);
            self::assertStringNotContainsString('secret-', $output);
            $lines = array_values(array_filter(explode("\n", trim($output))));
            self::assertCount(2, $lines);
            $entries = [];
            foreach ($lines as $line) {
                $start = strpos($line, '{');
                self::assertIsInt($start);
                $entries[] = json_decode(substr($line, $start), true, 512, JSON_THROW_ON_ERROR);
            }
            /** @var array{0:array{login:string,ip:string,session_cookie_present:bool,request_id:string},1:array{request_id:string,reason:string}} $entries */
            self::assertSame('st980251', $entries[0]['login']);
            self::assertSame('192.0.2.1', $entries[0]['ip']);
            self::assertTrue($entries[0]['session_cookie_present']);
            self::assertSame($entries[0]['request_id'], $entries[1]['request_id']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $entries[0]['request_id']);
            self::assertSame('authentication_failed', $entries[1]['reason']);
        } finally {
            ini_set('error_log', (string) $previous);
            $_SERVER = $server;
            $_POST = $post;
            $_COOKIE = $cookies;
            unlink($file);
        }
    }
}
