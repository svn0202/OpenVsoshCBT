<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_otp.php';

final class OtpFunctionsTest extends TestCase
{
    /** @throws \Random\RandomException */
    public function testRandomKeyUsesSixteenBase32Characters(): void
    {
        $key = \f_get_random_otp_key();

        self::assertSame(16, strlen($key));
        self::assertMatchesRegularExpression('/^[A-Z2-7]{16}$/', $key);
    }

    public function testBase32DecoderMatchesKnownValue(): void
    {
        self::assertSame('foo', \f_decode_base32('MZXW6==='));
    }

    public function testOtpMatchesRfc6238Sha1VectorAt59Seconds(): void
    {
        self::assertSame(287082, \f_get_otp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
    }
}
