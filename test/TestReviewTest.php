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
                    . 'function F_getTestData($id) { $GLOBALS["ids"][] = $id; '
                    . 'return ["test_duration_time" => 5]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_test_duration)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified("7"), $GLOBALS["ids"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame([300, [7]], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
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
                'namespace Harness; $GLOBALS["ids"] = []; function F_getTestData($id) { '
                    . '$GLOBALS["ids"][] = $id; return ["test_password" => "secret"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (F_getTestPassword|f_get_test_password)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([$qualified("7"), $GLOBALS["ids"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(['secret', [7]], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
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
