<?php

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMINISTRATOR;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_onboarding.php';
require_once '../../shared/config/tce_user_registration.php';
require_once '../../shared/code/tce_functions_openvsosh_settings.php';
require_once '../../shared/code/tce_functions_site_assets.php';

/**
 * @var array{
 *     a_meta_charset:string,
 *     ov_instance_settings?:string,
 *     ov_access_settings_saved:string,
 *     ov_settings_save_failed:string,
 *     ov_access_control:string,
 *     ov_disable_registration:string,
 *     ov_disable_registration_hint:string,
 *     ov_disable_password_reset:string,
 *     ov_disable_password_reset_hint:string,
 *     ov_access_help:string,
 *     ov_access_help_hint:string,
 *     ov_save?:string
 * } $l
 */
/** @var mixed $db */
/** @var array{REQUEST_METHOD:string,SCRIPT_NAME:string} $server */
$server = $_SERVER;
/**
 * @var array{
 *     save_onboarding?:mixed,
 *     save_site?:mixed,
 *     save_access?:mixed,
 *     csrf_token?:mixed,
 *     instruction_test_id?:mixed,
 *     demo_test_id?:mixed,
 *     disable_registration?:mixed,
 *     disable_password_reset?:mixed,
 *     access_help?:string,
 *     ...<string,mixed>
 * } $post
 */
$post = $_POST;
/** @var array<string,array<string,mixed>> $files */
$files = $_FILES;
/** @return array<array-key,mixed>|null */
$normalize_array = static fn (mixed $value): ?array => is_array($value) ? $value : null;
$normalize_query_result = static function (mixed $result): mixed {
    if (
        is_bool($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
};

$settings_charset = $l['a_meta_charset'];
$thispage_title = $l['ov_instance_settings'] ?? 'Настройки площадки';
require_once 'tce_page_header.php';

$config = f_get_onboarding_config();
$access_config = openvsosh_get_access_settings();
$site_config = openvsosh_get_site_settings();
$runtime_config = openvsosh_get_runtime_settings();
$appearance_config = openvsosh_get_appearance_settings();
if ($server['REQUEST_METHOD'] === 'POST' && isset($post['save_onboarding'])) {
    if (empty($post['csrf_token']) || !is_string($post['csrf_token']) || !check_csrf_token($post['csrf_token'])) {
        exit();
    }
    $instruction_id = max(0, (int) ($post['instruction_test_id'] ?? 0));
    $demo_id = max(0, (int) ($post['demo_test_id'] ?? 0));
    if ($instruction_id > 0 && $instruction_id === $demo_id) {
        F_print_error('WARNING', 'Для инструкции и демо-теста выберите разные тесты.');
    } elseif (f_save_onboarding_config($instruction_id, $demo_id)) {
        $config = f_get_onboarding_config();
        F_print_error('MESSAGE', 'Настройки вводных тестов сохранены.');
    } else {
        F_print_error('ERROR', 'Не удалось сохранить настройки. Проверьте права на shared/config.', false);
    }
}

if ($server['REQUEST_METHOD'] === 'POST' && isset($post['save_site'])) {
    if (empty($post['csrf_token']) || !is_string($post['csrf_token']) || !check_csrf_token($post['csrf_token'])) {
        exit();
    }
    $site_result = openvsosh_save_site_settings($post);
    $runtime_result = openvsosh_save_runtime_settings($post);
    $appearance_result = openvsosh_save_appearance_settings($post);
    if ($site_result['saved'] && $runtime_result['saved'] && $appearance_result['saved']) {
        $asset_errors = [];
        foreach (['logo', 'background'] as $asset_type) {
            $uploaded_file = $files['site_' . $asset_type] ?? null;
            if (is_array($uploaded_file)) {
                $asset_result = openvsosh_store_site_asset(
                    $asset_type,
                    $uploaded_file,
                );
                if (!$asset_result['stored']) {
                    $asset_errors[] = $asset_result['message'];
                }
            }
        }
        $site_config = openvsosh_get_site_settings();
        $runtime_config = openvsosh_get_runtime_settings();
        $appearance_config = openvsosh_get_appearance_settings();
        if ($asset_errors === []) {
            F_print_error('MESSAGE', 'Настройки площадки сохранены.');
        } else {
            F_print_error(
                'WARNING',
                htmlspecialchars(implode(' ', $asset_errors), ENT_QUOTES, $l['a_meta_charset']),
            );
        }
    } else {
        F_print_error(
            'WARNING',
            htmlspecialchars(
                implode(' ', array_merge(
                    $site_result['errors'],
                    $runtime_result['errors'],
                    $appearance_result['errors'],
                )),
                ENT_QUOTES,
                $l['a_meta_charset'],
            ),
        );
    }
}

if ($server['REQUEST_METHOD'] === 'POST' && isset($post['save_access'])) {
    if (empty($post['csrf_token']) || !is_string($post['csrf_token']) || !check_csrf_token($post['csrf_token'])) {
        exit();
    }

    $registration_enabled = !isset($post['disable_registration']);
    $password_reset_enabled = !isset($post['disable_password_reset']);
    $access_help = trim($post['access_help'] ?? '');
    if (mb_strlen($access_help) > 5000) {
        $access_help = mb_substr($access_help, 0, 5000);
    }

    if (openvsosh_save_access_settings($registration_enabled, $password_reset_enabled, $access_help)) {
        $access_config = openvsosh_get_access_settings();
        F_print_error('MESSAGE', $l['ov_access_settings_saved']);
    } else {
        F_print_error('ERROR', $l['ov_settings_save_failed'], false);
    }
}

$tests = [];
$sql = 'SELECT test_id, test_name, test_begin_time, test_end_time FROM ' . K_TABLE_TESTS . ' ORDER BY test_name';
if ($r = $normalize_query_result(F_db_query($sql, $db))) {
    while ($test = $normalize_array(F_db_fetch_array($r))) {
        $tests[] = $test;
    }
}

/** @param list<mixed> $tests */
function f_onboarding_test_select(string $name, int $selected, array $tests, string $charset): void
{
    echo '<select name="' . $name . '" id="' . $name . '">' . K_NEWLINE;
    echo '<option value="0">— не назначен —</option>' . K_NEWLINE;
    $valid_tests = array_filter($tests, 'is_array');
    foreach ($valid_tests as $test) {
        $id = (int) ($test['test_id'] ?? 0);
        echo '<option value="' . $id . '"' . ($id === (int) $selected ? ' selected="selected"' : '') . '>';
        echo htmlspecialchars((string) ($test['test_name'] ?? ''), ENT_QUOTES, $charset);
        echo '</option>' . K_NEWLINE;
    }
    echo '</select>' . K_NEWLINE;
}

echo '<div class="container onboarding-admin settings-console">' . K_NEWLINE;
echo '<section class="settings-intro"><div><span>Центр управления</span>'
    . '<h2>Внешний вид и поведение площадки</h2>'
    . '<p>Настройте узнаваемое оформление, экран входа и основные параметры без правки файлов.</p></div>'
    . '<a href="../../public/code/index.php">Открыть площадку <span aria-hidden="true">↗</span></a></section>' . K_NEWLINE;
echo '<form class="settings-form" action="' . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
    . '" method="post" enctype="multipart/form-data">' . K_NEWLINE;
echo '<fieldset class="settings-card"><legend><span aria-hidden="true">01</span> Фирменное оформление</legend>' . K_NEWLINE;
$site_fields = [
    'site_name' => ['Название площадки', 120],
    'site_description' => ['Краткое описание', 500],
    'site_contact' => ['Контакт', 250],
    'welcome' => ['Приветствие участника', 1000],
    'login_instruction' => ['Дополнительная инструкция входа', 2000],
];
foreach ($site_fields as $key => [$label, $limit]) {
    echo '<div class="row"><label for="' . $key . '">' . $label . '</label>';
    if ($limit > 250) {
        echo '<textarea name="' . $key . '" id="' . $key . '" maxlength="' . $limit . '">'
            . htmlspecialchars($site_config[$key] ?? '', ENT_QUOTES, $l['a_meta_charset']) . '</textarea>';
    } else {
        echo '<input type="text" name="' . $key . '" id="' . $key . '" maxlength="' . $limit
            . '" value="' . htmlspecialchars($site_config[$key] ?? '', ENT_QUOTES, $l['a_meta_charset']) . '" />';
    }
    echo '</div>' . K_NEWLINE;
}
echo '<div class="settings-preview" id="appearance-preview" data-palette="'
    . htmlspecialchars($appearance_config['admin_palette'], ENT_QUOTES) . '">' . K_NEWLINE;
echo '<div class="settings-preview-sidebar"><span></span><i></i><i></i><i></i></div>' . K_NEWLINE;
echo '<div class="settings-preview-stage">' . K_NEWLINE;
$preview_background_fit = $appearance_config['login_background_size'] === 'auto'
    ? 'none'
    : $appearance_config['login_background_size'];
echo '<div class="settings-preview-login" style="--preview-overlay:'
    . ((int) $appearance_config['login_background_overlay'] / 100) . ';--preview-position:'
    . htmlspecialchars($appearance_config['login_background_position'], ENT_QUOTES) . ';--preview-size:'
    . htmlspecialchars($preview_background_fit, ENT_QUOTES) . '">' . K_NEWLINE;
if (openvsosh_site_asset_metadata('background')) {
    echo '<img src="../../public/code/tce_site_asset.php?type=background" alt="" />' . K_NEWLINE;
}
echo '<div><strong>' . htmlspecialchars($site_config['site_name'], ENT_QUOTES, $l['a_meta_charset'])
    . '</strong><span>Предпросмотр экрана входа</span></div></div></div></div>' . K_NEWLINE;
echo '<div class="settings-choice-grid">' . K_NEWLINE;
echo '<div class="row"><label for="admin_palette">Палитра админки</label><select name="admin_palette" id="admin_palette">';
foreach (['ocean' => 'Северный океан', 'slate' => 'Графит', 'forest' => 'Лес', 'berry' => 'Ягода'] as $value => $label) {
    echo '<option value="' . $value . '"' . ($appearance_config['admin_palette'] === $value ? ' selected="selected"' : '')
        . '>' . $label . '</option>';
}
echo '</select><span class="form-help">Цвет меню, акцентов и кнопок.</span></div>' . K_NEWLINE;
echo '<div class="row"><label for="ui_font">Шрифт интерфейса</label><select name="ui_font" id="ui_font">';
foreach (['system' => 'Системный', 'humanist' => 'Гуманистический', 'serif' => 'Классический с засечками'] as $value => $label) {
    echo '<option value="' . $value . '"' . ($appearance_config['ui_font'] === $value ? ' selected="selected"' : '')
        . '>' . $label . '</option>';
}
echo '</select><span class="form-help">Применяется в админке и на экране входа.</span></div>' . K_NEWLINE;
echo '<div class="row"><label for="admin_density">Плотность форм</label><select name="admin_density" id="admin_density">';
foreach (['comfortable' => 'Комфортная', 'compact' => 'Компактная'] as $value => $label) {
    echo '<option value="' . $value . '"' . ($appearance_config['admin_density'] === $value ? ' selected="selected"' : '')
        . '>' . $label . '</option>';
}
echo '</select><span class="form-help">Компактный режим показывает больше полей на экране.</span></div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '<div class="row"><label for="site_logo">Основной логотип</label>'
    . '<input type="file" name="site_logo" id="site_logo" accept="image/jpeg,image/png" />'
    . '<span class="form-help">JPEG/PNG, 32–8192 px, до 5 МБ.</span></div>' . K_NEWLINE;
echo '<div class="row"><label for="site_background">Фон страницы входа</label>'
    . '<input type="file" name="site_background" id="site_background" accept="image/jpeg,image/png" />'
    . '<span class="form-help">JPEG/PNG, 32–8192 px, до 5 МБ.</span></div>' . K_NEWLINE;
echo '<div class="settings-choice-grid settings-background-controls">' . K_NEWLINE;
echo '<div class="row"><label for="login_background_position">Положение фона</label><select name="login_background_position" id="login_background_position">';
foreach (['center' => 'По центру', 'top' => 'Сверху', 'bottom' => 'Снизу', 'left' => 'Слева', 'right' => 'Справа'] as $value => $label) {
    echo '<option value="' . $value . '"' . ($appearance_config['login_background_position'] === $value ? ' selected="selected"' : '')
        . '>' . $label . '</option>';
}
echo '</select></div>' . K_NEWLINE;
echo '<div class="row"><label for="login_background_size">Масштаб фона</label><select name="login_background_size" id="login_background_size">';
foreach (['cover' => 'Заполнить экран', 'contain' => 'Показать целиком', 'auto' => 'Исходный размер'] as $value => $label) {
    echo '<option value="' . $value . '"' . ($appearance_config['login_background_size'] === $value ? ' selected="selected"' : '')
        . '>' . $label . '</option>';
}
echo '</select></div>' . K_NEWLINE;
echo '<div class="row range-row"><label for="login_background_overlay">Затемнение фона</label>'
    . '<input type="range" name="login_background_overlay" id="login_background_overlay" min="0" max="80" step="1" value="'
    . (int) $appearance_config['login_background_overlay'] . '" />'
    . '<output for="login_background_overlay">' . (int) $appearance_config['login_background_overlay'] . '%</output></div>' . K_NEWLINE;
echo '</div></fieldset>' . K_NEWLINE;
echo '<fieldset class="settings-card"><legend><span aria-hidden="true">02</span> Язык, время и предупреждения</legend>' . K_NEWLINE;
echo '<div class="row"><label for="default_language">Язык по умолчанию</label>'
    . '<select name="default_language" id="default_language">';
$raw_languages = $normalize_array(unserialize((string) K_AVAILABLE_LANGUAGES, ['allowed_classes' => false])) ?? [];
$available_languages = [];
foreach (array_keys($raw_languages) as $code) {
    if (!is_string($code) || !is_string($raw_languages[$code] ?? null)) {
        continue;
    }
    $available_languages[$code] = $raw_languages[$code];
}
foreach ($available_languages as $code => $name) {
    echo '<option value="' . htmlspecialchars($code, ENT_QUOTES) . '"'
        . ($runtime_config['default_language'] === $code ? ' selected="selected"' : '')
        . '>' . htmlspecialchars($name, ENT_QUOTES, $l['a_meta_charset']) . '</option>';
}
echo '</select></div>' . K_NEWLINE;
echo '<div class="row"><label for="default_timezone">Часовой пояс</label>'
    . '<input type="text" name="default_timezone" id="default_timezone" list="timezone-list" maxlength="64" value="'
    . htmlspecialchars($runtime_config['default_timezone'], ENT_QUOTES, $l['a_meta_charset']) . '" />'
    . '<datalist id="timezone-list">';
foreach (timezone_identifiers_list() as $timezone) {
    echo '<option value="' . htmlspecialchars($timezone, ENT_QUOTES) . '"></option>';
}
echo '</datalist></div>' . K_NEWLINE;
echo '<div class="row"><label for="timer_warning_seconds">Предупреждение таймера, сек.</label>'
    . '<input type="number" name="timer_warning_seconds" id="timer_warning_seconds" min="0" max="86400" value="'
    . (int) $runtime_config['timer_warning_seconds'] . '" /></div>' . K_NEWLINE;
echo '<div class="row"><label for="timer_critical_seconds">Критический порог, сек.</label>'
    . '<input type="number" name="timer_critical_seconds" id="timer_critical_seconds" min="0" max="86400" value="'
    . (int) $runtime_config['timer_critical_seconds'] . '" /></div>' . K_NEWLINE;
echo '<div class="row"><label for="timer_warning_color">Цвет предупреждения</label>'
    . '<input type="color" name="timer_warning_color" id="timer_warning_color" value="'
    . htmlspecialchars($runtime_config['timer_warning_color'], ENT_QUOTES) . '" /></div>' . K_NEWLINE;
echo '<div class="row"><label for="timer_critical_color">Критический цвет</label>'
    . '<input type="color" name="timer_critical_color" id="timer_critical_color" value="'
    . htmlspecialchars($runtime_config['timer_critical_color'], ENT_QUOTES) . '" /></div>' . K_NEWLINE;
echo '</fieldset><div class="onboarding-admin-actions">'
    . '<button type="submit" name="save_site" value="1" class="button">Сохранить оформление</button></div>'
    . f_get_csrf_token_field() . K_NEWLINE . '</form>' . K_NEWLINE;
echo '<form class="settings-form" action="' . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES) . '" method="post">' . K_NEWLINE;
echo '<fieldset class="settings-card"><legend><span aria-hidden="true">03</span> '
    . htmlspecialchars($l['ov_access_control'], ENT_QUOTES, $l['a_meta_charset'])
    . '</legend>' . K_NEWLINE;
echo '<div class="row check-row"><input type="checkbox" name="disable_registration" id="disable_registration" value="1"'
    . (!$access_config['registration_enabled'] ? ' checked="checked"' : '') . ' />' . K_NEWLINE;
echo '<div><label for="disable_registration">'
    . htmlspecialchars($l['ov_disable_registration'], ENT_QUOTES, $l['a_meta_charset'])
    . '</label><span class="form-help">'
    . htmlspecialchars($l['ov_disable_registration_hint'], ENT_QUOTES, $l['a_meta_charset'])
    . '</span></div></div>' . K_NEWLINE;
echo '<div class="row check-row"><input type="checkbox" name="disable_password_reset" id="disable_password_reset" value="1"'
    . (!$access_config['password_reset_enabled'] ? ' checked="checked"' : '') . ' />' . K_NEWLINE;
echo '<div><label for="disable_password_reset">'
    . htmlspecialchars($l['ov_disable_password_reset'], ENT_QUOTES, $l['a_meta_charset'])
    . '</label><span class="form-help">'
    . htmlspecialchars($l['ov_disable_password_reset_hint'], ENT_QUOTES, $l['a_meta_charset'])
    . '</span></div></div>' . K_NEWLINE;
echo '<div class="row"><label for="access_help">'
    . htmlspecialchars($l['ov_access_help'], ENT_QUOTES, $l['a_meta_charset'])
    . '</label><textarea name="access_help" id="access_help" maxlength="5000">'
    . htmlspecialchars($access_config['access_help'], ENT_QUOTES, $l['a_meta_charset'])
    . '</textarea><span class="form-help">'
    . htmlspecialchars($l['ov_access_help_hint'], ENT_QUOTES, $l['a_meta_charset'])
    . '</span></div>' . K_NEWLINE;
echo '</fieldset>' . K_NEWLINE;
echo '<div class="onboarding-admin-actions"><button type="submit" name="save_access" value="1" class="button">'
    . htmlspecialchars($l['ov_save'] ?? 'Сохранить', ENT_QUOTES, $settings_charset)
    . '</button></div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '<p>Укажите, какие тесты считать инструкцией и демо. Они будут показаны участнику над основным каталогом, пока он их не завершит.</p>' . K_NEWLINE;
echo '<form class="settings-form" action="' . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES) . '" method="post">' . K_NEWLINE;
echo '<fieldset class="settings-card"><legend><span aria-hidden="true">04</span> Последовательность знакомства</legend>' . K_NEWLINE;
echo '<div class="row"><label for="instruction_test_id">1. Тест-инструкция</label>' . K_NEWLINE;
f_onboarding_test_select(
    'instruction_test_id',
    $config['instruction_test_id'],
    $tests,
    $settings_charset,
);
echo '<span class="form-help">Объясняет порядок работы и правила прохождения.</span></div>' . K_NEWLINE;
echo '<div class="row"><label for="demo_test_id">2. Демо-тест</label>' . K_NEWLINE;
f_onboarding_test_select('demo_test_id', $config['demo_test_id'], $tests, $settings_charset);
echo '<span class="form-help">Позволяет участнику проверить вход и интерфейс без риска.</span></div>' . K_NEWLINE;
echo '</fieldset>' . K_NEWLINE;
echo '<div class="onboarding-admin-actions"><button type="submit" name="save_onboarding" value="1" class="button">Сохранить</button></div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form></div>' . K_NEWLINE;

$appearance_script = '../jscripts/appearance-preview.js';
$appearance_script_path = realpath(__DIR__ . '/' . $appearance_script);
if ($appearance_script_path !== false) {
    echo '<script src="' . $appearance_script . '?v=' . (int) filemtime($appearance_script_path) . '"></script>' . K_NEWLINE;
}

require_once 'tce_page_footer.php';
