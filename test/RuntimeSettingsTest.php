<?php

namespace Test;

use PHPUnit\Framework\TestCase;

if (!defined('K_TABLE_PREFIX')) {
    define('K_TABLE_PREFIX', 'tce_');
}

require_once __DIR__ . '/../shared/code/tce_functions_openvsosh_settings.php';

final class RuntimeSettingsTest extends TestCase
{
    public function testDatabaseBackedSettingsReadAndUpdateContractsRemainUnchanged(): void
    {
        $script = <<<'PHP'
define('K_TABLE_PREFIX', 'tce_');
define('K_DATABASE_TYPE', 'MYSQLI');
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [
    'access' => [
        ['setting_key' => 'registration_enabled', 'setting_value' => '1'],
        ['setting_key' => 'access_help', 'setting_value' => 'Read this first'],
    ],
    'single' => [['setting_value' => 'stored-value']],
    'exists' => [['setting_key' => 'site_name']],
];
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = $sql;
    if (str_contains($sql, 'setting_key, setting_value')) {
        return 'access';
    }
    if (str_contains($sql, 'SELECT setting_value')) {
        return 'single';
    }
    if (str_contains($sql, "WHERE setting_key='site_name'")) {
        return 'exists';
    }
    return true;
}
function F_db_fetch_array($result) {
    return is_string($result) ? array_shift($GLOBALS['rows'][$result]) : false;
}
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
$db = new stdClass();
require 'tce_functions_openvsosh_settings.php';
$access = openvsosh_get_access_settings();
$single = openvsosh_get_setting('custom');
$saved = openvsosh_save_setting('site_name', "Teacher's site");
echo json_encode([$access, $single, $saved, end($GLOBALS['queries'])], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [
                    'registration_enabled' => true,
                    'password_reset_enabled' => false,
                    'access_help' => 'Read this first',
                ],
                'stored-value',
                true,
                "UPDATE tce_openvsosh_settings SET setting_value='Teacher''s site' WHERE setting_key='site_name'",
            ],
            json_decode($output, true, flags: JSON_THROW_ON_ERROR),
        );
    }

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

    public function testAccessLabelsFallBackWhenLocalTranslationsAreOutdated(): void
    {
        self::assertSame(
            [
                'access_control' => 'Доступ к платформе',
                'disable_registration' => 'Отключить форму регистрации',
                'disable_registration_hint' =>
                    'Скрывает ссылку и запрещает прямой доступ к самостоятельной регистрации.',
                'disable_password_reset' => 'Отключить форму сброса пароля',
                'disable_password_reset_hint' =>
                    'Скрывает ссылку и запрещает прямой доступ к странице сброса пароля.',
                'access_help' => 'Помощь и инструкция по получению доступа',
                'access_help_hint' =>
                    'Текст будет показан под формой входа. Можно указать контакты и порядок получения учётных данных.',
                'settings_saved' => 'Настройки доступа сохранены.',
                'settings_save_failed' =>
                    'Не удалось сохранить настройки. Проверьте права доступа к базе данных.',
            ],
            \openvsosh_access_labels(['a_meta_charset' => 'UTF-8']),
        );

        self::assertSame(
            'Custom access title',
            \openvsosh_access_labels(['ov_access_control' => 'Custom access title'])['access_control'],
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
