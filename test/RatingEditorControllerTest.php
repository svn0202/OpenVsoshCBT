<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class RatingEditorControllerTest extends TestCase
{
    public function testUpdatePersistsRatingAndRendersSelectedAnswer(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_RATING', 10);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_ENABLE_QUESTION_EXPLANATION', true);
define('K_NEWLINE', "\n");
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_TESTS_LOGS', 'test_logs');
define('K_TABLE_TEST_USER', 'test_users');
define('K_TABLE_USERS', 'users');
$l = [
    'a_meta_charset' => 'UTF-8', 'h_answer' => 'Answer', 'h_display_all' => 'Display all',
    'h_display_user_info' => 'Display users', 'h_question_description' => 'Question',
    'h_score_right' => 'Right score', 'h_score_unanswered' => 'Unanswered score',
    'h_score_wrong' => 'Wrong score', 'h_score' => 'Score', 'h_select_answer' => 'Select answer',
    'h_test' => 'Test', 'h_update' => 'Update rating', 'hp_edit_rating' => 'Rating help',
    'm_authorization_denied' => 'Denied', 'm_score_higher_than_max' => 'Too high',
    'm_updated' => 'Updated', 't_rating_editor' => 'Rating editor', 'w_answer' => 'Answer',
    'w_comment' => 'Comment', 'w_display_all' => 'Display all',
    'w_display_user_info' => 'Display users', 'w_explanation' => 'Explanation', 'w_order' => 'Order',
    'w_question' => 'Question', 'w_score_right' => 'Right', 'w_score_unanswered' => 'Unanswered',
    'w_score_wrong' => 'Wrong', 'w_score' => 'Score', 'w_select' => 'Select', 'w_test' => 'Test',
    'w_time' => 'Time', 'w_update' => 'Update', 'w_user' => 'User',
];
$db = 'db';
$menu_mode = 'update';
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_edit_rating.php'];
$_SESSION = ['session_user_level' => 10];
$_POST = [];
$_REQUEST = [
    'test_id' => '5', 'testlog_id' => '12', 'testlog_score' => '2.5',
    'testlog_comment' => "Teacher's note", 'max_score' => '8',
    'display_user_info' => '1', 'display_all' => '1', 'sqlordermode' => '2',
];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [];
function f_is_authorized_user(...$arguments) { return true; }
function F_count_rows($sql) { return 1; }
function F_check_form_fields() { return true; }
function F_escape_sql($db, $value) { return str_replace("'", "''", (string) $value); }
function F_select_executed_tests_sql() { return 'SELECT executed_tests'; }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    if (str_starts_with($sql, 'UPDATE')) { return true; }
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        str_starts_with($sql, 'SELECT test_score_right') => [[
            'test_score_right' => '4', 'question_difficulty' => '2',
        ]],
        str_starts_with($sql, 'SELECT test_id, test_score_right') => [[
            'test_id' => '5', 'test_score_right' => '4', 'test_score_wrong' => '-1',
            'test_score_unanswered' => '0.5', 'testlog_id' => '12', 'testlog_score' => '2.5',
            'testlog_answer_text' => 'Essay answer', 'testlog_comment' => "Teacher's note",
            'question_description' => 'Essay question', 'question_difficulty' => '2',
            'question_explanation' => 'Expected reasoning',
        ]],
        $sql === 'SELECT executed_tests' => [[
            'test_id' => '5', 'test_begin_time' => '2026-08-11 12:30:00', 'test_name' => 'Final & Test',
        ]],
        str_starts_with($sql, 'SELECT testlog_id, testlog_score') => [[
            'testlog_id' => '12', 'testlog_score' => '2.5', 'user_lastname' => 'Doe',
            'user_firstname' => 'Jane', 'user_name' => 'jane', 'question_description' => 'Essay question',
        ]],
        default => [],
    };
    $GLOBALS['rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) {
    $id = get_resource_id($result);
    return array_shift($GLOBALS['rows'][$id]);
}
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error($type, $message, ...$arguments) { echo "<$type:$message>"; }
function F_decode_tcecode($value) { return '[decoded:' . $value . ']'; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_checkbox($name, $label, $title, $required, $value, $checked) {
    return '<CHECKBOX:' . $name . ':' . (int) $checked . '>';
}
function get_form_row_text_input($name, $label, $title, $required, $value, ...$arguments) {
    return '<TEXT:' . $name . ':' . $value . '>';
}
function get_form_row_text_box($name, $label, $title, $value) { return '<BOX:' . $name . ':' . $value . '>'; }
function F_submit_button($name, $value, $title) { echo '<SUBMIT:' . $name . ':' . $value . '>'; }
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
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_rating.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:list<string>} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $update_query = $result['queries'][1] ?? null;
        self::assertIsString($update_query);
        self::assertStringContainsString("UPDATE test_logs SET testlog_score=2.5, testlog_comment='Teacher''s note'", $update_query);
        self::assertStringContainsString('<MESSAGE:Updated>', $result['html']);
        self::assertStringContainsString('<option value="5" selected="selected">2026-08-11 : Final &amp; Test</option>', $result['html']);
        self::assertStringContainsString('<option value="12" selected="selected">+ 12 :: Doe Jane - jane</option>', $result['html']);
        self::assertStringContainsString('[decoded:Essay question]', $result['html']);
        self::assertStringContainsString('[decoded:Expected reasoning]', $result['html']);
        self::assertStringContainsString('[decoded:Essay answer]', $result['html']);
        self::assertStringContainsString('data-fraction="3/4"', $result['html']);
        self::assertStringContainsString('3/4 [6]', $result['html']);
        self::assertStringContainsString('1/2 [4]', $result['html']);
        self::assertStringContainsString('1/4 [2]', $result['html']);
        self::assertStringContainsString('<BOX:testlog_comment:Teacher\'s note>', $result['html']);
        self::assertStringContainsString('<SUBMIT:update:Update>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
    }
}
