<?php

ob_start();

require_once '../config/tce_config.php';
/** @var mixed $db Database connection initialized by tce_config.php. */

$pagelevel = K_AUTH_PUBLIC_TEST_EXECUTE;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_test.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/**
 * @param array<array-key, mixed> $payload
 */
function f_tmf_heartbeat_json(int $status_code, array $payload): never
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
    F_tmf_heartbeat_json(405, ['status' => 'method_not_allowed']);
}
if (
    !isset($_POST['csrf_token'])
    || !is_string($_POST['csrf_token'])
    || !checkCSRFTokenForScript($_POST['csrf_token'], __DIR__ . '/tce_test_execute.php')
) {
    F_tmf_heartbeat_json(403, ['status' => 'csrf_failed']);
}

$test_id = isset($_POST['testid']) && is_numeric($_POST['testid']) ? (int) $_POST['testid'] : 0;
$testlog_id = isset($_POST['testlogid']) && is_numeric($_POST['testlogid']) ? (int) $_POST['testlogid'] : 0;
if (
    $test_id <= 0
    || $testlog_id <= 0
    || !F_isRightTestlogUser($test_id, $testlog_id)
    || !F_executeTest($test_id)
) {
    F_tmf_heartbeat_json(403, ['status' => 'forbidden']);
}

$session_user_id = (int) ($_SESSION['session_user_id'] ?? 0);
$sql = 'UPDATE ' . K_TABLE_TEST_USER . "
    SET testuser_last_activity='" . date(K_TIMESTAMP_FORMAT) . "'
    WHERE testuser_test_id=" . $test_id . '
        AND testuser_user_id=' . $session_user_id . '
        AND testuser_status>0
        AND testuser_status<4';
$result = F_db_query($sql, $db);
if (!$result) {
    F_tmf_heartbeat_json(500, ['status' => 'error']);
}
if (F_db_affected_rows($db, $result) < 1) {
    F_tmf_heartbeat_json(409, ['status' => 'closed']);
}

F_tmf_heartbeat_json(200, ['status' => 'active']);
