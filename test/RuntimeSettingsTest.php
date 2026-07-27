<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_openvsosh_settings.php';

final class RuntimeSettingsTest extends TestCase
{
    public function testTimerTextUsesAHighContrastColor(): void
    {
        self::assertSame('#ffffff', \openvsosh_contrast_text('#000000'));
        self::assertSame('#000000', \openvsosh_contrast_text('#ffffff'));
        self::assertSame('#ffffff', \openvsosh_contrast_text('#b91c1c'));
    }
}
