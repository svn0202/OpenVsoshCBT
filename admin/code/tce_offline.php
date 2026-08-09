<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_ADMIN_TESTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once '../../shared/code/tce_functions_test.php';
require_once '../../shared/code/tce_functions_offline.php';

$thispage_title = 'Автономное проведение';
$test_id = isset($_REQUEST['test_id']) ? (int) $_REQUEST['test_id'] : 0;
$tests = [];
$result = F_db_query(F_select_tests_sql(), $db);
while ($result && ($test = F_db_fetch_array($result))) {
    $tests[(int) $test['test_id']] = $test;
}
if ($test_id > 0 && !isset($tests[$test_id])) {
    F_print_error('ERROR', $l['m_authorization_denied'], true);
}

$action_status = '';
if (isset($_POST['export_offline']) || isset($_POST['import_offline'])) {
    if (
        empty($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !checkCSRFToken($_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit();
    }
}
if (isset($_POST['export_offline'])) {
    $testuser_id = isset($_POST['testuser_id']) ? (int) $_POST['testuser_id'] : 0;
    $issued = F_tmf_offline_issue($testuser_id);
    if ($issued['status'] === 'issued') {
        $html = F_tmf_offline_html($issued['envelope']);
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $issued['filename'] . '"');
        header('Content-Length: ' . strlen($html));
        echo $html;
        exit();
    }
    $action_status = $issued['status'];
}
if (isset($_POST['import_offline'])) {
    if (
        !isset($_FILES['result_file'])
        || !is_array($_FILES['result_file'])
        || (int) ($_FILES['result_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || (int) ($_FILES['result_file']['size'] ?? 0) > TMF_OFFLINE_MAX_RESULT_BYTES
        || !is_uploaded_file((string) ($_FILES['result_file']['tmp_name'] ?? ''))
    ) {
        $action_status = 'invalid_upload';
    } else {
        $contents = file_get_contents((string) $_FILES['result_file']['tmp_name']);
        $imported = F_tmf_offline_import(is_string($contents) ? $contents : '');
        $action_status = $imported['status'];
    }
}

$attempts = [];
if ($test_id > 0) {
    $attempts_sql = 'SELECT tu.testuser_id, tu.testuser_status, tu.testuser_pregenerated,
            u.user_name, u.user_firstname, u.user_lastname
        FROM ' . K_TABLE_TEST_USER . ' tu
        INNER JOIN ' . K_TABLE_USERS . ' u ON u.user_id=tu.testuser_user_id
        WHERE tu.testuser_test_id=' . $test_id . '
            AND tu.testuser_status>0
            AND tu.testuser_status<4
        ORDER BY u.user_lastname, u.user_firstname, u.user_name';
    $attempts_result = F_db_query($attempts_sql, $db);
    while ($attempts_result && ($attempt = F_db_fetch_array($attempts_result))) {
        $attempts[] = $attempt;
    }
}

require_once 'tce_page_header.php';

echo '<div class="monitor-panel">';
echo f_openvsosh_admin_test_context($test_id, 'offline');
echo '<p class="pagehelp">Экспорт создаёт самодостаточную HTML-страницу, которая не обращается к сети. '
    . 'Результат подписан, ограничен участником, попыткой и сроком и импортируется только один раз.</p>';
if ($action_status !== '') {
    $success = in_array($action_status, ['imported', 'duplicate'], true);
    echo '<p class="monitor-message" role="status">'
        . ($success ? 'Результат принят: ' : 'Операция не выполнена: ')
        . htmlspecialchars($action_status, ENT_QUOTES, $l['a_meta_charset']) . '</p>';
}
echo '<form action="tce_offline.php" method="get" class="monitor-filters">';
echo '<label for="test_id">Тест</label><select name="test_id" id="test_id" required="required">';
echo '<option value="">Выберите тест</option>';
foreach ($tests as $available_test_id => $test) {
    $selected = $available_test_id === $test_id ? ' selected="selected"' : '';
    echo '<option value="' . $available_test_id . '"' . $selected . '>'
        . htmlspecialchars((string) $test['test_name'], ENT_QUOTES, $l['a_meta_charset']) . '</option>';
}
echo '</select><button type="submit">Показать</button></form>';

if ($test_id > 0) {
    echo '<section><h2>Выдать пакет</h2><form action="tce_offline.php" method="post">';
    echo '<input type="hidden" name="test_id" value="' . $test_id . '" />';
    echo '<label for="testuser_id">Участник</label><select name="testuser_id" id="testuser_id" required="required">';
    echo '<option value="">Выберите участника</option>';
    foreach ($attempts as $attempt) {
        $display = trim((string) $attempt['user_lastname'] . ' ' . (string) $attempt['user_firstname']);
        if ($display === '') {
            $display = (string) $attempt['user_name'];
        }
        echo '<option value="' . (int) $attempt['testuser_id'] . '">'
            . htmlspecialchars($display . ' (' . $attempt['user_name'] . ')', ENT_QUOTES, $l['a_meta_charset'])
            . '</option>';
    }
    echo '</select>' . F_getCSRFTokenField()
        . '<button type="submit" name="export_offline" value="1">Скачать автономный пакет</button>'
        . '</form></section>';
}

echo '<section><h2>Импортировать результат</h2>'
    . '<form action="tce_offline.php' . ($test_id > 0 ? '?test_id=' . $test_id : '')
    . '" method="post" enctype="multipart/form-data">'
    . '<label for="result_file">JSON-файл результата</label>'
    . '<input type="file" name="result_file" id="result_file" accept="application/json,.json" required="required" />'
    . F_getCSRFTokenField()
    . '<button type="submit" name="import_offline" value="1">Проверить и импортировать</button>'
    . '</form></section>';

if ($test_id > 0) {
    $packages_sql = 'SELECT p.*, u.user_name
        FROM ' . F_tmf_offline_table() . ' p
        INNER JOIN ' . K_TABLE_USERS . ' u ON u.user_id=p.offline_user_id
        WHERE p.offline_test_id=' . $test_id . '
        ORDER BY p.offline_issued_at DESC
        LIMIT 100';
    $packages_result = F_db_query($packages_sql, $db);
    echo '<section class="monitor-table-wrap"><h2>Выданные пакеты</h2><table class="monitor-table">'
        . '<thead><tr><th>Участник</th><th>Выдан</th><th>Истекает</th><th>Статус</th></tr></thead><tbody>';
    while ($packages_result && ($package = F_db_fetch_array($packages_result))) {
        echo '<tr><td>' . htmlspecialchars((string) $package['user_name'], ENT_QUOTES, $l['a_meta_charset'])
            . '</td><td>' . htmlspecialchars((string) $package['offline_issued_at'], ENT_QUOTES, $l['a_meta_charset'])
            . '</td><td>' . htmlspecialchars((string) $package['offline_expires_at'], ENT_QUOTES, $l['a_meta_charset'])
            . '</td><td>' . htmlspecialchars((string) $package['offline_status'], ENT_QUOTES, $l['a_meta_charset'])
            . '</td></tr>';
    }
    echo '</tbody></table></section>';
}
echo '</div>';

require_once '../code/tce_page_footer.php';
