<?php

/**
 * Return the OpenVsoshCBT role threshold for an administrator controller.
 *
 * The central map also protects upgraded installations whose local tce_auth.php predates the
 * cumulative 5–10 role model and is intentionally not overwritten by deployment.
 */
function openvsosh_admin_required_level(string $script, int $fallback): int
{
    $levels = [
        10 => [
            'tce_edit_backup.php', 'tce_edit_group.php', 'tce_edit_sslcerts.php',
            'tce_edit_user.php', 'tce_import_users.php', 'tce_onboarding_settings.php',
            'tce_menu_users.php', 'tce_select_users.php', 'tce_select_users_popup.php', 'tce_tsv_users.php',
            'tce_update.php', 'tce_users_xlsx.php', 'tce_xml_users.php',
        ],
        9 => [
            'tce_import_omr_answers.php', 'tce_import_omr_bulk.php',
        ],
        8 => [
            'tce_edit_test.php', 'tce_offline.php', 'tce_pregenerate.php',
            'tce_select_tests.php', 'tce_select_tests_popup.php', 'tce_test_access_rules.php',
        ],
        7 => [
            'tce_edit_answer.php', 'tce_edit_module.php', 'tce_edit_question.php',
            'tce_edit_subject.php', 'tce_filemanager.php', 'tce_functions_filemanager.php',
            'tce_import_questions.php', 'tce_menu_modules.php', 'tce_preview_tcecode.php', 'tce_select_mediafile.php',
            'tce_tsv_questions.php', 'tce_xml_questions.php', 'tmf_word_import.php',
        ],
        6 => [
            'tce_attachment.php', 'tce_attempt_archive.php', 'tce_edit_rating.php',
            'tce_email_results.php', 'tce_pdf_all_questions.php', 'tce_popup_test_info.php',
            'tce_show_all_questions.php', 'tce_show_result_allusers.php',
            'tce_show_result_user.php', 'tce_tsv_result_allusers.php',
            'tce_xlsx_result_allusers.php', 'tce_xml_question_stats.php',
            'tce_xml_results.php',
        ],
        5 => [
            'index.php', 'tce_menu_tests.php', 'tce_monitor.php', 'tce_self_profile.php',
            'tce_show_online_users.php',
        ],
    ];
    foreach ($levels as $level => $scripts) {
        if (in_array($script, $scripts, true)) {
            return $level;
        }
    }
    return $fallback;
}

/**
 * Return true when the group is the protected system group named "default".
 */
function openvsosh_is_default_group(int $group_id): bool
{
    global $db;
    if ($group_id < 1) {
        return false;
    }
    $result = F_db_query(
        'SELECT group_name FROM ' . K_TABLE_GROUPS . ' WHERE group_id=' . $group_id . ' LIMIT 1',
        $db,
    );
    $row = $result ? F_db_fetch_array($result) : false;
    return is_array($row) && (string) $row['group_name'] === 'default';
}

/**
 * Ensure a full administrator belongs to the default group.
 *
 * This is intentionally idempotent and is called only after a successful administrator login.
 */
function openvsosh_ensure_admin_default_group(int $user_id): bool
{
    global $db;
    if ($user_id < 1) {
        return false;
    }
    $sql = 'INSERT INTO ' . K_TABLE_USERGROUP . ' (usrgrp_user_id,usrgrp_group_id)
        SELECT u.user_id,g.group_id
        FROM ' . K_TABLE_USERS . ' u
        CROSS JOIN ' . K_TABLE_GROUPS . ' g
        WHERE u.user_id=' . $user_id . '
            AND u.user_level=10
            AND g.group_name=\'default\'
            AND NOT EXISTS (
                SELECT 1 FROM ' . K_TABLE_USERGROUP . ' ug
                WHERE ug.usrgrp_user_id=u.user_id
                    AND ug.usrgrp_group_id=g.group_id
            )';
    return F_db_query($sql, $db) !== false;
}
