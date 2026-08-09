<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class ErrorMessageFunctionsTest extends TestCase
{
    public function testErrorHandlerReturnsNothingWhenReportingIsDisabled(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_ERROR_TYPES", E_ALL); require "tce_functions_errmsg.php"; '
                    . 'error_reporting(0); '
                    . 'echo json_encode(F_error_handler(E_WARNING, "ignored", __FILE__, __LINE__));',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame('null', $output);
    }

    public function testFileExistsWrapperHandlesLocalAndUnsupportedPaths(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_ERROR_TYPES", E_ALL); require "tce_functions_errmsg.php"; '
                    . 'echo json_encode(['
                    . 'F_file_exists($argv[1]), '
                    . 'F_file_exists($argv[1] . ".missing"), '
                    . 'F_file_exists("ftp://example.test/file"), '
                    . 'F_file_exists(""), '
                    . 'F_url_exists("http://127.0.0.1:1")]);',
                __FILE__,
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame('[true,false,false,false,false]', $output);
    }
}
