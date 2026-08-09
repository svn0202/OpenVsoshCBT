<?php

/**
 * Remember that the current authenticated browser session has entered a test
 * password. The grant is scoped to the current user session and test ID, so an
 * administrator may rotate the shared token without ejecting participants.
 */
function F_tmf_test_session_unlock(int $test_id): void
{
    if (!isset($_SESSION['session_unlocked_tests']) || !is_array($_SESSION['session_unlocked_tests'])) {
        $_SESSION['session_unlocked_tests'] = [];
    }
    $_SESSION['session_unlocked_tests'][(string) $test_id] = [
        'user_id' => (int) ($_SESSION['session_user_id'] ?? 0),
        'unlocked_at' => time(),
    ];
}

function F_tmf_test_session_is_unlocked(int $test_id): bool
{
    $grant = $_SESSION['session_unlocked_tests'][(string) $test_id] ?? null;
    return is_array($grant)
        && (int) ($grant['user_id'] ?? 0) > 0
        && (int) ($grant['user_id'] ?? 0) === (int) ($_SESSION['session_user_id'] ?? 0);
}

/**
 * @return array{allowed:bool,reason:string}
 */
function F_tmf_test_access_status(int $test_id, int $user_id): array
{
    global $db;
    if (F_count_rows(
        K_TABLE_TEST_USER,
        'WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id
        . ' AND testuser_status<4',
    ) > 0) {
        return ['allowed' => true, 'reason' => 'active_attempt'];
    }
    $result = F_db_query(
        'SELECT test_required_finished_id,test_required_passed_id FROM '
        . K_TABLE_TESTS . ' WHERE test_id=' . $test_id . ' LIMIT 1',
        $db,
    );
    if (!$result || !($test = F_db_fetch_array($result))) {
        return ['allowed' => false, 'reason' => 'test_not_found'];
    }
    $required_finished = (int) ($test['test_required_finished_id'] ?? 0);
    if ($required_finished > 0) {
        $finished = F_count_rows(
            K_TABLE_TEST_USER,
            'WHERE testuser_test_id=' . $required_finished
            . ' AND testuser_user_id=' . $user_id . ' AND testuser_status>=4',
        );
        if ($finished < 1) {
            return ['allowed' => false, 'reason' => 'required_test_not_finished'];
        }
    }
    $required_passed = (int) ($test['test_required_passed_id'] ?? 0);
    if ($required_passed > 0 && !F_tmf_user_has_passed_test($required_passed, $user_id)) {
        return ['allowed' => false, 'reason' => 'required_test_not_passed'];
    }
    return ['allowed' => true, 'reason' => 'allowed'];
}

function F_tmf_user_has_passed_test(int $test_id, int $user_id): bool
{
    global $db;
    $test_result = F_db_query(
        'SELECT test_score_threshold,test_max_score FROM ' . K_TABLE_TESTS
        . ' WHERE test_id=' . $test_id . ' LIMIT 1',
        $db,
    );
    if (!$test_result || !($test = F_db_fetch_array($test_result))) {
        return false;
    }
    $attempt_result = F_db_query(
        'SELECT testuser_id FROM ' . K_TABLE_TEST_USER
        . ' WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id
        . ' AND testuser_status>=4 ORDER BY testuser_id DESC',
        $db,
    );
    while ($attempt_result && ($attempt = F_db_fetch_array($attempt_result))) {
        $score_result = F_db_query(
            'SELECT SUM(testlog_score) AS total_score FROM ' . K_TABLE_TESTS_LOGS
            . ' WHERE testlog_testuser_id=' . (int) $attempt['testuser_id'],
            $db,
        );
        $score_row = $score_result ? F_db_fetch_array($score_result) : false;
        $score = (float) ($score_row['total_score'] ?? 0);
        $threshold = (float) $test['test_score_threshold'];
        if ($threshold > 0 ? $score >= $threshold : $score > ((float) $test['test_max_score'] / 2)) {
            return true;
        }
    }
    return false;
}

/**
 * Reject prerequisite graphs that lead back to the edited test.
 *
 * @param array<int,int> $prerequisite_ids
 */
function F_tmf_test_prerequisite_would_cycle(int $test_id, array $prerequisite_ids): bool
{
    global $db;
    $pending = array_values(array_filter(array_map('intval', $prerequisite_ids)));
    $visited = [];
    while ($pending !== []) {
        $candidate = array_pop($pending);
        if ($candidate === $test_id) {
            return true;
        }
        if (isset($visited[$candidate])) {
            continue;
        }
        $visited[$candidate] = true;
        if (count($visited) > 1000) {
            return true;
        }
        $result = F_db_query(
            'SELECT test_required_finished_id,test_required_passed_id FROM '
            . K_TABLE_TESTS . ' WHERE test_id=' . $candidate . ' LIMIT 1',
            $db,
        );
        if ($result && ($row = F_db_fetch_array($result))) {
            $pending[] = (int) ($row['test_required_finished_id'] ?? 0);
            $pending[] = (int) ($row['test_required_passed_id'] ?? 0);
        }
    }
    return false;
}

/**
 * @return array{allowed:bool,reason:string,details:int|float|null}
 */
function F_tmf_test_completion_status(int $test_id, int $user_id, ?int $now = null): array
{
    global $db;
    $result = F_db_query(
        'SELECT test_minimum_duration_time,test_require_all_answers,'
        . 'test_block_finish_below_threshold,test_score_threshold FROM '
        . K_TABLE_TESTS . ' WHERE test_id=' . $test_id . ' LIMIT 1',
        $db,
    );
    if (!$result || !($test = F_db_fetch_array($result))) {
        return ['allowed' => false, 'reason' => 'test_not_found', 'details' => null];
    }
    $attempt_result = F_db_query(
        'SELECT testuser_id,testuser_creation_time FROM ' . K_TABLE_TEST_USER
        . ' WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id
        . ' AND testuser_status<4 ORDER BY testuser_id DESC LIMIT 1',
        $db,
    );
    if (!$attempt_result || !($attempt = F_db_fetch_array($attempt_result))) {
        return ['allowed' => false, 'reason' => 'attempt_not_found', 'details' => null];
    }
    $minimum_seconds = max(0, (int) $test['test_minimum_duration_time']) * 60;
    $elapsed = ($now ?? time()) - strtotime((string) $attempt['testuser_creation_time']);
    if ($minimum_seconds > 0 && $elapsed < $minimum_seconds) {
        return [
            'allowed' => false,
            'reason' => 'minimum_duration',
            'details' => $minimum_seconds - max(0, $elapsed),
        ];
    }
    if (f_get_boolean($test['test_require_all_answers'])) {
        $unanswered = F_count_rows(
            K_TABLE_TESTS_LOGS,
            'WHERE testlog_testuser_id=' . (int) $attempt['testuser_id']
            . ' AND testlog_change_time IS NULL',
        );
        if ($unanswered > 0) {
            return ['allowed' => false, 'reason' => 'required_answers', 'details' => $unanswered];
        }
    }
    if (f_get_boolean($test['test_block_finish_below_threshold'])) {
        $score_result = F_db_query(
            'SELECT SUM(testlog_score) AS total_score FROM ' . K_TABLE_TESTS_LOGS
            . ' WHERE testlog_testuser_id=' . (int) $attempt['testuser_id'],
            $db,
        );
        $score_row = $score_result ? F_db_fetch_array($score_result) : false;
        $score = (float) ($score_row['total_score'] ?? 0);
        $threshold = (float) $test['test_score_threshold'];
        if ($threshold > 0 && $score < $threshold) {
            return ['allowed' => false, 'reason' => 'score_threshold', 'details' => $threshold];
        }
    }
    return ['allowed' => true, 'reason' => 'allowed', 'details' => null];
}

/**
 * Return one-based question numbers that still have no submitted answer.
 *
 * @return array<int,int>
 */
function F_tmf_unanswered_question_numbers(int $test_id, int $user_id): array
{
    global $db;
    $result = F_db_query(
        'SELECT tl.testlog_change_time FROM ' . K_TABLE_TESTS_LOGS . ' tl'
        . ' INNER JOIN ' . K_TABLE_TEST_USER . ' tu ON tu.testuser_id=tl.testlog_testuser_id'
        . ' WHERE tu.testuser_test_id=' . $test_id . ' AND tu.testuser_user_id=' . $user_id
        . ' AND tu.testuser_status<4 ORDER BY tl.testlog_id',
        $db,
    );
    $missing = [];
    $number = 0;
    while ($result && ($row = F_db_fetch_array($result))) {
        ++$number;
        if ($row['testlog_change_time'] === null || $row['testlog_change_time'] === '') {
            $missing[] = $number;
        }
    }
    return $missing;
}
