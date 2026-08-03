<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_monitoring.php';

final class MonitoringTest extends TestCase
{
    public function testStatusesAreDerivedWithoutAnswerContents(): void
    {
        $now = strtotime('2026-07-27 12:00:00');

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
        // @mago-expect analysis:non-existent-function -- implementation is loaded by the require_once above
        self::assertTrue(\F_tmf_focus_event_is_valid('0123456789abcdef0123456789abcdef'));
        // @mago-expect analysis:non-existent-function -- implementation is loaded by the require_once above
        self::assertFalse(\F_tmf_focus_event_is_valid('0123456789ABCDEF0123456789ABCDEF'));
        // @mago-expect analysis:non-existent-function -- implementation is loaded by the require_once above
        self::assertFalse(\F_tmf_focus_event_is_valid('../0123456789abcdef0123456789abcdef'));
    }
}
