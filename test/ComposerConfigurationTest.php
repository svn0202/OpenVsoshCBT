<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class ComposerConfigurationTest extends TestCase
{
    public function testUnitTestScriptDisablesOnlyItsComposerProcessTimeout(): void
    {
        $contents = file_get_contents(dirname(__DIR__) . '/composer.json');
        self::assertIsString($contents);

        /** @var array{config:array<string,mixed>,scripts:array{test:mixed}} $composer */
        $composer = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('process-timeout', $composer['config']);
        self::assertSame(
            [
                'Composer\\Config::disableProcessTimeout',
                '@php vendor/bin/phpunit --stderr --no-coverage --testsuite unit',
            ],
            $composer['scripts']['test'],
        );
    }
}
