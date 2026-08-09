<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class OmrSecurityTest extends TestCase
{
    public function testQrDecoderRejectsEmptyImagePathBeforeRunningExternalTool(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_omr.php"; '
                    . 'echo json_encode(f_decode_omr_test_data_qr_code(""));',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }

    public function testValidOmrPayloadRoundTrips(): void
    {
        $payload = [42, [100, [1 => 501, 2 => 502]], [101, []]];

        $this->assertSame($payload, \f_decode_omr_test_data(\f_encode_omr_test_data($payload)));
    }

    public function testOmrPayloadRejectsObjects(): void
    {
        $compressed = gzcompress(serialize(new \stdClass()), 9);
        if ($compressed === false) {
            self::fail('Unable to compress the invalid OMR fixture.');
        }
        $payload = urlencode(base64_encode($compressed));

        $this->assertFalse(\f_decode_omr_test_data($payload));
    }

    public function testOmrPayloadRejectsInvalidStructureAndOversizedInput(): void
    {
        $compressed = gzcompress(serialize([42, ['bad']]), 9);
        if ($compressed === false) {
            self::fail('Unable to compress the malformed OMR fixture.');
        }
        $invalid = urlencode(base64_encode($compressed));

        $this->assertFalse(\f_decode_omr_test_data($invalid));
        $this->assertFalse(\f_decode_omr_test_data(str_repeat('A', 1_048_577)));
    }
}
