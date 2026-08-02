<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_OPERATOR;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once '../../shared/code/tce_functions_test.php';

$thispage_title = 'Наблюдение за тестированием';
$test_id = isset($_REQUEST['test_id']) ? (int) $_REQUEST['test_id'] : 0;
$status_filter = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$allowed_statuses = [
    'not_started',
    'in_progress',
    'connection_lost',
    'completed',
    'timed_out',
    'blocked',
];
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = '';
}

$tests = [];
$tests_result = F_db_query(F_select_tests_sql(), $db);
while ($tests_result && ($test_row = F_db_fetch_array($tests_result))) {
    $tests[(int) $test_row['test_id']] = $test_row;
}
if ($test_id > 0 && !isset($tests[$test_id])) {
    F_print_error('ERROR', $l['m_authorization_denied'], true);
}

$action_result = '';
if (isset($_POST['monitor_action'])) {
    if (
        empty($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !checkCSRFToken($_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit();
    }
    $action = is_string($_POST['monitor_action']) ? $_POST['monitor_action'] : '';
    $testuser_id = isset($_POST['testuser_id']) ? (int) $_POST['testuser_id'] : 0;
    $extend_minutes = isset($_POST['extend_minutes']) ? (int) $_POST['extend_minutes'] : 0;
    $result = F_tmf_monitor_apply_action($testuser_id, $action, $extend_minutes);
    $action_result = $result['status'];
    header(
        'Location: tce_monitor.php?test_id=' . $test_id
        . '&action_result=' . rawurlencode($action_result),
    );
    exit();
}
if (isset($_GET['action_result']) && is_string($_GET['action_result'])) {
    $action_result = $_GET['action_result'];
}

$participants = [];
$status_counts = array_fill_keys($allowed_statuses, 0);
if ($test_id > 0) {
    $author_name = '';
    $author_result = F_db_query(
        'SELECT user_name, user_firstname, user_lastname
        FROM ' . K_TABLE_USERS . '
        WHERE user_id=' . (int) $tests[$test_id]['test_user_id'] . '
        LIMIT 1',
        $db,
    );
    if ($author_result && ($author = F_db_fetch_array($author_result))) {
        $author_name = trim((string) $author['user_lastname'] . ' ' . (string) $author['user_firstname']);
        if ($author_name === '') {
            $author_name = (string) $author['user_name'];
        }
    }
    $user_sql = 'SELECT DISTINCT u.user_id, u.user_name, u.user_firstname, u.user_lastname
        FROM ' . K_TABLE_USERS . ' u
        INNER JOIN ' . K_TABLE_USERGROUP . ' ug ON ug.usrgrp_user_id=u.user_id
        INNER JOIN ' . K_TABLE_TEST_GROUPS . ' tg ON tg.tstgrp_group_id=ug.usrgrp_group_id
        WHERE tg.tstgrp_test_id=' . $test_id . '
        ORDER BY u.user_lastname, u.user_firstname, u.user_name';
    $user_result = F_db_query($user_sql, $db);
    while ($user_result && ($user = F_db_fetch_array($user_result))) {
        $participants[(int) $user['user_id']] = [
            'user' => $user,
            'attempt' => null,
            'questions_total' => 0,
            'questions_answered' => 0,
            'answer_saved_at' => null,
            'focus_loss_count' => 0,
        ];
    }

    $attempt_sql = 'SELECT testuser_id, testuser_user_id, testuser_status,
            testuser_creation_time, testuser_last_activity, testuser_close_reason,
            testuser_pregenerated, testuser_focus_loss_count
        FROM ' . K_TABLE_TEST_USER . '
        WHERE testuser_test_id=' . $test_id . '
        ORDER BY testuser_user_id, testuser_status, testuser_id DESC';
    $attempt_result = F_db_query($attempt_sql, $db);
    while ($attempt_result && ($attempt = F_db_fetch_array($attempt_result))) {
        $participant_id = (int) $attempt['testuser_user_id'];
        if (!isset($participants[$participant_id])) {
            continue;
        }
        $current = $participants[$participant_id]['attempt'];
        if (
            $current === null
            || ((int) $current['testuser_status'] >= 5 && (int) $attempt['testuser_status'] < 5)
        ) {
            $participants[$participant_id]['attempt'] = $attempt;
        }
    }

    $log_sql = 'SELECT tl.testlog_testuser_id,
            COUNT(tl.testlog_id) AS questions_total,
            SUM(CASE WHEN tl.testlog_change_time IS NULL THEN 0 ELSE 1 END) AS questions_answered,
            MAX(tl.testlog_answer_saved_at) AS answer_saved_at
        FROM ' . K_TABLE_TESTS_LOGS . ' tl
        INNER JOIN ' . K_TABLE_TEST_USER . ' tu ON tu.testuser_id=tl.testlog_testuser_id
        WHERE tu.testuser_test_id=' . $test_id . '
            AND tu.testuser_status<5
        GROUP BY tl.testlog_testuser_id';
    $log_result = F_db_query($log_sql, $db);
    $log_totals = [];
    while ($log_result && ($log = F_db_fetch_array($log_result))) {
        $log_totals[(int) $log['testlog_testuser_id']] = $log;
    }

    foreach ($participants as &$participant) {
        $attempt = $participant['attempt'];
        if (is_array($attempt) && isset($log_totals[(int) $attempt['testuser_id']])) {
            $log = $log_totals[(int) $attempt['testuser_id']];
            $participant['questions_total'] = (int) $log['questions_total'];
            $participant['questions_answered'] = (int) $log['questions_answered'];
            $participant['answer_saved_at'] = $log['answer_saved_at'];
        }
        if (is_array($attempt)) {
            $participant['focus_loss_count'] = (int) $attempt['testuser_focus_loss_count'];
        }
        $participant['status'] = is_array($attempt) && F_getBoolean($attempt['testuser_pregenerated'])
            ? 'not_started'
            : F_tmf_monitor_status(
                $attempt === null ? null : (int) $attempt['testuser_status'],
                $attempt === null || $attempt['testuser_close_reason'] === null
                    ? null
                    : (string) $attempt['testuser_close_reason'],
                $attempt === null || $attempt['testuser_last_activity'] === null
                    ? null
                    : (string) $attempt['testuser_last_activity'],
                time(),
            );
        $participant['remaining_seconds'] = null;
        if (
            is_array($attempt)
            && in_array($participant['status'], ['in_progress', 'connection_lost', 'blocked'], true)
        ) {
            $participant['remaining_seconds'] = max(
                0,
                strtotime((string) $attempt['testuser_creation_time'])
                    + ((int) $tests[$test_id]['test_duration_time'] * K_SECONDS_IN_MINUTE)
                    - time(),
            );
        }
        ++$status_counts[$participant['status']];
    }
    unset($participant);
}

$status_labels = [
    'not_started' => 'Не приступил',
    'in_progress' => 'Выполняет',
    'connection_lost' => 'Связь потеряна',
    'completed' => 'Завершил',
    'timed_out' => 'Время истекло',
    'blocked' => 'Заблокирован',
];

$visible_participants = array_filter(
    $participants,
    static function (array $participant) use ($status_filter, $search): bool {
        if ($status_filter !== '' && $participant['status'] !== $status_filter) {
            return false;
        }
        if ($search === '') {
            return true;
        }
        $user = $participant['user'];
        $haystack = strtolower(
            (string) $user['user_name'] . ' '
            . (string) $user['user_firstname'] . ' '
            . (string) $user['user_lastname'],
        );
        return str_contains($haystack, strtolower($search));
    },
);

if ($test_id > 0 && isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    require_once '../../shared/code/tce_functions_xlsx.php';
    $rows = [[
        'login', 'last_name', 'first_name', 'status', 'answered', 'total',
        'focus_loss_count', 'remaining_seconds', 'last_activity', 'last_saved',
    ]];
    foreach ($visible_participants as $participant) {
        $user = $participant['user'];
        $attempt = $participant['attempt'];
        $rows[] = [
            $user['user_name'],
            $user['user_lastname'],
            $user['user_firstname'],
            $status_labels[$participant['status']],
            ['value' => $participant['questions_answered'], 'type' => 'number'],
            ['value' => $participant['questions_total'], 'type' => 'number'],
            ['value' => (int) ($participant['focus_loss_count'] ?? 0), 'type' => 'number'],
            $participant['remaining_seconds'] === null
                ? ''
                : ['value' => $participant['remaining_seconds'], 'type' => 'number'],
            $attempt['testuser_last_activity'] ?? '',
            $participant['answer_saved_at'] ?? '',
        ];
    }
    $bytes = F_tmf_xlsx_build([[
        'name' => 'Мониторинг ' . $test_id,
        'widths' => [20, 22, 22, 18, 12, 10, 18, 20, 22, 22],
        'rows' => $rows,
    ]]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="monitor-test-' . $test_id . '.xlsx"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit();
}

if ($test_id > 0 && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="monitor-test-' . $test_id . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, [
        'Логин',
        'Фамилия',
        'Имя',
        'Статус',
        'Отвечено',
        'Всего',
        'Попытки переключения',
        'Осталось, сек.',
        'Последняя активность',
        'Последнее сохранение',
    ]);
    foreach ($visible_participants as $participant) {
        $user = $participant['user'];
        $attempt = $participant['attempt'];
        fputcsv($output, [
            $user['user_name'],
            $user['user_lastname'],
            $user['user_firstname'],
            $status_labels[$participant['status']],
            $participant['questions_answered'],
            $participant['questions_total'],
            (int) ($participant['focus_loss_count'] ?? 0),
            $participant['remaining_seconds'] ?? '',
            $attempt['testuser_last_activity'] ?? '',
            $participant['answer_saved_at'] ?? '',
        ]);
    }
    fclose($output);
    exit();
}

require_once 'tce_page_header.php';

function F_tmf_monitor_html(mixed $value): string
{
    global $l;
    return htmlspecialchars((string) $value, ENT_QUOTES, $l['a_meta_charset']);
}

echo '<div class="monitor-panel">' . K_NEWLINE;
echo '<p class="pagehelp">Техническое состояние участников без просмотра содержания ответов. '
    . 'Данные активности обновляются не реже одного раза в минуту.</p>' . K_NEWLINE;
if ($action_result !== '') {
    $message = $action_result === 'updated'
        ? 'Действие выполнено и записано в журнал.'
        : 'Действие не выполнено: ' . $action_result;
    echo '<p class="monitor-message" role="status">' . F_tmf_monitor_html($message) . '</p>' . K_NEWLINE;
}

echo '<form action="tce_monitor.php" method="get" class="monitor-filters">' . K_NEWLINE;
echo '<label for="test_id">Тест</label><select name="test_id" id="test_id" required="required">';
echo '<option value="">Выберите тест</option>';
foreach ($tests as $available_test_id => $test) {
    $selected = $available_test_id === $test_id ? ' selected="selected"' : '';
    echo '<option value="' . $available_test_id . '"' . $selected . '>'
        . F_tmf_monitor_html($test['test_name']) . '</option>';
}
echo '</select>';
echo '<label for="status">Статус</label><select name="status" id="status"><option value="">Все</option>';
foreach ($status_labels as $status => $label) {
    $selected = $status === $status_filter ? ' selected="selected"' : '';
    echo '<option value="' . $status . '"' . $selected . '>' . $label . '</option>';
}
echo '</select>';
echo '<label for="search">Участник</label><input type="search" name="search" id="search" value="'
    . F_tmf_monitor_html($search) . '" />';
echo '<button type="submit">Показать</button>';
echo '</form>' . K_NEWLINE;

if ($test_id > 0) {
    echo '<div class="monitor-summary" aria-label="Сводка">';
    foreach ($status_labels as $status => $label) {
        echo '<a href="?test_id=' . $test_id . '&amp;status=' . $status . '"><strong>'
            . $status_counts[$status] . '</strong><span>' . $label . '</span></a>';
    }
    echo '</div>' . K_NEWLINE;
    $test_token = (string) ($tests[$test_id]['test_password'] ?? '');
    echo '<dl class="monitor-test-meta"><div><dt>Автор</dt><dd>'
        . F_tmf_monitor_html($author_name === '' ? '—' : $author_name)
        . '</dd></div><div><dt>Токен теста</dt><dd><code>'
        . F_tmf_monitor_html($test_token === '' ? 'не установлен' : $test_token)
        . '</code></dd></div></dl>' . K_NEWLINE;
    echo '<p><a class="xmlbutton" href="?test_id=' . $test_id . '&amp;status='
        . rawurlencode($status_filter) . '&amp;search=' . rawurlencode($search)
        . '&amp;export=csv">Экспортировать CSV</a> '
        . '<a class="xmlbutton" href="?test_id=' . $test_id . '&amp;status='
        . rawurlencode($status_filter) . '&amp;search=' . rawurlencode($search)
        . '&amp;export=xlsx">Экспортировать XLSX</a></p>' . K_NEWLINE;

    echo '<div class="monitor-table-wrap"><table class="monitor-table"><thead><tr>'
        . '<th>Участник</th><th>Состояние</th><th>Прогресс</th><th>Осталось</th>'
        . '<th>Переключения</th><th>Последняя активность</th><th>Последнее сохранение</th>'
        . '<th>Управление</th></tr></thead><tbody>' . K_NEWLINE;
    foreach ($visible_participants as $participant) {
        $user = $participant['user'];
        $attempt = $participant['attempt'];
        $full_name = trim((string) $user['user_lastname'] . ' ' . (string) $user['user_firstname']);
        echo '<tr><td><strong>' . F_tmf_monitor_html($full_name === '' ? $user['user_name'] : $full_name)
            . '</strong><small>' . F_tmf_monitor_html($user['user_name']) . '</small></td>';
        echo '<td><span class="monitor-status monitor-status-' . $participant['status'] . '">'
            . $status_labels[$participant['status']] . '</span></td>';
        echo '<td>' . $participant['questions_answered'] . ' / ' . $participant['questions_total'] . '</td>';
        $remaining = $participant['remaining_seconds'];
        echo '<td>' . ($remaining === null ? '—' : sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60))
            . '</td>';
        echo '<td><strong>' . (int) ($participant['focus_loss_count'] ?? 0) . '</strong></td>';
        echo '<td>' . F_tmf_monitor_html($attempt['testuser_last_activity'] ?? '—') . '</td>';
        echo '<td>' . F_tmf_monitor_html($participant['answer_saved_at'] ?? '—') . '</td><td>';
        if (is_array($attempt)) {
            echo '<form action="tce_monitor.php" method="post" class="monitor-actions">';
            echo '<input type="hidden" name="test_id" value="' . $test_id . '" />';
            echo '<input type="hidden" name="testuser_id" value="' . (int) $attempt['testuser_id'] . '" />';
            echo F_getCSRFTokenField();
            if ($participant['status'] === 'blocked') {
                echo '<button name="monitor_action" value="unblock" type="submit">Разблокировать</button>';
            } elseif (in_array($participant['status'], ['in_progress', 'connection_lost'], true)) {
                echo '<button name="monitor_action" value="block" type="submit">Заблокировать</button>';
            }
            if (!in_array($participant['status'], ['not_started', 'completed'], true)) {
                echo '<label><span class="sr-only">Минуты</span><input type="number" name="extend_minutes" '
                    . 'value="5" min="1" max="60" /></label>'
                    . '<button name="monitor_action" value="extend" type="submit">Добавить время</button>';
            }
            echo '<button name="monitor_action" value="reset" type="submit" '
                . 'onclick="return confirm(\'Создать новую попытку? Прежние ответы останутся в архиве.\')">'
                . 'Сбросить попытку</button></form>';
        } else {
            echo '—';
        }
        echo '</td></tr>' . K_NEWLINE;
    }
    if ($visible_participants === []) {
        echo '<tr><td colspan="8">Нет участников, соответствующих фильтру.</td></tr>';
    }
    echo '</tbody></table></div>' . K_NEWLINE;

    $audit_sql = 'SELECT a.monitor_audit_time, a.monitor_action, a.monitor_details,
            actor.user_name AS actor_name, target.user_name AS target_name
        FROM ' . F_tmf_monitor_audit_table() . ' a
        INNER JOIN ' . K_TABLE_USERS . ' actor ON actor.user_id=a.monitor_actor_user_id
        INNER JOIN ' . K_TABLE_USERS . ' target ON target.user_id=a.monitor_target_user_id
        WHERE a.monitor_test_id=' . $test_id . '
        ORDER BY a.monitor_audit_id DESC
        LIMIT 50';
    $audit_result = F_db_query($audit_sql, $db);
    echo '<details class="monitor-audit"><summary>Журнал действий</summary><table>'
        . '<thead><tr><th>Время</th><th>Оператор</th><th>Участник</th><th>Действие</th>'
        . '<th>Детали</th></tr></thead><tbody>';
    while ($audit_result && ($audit = F_db_fetch_array($audit_result))) {
        echo '<tr><td>' . F_tmf_monitor_html($audit['monitor_audit_time']) . '</td><td>'
            . F_tmf_monitor_html($audit['actor_name']) . '</td><td>'
            . F_tmf_monitor_html($audit['target_name']) . '</td><td>'
            . F_tmf_monitor_html($audit['monitor_action']) . '</td><td>'
            . F_tmf_monitor_html($audit['monitor_details'] ?? '') . '</td></tr>';
    }
    echo '</tbody></table></details>';
}
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';
