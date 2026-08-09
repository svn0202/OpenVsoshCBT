<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_otp.php';

final class OtpFunctionsTest extends TestCase
{
    public function testRandomKeyUsesSixteenBase32Characters(): void
    {
        $key = \F_getRandomOTPkey();

        self::assertSame(16, strlen($key));
        self::assertMatchesRegularExpression('/^[A-Z2-7]{16}$/', $key);
    }

    public function testBase32DecoderMatchesKnownValue(): void
    {
        self::assertSame('foo', \F_decodeBase32('MZXW6==='));
    }

    public function testOtpMatchesRfc6238Sha1VectorAt59Seconds(): void
    {
        self::assertSame(287082, \F_getOTP('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
    }
}
