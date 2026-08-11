<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class OmrSecurityTest extends TestCase
{
    public function testMissingOmrDirectoryReturnsFalseWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_omr.php"; '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$handle = f_omr_open_dir_silently(K_PATH_CACHE . "missing-omr-directory/"); '
                    . 'restore_error_handler(); echo json_encode([$handle, $warnings]);',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('[false,[]]', $output);
    }

    public function testOmrPageDecoderStopsWhenBarcodeHasNoQuestionNumber(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_OMR_PATH_ZBARIMG", "/usr/bin/zbarimg"); '
                    . '$GLOBALS["commands"] = []; '
                    . 'function exec($command) { $GLOBALS["commands"][] = $command; return "0"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_decode_omr_page)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$result = $qualified("scan file.png"); '
                    . 'echo json_encode([$result, $GLOBALS["commands"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_omr.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [false, ["/usr/bin/zbarimg --raw -Sdisable -Scode128.enable -q 'scan file.png'"]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testOmrImportRejectsMismatchedQuestionAndAnswerCountsBeforeDatabaseAccess(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_import_omr_test_data)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strlen($source); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode($qualified(7, "2026-08-10 12:00:00", '
                    . '[42, [100, [1 => 501]], [101, [1 => 502]]], [], false));',
                dirname(__DIR__) . '/admin/code/tce_functions_omr.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('false', $output);
    }

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

    public function testOmrPayloadRejectsNonPositiveQuestionAndAnswerIdentifiers(): void
    {
        self::assertFalse(\f_decode_omr_test_data(\f_encode_omr_test_data([42, [0, [1 => 501]]])));
        self::assertFalse(\f_decode_omr_test_data(\f_encode_omr_test_data([42, [100, [0 => 501]]])));
        self::assertFalse(\f_decode_omr_test_data(\f_encode_omr_test_data([42, [100, [1 => -1]]])));
    }
}
