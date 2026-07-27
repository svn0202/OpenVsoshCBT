<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_ADMINISTRATOR;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_onboarding.php';
require_once '../../shared/config/tce_user_registration.php';
require_once '../../shared/code/tce_functions_openvsosh_settings.php';
require_once '../../shared/code/tce_functions_site_assets.php';

$thispage_title = $l['ov_instance_settings'];
require_once 'tce_page_header.php';

$config = F_getOnboardingConfig();
$access_config = openvsosh_get_access_settings();
$site_config = openvsosh_get_site_settings();
$runtime_config = openvsosh_get_runtime_settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_onboarding'])) {
    if (empty($_POST['csrf_token']) || !checkCSRFToken($_POST['csrf_token'])) {
        exit();
    }
    $instruction_id = max(0, (int) ($_POST['instruction_test_id'] ?? 0));
    $demo_id = max(0, (int) ($_POST['demo_test_id'] ?? 0));
    if ($instruction_id > 0 && $instruction_id === $demo_id) {
        F_print_error('WARNING', 'Для инструкции и демо-теста выберите разные тесты.');
    } elseif (F_saveOnboardingConfig($instruction_id, $demo_id)) {
        $config = F_getOnboardingConfig();
        F_print_error('MESSAGE', 'Настройки вводных тестов сохранены.');
    } else {
        F_print_error('ERROR', 'Не удалось сохранить настройки. Проверьте права на shared/config.', false);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_site'])) {
    if (empty($_POST['csrf_token']) || !checkCSRFToken($_POST['csrf_token'])) {
        exit();
    }
    $site_result = openvsosh_save_site_settings($_POST);
    $runtime_result = openvsosh_save_runtime_settings($_POST);
    if ($site_result['saved'] && $runtime_result['saved']) {
        $asset_errors = [];
        foreach (['logo', 'background'] as $asset_type) {
            if (isset($_FILES['site_' . $asset_type])) {
                $asset_result = openvsosh_store_site_asset(
                    $asset_type,
                    (array) $_FILES['site_' . $asset_type],
                );
                if (!$asset_result['stored']) {
                    $asset_errors[] = $asset_result['message'];
                }
            }
        }
        $site_config = openvsosh_get_site_settings();
        $runtime_config = openvsosh_get_runtime_settings();
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
                implode(' ', array_merge($site_result['errors'], $runtime_result['errors'])),
                ENT_QUOTES,
                $l['a_meta_charset'],
            ),
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_access'])) {
    if (empty($_POST['csrf_token']) || !checkCSRFToken($_POST['csrf_token'])) {
        exit();
    }

    $registration_enabled = !isset($_POST['disable_registration']);
    $password_reset_enabled = !isset($_POST['disable_password_reset']);
    $access_help = trim((string) ($_POST['access_help'] ?? ''));
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
if ($r = F_db_query($sql, $db)) {
    while ($test = F_db_fetch_array($r)) {
        $tests[] = $test;
    }
}

function F_onboardingTestSelect($name, $selected, $tests)
{
    global $l;
    echo '<select name="' . $name . '" id="' . $name . '">' . K_NEWLINE;
    echo '<option value="0">— не назначен —</option>' . K_NEWLINE;
    foreach ($tests as $test) {
        $id = (int) $test['test_id'];
        echo '<option value="' . $id . '"' . ($id === (int) $selected ? ' selected="selected"' : '') . '>';
        echo htmlspecialchars((string) $test['test_name'], ENT_QUOTES, $l['a_meta_charset']);
        echo '</option>' . K_NEWLINE;
    }
    echo '</select>' . K_NEWLINE;
}

echo '<div class="container onboarding-admin">' . K_NEWLINE;
echo '<style>
.onboarding-admin{max-width:900px;margin:28px auto;padding:28px 32px;background:#fff;border:1px solid #cbd5df;border-radius:12px;box-shadow:0 12px 32px rgba(30,50,70,.08)}
.onboarding-admin h1{margin:0 0 8px;color:#183b64}.onboarding-admin>p{margin:0 0 26px;color:#52677d}
.onboarding-admin fieldset{padding:22px;border:1px solid #d5dee8;border-radius:10px}.onboarding-admin legend{padding:0 8px;font-weight:700;color:#274f7c}
.onboarding-admin .row{display:grid;grid-template-columns:190px minmax(260px,1fr);gap:8px 18px;align-items:center;margin:16px 0}
.onboarding-admin label{font-weight:700}.onboarding-admin select,.onboarding-admin textarea{width:100%;min-height:44px;padding:8px 12px;border:1px solid #aebdcb;border-radius:7px;background:#fff;box-sizing:border-box}
.onboarding-admin textarea{min-height:150px;resize:vertical}.onboarding-admin .check-row{grid-template-columns:32px minmax(0,1fr)}.onboarding-admin .check-row input{width:22px;height:22px;margin:0;accent-color:#2f6da8}.onboarding-admin .check-row label{display:block}.onboarding-admin .check-row .form-help{grid-column:2}
.onboarding-admin .form-help{grid-column:2;color:#65798d;font-size:13px}.onboarding-admin-actions{display:flex;justify-content:flex-end;margin-top:20px}
.onboarding-admin-actions .button{padding:10px 24px;border:0;border-radius:7px;background:#2f6da8;color:#fff;font-weight:700;cursor:pointer}
@media(max-width:700px){.onboarding-admin .row{grid-template-columns:1fr}.onboarding-admin .form-help{grid-column:1}}
</style>' . K_NEWLINE;
echo '<form action="' . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
    . '" method="post" enctype="multipart/form-data">' . K_NEWLINE;
echo '<fieldset><legend>Оформление площадки</legend>' . K_NEWLINE;
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
            . htmlspecialchars($site_config[$key], ENT_QUOTES, $l['a_meta_charset']) . '</textarea>';
    } else {
        echo '<input type="text" name="' . $key . '" id="' . $key . '" maxlength="' . $limit
            . '" value="' . htmlspecialchars($site_config[$key], ENT_QUOTES, $l['a_meta_charset']) . '" />';
    }
    echo '</div>' . K_NEWLINE;
}
echo '<div class="row"><label for="site_logo">Основной логотип</label>'
    . '<input type="file" name="site_logo" id="site_logo" accept="image/jpeg,image/png" />'
    . '<span class="form-help">JPEG/PNG, 32–8192 px, до 5 МБ.</span></div>' . K_NEWLINE;
echo '<div class="row"><label for="site_background">Фон страницы входа</label>'
    . '<input type="file" name="site_background" id="site_background" accept="image/jpeg,image/png" />'
    . '<span class="form-help">JPEG/PNG, 32–8192 px, до 5 МБ.</span></div>' . K_NEWLINE;
echo '</fieldset><fieldset><legend>Язык, время и предупреждения</legend>' . K_NEWLINE;
echo '<div class="row"><label for="default_language">Язык по умолчанию</label>'
    . '<select name="default_language" id="default_language">';
foreach ((array) unserialize(K_AVAILABLE_LANGUAGES, ['allowed_classes' => false]) as $code => $name) {
    echo '<option value="' . htmlspecialchars((string) $code, ENT_QUOTES) . '"'
        . ((string) $runtime_config['default_language'] === (string) $code ? ' selected="selected"' : '')
        . '>' . htmlspecialchars((string) $name, ENT_QUOTES, $l['a_meta_charset']) . '</option>';
}
echo '</select></div>' . K_NEWLINE;
echo '<div class="row"><label for="default_timezone">Часовой пояс</label>'
    . '<input type="text" name="default_timezone" id="default_timezone" list="timezone-list" maxlength="64" value="'
    . htmlspecialchars((string) $runtime_config['default_timezone'], ENT_QUOTES, $l['a_meta_charset']) . '" />'
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
    . htmlspecialchars((string) $runtime_config['timer_warning_color'], ENT_QUOTES) . '" /></div>' . K_NEWLINE;
echo '<div class="row"><label for="timer_critical_color">Критический цвет</label>'
    . '<input type="color" name="timer_critical_color" id="timer_critical_color" value="'
    . htmlspecialchars((string) $runtime_config['timer_critical_color'], ENT_QUOTES) . '" /></div>' . K_NEWLINE;
echo '</fieldset><div class="onboarding-admin-actions">'
    . '<button type="submit" name="save_site" value="1" class="button">Сохранить оформление</button></div>'
    . F_getCSRFTokenField() . K_NEWLINE . '</form>' . K_NEWLINE;
echo '<form action="' . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES) . '" method="post">' . K_NEWLINE;
echo '<fieldset><legend>'
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
    . htmlspecialchars($l['ov_save'], ENT_QUOTES, $l['a_meta_charset'])
    . '</button></div>' . K_NEWLINE;
echo F_getCSRFTokenField() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '<p>Укажите, какие тесты считать инструкцией и демо. Они будут показаны участнику над основным каталогом, пока он их не завершит.</p>' . K_NEWLINE;
echo '<form action="' . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES) . '" method="post">' . K_NEWLINE;
echo '<fieldset><legend>Последовательность знакомства</legend>' . K_NEWLINE;
echo '<div class="row"><label for="instruction_test_id">1. Тест-инструкция</label>' . K_NEWLINE;
F_onboardingTestSelect('instruction_test_id', $config['instruction_test_id'], $tests);
echo '<span class="form-help">Объясняет порядок работы и правила прохождения.</span></div>' . K_NEWLINE;
echo '<div class="row"><label for="demo_test_id">2. Демо-тест</label>' . K_NEWLINE;
F_onboardingTestSelect('demo_test_id', $config['demo_test_id'], $tests);
echo '<span class="form-help">Позволяет участнику проверить вход и интерфейс без риска.</span></div>' . K_NEWLINE;
echo '</fieldset>' . K_NEWLINE;
echo '<div class="onboarding-admin-actions"><button type="submit" name="save_onboarding" value="1" class="button">Сохранить</button></div>' . K_NEWLINE;
echo F_getCSRFTokenField() . K_NEWLINE;
echo '</form></div>' . K_NEWLINE;

require_once 'tce_page_footer.php';
