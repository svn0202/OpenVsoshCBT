<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_test_access.php';

final class TestAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['session_unlocked_tests'], $_SESSION['session_user_id']);
    }

    public function testDatabaseBackedAccessAndCompletionDecisionsRemainUnchanged(): void
    {
        $script = <<<'PHP'
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_TEST_USER', 'test_users');
define('K_TABLE_TESTS_LOGS', 'test_logs');
$GLOBALS['db'] = 'db';
$GLOBALS['results'] = [
    'access', 'passing-test', 'attempts', 'score-1', 'score-2',
    'cycle-node', 'completion-test', 'current-attempt', 'completion-score', 'logs',
];
$GLOBALS['rows'] = [
    'access' => [['test_required_finished_id' => 9, 'test_required_passed_id' => 10]],
    'passing-test' => [['test_score_threshold' => 50, 'test_max_score' => 100]],
    'attempts' => [['testuser_id' => 101], ['testuser_id' => 102], false],
    'score-1' => [['total_score' => 40]],
    'score-2' => [['total_score' => 60]],
    'cycle-node' => [['test_required_finished_id' => 0, 'test_required_passed_id' => 1]],
    'completion-test' => [[
        'test_minimum_duration_time' => 5,
        'test_require_all_answers' => true,
        'test_block_finish_below_threshold' => true,
        'test_score_threshold' => 50,
    ]],
    'current-attempt' => [['testuser_id' => 201, 'testuser_creation_time' => '2026-08-10 12:00:00']],
    'completion-score' => [['total_score' => 60]],
    'logs' => [
        ['testlog_change_time' => null],
        ['testlog_change_time' => '2026-08-10 12:01:00'],
        ['testlog_change_time' => ''],
        false,
    ],
];
$GLOBALS['counts'] = [0, 1, 0];
$GLOBALS['queries'] = [];
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql));
    return array_shift($GLOBALS['results']);
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][$result]); }
function F_count_rows($table, $where) { return array_shift($GLOBALS['counts']); }
function f_get_boolean($value) { return (bool) $value; }
require 'tce_functions_test_access.php';
$status = F_tmf_test_access_status(20, 7);
$cycle = F_tmf_test_prerequisite_would_cycle(1, [2]);
$completion = F_tmf_test_completion_status(20, 7, strtotime('2026-08-10 12:06:00'));
$unanswered = F_tmf_unanswered_question_numbers(20, 7);
echo json_encode([$status, $cycle, $completion, $unanswered, count($GLOBALS['queries'])], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertJson($output);
        self::assertSame(
            [
                ['allowed' => true, 'reason' => 'allowed'],
                true,
                ['allowed' => true, 'reason' => 'allowed', 'details' => null],
                [1, 3],
                10,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testPasswordUnlockIsScopedToTestAndCurrentUser(): void
    {
        $_SESSION['session_user_id'] = 42;
        \F_tmf_test_session_unlock(7);

        self::assertTrue(\F_tmf_test_session_is_unlocked(7));
        self::assertFalse(\F_tmf_test_session_is_unlocked(8));

        $_SESSION['session_user_id'] = 43;
        self::assertFalse(\F_tmf_test_session_is_unlocked(7));
    }

    public function testExecuteTestPreservesAuthorizationCreationAndStatusDecisions(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS", "tests"); '
                    . 'define("K_TIMESTAMP_FORMAT", "format"); $GLOBALS["db"] = "db"; '
                    . '$_SESSION = ["session_user_id" => "11", "session_user_ip" => "127.0.0.1"]; '
                    . '$row = ["test_id" => 22, "test_ip_range" => "127.0.0.1", '
                    . '"test_duration_time" => 30, "test_repeatable" => 2]; '
                    . '$nonrepeat = $row; $nonrepeat["test_repeatable"] = 0; '
                    . '$GLOBALS["rows"] = [false, $row, $row, $row, $row, $row, $row, $row, $nonrepeat]; '
                    . '$GLOBALS["ip"] = [false, true, true, true, true, true, true, true]; '
                    . '$GLOBALS["access"] = [false, true, true, true, true, true, true]; '
                    . '$GLOBALS["pregeneration"] = ["invalidated", null, null, null, null, null]; '
                    . '$GLOBALS["statuses"] = [[0, 0], [1, 101], [4, 102], [5, 103], [5, 104]]; '
                    . '$GLOBALS["counts"] = [1, 1]; $GLOBALS["queries"] = []; '
                    . '$GLOBALS["create_calls"] = []; $GLOBALS["status_calls"] = []; $GLOBALS["errors"] = 0; '
                    . 'function date($format) { return "2026-08-10 12:00:00"; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return true; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function f_is_valid_test_user(...$arguments) { return array_shift($GLOBALS["ip"]); } '
                    . 'function F_tmf_test_access_status($testId, $userId) { '
                    . 'return ["allowed" => array_shift($GLOBALS["access"])]; } '
                    . 'function F_tmf_pregeneration_activate($testId, $userId) { '
                    . 'return array_shift($GLOBALS["pregeneration"]); } '
                    . 'function f_create_test(...$arguments) { $GLOBALS["create_calls"][] = $arguments; return true; } '
                    . 'function f_check_test_status(...$arguments) { $GLOBALS["status_calls"][] = $arguments; '
                    . 'return array_shift($GLOBALS["statuses"]); } '
                    . 'function f_count_user_test($userId, $testId) { return array_shift($GLOBALS["counts"]); } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_execute_test)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $results = []; '
                    . 'for ($i = 0; $i < 9; ++$i) { $results[] = $qualified("22"); } '
                    . 'echo json_encode([$results, $GLOBALS["create_calls"], '
                    . '$GLOBALS["status_calls"], count($GLOBALS["queries"]), $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [false, false, false, true, true, true, false, true, false],
                [[22, 11], [22, '11'], [22, '11']],
                [
                    ['11', 22, 30],
                    ['11', 22, 30],
                    ['11', 22, 30],
                    ['11', 22, 30],
                    ['11', 22, 30],
                ],
                9,
                0,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserTestCataloguePreservesEmptyAndBlockedResults(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS", "tests"); '
                    . 'define("K_TABLE_TEST_SUBJSET", "subject_sets"); define("K_TIMESTAMP_FORMAT", "format"); '
                    . 'define("K_NEWLINE", "\\n"); define("K_DISPLAY_TEST_DESCRIPTION", false); '
                    . '$GLOBALS["db"] = "db"; $_SESSION = ["session_user_id" => "11", '
                    . '"session_user_ip" => "127.0.0.1"]; '
                    . '$GLOBALS["l"] = ["m_no_test_available" => "NONE", "t_test_list" => "Tests", '
                    . '"w_test" => "Test", "w_from" => "From", "w_to" => "To", '
                    . '"w_status" => "Status", "w_action" => "Action", "a_meta_charset" => "UTF-8"]; '
                    . '$GLOBALS["l"] += ["w_passed" => "Passed", "w_not_passed" => "Failed", '
                    . '"h_result" => "Result"]; '
                    . '$row = ["test_id" => "22", "test_ip_range" => "*", "test_duration_time" => "30", '
                    . '"test_begin_time" => "2026-08-09 00:00:00", "test_end_time" => "2026-08-11 00:00:00", '
                    . '"test_password" => null, "test_name" => "Exam", "test_repeatable" => "0", '
                    . '"test_results_to_users" => "1"]; '
                    . '$protectedRow = $row; $protectedRow["test_password"] = "secret"; '
                    . '$GLOBALS["results"] = [false, "empty", "unauthorized", "blocked", "published"]; '
                    . '$GLOBALS["rows"] = ["empty" => [false], "unauthorized" => [$row, false], '
                    . '"blocked" => [$protectedRow, false], "published" => [$row, false]]; '
                    . '$GLOBALS["ip"] = [false, true, true]; $GLOBALS["test_statuses"] = [0, 4]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; $GLOBALS["published"] = []; '
                    . 'function date($format) { return "2026-08-10 12:00:00"; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function f_is_valid_test_user(...$arguments) { return array_shift($GLOBALS["ip"]); } '
                    . 'function F_tmf_test_access_status($testId, $userId) { '
                    . 'return ["allowed" => false, "reason" => "required_test_not_passed"]; } '
                    . 'function f_check_test_status(...$arguments) { '
                    . 'return [array_shift($GLOBALS["test_statuses"]), 99, false]; } '
                    . 'function F_tmf_catalog_test_status($status, $pregenerated) { return $status; } '
                    . 'function F_tmf_results_are_published($test) { '
                    . '$GLOBALS["published"][] = $test; return true; } '
                    . 'function f_get_user_test_stat(...$arguments) { return ["user_score" => "8", '
                    . '"test_score_threshold" => "5", "test_max_score" => "10"]; } '
                    . 'function f_test_info_link($testId, $name) { return "INFO:" . $name; } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_user_tests)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $catalogues = []; '
                    . 'for ($i = 0; $i < 5; ++$i) { $catalogues[] = $qualified(); } '
                    . 'echo json_encode([$catalogues, count($GLOBALS["queries"]), '
                    . '$GLOBALS["errors"], $GLOBALS["published"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *   0: array{0:string,1:string,2:string,3:string,4:string},
         *   1: int,
         *   2: int,
         *   3: array{0:array<string,mixed>}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$results, $queryCount, $errors, $published] = $decoded;
        self::assertSame(['NONE', 'NONE', 'NONE'], array_slice($results, 0, 3));
        self::assertStringContainsString('<table class="testlist">', $results[3]);
        self::assertStringContainsString('data-test-id="22"', $results[3]);
        self::assertStringContainsString('<td>2026-08-09 00:00:00</td>', $results[3]);
        self::assertStringContainsString('<td>2026-08-11 00:00:00</td>', $results[3]);
        self::assertStringContainsString('<td style="background-color:#ffffcc;"><strong>INFO:Exam</strong>', $results[3]);
        self::assertStringContainsString('Сначала пройдите обязательный тест', $results[3]);
        self::assertStringNotContainsString('tce_test_execute.php', $results[3]);
        self::assertStringContainsString('<td><strong>INFO:Exam</strong>', $results[4]);
        self::assertStringContainsString('testuser_id=99&amp;test_id=22', $results[4]);
        self::assertStringContainsString('8 / 10 (80%) - Passed', $results[4]);
        self::assertSame('1', $published[0]['test_results_to_users'] ?? null);
        self::assertSame(5, $queryCount);
        self::assertSame(1, $errors);
    }

    public function testTestLoginFormPreservesMarkupFieldsAndPasswordInputArguments(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_NEWLINE", "\\n"); '
                    . '$GLOBALS["l"] = ["w_test_password" => "Test password", '
                    . '"h_test_password" => "Enter password", "w_login" => "Open test", '
                    . '"h_login_button" => "Submit password", "hp_test_password" => "Password help"]; '
                    . '$GLOBALS["field_calls"] = []; '
                    . 'function get_form_row_text_input(...$arguments) { '
                    . '$GLOBALS["field_calls"][] = $arguments; return "<PASSWORD-FIELD>"; } '
                    . 'function f_get_csrf_token_field() { return "<CSRF-FIELD>"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_test_login_form)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$markup = $qualified("/start?x=1&y=2", "password-form", "post", '
                    . '"multipart/form-data", "007"); '
                    . 'echo json_encode([$markup, $GLOBALS["field_calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: string, 1: list<list<mixed>>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$markup, $fieldCalls] = $decoded;
        self::assertStringContainsString(
            '<form action="/start?x=1&y=2" method="post" id="password-form" enctype="multipart/form-data">',
            $markup,
        );
        self::assertStringContainsString('<PASSWORD-FIELD>', $markup);
        self::assertStringContainsString('value="Open test" title="Submit password"', $markup);
        self::assertStringContainsString('name="testpswaction" id="testpswaction" value="login"', $markup);
        self::assertStringContainsString('name="testid" id="testid" value="7"', $markup);
        self::assertStringContainsString("<CSRF-FIELD>\n</form>", $markup);
        self::assertStringContainsString('<div class="pagehelp">Password help</div>', $markup);
        self::assertSame(
            [['xtest_password', 'Test password', 'Enter password', '', '', '', 255, false, false, true, '']],
            $fieldCalls,
        );
    }

    public function testTestInfoPreservesEmptyUnauthorizedAndDetailedMarkup(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS", "tests"); define("K_NEWLINE", "\\n"); '
                    . '$GLOBALS["db"] = "db"; $_SESSION["session_user_ip"] = "127.0.0.1"; '
                    . '$GLOBALS["l"] = ["w_no" => "No", "w_yes" => "Yes", "a_meta_charset" => "UTF-8", '
                    . '"w_time_begin" => "Begin", "h_time_begin" => "Begin help", '
                    . '"w_time_end" => "End", "h_time_end" => "End help", '
                    . '"w_test_time" => "Duration", "h_test_time" => "Duration help", "w_minutes" => "minutes", '
                    . '"w_score_right" => "Right", "h_score_right" => "Right help", '
                    . '"w_score_wrong" => "Wrong", "h_score_wrong" => "Wrong help", '
                    . '"w_score_unanswered" => "Unanswered", "h_score_unanswered" => "Unanswered help", '
                    . '"w_max_score" => "Maximum", "w_test_score_threshold" => "Threshold", '
                    . '"h_test_score_threshold" => "Threshold help", "w_results_to_users" => "Results", '
                    . '"h_results_to_users" => "Results help", "w_report_to_users" => "Report", '
                    . '"h_report_to_users" => "Report help", "w_repeatable" => "Repeatable", '
                    . '"h_repeatable_test" => "Repeat help", "w_ip_range" => "IP range", '
                    . '"h_ip_range" => "IP help"]; '
                    . '$row = ["test_ip_range" => "127.0.0.1", "test_name" => "<Exam & \\"Q\\">", '
                    . '"test_description" => "Description", "test_begin_time" => "begin", '
                    . '"test_end_time" => "end", "test_duration_time" => 30, "test_score_right" => 2, '
                    . '"test_score_wrong" => -1, "test_score_unanswered" => 0, "test_max_score" => 20, '
                    . '"test_score_threshold" => 10, "test_results_to_users" => true, '
                    . '"test_report_to_users" => false, "test_repeatable" => 3]; '
                    . '$GLOBALS["results"] = [false, "empty", "unauthorized", "authorized"]; '
                    . '$GLOBALS["rows"] = ["empty" => [false], "unauthorized" => [$row], "authorized" => [$row]]; '
                    . '$GLOBALS["valid"] = [false, true]; $GLOBALS["queries"] = []; '
                    . '$GLOBALS["errors"] = 0; $GLOBALS["two_col"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . 'function f_is_valid_test_user(...$arguments) { return array_shift($GLOBALS["valid"]); } '
                    . 'function F_decode_tcecode($value) { return "[" . $value . "]"; } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function f_two_col_row(...$arguments) { $GLOBALS["two_col"][] = $arguments; '
                    . 'return "<ROW:" . $arguments[0] . ":" . $arguments[2] . ">"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_print_test_info)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$outputs = [$qualified("007", true), $qualified("007", true), '
                    . '$qualified("007", true), $qualified("007", true)]; '
                    . 'echo json_encode([$outputs, $GLOBALS["queries"], '
                    . '$GLOBALS["errors"], $GLOBALS["two_col"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: array{0: string, 1: string, 2: string, 3: string}, 1: list<string>, 2: int, 3: list<list<mixed>>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$outputs, $queries, $errors, $rows] = $decoded;
        self::assertSame(['</div>', '</div>', ''], array_slice($outputs, 0, 3));
        self::assertStringContainsString('<h1>&lt;Exam &amp; "Q"&gt;</h1>', $outputs[3]);
        self::assertStringContainsString('[Description]', $outputs[3]);
        self::assertStringContainsString('<ROW:Repeatable:Yes ( 3 )>', $outputs[3]);
        self::assertStringContainsString('<ROW:IP range:127.0.0.1>', $outputs[3]);
        self::assertCount(12, $rows);
        self::assertCount(4, $queries);
        self::assertStringContainsString('test_id=007', $queries[0] ?? '');
        self::assertSame(1, $errors);
    }

    public function testCreateTestPreservesLimitInsertFailureAndSuccessfulLifecycle(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_DATABASE_TYPE", "MYSQL"); '
                    . 'define("K_TIMESTAMP_FORMAT", "format"); define("K_TABLE_TEST_USER", "test_users"); '
                    . 'define("K_TABLE_TEST_SUBJSET", "test_subject_sets"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["limits"] = [true, false, false]; '
                    . '$GLOBALS["results"] = [false, true, "sets", true]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = []; '
                    . '$GLOBALS["insert_calls"] = []; $GLOBALS["stat_dates"] = []; '
                    . '$testdata = ["test_random_questions_select" => false, '
                    . '"test_random_questions_order" => false, "test_questions_order_mode" => 0, '
                    . '"test_random_answers_select" => false, "test_random_answers_order" => false, '
                    . '"test_answers_order_mode" => 0]; '
                    . 'function f_is_test_over_limits() { return array_shift($GLOBALS["limits"]); } '
                    . 'function f_get_test_data($testId) { return $GLOBALS["testdata"]; } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . 'function date($format) { return "2026-08-10 12:34:56"; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . 'function F_display_db_error(...$arguments) { $GLOBALS["errors"][] = $arguments; } '
                    . 'function F_db_insert_id(...$arguments) { $GLOBALS["insert_calls"][] = $arguments; return 55; } '
                    . 'function f_update_testuser_stat($date) { $GLOBALS["stat_dates"][] = $date; } '
                    . 'function f_get_first_test_user($testId) { return 0; } '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function f_legacy_literal_equals($value, $expected) { return $value === $expected; } '
                    . '$GLOBALS["testdata"] = $testdata; '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_create_test)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$returns = [$qualified("007", "011"), $qualified("007", "011"), '
                    . '$qualified("007", "011")]; '
                    . 'echo json_encode([$returns, $GLOBALS["queries"], $GLOBALS["errors"], '
                    . '$GLOBALS["insert_calls"], $GLOBALS["stat_dates"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: array{false, false, true}, 1: array{0: string, 1: string, 2: string, 3: string}, 2: list<list<mixed>>, 3: array{0: list<mixed>}, 4: array{0: string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$returns, $queries, $errors, $insertCalls, $statDates] = $decoded;
        self::assertSame([false, false, true], $returns);
        self::assertStringContainsString('VALUES ( 7, 11, 0,', $queries[0]);
        self::assertStringContainsString('VALUES ( 7, 11, 0,', $queries[1]);
        self::assertStringContainsString('FROM test_subject_sets WHERE tsubset_test_id=7', $queries[2]);
        self::assertSame(
            "UPDATE test_users SET testuser_status=1, testuser_creation_time='2026-08-10 12:34:56' "
                . 'WHERE testuser_id=55',
            $queries[3],
        );
        self::assertSame([[false]], $errors);
        self::assertSame([['db', 'test_users', 'testuser_id']], $insertCalls);
        self::assertSame(['2026-08-10 12:34:56'], $statDates);
    }
}
