<?php

namespace Test\Integration;

use PHPUnit\Framework\TestCase;

final class MonitoringQueryEquivalenceTest extends TestCase
{
    public function testExactRowsTypesAndOrderAtTimeBoundaries(): void
    {
        if (getenv('TCEXAM_DB_TYPE') !== 'POSTGRESQL') {
            self::markTestSkipped('Requires TCEXAM_DB_* PostgreSQL connection; queries are read-only.');
        }
        define('OPENVSOSH_MONITOR_SQL_ONLY', true);
        require_once __DIR__ . '/../../tools/monitoring/zabbix-metrics.php';
        require_once __DIR__ . '/../../tools/monitoring/zabbix-participant-history.php';
        $parts = [];
        foreach (['host' => 'HOST', 'port' => 'PORT', 'dbname' => 'NAME', 'user' => 'USER', 'password' => 'PASSWORD'] as $key => $env) {
            $value = (string) getenv('TCEXAM_DB_' . $env);
            $parts[] = $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }
        $db = pg_connect(implode(' ', $parts), PGSQL_CONNECT_FORCE_NEW);
        self::assertNotFalse($db);
        pg_query($db, 'BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        pg_query($db, "SET LOCAL TIME ZONE 'Asia/Yekaterinburg'");
        pg_query($db, "SET LOCAL statement_timeout='10s'");
        $fixtures = __DIR__ . '/fixtures/monitoring/';
        $fixture = file_get_contents($fixtures . 'boundary-fixture.sql');
        $builders = ['stats' => 'tce_monitor_summary_sql', 'history' => 'tce_monitor_history_sql'];
        try {
            foreach ($builders as $name => $builder) {
                $original = str_replace('tce_', 'fixture_', file_get_contents($fixtures . $name . '.original.sql'));
                $candidate = $builder('fixture_');
                foreach (['2026-09-30 00:00:00', '2026-09-30 11:29:59.999999', '2026-09-30 11:30:00', '2026-09-30 12:00:00'] as $now) {
                    foreach ([false, true] as $empty) {
                        $data = $fixture;
                        // A false predicate empties the input without changing its column types.
                        if ($empty) {
                            $data = preg_replace('/\)\s*$/', '), empty_users AS (SELECT * FROM fixture_tests_users WHERE false) ', $data);
                        }
                        $run = static function (string $sql) use ($db, $data, $now, $empty): array {
                            if ($empty) {
                                $sql = str_replace('fixture_tests_users', 'empty_users', $sql);
                            }
                            $sql = str_replace('localtimestamp', "timestamp '" . $now . "'", $sql);
                            $sql = str_starts_with($sql, 'WITH ')
                                ? $data . ', ' . substr($sql, 5)
                                : $data . ' ' . $sql;
                            $result = pg_query($db, $sql);
                            self::assertNotFalse($result);
                            $schema = [];
                            for ($i = 0; $i < pg_num_fields($result); ++$i) {
                                $schema[] = [pg_field_name($result, $i), pg_field_type_oid($result, $i)];
                            }
                            return [$schema, pg_fetch_all($result)];
                        };
                        $expected = $run($original);
                        $actual = $run($candidate);
                        self::assertSame($expected, $actual, $name . ' ' . $now . ' empty=' . (int) $empty);
                        if (!$empty && $now === '2026-09-30 12:00:00') {
                            if ($name === 'stats') {
                                self::assertSame(['4', '1', '3', '2', '2', '3', '2'], array_values($actual[1][0]));
                            } else {
                                self::assertCount(97, $actual[1]);
                                self::assertSame(['4', '1'], array_slice(array_values($actual[1][96]), 1));
                            }
                        }
                    }
                }
            }
        } finally {
            pg_query($db, 'ROLLBACK');
            pg_close($db);
        }
    }
}
