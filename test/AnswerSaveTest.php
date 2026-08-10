<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_answer_save.php';

final class AnswerSaveTest extends TestCase
{
    public function testNewOperationCanSaveAtCurrentVersion(): void
    {
        self::assertSame(
            'save',
            \F_tmf_answer_save_decision(4, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 4, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
        );
    }

    public function testRepeatedOperationIsIdempotent(): void
    {
        self::assertSame(
            'duplicate',
            \F_tmf_answer_save_decision(5, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 4, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
        );
    }

    public function testStaleVersionCannotOverwriteNewerAnswer(): void
    {
        self::assertSame(
            'conflict',
            \F_tmf_answer_save_decision(5, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 4, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
        );
    }

    public function testInvalidOperationIsRejected(): void
    {
        self::assertSame('invalid', \F_tmf_answer_save_decision(0, null, 0, '../../invalid'));
        self::assertSame('invalid', \F_tmf_answer_save_decision(0, null, -1, str_repeat('a', 32)));
    }

    public function testAtomicSavePreservesTransactionVersionAndLiveScore(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-answer-save-" . uniqid(); '
                    . 'mkdir($root . "/shared/code", 0700, true); mkdir($root . "/shared/config", 0700); '
                    . 'copy($argv[1], $root . "/shared/code/tce_functions_answer_save.php"); '
                    . 'file_put_contents($root . "/shared/config/tce_config.php", "<?php '
                    . 'define(\\"K_TABLE_TESTS_LOGS\\", \\"test_logs\\"); '
                    . 'define(\\"K_TABLE_TEST_USER\\", \\"test_users\\"); '
                    . 'define(\\"K_TIMESTAMP_FORMAT\\", \\"Y-m-d H:i:s\\");"); '
                    . '$GLOBALS["db"] = "db-link"; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); '
                    . 'return str_starts_with(trim($sql), "SELECT") ? "row-result" : true; } '
                    . 'function F_db_fetch_array($result) { return ["testlog_testuser_id" => "55", '
                    . '"testlog_answer_version" => "2", "testlog_answer_operation" => null]; } '
                    . 'function f_update_question_log(...$arguments) { $GLOBALS["update"] = $arguments; return true; } '
                    . 'function F_tmf_live_score($testId, $testUserId) { '
                    . '$GLOBALS["score"] = [$testId, $testUserId]; return 7.5; } '
                    . 'chdir($root . "/shared/code"); require "tce_functions_answer_save.php"; '
                    . '$invalid = f_tmf_save_question_answer(4, 9, [], "", 0, -1, str_repeat("a", 32)); '
                    . '$queryCount = count($GLOBALS["queries"]); '
                    . '$saved = f_tmf_save_question_answer(4, 9, [1 => 2], "answer", 123, 2, str_repeat("b", 32)); '
                    . '$result = [$invalid, $queryCount, $saved, $GLOBALS["queries"], '
                    . '$GLOBALS["update"], $GLOBALS["score"]]; '
                    . 'unlink($root . "/shared/code/tce_functions_answer_save.php"); '
                    . 'unlink($root . "/shared/config/tce_config.php"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared/config"); '
                    . 'rmdir($root . "/shared"); rmdir($root); echo json_encode($result);',
                dirname(__DIR__) . '/shared/code/tce_functions_answer_save.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: array{status: string, version: int},
         *     1: int,
         *     2: array{status: string, version: int, live_score: float},
         *     3: array{string, string, string, string, string},
         *     4: array{int, int, array<int, int>, string, int},
         *     5: array{int, int}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['status' => 'invalid', 'version' => -1], $decoded[0]);
        self::assertSame(0, $decoded[1]);
        self::assertSame(['status' => 'saved', 'version' => 3, 'live_score' => 7.5], $decoded[2]);
        self::assertSame('START TRANSACTION', $decoded[3][0]);
        self::assertStringContainsString('WHERE testlog_id=9 FOR UPDATE', $decoded[3][1]);
        self::assertStringContainsString('SET testlog_answer_version=3', $decoded[3][2]);
        self::assertStringContainsString('WHERE testuser_id=55 AND testuser_status<4', $decoded[3][3]);
        self::assertSame('COMMIT', $decoded[3][4]);
        self::assertSame([4, 9, [1 => 2], 'answer', 123], $decoded[4]);
        self::assertSame([4, 55], $decoded[5]);
    }

    public function testQuestionLogUpdatePreservesFailuresLimitsAndTextAnswerSql(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS_LOGS", "test_logs"); '
                    . 'define("K_TABLE_QUESTIONS", "questions"); define("K_TABLE_ANSWERS", "answers"); '
                    . 'define("K_TIMESTAMP_FORMAT", "format"); define("K_SHORT_ANSWERS_BINARY", false); '
                    . '$GLOBALS["db"] = "db"; $_SERVER["REMOTE_ADDR"] = "127.0.0.1"; '
                    . '$GLOBALS["results"] = [false, "empty", "mcma", "text", "answers", true]; '
                    . '$base = ["testlog_answer_text" => "", "question_id" => 41, '
                    . '"question_difficulty" => 2, "question_description" => "Question"]; '
                    . '$GLOBALS["rows"] = ["empty" => [false], '
                    . '"mcma" => [$base + ["question_type" => 2]], '
                    . '"text" => [$base + ["question_type" => 3]], "answers" => [false]]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function f_get_test_data($testId) { return ["test_score_right" => 2, '
                    . '"test_score_wrong" => -1, "test_score_unanswered" => 0, '
                    . '"test_mcma_partial_score" => false]; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . 'function F_tmf_question_options($description) { return ["matching_reuse_positions" => false, '
                    . '"max_selections" => 1, "similarity_threshold" => 100]; } '
                    . 'function F_tmf_selection_limit_is_valid($answers, $limit) { return false; } '
                    . 'function f_get_answer_id_from_position($testlogId, $answers) { return []; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function f_legacy_equals($left, $right) { return $left == $right; } '
                    . 'function F_tmf_short_answer_score(...$arguments) { return null; } '
                    . 'function f_empty_to_null($value) { return $value === "" ? "NULL" : "\'" . $value . "\'"; } '
                    . 'function date($format) { return "2026-08-10 12:34:56"; } '
                    . 'function get_normalized_ip($ip) { return "IP"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_update_question_log)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$returns = [$qualified("07", "08"), $qualified("07", "08"), '
                    . '$qualified("07", "08", [1 => 1]), '
                    . '$qualified("07", "08", [], "hello", "123")]; '
                    . 'echo json_encode([$returns, $GLOBALS["queries"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: array{false, true, false, true}, 1: array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}, 2: int} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$returns, $queries, $errors] = $decoded;
        self::assertSame([false, true, false, true], $returns);
        self::assertCount(6, $queries);
        self::assertStringContainsString('testlog_id=8 LIMIT 1', $queries[0]);
        self::assertStringContainsString('testlog_id=8 LIMIT 1', $queries[3]);
        self::assertStringContainsString('WHERE answer_question_id=41', $queries[4]);
        self::assertSame(
            "UPDATE test_logs SET testlog_answer_text='hello', testlog_score=NULL, "
                . "testlog_change_time='2026-08-10 12:34:56', testlog_reaction_time=123, "
                . "testlog_user_ip='IP' WHERE testlog_id=8",
            $queries[5],
        );
        self::assertSame(1, $errors);
    }
}
