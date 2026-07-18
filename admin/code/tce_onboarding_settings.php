<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_ADMIN_TESTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_onboarding.php';

$thispage_title = 'Настройки интерфейса';
require_once 'tce_page_header.php';

$config = F_getOnboardingConfig();
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
.onboarding-admin label{font-weight:700}.onboarding-admin select{width:100%;min-height:44px;padding:8px 12px;border:1px solid #aebdcb;border-radius:7px;background:#fff}
.onboarding-admin .form-help{grid-column:2;color:#65798d;font-size:13px}.onboarding-admin-actions{display:flex;justify-content:flex-end;margin-top:20px}
.onboarding-admin-actions .button{padding:10px 24px;border:0;border-radius:7px;background:#2f6da8;color:#fff;font-weight:700;cursor:pointer}
@media(max-width:700px){.onboarding-admin .row{grid-template-columns:1fr}.onboarding-admin .form-help{grid-column:1}}
</style>' . K_NEWLINE;
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
