<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminAnswerEditorControllerTest extends TestCase
{
    public function testSelectedAnswerFieldsNavigationAndActionsAreRendered(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_ANSWERS', 10);
define('K_DATABASE_TYPE', 'MYSQL');
define('K_ENABLE_ANSWER_EXPLANATION', true);
define('K_NEWLINE', "\n");
define('K_PATH_CACHE', '/tmp');
define('K_PATH_SHARED_JSCRIPTS', '/shared/js/');
define('K_SELECT_SUBSTRING', 80);
define('K_TABLE_ANSWERS', 'answers');
define('K_TABLE_LOG_ANSWER', 'log_answers');
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_SUBJECTS', 'subjects');
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
preg_match_all("/\['([a-z][a-z0-9_]*)'\]/", $source, $labels);
$l = array_fill_keys(array_unique($labels[1]), 'label');
$l['a_meta_charset'] = 'UTF-8';
$l['w_module'] = 'Module';
$l['w_subject'] = 'Subject';
$l['w_question'] = 'Question';
$l['w_answer'] = 'Answer';
$l['w_explanation'] = 'Explanation';
$l['w_right'] = 'Right';
$l['w_enabled'] = 'Enabled';
$l['w_position'] = 'Position';
$l['w_keyboard_key'] = 'Key';
$l['w_update'] = 'Update';
$l['w_add'] = 'Add';
$l['w_delete'] = 'Delete';
$l['w_clear'] = 'Clear';
$l['w_confirm'] = 'Confirm';
$l['w_list'] = 'List';
$l['w_preview'] = 'Preview';
$l['t_questions_editor'] = 'Question editor';
$l['t_questions_list'] = 'Question list';
$l['hp_edit_answer'] = 'Answer help';
$db = 'db';
$formstatus = true;
$menu_mode = '';
$_FILES = [];
$_POST = [];
$_REQUEST = [
    'subject_module_id' => '5', 'question_subject_id' => '11', 'answer_question_id' => '21',
    'answer_id' => '31', 'firstrow' => '20',
];
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_edit_answer.php'];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [];
function f_get_boolean($value) { return $value === true || $value === 1 || $value === '1'; }
function f_legacy_literal_equals($value, $expected) { return (string) $value === $expected; }
function f_is_authorized_user(...$arguments) { return true; }
function F_tmf_question_options($description) {
    return ['matching_reuse_positions' => false, 'matching_positions' => 0];
}
function F_select_modules_sql() { return 'SELECT modules'; }
function F_select_subjects_sql($where) { return 'SELECT subjects WHERE ' . $where; }
function F_db_query($sql, $db) {
    $normalized = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $normalized;
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        $normalized === 'SELECT question_type,question_description FROM questions WHERE question_id=21 LIMIT 1' => [[
            'question_type' => '1', 'question_description' => 'What is two plus two?',
        ]],
        str_contains($normalized, 'SELECT subject_module_id,question_subject_id,answer_question_id FROM') => [[
            'subject_module_id' => '5', 'question_subject_id' => '11', 'answer_question_id' => '21',
        ]],
        $normalized === 'SELECT * FROM answers WHERE answer_id=31 LIMIT 1' => [[
            'answer_id' => '31', 'answer_question_id' => '21', 'answer_description' => 'Four & only',
            'answer_explanation' => 'Because <four>', 'answer_isright' => '1', 'answer_enabled' => '1',
            'answer_position' => '2', 'answer_keyboard_key' => '65', 'answer_weight' => '40',
        ]],
        $normalized === 'SELECT modules' => [
            ['module_id' => '5', 'module_enabled' => '1', 'module_name' => 'Arithmetic'],
            ['module_id' => '6', 'module_enabled' => '0', 'module_name' => 'Geometry'],
        ],
        $normalized === 'SELECT subjects WHERE subject_module_id=5' => [[
            'subject_id' => '11', 'subject_enabled' => '1', 'subject_name' => 'Numbers',
        ]],
        str_starts_with($normalized, 'SELECT * FROM questions WHERE question_subject_id=11') => [[
            'question_id' => '21', 'question_enabled' => '1', 'question_type' => '1',
            'question_position' => '1', 'question_description' => 'What is two plus two?',
        ]],
        str_starts_with($normalized, 'SELECT * FROM answers WHERE answer_question_id=21') => [
            ['answer_id' => '31', 'answer_enabled' => '1', 'answer_isright' => '1',
                'answer_position' => '2', 'answer_description' => 'Four & only'],
            ['answer_id' => '32', 'answer_enabled' => '1', 'answer_isright' => '0',
                'answer_position' => '1', 'answer_description' => 'Five'],
        ],
        default => [],
    };
    $GLOBALS['rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][get_resource_id($result)]); }
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error(...$arguments) { echo '<FORM-ERROR>'; }
function F_count_rows(...$arguments) { return 2; }
function f_remove_tcecode($value) { return $value; }
function f_substr_utf8($value, $start, $length) { return substr($value, $start, $length); }
function F_decode_tcecode($value) { return '[[decoded:' . $value . ']]'; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_rich_content_editor_button($name) { return '<RICH:' . $name . '>'; }
function tcecode_editor_tag_buttons($form, $name) { return '<TAGS:' . $name . '>'; }
function get_form_row_checkbox($name, $label, $title, $required, $value, $checked, ...$arguments) {
    return '<CHECK:' . $name . ':' . ($checked ? '1' : '0') . '>';
}
function get_form_row_text_input($name, $label, $title, $required, $value, ...$arguments) {
    return '<TEXT:' . $name . ':' . ($value ?? '') . '>';
}
function F_submit_button($name, $label, $title) { echo '<BUTTON:' . $name . ':' . $label . '>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_answer.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:list<string>} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(8, $result['queries']);
        self::assertSame(
            'SELECT question_type,question_description FROM questions WHERE question_id=21 LIMIT 1',
            $result['queries'][0] ?? null,
        );
        self::assertStringContainsString('AND answer_id=31 LIMIT 1', $result['queries'][1] ?? '');
        self::assertSame('SELECT * FROM answers WHERE answer_id=31 LIMIT 1', $result['queries'][2] ?? null);
        self::assertStringContainsString('<option value="5" selected="selected">1. + Arithmetic', $result['html']);
        self::assertStringContainsString('<option value="6">2. - Geometry', $result['html']);
        self::assertStringContainsString('<option value="11" selected="selected">1. + Numbers', $result['html']);
        self::assertStringContainsString('<option value="21" selected="selected">1. S What is two plus two?', $result['html']);
        self::assertStringContainsString('<option value="31" selected="selected">1. T Four &amp; only', $result['html']);
        self::assertStringContainsString('>Four &amp; only</textarea>', $result['html']);
        self::assertStringContainsString('>Because &lt;four&gt;</textarea>', $result['html']);
        self::assertStringContainsString('<CHECK:answer_isright:1>', $result['html']);
        self::assertStringContainsString('<CHECK:answer_enabled:1>', $result['html']);
        self::assertStringContainsString('<TEXT:answer_weight:40>', $result['html']);
        self::assertStringContainsString('<option value="2" selected="selected">2</option>', $result['html']);
        self::assertStringContainsString('<option value="65" selected="selected">A</option>', $result['html']);
        self::assertStringContainsString('<BUTTON:update:Update>', $result['html']);
        self::assertStringContainsString('<BUTTON:add:Add>', $result['html']);
        self::assertStringContainsString('<BUTTON:delete:Delete>', $result['html']);
        self::assertStringContainsString('[[decoded:Four & only]]', $result['html']);
        self::assertStringContainsString('question_id=21&amp;firstrow=20', $result['html']);
        self::assertStringContainsString('#qid_21', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringContainsString('Answer help', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
        self::assertStringNotContainsString('<FORM-ERROR>', $result['html']);
    }
}
