<?php

namespace Test;

use PHPUnit\Framework\TestCase;

if (!defined('K_TABLE_PREFIX')) {
    define('K_TABLE_PREFIX', 'tce_');
}

require_once __DIR__ . '/../shared/code/tce_functions_openvsosh_settings.php';

final class RuntimeSettingsTest extends TestCase
{
    public function testAccessSettingDefaultsReflectFileConfiguration(): void
    {
        self::assertSame(
            [
                'registration_enabled' => false,
                'password_reset_enabled' => false,
                'access_help' => '',
            ],
            \openvsosh_access_setting_defaults(),
        );
    }

    public function testTimerTextUsesAHighContrastColor(): void
    {
        self::assertSame('#ffffff', \openvsosh_contrast_text('#000000'));
        self::assertSame('#000000', \openvsosh_contrast_text('#ffffff'));
        self::assertSame('#ffffff', \openvsosh_contrast_text('#b91c1c'));
    }
}
