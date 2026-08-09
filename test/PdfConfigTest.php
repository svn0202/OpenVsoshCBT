<?php

namespace Test;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class PdfConfigTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAllowedPathsContainOnlyExistingDirectories(): void
    {
        define('K_PATH_MAIN', dirname(__DIR__) . '/');
        define('K_PATH_FONTS', dirname(__DIR__) . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts');
        require __DIR__ . '/../shared/config.default/tce_pdf.php';

        $serialized_paths = (static fn(mixed $value): string => is_string($value) ? $value : '')(
            K_PDF_ALLOWED_PATHS,
        );
        $expected_paths = array_values(array_filter([
            realpath(K_PATH_MAIN . 'images'),
            realpath(K_PATH_MAIN . 'cache'),
            realpath(K_PATH_FONTS),
            realpath(K_PATH_FONTS),
            realpath(sys_get_temp_dir()),
        ]));
        self::assertSame(serialize($expected_paths), $serialized_paths);
    }
}
