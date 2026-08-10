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
                    . '$GLOBALS["results"] = ["first-empty", false, "question", true]; '
                    . '$row = ["question_fullscreen" => false, "testlog_answer_version" => 4, '
                    . '"testlog_testuser_id" => 55, "question_description" => "Question", '
                    . '"question_type" => 3, "testlog_answer_text" => "Saved answer", '
                    . '"question_timer" => 0, "testlog_display_time" => "2026-08-10 12:00:00"]; '
                    . '$GLOBALS["rows"] = ["first-empty" => [false], "question" => [$row]]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function f_get_test_data($testId) { return ["test_noanswer_enabled" => false, '
                    . '"test_duration_time" => 30, "test_logout_on_timeout" => true]; } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . 'function f_get_test_start_time($testUserId) { return 1000; } '
                    . 'function F_tmf_question_options($description) { return []; } '
                    . 'function F_tmf_question_editor_description($description) { return "EDITED:" . $description; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function F_decode_tcecode($value) { return "[" . $value . "]"; } '
                    . 'function F_tmf_attachment_list($testlogId) { return ["one"]; } '
                    . 'function F_tmf_attachment_html($testlogId) { return "<ATTACHMENTS:" . $testlogId . ">"; } '
                    . 'function F_questionsMenu(...$arguments) { return "<MENU:" . implode(",", '
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
                    . '$qualified(7, 8, "form"), $qualified(7, 8, "form")]; '
                    . 'echo json_encode([$outputs, $GLOBALS["queries"], '
                    . '$GLOBALS["errors"], $GLOBALS["examtime"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: array{0: null, 1: null, 2: string, 3: string}, 1: array{0: string, 1: string, 2: string, 3: string}, 2: int, 3: int} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$outputs, $queries, $errors, $examtime] = $decoded;
        self::assertSame([null, null, ''], array_slice($outputs, 0, 3));
        self::assertStringContainsString('name="testid" id="testid" value="7"', $outputs[3]);
        self::assertStringContainsString('name="testlogid" id="testlogid" value="8"', $outputs[3]);
        self::assertStringContainsString('name="answer_version" id="answer_version" value="4"', $outputs[3]);
        self::assertStringContainsString('name="examtime" id="examtime" value="2800"', $outputs[3]);
        self::assertStringContainsString('name="timeout_logout" id="timeout_logout" value="1"', $outputs[3]);
        self::assertStringContainsString("<label for=\"answertext\">[EDITED:Question]\n</label>", $outputs[3]);
        self::assertStringContainsString('>Saved answer</textarea>', $outputs[3]);
        self::assertStringContainsString('уже сохранено: 1.', $outputs[3]);
        self::assertStringContainsString('<ATTACHMENTS:8>', $outputs[3]);
        self::assertStringContainsString('<MENU:55,8,0>', $outputs[3]);
        self::assertCount(4, $queries);
        self::assertStringContainsString("SET testuser_last_activity='2026-08-10 12:34:56'", $queries[3]);
        self::assertSame(1, $errors);
        self::assertSame(2800, $examtime);
    }
}
