<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TsvResultsEndpointTest extends TestCase
{
    public function testAllUsersExportPreservesFiltersOrderingAndDetails(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_ADMIN_RESULTS', 8);
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_NEWLINE', "\n");
define('K_TAB', "\t");
function f_is_authorized_user(...$arguments): bool
{
    $GLOBALS['authorization'] = $arguments;
    return true;
}
function f_get_all_users_test_stat(...$arguments): array
{
    $GLOBALS['stat_calls'][] = $arguments;
    $userId = $arguments[2];
    return $userId === 0
        ? ['scope' => 'all', 'testuser' => [['user_id' => '9'], ['user_id' => '10']]]
        : ['scope' => (string) $userId, 'testuser' => [['user_id' => (string) $userId]]];
}
function f_print_test_result_stat(array $data, ...$arguments): string
{
    $GLOBALS['result_calls'][] = $arguments;
    return '<RESULT:' . $data['scope'] . '>';
}
function f_print_test_stat($testId, $groupId, $userId, $start, $end, $mode, array $data, $display): string
{
    $GLOBALS['summary_calls'][] = [$testId, $groupId, $userId, $start, $end, $mode, $display];
    return '<STAT:' . $data['scope'] . '>';
}
function f_html_to_tsv(string $table): string { return 'TSV[' . $table . ']'; }
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-tsv-results-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_tsv_result_allusers.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'foreach (["tce_authorization.php", "tce_functions_test_stats.php"] as $file) '
                    . '{ file_put_contents($root . "/shared/code/" . $file, "<?php"); } '
                    . '$_REQUEST = ["test_id" => "17", "group_id" => "4", "user_id" => "0", '
                    . '"startdate" => "2026-08-01 10:20:30", "enddate" => "2026-08-02 11:22:33", '
                    . '"order_field" => "user_lastname", "orderdir" => "DESC", "display_mode" => "9"]; '
                    . 'chdir($root . "/admin/code"); ob_start(); require "tce_tsv_result_allusers.php"; '
                    . '$body = ob_get_clean(); $result = [$body, $GLOBALS["authorization"], '
                    . '$GLOBALS["stat_calls"], $GLOBALS["result_calls"], $GLOBALS["summary_calls"]]; '
                    . 'foreach (["/admin/code/tce_tsv_result_allusers.php", "/admin/config/tce_config.php", '
                    . '"/shared/code/tce_authorization.php", "/shared/code/tce_functions_test_stats.php"] '
                    . 'as $file) { unlink($root . $file); } rmdir($root . "/admin/code"); '
                    . 'rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_tsv_result_allusers.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: string,
         *     1: array{string, string, int, string},
         *     2: array{array<mixed>, array<mixed>, array<mixed>},
         *     3: array{array<mixed>, array<mixed>, array<mixed>},
         *     4: array{array<mixed>, array<mixed>, array<mixed>}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            "TSV[<RESULT:all><STAT:all>]\n\n\n<<< DETAILS >>>\n"
                . "\n\n### USER\t9\n\nTSV[<RESULT:9><STAT:9>]"
                . "\n\n### USER\t10\n\nTSV[<RESULT:10><STAT:10>]",
            $decoded[0],
        );
        self::assertSame(['tests', 'test_id', 17, 'test_user_id'], $decoded[1]);
        self::assertSame(
            [
                [17, 4, 0, '2026-08-01 10:20:30', '2026-08-02 11:22:33', 'user_lastname DESC', false, 5],
                [17, 4, '9', '2026-08-01 10:20:30', '2026-08-02 11:22:33', 'user_lastname DESC'],
                [17, 4, '10', '2026-08-01 10:20:30', '2026-08-02 11:22:33', 'user_lastname DESC'],
            ],
            $decoded[2],
        );
        self::assertSame([[1, 'user_lastname', '', false, 5], [1, 'user_lastname', '', false, 5],
            [1, 'user_lastname', '', false, 5]], $decoded[3]);
        self::assertSame([[17, 4, 0, '2026-08-01 10:20:30', '2026-08-02 11:22:33', 0, 5],
            [17, 4, '9', '2026-08-01 10:20:30', '2026-08-02 11:22:33', 0, 5],
            [17, 4, '10', '2026-08-01 10:20:30', '2026-08-02 11:22:33', 0, 5]], $decoded[4]);
    }
}
