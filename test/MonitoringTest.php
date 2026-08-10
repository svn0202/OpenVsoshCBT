<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_monitoring.php';

final class MonitoringTest extends TestCase
{
    public function testStatusesAreDerivedWithoutAnswerContents(): void
    {
        $now = strtotime('2026-07-27 12:00:00');
        if ($now === false) {
            self::fail('Unable to create the monitoring reference timestamp.');
        }

        self::assertSame('not_started', \F_tmf_monitor_status(null, null, null, $now));
        self::assertSame('in_progress', \F_tmf_monitor_status(1, null, '2026-07-27 11:59:00', $now));
        self::assertSame('connection_lost', \F_tmf_monitor_status(2, null, '2026-07-27 11:50:00', $now));
        self::assertSame('completed', \F_tmf_monitor_status(4, 'completed', '2026-07-27 11:59:00', $now));
        self::assertSame('blocked', \F_tmf_monitor_status(4, 'blocked', '2026-07-27 11:59:00', $now));
        self::assertSame('timed_out', \F_tmf_monitor_status(4, 'timeout', '2026-07-27 11:59:00', $now));
    }

    public function testOnlyKnownMonitoringActionsAreAccepted(): void
    {
        foreach (['block', 'unblock', 'extend', 'reset'] as $action) {
            self::assertTrue(\F_tmf_monitor_action_is_valid($action));
        }
        self::assertFalse(\F_tmf_monitor_action_is_valid('delete'));
    }

    public function testFocusEventIdentifiersAreStrictlyValidated(): void
    {
        self::assertTrue(\F_tmf_focus_event_is_valid('0123456789abcdef0123456789abcdef'));
        self::assertFalse(\F_tmf_focus_event_is_valid('0123456789ABCDEF0123456789ABCDEF'));
        self::assertFalse(\F_tmf_focus_event_is_valid('../0123456789abcdef0123456789abcdef'));
    }

    public function testBlockActionKeepsItsTransactionAndAuditContract(): void
    {
        $result = self::runAction('block');

        self::assertSame(['status' => 'updated', 'testuser_id' => 42], $result['response']);
        self::assertSame([], $result['created']);
        self::assertCount(6, $result['queries']);
        /** @var array{string,string,string,string,string,string} $queries */
        $queries = $result['queries'];
        self::assertSame('START TRANSACTION', $queries[1]);
        self::assertStringContainsString('FOR UPDATE', $queries[2]);
        self::assertStringContainsString('SET testuser_status=4,', $queries[3]);
        self::assertStringContainsString("testuser_close_reason='blocked'", $queries[3]);
        self::assertStringContainsString("'block', NULL, '192.0.2.1'", $queries[4]);
        self::assertSame('COMMIT', $queries[5]);
    }

    public function testResetActionKeepsArchivalAndReplacementContract(): void
    {
        $result = self::runAction('reset');

        self::assertSame(
            ['status' => 'updated', 'testuser_id' => 42, 'new_testuser_id' => 88],
            $result['response'],
        );
        self::assertSame([[7, 11]], $result['created']);
        self::assertCount(8, $result['queries']);
        /** @var array{string,string,string,string,string,string,string,string} $queries */
        $queries = $result['queries'];
        self::assertStringContainsString('SELECT MAX(testuser_status) AS max_status', $queries[3]);
        self::assertStringContainsString('SET testuser_status=7,', $queries[4]);
        self::assertStringContainsString("testuser_close_reason='reset'", $queries[4]);
        self::assertStringContainsString('ORDER BY testuser_id DESC', $queries[5]);
        self::assertStringContainsString("'reset', 'new_testuser_id=88', '192.0.2.1'", $queries[6]);
        self::assertSame('COMMIT', $queries[7]);
    }

    /**
     * @return array{
     *     response:array{status:string,testuser_id:int,new_testuser_id?:int},
     *     queries:list<string>,
     *     created:list<array{int,int}>
     * }
     */
    private static function runAction(string $action): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TABLE_PREFIX', 't_');
define('K_TABLE_TEST_USER', 't_test_users');
define('K_TABLE_TESTS', 't_tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_SECONDS_IN_MINUTE', 60);
$db = 'db';
$_SESSION = ['session_user_id' => 9];
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$GLOBALS['queries'] = [];
$GLOBALS['created'] = [];
function date($format, $timestamp = null) { return '2026-08-10 12:34:56'; }
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    if (str_starts_with($sql, 'SELECT')) { return fopen('php://memory', 'r'); }
    return true;
}
function F_db_fetch_array($result) {
    $sql = end($GLOBALS['queries']);
    if (str_contains($sql, 'SELECT testuser_test_id FROM')) { return ['testuser_test_id' => '7']; }
    if (str_contains($sql, 'FOR UPDATE')) { return [
            'testuser_test_id' => '7', 'testuser_user_id' => '11',
            'testuser_status' => '1', 'testuser_creation_time' => '2026-08-10 10:00:00',
            'testuser_close_reason' => null,
        ]; }
    if (str_contains($sql, 'SELECT MAX(testuser_status)')) { return ['max_status' => '6']; }
    if (str_contains($sql, 'ORDER BY testuser_id DESC')) { return ['testuser_id' => '88']; }
    return false;
}
function f_is_authorized_user(...$arguments) { return true; }
function f_create_test($test_id, $user_id) {
    $GLOBALS['created'][] = [$test_id, $user_id];
    return true;
}
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function get_normalized_ip($ip) { return $ip; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
$response = F_tmf_monitor_apply_action(42, $argv[2]);
echo json_encode([
    'response' => $response, 'queries' => $GLOBALS['queries'], 'created' => $GLOBALS['created'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/shared/code/tce_functions_monitoring.php', $action],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{
         *     response:array{status:string,testuser_id:int,new_testuser_id?:int},
         *     queries:list<string>,
         *     created:list<array{int,int}>
         * }
         */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
