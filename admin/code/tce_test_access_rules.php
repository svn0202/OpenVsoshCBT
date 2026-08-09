<?php

require_once '../config/tce_config.php';
$pagelevel = K_AUTH_ADMIN_TESTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once '../../shared/code/tce_functions_test_access.php';
require_once '../../shared/code/tce_functions_test.php';

$thispage_title = 'Условия доступа и завершения теста';
$test_id = isset($_REQUEST['test_id']) ? (int) $_REQUEST['test_id'] : 0;
$tests = [];
$result = F_db_query(F_select_tests_sql(), $db);
while ($result && ($test = F_db_fetch_array($result))) {
    $tests[(int) $test['test_id']] = $test;
}
if ($test_id > 0 && !isset($tests[$test_id])) {
    F_print_error('ERROR', $l['m_authorization_denied'], true);
}

$message = '';
$parse_publication_time = static function (mixed $value): string|false|null {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
        return false;
    }
    return $date->format(K_TIMESTAMP_FORMAT);
};
if (isset($_POST['save_rules'])) {
    if (
        empty($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !checkCSRFToken($_POST['csrf_token'])
        || $test_id <= 0
    ) {
        http_response_code(403);
        exit();
    }
    $required_finished = isset($_POST['required_finished']) ? (int) $_POST['required_finished'] : 0;
    $required_passed = isset($_POST['required_passed']) ? (int) $_POST['required_passed'] : 0;
    $results_publish_at = $parse_publication_time($_POST['results_publish_at'] ?? '');
    $results_unpublish_at = $parse_publication_time($_POST['results_unpublish_at'] ?? '');
    if (
        $required_finished === $test_id
        || $required_passed === $test_id
        || ($required_finished > 0 && !isset($tests[$required_finished]))
        || ($required_passed > 0 && !isset($tests[$required_passed]))
        || F_tmf_test_prerequisite_would_cycle($test_id, [$required_finished, $required_passed])
    ) {
        $message = 'Нельзя выбрать сам тест, недоступный тест или создать цикл условий.';
    } elseif ($results_publish_at === false || $results_unpublish_at === false) {
        $message = 'Укажите корректные дату и время публикации результатов.';
    } elseif (
        $results_publish_at !== null
        && $results_unpublish_at !== null
        && strtotime($results_unpublish_at) <= strtotime($results_publish_at)
    ) {
        $message = 'Дата отзыва должна быть позже даты публикации.';
    } else {
        $minimum_duration = max(0, min(1440, (int) ($_POST['minimum_duration'] ?? 0)));
        $completion_message = trim((string) ($_POST['completion_message'] ?? ''));
        if (mb_strlen($completion_message) > 4000) {
            $completion_message = mb_substr($completion_message, 0, 4000);
        }
        $sql = 'UPDATE ' . K_TABLE_TESTS . ' SET '
            . 'test_required_finished_id=' . ($required_finished > 0 ? $required_finished : 'NULL') . ','
            . 'test_required_passed_id=' . ($required_passed > 0 ? $required_passed : 'NULL') . ','
            . 'test_minimum_duration_time=' . $minimum_duration . ','
            . "test_require_all_answers='" . (isset($_POST['require_all_answers']) ? 1 : 0) . "',"
            . "test_block_finish_below_threshold='" . (isset($_POST['block_below_threshold']) ? 1 : 0) . "',"
            . "test_live_score='" . (isset($_POST['live_score']) ? 1 : 0) . "',"
            . "test_auto_fullscreen='" . (isset($_POST['auto_fullscreen']) ? 1 : 0) . "',"
            . "test_hide_exam_info='" . (isset($_POST['hide_exam_info']) ? 1 : 0) . "',"
            . "test_results_to_users='" . (isset($_POST['results_to_users']) ? 1 : 0) . "',"
            . "test_results_anonymized='" . (isset($_POST['results_anonymized']) ? 1 : 0) . "',"
            . 'test_results_publish_at=' . ($results_publish_at === null
                ? 'NULL'
                : "'" . F_escape_sql($db, $results_publish_at) . "'") . ','
            . 'test_results_unpublish_at=' . ($results_unpublish_at === null
                ? 'NULL'
                : "'" . F_escape_sql($db, $results_unpublish_at) . "'") . ','
            . "test_disable_previous='" . (isset($_POST['disable_previous']) ? 1 : 0) . "',"
            . "test_disable_next='" . (isset($_POST['disable_next']) ? 1 : 0) . "',"
            . "test_hide_editor='" . (isset($_POST['hide_editor']) ? 1 : 0) . "',"
            . 'test_completion_message=' . ($completion_message === ''
                ? 'NULL'
                : "'" . F_escape_sql($db, $completion_message) . "'")
            . ' WHERE test_id=' . $test_id;
        $message = F_db_query($sql, $db) ? 'Настройки сохранены.' : 'Не удалось сохранить настройки.';
    }
}

$rules = [
    'test_required_finished_id' => 0,
    'test_required_passed_id' => 0,
    'test_minimum_duration_time' => 0,
    'test_require_all_answers' => 0,
    'test_block_finish_below_threshold' => 0,
    'test_live_score' => 0,
    'test_auto_fullscreen' => 0,
    'test_hide_exam_info' => 0,
    'test_results_to_users' => 0,
    'test_results_publish_at' => '',
    'test_results_unpublish_at' => '',
    'test_results_anonymized' => 0,
    'test_disable_previous' => 0,
    'test_disable_next' => 0,
    'test_hide_editor' => 0,
    'test_completion_message' => '',
];
if ($test_id > 0) {
    $rules_result = F_db_query('SELECT * FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . $test_id . ' LIMIT 1', $db);
    if ($rules_result && ($row = F_db_fetch_array($rules_result))) {
        $rules = array_replace($rules, $row);
    }
}

require_once 'tce_page_header.php';

$html = static fn (mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    $l['a_meta_charset'],
);
$test_options = static function (int $selected) use ($tests, $test_id, $html): string {
    $output = '<option value="0">Не требуется</option>';
    foreach ($tests as $candidate_id => $candidate) {
        if ($candidate_id === $test_id) {
            continue;
        }
        $output .= '<option value="' . $candidate_id . '"'
            . ($candidate_id === $selected ? ' selected="selected"' : '') . '>'
            . $html($candidate['test_name']) . '</option>';
    }
    return $output;
};

echo '<div class="container"><div class="tceformbox">';
echo f_openvsosh_admin_test_context($test_id, 'access');
echo '<p class="pagehelp">Все ограничения проверяются сервером. Изменение токена не выкидывает '
    . 'участников, уже вошедших в этот тест в текущей сессии.</p>';
if ($message !== '') {
    echo '<p role="status">' . $html($message) . '</p>';
}
echo '<form action="tce_test_access_rules.php" method="get"><label for="test_id">Тест</label>'
    . '<select name="test_id" id="test_id" required="required"><option value="">Выберите тест</option>';
foreach ($tests as $available_id => $test) {
    echo '<option value="' . $available_id . '"' . ($available_id === $test_id ? ' selected="selected"' : '')
        . '>' . $html($test['test_name']) . '</option>';
}
echo '</select><button type="submit">Открыть</button></form>';

if ($test_id > 0) {
    echo '<form action="tce_test_access_rules.php" method="post">'
        . '<input type="hidden" name="test_id" value="' . $test_id . '" />'
        . '<div class="row"><span class="label"><label for="required_finished">Сначала завершить тест</label>'
        . '</span><span class="formw"><select name="required_finished" id="required_finished">'
        . $test_options((int) $rules['test_required_finished_id']) . '</select></span></div>'
        . '<div class="row"><span class="label"><label for="required_passed">Сначала пройти тест</label>'
        . '</span><span class="formw"><select name="required_passed" id="required_passed">'
        . $test_options((int) $rules['test_required_passed_id']) . '</select></span></div>'
        . '<div class="row"><span class="label"><label for="minimum_duration">Минимум до завершения, минут</label>'
        . '</span><span class="formw"><input type="number" name="minimum_duration" id="minimum_duration" '
        . 'min="0" max="1440" value="' . (int) $rules['test_minimum_duration_time'] . '" /></span></div>';
    foreach ([
        'require_all_answers' => ['test_require_all_answers', 'Требовать ответ на каждый вопрос'],
        'block_below_threshold' => ['test_block_finish_below_threshold', 'Не завершать ниже проходного балла'],
        'live_score' => ['test_live_score', 'Показывать текущий балл во время экзамена'],
        'auto_fullscreen' => ['test_auto_fullscreen', 'Открывать fullscreen после первого действия'],
        'hide_exam_info' => ['test_hide_exam_info', 'Скрывать служебную информацию во время экзамена'],
        'results_to_users' => ['test_results_to_users', 'Публиковать результаты участникам'],
        'results_anonymized' => ['test_results_anonymized', 'Обезличивать участника в опубликованном результате'],
        'disable_previous' => ['test_disable_previous', 'Отключить кнопку «Назад»'],
        'disable_next' => ['test_disable_next', 'Отключить кнопку «Далее»'],
        'hide_editor' => ['test_hide_editor', 'Не загружать редактор для эссе'],
    ] as $name => [$field, $label]) {
        echo '<div class="row"><span class="label"><label for="' . $name . '">' . $label
            . '</label></span><span class="formw"><input type="checkbox" name="' . $name
            . '" id="' . $name . '" value="1"' . (F_getBoolean($rules[$field]) ? ' checked="checked"' : '')
            . ' /></span></div>';
    }
    $datetime_value = static function (mixed $value): string {
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
    };
    echo '<div class="row"><span class="label"><label for="results_publish_at">Опубликовать не раньше</label>'
        . '</span><span class="formw"><input type="datetime-local" name="results_publish_at" '
        . 'id="results_publish_at" value="' . $html($datetime_value($rules['test_results_publish_at'])) . '" />'
        . '</span></div>'
        . '<div class="row"><span class="label"><label for="results_unpublish_at">Отозвать публикацию</label>'
        . '</span><span class="formw"><input type="datetime-local" name="results_unpublish_at" '
        . 'id="results_unpublish_at" value="' . $html($datetime_value($rules['test_results_unpublish_at'])) . '" />'
        . '</span></div>';
    echo '<div class="row"><span class="label"><label for="completion_message">Сообщение после завершения</label>'
        . '</span><span class="formw"><textarea name="completion_message" id="completion_message" '
        . 'rows="5" cols="60">' . $html($rules['test_completion_message']) . '</textarea></span></div>'
        . '<button type="submit" name="save_rules" value="1">Сохранить</button>'
        . F_getCSRFTokenField() . '</form>';
}
echo '</div></div>';

require_once 'tce_page_footer.php';
