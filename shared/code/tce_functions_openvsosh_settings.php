<?php

//============================================================+
// File name   : tce_functions_openvsosh_settings.php
// Description : Database-backed OpenVsoshCBT instance settings.
// License     : AGPL-3.0-or-later (see LICENSE).
//============================================================+

if (!defined('K_TABLE_OPENVSOSH_SETTINGS')) {
    define('K_TABLE_OPENVSOSH_SETTINGS', K_TABLE_PREFIX . 'openvsosh_settings');
}

/**
 * Return access settings inherited from the current file configuration.
 *
 * These values are used when upgrading an existing installation before the
 * OpenVsoshCBT settings table has been created.
 *
 * @return array{registration_enabled: bool, password_reset_enabled: bool, access_help: string}
 */
function openvsosh_access_setting_defaults(): array
{
    return [
        'registration_enabled' => defined('K_USRREG_ENABLED') && constant('K_USRREG_ENABLED') === true,
        'password_reset_enabled' => defined('K_PASSWORD_RESET') && constant('K_PASSWORD_RESET') === true,
        'access_help' => '',
    ];
}

/**
 * Return access-page labels even when an upgraded installation still uses an
 * older instance-local translation file.
 *
 * @param array<array-key, string> $translations
 * @return array{access_control: string, disable_registration: string,
 *     disable_registration_hint: string, disable_password_reset: string,
 *     disable_password_reset_hint: string, access_help: string, access_help_hint: string,
 *     settings_saved: string, settings_save_failed: string}
 */
function openvsosh_access_labels(array $translations): array
{
    $fallbacks = [
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
        'settings_save_failed' => 'Не удалось сохранить настройки. Проверьте права доступа к базе данных.',
    ];
    foreach ($fallbacks as $key => $fallback) {
        $translated = $translations['ov_' . $key] ?? '';
        if ($translated !== '') {
            $fallbacks[$key] = $translated;
        }
    }
    $settings_saved = $translations['ov_access_settings_saved'] ?? '';
    if ($settings_saved !== '') {
        $fallbacks['settings_saved'] = $settings_saved;
    }
    return $fallbacks;
}

/**
 * Run a database query without exposing an expected migration-time warning.
 *
 * @param string $sql database statement
 * @return mixed database result or false
 */
function openvsosh_silent_query(string $sql): mixed
{
    global $db;

    set_error_handler(static fn(): bool => true);
    try {
        return F_db_query($sql, $db);
    } finally {
        restore_error_handler();
    }
}

/** @return non-empty-array<array-key, mixed>|null */
function openvsosh_settings_row(mixed $row): ?array
{
    return is_array($row) && $row !== [] ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool|string */
function openvsosh_query_result(mixed $result): mixed
{
    if (
        is_bool($result)
        || is_string($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
}

/** @return array<array-key, mixed> */
function openvsosh_settings_array(mixed $value): array
{
    return is_array($value) ? $value : [];
}

/**
 * Create the small settings table for existing installations.
 *
 * Fresh installations receive this table from install/*_db_structure.sql.
 * The runtime check makes the upgrade backwards-compatible without requiring
 * public requests to fail while an administrator is applying the SQL upgrade.
 *
 * @return bool true when the table is available
 */
function openvsosh_ensure_settings_table(): bool
{
    if (openvsosh_silent_query('SELECT setting_key FROM ' . K_TABLE_OPENVSOSH_SETTINGS . ' LIMIT 1')) {
        return true;
    }

    switch (K_DATABASE_TYPE) {
        case 'MYSQL':
        case 'MYSQLI':
            $sql =
                'CREATE TABLE IF NOT EXISTS '
                . K_TABLE_OPENVSOSH_SETTINGS
                . ' (setting_key VARCHAR(64) NOT NULL, setting_value TEXT NOT NULL, PRIMARY KEY (setting_key))'
                . ' ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            break;
        case 'POSTGRESQL':
            $sql =
                'CREATE TABLE IF NOT EXISTS '
                . K_TABLE_OPENVSOSH_SETTINGS
                . ' (setting_key VARCHAR(64) NOT NULL PRIMARY KEY, setting_value TEXT NOT NULL)';
            break;
        case 'ORACLE':
            $sql =
                'CREATE TABLE '
                . K_TABLE_OPENVSOSH_SETTINGS
                . ' (setting_key VARCHAR2(64) NOT NULL, setting_value NCLOB NOT NULL,'
                . ' CONSTRAINT pk_openvsosh_settings PRIMARY KEY (setting_key))';
            break;
        default:
            return false;
    }

    return openvsosh_silent_query($sql) !== false;
}

/**
 * Read the access settings.
 *
 * @return array{registration_enabled: bool, password_reset_enabled: bool, access_help: string}
 */
function openvsosh_get_access_settings(): array
{
    global $db;
    $settings = openvsosh_access_setting_defaults();
    if (!openvsosh_ensure_settings_table()) {
        return $settings;
    }

    $keys = array_keys($settings);
    $quoted_keys = array_map(
        static fn($key): string => "'" . F_escape_sql($db, $key) . "'",
        $keys,
    );
    $sql =
        'SELECT setting_key, setting_value FROM '
        . K_TABLE_OPENVSOSH_SETTINGS
        . ' WHERE setting_key IN ('
        . implode(',', $quoted_keys)
        . ')';
    $result = openvsosh_query_result(openvsosh_silent_query($sql));
    if (!$result) {
        return $settings;
    }

    while (($row = openvsosh_settings_row(F_db_fetch_array($result))) !== null) {
        $key = (string) ($row['setting_key'] ?? '');
        if ($key === 'registration_enabled' || $key === 'password_reset_enabled') {
            $settings[$key] = (string) ($row['setting_value'] ?? '') === '1';
        } elseif ($key === 'access_help') {
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }
    }

    return $settings;
}

/**
 * Store all access settings using portable UPDATE/INSERT statements.
 *
 * @param bool $registration_enabled enable the public self-registration form
 * @param bool $password_reset_enabled enable the public password-reset form
 * @param string $access_help plain-text access instructions shown on the login page
 * @return bool true when every value was stored
 */
function openvsosh_save_access_settings(
    bool $registration_enabled,
    bool $password_reset_enabled,
    string $access_help,
): bool
{
    global $db;
    if (!openvsosh_ensure_settings_table()) {
        return false;
    }

    $values = [
        'registration_enabled' => $registration_enabled ? '1' : '0',
        'password_reset_enabled' => $password_reset_enabled ? '1' : '0',
        'access_help' => trim($access_help),
    ];

    foreach ($values as $key => $value) {
        $escaped_key = F_escape_sql($db, $key);
        $escaped_value = F_escape_sql($db, $value);
        $exists = false;
        $select = openvsosh_query_result(openvsosh_silent_query(
            "SELECT setting_key FROM " . K_TABLE_OPENVSOSH_SETTINGS . " WHERE setting_key='" . $escaped_key . "'",
        ));
        if ($select && F_db_fetch_array($select)) {
            $exists = true;
        }

        $sql = $exists
            ? "UPDATE " . K_TABLE_OPENVSOSH_SETTINGS . " SET setting_value='" . $escaped_value
                . "' WHERE setting_key='" . $escaped_key . "'"
            : "INSERT INTO " . K_TABLE_OPENVSOSH_SETTINGS . " (setting_key, setting_value) VALUES ('"
                . $escaped_key . "','" . $escaped_value . "')";
        if (!openvsosh_silent_query($sql)) {
            return false;
        }
    }

    return true;
}

/**
 * Read one OpenVsoshCBT setting without exposing migration-time warnings.
 */
function openvsosh_get_setting(string $key): ?string
{
    global $db;
    if (!openvsosh_ensure_settings_table()) {
        return null;
    }
    $escaped_key = F_escape_sql($db, $key);
    $result = openvsosh_query_result(openvsosh_silent_query(
        "SELECT setting_value FROM " . K_TABLE_OPENVSOSH_SETTINGS
        . " WHERE setting_key='" . $escaped_key . "'",
    ));
    $row = $result ? openvsosh_settings_row(F_db_fetch_array($result)) : null;
    return $row !== null ? (string) ($row['setting_value'] ?? '') : null;
}

/**
 * Store one OpenVsoshCBT setting using portable UPDATE/INSERT statements.
 */
function openvsosh_save_setting(string $key, string $value): bool
{
    global $db;
    if (!openvsosh_ensure_settings_table()) {
        return false;
    }
    $escaped_key = F_escape_sql($db, $key);
    $escaped_value = F_escape_sql($db, $value);
    $exists = openvsosh_query_result(openvsosh_silent_query(
        "SELECT setting_key FROM " . K_TABLE_OPENVSOSH_SETTINGS
        . " WHERE setting_key='" . $escaped_key . "'",
    ));
    $sql = $exists && F_db_fetch_array($exists)
        ? "UPDATE " . K_TABLE_OPENVSOSH_SETTINGS . " SET setting_value='" . $escaped_value
            . "' WHERE setting_key='" . $escaped_key . "'"
        : "INSERT INTO " . K_TABLE_OPENVSOSH_SETTINGS . " (setting_key,setting_value) VALUES ('"
            . $escaped_key . "','" . $escaped_value . "')";
    return openvsosh_silent_query($sql) !== false;
}

/**
 * @return array{site_name:string,site_description:string,site_contact:string,welcome:string,login_instruction:string}
 */
function openvsosh_get_site_settings(): array
{
    $defaults = [
        'site_name' => 'OpenVsoshCBT',
        'site_description' => 'Платформа олимпиадного тестирования',
        'site_contact' => '',
        'welcome' => '',
        'login_instruction' => '',
    ];
    foreach ($defaults as $key => $default) {
        $value = openvsosh_get_setting($key);
        if ($value !== null) {
            $defaults[$key] = $value;
        }
    }
    return $defaults;
}

/**
 * Validate and save plain-text instance branding.
 *
 * @param array<array-key, mixed> $input
 * @return array{saved:bool,errors:array<int,string>}
 */
function openvsosh_save_site_settings(array $input): array
{
    $limits = [
        'site_name' => 120,
        'site_description' => 500,
        'site_contact' => 250,
        'welcome' => 1000,
        'login_instruction' => 2000,
    ];
    $values = [];
    $errors = [];
    foreach ($limits as $key => $limit) {
        $value = trim((string) ($input[$key] ?? ''));
        if ($key === 'site_name' && $value === '') {
            $errors[] = 'Название площадки обязательно.';
        } elseif (mb_strlen($value) > $limit) {
            $errors[] = 'Поле ' . $key . ' превышает допустимую длину.';
        }
        $values[$key] = $value;
    }
    if ($errors !== []) {
        return ['saved' => false, 'errors' => $errors];
    }
    foreach ($values as $key => $value) {
        if (!openvsosh_save_setting($key, $value)) {
            return ['saved' => false, 'errors' => ['Не удалось сохранить настройки площадки.']];
        }
    }
    return ['saved' => true, 'errors' => []];
}

/**
 * Return administrator-controlled visual preferences from a constrained set.
 *
 * @return array{admin_palette:string,admin_density:string,ui_font:string,
 *     login_background_position:string,login_background_size:string,login_background_overlay:int}
 */
function openvsosh_get_appearance_settings(): array
{
    $overlay = openvsosh_get_setting('login_background_overlay');
    return [
        'admin_palette' => openvsosh_get_setting('admin_palette') ?? 'ocean',
        'admin_density' => openvsosh_get_setting('admin_density') ?? 'comfortable',
        'ui_font' => openvsosh_get_setting('ui_font') ?? 'system',
        'login_background_position' => openvsosh_get_setting('login_background_position') ?? 'center',
        'login_background_size' => openvsosh_get_setting('login_background_size') ?? 'cover',
        'login_background_overlay' => $overlay === null ? 34 : (int) $overlay,
    ];
}

/**
 * Validate and save visual preferences without accepting arbitrary CSS values.
 * Missing fields retain their current values for backwards-compatible posts.
 *
 * @param array<array-key, mixed> $input
 * @return array{saved:bool,errors:array<int,string>}
 */
function openvsosh_save_appearance_settings(array $input): array
{
    $current = openvsosh_get_appearance_settings();
    $values = [
        'admin_palette' => (string) ($input['admin_palette'] ?? $current['admin_palette']),
        'admin_density' => (string) ($input['admin_density'] ?? $current['admin_density']),
        'ui_font' => (string) ($input['ui_font'] ?? $current['ui_font']),
        'login_background_position' => (string) (
            $input['login_background_position'] ?? $current['login_background_position']
        ),
        'login_background_size' => (string) ($input['login_background_size'] ?? $current['login_background_size']),
        'login_background_overlay' => (int) (
            $input['login_background_overlay'] ?? $current['login_background_overlay']
        ),
    ];
    $errors = [];
    if (!in_array($values['admin_palette'], ['ocean', 'slate', 'forest', 'berry'], true)) {
        $errors[] = 'Выбрана недопустимая палитра оформления.';
    }
    if (!in_array($values['admin_density'], ['comfortable', 'compact'], true)) {
        $errors[] = 'Выбрана недопустимая плотность интерфейса.';
    }
    if (!in_array($values['ui_font'], ['system', 'humanist', 'serif'], true)) {
        $errors[] = 'Выбран недопустимый шрифт интерфейса.';
    }
    if (!in_array($values['login_background_position'], ['center', 'top', 'bottom', 'left', 'right'], true)) {
        $errors[] = 'Выбрано недопустимое положение фона.';
    }
    if (!in_array($values['login_background_size'], ['cover', 'contain', 'auto'], true)) {
        $errors[] = 'Выбран недопустимый размер фона.';
    }
    if ($values['login_background_overlay'] < 0 || $values['login_background_overlay'] > 80) {
        $errors[] = 'Затемнение фона должно быть от 0 до 80 процентов.';
    }
    if ($errors !== []) {
        return ['saved' => false, 'errors' => $errors];
    }
    foreach ($values as $key => $value) {
        if (!openvsosh_save_setting($key, (string) $value)) {
            return ['saved' => false, 'errors' => ['Не удалось сохранить настройки оформления.']];
        }
    }
    return ['saved' => true, 'errors' => []];
}

/**
 * @return array{default_language:string,default_timezone:string,timer_warning_seconds:int,
 *     timer_critical_seconds:int,timer_warning_color:string,timer_critical_color:string}
 */
function openvsosh_get_runtime_settings(): array
{
    /** @var string $default_language */
    $default_language = defined('K_LANGUAGE') ? K_LANGUAGE : 'ru';
    /** @var string $default_timezone */
    $default_timezone = defined('K_TIMEZONE') ? K_TIMEZONE : 'UTC';
    /** @var array{default_language:string,default_timezone:string,timer_warning_seconds:int,
     *     timer_critical_seconds:int,timer_warning_color:string,timer_critical_color:string} $defaults
     */
    $defaults = [
        'default_language' => $default_language,
        'default_timezone' => $default_timezone,
        'timer_warning_seconds' => 600,
        'timer_critical_seconds' => 300,
        'timer_warning_color' => '#b45309',
        'timer_critical_color' => '#b91c1c',
    ];
    $language = openvsosh_get_setting('default_language');
    $defaults['default_language'] = $language ?? $defaults['default_language'];
    $timezone = openvsosh_get_setting('default_timezone');
    $defaults['default_timezone'] = $timezone ?? $defaults['default_timezone'];
    $warning_seconds = openvsosh_get_setting('timer_warning_seconds');
    $defaults['timer_warning_seconds'] = $warning_seconds === null
        ? $defaults['timer_warning_seconds']
        : (int) $warning_seconds;
    $critical_seconds = openvsosh_get_setting('timer_critical_seconds');
    $defaults['timer_critical_seconds'] = $critical_seconds === null
        ? $defaults['timer_critical_seconds']
        : (int) $critical_seconds;
    $warning_color = openvsosh_get_setting('timer_warning_color');
    $defaults['timer_warning_color'] = $warning_color ?? $defaults['timer_warning_color'];
    $critical_color = openvsosh_get_setting('timer_critical_color');
    $defaults['timer_critical_color'] = $critical_color ?? $defaults['timer_critical_color'];
    return $defaults;
}

function openvsosh_bootstrap_settings_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'openvsosh-bootstrap.json';
}

/**
 * @param array<array-key, mixed> $input
 * @return array{saved:bool,errors:array<int,string>}
 */
function openvsosh_save_runtime_settings(array $input): array
{
    /** @var string $available_languages */
    $available_languages = K_AVAILABLE_LANGUAGES;
    $decoded_languages = openvsosh_settings_array(
        unserialize($available_languages, ['allowed_classes' => false]),
    );
    $languages = array_keys($decoded_languages);
    $language = (string) ($input['default_language'] ?? '');
    $timezone = (string) ($input['default_timezone'] ?? '');
    $warning = (int) ($input['timer_warning_seconds'] ?? -1);
    $critical = (int) ($input['timer_critical_seconds'] ?? -1);
    $warning_color = strtolower((string) ($input['timer_warning_color'] ?? ''));
    $critical_color = strtolower((string) ($input['timer_critical_color'] ?? ''));
    $errors = [];
    if (!in_array($language, $languages, true)) {
        $errors[] = 'Выбран неподдерживаемый язык.';
    }
    if (!in_array($timezone, timezone_identifiers_list(), true)) {
        $errors[] = 'Выбран неподдерживаемый часовой пояс.';
    }
    if ($warning < 0 || $warning > 86_400 || $critical < 0 || $critical > $warning) {
        $errors[] = 'Пороги таймера должны быть от 0 до 86400 секунд, критический — не больше предупреждения.';
    }
    foreach ([$warning_color, $critical_color] as $color) {
        if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
            $errors[] = 'Цвета таймера должны быть в формате #RRGGBB.';
            break;
        }
    }
    if ($errors !== []) {
        return ['saved' => false, 'errors' => $errors];
    }
    $values = [
        'default_language' => $language,
        'default_timezone' => $timezone,
        'timer_warning_seconds' => (string) $warning,
        'timer_critical_seconds' => (string) $critical,
        'timer_warning_color' => $warning_color,
        'timer_critical_color' => $critical_color,
    ];
    foreach ($values as $key => $value) {
        if (!openvsosh_save_setting($key, $value)) {
            return ['saved' => false, 'errors' => ['Не удалось сохранить параметры среды.']];
        }
    }
    $path = openvsosh_bootstrap_settings_path();
    // @mago-expect analysis:unhandled-thrown-type -- entropy failure retains the existing exception contract
    $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $json = json_encode(
        ['language' => $language, 'timezone' => $timezone],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    if (
        file_put_contents($temporary, $json . "\n", LOCK_EX) === false
        || !chmod($temporary, 0o640)
        || !rename($temporary, $path)
    ) {
        if (is_file($temporary)) {
            unlink($temporary);
        }
        return ['saved' => false, 'errors' => ['Не удалось обновить загрузочные настройки языка и времени.']];
    }
    return ['saved' => true, 'errors' => []];
}

/**
 * Choose black or white timer text with WCAG contrast of at least 4.5:1.
 */
function openvsosh_contrast_text(string $background): string
{
    $rgb = sscanf(ltrim($background, '#'), '%02x%02x%02x');
    if (!is_array($rgb) || count($rgb) !== 3) {
        return '#000000';
    }
    $channels = array_map(static function (int $channel): float {
        $value = $channel / 255;
        return $value <= 0.040_45 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, $rgb);
    /** @var array{float, float, float} $channels */
    $luminance = (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    $white_contrast = 1.05 / ($luminance + 0.05);
    return $white_contrast >= 4.5 ? '#ffffff' : '#000000';
}

/**
 * Return the instance-local key used to authenticate offline packages.
 */
function openvsosh_get_offline_package_secret(): string
{
    $secret = openvsosh_get_setting('offline_package_secret');
    if (is_string($secret) && preg_match('/^[a-f0-9]{64}$/', $secret) === 1) {
        return $secret;
    }
    // @mago-expect analysis:unhandled-thrown-type -- entropy failure retains the existing exception contract
    $secret = bin2hex(random_bytes(32));
    if (!openvsosh_save_setting('offline_package_secret', $secret)) {
        // @mago-expect analysis:unhandled-thrown-type -- callers already receive persistence failures
        throw new RuntimeException('Unable to persist the offline package secret.');
    }
    $persisted = openvsosh_get_setting('offline_package_secret');
    if (!is_string($persisted) || preg_match('/^[a-f0-9]{64}$/', $persisted) !== 1) {
        throw new RuntimeException('Unable to read the offline package secret.');
    }
    return $persisted;
}
