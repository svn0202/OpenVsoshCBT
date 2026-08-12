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
        self::assertStringContainsString('--stdin-input "$driver_file"', $analysis);
        self::assertStringNotContainsString('analyze "$driver_file" --ignore-baseline', $analysis);
    }
}
