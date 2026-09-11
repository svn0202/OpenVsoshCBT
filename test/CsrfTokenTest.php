<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class CsrfTokenTest extends TestCase
{
    public function testSessionScopeRandomnessAndStrictParsing(): void
    {
        self::runChecks(<<<'PHP'
$script = '/srv/app/public/code/tce_test_execute.php';
$token = f_get_csrf_token_for_script($script);
$other = f_get_csrf_token_for_script($script);
$wrongKeyMac = hash_hmac('sha256', 'openvsosh-csrf-v2' . strlen(session_id()) . ':' . session_id()
    . strlen($script) . ':' . $script . strlen(get_client_fingerprint()) . ':' . get_client_fingerprint()
    . '32:' . substr($token, 3, 32), str_repeat('different-key', 4));
$checks = [
    'exact format' => preg_match('/\Av2\.[a-f0-9]{32}\.[a-f0-9]{64}\z/', $token) === 1,
    'fresh nonce' => $token !== $other,
    'foreign installation key' => !check_csrf_token_for_script(substr($token, 0, 36) . $wrongKeyMac, $script),
    'round trip' => check_csrf_token_for_script($token, $script),
    'previous token remains valid' => check_csrf_token_for_script($token, $script),
    'second tab' => check_csrf_token_for_script($other, $script),
    'default entry script' => check_csrf_token(f_get_csrf_token()),
    'wrong script' => !check_csrf_token_for_script($token, $script . '.other'),
];
foreach (['', 'v1' . substr($token, 2), strtoupper($token), substr($token, 0, -1),
    $token . "\n", $token . "\0", $token . 'x', ' ' . $token, str_repeat('a', 100000),
    substr_replace($token, 'z', 4, 1), substr_replace($token, $token[40] === 'a' ? 'b' : 'a', 40, 1),
    substr_replace($token, $token[4] === 'a' ? 'b' : 'a', 4, 1)] as $i => $bad) {
    $checks['malformed ' . $i] = !check_csrf_token_for_script($bad, $script);
}
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] = '1';
$checks['document to fetch'] = check_csrf_token_for_script($token, $script);
$_SERVER['HTTP_USER_AGENT'] = 'different browser';
$checks['fingerprint changed'] = !check_csrf_token_for_script($token, $script);
unset($_SERVER['HTTP_USER_AGENT']);
session_id(str_repeat('b', 32));
$checks['foreign session'] = !check_csrf_token_for_script($token, $script);
session_id(str_repeat('a', 32));
$long = str_repeat('/very-long-scope', 20);
$longToken = f_get_csrf_token_for_script($long . '/one');
$checks['long scope round trip'] = check_csrf_token_for_script($longToken, $long . '/one');
$checks['long scope suffix is bound'] = !check_csrf_token_for_script($longToken, $long . '/two');
session_id('');
$checks['missing session rejects'] = !check_csrf_token_for_script($token, $script);
try { f_get_csrf_token_for_script($script); $checks['missing session issuance'] = false; }
catch (Error $e) { $checks['missing session issuance'] = true; }
echo json_encode($checks, JSON_THROW_ON_ERROR);
PHP);
    }

    public function testLegacyTransitionHasFixedDeadlineAndBoundedCost(): void
    {
        self::runChecks(<<<'PHP'
$script = '/srv/app/execute.php';
$legacy = password_hash(get_plain_csrf_token_for_script($script), PASSWORD_BCRYPT, ['cost' => 10]);
$checks = ['legacy disabled by default' => !check_csrf_token_for_script($legacy, $script)];
putenv('OPENVSOSH_CSRF_LEGACY_UNTIL=' . (time() + 600));
$checks['legacy accepted during transition'] = check_csrf_token_for_script($legacy, $script);
$checks['legacy wrong script'] = !check_csrf_token_for_script($legacy, '/other.php');
$checks['expensive cost rejected'] = !check_csrf_token_for_script(str_replace('$10$', '$31$', $legacy), $script);
$checks['unknown algorithm rejected'] = !check_csrf_token_for_script('$argon2id$v=19$m=999999999,t=99,p=99$abc$abc', $script);
putenv('OPENVSOSH_CSRF_ISSUE_LEGACY=1');
$issued = f_get_csrf_token_for_script($script);
$checks['old reader accepts transition issuer'] = check_password(get_plain_csrf_token_for_script($script), $issued);
putenv('OPENVSOSH_CSRF_ISSUE_LEGACY');
$new = f_get_csrf_token_for_script($script);
$checks['dual reader accepts new issuer'] = check_csrf_token_for_script($new, $script);
putenv('OPENVSOSH_CSRF_LEGACY_UNTIL=' . (time() - 1));
$checks['deadline rejects legacy'] = !check_csrf_token_for_script($legacy, $script);
$checks['deadline preserves v2'] = check_csrf_token_for_script($new, $script);
putenv('OPENVSOSH_CSRF_ISSUE_LEGACY=1');
try { f_get_csrf_token_for_script($script); $checks['expired issuance fails closed'] = false; }
catch (Error $e) { $checks['expired issuance fails closed'] = true; }
putenv('OPENVSOSH_CSRF_LEGACY_UNTIL=invalid');
$checks['invalid deadline'] = !check_csrf_token_for_script($legacy, $script);
echo json_encode($checks, JSON_THROW_ON_ERROR);
PHP);
    }

    public function testUnconfiguredSigningSecretsFailClosed(): void
    {
        foreach (['', 'CHANGE_THIS_K_RANDOM_SECURITY', 'mkTzxf8WwUxwvj6w', 'short'] as $secret) {
            self::runChecks(<<<'PHP'
$checks = ['verification' => !check_csrf_token_for_script('v2.' . str_repeat('a', 32) . '.' . str_repeat('b', 64), '/test.php')];
try { f_get_csrf_token_for_script('/test.php'); $checks['issuance'] = false; }
catch (Error $e) { $checks['issuance'] = true; }
echo json_encode($checks, JSON_THROW_ON_ERROR);
PHP, $secret);
        }
    }

    private static function runChecks(string $script, #[\SensitiveParameter] string $secret = 'test-install-secret-0123456789abcdef'): void
    {
        $setup = 'define("K_COOKIE_SECURE", true); define("K_COOKIE_HTTPONLY", true); '
            . 'define("K_COOKIE_SAMESITE", "Strict"); define("K_RANDOM_SECURITY", $argv[2]); '
            . 'require $argv[1]; session_id(str_repeat("a", 32)); $_SERVER = []; '
            . 'putenv("OPENVSOSH_CSRF_LEGACY_UNTIL"); putenv("OPENVSOSH_CSRF_ISSUE_LEGACY"); ';
        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $setup . $script, dirname(__DIR__) . '/shared/code/TCExamSessionHandler.php', $secret],
            dirname(__DIR__),
        );
        self::assertSame(0, $status, $output);
        $checks = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($checks);
        self::assertNotEmpty($checks);
        foreach ($checks as $name => $passed) {
            self::assertTrue($passed, (string) $name);
        }
    }
}
