<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminQuestionEditorControllerTest extends TestCase
{
    public function testSelectedQuestionFieldsOptionsNavigationAndActionsAreRendered(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_QUESTIONS', 10);
define('K_DATABASE_TYPE', 'MYSQL');
define('K_ENABLE_QUESTION_EXPLANATION', true);
define('K_NEWLINE', "\n");
define('K_PATH_CACHE', '/tmp');
define('K_PATH_SHARED_JSCRIPTS', '/shared/js/');
define('K_QUESTION_DIFFICULTY_LEVELS', 5);
define('K_SELECT_SUBSTRING', 80);
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_SUBJECTS', 'subjects');
define('K_TABLE_TESTS_LOGS', 'test_logs');
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
preg_match_all("/\['([a-z][a-z0-9_]*)'\]/", $source, $labels);
$l = array_fill_keys(array_unique($labels[1]), 'label');
$l['a_meta_charset'] = 'UTF-8';
$l['w_module'] = 'Module';
$l['w_subject'] = 'Subject';
$l['w_question'] = 'Question';
$l['w_explanation'] = 'Explanation';
$l['w_enabled'] = 'Enabled';
$l['w_confirm'] = 'Confirm';
$l['w_update'] = 'Update';
$l['w_add'] = 'Add';
$l['w_delete'] = 'Delete';
$l['w_clear'] = 'Clear';
$l['w_list'] = 'List';
$l['w_preview'] = 'Preview';
$l['t_subjects_editor'] = 'Subject editor';
$l['t_questions_list'] = 'Question list';
$l['t_answers_editor'] = 'Answer editor';
$l['hp_edit_question'] = 'Question help';
$db = 'db';
$formstatus = true;
$menu_mode = '';
$_FILES = [];
$_POST = [];
$_REQUEST = [
    'subject_module_id' => '5', 'question_subject_id' => '11', 'question_id' => '21', 'firstrow' => '20',
];
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_edit_question.php'];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [];
function f_get_boolean($value) { return $value === true || $value === 1 || $value === '1'; }
function f_legacy_literal_equals($value, $expected) { return (string) $value === $expected; }
function f_is_authorized_user(...$arguments) { return true; }
function F_tmf_question_options($description) {
    return [
        'similarity_threshold' => 75, 'matching_positions' => 4,
        'matching_reuse_positions' => true, 'audio_play_limit' => 2,
    ];
}
function F_tmf_question_editor_description($description) { return 'Question body <tag>'; }
function F_select_modules_sql() { return 'SELECT modules'; }
function F_select_subjects_sql($where) { return 'SELECT subjects WHERE ' . $where; }
function F_db_query($sql, $db) {
    $normalized = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $normalized;
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        str_contains($normalized, 'SELECT subject_module_id, question_subject_id FROM') => [[
            'subject_module_id' => '5', 'question_subject_id' => '11',
        ]],
        $normalized === 'SELECT * FROM questions WHERE question_id=21 LIMIT 1' => [[
            'question_id' => '21', 'question_subject_id' => '11',
            'question_description' => 'Stored question metadata', 'question_explanation' => 'Because <reason>',
            'question_type' => '2', 'question_difficulty' => '3', 'question_enabled' => '1',
            'question_position' => '2', 'question_timer' => '30', 'question_fullscreen' => '1',
            'question_inline_answers' => '1', 'question_auto_next' => '1', 'question_shuffle_answers' => '1',
        ]],
        $normalized === 'SELECT modules' => [
            ['module_id' => '5', 'module_enabled' => '1', 'module_name' => 'Arithmetic'],
            ['module_id' => '6', 'module_enabled' => '0', 'module_name' => 'Geometry'],
        ],
        $normalized === 'SELECT subjects WHERE subject_module_id=5' => [[
            'subject_id' => '11', 'subject_enabled' => '1', 'subject_name' => 'Numbers',
        ]],
        str_starts_with($normalized, 'SELECT * FROM questions WHERE question_subject_id=11') => [
            ['question_id' => '21', 'question_enabled' => '1', 'question_type' => '2',
                'question_position' => '2', 'question_description' => 'Selected question'],
            ['question_id' => '22', 'question_enabled' => '0', 'question_type' => '1',
                'question_position' => '1', 'question_description' => 'Disabled question'],
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
    return '<TEXT:' . $name . ':' . $value . '>';
}
function get_form_row_select_box($name, $label, $title, $required, $value, ...$arguments) {
    return '<SELECT:' . $name . ':' . $value . '>';
}
function F_submit_button($name, $label, $title) { echo '<BUTTON:' . $name . ':' . $label . '>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_question.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:list<string>} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(5, $result['queries']);
        self::assertStringContainsString('AND question_id=21 LIMIT 1', $result['queries'][0] ?? '');
        self::assertSame('SELECT * FROM questions WHERE question_id=21 LIMIT 1', $result['queries'][1] ?? null);
        self::assertStringContainsString('<option value="5" selected="selected">1. + Arithmetic', $result['html']);
        self::assertStringContainsString('<option value="6">2. - Geometry', $result['html']);
        self::assertStringContainsString('<option value="11" selected="selected">1. + Numbers', $result['html']);
        self::assertStringContainsString('<option value="21" selected="selected">1. M Selected question', $result['html']);
        self::assertStringContainsString('<option value="22">2. - Disabled question', $result['html']);
        self::assertStringContainsString('>Question body &lt;tag&gt;</textarea>', $result['html']);
        self::assertStringContainsString('>Because &lt;reason&gt;</textarea>', $result['html']);
        self::assertStringContainsString('id="multiple_answers" value="2" checked="checked"', $result['html']);
        self::assertStringContainsString('<SELECT:question_difficulty:3>', $result['html']);
        self::assertStringContainsString('<option value="2" selected="selected">2</option>', $result['html']);
        self::assertStringContainsString('<TEXT:question_timer:30>', $result['html']);
        self::assertStringContainsString('<TEXT:question_similarity_threshold:75>', $result['html']);
        self::assertStringContainsString('<TEXT:question_matching_positions:4>', $result['html']);
        self::assertStringContainsString('<CHECK:question_matching_reuse_positions:1>', $result['html']);
        self::assertStringContainsString('<TEXT:question_audio_play_limit:2>', $result['html']);
        self::assertStringContainsString('<CHECK:question_fullscreen:1>', $result['html']);
        self::assertStringContainsString('<CHECK:question_inline_answers:1>', $result['html']);
        self::assertStringContainsString('<CHECK:question_auto_next:1>', $result['html']);
        self::assertStringContainsString('<CHECK:question_shuffle_answers:1>', $result['html']);
        self::assertStringContainsString('<CHECK:question_enabled:1>', $result['html']);
        self::assertStringContainsString('<BUTTON:update:Update>', $result['html']);
        self::assertStringContainsString('<BUTTON:add:Add>', $result['html']);
        self::assertStringContainsString('<BUTTON:delete:Delete>', $result['html']);
        self::assertStringContainsString('subject_id=11&amp;submitted=1&amp;firstrow=20#qid_21', $result['html']);
        self::assertStringContainsString('answer_question_id=21&amp;firstrow=20', $result['html']);
        self::assertStringContainsString('[[decoded:Question body <tag>]]', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringContainsString('Question help', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
        self::assertStringNotContainsString('<FORM-ERROR>', $result['html']);
    }
}
