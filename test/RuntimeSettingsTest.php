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

    public function testSavingAccessHelpTrimsAndEscapesItsValue(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_TABLE_PREFIX", "tce_"); '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return true; } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . 'function F_escape_sql($db, $value) { return str_replace("\'", "\'\'", $value); } '
                    . '$db = new stdClass(); require "tce_functions_openvsosh_settings.php"; '
                    . '$saved = openvsosh_save_access_settings(true, false, "  teacher\'s note  "); '
                    . 'echo json_encode([$saved, end($queries)]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                true,
                "INSERT INTO tce_openvsosh_settings (setting_key, setting_value) "
                    . "VALUES ('access_help','teacher''s note')",
            ],
            json_decode($output, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testTimerTextUsesAHighContrastColor(): void
    {
        self::assertSame('#ffffff', \openvsosh_contrast_text('#000000'));
        self::assertSame('#000000', \openvsosh_contrast_text('#ffffff'));
        self::assertSame('#ffffff', \openvsosh_contrast_text('#b91c1c'));
    }

    public function testRuntimeTimerColorsKeepTheirStringContract(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_TABLE_PREFIX", "tce_"); define("K_DATABASE_TYPE", "UNSUPPORTED"); '
                    . 'define("K_LANGUAGE", "ru"); '
                    . 'define("K_TIMEZONE", "Asia/Yekaterinburg"); '
                    . 'function F_db_query($sql, $db) { return false; } '
                    . '$db = new stdClass(); require "tce_functions_openvsosh_settings.php"; '
                    . '$settings = openvsosh_get_runtime_settings(); '
                    . 'echo json_encode(['
                    . 'gettype($settings["timer_warning_color"]), $settings["timer_warning_color"], '
                    . 'gettype($settings["timer_critical_color"]), $settings["timer_critical_color"]]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('["string","#b45309","string","#b91c1c"]', $output);
    }
}
