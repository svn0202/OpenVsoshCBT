<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../admin/code/tce_functions_filemanager.php';

final class FileManagerFunctionsTest extends TestCase
{
    public function testFormatsFileSizesUsingLegacyUnitsAndRounding(): void
    {
        self::assertSame('0', F_formatFileSize(0));
        self::assertSame('1 B ', F_formatFileSize(1));
        self::assertSame('2 KB', F_formatFileSize(1536));
        self::assertSame('1 MB', F_formatFileSize(1024 * 1024));
        self::assertSame('1 KB', F_formatFileSize('1024'));
    }
}
