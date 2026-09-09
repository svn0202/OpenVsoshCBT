<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__) . '/shared/code/tce_functions_authorization.php';
    }

    public function testRemainingDelayNeverExtendsTheStoredWindow(): void
    {
        $now = strtotime('2026-09-09 12:00:00');
        self::assertIsInt($now);

        self::assertSame(17, \f_login_throttle_remaining('2026-09-09 12:00:17', $now, 16, 3600));
        self::assertSame(0, \f_login_throttle_remaining('2026-09-09 11:59:59', $now, 16, 3600));
        self::assertSame(0, \f_login_throttle_remaining('invalid', $now, 16, 3600));
        self::assertSame(
            0,
            \f_login_throttle_remaining('2026-09-09 13:08:16', $now, 4096, 3600),
            'Legacy 4096-second lockouts must allow a valid login to clear them',
        );
    }

    public function testDelayGrowsOnlyWhenRecordingAnotherFailureAndIsCapped(): void
    {
        self::assertSame(1, \f_login_throttle_next_delay(0, 2, 3600));
        self::assertSame(2, \f_login_throttle_next_delay(1, 2, 3600));
        self::assertSame(2048, \f_login_throttle_next_delay(1024, 2, 3600));
        self::assertSame(3600, \f_login_throttle_next_delay(2048, 2, 3600));
        self::assertSame(3600, \f_login_throttle_next_delay(PHP_INT_MAX, 2, 3600));
    }

    public function testAuthorizationResetsThrottleOnlyAfterSuccessfulLogin(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/shared/code/tce_authorization.php');
        self::assertIsString($source);

        $success = strpos($source, 'if ($logged) {');
        $reset = strpos($source, "'DELETE FROM ' . K_TABLE_SESSIONS", $success === false ? 0 : $success);
        $failure = strpos($source, 'f_login_throttle_next_delay(', $success === false ? 0 : $success);

        self::assertIsInt($success);
        self::assertIsInt($reset);
        self::assertIsInt($failure);
        self::assertLessThan($reset, $success);
        self::assertLessThan($failure, $reset);
    }

    public function testDefaultSessionLifetimeIsTwoHours(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/shared/config.default/tce_config.php');
        self::assertIsString($source);
        self::assertStringContainsString("define('K_SESSION_LIFE', 2 * K_SECONDS_IN_HOUR);", $source);
    }
}
