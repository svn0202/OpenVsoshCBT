<?php

/**
 * Monitoring helpers for active test attempts.
 */

const TMF_MONITOR_LOST_AFTER_SECONDS = 180;
const TMF_MONITOR_ACTIONS = ['block', 'unblock', 'extend', 'reset'];

function f_tmf_focus_event_is_valid(string $event_id): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $event_id) === 1;
}

function f_tmf_monitor_audit_table(): string
{
    return K_TABLE_PREFIX . 'monitor_audit';
}

function f_tmf_monitor_action_is_valid(string $action): bool
{
    return in_array($action, TMF_MONITOR_ACTIONS, true);
}

/** @return 'blocked'|'completed'|'connection_lost'|'in_progress'|'not_started'|'timed_out' */
function f_tmf_monitor_status(
    ?int $attempt_status,
    ?string $close_reason,
    ?string $last_activity,
    int $now,
    int $lost_after = TMF_MONITOR_LOST_AFTER_SECONDS,
): string {
    if ($attempt_status === null) {
        return 'not_started';
    }
    if ($close_reason === 'blocked') {
        return 'blocked';
    }
    if ($close_reason === 'timeout') {
        return 'timed_out';
    }
    if ($close_reason === 'completed' || $attempt_status >= 3) {
        return 'completed';
    }
    if ($last_activity === null || (int) strtotime($last_activity) < ($now - $lost_after)) {
        return 'connection_lost';
    }
    return 'in_progress';
}

function f_tmf_monitor_attempt_is_authorized(int $testuser_id): bool
{
    require_once '../config/tce_config.php';
    global $db;

    $sql = 'SELECT testuser_test_id
        FROM ' . K_TABLE_TEST_USER . '
        WHERE testuser_id=' . $testuser_id . '
        LIMIT 1';
    $row = f_tmf_monitor_fetch_row(F_db_query($sql, $db));
    if ($row === null) {
        return false;
    }
    /** @var array{testuser_test_id:mixed} $row */
    return f_is_authorized_user(K_TABLE_TESTS, 'test_id', (int) $row['testuser_test_id'], 'test_user_id');
}

/**
 * Apply an audited operator action to one attempt.
 *
 * Reset archives the old attempt and generates a new one, preserving the old
 * answers for audit and review.
 *
 * @return array{status:string,testuser_id:int,new_testuser_id?:int}
 */
function f_tmf_monitor_apply_action(
    int $testuser_id,
    string $action,
    int $extend_minutes = 0,
): array {
    require_once '../config/tce_config.php';
    global $db;

    if (
        $testuser_id <= 0
        || !F_tmf_monitor_action_is_valid($action)
        || ($action === 'extend' && ($extend_minutes < 1 || $extend_minutes > 60))
        || !F_tmf_monitor_attempt_is_authorized($testuser_id)
    ) {
        return ['status' => 'forbidden', 'testuser_id' => $testuser_id];
    }
    if (!f_tmf_monitor_query_succeeded(F_db_query('START TRANSACTION', $db))) {
        return ['status' => 'error', 'testuser_id' => $testuser_id];
    }

    try {
        $sql = 'SELECT testuser_test_id, testuser_user_id, testuser_status,
                testuser_creation_time, testuser_close_reason
            FROM ' . K_TABLE_TEST_USER . '
            WHERE testuser_id=' . $testuser_id . '
            FOR UPDATE';
        $attempt = f_tmf_monitor_fetch_row(F_db_query($sql, $db));
        if ($attempt === null) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'not_found', 'testuser_id' => $testuser_id];
        }

        /** @var array{
         *     testuser_test_id:mixed,
         *     testuser_user_id:mixed,
         *     testuser_status:mixed,
         *     testuser_creation_time:mixed,
         *     testuser_close_reason:mixed
         * } $attempt
         */
        $test_id = (int) $attempt['testuser_test_id'];
        $target_user_id = (int) $attempt['testuser_user_id'];
        $now = date(K_TIMESTAMP_FORMAT);
        $details = null;
        $new_testuser_id = 0;

        if ($action === 'block') {
            if (
                (int) $attempt['testuser_status'] < 1
                || (int) $attempt['testuser_status'] > 2
            ) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'invalid_state', 'testuser_id' => $testuser_id];
            }
            $sql = 'UPDATE ' . K_TABLE_TEST_USER . "
                SET testuser_status=4,
                    testuser_close_reason='blocked',
                    testuser_last_activity='" . $now . "'
                WHERE testuser_id=" . $testuser_id;
        } elseif ($action === 'unblock') {
            if (
                (int) $attempt['testuser_status'] !== 4
                || (string) $attempt['testuser_close_reason'] !== 'blocked'
            ) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'invalid_state', 'testuser_id' => $testuser_id];
            }
            $sql = 'UPDATE ' . K_TABLE_TEST_USER . "
                SET testuser_status=1,
                    testuser_close_reason=NULL,
                    testuser_last_activity='" . $now . "'
                WHERE testuser_id=" . $testuser_id;
        } elseif ($action === 'extend') {
            if (
                (int) $attempt['testuser_status'] < 1
                || (int) $attempt['testuser_status'] >= 5
                || (
                    (int) $attempt['testuser_status'] === 4
                    && (string) $attempt['testuser_close_reason'] !== 'timeout'
                )
            ) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'invalid_state', 'testuser_id' => $testuser_id];
            }
            $new_start = date(
                K_TIMESTAMP_FORMAT,
                (int) strtotime((string) $attempt['testuser_creation_time'])
                    + ($extend_minutes * K_SECONDS_IN_MINUTE),
            );
            $details = 'minutes=' . $extend_minutes;
            $reopen_sql = (int) $attempt['testuser_status'] === 4
                ? ",
                    testuser_status=1,
                    testuser_close_reason=NULL"
                : '';
            $sql = 'UPDATE ' . K_TABLE_TEST_USER . "
                SET testuser_creation_time='" . $new_start . "',
                    testuser_last_activity='" . $now . "'"
                . $reopen_sql
                . '
                WHERE testuser_id=' . $testuser_id;
        } else {
            $status_row = f_tmf_monitor_fetch_row(F_db_query(
                'SELECT MAX(testuser_status) AS max_status
                FROM ' . K_TABLE_TEST_USER . '
                WHERE testuser_test_id=' . $test_id . '
                    AND testuser_user_id=' . $target_user_id,
                $db,
            ));
            /** @var array{max_status?:mixed}|null $status_row */
            $archive_status = max(5, (int) ($status_row['max_status'] ?? 4) + 1);
            $sql = 'UPDATE ' . K_TABLE_TEST_USER . "
                SET testuser_status=" . $archive_status . ",
                    testuser_close_reason='reset',
                    testuser_last_activity='" . $now . "'
                WHERE testuser_id=" . $testuser_id;
        }

        if (!f_tmf_monitor_query_succeeded(F_db_query($sql, $db))) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error', 'testuser_id' => $testuser_id];
        }

        if ($action === 'reset') {
            if (!f_create_test($test_id, $target_user_id)) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'error', 'testuser_id' => $testuser_id];
            }
            $new_attempt = f_tmf_monitor_fetch_row(F_db_query(
                'SELECT testuser_id
                FROM ' . K_TABLE_TEST_USER . '
                WHERE testuser_test_id=' . $test_id . '
                    AND testuser_user_id=' . $target_user_id . '
                    AND testuser_status<5
                ORDER BY testuser_id DESC
                LIMIT 1',
                $db,
            ));
            /** @var array{testuser_id?:mixed}|null $new_attempt */
            $new_testuser_id = (int) ($new_attempt['testuser_id'] ?? 0);
            if ($new_testuser_id <= 0) {
                F_db_query('ROLLBACK', $db);
                return ['status' => 'error', 'testuser_id' => $testuser_id];
            }
            $details = 'new_testuser_id=' . $new_testuser_id;
        }

        $details_sql = $details === null ? 'NULL' : "'" . F_escape_sql($db, $details) . "'";
        $ip = F_escape_sql($db, (string) get_normalized_ip($_SERVER['REMOTE_ADDR'] ?? ''));
        /** @mago-expect analysis:possibly-undefined-string-array-index */
        $actor_user_id = (int) $_SESSION['session_user_id'];
        $audit_sql = 'INSERT INTO ' . F_tmf_monitor_audit_table() . ' (
                monitor_audit_time,
                monitor_actor_user_id,
                monitor_testuser_id,
                monitor_test_id,
                monitor_target_user_id,
                monitor_action,
                monitor_details,
                monitor_ip
            ) VALUES (
                \'' . $now . '\',
                ' . $actor_user_id . ',
                ' . $testuser_id . ',
                ' . $test_id . ',
                ' . $target_user_id . ",
                '" . F_escape_sql($db, $action) . "',
                " . $details_sql . ",
                '" . $ip . "'
            )";
        if (
            !f_tmf_monitor_query_succeeded(F_db_query($audit_sql, $db))
            || !f_tmf_monitor_query_succeeded(F_db_query('COMMIT', $db))
        ) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error', 'testuser_id' => $testuser_id];
        }

        $response = ['status' => 'updated', 'testuser_id' => $testuser_id];
        if ($new_testuser_id > 0) {
            $response['new_testuser_id'] = $new_testuser_id;
        }
        return $response;
    } catch (Throwable) {
        F_db_query('ROLLBACK', $db);
        return ['status' => 'error', 'testuser_id' => $testuser_id];
    }
}

function f_tmf_monitor_query_succeeded(mixed $result): bool
{
    return (bool) f_tmf_monitor_query_result($result);
}

/** @return array<array-key,mixed>|null */
function f_tmf_monitor_fetch_row(mixed $result): ?array
{
    $result = f_tmf_monitor_query_result($result);
    if (!$result) {
        return null;
    }
    return f_tmf_monitor_row(F_db_fetch_array($result));
}

/** @return array<array-key,mixed>|null */
function f_tmf_monitor_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool */
function f_tmf_monitor_query_result(mixed $result): mixed
{
    if (
        is_bool($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
}
