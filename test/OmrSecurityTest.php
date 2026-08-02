<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class OmrSecurityTest extends TestCase
{
    public function testValidOmrPayloadRoundTrips(): void
    {
        $payload = [42, [100, [1 => 501, 2 => 502]], [101, []]];

        $this->assertSame($payload, \F_decodeOMRTestData(\F_encodeOMRTestData($payload)));
    }

    public function testOmrPayloadRejectsObjects(): void
    {
        $payload = urlencode(base64_encode(gzcompress(serialize(new \stdClass()), 9)));

        $this->assertFalse(\F_decodeOMRTestData($payload));
    }

    public function testOmrPayloadRejectsInvalidStructureAndOversizedInput(): void
    {
        $invalid = urlencode(base64_encode(gzcompress(serialize([42, ['bad']]), 9)));

        $this->assertFalse(\F_decodeOMRTestData($invalid));
        $this->assertFalse(\F_decodeOMRTestData(str_repeat('A', 1_048_577)));
    }
}
