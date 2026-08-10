<?php

require_once '../config/tce_config.php';
$pagelevel = K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_test_stats.php';
require_once '../../shared/code/tce_functions_xlsx.php';

$test_id = isset($_REQUEST['test_id']) ? (int) $_REQUEST['test_id'] : 0;
if ($test_id <= 0 || !f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
    http_response_code(403);
    exit();
}
$group_id = isset($_REQUEST['group_id']) ? max(0, (int) $_REQUEST['group_id']) : 0;
$user_id = isset($_REQUEST['user_id']) ? max(0, (int) $_REQUEST['user_id']) : 0;
$startdate = isset($_REQUEST['startdate']) && is_string($_REQUEST['startdate'])
    ? date(K_TIMESTAMP_FORMAT, strtotime($_REQUEST['startdate'])) : 0;
$enddate = isset($_REQUEST['enddate']) && is_string($_REQUEST['enddate'])
    ? date(K_TIMESTAMP_FORMAT, strtotime($_REQUEST['enddate'])) : 0;

$data = F_getAllUsersTestStat(
    $test_id,
    $group_id,
    $user_id,
    $startdate,
    $enddate,
    'user_lastname,user_firstname,user_name',
    false,
    1,
);
$rows = [[
    'attempt_id', 'user_id', 'login', 'last_name', 'first_name', 'started_at', 'finished_at',
    'duration', 'score', 'score_percent', 'passed', 'correct', 'wrong', 'unanswered', 'unrated',
]];
foreach ($data['testuser'] as $attempt) {
    $rows[] = [
        ['value' => (int) $attempt['id'], 'type' => 'number'],
        ['value' => (int) $attempt['user_id'], 'type' => 'number'],
        $attempt['user_name'],
        $attempt['user_lastname'],
        $attempt['user_firstname'],
        $attempt['testuser_creation_time'],
        $attempt['testuser_end_time'],
        $attempt['time_diff'],
        ['value' => f_format_float($attempt['total_score']), 'type' => 'number'],
        ['value' => (string) $attempt['total_score_perc'], 'type' => 'number'],
        $attempt['passmsg'] ? 'Да' : 'Нет',
        ['value' => (int) $attempt['right'], 'type' => 'number'],
        ['value' => (int) $attempt['wrong'], 'type' => 'number'],
        ['value' => (int) $attempt['unanswered'], 'type' => 'number'],
        ['value' => (int) $attempt['unrated'], 'type' => 'number'],
    ];
}
$bytes = F_tmf_xlsx_build([[
    'name' => 'Результаты теста ' . $test_id,
    'widths' => [12, 10, 20, 22, 22, 22, 22, 12, 12, 16, 10, 12, 12, 14, 12],
    'rows' => $rows,
]]);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="openvsosh-results-' . $test_id . '-' . date('Ymd-His') . '.xlsx"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $bytes;
