<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UserChangePasswordControllerTest extends TestCase
{
    public function testSuccessfulPasswordChangeKeepsQueryAndFormContract(): void
    {
        $output = self::runUpdate('current-secret');

        self::assertStringContainsString('[[QUERY:SELECT user_password FROM users WHERE user_id=42]]', $output);
        self::assertStringContainsString(
            "[[QUERY:UPDATE users SET user_password='hash:new-secret' WHERE user_id=42]]",
            $output,
        );
        self::assertStringContainsString('[[MESSAGE:MESSAGE:Password updated]]', $output);
        self::assertStringContainsString('name="currentpassword"', $output);
        self::assertStringContainsString('name="newpassword"', $output);
        self::assertStringContainsString('name="newpassword_repeat"', $output);
    }

    public function testWrongCurrentPasswordDoesNotUpdateUser(): void
    {
        $output = self::runUpdate('wrong-secret');

        self::assertStringContainsString('[[QUERY:SELECT user_password FROM users WHERE user_id=42]]', $output);
        self::assertStringContainsString('[[MESSAGE:WARNING:Wrong password]]', $output);
        self::assertStringNotContainsString('UPDATE users', $output);
        self::assertStringNotContainsString('Password updated', $output);
    }

    private static function runUpdate(#[\SensitiveParameter] string $currentPassword): string
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_USER_CHANGE_PASSWORD', 1);
define('K_NEWLINE', "\n");
define('K_TABLE_USERS', 'users');
define('K_USRREG_PASSWORD_RE', 'password-pattern');
$l = [
    't_user_change_password' => 'Change password', 'w_current_password' => 'Current password',
    'w_new_password' => 'New password', 'a_meta_charset' => 'UTF-8',
    'm_different_passwords' => 'Different passwords', 'm_login_wrong' => 'Wrong password',
    'm_password_updated' => 'Password updated', 'h_password' => 'Password help',
    'd_password_length' => 'Length', 'h_password_repeat' => 'Repeat help',
    'w_repeat' => 'repeat', 'w_update' => 'Update', 'h_update' => 'Update help',
    'hp_user_change_password' => 'Password page help',
];
$db = 'db';
$menu_mode = 'update';
$_POST = [
    'currentpassword' => $argv[2], 'newpassword' => 'new-secret',
    'newpassword_repeat' => 'new-secret',
];
$_REQUEST = [];
$_SESSION = ['session_user_id' => 42];
$_SERVER['SCRIPT_NAME'] = '/public/code/tce_user_change_password.php';
function F_check_form_fields() { return true; }
function F_db_query($sql, $db) { echo '[[QUERY:' . preg_replace('/\s+/', ' ', trim($sql)) . ']]'; return true; }
function F_db_fetch_array($result) { return ['user_password' => 'stored-hash']; }
function check_password($plain, $hash) { return $plain === 'current-secret' && $hash === 'stored-hash'; }
function get_password_hash($password) { return 'hash:' . $password; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
function F_print_error($type, $message) { echo "[[MESSAGE:$type:$message]]"; }
function get_form_row_text_input($name, ...$arguments) { return '<input name="' . $name . '">'; }
function F_submit_button($name, $label, $help) { echo '<button name="' . $name . '">' . $label . '</button>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/public/code/tce_user_change_password.php',
                $currentPassword,
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringNotContainsString('[[DB-ERROR]]', $output);
        return $output;
    }
}
