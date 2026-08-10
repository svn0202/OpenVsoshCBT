<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PasswordResetControllerTest extends TestCase
{
    public function testPasswordResetFormContractRemainsUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_NEWLINE', "\n");
define('K_EMAIL_RE_PATTERN', '/email/');
$l = [
    't_password_assistance' => 'Password assistance', 'w_email' => 'Email',
    'a_meta_charset' => 'UTF-8', 'd_reset_password' => 'Reset instructions',
    'h_usered_email' => 'Email help', 'w_submit' => 'Submit', 'h_submit' => 'Submit help',
];
$_SERVER['SCRIPT_NAME'] = '/public/code/tce_password_reset.php';
$_POST = [];
$_REQUEST = [];
$GLOBALS['fields'] = [];
function openvsosh_get_access_settings() { return ['password_reset_enabled' => true]; }
function get_form_row_text_input(...$arguments) { $GLOBALS['fields'][] = $arguments; return '<EMAIL-FIELD>'; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['fields'], $_REQUEST], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/tce_password_reset.php'],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string,list<list<mixed>>,array<string,mixed>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $fields, $request] = $decoded;
        self::assertStringContainsString('id="form_usereditor"', $html);
        self::assertStringContainsString('<p>Reset instructions</p>', $html);
        self::assertStringContainsString('<EMAIL-FIELD>', $html);
        self::assertStringContainsString('<SUBMIT:resetpassword:Submit:Submit help>', $html);
        self::assertStringContainsString("<CSRF>\n</form>", $html);
        self::assertSame('user_email', $request['ff_required'] ?? null);
        self::assertSame('Email', $request['ff_required_labels'] ?? null);
        self::assertSame(
            [['user_email', 'Email', 'Email help', '', '', '/email/', 255, false, false, false, '', true, 'email', 'email']],
            $fields,
        );
    }

    public function testSuccessfulPasswordResetPostContractRemainsUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_NEWLINE', "\n");
define('K_TABLE_USERS', 'users');
$l = [
    't_password_assistance' => 'Password assistance', 'w_email' => 'Email',
    'a_meta_charset' => 'UTF-8', 'm_user_verification_sent' => 'Verification sent',
    'h_index' => 'Home',
];
$db = 'db';
$_POST = ['resetpassword' => '1', 'user_email' => 'alice@example.test'];
$_REQUEST = [];
function openvsosh_get_access_settings() { return ['password_reset_enabled' => true]; }
function F_check_form_fields() { return true; }
function random_int($min, $max) { return 123; }
function uniqid($prefix = '', $moreEntropy = false) { return 'fixed'; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function F_db_query($sql, $db) { echo '[[QUERY:' . $sql . ']]'; return true; }
function F_db_fetch_array($result) { return ['user_id' => 9]; }
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
function F_send_user_reg_email($id, $email, $code) { echo "[[MAIL:$id:$email:$code]]"; }
function F_print_error($type, $message) { echo "[[MESSAGE:$type:$message]]"; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/tce_password_reset.php'],
            dirname(__DIR__) . '/public/code',
        );

        $verifyCode = '@' . substr(md5('fixed'), 1);
        self::assertSame(0, $status, $output);
        self::assertStringContainsString(
            "[[QUERY:SELECT user_id FROM users WHERE user_email='alice@example.test']]",
            $output,
        );
        self::assertStringContainsString(
            "[[QUERY:UPDATE users SET user_verifycode='" . $verifyCode . "' WHERE user_id=9]]",
            $output,
        );
        self::assertStringContainsString("[[MAIL:9:alice@example.test:{$verifyCode}]]", $output);
        self::assertStringContainsString('[[MESSAGE:MESSAGE:alice@example.test: Verification sent]]', $output);
        self::assertStringContainsString('<a href="index.php" title="Home">Home &gt;</a>', $output);
        self::assertStringNotContainsString('[[DB-ERROR]]', $output);
    }
}
