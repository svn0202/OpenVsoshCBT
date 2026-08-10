<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class QuestionSelectionTest extends TestCase
{
    public function testEmptyQuestionSelectionReportsMessageAndReturnsFalse(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"]["m_databasempty"] = "No questions"; $GLOBALS["calls"] = []; '
                    . 'function F_count_rows($table) { $GLOBALS["calls"]["table"] = $table; return 0; } '
                    . 'function F_print_error(...$arguments) { $GLOBALS["calls"]["message"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_show_select_questions\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$result = F_show_select_questions('
                    . '"enabled=1", "2", "3", "invalid", "1", "4", "25", true); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_show_all_questions.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => false,
                'calls' => [
                    'table' => 'tce_questions',
                    'message' => ['MESSAGE', 'No questions'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testQuestionSelectionRendersCardActionsAndPagination(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_DATABASE_TYPE', 'MYSQL');
define('K_ENABLE_QUESTION_EXPLANATION', false);
define('K_NEWLINE', "\n");
define('K_TABLE_QUESTIONS', 'questions');
$_REQUEST = ['checkall' => '1'];
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_show_all_questions.php'];
$db = 'db';
$l = [];
foreach ([
    'a_meta_charset', 'a_meta_dir', 'h_position', 'h_question_difficulty', 'h_question_timer',
    'h_subject', 'h_update', 'm_databasempty', 'm_with_selected', 't_questions_editor', 'w_all',
    'w_auto_next', 'w_check_all', 'w_copy', 'w_delete', 'w_disable', 'w_disabled', 'w_edit',
    'w_enable', 'w_enabled', 'w_explanation', 'w_free_answer', 'w_fullscreen', 'w_inline_answers',
    'w_matching_answer', 'w_move', 'w_multiple_answers', 'w_ordering_answer', 'w_select',
    'w_single_answer', 'w_subject', 'w_uncheck_all', 'w_update',
] as $key) { $l[$key] = $key; }
$l['a_meta_charset'] = 'UTF-8';
$l['a_meta_dir'] = 'ltr';
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [
    'questions' => [[
        'question_id' => '11', 'question_enabled' => '1', 'question_type' => '1',
        'question_description' => 'Question body', 'question_explanation' => '',
        'question_difficulty' => '3', 'question_position' => '2', 'question_fullscreen' => '0',
        'question_inline_answers' => '0', 'question_auto_next' => '0', 'question_timer' => '30',
    ]],
    'subjects' => [[
        'module_id' => '2', 'module_name' => 'Module & Two', 'subject_id' => '4',
        'subject_name' => 'Destination',
    ]],
];
$GLOBALS['navigator'] = null;
function F_count_rows($table) { return 1; }
function F_escape_sql($db, $value) { return (string) $value; }
function f_legacy_literal_equals($value, $expected) { return $value === $expected; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function f_get_boolean($value) { return (bool) $value; }
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql));
    return str_contains($sql, 'FROM questions') ? 'questions' : 'subjects';
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][$result]); }
function F_display_db_error() { echo '<DB-ERROR>'; }
function F_print_error($type, $message) { echo "<$type:$message>"; }
function F_decode_tcecode($value) { return '[decoded:' . $value . ']'; }
function f_text_to_xml($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function F_select_module_subjects_sql($where) { return 'SELECT module_subjects WHERE ' . $where; }
function F_submit_button($name, $value, $title) { echo '<SUBMIT:' . $name . ':' . $value . '>'; }
function F_show_page_navigator($script, $sql, $first, $rows, $params) {
    $GLOBALS['navigator'] = [$script, preg_replace('/\s+/', ' ', trim($sql)), $first, $rows, $params];
}
$source = file_get_contents($argv[1]);
preg_match('/function [Ff]_show_select_questions\(/', $source, $match, PREG_OFFSET_CAPTURE);
$function = substr($source, $match[0][1]);
$function = preg_replace('/^\s*require_once [^;]+;\n/m', '', $function);
ob_start();
eval('namespace Harness; ' . $function);
$result = f_show_select_questions('', 2, 3, 'question_id', 0, 0, 25, true);
$html = ob_get_clean();
echo json_encode([$result, $html, $GLOBALS['queries'], $GLOBALS['navigator']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_show_all_questions.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0:bool,1:string,2:list<string>,3:array{string,string,int,int,string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$result, $html, $queries, $navigator] = $decoded;
        self::assertTrue($result);
        self::assertStringContainsString('id="qid_11"', $html);
        self::assertStringContainsString('name="questionid1"', $html);
        self::assertStringContainsString('checked="checked"', $html);
        self::assertStringContainsString('<span class="question-card__type">w_single_answer</span>', $html);
        self::assertStringContainsString('[decoded:Question body]', $html);
        self::assertStringContainsString('question_id=11&amp;firstrow=0', $html);
        self::assertStringContainsString('<option value="4">&nbsp;&nbsp;&nbsp;&nbsp;Destination</option>', $html);
        self::assertStringContainsString('<SUBMIT:update:w_update>', $html);
        self::assertCount(2, $queries);
        $question_query = $queries[0] ?? null;
        self::assertIsString($question_query);
        self::assertStringContainsString('ORDER BY question_id LIMIT 25 OFFSET 0', $question_query);
        self::assertSame('/admin/code/tce_show_all_questions.php', $navigator[0]);
        self::assertSame(0, $navigator[2]);
        self::assertSame(25, $navigator[3]);
        self::assertStringContainsString('&amp;subject_module_id=2', $navigator[4]);
        self::assertStringNotContainsString('<DB-ERROR>', $html);
    }

    public function testAddingQuestionAnswersPreservesFreeTextAndSelectionBehavior(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_QUESTIONS", "questions"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["select_calls"] = []; $GLOBALS["logged"] = []; '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function f_get_boolean($value) { return filter_var($value, FILTER_VALIDATE_BOOLEAN); } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return true; } '
                    . 'function F_db_fetch_array($result) { return ["question_shuffle_answers" => false]; } '
                    . 'function f_select_answers(...$arguments) { $GLOBALS["select_calls"][] = $arguments; '
                    . 'return [2 => 17, 0 => 13]; } '
                    . 'function f_add_log_answers($testlogId, $answerIds) { '
                    . '$GLOBALS["logged"][] = [$testlogId, $answerIds]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_add_question_answers)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$testdata = ["test_random_answers_order" => false, "test_answers_order_mode" => 0, '
                    . '"test_random_questions_select" => true, "test_random_answers_select" => false]; '
                    . '$freeText = $qualified(31, 41, 3, 0, 0, $testdata); '
                    . '$multiple = $qualified(32, 42, 2, 2, 0, $testdata); '
                    . 'echo json_encode([$freeText, $multiple, $GLOBALS["queries"], '
                    . '$GLOBALS["select_calls"], $GLOBALS["logged"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                true,
                true,
                ['SELECT question_shuffle_answers FROM questions WHERE question_id=42 LIMIT 1'],
                [[42, '', false, 2, 0, false, 0]],
                [[32, [0 => 13, 2 => 17]]],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAnswerSelectionPreservesOrderingKeysQueriesAndFailureResult(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_ANSWERS", "answers"); '
                    . 'define("K_DATABASE_TYPE", "MYSQL"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . '$GLOBALS["query_results"] = [[['
                    . '"answer_id" => 101, "answer_position" => 3], '
                    . '["answer_id" => 102, "answer_position" => 1]], [['
                    . '"answer_id" => 21, "answer_position" => 4], '
                    . '["answer_id" => 17, "answer_position" => 2]], false]; '
                    . 'function F_escape_sql($db, $value) { return (string) $value; } '
                    . 'function f_legacy_literal_equals($value, $expected) { return $value === $expected; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . '$rows = array_shift($GLOBALS["query_results"]); if ($rows === false) { return false; } '
                    . '$GLOBALS["rows"] = $rows; return true; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error($show = true) { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_select_answers)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$byPosition = $qualified("07", 1, false, 2, 0, false, 0); '
                    . '$byId = $qualified(8, "", false, 0, 5, false, 2); '
                    . '$failed = $qualified(9, "", false, 0, 0, false, 1); '
                    . 'echo json_encode([$byPosition, $byId, $failed, '
                    . '$GLOBALS["queries"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [3 => 101, 1 => 102],
                [21 => 21, 17 => 17],
                false,
                [
                    "SELECT answer_id, answer_position\n\t\tFROM answers\n\t\tWHERE answer_question_id=7\n"
                        . "\t\tAND answer_enabled='1' AND answer_isright='1'"
                        . ' AND answer_position>0 ORDER BY answer_position LIMIT 2',
                    "SELECT answer_id, answer_position\n\t\tFROM answers\n\t\tWHERE answer_question_id=8\n"
                        . "\t\tAND answer_enabled='1' ORDER BY answer_id",
                    "SELECT answer_id, answer_position\n\t\tFROM answers\n\t\tWHERE answer_question_id=9\n"
                        . "\t\tAND answer_enabled='1' ORDER BY answer_description",
                ],
                1,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testQuestionFormPreservesEarlyReturnsErrorsAndTextQuestionMarkup(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_users"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); define("K_TABLE_QUESTIONS", "questions"); '
                    . 'define("K_SECONDS_IN_MINUTE", 60); define("K_ANSWER_TEXTAREA_COLS", 40); '
                    . 'define("K_ANSWER_TEXTAREA_ROWS", 8); define("TMF_ATTACHMENT_MAX_FILES", 3); '
                    . 'define("K_TIMESTAMP_FORMAT", "format"); define("K_NEWLINE", "\\n"); '
                    . '$GLOBALS["db"] = "db"; $_SESSION["session_user_id"] = "11"; '
                    . '$GLOBALS["examtime"] = 0; $GLOBALS["timeout_logout"] = false; '
                    . '$GLOBALS["results"] = ["first-empty", false, "question", true, "question-2", true]; '
                    . '$row = ["question_fullscreen" => false, "testlog_answer_version" => 4, '
                    . '"testlog_testuser_id" => 55, "question_description" => "Question", '
                    . '"question_type" => 3, "testlog_answer_text" => "Saved answer", '
                    . '"question_timer" => 0, "testlog_display_time" => "2026-08-10 12:00:00"]; '
                    . '$GLOBALS["rows"] = ["first-empty" => [false], "question" => [$row], "question-2" => [$row]]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function f_get_test_data($testId) { return ["test_noanswer_enabled" => false, '
                    . '"test_duration_time" => 30, "test_logout_on_timeout" => true]; } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$GLOBALS["start_times"] = [1000, false]; '
                    . 'function f_get_test_start_time($testUserId) { return array_shift($GLOBALS["start_times"]); } '
                    . 'function F_tmf_question_options($description) { return []; } '
                    . 'function F_tmf_question_editor_description($description) { return "EDITED:" . $description; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function F_decode_tcecode($value) { return "[" . $value . "]"; } '
                    . 'function F_tmf_attachment_list($testlogId) { return ["one"]; } '
                    . 'function F_tmf_attachment_html($testlogId) { return "<ATTACHMENTS:" . $testlogId . ">"; } '
                    . 'function f_questions_menu(...$arguments) { return "<MENU:" . implode(",", '
                    . '[$arguments[1], $arguments[2], (int) $arguments[3]]) . ">"; } '
                    . 'function date($format) { return "2026-08-10 12:34:56"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_question_form)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$outputs = [$qualified(0, 0, "form"), $qualified(7, 0, "form"), '
                    . '$qualified(7, 8, "form"), $qualified(7, 8, "form"), $qualified(7, 8, "form")]; '
                    . 'echo json_encode([$outputs, $GLOBALS["queries"], '
                    . '$GLOBALS["errors"], $GLOBALS["examtime"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: array{0: null, 1: null, 2: string, 3: string, 4: string}, 1: array{string,string,string,string,string,string}, 2: int, 3: int} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$outputs, $queries, $errors, $examtime] = $decoded;
        self::assertSame([null, null, ''], array_slice($outputs, 0, 3));
        self::assertStringContainsString('name="testid" id="testid" value="7"', $outputs[3]);
        self::assertStringContainsString('name="testlogid" id="testlogid" value="8"', $outputs[3]);
        self::assertStringContainsString('name="answer_version" id="answer_version" value="4"', $outputs[3]);
        self::assertStringContainsString('name="examtime" id="examtime" value="2800"', $outputs[3]);
        self::assertStringContainsString('name="examtime" id="examtime" value="1800"', $outputs[4]);
        self::assertStringContainsString('name="timeout_logout" id="timeout_logout" value="1"', $outputs[3]);
        self::assertStringContainsString("<label for=\"answertext\">[EDITED:Question]\n</label>", $outputs[3]);
        self::assertStringContainsString('>Saved answer</textarea>', $outputs[3]);
        self::assertStringContainsString('уже сохранено: 1.', $outputs[3]);
        self::assertStringContainsString('<ATTACHMENTS:8>', $outputs[3]);
        self::assertStringContainsString('<MENU:55,8,0>', $outputs[3]);
        self::assertCount(6, $queries);
        self::assertStringContainsString("SET testuser_last_activity='2026-08-10 12:34:56'", $queries[3]);
        self::assertStringContainsString("SET testuser_last_activity='2026-08-10 12:34:56'", $queries[5]);
        self::assertSame(1, $errors);
        self::assertSame(1800, $examtime);
    }

    public function testQuestionsMenuPreservesSelectedReviewNavigationAndToolbarMarkup(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_QUESTIONS", "questions"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); define("K_NEWLINE", "\\n"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["l"] = ['
                    . '"h_question_displayed" => "Displayed", "h_question_not_displayed" => "Not displayed", '
                    . '"h_question_answered" => "Answered", "h_question_not_answered" => "Not answered", '
                    . '"w_max_score" => "Maximum", "w_question" => "Question", '
                    . '"ov_increase_text" => "Zoom in", "ov_decrease_text" => "Zoom out", '
                    . '"ov_switch_theme" => "Theme", "ov_mark_for_review" => "Review", '
                    . '"w_image" => "Image", "w_close" => "Close", "w_fullscreen" => "Fullscreen", '
                    . '"w_previous" => "Previous", "w_next" => "Next", "w_questions" => "Questions", '
                    . '"ov_answer_saving" => "Saving", "ov_answer_saved" => "Saved", '
                    . '"ov_answer_not_saved" => "Error", "ov_answer_save_conflict" => "Conflict", '
                    . '"ov_answer_unsaved" => "Unsaved", "ov_answer_retrying" => "Retrying", '
                    . '"ov_save" => "Save", "a_meta_charset" => "UTF-8", "a_meta_language" => "ru"]; '
                    . '$row = ["question_description" => "Question description", "question_difficulty" => 2, '
                    . '"question_timer" => 0, "testlog_id" => 8, "testlog_answer_text" => "", '
                    . '"testlog_display_time" => "shown", "testlog_change_time" => "answered", '
                    . '"testlog_reviewed" => true]; $GLOBALS["rows"] = [$row, false]; '
                    . '$GLOBALS["queries"] = []; '
                    . 'function F_tmf_question_options($description) { return ["audio_play_limit" => 2]; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return true; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function f_tcecode_to_title($value) { return "TITLE:" . $value; } '
                    . 'function f_tcecode_to_line($value) { return "LINE:" . $value; } '
                    . 'function F_tmf_live_score($testId, $testUserId) { return null; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_questions_menu)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$testdata = ["test_score_right" => 2, "test_id" => 7, '
                    . '"test_menu_enabled" => true, "test_auto_fullscreen" => false, '
                    . '"test_hide_exam_info" => false, "test_disable_previous" => false, '
                    . '"test_disable_next" => false]; '
                    . '$markup = $qualified($testdata, "055", "008", false); '
                    . 'echo json_encode([$markup, $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: string, 1: array{0: string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$markup, $queries] = $decoded;
        self::assertStringContainsString('data-audio-play-limit="2"', $markup);
        self::assertStringContainsString('Question <span>1</span> / 1', $markup);
        self::assertStringContainsString('class="selected marked-for-review" data-testlog-id="8"', $markup);
        self::assertStringContainsString('title="Displayed">+', $markup);
        self::assertStringContainsString('title="Answered">+', $markup);
        self::assertStringContainsString('title="Maximum: 4">  4.0', $markup);
        self::assertStringContainsString('LINE:Question description', $markup);
        self::assertStringContainsString('data-reviewed="1" checked="checked"', $markup);
        self::assertStringContainsString('id="prevquestion"', $markup);
        self::assertStringContainsString('id="nextquestion"', $markup);
        self::assertSame(3, substr_count($markup, 'disabled="disabled"'));
        self::assertStringContainsString('id="saveanswer"', $markup);
        self::assertStringContainsString('<details class="tcecontentbox exam-question-list" open="open">', $markup);
        self::assertStringContainsString('testlog_testuser_id=55', $queries[0]);
    }
}
