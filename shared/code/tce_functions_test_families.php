<?php

/**
 * Derive the default identity of an examination, not of an individual variant.
 * Preserve subject, year, grade and round numbers. Only variant labels and
 * punctuation are ignored. A different starting date is a separate sitting.
 */
function f_tmf_test_family_key(string $name, string $begin, string $explicit = ''): string
{
    $explicit = trim($explicit);
    if ($explicit !== '') {
        return 'explicit:' . $explicit;
    }
    $name = mb_strtolower($name, 'UTF-8');
    $name = (string) preg_replace(
        '/(?<![\p{L}\p{N}])(?:вариант|вар\.?|variant)\s*[-_№#:]?\s*(?:[0-9]+|[ivx]+|[абвгд])(?=$|[^\p{L}\p{N}])/u',
        ' ',
        $name,
    );
    $name = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
    if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $begin)) {
        return '';
    }
    return 'auto:' . substr($begin, 0, 10) . ':' . $name;
}

/**
 * Read all identities once per request. No installed ID list, subject list or
 * year list is involved: newly inserted/imported tests participate immediately.
 *
 * @return list<int>|null Null means the test/configuration could not be read.
 */
function f_tmf_test_family(int $test_id): ?array
{
    global $db;
    /** @var array<int,list<int>>|null $by_test */
    static $by_test = null;
    if ($by_test === null) {
        $result = F_db_query('SELECT test_id,test_name,test_begin_time,test_family_key FROM ' . K_TABLE_TESTS, $db);
        if ($result === false) {
            return null;
        }
        $groups = [];
        $keys = [];
        while (true) {
            $row = F_db_fetch_array($result);
            if (!is_array($row)) {
                break;
            }
            $id = (int) ($row['test_id'] ?? 0);
            $key = f_tmf_test_family_key(
                (string) ($row['test_name'] ?? ''),
                (string) ($row['test_begin_time'] ?? ''),
                (string) ($row['test_family_key'] ?? ''),
            );
            // Undated/unnamed drafts cannot accidentally collapse together.
            $key = $key === '' ? 'single:' . $id : $key;
            $keys[$id] = $key;
            $groups[$key][] = $id;
        }
        $by_test = [];
        foreach ($keys as $id => $key) {
            $members = $groups[$key] ?? [];
            $by_test[$id] = count($members) > 1 ? $members : [];
        }
    }
    return $by_test[$test_id] ?? null;
}

/**
 * Existing work remains readable/continuable. A new attempt may only use the
 * first started variant, or the lowest group-eligible ID before the first start.
 * Prepared attempts and incomplete generation do not count as actual starts.
 * Recheck under the user row lock before creation/activation.
 */
function f_tmf_test_family_allows(int $test_id, int $user_id, bool $starting = false): bool
{
    global $db;
    // Continuing real work is allowed even when another variant was started first.
    // Check this indexed row before loading every examination identity. Creation
    // and pregeneration activation must still use the complete family decision.
    if (!$starting) {
        $existing = F_db_query('SELECT testuser_id FROM ' . K_TABLE_TEST_USER
            . ' WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id
            . " AND testuser_status>0 AND testuser_pregenerated='0' LIMIT 1", $db);
        if ($existing === false) {
            return false;
        }
        if (is_array(F_db_fetch_array($existing))) {
            return true;
        }
    }
    $family = f_tmf_test_family($test_id);
    if ($family === null) {
        return false;
    }
    if ($family === []) {
        return true;
    }
    $ids = implode(',', $family);
    $result = F_db_query('SELECT testuser_test_id FROM ' . K_TABLE_TEST_USER
        . ' WHERE testuser_user_id=' . $user_id . ' AND testuser_test_id IN (' . $ids . ')'
        . " AND testuser_status>0 AND testuser_pregenerated='0'"
        . ' ORDER BY testuser_creation_time,testuser_id', $db);
    if ($result === false) {
        return false;
    }
    $first = null;
    while (true) {
        $row = F_db_fetch_array($result);
        if (!is_array($row)) {
            break;
        }
        $started = (int) ($row['testuser_test_id'] ?? 0);
        if (!$starting && $started === $test_id) {
            return true;
        }
        $first ??= $started;
    }
    if ($first !== null) {
        return $first === $test_id;
    }
    $result = F_db_query('SELECT MIN(tstgrp_test_id) AS selected_test_id FROM ' . K_TABLE_TEST_GROUPS
        . ' INNER JOIN ' . K_TABLE_USERGROUP . ' ON usrgrp_group_id=tstgrp_group_id'
        . ' WHERE usrgrp_user_id=' . $user_id . ' AND tstgrp_test_id IN (' . $ids . ')', $db);
    if ($result === false) {
        return false;
    }
    $row = F_db_fetch_array($result);
    return is_array($row) && (int) ($row['selected_test_id'] ?? 0) === $test_id;
}
