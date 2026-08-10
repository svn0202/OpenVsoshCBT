<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EditModuleControllerTest extends TestCase
{
    public function testAddPersistsOwnerAndRendersSelectedModule(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_MODULES', 10);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_NEWLINE', "\n");
define('K_PATH_PUBLIC_CODE', '/public/code/');
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_SUBJECTS', 'subjects');
define('K_TABLE_SUBJECT_SET', 'subject_set');
define('K_TABLE_USERS', 'users');
define('K_TABLE_GROUPS', 'groups');
define('K_TABLE_USERGROUP', 'usergroup');
$l = [
    't_modules_editor' => 'Module editor', 'm_authorization_denied' => 'Denied',
    'w_name' => 'Name', 'a_meta_charset' => 'UTF-8', 'm_disabled_vs_deleted' => 'Disabled',
    'm_delete_confirm' => 'Confirm delete', 'w_delete' => 'Delete', 'h_delete' => 'Delete module',
    'w_cancel' => 'Cancel', 'h_cancel' => 'Cancel', 'm_deleted' => 'Deleted',
    'm_form_missing_fields' => 'Missing', 'w_confirm' => 'Confirm', 'w_update' => 'Update',
    'm_update_restrict' => 'Restricted', 'w_record_status' => 'Status', 'w_enabled' => 'Enabled',
    'w_disabled' => 'Disabled', 'm_duplicate_name' => 'Duplicate', 'm_updated' => 'Updated',
    'w_module' => 'Module', 'h_module_name' => 'Module name', 'w_owner' => 'Owner',
    'h_module_owner' => 'Module owner', 'w_select' => 'Select', 'w_groups' => 'Groups',
    'h_enabled' => 'Enabled module', 'h_update' => 'Update module', 'w_add' => 'Add',
    'h_add' => 'Add module', 'w_clear' => 'Clear', 'h_clear' => 'Clear form',
    't_subjects_editor' => 'Subjects', 'hp_edit_module' => 'Module help',
];
$db = 'db';
$menu_mode = 'add';
$formstatus = true;
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_edit_module.php';
$_SESSION = ['session_user_id' => 7, 'session_user_level' => 10];
$_POST = [];
$_REQUEST = ['module_name' => 'New Module', 'module_enabled' => '1', 'module_user_id' => '7'];
$GLOBALS['queries'] = [];
$GLOBALS['result_rows'] = [];
function f_is_authorized_user(...$arguments) { return true; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function f_get_boolean($value) { return (bool) $value; }
function utrim($value) { return trim((string) $value); }
function F_check_form_fields() { return true; }
function F_check_unique(...$arguments) { return true; }
function F_escape_sql($db, $value) { return str_replace("'", "''", (string) $value); }
function F_select_modules_sql($where = '') { return 'SELECT modules' . ($where === '' ? '' : ' WHERE ' . $where); }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    if (!str_starts_with($sql, 'SELECT')) { return true; }
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        $sql === 'SELECT modules WHERE module_id=9 LIMIT 1' => [[
            'module_id' => '9', 'module_name' => 'New Module', 'module_enabled' => '1', 'module_user_id' => '7',
        ]],
        $sql === 'SELECT modules' => [[
            'module_id' => '9', 'module_name' => 'New Module', 'module_enabled' => '1', 'module_user_id' => '7',
        ]],
        str_contains($sql, 'FROM users') => [[
            'user_id' => '7', 'user_name' => 'owner', 'user_lastname' => 'Owner', 'user_firstname' => 'Olga',
        ]],
        str_contains($sql, 'FROM groups') => [['group_name' => 'Editors']],
        default => [],
    };
    $GLOBALS['result_rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) {
    $id = get_resource_id($result);
    return array_shift($GLOBALS['result_rows'][$id]);
}
function F_db_insert_id($db, $table, $field) { return 9; }
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error($type, $message) { echo "<$type:$message>"; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_text_input($name, $label, $title, $required, $value) {
    return '<TEXT:' . $name . ':' . $value . '>';
}
function get_form_row_checkbox($name, $label, $title, $required, $value, $checked) {
    return '<CHECKBOX:' . $name . ':' . (int) $checked . '>';
}
function unhtmlentities($value) { return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_module.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:array{0:string,1:string,2:string,3:string,4:string}} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(5, $result['queries']);
        self::assertStringStartsWith('INSERT INTO modules', $result['queries'][0]);
        self::assertStringContainsString("'New Module', '1', '7'", $result['queries'][0]);
        self::assertSame('SELECT modules WHERE module_id=9 LIMIT 1', $result['queries'][1]);
        self::assertSame('SELECT modules', $result['queries'][2]);
        self::assertStringContainsString('FROM users WHERE (user_level>5)', $result['queries'][3]);
        self::assertStringContainsString('usrgrp_user_id=7', $result['queries'][4]);
        self::assertStringContainsString('<option value="9" selected="selected">1. + New Module', $result['html']);
        self::assertStringContainsString('<option value="7" selected="selected">(owner) Owner Olga</option>', $result['html']);
        self::assertStringContainsString(' · Editors', $result['html']);
        self::assertStringContainsString('<CHECKBOX:module_enabled:1>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
    }
}
