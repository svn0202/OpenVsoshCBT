<?php

/** Classify only an owned attempt; never reveal another participant's state.
 * @param array<array-key,mixed>|null $row
 */
function f_tmf_answer_access_reason(?array $row, int $test_id, int $user_id, int $now): string
{
    if ($row === null || (int) ($row['testuser_user_id'] ?? 0) !== $user_id
        || (int) ($row['testuser_test_id'] ?? 0) !== $test_id) {
        return 'access_denied';
    }
    $reason = (string) ($row['testuser_close_reason'] ?? '');
    if ($reason === 'blocked') {
        return 'attempt_blocked';
    }
    if ($reason === 'timeout') {
        return 'time_expired';
    }
    if ((int) ($row['testuser_status'] ?? 0) >= 4) {
        return 'attempt_closed';
    }
    $begin = strtotime((string) ($row['test_begin_time'] ?? ''));
    $end = strtotime((string) ($row['test_end_time'] ?? ''));
    $created = strtotime((string) ($row['testuser_creation_time'] ?? ''));
    if ($begin === false || $end === false || $created === false) {
        return 'error';
    }
    if ($now >= $end || (!f_get_boolean($row['testuser_pregenerated'] ?? false)
        && $now > $created + (int) ($row['test_duration_time'] ?? 0) * 60)) {
        return 'time_expired';
    }
    if ($now < $begin || (int) ($row['testuser_status'] ?? 0) < 1
        || f_get_boolean($row['testuser_pregenerated'] ?? false)) {
        return 'access_denied';
    }
    return 'allowed';
}

function f_tmf_answer_access(int $test_id, int $testlog_id): string
{
    global $db;
    $sql = 'SELECT u.testuser_user_id,u.testuser_test_id,u.testuser_status,u.testuser_close_reason,
        u.testuser_creation_time,u.testuser_pregenerated,t.test_begin_time,t.test_end_time,t.test_duration_time
        FROM ' . K_TABLE_TESTS_LOGS . ' l JOIN ' . K_TABLE_TEST_USER . ' u ON u.testuser_id=l.testlog_testuser_id
        JOIN ' . K_TABLE_TESTS . ' t ON t.test_id=u.testuser_test_id WHERE l.testlog_id=' . $testlog_id;
    $result = F_db_query($sql, $db);
    if ($result === false) {
        return 'error';
    }
    $row = F_db_fetch_array($result);
    return f_tmf_answer_access_reason(is_array($row) ? $row : null, $test_id,
        (int) ($_SESSION['session_user_id'] ?? 0), time());
}
