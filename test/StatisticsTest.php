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
                    . 'define("K_TABLE_MODULES", "modules"); define("K_NEWLINE", "\\n"); '
                    . 'define("K_ENABLE_QUESTION_EXPLANATION", false); '
                    . 'define("K_ENABLE_ANSWER_EXPLANATION", false); $GLOBALS["db"] = "db"; '
                    . '$row = ["testlog_score" => "2.5", "testlog_user_ip" => "packed", '
                    . '"testlog_reaction_time" => 0, "question_description" => "Question", '
                    . '"question_explanation" => "", "question_type" => 3, '
                    . '"testlog_answer_text" => "Answer", "testlog_id" => 77, '
                    . '"testlog_comment" => "Teacher note"]; '
                    . '$GLOBALS["results"] = [false, "questions"]; '
                    . '$GLOBALS["rows"] = ["questions" => [$row, false]]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; $GLOBALS["attachments"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . 'function get_ip_as_string($value) { return "IPVALUE"; } '
                    . 'function F_decode_tcecode($value) { return "[" . $value . "]"; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function F_tmf_attachment_html($testlogId) { '
                    . '$GLOBALS["attachments"][] = $testlogId; return "<ATTACHMENT>"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (F_printUserTestStat)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$failed = $qualified("007"); $markup = $qualified("007"); '
                    . 'echo json_encode([$failed, $markup, $GLOBALS["queries"], '
                    . '$GLOBALS["errors"], $GLOBALS["attachments"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test_stats.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: string, 1: string, 2: array{0: string, 1: string}, 3: int, 4: array{0: int}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$failed, $markup, $queries, $errors, $attachments] = $decoded;
        self::assertSame('', $failed);
        self::assertStringContainsString('<ol class="question">', $markup);
        self::assertStringContainsString('<strong>[2.5]', $markup);
        self::assertStringContainsString('(IP:IPVALUE', $markup);
        self::assertStringContainsString('| --:--:--', $markup);
        self::assertStringContainsString('| ------', $markup);
        self::assertStringContainsString('[Question]', $markup);
        self::assertStringContainsString('[Answer]<ATTACHMENT>&nbsp;', $markup);
        self::assertStringContainsString('[Teacher note]&nbsp;', $markup);
        self::assertCount(2, $queries);
        self::assertStringContainsString('testlog_testuser_id=7', $queries[0]);
        self::assertStringContainsString('testlog_testuser_id=7', $queries[1]);
        self::assertSame(1, $errors);
        self::assertSame([77], $attachments);
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
                    . 'function F_getRawTestStat(...$args) { $GLOBALS["raw_calls"][] = $args; '
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
                    . 'echo json_encode([$qualified(["unchanged" => 1]), '
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
        /**
         * @var array{
         *     median: array{spread: float, constant: float},
         *     standard_deviation: array{spread: float, constant: float},
         *     skewness: array{spread: float, constant: int},
         *     kurtosi: array{spread: float, constant: int}
         * } $statistics
         */
        $statistics = \f_get_array_statistics([
            'spread' => [1, 3],
            'constant' => [2, 2],
        ]);

        self::assertSame(2.0, $statistics['median']['spread']);
        self::assertSame(1.0, $statistics['standard_deviation']['spread']);
        self::assertSame(0.0, $statistics['standard_deviation']['constant']);
        self::assertSame(0, $statistics['skewness']['constant']);
        self::assertSame(0, $statistics['kurtosi']['constant']);
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
