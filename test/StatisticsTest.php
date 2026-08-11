<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_statistics.php';

final class StatisticsTest extends TestCase
{
    public function testUserTestStatisticsPreserveTextQuestionMarkupAndQueryFailure(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_QUESTIONS", "questions"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); define("K_TABLE_SUBJECTS", "subjects"); '
                    . 'define("K_TABLE_MODULES", "modules"); define("K_TABLE_LOG_ANSWER", "log_answers"); '
                    . 'define("K_TABLE_ANSWERS", "answers"); define("K_NEWLINE", "\\n"); '
                    . 'define("K_ENABLE_QUESTION_EXPLANATION", false); '
                    . 'define("K_ENABLE_ANSWER_EXPLANATION", false); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["l"] = ["h_answer_right" => "Right", "w_answers_right" => "Correct"]; '
                    . '$row = ["testlog_score" => "2.5", "testlog_user_ip" => "packed", '
                    . '"testlog_reaction_time" => 0, "question_description" => "Question", '
                    . '"question_explanation" => "", "question_type" => 3, '
                    . '"testlog_answer_text" => "Answer", "testlog_id" => 77, '
                    . '"testlog_comment" => "Teacher note"]; '
                    . '$invalidRow = $row; $invalidRow["testlog_display_time"] = "invalid-display"; '
                    . '$invalidRow["testlog_change_time"] = "invalid-change"; '
                    . '$choiceRow = $row; $choiceRow["question_type"] = "2"; '
                    . '$answerRow = ["logansw_position" => "0", "answer_position" => "1", '
                    . '"logansw_selected" => "1", "answer_isright" => "1", '
                    . '"answer_description" => "Choice", "answer_explanation" => ""]; '
                    . '$GLOBALS["results"] = [false, "questions", "invalid-questions", '
                    . '"choice-questions", "answers"]; '
                    . '$GLOBALS["rows"] = ["questions" => [$row, false], '
                    . '"invalid-questions" => [$invalidRow, false], '
                    . '"choice-questions" => [$choiceRow, false], "answers" => [$answerRow, false]]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; $GLOBALS["attachments"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . 'function strtotime($value) { return false; } '
                    . 'function date($format, $timestamp) { return "TIME:" . (int) $timestamp; } '
                    . 'function get_ip_as_string($value) { return "IPVALUE"; } '
                    . 'function F_decode_tcecode($value) { return "[" . $value . "]"; } '
                    . 'function f_get_boolean($value) { return (bool) (int) $value; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function F_tmf_attachment_html($testlogId) { '
                    . '$GLOBALS["attachments"][] = $testlogId; return "<ATTACHMENT>"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_print_user_test_stat)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$failed = $qualified("007"); $markup = $qualified("007"); '
                    . '$invalid = $qualified("007"); $choices = $qualified("007"); '
                    . 'echo json_encode([$failed, $markup, $invalid, $choices, $GLOBALS["queries"], '
                    . '$GLOBALS["errors"], $GLOBALS["attachments"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *   0:string,1:string,2:string,3:string,4:array{0:string,1:string,2:string,3:string,4:string},
         *   5:int,6:array{0:int,1:int}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$failed, $markup, $invalid, $choices, $queries, $errors, $attachments] = $decoded;
        self::assertSame('', $failed);
        self::assertStringContainsString('<ol class="question">', $markup);
        self::assertStringContainsString('<strong>[2.5]', $markup);
        self::assertStringContainsString('(IP:IPVALUE', $markup);
        self::assertStringContainsString('| --:--:--', $markup);
        self::assertStringContainsString('| ------', $markup);
        self::assertStringContainsString('[Question]', $markup);
        self::assertStringContainsString('[Answer]<ATTACHMENT>&nbsp;', $markup);
        self::assertStringContainsString('[Teacher note]&nbsp;', $markup);
        self::assertStringContainsString('| TIME:0', $invalid);
        self::assertStringContainsString('class="okbox">x</abbr>', $choices);
        self::assertStringContainsString('class="onbox">&reg;</abbr> [Choice]', $choices);
        self::assertCount(5, $queries);
        self::assertStringContainsString('testlog_testuser_id=7', $queries[0]);
        self::assertStringContainsString('testlog_testuser_id=7', $queries[1]);
        self::assertStringContainsString('logansw_testlog_id=\'77\'', $queries[4]);
        self::assertSame(1, $errors);
        self::assertSame([77, 77], $attachments);
    }

    public function testAllUsersStatisticsPreserveFiltersAndDisabledStatisticsShape(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS_LOGS", "test_logs"); '
                    . 'define("K_TABLE_TEST_USER", "test_users"); define("K_TABLE_USERS", "users"); '
                    . 'define("K_TABLE_USERGROUP", "user_groups"); define("K_TIMESTAMP_FORMAT", "format"); '
                    . 'define("K_SECONDS_IN_MINUTE", 60); $GLOBALS["db"] = "db"; '
                    . '$row = ["testuser_id" => 99, "testuser_test_id" => 7, '
                    . '"testuser_creation_time" => "creation", "testuser_status" => 4, "user_id" => 11, '
                    . '"user_lastname" => "Last", "user_firstname" => "First", "user_name" => "login", '
                    . '"user_email" => "mail@example.test", "total_score" => "6.50", '
                    . '"testuser_end_time" => "end"]; $GLOBALS["rows"] = [$row, false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["statistics"] = []; $GLOBALS["test_stats"] = []; '
                    . '$GLOBALS["user_test_starts"] = ["start", "start", "invalid-start-time"]; '
                    . 'function f_get_safe_users_test_stat_order_by($value) { return "user_name DESC"; } '
                    . 'function strtotime($value) { return ["start-filter" => 10, "end-filter" => 20, '
                    . '"creation" => 100, "end" => 200, "start" => 4000][$value] ?? false; } '
                    . 'function date($format, $timestamp) { return "DATE:" . (int) $timestamp; } '
                    . 'function time() { return 10000; } function gmdate($format, $seconds) { return "TIME:" . $seconds; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return true; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function f_get_user_test_stat($testId, $userId, $testUserId) { return ['
                    . '"test_max_score" => 10, "test_duration_time" => 30, "test_score_threshold" => 5, '
                    . '"user_score" => 6, "user_test_start_time" => array_shift($GLOBALS["user_test_starts"]), '
                    . '"user_comment" => "comment"]; } '
                    . 'function f_get_test_stat(...$arguments) { $GLOBALS["test_stats"][] = $arguments; return ['
                    . '"qstats" => ["recurrence" => 3, "right" => 2, "right_perc" => 50, '
                    . '"wrong" => 1, "wrong_perc" => 25, "unanswered" => 1, "unanswered_perc" => 25, '
                    . '"undisplayed" => 0, "undisplayed_perc" => 0, "unrated" => 0, "unrated_perc" => 0]]; } '
                    . 'function f_format_float($value) { return "FMT:" . $value; } '
                    . 'function f_get_array_statistics($data) { $GLOBALS["statistics"] = $data; return ["done" => true]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_all_users_test_stat)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$data = $qualified("07", "03", "011", "start-filter", "end-filter", '
                    . '"unsafe", false, 0); '
                    . '$GLOBALS["rows"] = [$row, false]; '
                    . '$enabled = $qualified("07", "03", "011", "start-filter", "end-filter", '
                    . '"unsafe", false, 1); '
                    . '$invalidRow = $row; $invalidRow["testuser_creation_time"] = "invalid-creation"; '
                    . '$invalidRow["testuser_end_time"] = "invalid-end-time"; '
                    . '$GLOBALS["rows"] = [$invalidRow, false]; '
                    . '$invalid = $qualified("07", "03", "011", "invalid-start", "invalid-end", '
                    . '"unsafe", false, 0); '
                    . 'echo json_encode([$data, $GLOBALS["queries"], $GLOBALS["statistics"], '
                    . '$enabled, $GLOBALS["test_stats"], $invalid]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *   0: array{
         *     svgpoints: string,
         *     passed: int,
         *     passed_perc: int,
         *     num_records: int,
         *     statistics: array{done: bool},
         *     testuser: array{"'99'": array{
         *       total_score: string,
         *       total_score_perc: int,
         *       time_diff: string,
         *       remaining_time: int,
         *       passmsg: bool,
         *       locked: bool,
         *       right: string
         *     }}
         *   },
         *   1: array{0:string,1:string,2:string},
         *   2: array{score: array{0: string}, score_perc: array{0: int}},
         *   3: array{testuser: array{"'99'": array{right: int, recurrence: int}}},
         *   4: array{0: array{0: int, 1: int, 2: int, 3: string, 4: string, 5: int, 6: bool}},
         *   5: array{testuser:array{"'99'":array{time_diff:string,remaining_time:int}}}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$data, $queries, $statistics, $enabled, $testStats, $invalid] = $decoded;
        self::assertStringContainsString('testuser_test_id=7', $queries[0]);
        self::assertStringContainsString('usrgrp_group_id=3', $queries[0]);
        self::assertStringContainsString('user_id=11', $queries[0]);
        self::assertStringContainsString("testuser_creation_time>='DATE:10'", $queries[0]);
        self::assertStringContainsString("testuser_creation_time<='DATE:20'", $queries[0]);
        self::assertStringContainsString('ORDER BY user_name DESC', $queries[0]);
        self::assertStringContainsString("testuser_creation_time>='DATE:0'", $queries[2]);
        self::assertStringContainsString("testuser_creation_time<='DATE:0'", $queries[2]);
        self::assertSame('x65v', $data['svgpoints']);
        self::assertSame(1, $data['passed']);
        self::assertSame(100, $data['passed_perc']);
        self::assertSame(1, $data['num_records']);
        self::assertSame(['done' => true], $data['statistics']);
        $user = $data['testuser']["'99'"];
        self::assertSame('FMT:6.50', $user['total_score']);
        self::assertSame(65, $user['total_score_perc']);
        self::assertSame('TIME:100', $user['time_diff']);
        self::assertSame(70, $user['remaining_time']);
        self::assertTrue($user['passmsg']);
        self::assertTrue($user['locked']);
        self::assertSame('', $user['right']);
        self::assertSame(['6.50'], $statistics['score']);
        self::assertSame([65], $statistics['score_perc']);
        self::assertSame(2, $enabled['testuser']["'99'"]['right']);
        self::assertSame(3, $enabled['testuser']["'99'"]['recurrence']);
        self::assertSame([[7, 3, 11, 'DATE:10', 'DATE:20', 99, false]], $testStats);
        self::assertSame('TIME:0', $invalid['testuser']["'99'"]['time_diff']);
        self::assertSame(137, $invalid['testuser']["'99'"]['remaining_time']);
    }

    public function testRawStatisticsPreserveEmptyShapeAndIndividualPublicFilters(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_users"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); define("K_TABLE_ANSWERS", "answers"); '
                    . 'define("K_TABLE_LOG_ANSWER", "log_answers"); define("K_TABLE_QUESTIONS", "questions"); '
                    . 'define("K_TABLE_SUBJECTS", "subjects"); define("K_TABLE_MODULES", "modules"); '
                    . 'define("K_TABLE_USERS", "users"); define("K_TABLE_USERGROUP", "user_groups"); '
                    . 'define("K_TABLE_TESTS", "tests"); define("K_TIMESTAMP_FORMAT", "format"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["queries"] = []; '
                    . '$GLOBALS["query_results"] = [true, true, true, false]; '
                    . '$GLOBALS["test_id_rows"] = [["testuser_test_id" => "9"], false]; '
                    . '$GLOBALS["authorizations"] = []; $GLOBALS["errors"] = 0; '
                    . 'function f_get_test_id_results($testId, $userId) { return "7,8"; } '
                    . 'function strtotime($value) { return ["start" => 10, "end" => 20][$value] ?? false; } '
                    . 'function date($format, $timestamp) { return "DATE:" . (int) $timestamp; } '
                    . 'function f_get_test_data($testId) { return ["test_score_right" => 2]; } '
                    . 'function F_db_datetime_diff_seconds($start, $end) { return "DIFF_SECONDS"; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function f_legacy_db_query_result($result) { return $result; } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . 'function F_db_fetch_assoc($result) { return array_shift($GLOBALS["test_id_rows"]); } '
                    . 'function f_is_authorized_user(...$arguments) { '
                    . '$GLOBALS["authorizations"][] = $arguments; return false; } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_raw_test_stat)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$data = $qualified("07", "03", "011", "start", "end", "099", '
                    . '["seed" => "keep"], true); '
                    . '$qualified("07", "03", "011", "invalid-start", "invalid-end", "099", [], true); '
                    . '$passthrough = $qualified("0", 0, 0, 0, 0, 0, 17, false); '
                    . '$failed = $qualified("0", 0, 0, 0, 0, 0, ["failed" => "keep"], false); '
                    . 'echo json_encode([$data, $GLOBALS["queries"], $passthrough, $failed, '
                    . '$GLOBALS["authorizations"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *   0: array{seed: string, qstats: array<string, mixed>},
         *   1: array{0:string,1:string,2:string,3:string},
         *   2: int,3:array{failed:string},4:array{0:array{0:string,1:string,2:string,3:string}},5:int
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$data, $queries, $passthrough, $failed, $authorizations, $errors] = $decoded;
        self::assertSame('keep', $data['seed']);
        self::assertSame(
            [
                'recurrence' => 0,
                'recurrence_perc' => 0,
                'average_score' => 0,
                'average_score_perc' => 0,
                'average_time' => 0,
                'right' => 0,
                'right_perc' => 0,
                'wrong' => 0,
                'wrong_perc' => 0,
                'unanswered' => 0,
                'unanswered_perc' => 0,
                'undisplayed' => 0,
                'undisplayed_perc' => 0,
                'unrated' => 0,
                'unrated_perc' => 0,
                'qnum' => 0,
                'module' => [],
            ],
            $data['qstats'],
        );
        self::assertStringContainsString('testlog_score, testlog_user_ip, testlog_display_time', $queries[0]);
        self::assertStringContainsString('AVG(DIFF_SECONDS) AS average_time', $queries[0]);
        self::assertStringContainsString('testuser_test_id=7', $queries[0]);
        self::assertStringContainsString('testuser_id=99', $queries[0]);
        self::assertStringContainsString('testuser_user_id=user_id AND user_id=11', $queries[0]);
        self::assertStringContainsString("testuser_creation_time>='DATE:10'", $queries[0]);
        self::assertStringContainsString("testuser_creation_time<='DATE:20'", $queries[0]);
        self::assertStringContainsString("testuser_creation_time>='DATE:0'", $queries[1]);
        self::assertStringContainsString("testuser_creation_time<='DATE:0'", $queries[1]);
        self::assertStringContainsString('GROUP BY testuser_test_id', $queries[2]);
        self::assertStringContainsString('GROUP BY testuser_test_id', $queries[3]);
        self::assertSame(17, $passthrough);
        self::assertSame(['failed' => 'keep'], $failed);
        self::assertSame([['tests', 'test_id', '9', 'test_user_id']], $authorizations);
        self::assertSame(1, $errors);
    }

    public function testStatisticsPrintersPreserveDisabledAndEmptyResults(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_NEWLINE", "\\n"); '
                    . 'function f_format_percentage($value, $decimals) { return "P:" . $value; } '
                    . 'function F_select_table_header_element(...$arguments) { return "<HEADER>\\n"; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_print_test_stat)\\(/", '
                    . '$source, $statMatch, PREG_OFFSET_CAPTURE); '
                    . '$statStart = $statMatch[0][1]; $statEnd = strpos($source, "\\n/**", $statStart); '
                    . '$statFunction = substr($source, $statStart, $statEnd - $statStart); '
                    . '$statFunction = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $statFunction); '
                    . 'eval("namespace Harness; " . $statFunction); '
                    . 'preg_match("/function (f_print_test_result_stat)\\(/", '
                    . '$source, $resultMatch, PREG_OFFSET_CAPTURE); '
                    . '$resultStart = $resultMatch[0][1]; $resultEnd = strpos($source, "\\n/**", $resultStart); '
                    . '$resultFunction = substr($source, $resultStart, $resultEnd - $resultStart); '
                    . '$resultFunction = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $resultFunction); '
                    . 'preg_match_all("/\\[\'([a-z][a-z0-9_]*)\'\\]/", '
                    . '$statFunction . $resultFunction, $labels); '
                    . '$l = array_fill_keys(array_unique($labels[1]), "label"); $l["a_meta_dir"] = "ltr"; '
                    . 'eval("namespace Harness; " . $resultFunction); '
                    . '$statName = __NAMESPACE__ . "\\\\" . $statMatch[1][0]; '
                    . '$resultName = __NAMESPACE__ . "\\\\" . $resultMatch[1][0]; '
                    . '$qstats = array_fill_keys(["recurrence", "recurrence_perc", "average_score_perc", '
                    . '"average_time", "right", "right_perc", "wrong", "wrong_perc", "unanswered", '
                    . '"unanswered_perc", "undisplayed", "undisplayed_perc", "unrated", "unrated_perc"], 0); '
                    . '$qstats["recurrence"] = 1; $qstats["module"] = []; '
                    . '$resultData = ["num_records" => 1, "testuser" => [], "passed_perc" => 0, '
                    . '"passed" => 0, "statistics" => []]; '
                    . '$returns = [$statName(7, 0, 0, 0, 0, 0, ["qstats" => ["recurrence" => 1]], 1), '
                    . '$statName(7, 0, 0, 0, 0, 0, ["qstats" => ["recurrence" => 0]], 2), '
                    . '$resultName(["num_records" => 0], 1, "score", ""), '
                    . '$statName("7", 0, 0, 0, 0, 0, ["qstats" => $qstats], "2"), '
                    . '$resultName($resultData, "1", "score", "filter", false, "0")]; '
                    . 'echo json_encode($returns);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0:null,1:null,2:null,3:string,4:string} $returns */
        $returns = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([null, null, null], array_slice($returns, 0, 3));
        self::assertStringStartsWith('<table class="userselect">', $returns[3]);
        self::assertStringContainsString('<td class="numeric">1 P:0</td>', $returns[3]);
        self::assertStringEndsWith('</table>' . "\n", $returns[3]);
        self::assertStringStartsWith('<table class="userselect">', $returns[4]);
        self::assertStringContainsString('label: 0 P:0', $returns[4]);
        self::assertStringEndsWith('</table>' . "\n", $returns[4]);
    }




    public function testUserTestStatisticsOrderByFiltersAndFormatsInput(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_safe_users_test_stat_order_by)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode(['
                    . '$qualified(" user_lastname desc , TOTAL_SCORE, malicious; DROP, user_name ASC, '
                    . 'testuser_end_time DeSc "), '
                    . '$qualified("unknown ASC, total_score DESC"), '
                    . '$qualified(""), $qualified(null)]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'user_lastname DESC, total_score, testuser_end_time DESC',
                'total_score DESC',
                'total_score, user_lastname, user_firstname',
                'total_score, user_lastname, user_firstname',
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testLockUserTestPreservesUpdateAndErrorHandling(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . 'define("K_TIMESTAMP_FORMAT", "\\\\F\\\\I\\\\X\\\\E\\\\D"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["results"] = [true, false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["results"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_lock_user_test)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("007", "11"), $qualified("8", "12")], '
                    . '$GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [null, null],
                1,
                [
                    "UPDATE test_user\n\t\t\tSET testuser_status=4,\n"
                        . "\t\t\t\ttestuser_close_reason='completed',\n"
                        . "\t\t\t\ttestuser_last_activity='FIXED'\n"
                        . "\t\t\tWHERE testuser_test_id=7\n"
                        . "\t\t\t\tAND testuser_user_id=11\n\t\t\t\tAND testuser_status<4",
                    "UPDATE test_user\n\t\t\tSET testuser_status=4,\n"
                        . "\t\t\t\ttestuser_close_reason='completed',\n"
                        . "\t\t\t\ttestuser_last_activity='FIXED'\n"
                        . "\t\t\tWHERE testuser_test_id=8\n"
                        . "\t\t\t\tAND testuser_user_id=12\n\t\t\t\tAND testuser_status<4",
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testPublishedTestIdWrappersPreserveFiltersAndArguments(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["calls"] = []; '
                    . 'function f_get_test_ids($testId, $userId, $filter) { '
                    . '$GLOBALS["calls"][] = [$testId, $userId, $filter]; return "ids:" . $filter; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_id_results)\\(/", '
                    . '$source, $resultsMatch, PREG_OFFSET_CAPTURE); '
                    . 'preg_match("/function (f_get_test_id_reports)\\(/", '
                    . '$source, $reportsMatch, PREG_OFFSET_CAPTURE); '
                    . '$start = $resultsMatch[0][1]; '
                    . '$end = strpos($source, "\\n/**", $reportsMatch[0][1]); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$results = __NAMESPACE__ . "\\\\" . $resultsMatch[1][0]; '
                    . '$reports = __NAMESPACE__ . "\\\\" . $reportsMatch[1][0]; '
                    . 'echo json_encode([[$results("007", "11"), $reports("008", "12")], $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                ['ids:test_results_to_users', 'ids:test_report_to_users'],
                [
                    ['007', '11', 'test_results_to_users'],
                    ['008', '12', 'test_report_to_users'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testPublishedTestIdsPreserveQueryResultsAndErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS", "tests"); '
                    . 'define("K_TABLE_TEST_USER", "test_user"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["query_results"] = [true, false]; '
                    . '$GLOBALS["rows"] = [["test_id" => 3], ["test_id" => "9"], false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_assoc($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_ids)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); if ($end === false) { $end = strlen($source); } '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7x", "011", "custom_flag"), $qualified("8", "12")], '
                    . '$GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                ['0,3,9', '0'],
                1,
                [
                    'SELECT test_id FROM tests WHERE test_id IN (SELECT DISTINCT testuser_test_id FROM test_user '
                        . 'WHERE testuser_user_id=11 AND testuser_status>3) AND custom_flag=1',
                    'SELECT test_id FROM tests WHERE test_id IN (SELECT DISTINCT testuser_test_id FROM test_user '
                        . 'WHERE testuser_user_id=12 AND testuser_status>3) AND test_results_to_users=1',
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestStatisticsWrapperNormalizesOnlyQuestionStatistics(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["raw"] = [['
                    . '"test" => "plain"], ["qstats" => ["recurrence" => 0], "test" => "questions"]]; '
                    . '$GLOBALS["raw_calls"] = []; $GLOBALS["normalized"] = []; '
                    . 'function f_get_raw_test_stat(...$args) { $GLOBALS["raw_calls"][] = $args; '
                    . 'return array_shift($GLOBALS["raw"]); } '
                    . 'function f_normalize_test_stat_averages($data) { $GLOBALS["normalized"][] = $data; '
                    . '$data["normalized"] = true; return $data; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_stat)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([['
                    . '$qualified("1", "2", "3", "start-a", "end-a", "6", "public-a"), '
                    . '$qualified("7", "8", "9", "start-b", "end-b", "12", "public-b")], '
                    . '$GLOBALS["raw_calls"], $GLOBALS["normalized"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [
                    ['test' => 'plain'],
                    ['qstats' => ['recurrence' => 0], 'test' => 'questions', 'normalized' => true],
                ],
                [
                    ['1', '2', '3', 'start-a', 'end-a', '6', [], 'public-a'],
                    ['7', '8', '9', 'start-b', 'end-b', '12', [], 'public-b'],
                ],
                [['qstats' => ['recurrence' => 0], 'test' => 'questions']],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserTestStatisticsWrapperCastsIdsAndPreservesLeftValues(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["data_calls"] = []; $GLOBALS["totals_calls"] = []; '
                    . 'function f_get_test_data($testId) { $GLOBALS["data_calls"][] = $testId; '
                    . 'return ["shared" => "left", "test" => $testId]; } '
                    . 'function f_get_user_test_totals(...$args) { $GLOBALS["totals_calls"][] = $args; '
                    . 'return ["shared" => "right", "score" => 9]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_user_test_stat)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified("007", "008", "009", "public"), '
                    . '$GLOBALS["data_calls"], $GLOBALS["totals_calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                ['shared' => 'left', 'test' => 7, 'score' => 9],
                [7],
                [[7, 8, 9, 'public']],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserTestTotalsPreserveFiltersRowsAndErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["query_results"] = [true, true, false]; '
                    . '$GLOBALS["rows"] = [["testuser_id" => 9, "total_score" => "17.5", '
                    . '"testuser_creation_time" => "start", "test_end_time" => "end", '
                    . '"testuser_status" => 4, "testuser_comment" => "note"], false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function f_legacy_db_query_result($result) { return $result; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_user_test_totals)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([['
                    . '$qualified("7", "0", "9"), $qualified("7", "8", "9"), '
                    . '$qualified("10", "11", "12", true), $qualified("13", "14", "15", true)], '
                    . '$GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [
                    [],
                    [
                        'testuser_id' => 9,
                        'user_score' => '17.5',
                        'user_test_start_time' => 'start',
                        'user_test_end_time' => 'end',
                        'testuser_status' => 4,
                        'user_comment' => 'note',
                    ],
                    [],
                    [],
                ],
                1,
                [
                    $this->userTestTotalsQuery(7, 8, 9, 0),
                    $this->userTestTotalsQuery(10, 11, 12, 3),
                    $this->userTestTotalsQuery(13, 14, 15, 3),
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testStatisticsAverageNormalizationPreservesEveryLevel(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_normalize_test_stat_averages)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$answer = ["recurrence" => 2, "right" => 1, "wrong" => 1, "unanswered" => 0]; '
                    . '$base = ["recurrence" => 4, "qnum" => 2, "average_score" => 10, '
                    . '"average_score_perc" => 2, "average_time" => 8, "right" => 2, "wrong" => 1, '
                    . '"unanswered" => 1, "undisplayed" => 0, "unrated" => 0]; '
                    . '$question = $base + ["anum" => 4, "answer" => ["a" => $answer]]; '
                    . '$subject = $base + ["question" => ["q" => $question]]; '
                    . '$module = $base + ["subject" => ["s" => $subject]]; '
                    . '$data = ["qstats" => ["recurrence" => 8, "qnum" => 4, "average_score" => 20, '
                    . '"average_score_perc" => 4, "average_time" => 16, "right" => 4, "wrong" => 2, '
                    . '"unanswered" => 1, "undisplayed" => 1, "unrated" => 0, "module" => ["m" => $module]]]; '
                    . '$normalized = $qualified($data); $q = $normalized["qstats"]; $m = $q["module"]["m"]; '
                    . '$s = $m["subject"]["s"]; $question = $s["question"]["q"]; '
                    . '$a = $question["answer"]["a"]; '
                    . 'echo json_encode([$qualified(17), $qualified(["unchanged" => 1]), '
                    . '$qualified(["qstats" => ["recurrence" => 0, "marker" => 2]]), '
                    . '[$q["recurrence_perc"], $q["average_score"], $q["average_score_perc"], '
                    . '$q["average_time"], $q["right_perc"], $q["wrong_perc"], '
                    . '$q["unanswered_perc"], $q["undisplayed_perc"], $q["unrated_perc"]], '
                    . '[$m["recurrence_perc"], $m["average_score"], $m["average_score_perc"], '
                    . '$m["average_time"], $m["right_perc"], $m["wrong_perc"], $m["unanswered_perc"]], '
                    . '[$s["recurrence_perc"], $question["recurrence_perc"]], '
                    . '[$a["recurrence_perc"], $a["right_perc"], $a["wrong_perc"], $a["unanswered_perc"]]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                17,
                ['unchanged' => 1],
                ['qstats' => ['recurrence' => 0, 'marker' => 2]],
                [100, 5, 50, 4, 50, 25, 13, 13, 0],
                [50, 5, 50, 4, 50, 25, 25],
                [50, 50],
                [50, 50, 50, 0],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testEvenMedianAndStandardDeviationBranches(): void
    {
        /** @var array<string, array<string, int|float>> $statistics */
        $statistics = \f_get_array_statistics([
            'spread' => [1, 3],
            'constant' => [2, 2],
        ]);

        self::assertSame([
            'number' => ['spread' => 2, 'constant' => 2],
            'sum' => ['spread' => 4.0, 'constant' => 4.0],
            'mean' => ['spread' => 2.0, 'constant' => 2.0],
            'median' => ['spread' => 2.0, 'constant' => 2.0],
            'mode' => ['spread' => 1.0, 'constant' => 2.0],
            'minimum' => ['spread' => 1, 'constant' => 2],
            'maximum' => ['spread' => 3, 'constant' => 2],
            'range' => ['spread' => 2.0, 'constant' => 0.0],
            'variance' => ['spread' => 1.0, 'constant' => 0.0],
            'standard_deviation' => ['spread' => 1.0, 'constant' => 0.0],
            'skewness' => ['spread' => 0.0, 'constant' => 0],
            'kurtosi' => ['spread' => 1.0, 'constant' => 0],
        ], $statistics);
    }

    private function userTestTotalsQuery(int $testId, int $userId, int $testuserId, int $status): string
    {
        return "SELECT SUM(testlog_score) AS total_score, MAX(testlog_change_time) AS test_end_time, "
            . "testuser_id, testuser_creation_time, testuser_status, testuser_comment\n"
            . "\t\tFROM test_user, test_logs\n\t\tWHERE testlog_testuser_id=testuser_id\n"
            . "\t\t\tAND testuser_id=" . $testuserId . "\n"
            . "\t\t\tAND testuser_test_id=" . $testId . "\n"
            . "\t\t\tAND testuser_user_id=" . $userId . "\n"
            . "\t\t\tAND testuser_status>" . $status . "\n"
            . "\t\tGROUP BY testuser_id, testuser_creation_time, testuser_status, testuser_comment";
    }
}
