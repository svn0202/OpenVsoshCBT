<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XlsxResultsEndpointTest extends TestCase
{
    public function testAllUsersExportPreservesFiltersAndRows(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_ADMIN_RESULTS', 8);
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
function f_is_authorized_user(...$arguments): bool
{
    $GLOBALS['authorization'] = $arguments;
    return true;
}
function f_get_all_users_test_stat(...$arguments): array
{
    $GLOBALS['stat_arguments'] = $arguments;
    return ['testuser' => [[
        'id' => '25', 'user_id' => '9', 'user_name' => 'student',
        'user_lastname' => 'Last', 'user_firstname' => 'First',
        'testuser_creation_time' => '2026-08-01 10:00:00',
        'testuser_end_time' => '2026-08-01 10:30:00', 'time_diff' => '00:30:00',
        'total_score' => '14.5', 'total_score_perc' => '72.500', 'passmsg' => true,
        'right' => '8', 'wrong' => '2', 'unanswered' => '1', 'unrated' => '0',
    ]]];
}
function f_format_float($value): string { return number_format((float) $value, 3, '.', ''); }
function F_tmf_xlsx_build(array $sheets): string
{
    $GLOBALS['sheets'] = $sheets;
    return 'XLSX-BYTES';
}
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-xlsx-results-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_xlsx_result_allusers.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'foreach (["tce_authorization.php", "tce_functions_test_stats.php", '
                    . '"tce_functions_xlsx.php"] as $file) '
                    . '{ file_put_contents($root . "/shared/code/" . $file, "<?php"); } '
                    . '$_REQUEST = ["test_id" => "17", "group_id" => "4", "user_id" => "9", '
                    . '"startdate" => "2026-08-01 10:20:30", "enddate" => "2026-08-02 11:22:33"]; '
                    . 'chdir($root . "/admin/code"); ob_start(); require "tce_xlsx_result_allusers.php"; '
                    . '$body = ob_get_clean(); $result = [$body, $GLOBALS["authorization"], '
                    . '$GLOBALS["stat_arguments"], $GLOBALS["sheets"]]; '
                    . 'foreach (["/admin/code/tce_xlsx_result_allusers.php", "/admin/config/tce_config.php", '
                    . '"/shared/code/tce_authorization.php", "/shared/code/tce_functions_test_stats.php", '
                    . '"/shared/code/tce_functions_xlsx.php"] as $file) { unlink($root . $file); } '
                    . 'rmdir($root . "/admin/code"); rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_xlsx_result_allusers.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: string,
         *     1: array{string, string, int, string},
         *     2: array{int, int, int, string, string, string, false, int},
         *     3: array{array{
         *         name: string,
         *         widths: list<int>,
         *         rows: array{list<string>, list<string|array{value: int|string, type: string}>}
         *     }}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('XLSX-BYTES', $decoded[0]);
        self::assertSame(['tests', 'test_id', 17, 'test_user_id'], $decoded[1]);
        self::assertSame(
            [17, 4, 9, '2026-08-01 10:20:30', '2026-08-02 11:22:33',
                'user_lastname,user_firstname,user_name', false, 1],
            $decoded[2],
        );
        self::assertSame('Результаты теста 17', $decoded[3][0]['name']);
        self::assertSame(
            ['25', '9', 'student', 'Last', 'First', '2026-08-01 10:00:00',
                '2026-08-01 10:30:00', '00:30:00', '14.500', '72.500', 'Да', '8', '2', '1', '0'],
            array_map(
                static fn(mixed $cell): string => (string) (is_array($cell) ? $cell['value'] : $cell),
                $decoded[3][0]['rows'][1],
            ),
        );
    }
}
