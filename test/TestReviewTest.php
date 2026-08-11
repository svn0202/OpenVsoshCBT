<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_test.php';

final class TestReviewTest extends TestCase
{
    public function testTwoColumnRowPreservesExactMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        self::assertSame(
            '<div class="row"><span class="label"><span title="Description">Label: '
                . '</span></span><span class="value">Value</span></div>' . K_NEWLINE,
            \f_two_col_row('Label', 'Description', 'Value'),
        );
    }

    public function testTestInfoLinkPreservesPopupOptionsAndPlainCaption(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TEST_INFO_HEIGHT", 600); define("K_TEST_INFO_WIDTH", 800); '
                    . '$GLOBALS["l"] = ["m_new_window_link" => "New window", "w_info" => "Info"]; '
                    . 'require $argv[2]; $source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_test_info_link)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo $qualified(7, "<b>A &amp; B</b>");',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
                dirname(__DIR__) . '/shared/code/tce_functions_general.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            '<a href="tce_popup_test_info.php?testid=7" '
                . 'onclick="infoTestWindow=window.open(\'tce_popup_test_info.php?testid=7\''
                . ',\'infoTestWindow\',\'dependent,height=600,width=800,menubar=no,resizable=yes,'
                . 'scrollbars=yes,status=no,toolbar=no\');return false;" title="New window">A & B</a>',
            $output,
        );
    }

    public function testTestDurationConvertsConfiguredMinutesToSeconds(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_SECONDS_IN_MINUTE", 60); $GLOBALS["ids"] = []; '
                    . '$GLOBALS["durations"] = [5, "2.5", "invalid"]; '
                    . 'function f_get_test_data($id) { $GLOBALS["ids"][] = $id; '
                    . 'return ["test_duration_time" => array_shift($GLOBALS["durations"])]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_duration)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $values = []; '
                    . 'foreach (["7", "8", "9"] as $id) { try { $value = $qualified($id); '
                    . '$values[] = [$value, get_debug_type($value)]; } catch (\\Throwable $error) { '
                    . '$values[] = [get_class($error)]; } } '
                    . 'echo json_encode([$values, $GLOBALS["ids"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [[[300, 'int'], [150, 'float'], ['TypeError']], [7, 8, 9]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testOmittedQuestionCountUsesActiveUserAndIncompleteLogs(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); $_SESSION["session_user_id"] = "11"; '
                    . '$GLOBALS["count_calls"] = []; function F_count_rows($tables, $where) { '
                    . '$GLOBALS["count_calls"][] = [$tables, $where]; return 3; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_num_omitted_questions)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $count = $qualified("7"); '
                    . '$call = $GLOBALS["count_calls"][0]; echo json_encode([$count, $call[0], '
                    . 'str_contains($call[1], "testuser_test_id=7"), '
                    . 'str_contains($call[1], "testuser_user_id=11"), '
                    . 'str_contains($call[1], "testuser_status<5"), '
                    . 'str_contains($call[1], "testlog_change_time IS NULL OR testlog_display_time IS NULL")]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [3, 'test_user, test_logs', true, true, true, true],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAnswerIdsAreMappedFromDisplayedPositions(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_LOG_ANSWER", "log_answers"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return $sql; } '
                    . 'function F_db_fetch_array($sql) { preg_match("/logansw_order=([0-9]+)/", $sql, $m); '
                    . 'return ["logansw_answer_id" => 100 + (int) $m[1]]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_answer_id_from_position)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$result = $qualified("7", [2 => "B", 5 => "E"]); '
                    . 'echo json_encode([$result, $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [[102 => 'B', 105 => 'E'], [
                'SELECT logansw_answer_id FROM log_answers WHERE logansw_testlog_id=7 AND logansw_order=2 LIMIT 1',
                'SELECT logansw_answer_id FROM log_answers WHERE logansw_testlog_id=7 AND logansw_order=5 LIMIT 1',
            ]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestPasswordReadsTheRequestedTestData(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["ids"] = []; $GLOBALS["errors"] = []; '
                    . '$GLOBALS["data"] = [["test_password" => "secret"], ["test_password" => null], []]; '
                    . 'set_error_handler(function ($severity, $message) { $GLOBALS["errors"][] = $message; return true; }); '
                    . 'function f_get_test_data($id) { $GLOBALS["ids"][] = $id; '
                    . 'return array_shift($GLOBALS["data"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_password)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7"), $qualified("8"), $qualified("9")], '
                    . '$GLOBALS["ids"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [['secret', null, null], [7, 8, 9], ['Undefined array key "test_password"']],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestNameReadsTheRequestedTestData(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["ids"] = []; $GLOBALS["errors"] = []; '
                    . '$GLOBALS["data"] = [["test_name" => "Final exam"], ["test_name" => null], []]; '
                    . 'set_error_handler(function ($severity, $message) { $GLOBALS["errors"][] = $message; return true; }); '
                    . 'function f_get_test_data($id) { $GLOBALS["ids"][] = $id; '
                    . 'return array_shift($GLOBALS["data"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_name)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("9"), $qualified("10"), $qualified("11")], '
                    . '$GLOBALS["ids"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [['Final exam', null, null], [9, 10, 11], ['Undefined array key "test_name"']],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestGroupsReturnOrderedCommaSeparatedIds(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_GROUPS", "test_groups"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["rows"] = [['
                    . '"tstgrp_group_id" => 3], ["tstgrp_group_id" => 7]]; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return true; } '
                    . 'function F_db_fetch_assoc($result) { return array_shift($GLOBALS["rows"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_groups)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified("7"), $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            ['0,3,7', ['SELECT tstgrp_group_id FROM test_groups WHERE tstgrp_test_id=7 ORDER BY tstgrp_group_id']],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestSslCertificatesReturnOrderedCommaSeparatedIds(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_SSLCERTS", "test_ssl"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["rows"] = [['
                    . '"tstssl_ssl_id" => 2], ["tstssl_ssl_id" => 8]]; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return true; } '
                    . 'function F_db_fetch_assoc($result) { return array_shift($GLOBALS["rows"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_ssl_certs)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified("7"), $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            ['0,2,8', ['SELECT tstssl_ssl_id FROM test_ssl WHERE tstssl_test_id=7 ORDER BY tstssl_ssl_id']],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testCompletedUserTestCountUsesRetryStatusBoundary(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . '$GLOBALS["calls"] = []; function F_count_rows($table, $where) { '
                    . '$GLOBALS["calls"][] = [$table, $where]; return 4; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_count_user_test)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified(11, 7), $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [4, [['test_user', 'WHERE testuser_test_id=7 AND testuser_user_id=11 AND testuser_status >= 4']]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testFirstTestUserUsesOnlyStartedAttempts(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return true; } '
                    . 'function F_db_fetch_array($result) { return ["testuser_id" => 42]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_first_test_user)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified("7"), $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [42, ["SELECT testuser_id\n\t\tFROM test_user\n\t\tWHERE testuser_test_id=7\n\t\t\tAND testuser_status>0\n\t\tLIMIT 1"]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestStartTimePreservesEpochAndInvalidDateResults(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["rows"] = [['
                    . '"testuser_creation_time" => "1970-01-01 00:00:01 UTC"], '
                    . '["testuser_creation_time" => "invalid"]]; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return true; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_start_time)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified("7"), $qualified("8"), $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [1, false, [
                "SELECT testuser_creation_time\n\t\tFROM test_user\n\t\tWHERE testuser_id=7",
                "SELECT testuser_creation_time\n\t\tFROM test_user\n\t\tWHERE testuser_id=8",
            ]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestLogOwnershipPreservesAllDecisionBranches(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); $_SESSION["session_user_id"] = "11"; '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["query_results"] = [true, true, true, true, false]; '
                    . '$GLOBALS["rows"] = [["testuser_user_id" => "11", "testuser_test_id" => "7"], '
                    . '["testuser_user_id" => "12", "testuser_test_id" => "7"], '
                    . '["testuser_user_id" => "11", "testuser_test_id" => "8"], false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . 'function f_legacy_int_equals($left, $right) { return (int) $left === (int) $right; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_is_right_testlog_user)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $results = []; '
                    . 'foreach ([21, 22, 23, 24, 25] as $testlog_id) { '
                    . '$results[] = $qualified("7", (string) $testlog_id); } '
                    . 'echo json_encode([$results, $GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [true, false, false, false, true],
                1,
                [
                    "SELECT testuser_user_id, testuser_test_id\n\t\tFROM test_user, test_logs\n"
                        . "\t\tWHERE testuser_id=testlog_testuser_id\n\t\t\tAND testlog_id=21",
                    "SELECT testuser_user_id, testuser_test_id\n\t\tFROM test_user, test_logs\n"
                        . "\t\tWHERE testuser_id=testlog_testuser_id\n\t\t\tAND testlog_id=22",
                    "SELECT testuser_user_id, testuser_test_id\n\t\tFROM test_user, test_logs\n"
                        . "\t\tWHERE testuser_id=testlog_testuser_id\n\t\t\tAND testlog_id=23",
                    "SELECT testuser_user_id, testuser_test_id\n\t\tFROM test_user, test_logs\n"
                        . "\t\tWHERE testuser_id=testlog_testuser_id\n\t\t\tAND testlog_id=24",
                    "SELECT testuser_user_id, testuser_test_id\n\t\tFROM test_user, test_logs\n"
                        . "\t\tWHERE testuser_id=testlog_testuser_id\n\t\t\tAND testlog_id=25",
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestSslCertificateValidationPreservesAllBranches(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_SSLCERTS", "test_ssl"); '
                    . 'define("K_TABLE_SSLCERTS", "ssl_certs"); '
                    . '$GLOBALS["counts"] = [0, 1, 1, 1, 0]; $GLOBALS["calls"] = []; $GLOBALS["hash_calls"] = 0; '
                    . 'function F_count_rows($tables, $where) { $GLOBALS["calls"][] = [$tables, $where]; '
                    . 'return array_shift($GLOBALS["counts"]); } '
                    . 'function f_get_ssl_client_hash() { ++$GLOBALS["hash_calls"]; return "client-hash"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_is_valid_ssl_cert)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7"), $qualified("8"), $qualified("9")], '
                    . '$GLOBALS["hash_calls"], $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [true, true, false],
                2,
                [
                    ['test_ssl', 'WHERE tstssl_test_id=7'],
                    ['test_ssl', 'WHERE tstssl_test_id=8'],
                    [
                        'test_ssl, ssl_certs',
                        "WHERE tstssl_ssl_id=ssl_id\n\t\t\tAND tstssl_test_id=8\n"
                            . "\t\t\tAND ssl_hash='client-hash'\n\t\t\tLIMIT 1",
                    ],
                    ['test_ssl', 'WHERE tstssl_test_id=9'],
                    [
                        'test_ssl, ssl_certs',
                        "WHERE tstssl_ssl_id=ssl_id\n\t\t\tAND tstssl_test_id=9\n"
                            . "\t\t\tAND ssl_hash='client-hash'\n\t\t\tLIMIT 1",
                    ],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserDataLookupPreservesRowsMissingRowsAndQueryErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_USERS", "users"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["query_results"] = [true, true, false]; '
                    . '$GLOBALS["rows"] = [["user_id" => 7, "user_name" => "alice"], false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_assoc($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_user_data)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7"), $qualified("8"), $qualified("9")], '
                    . '$GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [['user_id' => 7, 'user_name' => 'alice'], false, []],
                1,
                [
                    "SELECT *\n\t\tFROM users\n\t\tWHERE user_id=7\n\t\tLIMIT 1",
                    "SELECT *\n\t\tFROM users\n\t\tWHERE user_id=8\n\t\tLIMIT 1",
                    "SELECT *\n\t\tFROM users\n\t\tWHERE user_id=9\n\t\tLIMIT 1",
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestDataLookupPreservesRowsMissingRowsAndQueryErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS", "tests"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["query_results"] = [true, true, false]; '
                    . '$GLOBALS["rows"] = [["test_id" => 7, "test_name" => "Final"], false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_assoc($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_data)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7"), $qualified("8"), $qualified("9")], '
                    . '$GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [['test_id' => 7, 'test_name' => 'Final'], false, []],
                1,
                [
                    "SELECT *\n\t\tFROM tests\n\t\tWHERE test_id=7\n\t\tLIMIT 1",
                    "SELECT *\n\t\tFROM tests\n\t\tWHERE test_id=8\n\t\tLIMIT 1",
                    "SELECT *\n\t\tFROM tests\n\t\tWHERE test_id=9\n\t\tLIMIT 1",
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestLimitChecksPreservePriorityAndBoundaries(): void
    {
        $cases = [
            'remaining exhausted' => [[-1, 2, 3, 4], [], true, 0],
            'daily limit reached' => [[0, 2, 0, false], [2], true, 1],
            'monthly limit reached' => [[0, 2, 3, false], [1, 3], true, 2],
            'yearly limit reached' => [[0, 0, 0, 4], [4], true, 1],
            'all limits available' => [[0, 2, 3, 4], [1, 2, 3], false, 3],
        ];

        foreach ($cases as $label => [$limits, $counts, $expected, $expectedCalls]) {
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    'namespace Harness; $config = json_decode($argv[2], true); '
                        . 'define("K_REMAINING_TESTS", $config[0][0]); define("K_MAX_TESTS_DAY", $config[0][1]); '
                        . 'define("K_MAX_TESTS_MONTH", $config[0][2]); define("K_MAX_TESTS_YEAR", $config[0][3]); '
                        . 'define("K_SECONDS_IN_DAY", 86400); define("K_SECONDS_IN_MONTH", 2592000); '
                        . 'define("K_SECONDS_IN_YEAR", 31536000); define("K_TIMESTAMP_FORMAT", "\\\\F\\\\I\\\\X\\\\E\\\\D"); '
                        . 'define("K_TABLE_TESTUSER_STAT", "test_stats"); '
                        . '$GLOBALS["counts"] = $config[1]; $GLOBALS["calls"] = []; '
                        . 'function F_count_rows($table, $where) { $GLOBALS["calls"][] = [$table, $where]; '
                        . 'return array_shift($GLOBALS["counts"]); } '
                        . '$source = file_get_contents($argv[1]); '
                        . 'preg_match("/function (f_is_test_over_limits)\\(/", '
                        . '$source, $match, PREG_OFFSET_CAPTURE); '
                        . '$name = $match[1][0]; $start = $match[0][1]; '
                        . '$end = strpos($source, "\\n/**", $start); '
                        . '$function = substr($source, $start, $end - $start); '
                        . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                        . 'eval("namespace Harness; " . $function); '
                        . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                        . 'echo json_encode([$qualified(), $GLOBALS["calls"]]);',
                    dirname(__DIR__) . '/shared/code/tce_functions_test.php',
                    json_encode([$limits, $counts], JSON_THROW_ON_ERROR),
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $label . ': ' . $output);
            /** @var array{0: bool, 1: list<array{0: string, 1: string}>} $decoded */
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            [$actual, $calls] = $decoded;
            self::assertSame($expected, $actual, $label);
            self::assertCount($expectedCalls, $calls, $label);
            foreach ($calls as $call) {
                self::assertSame(
                    ['test_stats', "WHERE tus_date>='FIXED' AND tus_date<='FIXED'"],
                    $call,
                    $label,
                );
            }
        }
    }

    public function testGeneratedTestStatisticsPreserveInsertAndErrorHandling(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTUSER_STAT", "test_stats"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["results"] = [true, false]; $GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["results"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_update_testuser_stat)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("2026-01-02 03:04:05"), '
                    . '$qualified("2026-06-07 08:09:10")], $GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [null, null],
                1,
                [
                    "INSERT INTO test_stats (tus_date) VALUES ('2026-01-02 03:04:05')",
                    "INSERT INTO test_stats (tus_date) VALUES ('2026-06-07 08:09:10')",
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testLogAnswersInsertPreservesEmptyOrderAndErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_LOG_ANSWER", "log_answers"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["results"] = [true, true, false]; $GLOBALS["queries"] = []; $GLOBALS["errors"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["results"]); } '
                    . 'function F_display_db_error(...$args) { $GLOBALS["errors"][] = $args; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_add_log_answers)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7", []), $qualified("8", [10 => 3, 20 => "4"]), '
                    . '$qualified("9", [5])], $GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [true, true, false],
                [[false]],
                [
                    $this->logAnswersInsertSql(''),
                    $this->logAnswersInsertSql('(8, 3, -1, 1), (8, 4, -1, 2)'),
                    $this->logAnswersInsertSql('(9, 5, -1, 1)'),
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testNewTestLogPreservesInsertIdAndFailureBehavior(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS_LOGS", "test_logs"); '
                    . 'define("K_TIMESTAMP_FORMAT", "timestamp-format"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["results"] = [true, false]; $GLOBALS["queries"] = []; '
                    . '$GLOBALS["errors"] = []; $GLOBALS["insert_calls"] = []; '
                    . 'function date($format) { return "2026-08-10 12:34:56"; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_insert_id(...$arguments) { $GLOBALS["insert_calls"][] = $arguments; return 55; } '
                    . 'function F_display_db_error(...$arguments) { $GLOBALS["errors"][] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_new_test_log)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$success = $qualified("07", "08", "2.50", "09", "010"); '
                    . '$failure = $qualified(11, 12, -1, 13, 4); '
                    . 'echo json_encode([$success, $failure, $GLOBALS["queries"], '
                    . '$GLOBALS["insert_calls"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                55,
                false,
                [
                    $this->newTestLogInsertSql(7, 8, '2.5', '09', '010'),
                    $this->newTestLogInsertSql(11, 12, '-1', '13', '4'),
                ],
                [['db', 'test_logs', 'testlog_id']],
                [[false]],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestStatusCheckPreservesEveryStateTransition(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_users"); '
                    . 'define("K_TABLE_TESTS_LOGS", "test_logs"); define("K_TIMESTAMP_FORMAT", "format"); '
                    . 'define("K_SECONDS_IN_MINUTE", 60); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["rows"] = [false, '
                    . '["testuser_id" => 101, "testuser_status" => 1, '
                    . '"testuser_creation_time" => "old", "testuser_pregenerated" => false], '
                    . '["testuser_id" => 102, "testuser_status" => 0, '
                    . '"testuser_creation_time" => "future", "testuser_pregenerated" => false], '
                    . '["testuser_id" => 103, "testuser_status" => 1, '
                    . '"testuser_creation_time" => "future", "testuser_pregenerated" => false], '
                    . '["testuser_id" => 104, "testuser_status" => 2, '
                    . '"testuser_creation_time" => "future", "testuser_pregenerated" => false], '
                    . '["testuser_id" => 105, "testuser_status" => 1, '
                    . '"testuser_creation_time" => "old", "testuser_pregenerated" => true]]; '
                    . '$GLOBALS["counts"] = [0, 0, 1]; $GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function date($format, $timestamp = null) { if ($timestamp === null) { '
                    . 'return "2026-08-10 12:00:00"; } return $timestamp > 500 '
                    . '? "2026-08-10 13:00:00" : "2026-08-10 11:00:00"; } '
                    . 'function strtotime($value) { return $value === "future" ? 1000 : 100; } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return true; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_count_rows($table, $where) { return array_shift($GLOBALS["counts"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_check_test_status)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $results = []; '
                    . 'for ($i = 0; $i < 6; ++$i) { $results[] = $qualified("11", "22", "5"); } '
                    . 'echo json_encode([$results, $GLOBALS["queries"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        $select = 'SELECT testuser_id, testuser_status, testuser_creation_time, testuser_pregenerated '
            . 'FROM test_users WHERE testuser_test_id=22 AND testuser_user_id=11 '
            . 'ORDER BY testuser_status LIMIT 1';
        self::assertSame(
            [
                [
                    [0, 0, false],
                    [4, 101, false],
                    [0, 102, false],
                    [2, 103, false],
                    [3, 104, false],
                    [1, 105, true],
                ],
                [
                    $select,
                    $select,
                    "UPDATE test_users SET testuser_status=4, testuser_close_reason='timeout', "
                        . "testuser_last_activity='2026-08-10 12:00:00' WHERE testuser_id=101",
                    $select,
                    'DELETE FROM test_users WHERE testuser_id=102',
                    $select,
                    'UPDATE test_users SET testuser_status=2 WHERE testuser_id=103',
                    $select,
                    'UPDATE test_users SET testuser_status=3 WHERE testuser_id=104',
                    $select,
                ],
                0,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testRepeatTestPreservesAttemptUpdatesAndDatabaseErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TESTS", "tests"); '
                    . 'define("K_TABLE_TEST_USER", "test_users"); $GLOBALS["db"] = "db"; '
                    . '$_SESSION["session_user_id"] = "011"; '
                    . '$GLOBALS["results"] = [false, "empty", "test-3", false, '
                    . '"test-4", "attempts", true, false]; '
                    . '$GLOBALS["rows"] = ["empty" => [false], "test-3" => [["test_id" => 22]], '
                    . '"test-4" => [["test_id" => 22]], "attempts" => [['
                    . '"testuser_id" => 201], ["testuser_id" => 202], false]]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'preg_replace("/\\s+/", " ", trim($sql)); return array_shift($GLOBALS["results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"][$result]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_repeat_test)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $returns = []; '
                    . 'for ($i = 0; $i < 4; ++$i) { $returns[] = $qualified("022"); } '
                    . 'echo json_encode([$returns, $GLOBALS["queries"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        $testQuery = "SELECT test_id FROM tests WHERE test_id=22 AND test_repeatable<>'0' LIMIT 1";
        $attemptQuery = 'SELECT testuser_id FROM test_users WHERE testuser_test_id=22 '
            . 'AND testuser_user_id=11 AND testuser_status>3 ORDER BY testuser_status DESC';
        self::assertSame(
            [
                [null, null, null, null],
                [
                    $testQuery,
                    $testQuery,
                    $testQuery,
                    $attemptQuery,
                    $testQuery,
                    $attemptQuery,
                    'UPDATE test_users SET testuser_status=testuser_status+1 WHERE testuser_id=201',
                    'UPDATE test_users SET testuser_status=testuser_status+1 WHERE testuser_id=202',
                ],
                3,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function logAnswersInsertSql(string $values): string
    {
        return "INSERT INTO log_answers (\n\t\t\tlogansw_testlog_id,\n"
            . "\t\t\tlogansw_answer_id,\n\t\t\tlogansw_selected,\n\t\t\tlogansw_order\n"
            . "\t\t\t) VALUES " . $values;
    }

    private function newTestLogInsertSql(int $testUserId, int $questionId, string $score, string $order, string $answers): string
    {
        return "INSERT INTO test_logs (\n\t\ttestlog_testuser_id,\n\t\ttestlog_question_id,\n"
            . "\t\ttestlog_score,\n\t\ttestlog_creation_time,\n\t\ttestlog_reaction_time,\n"
            . "\t\ttestlog_order,\n\t\ttestlog_num_answers\n\t\t) VALUES (\n\t\t"
            . $testUserId . ",\n\t\t" . $questionId . ",\n\t\t" . $score
            . ",\n\t\t'2026-08-10 12:34:56',\n\t\t0,\n\t\t" . $order . ",\n\t\t" . $answers . "\n\t\t)";
    }

    public function testTestUserAuthorizationPreservesShortCircuitChecks(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_USERGROUP", "user_groups"); '
                    . 'define("K_TABLE_TEST_GROUPS", "test_groups"); $_SESSION["session_user_id"] = "11"; '
                    . '$GLOBALS["ip_results"] = [false, true, true, true]; '
                    . '$GLOBALS["ssl_results"] = [false, true, true]; $GLOBALS["counts"] = [0, 1]; '
                    . '$GLOBALS["ip_calls"] = []; $GLOBALS["ssl_calls"] = []; $GLOBALS["count_calls"] = []; '
                    . 'function f_is_valid_ip($userIp, $testIp) { $GLOBALS["ip_calls"][] = [$userIp, $testIp]; '
                    . 'return array_shift($GLOBALS["ip_results"]); } '
                    . 'function f_is_valid_ssl_cert($testId) { $GLOBALS["ssl_calls"][] = $testId; '
                    . 'return array_shift($GLOBALS["ssl_results"]); } '
                    . 'function F_count_rows($tables, $where) { $GLOBALS["count_calls"][] = [$tables, $where]; '
                    . 'return array_shift($GLOBALS["counts"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_is_valid_test_user)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([['
                    . '$qualified("7", "user-ip-1", "test-ip-1"), '
                    . '$qualified("8", "user-ip-2", "test-ip-2"), '
                    . '$qualified("9", "user-ip-3", "test-ip-3"), '
                    . '$qualified("10", "user-ip-4", "test-ip-4")], '
                    . '$GLOBALS["ip_calls"], $GLOBALS["ssl_calls"], $GLOBALS["count_calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [false, false, false, true],
                [
                    ['user-ip-1', 'test-ip-1'],
                    ['user-ip-2', 'test-ip-2'],
                    ['user-ip-3', 'test-ip-3'],
                    ['user-ip-4', 'test-ip-4'],
                ],
                [8, 9, 10],
                [
                    [
                        'user_groups, test_groups',
                        "WHERE usrgrp_group_id=tstgrp_group_id\n\t\t\tAND tstgrp_test_id=9\n"
                            . "\t\t\tAND usrgrp_user_id=11\n\t\t\tLIMIT 1",
                    ],
                    [
                        'user_groups, test_groups',
                        "WHERE usrgrp_group_id=tstgrp_group_id\n\t\t\tAND tstgrp_test_id=10\n"
                            . "\t\t\tAND usrgrp_user_id=11\n\t\t\tLIMIT 1",
                    ],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTestTerminationPreservesReasonsUpdateAndErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . 'define("K_TIMESTAMP_FORMAT", "\\\\F\\\\I\\\\X\\\\E\\\\D"); '
                    . '$_SESSION["session_user_id"] = "11"; $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["results"] = [true, false, true]; $GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["results"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_terminate_user_test)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7"), $qualified("8", "timeout"), '
                    . '$qualified("9", "unexpected")], $GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [null, null, null],
                1,
                [
                    $this->terminationUpdateSql(7, 'completed'),
                    $this->terminationUpdateSql(8, 'timeout'),
                    $this->terminationUpdateSql(9, 'completed'),
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function terminationUpdateSql(int $testId, string $reason): string
    {
        return "UPDATE test_user\n\t\tSET testuser_status=4,\n"
            . "\t\t\ttestuser_close_reason='" . $reason . "',\n"
            . "\t\t\ttestuser_last_activity='FIXED'\n"
            . "\t\tWHERE testuser_test_id=" . $testId . "\n"
            . "\t\t\tAND testuser_user_id=11\n\t\t\tAND testuser_status<4";
    }












    #[DataProvider('reviewValues')]
    public function testReviewValueNormalization(mixed $value, int $expected): void
    {
        self::assertSame($expected, \f_tmf_review_value($value));
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function reviewValues(): array
    {
        return [
            'string one' => ['1', 1],
            'integer one' => [1, 1],
            'float one' => [1.0, 1],
            'boolean true' => [true, 1],
            'zero' => ['0', 0],
            'missing value' => [null, 0],
            'array input' => [['1'], 0],
        ];
    }
}
