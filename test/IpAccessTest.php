<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_test.php';

final class IpAccessTest extends TestCase
{
    public function testSingleWildcardDisablesRestrictionForIpv4AndIpv6(): void
    {
        self::assertTrue(\f_is_valid_ip('192.0.2.10', '*'));
        self::assertTrue(\f_is_valid_ip('2001:db8::10', '*'));
    }

    public function testLegacyIpv4WildcardDoesNotAccidentallyAllowIpv6(): void
    {
        self::assertTrue(\f_is_valid_ip('192.0.2.10', '*.*.*.*'));
        self::assertFalse(\f_is_valid_ip('2001:db8::10', '*.*.*.*'));
    }

    public function testExactRangeAndEmptyIpRulesRemainUnchanged(): void
    {
        self::assertFalse(\f_is_valid_ip('', '192.0.2.1'));
        self::assertTrue(\f_is_valid_ip('2001:db8::10', '2001:DB8::10'));
        self::assertTrue(\f_is_valid_ip('192.0.2.10', '192.0.2.1-192.0.2.20'));
        self::assertFalse(\f_is_valid_ip('192.0.2.21', '192.0.2.1-192.0.2.20'));
    }
}
