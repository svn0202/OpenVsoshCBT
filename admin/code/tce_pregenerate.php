<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_ADMIN_TESTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once '../../shared/code/tce_functions_test.php';

$thispage_title = 'Предварительная генерация вариантов';
$test_id = isset($_REQUEST['test_id']) ? (int) $_REQUEST['test_id'] : 0;
$tests = [];
$result = F_db_query(F_select_tests_sql(), $db);
while ($result && ($test = F_db_fetch_array($result))) {
    $tests[(int) $test['test_id']] = $test;
}
if ($test_id > 0 && !isset($tests[$test_id])) {
    F_print_error('ERROR', $l['m_authorization_denied'], true);
}

$generated_now = 0;
$invalidated_now = 0;
$generation_errors = 0;
if (isset($_POST['pregenerate'])) {
    if (
        empty($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !checkCSRFToken($_POST['csrf_token'])
        || $test_id <= 0
    ) {
        http_response_code(403);
        exit();
    }

    $invalidated_now = F_tmf_pregeneration_invalidate($test_id);
    $eligible_ids = F_tmf_pregeneration_eligible_users($test_id);
    foreach ($eligible_ids as $eligible_user_id) {
        if ($generated_now >= TMF_PREGENERATION_BATCH_MAX) {
            break;
        }
        $status = F_tmf_pregenerate_user($test_id, $eligible_user_id);
        if ($status === 'generated') {
            ++$generated_now;
        } elseif ($status === 'error') {
            ++$generation_errors;
        }
    }
}

$eligible_ids = $test_id > 0 ? F_tmf_pregeneration_eligible_users($test_id) : [];
$eligible_count = count($eligible_ids);
$prepared_count = 0;
$started_count = 0;
if ($test_id > 0) {
    $counts_sql = 'SELECT testuser_user_id, testuser_status, testuser_pregenerated
        FROM ' . K_TABLE_TEST_USER . '
        WHERE testuser_test_id=' . $test_id . '
            AND testuser_status<5';
    $counts_result = F_db_query($counts_sql, $db);
    $eligible_lookup = array_fill_keys($eligible_ids, true);
    while ($counts_result && ($attempt = F_db_fetch_array($counts_result))) {
        if (!isset($eligible_lookup[(int) $attempt['testuser_user_id']])) {
            continue;
        }
        if (f_get_boolean($attempt['testuser_pregenerated'])) {
            ++$prepared_count;
        } else {
            ++$started_count;
        }
    }
}
$waiting_count = max(0, $eligible_count - $prepared_count - $started_count);

require_once 'tce_page_header.php';

echo '<div class="monitor-panel">' . K_NEWLINE;
echo f_openvsosh_admin_test_context($test_id, 'generation');
echo '<p class="pagehelp">Варианты создаются штатным серверным генератором пакетами не более '
    . TMF_PREGENERATION_BATCH_MAX . ' участников. Ключи правильных ответов клиенту не выдаются.</p>';
echo '<form action="tce_pregenerate.php" method="get" class="monitor-filters">';
echo '<label for="test_id">Тест</label><select name="test_id" id="test_id" required="required">';
echo '<option value="">Выберите тест</option>';
foreach ($tests as $available_test_id => $test) {
    $selected = $available_test_id === $test_id ? ' selected="selected"' : '';
    echo '<option value="' . $available_test_id . '"' . $selected . '>'
        . htmlspecialchars((string) $test['test_name'], ENT_QUOTES, $l['a_meta_charset']) . '</option>';
}
echo '</select><button type="submit">Показать</button></form>';

if ($test_id > 0) {
    echo '<div class="monitor-summary" aria-label="Сводка генерации">'
        . '<span><strong>' . $eligible_count . '</strong><small>Всего участников</small></span>'
        . '<span><strong>' . $prepared_count . '</strong><small>Подготовлено</small></span>'
        . '<span><strong>' . $started_count . '</strong><small>Уже начали</small></span>'
        . '<span><strong>' . $waiting_count . '</strong><small>Ожидают генерации</small></span>'
        . '</div>';
    if ($generated_now > 0 || $invalidated_now > 0 || $generation_errors > 0) {
        echo '<p class="monitor-message" role="status">Создано: ' . $generated_now
            . '; устаревших вариантов удалено: ' . $invalidated_now
            . '; ошибок: ' . $generation_errors . '.</p>';
    }
    echo '<form action="tce_pregenerate.php" method="post">';
    echo '<input type="hidden" name="test_id" value="' . $test_id . '" />';
    echo F_getCSRFTokenField();
    echo '<button type="submit" name="pregenerate" value="1"'
        . ($waiting_count === 0 ? ' disabled="disabled"' : '')
        . '>Сгенерировать следующую партию</button></form>';
    echo '<p class="pagehelp">Перед каждой партией система удаляет только неоткрытые варианты, '
        . 'которые устарели после изменения теста, вопросов, ответов, выборки или групп. '
        . 'Открытая участником попытка не изменяется.</p>';
}
echo '</div>';

require_once '../code/tce_page_footer.php';
