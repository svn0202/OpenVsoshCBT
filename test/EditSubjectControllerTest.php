<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EditSubjectControllerTest extends TestCase
{
    public function testAddPersistsSubjectAndRendersSelectedOption(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_SUBJECTS', 10);
define('K_NEWLINE', "\n");
define('K_PATH_CACHE', '/tmp');
define('K_PATH_SHARED_JSCRIPTS', '/shared/js/');
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_SUBJECTS', 'subjects');
define('K_TABLE_SUBJECT_SET', 'subject_set');
$l = [
    't_subjects_editor' => 'Subject editor', 'w_name' => 'Name', 'a_meta_charset' => 'UTF-8',
    'm_authorization_denied' => 'Denied', 'm_disabled_vs_deleted' => 'Disabled',
    'm_delete_confirm' => 'Confirm delete', 'w_delete' => 'Delete', 'h_delete' => 'Delete subject',
    'w_cancel' => 'Cancel', 'h_cancel' => 'Cancel', 'm_deleted' => 'Deleted',
    'm_form_missing_fields' => 'Missing', 'w_confirm' => 'Confirm', 'w_update' => 'Update',
    'm_update_restrict' => 'Restricted', 'w_record_status' => 'Status', 'w_enabled' => 'Enabled',
    'w_disabled' => 'Disabled', 'm_duplicate_name' => 'Duplicate', 'm_updated' => 'Updated',
    't_modules_editor' => 'Modules', 'hp_edit_subject' => 'Subject help', 'w_module' => 'Module',
    'w_subject' => 'Subject', 'h_subject' => 'Choose subject', 'h_subject_name' => 'Subject name',
    'w_description' => 'Description', 'h_preview' => 'Preview', 'w_preview' => 'Preview',
    'h_subject_description' => 'Subject description', 'h_enabled' => 'Enabled subject',
    'h_update' => 'Update subject', 'w_add' => 'Add', 'h_add' => 'Add subject',
    'w_clear' => 'Clear', 'h_clear' => 'Clear form', 't_questions_editor' => 'Questions',
];
$db = 'db';
$menu_mode = 'add';
$formstatus = true;
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_edit_subject.php';
$_SESSION = ['session_user_id' => 7];
$_POST = [];
$_FILES = [];
$_REQUEST = [
    'subject_name' => 'New Subject', 'subject_description' => 'Subject body',
    'subject_enabled' => '1', 'subject_module_id' => '3',
];
$GLOBALS['queries'] = [];
$GLOBALS['result_rows'] = [];
function f_is_authorized_user(...$arguments) { return true; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function f_get_boolean($value) { return $value === true || $value === 1 || $value === '1'; }
function utrim($value) { return trim((string) $value); }
function F_check_form_fields() { return true; }
function F_check_unique(...$arguments) { return true; }
function F_escape_sql($db, $value) { return str_replace("'", "''", (string) $value); }
function f_empty_to_null($value) { return $value === '' ? 'NULL' : "'" . str_replace("'", "''", $value) . "'"; }
function F_select_modules_sql($where = '') { return 'SELECT modules' . ($where === '' ? '' : ' WHERE ' . $where); }
function F_select_subjects_sql($where = '') { return 'SELECT subjects' . ($where === '' ? '' : ' WHERE ' . $where); }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    if (!str_starts_with($sql, 'SELECT')) { return true; }
    $result = fopen('php://memory', 'r');
    $rows = match ($sql) {
        'SELECT subjects WHERE subject_id=9 AND subject_module_id=3 LIMIT 1' => [[
            'subject_id' => '9', 'subject_module_id' => '3', 'subject_name' => 'New Subject',
            'subject_description' => 'Subject body', 'subject_enabled' => '1',
        ]],
        'SELECT modules' => [['module_id' => '3', 'module_name' => 'Math', 'module_enabled' => '1']],
        'SELECT subjects WHERE subject_module_id=3' => [[
            'subject_id' => '9', 'subject_module_id' => '3', 'subject_name' => 'New Subject',
            'subject_description' => 'Subject body', 'subject_enabled' => '1',
        ]],
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
function F_print_error($type, $message, ...$arguments) { echo "<$type:$message>"; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_text_input($name, $label, $title, $required, $value) { return '<TEXT:' . $name . ':' . $value . '>'; }
function get_form_row_checkbox($name, $label, $title, $required, $value, $checked) {
    return '<CHECKBOX:' . $name . ':' . (int) $checked . '>';
}
function tcecode_editor_tag_buttons($form, $field) { return '<EDITOR:' . $form . ':' . $field . '>'; }
function F_decode_tcecode($value) { return '[[decoded:' . $value . ']]'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_subject.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:array{0:string,1:string,2:string,3:string}} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(4, $result['queries']);
        self::assertStringStartsWith('INSERT INTO subjects', $result['queries'][0]);
        self::assertStringContainsString("'New Subject', 'Subject body', '1', '7', 3", $result['queries'][0]);
        self::assertSame('SELECT subjects WHERE subject_id=9 AND subject_module_id=3 LIMIT 1', $result['queries'][1]);
        self::assertSame('SELECT modules', $result['queries'][2]);
        self::assertSame('SELECT subjects WHERE subject_module_id=3', $result['queries'][3]);
        self::assertStringContainsString('<option value="3" selected="selected">1. + Math', $result['html']);
        self::assertStringContainsString('<option value="9" selected="selected">1. + New Subject', $result['html']);
        self::assertStringContainsString('<textarea', $result['html']);
        self::assertStringContainsString('>Subject body</textarea>', $result['html']);
        self::assertStringContainsString('[[decoded:Subject body]]', $result['html']);
        self::assertStringContainsString('<CHECKBOX:subject_enabled:1>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
    }
}
