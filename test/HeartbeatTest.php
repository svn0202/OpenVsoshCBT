<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class HeartbeatTest extends TestCase
{
    public function testHeartbeatPreservesDatabaseOutcomeResponses(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/public/code/tce_test_heartbeat.php');
        self::assertIsString($source);

        self::assertStringContainsString('$result = F_db_query($sql, $db);', $source);
        self::assertMatchesRegularExpression(
            '/if \(!\$result\) \{\s*F_tmf_heartbeat_json\(500, \[\'status\' => \'error\'\]\);/s',
            $source,
        );
        self::assertMatchesRegularExpression(
            '/if \(F_db_affected_rows\(\$db, \$result\) < 1\) \{\s*'
                . 'F_tmf_heartbeat_json\(409, \[\'status\' => \'closed\'\]\);/s',
            $source,
        );
        self::assertStringContainsString(
            "F_tmf_heartbeat_json(200, ['status' => 'active']);",
            $source,
        );
    }
}
