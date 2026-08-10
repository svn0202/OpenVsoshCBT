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
                    . 'function F_selectAnswers(...$arguments) { $GLOBALS["select_calls"][] = $arguments; '
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
                    . 'preg_match("/function (F_selectAnswers)\\(/", '
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
}
