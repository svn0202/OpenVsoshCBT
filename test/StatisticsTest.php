<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_statistics.php';

final class StatisticsTest extends TestCase
{
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
                    . 'preg_match("/function (F_lockUserTest|f_lock_user_test)\\(/", '
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
}
