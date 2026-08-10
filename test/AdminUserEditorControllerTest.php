<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminUserEditorControllerTest extends TestCase
{
    public function testSelectedUserFieldsGroupsAndActionsAreRendered(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_USERS', 10);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_AUTH_DELETE_USERS', 8);
define('K_DATABASE_TYPE', 'MYSQL');
define('K_EMAIL_RE_PATTERN', 'email-pattern');
define('K_MAX_ROWS_PER_PAGE', 50);
define('K_NEWLINE', "\n");
define('K_PATH_HOST', 'https://example.test/');
define('K_TABLE_GROUPS', 'groups');
define('K_TABLE_USERGROUP', 'user_groups');
define('K_TABLE_USERS', 'users');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_USRREG_PASSWORD_RE', 'password-pattern');
$keys = [
    'a_meta_charset', 'd_password_length', 'h_add', 'h_birth_date', 'h_birth_place', 'h_cancel', 'h_clear',
    'h_delete', 'h_firstname', 'h_fiscal_code', 'h_ip', 'h_lastname', 'h_level', 'h_login_name', 'h_otpkey',
    'h_password', 'h_password_repeat', 'h_regcode', 'h_regdate', 'h_update', 'h_usered_email', 'hp_edit_user',
    'm_authorization_denied', 'm_delete_anonymous', 'm_delete_confirm', 'm_different_passwords', 'm_duplicate_name',
    'm_duplicate_regnumber', 'm_duplicate_ssn', 'm_empty_password', 'm_form_missing_fields', 'm_user_deleted',
    'm_user_updated', 't_user_editor', 'w_add', 'w_birth_date', 'w_birth_place', 'w_cancel', 'w_clear',
    'w_confirm', 'w_date_format', 'w_delete', 'w_email', 'w_firstname', 'w_fiscal_code', 'w_groups', 'w_ip',
    'w_lastname', 'w_level', 'w_name', 'w_otp_qrcode', 'w_otpkey', 'w_password', 'w_regcode', 'w_regdate',
    'w_repeat', 'w_search', 'w_select', 'w_update', 'w_user', 'w_username',
];
$l = [];
foreach ($keys as $key) { $l[$key] = $key; }
$l['a_meta_charset'] = 'UTF-8';
$db = 'db';
$formstatus = true;
$menu_mode = '';
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_edit_user.php'];
$_SESSION = ['session_user_id' => 99, 'session_user_level' => 10];
$_POST = [];
$_FILES = [];
$_REQUEST = ['user_id' => '7'];
$GLOBALS['rows'] = [];
$GLOBALS['queries'] = [];
function f_is_authorized_editor_for_user($id) { return true; }
function f_is_authorized_editor_for_group($id) { return true; }
function f_is_user_on_group($userId, $groupId) { return (int) $userId === 7 && (int) $groupId === 3; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function f_legacy_literal_equals($value, $expected) { return (string) $value === $expected; }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        $sql === 'SELECT * FROM users WHERE user_id=7 LIMIT 1' => [[
            'user_id' => '7', 'user_regdate' => '2026-08-01 10:00:00', 'user_ip' => '127.0.0.1',
            'user_name' => 'jane', 'user_email' => 'jane@example.test', 'user_password' => 'stored-hash',
            'user_regnumber' => 'REG-7', 'user_firstname' => 'Jane', 'user_lastname' => 'Doe',
            'user_birthdate' => '2001-02-03 00:00:00', 'user_birthplace' => 'Springfield',
            'user_ssn' => 'SSN-7', 'user_note' => 'Visible note', 'user_schedule' => 'Visible schedule',
            'user_level' => '5', 'user_otpkey' => '',
        ]],
        $sql === 'SELECT * FROM groups ORDER BY group_name' => [
            ['group_id' => '3', 'group_name' => 'Editors'],
            ['group_id' => '4', 'group_name' => 'Reviewers'],
        ],
        default => [],
    };
    $GLOBALS['rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][get_resource_id($result)]); }
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error(...$arguments) { echo '<FORM-ERROR>'; }
function F_submit_button($name, $label, $title) { echo '<BUTTON:' . $name . ':' . $label . '>'; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_text_input($name, $label, $title, $required, $value, ...$arguments) {
    return '<TEXT:' . $name . ':' . $value . '>';
}
function get_form_row_fixed_value($name, $label, $title, $required, $value) {
    return '<FIXED:' . $name . ':' . $value . '>';
}
function get_form_row_select_box($name, $label, $title, $required, $value, $options) {
    return '<SELECT:' . $name . ':' . $value . '>';
}
function f_tmf_user_photo_path($id) { return '/missing/photo-' . $id; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_user.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:list<string>} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'SELECT * FROM users WHERE user_id=7 LIMIT 1',
            'SELECT * FROM groups ORDER BY group_name',
        ], $result['queries']);
        self::assertStringContainsString('<option value="7" selected="selected">Doe Jane - jane</option>', $result['html']);
        self::assertStringContainsString('<TEXT:user_name:jane>', $result['html']);
        self::assertStringContainsString('<TEXT:user_email:jane@example.test>', $result['html']);
        self::assertStringContainsString('<FIXED:user_regdate:2026-08-01 10:00:00>', $result['html']);
        self::assertStringContainsString('<SELECT:user_level:5>', $result['html']);
        self::assertStringContainsString('<TEXT:user_birthdate:2001-02-03>', $result['html']);
        self::assertStringContainsString('>Visible note</textarea>', $result['html']);
        self::assertStringContainsString('>Visible schedule</textarea>', $result['html']);
        self::assertStringContainsString('<option value="3" selected="selected">* Editors</option>', $result['html']);
        self::assertStringContainsString('<option value="4">Reviewers</option>', $result['html']);
        self::assertStringContainsString('<BUTTON:update:w_update>', $result['html']);
        self::assertStringContainsString('<BUTTON:delete:w_delete>', $result['html']);
        self::assertStringContainsString('value="stored-hash"', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
    }
}
