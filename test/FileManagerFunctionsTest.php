<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../admin/code/tce_functions_filemanager.php';

final class FileManagerFunctionsTest extends TestCase
{
    public function testReturnsFileInformationWithStableScalarFields(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "tce_functions_filemanager.php"; $info = f_get_file_info($argv[1]); echo implode("\n", ['
                    . '$info["basename"], $info["extension"], $info["filename"], $info["dir"] ? "1" : "0", '
                    . '(string) $info["size"], $info["link"] ? "1" : "0", $info["aperms"]]);',
                __FILE__,
            ],
            __DIR__ . '/../admin/code',
        );
        self::assertSame(0, $status);

        $fields = explode("\n", $output);
        self::assertCount(7, $fields);
        self::assertSame(
            [
                'FileManagerFunctionsTest.php',
                'php',
                'FileManagerFunctionsTest',
                '0',
                (string) filesize(__FILE__),
                '0',
            ],
            array_slice($fields, 0, 6),
        );
        self::assertStringStartsWith('-', $fields[6] ?? '');
    }

    public function testFormatsFileSizesUsingLegacyUnitsAndRounding(): void
    {
        self::assertSame('0', f_format_file_size(0));
        self::assertSame('1 B ', f_format_file_size(1));
        self::assertSame('2 KB', f_format_file_size(1536));
        self::assertSame('1 MB', f_format_file_size(1024 * 1024));
        self::assertSame('1 KB', f_format_file_size('1024'));
    }
}
