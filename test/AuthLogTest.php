<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AuthLogTest extends TestCase
{
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
