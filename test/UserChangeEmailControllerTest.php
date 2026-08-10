<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UserChangeEmailControllerTest extends TestCase
{
    public function testPrivilegedEmailChangePreservesRoleAndSkipsVerification(): void
    {
        $output = self::runSuccessfulUpdate(5);

        self::assertStringContainsString('[[QUERY:SELECT user_password FROM users WHERE user_id=42]]', $output);
        self::assertStringContainsString(
            "[[QUERY:UPDATE users SET user_email='new@example.test', user_verifycode=NULL WHERE user_id=42]]",
            $output,
        );
        self::assertStringNotContainsString("user_level='0'", $output);
        self::assertStringNotContainsString('[[MAIL:', $output);
        self::assertStringContainsString('[[MESSAGE:MESSAGE:Email updated]]', $output);
        self::assertStringContainsString('<a href="index.php" title="Home">Home &gt;</a>', $output);
    }

    public function testParticipantEmailChangeRetainsVerificationFlow(): void
    {
        $output = self::runSuccessfulUpdate(1);

        self::assertStringContainsString(
            "[[QUERY:UPDATE users SET user_email='new@example.test', user_verifycode='verify-code',"
                . " user_level='0' WHERE user_id=42]]",
            $output,
        );
        self::assertStringContainsString('[[MAIL:42:new@example.test:verify-code]]', $output);
        self::assertStringContainsString(
            '[[MESSAGE:MESSAGE:new@example.test: Verification sent]]',
            $output,
        );
    }

    private static function runSuccessfulUpdate(int $level): string
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_USER_CHANGE_EMAIL', 1);
define('K_NEWLINE', "\n");
define('K_TABLE_USERS', 'users');
$l = [
    't_user_change_email' => 'Change email', 'w_email' => 'Email', 'a_meta_charset' => 'UTF-8',
    'm_different_emails' => 'Different emails', 'm_login_wrong' => 'Wrong password',
    'm_email_updated' => 'Email updated', 'm_user_verification_sent' => 'Verification sent',
    'h_index' => 'Home',
];
$db = 'db';
$menu_mode = 'update';
$_POST = [
    'currentpassword' => 'current-secret', 'user_email' => 'new@example.test',
    'user_email_repeat' => 'new@example.test',
];
$_REQUEST = [];
$_SESSION = ['session_user_id' => 42, 'session_user_level' => (int) $argv[2]];
function F_check_form_fields() { return true; }
function F_db_query($sql, $db) { echo '[[QUERY:' . preg_replace('/\s+/', ' ', trim($sql)) . ']]'; return true; }
function F_db_fetch_array($result) { return ['user_password' => 'stored-hash']; }
function check_password($plain, $hash) { return $plain === 'current-secret' && $hash === 'stored-hash'; }
function get_new_session_id() { return 'verify-code'; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
function F_print_error($type, $message) { echo "[[MESSAGE:$type:$message]]"; }
function F_send_user_reg_email($id, $email, $code) { echo "[[MAIL:$id:$email:$code]]"; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/tce_user_change_email.php', (string) $level],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringNotContainsString('[[DB-ERROR]]', $output);
        return $output;
    }
}
