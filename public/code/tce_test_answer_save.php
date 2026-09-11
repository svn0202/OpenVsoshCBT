<?php

ob_start();
require_once __DIR__ . '/../../shared/code/tce_functions_request_log.php';
openvsosh_request_id();

define('OPENVSOSH_ANSWER_API', true);

/**
 * @param array<array-key, mixed> $payload
 */
function f_tmf_answer_json(int $status_code, array $payload): never
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $payload['request_id'] = openvsosh_request_id();
    $input = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : $_POST;
    $integer = static function (mixed $value): ?int {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return is_int($parsed) ? $parsed : null;
    };
    $operation = $input['answer_operation'] ?? '';
    openvsosh_log_answer_event('', [
        'testid' => $integer($input['testid'] ?? null),
        'testlogid' => $integer($input['testlogid'] ?? null),
        'expected_version' => $integer($input['answer_version'] ?? null),
        'operation_id' => is_string($operation) && preg_match('/^[a-f0-9]{32}$/D', $operation) === 1 ? $operation : null,
        'status' => $payload['status'] ?? 'error', 'http_status' => $status_code,
    ]);
    http_response_code($status_code);
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_PUBLIC_TEST_EXECUTE;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_test.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once '../../shared/code/tce_functions_answer_access.php';

$refresh = $_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'refresh_csrf';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$refresh) {
    header('Allow: GET, POST');
    F_tmf_answer_json(405, ['status' => 'method_not_allowed']);
}
$input = $refresh ? $_GET : $_POST;
$test_id = filter_var($input['testid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$test_id = is_int($test_id) ? $test_id : 0;
$testlog_id = filter_var($input['testlogid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$testlog_id = is_int($testlog_id) ? $testlog_id : 0;
if (!$test_id || !$testlog_id) {
    F_tmf_answer_json(422, ['status' => 'invalid_request']);
}
$access = f_tmf_answer_access($test_id, $testlog_id);
if ($access !== 'allowed') {
    F_tmf_answer_json($access === 'error' ? 500 : 403, ['status' => $access]);
}
if ($refresh) {
    F_tmf_answer_json(200, [
        'status' => 'csrf_refreshed',
        'csrf_token' => get_password_hash(get_plain_csrf_token_for_script(__DIR__ . '/tce_test_execute.php')),
    ]);
}
if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token'])
    || !check_csrf_token_for_script($_POST['csrf_token'], __DIR__ . '/tce_test_execute.php')) {
    F_tmf_answer_json(403, ['status' => 'csrf_failed']);
}

$expected_version = isset($_POST['answer_version']) && is_numeric($_POST['answer_version'])
    ? (int) $_POST['answer_version']
    : -1;
$operation_id = isset($_POST['answer_operation']) && is_string($_POST['answer_operation'])
    ? $_POST['answer_operation']
    : '';
$answer_text = isset($_POST['answertext']) && is_string($_POST['answertext']) ? $_POST['answertext'] : '';
$reaction_time = isset($_POST['reaction_time']) && is_numeric($_POST['reaction_time'])
    ? max(0, (int) $_POST['reaction_time'])
    : 0;
$answer_positions = [];
if (isset($_POST['answpos'])) {
    if (is_array($_POST['answpos'])) {
        foreach ($_POST['answpos'] as $position => $value) {
            if (!is_numeric($position) || !is_numeric($value)) {
                F_tmf_answer_json(422, ['status' => 'invalid']);
            }
            $answer_positions[(int) $position] = (int) $value;
        }
    } elseif (is_numeric($_POST['answpos'])) {
        $answer_positions[(int) $_POST['answpos']] = 1;
    } else {
        F_tmf_answer_json(422, ['status' => 'invalid']);
    }
}

if (!f_tmf_answer_operation_is_valid($operation_id) || $expected_version < 0) {
    F_tmf_answer_json(422, ['status' => 'invalid_request']);
}
if (!f_execute_test($test_id)) {
    $reason = f_tmf_answer_access($test_id, $testlog_id);
    F_tmf_answer_json($reason === 'error' ? 500 : 403,
        ['status' => $reason === 'allowed' ? 'access_denied' : $reason]);
}

$result = F_tmf_save_question_answer(
    $test_id,
    $testlog_id,
    $answer_positions,
    $answer_text,
    $reaction_time,
    $expected_version,
    $operation_id,
);

if ($result['status'] === 'saved') {
    F_tmf_answer_json(200, $result);
}
if ($result['status'] === 'conflict') {
    F_tmf_answer_json(409, $result);
}
if ($result['status'] === 'invalid') {
    F_tmf_answer_json(422, $result);
}
F_tmf_answer_json(500, $result);
