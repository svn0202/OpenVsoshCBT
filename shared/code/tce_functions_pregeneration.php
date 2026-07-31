<?php

/**
 * Safe participant-specific test pre-generation.
 */

const TMF_PREGENERATION_BATCH_MAX = 25;

/**
 * Return query rows in a stable, JSON-safe representation.
 */
function F_tmf_pregeneration_hash_rows(string $sql): array
{
    require_once '../config/tce_config.php';
    global $db;

    $rows = [];
    $result = F_db_query($sql, $db);
    while ($result && ($row = F_db_fetch_assoc($result))) {
        ksort($row);
        foreach ($row as $key => $value) {
            if (is_resource($value)) {
                $row[$key] = stream_get_contents($value);
            } elseif (is_object($value) && method_exists($value, 'load')) {
                $row[$key] = $value->load();
            } elseif (is_object($value)) {
                $row[$key] = (string) $value;
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Hash every input that can affect a generated participant variant.
 */
function F_tmf_pregeneration_hash(int $test_id, int $user_id): string
{
    $test_id = max(0, $test_id);
    $user_id = max(0, $user_id);
    $queries = [
        'test' => 'SELECT * FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . $test_id,
        'sets' => 'SELECT ts.*, ss.subjset_subject_id
            FROM ' . K_TABLE_TEST_SUBJSET . ' ts
            LEFT JOIN ' . K_TABLE_SUBJECT_SET . ' ss ON ss.subjset_tsubset_id=ts.tsubset_id
            WHERE ts.tsubset_test_id=' . $test_id . '
            ORDER BY ts.tsubset_id, ss.subjset_subject_id',
        'questions' => 'SELECT q.*
            FROM ' . K_TABLE_QUESTIONS . ' q
            WHERE q.question_subject_id IN (
                SELECT ss.subjset_subject_id
                FROM ' . K_TABLE_TEST_SUBJSET . ' ts
                INNER JOIN ' . K_TABLE_SUBJECT_SET . ' ss ON ss.subjset_tsubset_id=ts.tsubset_id
                WHERE ts.tsubset_test_id=' . $test_id . '
            )
            ORDER BY q.question_id',
        'answers' => 'SELECT a.*
            FROM ' . K_TABLE_ANSWERS . ' a
            INNER JOIN ' . K_TABLE_QUESTIONS . ' q ON q.question_id=a.answer_question_id
            WHERE q.question_subject_id IN (
                SELECT ss.subjset_subject_id
                FROM ' . K_TABLE_TEST_SUBJSET . ' ts
                INNER JOIN ' . K_TABLE_SUBJECT_SET . ' ss ON ss.subjset_tsubset_id=ts.tsubset_id
                WHERE ts.tsubset_test_id=' . $test_id . '
            )
            ORDER BY a.answer_id',
        'test_groups' => 'SELECT tstgrp_group_id
            FROM ' . K_TABLE_TEST_GROUPS . '
            WHERE tstgrp_test_id=' . $test_id . '
            ORDER BY tstgrp_group_id',
        'user_groups' => 'SELECT usrgrp_group_id
            FROM ' . K_TABLE_USERGROUP . '
            WHERE usrgrp_user_id=' . $user_id . '
            ORDER BY usrgrp_group_id',
    ];

    $source = ['test_id' => $test_id, 'user_id' => $user_id];
    foreach ($queries as $name => $sql) {
        $source[$name] = F_tmf_pregeneration_hash_rows($sql);
    }
    return hash(
        'sha256',
        json_encode(
            $source,
            JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ),
    );
}

/**
 * Return the public catalogue status for an attempt.
 *
 * A pre-generated attempt is only a prepared variant. It must look like a
 * test that can be started until the participant actually opens it.
 */
function F_tmf_catalog_test_status(int $test_status, bool $pregenerated): int
{
    return $pregenerated && $test_status === 1 ? 0 : $test_status;
}

/**
 * Delete unopened pre-generated attempts whose source inputs changed.
 */
function F_tmf_pregeneration_invalidate(int $test_id, ?int $user_id = null): int
{
    require_once '../config/tce_config.php';
    global $db;

    $sql = 'SELECT testuser_id, testuser_user_id, testuser_generation_hash
        FROM ' . K_TABLE_TEST_USER . '
        WHERE testuser_test_id=' . $test_id . '
            AND testuser_status=1
            AND testuser_pregenerated=\'1\'';
    if ($user_id !== null) {
        $sql .= ' AND testuser_user_id=' . $user_id;
    }
    $result = F_db_query($sql, $db);
    $removed = 0;
    while ($result && ($attempt = F_db_fetch_array($result))) {
        $current_hash = F_tmf_pregeneration_hash($test_id, (int) $attempt['testuser_user_id']);
        if (!hash_equals((string) $attempt['testuser_generation_hash'], $current_hash)) {
            $delete = F_db_query(
                'DELETE FROM ' . K_TABLE_TEST_USER . '
                WHERE testuser_id=' . (int) $attempt['testuser_id'] . '
                    AND testuser_status=1
                    AND testuser_pregenerated=\'1\'',
                $db,
            );
            if ($delete) {
                ++$removed;
            }
        }
    }
    return $removed;
}

/**
 * Validate and claim an unopened pre-generated attempt on first entry.
 *
 * @return string "none", "activated" or "invalidated"
 */
function F_tmf_pregeneration_activate(int $test_id, int $user_id): string
{
    require_once '../config/tce_config.php';
    global $db;

    $sql = 'SELECT testuser_id, testuser_generation_hash
        FROM ' . K_TABLE_TEST_USER . '
        WHERE testuser_test_id=' . $test_id . '
            AND testuser_user_id=' . $user_id . '
            AND testuser_status=1
            AND testuser_pregenerated=\'1\'
        ORDER BY testuser_id DESC
        LIMIT 1';
    $result = F_db_query($sql, $db);
    $attempt = $result ? F_db_fetch_array($result) : false;
    if (!is_array($attempt)) {
        return 'none';
    }
    $current_hash = F_tmf_pregeneration_hash($test_id, $user_id);
    if (!hash_equals((string) $attempt['testuser_generation_hash'], $current_hash)) {
        F_db_query(
            'DELETE FROM ' . K_TABLE_TEST_USER . '
            WHERE testuser_id=' . (int) $attempt['testuser_id'] . '
                AND testuser_status=1
                AND testuser_pregenerated=\'1\'',
            $db,
        );
        return 'invalidated';
    }
    $started_at = date(K_TIMESTAMP_FORMAT);
    $updated = F_db_query(
        'UPDATE ' . K_TABLE_TEST_USER . '
        SET testuser_pregenerated=\'0\',
            testuser_creation_time=\'' . $started_at . '\',
            testuser_last_activity=\'' . $started_at . '\'
        WHERE testuser_id=' . (int) $attempt['testuser_id'] . '
            AND testuser_status=1
            AND testuser_pregenerated=\'1\'',
        $db,
    );
    return $updated ? 'activated' : 'none';
}

/**
 * Generate one unopened attempt using the standard server-side generator.
 */
function F_tmf_pregenerate_user(int $test_id, int $user_id): string
{
    require_once '../config/tce_config.php';
    global $db;

    F_tmf_pregeneration_invalidate($test_id, $user_id);
    if (!F_db_query('START TRANSACTION', $db)) {
        return 'error';
    }
    try {
        $existing = F_count_rows(
            K_TABLE_TEST_USER,
            'WHERE testuser_test_id=' . $test_id . '
                AND testuser_user_id=' . $user_id . '
                AND testuser_status<5',
        );
        if ($existing > 0) {
            F_db_query('COMMIT', $db);
            return 'exists';
        }

        $hash = F_tmf_pregeneration_hash($test_id, $user_id);
        if (!F_createTest($test_id, $user_id)) {
            F_db_query('ROLLBACK', $db);
            return 'error';
        }
        $result = F_db_query(
            'SELECT testuser_id
            FROM ' . K_TABLE_TEST_USER . '
            WHERE testuser_test_id=' . $test_id . '
                AND testuser_user_id=' . $user_id . '
                AND testuser_status=1
            ORDER BY testuser_id DESC
            LIMIT 1',
            $db,
        );
        $attempt = $result ? F_db_fetch_array($result) : false;
        if (!is_array($attempt)) {
            F_db_query('ROLLBACK', $db);
            return 'error';
        }
        $updated = F_db_query(
            'UPDATE ' . K_TABLE_TEST_USER . "
            SET testuser_generation_hash='" . $hash . "',
                testuser_pregenerated='1'
            WHERE testuser_id=" . (int) $attempt['testuser_id'] . '
                AND testuser_status=1',
            $db,
        );
        if (!$updated || !F_db_query('COMMIT', $db)) {
            F_db_query('ROLLBACK', $db);
            return 'error';
        }
        return 'generated';
    } catch (Throwable) {
        F_db_query('ROLLBACK', $db);
        return 'error';
    }
}

/**
 * Eligible participants assigned through a test group.
 *
 * @return list<int>
 */
function F_tmf_pregeneration_eligible_users(int $test_id): array
{
    require_once '../config/tce_config.php';
    global $db;

    $ids = [];
    $sql = 'SELECT DISTINCT ug.usrgrp_user_id
        FROM ' . K_TABLE_USERGROUP . ' ug
        INNER JOIN ' . K_TABLE_TEST_GROUPS . ' tg ON tg.tstgrp_group_id=ug.usrgrp_group_id
        WHERE tg.tstgrp_test_id=' . $test_id . '
        ORDER BY ug.usrgrp_user_id';
    $result = F_db_query($sql, $db);
    while ($result && ($row = F_db_fetch_array($result))) {
        $ids[] = (int) $row['usrgrp_user_id'];
    }
    return $ids;
}
