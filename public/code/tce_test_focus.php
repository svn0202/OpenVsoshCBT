<?php

ob_start();

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_PUBLIC_TEST_EXECUTE;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_test.php';

/** @var mixed $db Database connection created by tce_authorization.php. */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/** @param array<string, int|string> $payload */
function f_tmf_focus_json(int $status_code, array $payload): never
{
    http_response_code($status_code);
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    F_tmf_focus_json(405, ['status' => 'method_not_allowed']);
}
if (
    !isset($_POST['csrf_token'])
    || !is_string($_POST['csrf_token'])
    || !check_csrf_token_for_script($_POST['csrf_token'], __DIR__ . '/tce_test_execute.php')
) {
    F_tmf_focus_json(403, ['status' => 'csrf_failed']);
}

$test_id = isset($_POST['testid']) && is_numeric($_POST['testid']) ? (int) $_POST['testid'] : 0;
$testlog_id = isset($_POST['testlogid']) && is_numeric($_POST['testlogid']) ? (int) $_POST['testlogid'] : 0;
$event_id = isset($_POST['event_id']) && is_string($_POST['event_id']) ? $_POST['event_id'] : '';
if (
    $test_id <= 0
    || $testlog_id <= 0
    || !F_tmf_focus_event_is_valid($event_id)
    || !f_is_right_testlog_user($test_id, $testlog_id)
    || !f_execute_test($test_id)
) {
    F_tmf_focus_json(403, ['status' => 'forbidden']);
}

$normalize_row = static fn(mixed $row): ?array => is_array($row) && $row !== [] ? $row : null;
$log_result = F_db_query(
    'SELECT testlog_testuser_id
    FROM ' . K_TABLE_TESTS_LOGS . '
    WHERE testlog_id=' . $testlog_id,
    $db,
);
/** @var \mysqli_result|\PgSql\Result|false $log_result */
$log = $log_result === false ? null : $normalize_row(F_db_fetch_array($log_result));
$testuser_id = (int) ($log['testlog_testuser_id'] ?? 0);
if ($testuser_id <= 0) {
    F_tmf_focus_json(403, ['status' => 'forbidden']);
}

$session_user_id = (int) ($_SESSION['session_user_id'] ?? 0);
$escaped_event_id = F_escape_sql($db, $event_id);
$sql = 'UPDATE ' . K_TABLE_TEST_USER . '
    SET testuser_focus_loss_count=testuser_focus_loss_count+1,
        testuser_last_focus_event=\'' . $escaped_event_id . '\'
    WHERE testuser_id=' . $testuser_id . '
        AND testuser_test_id=' . $test_id . '
        AND testuser_user_id=' . $session_user_id . '
        AND testuser_status>0
        AND testuser_status<4
        AND (testuser_last_focus_event IS NULL
            OR testuser_last_focus_event<>\'' . $escaped_event_id . '\')';
/** @var true|\mysqli_result|\PgSql\Result|false $result */
$result = F_db_query($sql, $db);
if (!$result) {
    F_tmf_focus_json(500, ['status' => 'error']);
}

$count_result = F_db_query(
    'SELECT testuser_focus_loss_count, testuser_last_focus_event
    FROM ' . K_TABLE_TEST_USER . '
    WHERE testuser_id=' . $testuser_id . '
        AND testuser_test_id=' . $test_id . '
        AND testuser_user_id=' . $session_user_id . '
        AND testuser_status>0
        AND testuser_status<4',
    $db,
);
/** @var \mysqli_result|\PgSql\Result|false $count_result */
$attempt = $count_result === false ? null : $normalize_row(F_db_fetch_array($count_result));
if ($attempt === null) {
    F_tmf_focus_json(409, ['status' => 'closed']);
}
if ((string) ($attempt['testuser_last_focus_event'] ?? '') !== $event_id) {
    F_tmf_focus_json(409, ['status' => 'conflict']);
}

F_tmf_focus_json(200, [
    'status' => 'recorded',
    'count' => (int) ($attempt['testuser_focus_loss_count'] ?? 0),
]);
