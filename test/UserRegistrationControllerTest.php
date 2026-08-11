<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UserRegistrationControllerTest extends TestCase
{
    public function testPasswordConfirmationPreservesAllRegistrationOutcomes(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; '
                    . 'function get_password_hash($value) { return "hash:" . $value; } '
                    . 'function f_tce_user_registration_string($value) { return (string) $value; } '
                    . 'function f_get_random_otp_key() { return "otp"; } '
                    . 'function F_print_error($type, $message) { $GLOBALS["errors"][] = [$type, $message]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "        \\$user_password = \'\';"); '
                    . '$end = strpos($source, "        if (\\$formstatus) {", $start); '
                    . '$block = substr($source, $start, $end - $start); '
                    . '$l = ["m_different_passwords" => "different", "m_empty_password" => "empty"]; '
                    . '$results = []; foreach ([["same", "same"], ["same", "other"], ["", ""]] as $case) {'
                    . '[$newpassword, $newpassword_repeat] = $case; $formstatus = true; $GLOBALS["errors"] = []; '
                    . 'eval("namespace Harness; " . $block); '
                    . '$results[] = [$user_password, $user_otpkey, $formstatus, $GLOBALS["errors"]]; } '
                    . 'echo json_encode($results);',
                dirname(__DIR__) . '/public/code/tce_user_registration.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                ['hash:same', 'otp', true, []],
                ['', '', false, [['WARNING', 'different']]],
                ['', '', false, [['WARNING', 'empty']]],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testRegistrationFormPreservesValuesGroupsAndRequiredFields(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_EMAIL_RE_PATTERN', 'email-pattern');
define('K_NEWLINE', "\n");
define('K_TABLE_GROUPS', 'groups');
define('K_USRREG_AGREEMENT', '/agreement');
define('K_USRREG_EMAIL_CONFIRM', false);
define('K_USRREG_GROUP', 2);
define('K_USRREG_PASSWORD_RE', 'password-pattern');
$keys = [
    'a_meta_charset', 'd_password_length', 'h_add', 'h_birth_date', 'h_birth_place', 'h_firstname',
    'h_fiscal_code', 'h_index', 'h_lastname', 'h_login_name', 'h_password_repeat', 'h_password',
    'h_regcode', 'h_usered_email', 'hp_user_registration', 'm_different_passwords', 'm_duplicate_name',
    'm_duplicate_regnumber', 'm_duplicate_ssn', 'm_empty_password', 'm_new_window_link',
    'm_user_registration_ok', 'm_user_verification_sent', 't_user_registration', 'w_add', 'w_birth_date',
    'w_birth_place', 'w_date_format', 'w_email', 'w_firstname', 'w_fiscal_code', 'w_groups',
    'w_i_agree', 'w_lastname', 'w_name', 'w_password', 'w_regcode', 'w_repeat', 'w_username',
];
$l = [];
foreach ($keys as $key) { $l[$key] = $key; }
$l['a_meta_charset'] = 'UTF-8';
$db = 'db';
$menu_mode = '';
$regfields = [
    'user_name' => 2, 'newpassword' => 2, 'newpassword_repeat' => 2, 'user_email' => 1,
    'user_regnumber' => 1, 'user_firstname' => 2, 'user_lastname' => 1, 'user_birthdate' => 1,
    'user_birthplace' => 1, 'user_ssn' => 1, 'user_groups' => 2, 'user_agreement' => 1,
];
$_SERVER = ['SCRIPT_NAME' => '/public/code/tce_user_registration.php'];
$_POST = [];
$_REQUEST = [
    'user_name' => 'alice<admin>', 'user_email' => 'alice@example.test', 'user_regnumber' => 'R-7',
    'user_firstname' => 'Alice', 'user_lastname' => 'Doe', 'user_birthdate' => '2000-01-02',
    'user_birthplace' => 'Town', 'user_ssn' => 'SSN-7', 'user_groups' => ['3'],
];
$GLOBALS['rows'] = [];
function openvsosh_get_access_settings() { return ['registration_enabled' => true]; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function show_required_field($value) { return (int) $value === 2 ? '<REQUIRED>' : ''; }
function get_form_row_text_input($name, $label, $title, $description, $value, ...$arguments) {
    return '<TEXT:' . $name . ':' . $value . ':' . (int) end($arguments) . '>';
}
function F_db_query($sql, $db) {
    $result = fopen('php://memory', 'r');
    $GLOBALS['rows'][get_resource_id($result)] = [
        ['group_id' => '2', 'group_name' => 'Default & Group'],
        ['group_id' => '3', 'group_name' => 'Chosen Group'],
    ];
    return $result;
}
function F_db_fetch_array($result) {
    $id = get_resource_id($result);
    return array_shift($GLOBALS['rows'][$id]);
}
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_submit_button($name, $value, $title) { echo '<SUBMIT:' . $name . ':' . $value . '>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode($html, JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/tce_user_registration.php'],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /** @var string $html */
        $html = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('<TEXT:user_name:alice&lt;admin&gt;', $html);
        self::assertStringContainsString('<TEXT:user_email:alice@example.test:', $html);
        self::assertStringContainsString('<TEXT:user_firstname:Alice:', $html);
        self::assertStringContainsString('<option value="2" selected="selected">Default &amp; Group</option>', $html);
        self::assertStringContainsString('<option value="3" selected="selected">Chosen Group</option>', $html);
        self::assertStringContainsString('<REQUIRED>', $html);
        self::assertStringContainsString('href="/agreement"', $html);
        self::assertStringContainsString('<SUBMIT:add:w_add>', $html);
        self::assertStringContainsString('<CSRF>', $html);
        self::assertStringNotContainsString('<DB-ERROR>', $html);
    }
}
