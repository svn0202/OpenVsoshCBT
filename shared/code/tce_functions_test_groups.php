<?php

/**
 * Read group assignments once per catalogue rendering, never across requests.
 * An empty result on failure denies access; direct test access is checked live.
 *
 * @return array<int,true>
 */
function f_tmf_group_test_ids(int $user_id): array
{
    global $db;
    $result = F_db_query('SELECT DISTINCT tstgrp_test_id FROM ' . K_TABLE_TEST_GROUPS
        . ' INNER JOIN ' . K_TABLE_USERGROUP . ' ON usrgrp_group_id=tstgrp_group_id'
        . ' WHERE usrgrp_user_id=' . $user_id, $db);
    if ($result === false) {
        return [];
    }
    $ids = [];
    while (is_array($row = F_db_fetch_array($result))) {
        $ids[(int) $row['tstgrp_test_id']] = true;
    }
    return $ids;
}
