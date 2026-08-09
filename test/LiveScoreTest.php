<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class LiveScoreTest extends TestCase
{
    public function testExecutedTestCounterNormalizesBoundsAndReturnsDalCount(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_TIMESTAMP_FORMAT", "Y-m-d H:i:s"); define("K_TABLE_TESTUSER_STAT", "test_stats"); '
                    . '$GLOBALS["count_args"] = []; function F_count_rows($table, $where) '
                    . '{ $GLOBALS["count_args"] = [$table, $where]; return 7; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_count_executed_tests/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = str_replace("    require_once \'../config/tce_config.php\';\\n", "", $function); '
                    . 'eval($function); $count = F_count_executed_tests('
                    . '"2024-02-03 04:05:06", "2024-02-04 05:06:07"); '
                    . 'echo json_encode([$count, $GLOBALS["count_args"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [7, ['test_stats', "WHERE tus_date>='2024-02-03 04:05:06' AND tus_date<='2024-02-04 05:06:07'"]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testLiveScoreKeepsValidationFeatureFlagAndRoundingBehavior(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_TABLE_TESTS", "tests"); define("K_TABLE_TESTS_LOGS", "test_logs"); '
                    . '$GLOBALS["db"] = new stdClass(); $GLOBALS["results"] = []; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return $result; } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_tmf_live_score/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = str_replace("    require_once \'../config/tce_config.php\';\\n", "", $function); '
                    . 'eval($function); '
                    . '$invalid = F_tmf_live_score(0, 2); '
                    . '$GLOBALS["results"] = [["test_live_score" => 0]]; $disabled = F_tmf_live_score(3, 4); '
                    . '$GLOBALS["results"] = [["test_live_score" => 1], ["live_score" => "12.3456"]]; '
                    . '$enabled = F_tmf_live_score(3, 4); '
                    . '$GLOBALS["results"] = [["test_live_score" => 1], false]; $failed = F_tmf_live_score(3, 4); '
                    . 'echo json_encode([$invalid, $disabled, $enabled, $failed, $GLOBALS["queries"]], '
                    . 'JSON_PRESERVE_ZERO_FRACTION);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                null,
                null,
                12.346,
                0.0,
                [
                    'SELECT test_live_score FROM tests WHERE test_id=3 LIMIT 1',
                    'SELECT test_live_score FROM tests WHERE test_id=3 LIMIT 1',
                    'SELECT COALESCE(SUM(testlog_score),0) AS live_score FROM test_logs WHERE testlog_testuser_id=4',
                    'SELECT test_live_score FROM tests WHERE test_id=3 LIMIT 1',
                    'SELECT COALESCE(SUM(testlog_score),0) AS live_score FROM test_logs WHERE testlog_testuser_id=4',
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
