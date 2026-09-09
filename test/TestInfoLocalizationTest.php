<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestInfoLocalizationTest extends TestCase
{
    public function testUnlimitedRepeatCountIsLocalized(): void
    {
        $tmx = __DIR__ . '/../shared/config.default/lang/language_tmx.xml';

        $english = (new \TMXResourceBundle($tmx, 'EN', ''))->getResource();
        $russian = (new \TMXResourceBundle($tmx, 'RU', ''))->getResource();

        self::assertSame('unlimited', $english['w_unlimited'] ?? null);
        self::assertSame('без ограничений', $russian['w_unlimited'] ?? null);
    }

    public function testTestInfoUsesLocalizedUnlimitedLabel(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../shared/code/tce_functions_test.php');

        self::assertStringContainsString("\$l['w_unlimited'] ?? 'без ограничений'", $source);
        self::assertStringNotContainsString("' ( unlimited )'", $source);
    }
}
