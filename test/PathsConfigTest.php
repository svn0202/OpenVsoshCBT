<?php

namespace Test;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class PathsConfigTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testEmptyDocumentRootIsDerivedFromScriptFilename(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = '';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/public/index.php';
        $_SERVER['PHP_SELF'] = '/public/index.php';

        require __DIR__ . '/../shared/config.default/tce_paths.php';

        self::assertSame('/var/www', $_SERVER['DOCUMENT_ROOT']);
    }

    #[RunInSeparateProcess]
    public function testEmptyDocumentRootFallsBackToTranslatedPath(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = '';
        unset($_SERVER['SCRIPT_FILENAME']);
        $_SERVER['PATH_TRANSLATED'] = 'C:\\inetpub\\wwwroot\\public\\index.php';
        $_SERVER['PHP_SELF'] = '/public/index.php';

        require __DIR__ . '/../shared/config.default/tce_paths.php';

        self::assertSame('C:/inetpub/wwwroot', $_SERVER['DOCUMENT_ROOT']);
    }

    #[RunInSeparateProcess]
    public function testMissingServerPathsUseLegacyDocumentRootFallback(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = '';
        unset($_SERVER['SCRIPT_FILENAME'], $_SERVER['PATH_TRANSLATED']);
        $_SERVER['PHP_SELF'] = '/public/index.php';

        require __DIR__ . '/../shared/config.default/tce_paths.php';

        self::assertSame('/var/www', $_SERVER['DOCUMENT_ROOT']);
    }
}
