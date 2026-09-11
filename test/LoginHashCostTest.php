<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class LoginHashCostTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('otpModes')]
    public function testOnlyExternalAccountWritesCreatePasswordHashes(string $otpMode): void
    {
        $script = <<<'PHP'
namespace Harness;
use DateTime;
define('K_OTP_LOGIN', $argv[2] !== 'off'); define('K_TABLE_USERS', 'users');
define('K_TABLE_SESSIONS', 'sessions'); define('K_USER_GROUP_RSYNC', false);
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
function get_password_hash($p) { ++$GLOBALS['hashes']; return 'new-hash-' . $GLOBALS['hashes']; }
function check_password($p, $hash) { ++$GLOBALS['verifications']; return $GLOBALS['case'] === 'local-ok'; }
function openvsosh_authorization_bool($v) { return (bool) $v; }
function openvsosh_authorization_string($v) { return (string) $v; }
function openvsosh_authorization_row($v) { return $v; }
function F_escape_sql($db, $v) { return $v; }
function get_normalized_ip($ip) { return $ip; }
function F_db_query($sql, $db) { $GLOBALS['queries'][] = $sql; return $sql; }
function f_get_otp(...$args) { return '123456'; }
function F_db_fetch_array($result) {
    if (str_contains($result, 'FROM sessions')) { return false; }
    if ($GLOBALS['case'] === 'external-new') { return false; }
    return ['user_id' => 7, 'user_name' => 'fixture', 'user_password' => 'stored-hash',
        'user_level' => 1, 'user_firstname' => 'Synthetic', 'user_lastname' => 'User'];
}
function F_check_unique(...$args) { return $GLOBALS['case'] === 'external-new'; }
function F_db_insert_id(...$args) { return 8; }
function F_display_db_error() { throw new \RuntimeException('unexpected DB error'); }
function F_print_error(...$args) {}
function f_sync_user_groups(...$args) {}
function f_empty_to_null($v) { return "'" . $v . "'"; }
function openvsosh_log_auth_event(...$args) {}
$source = file_get_contents($argv[1]);
$start = strpos($source, '    if ($bruteforce) {');
$end = strpos($source, '        openvsosh_log_auth_event(', $start + strlen('    if ($bruteforce) {'));
// Skip the rate-limit log and stop before the login outcome logging block.
$end = strpos($source, "\n        openvsosh_log_auth_event(\n", $start);
$block = substr($source, $start, $end - $start) . "\n}";
$checks = [];
foreach (['local-ok' => 0, 'local-wrong' => 0, 'external-sync' => 1, 'external-new' => 1] as $case => $expected) {
    $GLOBALS['case'] = $case; $GLOBALS['hashes'] = 0; $GLOBALS['verifications'] = 0; $GLOBALS['queries'] = [];
    $bruteforce = false; $logged = false; $login_error = false; $db = 'db';
    $submitted_username = 'fixture'; $submitted_password = 'synthetic';
    $submitted_otpcode = $argv[2] === 'valid' ? '123456' : 'invalid';
    $m = ['user_otpkey' => 'fixture-key'];
    $authorizedOtp = $argv[2] !== 'invalid';
    $server = ['REMOTE_ADDR' => '127.0.0.1']; $l = ['m_login_wrong' => 'Wrong'];
    $_SESSION = []; $_COOKIE = [];
    $altusr = str_starts_with($case, 'external') ? [
        'user_email' => '', 'user_regnumber' => '', 'user_firstname' => '', 'user_lastname' => '',
        'user_birthdate' => '', 'user_birthplace' => '', 'user_ssn' => '', 'user_level' => 1,
        'usrgrp_group_id' => [],
    ] : false;
    eval('namespace Harness; ' . $block);
    $checks[$case . ' hashes'] = $GLOBALS['hashes'] === ($authorizedOtp ? $expected : 0);
    $checks[$case . ' logged'] = $logged === ($authorizedOtp && $case !== 'local-wrong');
    $checks[$case . ' verifies existing password'] = $GLOBALS['verifications'] === (!$authorizedOtp || $case === 'external-new' ? 0 : 1);
    if ($case === 'external-sync') {
        $checks['same hash in UPDATE and SELECT'] = count(array_filter($GLOBALS['queries'], fn($q) => str_contains($q, 'new-hash-1'))) === ($authorizedOtp ? 2 : 0);
    }
}
echo json_encode($checks, JSON_THROW_ON_ERROR);
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/shared/code/tce_authorization.php', $otpMode],
            dirname(__DIR__),
        );
        self::assertSame(0, $status, $output);
        $checks = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($checks);
        self::assertCount(13, $checks);
        foreach ($checks as $name => $passed) {
            self::assertTrue($passed, (string) $name);
        }
    }
    /** @return array<string,array{string}> */
    public static function otpModes(): array
    {
        return ['disabled' => ['off'], 'valid OTP' => ['valid'], 'invalid OTP' => ['invalid']];
    }

}
