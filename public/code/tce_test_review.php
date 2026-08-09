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
function f_tmf_review_json(int $status_code, array $payload): never
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
    F_tmf_review_json(405, ['status' => 'method_not_allowed']);
}
if (
    !isset($_POST['csrf_token'])
    || !is_string($_POST['csrf_token'])
    || !checkCSRFTokenForScript($_POST['csrf_token'], __DIR__ . '/tce_test_execute.php')
) {
    F_tmf_review_json(403, ['status' => 'csrf_failed']);
}

$test_id = isset($_POST['testid']) && is_numeric($_POST['testid']) ? (int) $_POST['testid'] : 0;
$testlog_id = isset($_POST['testlogid']) && is_numeric($_POST['testlogid']) ? (int) $_POST['testlogid'] : 0;
$reviewed = f_tmf_review_value($_POST['reviewed'] ?? null);

if (
    $test_id <= 0
    || $testlog_id <= 0
    || !F_isRightTestlogUser($test_id, $testlog_id)
    || !F_executeTest($test_id)
) {
    F_tmf_review_json(403, ['status' => 'forbidden']);
}

$sql = 'UPDATE ' . K_TABLE_TESTS_LOGS
    . ' SET testlog_reviewed=' . $reviewed
    . ' WHERE testlog_id=' . $testlog_id;
if (!F_db_query($sql, $db)) {
    F_tmf_review_json(500, ['status' => 'error']);
}

F_tmf_review_json(200, ['status' => 'saved', 'reviewed' => $reviewed === 1]);
