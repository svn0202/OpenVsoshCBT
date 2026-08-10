<?php

ob_start();

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_PUBLIC_TEST_EXECUTE;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_test.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/**
 * @param array<array-key, mixed> $payload
 */
function f_tmf_answer_json(int $status_code, array $payload): never
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
    F_tmf_answer_json(405, ['status' => 'method_not_allowed']);
}
if (
    !isset($_POST['csrf_token'])
    || !is_string($_POST['csrf_token'])
    || !check_csrf_token_for_script($_POST['csrf_token'], __DIR__ . '/tce_test_execute.php')
) {
    F_tmf_answer_json(403, ['status' => 'csrf_failed']);
}

$test_id = isset($_POST['testid']) && is_numeric($_POST['testid']) ? (int) $_POST['testid'] : 0;
$testlog_id = isset($_POST['testlogid']) && is_numeric($_POST['testlogid']) ? (int) $_POST['testlogid'] : 0;
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

if (
    $test_id <= 0
    || $testlog_id <= 0
    || !f_tmf_answer_operation_is_valid($operation_id)
    || !f_is_right_testlog_user($test_id, $testlog_id)
    || !f_execute_test($test_id)
) {
    F_tmf_answer_json(403, ['status' => 'forbidden']);
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
