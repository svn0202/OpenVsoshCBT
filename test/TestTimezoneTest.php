<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestTimezoneTest extends TestCase
{
    public function testDatabaseTimezoneAppliesBeforeAuthorizationAndControlsScheduleQueries(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TIMEZONE', 'UTC');
define('K_DATABASE_HOST', 'host');
define('K_DATABASE_PORT', 0);
define('K_DATABASE_USER_NAME', 'user');
define('K_DATABASE_USER_PASSWORD', '');
define('K_DATABASE_NAME', 'db');
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
function F_db_connect(...$args) { return 'db'; }
function openvsosh_get_setting($key) {
    if (($GLOBALS['db'] ?? null) !== 'db') { throw new \RuntimeException('Missing global connection'); }
    return $GLOBALS['timezone'];
}
function date($format) { return \date($format, strtotime('2026-09-10T04:00:00Z')); }
function F_db_query($sql, $db) { $GLOBALS['query'] = preg_replace('/\s+/', ' ', $sql); return true; }
function F_db_fetch_array($result) { return false; }
$connect = file_get_contents($argv[1] . '/shared/code/tce_db_connect.php');
$connect = preg_replace('/^require_once .*$/m', '', substr($connect, 5));
$source = file_get_contents($argv[1] . '/shared/code/tce_functions_test.php');
$start = strpos($source, 'function f_execute_test(');
$end = strpos($source, '
/**', $start);
$execute = preg_replace('/^    require_once .*$/m', '', substr($source, $start, $end - $start));
eval('namespace Harness; ' . $execute);
$result = [];
foreach (['Asia/Yekaterinburg', 'UTC', null] as $timezone) {
    $GLOBALS['timezone'] = $timezone;
    date_default_timezone_set('Pacific/Honolulu');
    (static function ($source) { eval('namespace Harness; ' . $source); })($connect);
    f_execute_test(7);
    $result[] = [date_default_timezone_get(), date(K_TIMESTAMP_FORMAT),
        strtotime('2026-09-10 09:00:00'), $GLOBALS['query']];
}
echo json_encode($result);
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__)],
            dirname(__DIR__),
        );
        self::assertSame(0, $status, $output);
        /** @var array{array{string,string,int,string},array{string,string,int,string},array{string,string,int,string}} $results */
        $results = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Asia/Yekaterinburg', $results[0][0]);
        self::assertSame('2026-09-10 09:00:00', $results[0][1]);
        self::assertSame(strtotime('2026-09-10T04:00:00Z'), $results[0][2]);
        self::assertStringContainsString("test_begin_time <= '2026-09-10 09:00:00'", $results[0][3]);
        self::assertStringContainsString("test_end_time > '2026-09-10 09:00:00'", $results[0][3]);
        foreach ([$results[1], $results[2]] as $result) {
            self::assertSame('UTC', $result[0]);
            self::assertSame('2026-09-10 04:00:00', $result[1]);
            self::assertSame(strtotime('2026-09-10T09:00:00Z'), $result[2]);
        }
    }
}
