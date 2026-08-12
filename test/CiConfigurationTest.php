<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;

final class CiConfigurationTest extends TestCase
{
    public function testMakeLintUsesIsolatedDatabaseDriverAnalysis(): void
    {
        $makefile = (string) file_get_contents(__DIR__ . '/../Makefile');
        $analysis = (string) file_get_contents(__DIR__ . '/../tools/analyse-src.sh');

        self::assertStringContainsString("\tbash tools/analyse-src.sh", $makefile);
        self::assertStringContainsString('test: ensuretarget preparetestconfig', $makefile);
        self::assertStringContainsString('tools/prepare-test-config.php', $makefile);
        self::assertStringContainsString('TCEXAM_TEST_MODE=1 ./vendor/bin/phpunit', $makefile);
        self::assertStringContainsString('--stdin-input "$driver_file"', $analysis);
        self::assertStringNotContainsString('analyze "$driver_file" --ignore-baseline', $analysis);
    }
}
