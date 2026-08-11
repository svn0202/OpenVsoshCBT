<?php

//============================================================+
// File name   : tce_functions_test_stats.php
// Begin       : 2004-06-10
// Last Update : 2023-11-30
//
// Description : Statistical functions for test results.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Statistical functions for test results.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2004-06-10
 */

/**
 * Returns statistic array for the test-user
 * @param mixed $test_id Test ID.
 * @param mixed $user_id User ID - if greater than zero, filter stats for the specified user.
 * @param mixed $testuser_id Test-user ID - if greater than zero, filter stats for the specified test-user.
 * @param mixed $pubmode If true filter the results for the public interface.
 * @return array<array-key, mixed> Test-user statistics.
 */
function f_get_user_test_stat(
    mixed $test_id,
    mixed $user_id = 0,
    mixed $testuser_id = 0,
    mixed $pubmode = false,
): array
{
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_test.php';
    global $db, $l;
    $test_id = (int) $test_id;
    $user_id = (int) $user_id;
    $testuser_id = (int) $testuser_id;
    // get test data array
    $data = f_get_test_data($test_id);
    /** @var array<array-key,mixed> $data */
    return $data + f_get_user_test_totals($test_id, $user_id, $testuser_id, $pubmode);
}

/**
 * Returns test-user totals
 * @param mixed $test_id Test ID.
 * @param mixed $user_id User ID - if greater than zero, filter stats for the specified user.
 * @param mixed $testuser_id Test-user ID - if greater than zero, filter stats for the specified test-user.
 * @param mixed $pubmode If true filter the results for the public interface.
 * @return array{
 *     testuser_id?: mixed,
 *     user_score?: mixed,
 *     user_test_start_time?: mixed,
 *     user_test_end_time?: mixed,
 *     testuser_status?: mixed,
 *     user_comment?: mixed
 * } Test-user totals.
 */
function f_get_user_test_totals(
    mixed $test_id,
    mixed $user_id = 0,
    mixed $testuser_id = 0,
    mixed $pubmode = false,
): array
{
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_test.php';
    global $db, $l;
    $test_id = (int) $test_id;
    $user_id = (int) $user_id;
    $testuser_id = (int) $testuser_id;
    // get test data array
    $data = [];
    $status_filter = 0;
    if ($pubmode) {
        $status_filter = 3;
    }

    // additional info
    if ($test_id > 0 && $user_id > 0 && $testuser_id > 0) {
        // get user totals
        $sqlu =
            'SELECT SUM(testlog_score) AS total_score, MAX(testlog_change_time) AS test_end_time, testuser_id, testuser_creation_time, testuser_status, testuser_comment
		FROM '
            . K_TABLE_TEST_USER
            . ', '
            . K_TABLE_TESTS_LOGS
            . '
		WHERE testlog_testuser_id=testuser_id
			AND testuser_id='
            . $testuser_id
            . '
			AND testuser_test_id='
            . $test_id
            . '
			AND testuser_user_id='
            . $user_id
            . '
			AND testuser_status>'
            . $status_filter
            . '
		GROUP BY testuser_id, testuser_creation_time, testuser_status, testuser_comment';
        if ($ru = F_db_query($sqlu, $db)) {
            if ($mu = F_db_fetch_array($ru)) {
                $data['testuser_id'] = $mu['testuser_id'];
                $data['user_score'] = $mu['total_score'];
                $data['user_test_start_time'] = $mu['testuser_creation_time'];
                $data['user_test_end_time'] = $mu['test_end_time'];
                $data['testuser_status'] = $mu['testuser_status'];
                $data['user_comment'] = $mu['testuser_comment'];
            }
        } else {
            F_display_db_error();
        }
    }

    return $data;
}

/**
 * Returns statistic array for the selected test.
 * @param mixed $test_id Test ID.
 * @param mixed $group_id Group ID - if greater than zero, filter stats for the specified user group.
 * @param mixed $user_id User ID - if greater than zero, filter stats for the specified user.
 * @param mixed $startdate Start date - if greater than zero, filter stats for the specified starting date.
 * @param mixed $enddate End date - if greater than zero, filter stats for the specified ending date.
 * @param mixed $testuser_id Test-user ID - if greater than zero, filter stats for the specified test-user.
 * @param mixed $pubmode If true filter the results for the public interface.
 * @return array<array-key, mixed> Test statistics.
 */
function f_get_test_stat(
    mixed $test_id,
    mixed $group_id = 0,
    mixed $user_id = 0,
    mixed $startdate = 0,
    mixed $enddate = 0,
    mixed $testuser_id = 0,
    mixed $pubmode = false,
): array {
    $data = f_get_raw_test_stat($test_id, $group_id, $user_id, $startdate, $enddate, $testuser_id, [], $pubmode);
    /** @var array<array-key, mixed> $data */
    if (isset($data['qstats']['recurrence'])) {
        $data = f_normalize_test_stat_averages($data);
        /** @var array<array-key, mixed> $data */
    }

    /** @var array<array-key, mixed> $data */
    return $data;
}

/**
 * Returns raw statistic array for the selected test.
 * @param mixed $test_id Test ID.
 * @param mixed $group_id Group ID - if greater than zero, filter stats for the specified user group.
 * @param mixed $user_id User ID - if greater than zero, filter stats for the specified user.
 * @param mixed $startdate Start date - if greater than zero, filter stats for the specified starting date.
 * @param mixed $enddate End date - if greater than zero, filter stats for the specified ending date.
 * @param mixed $testuser_id Test-user ID - if greater than zero, filter stats for the specified test-user.
 * @param mixed $data Existing data to be merged with the current statistics.
 * @param mixed $pubmode If true filter the results for the public interface.
 * @return mixed Test statistics, or the unchanged input when no test is selected.
 */
function f_get_raw_test_stat(
    mixed $test_id,
    mixed $group_id = 0,
    mixed $user_id = 0,
    mixed $startdate = 0,
    mixed $enddate = 0,
    mixed $testuser_id = 0,
    mixed $data = [],
    mixed $pubmode = false,
): mixed {
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_authorization.php';
    require_once '../../shared/code/tce_functions_test.php';
    global $db, $l;
    $test_id = (int) $test_id;
    $group_id = (int) $group_id;
    $user_id = (int) $user_id;
    $testuser_id = (int) $testuser_id;
    // query to calculate total number of questions
    $sqltot = K_TABLE_TEST_USER . ', ' . K_TABLE_TESTS_LOGS;
    $sqltb = K_TABLE_TEST_USER . ', ' . K_TABLE_TESTS_LOGS . ', ' . K_TABLE_ANSWERS . ', ' . K_TABLE_LOG_ANSWER;
    $sqlm =
        K_TABLE_TEST_USER
        . ', '
        . K_TABLE_TESTS_LOGS
        . ', '
        . K_TABLE_QUESTIONS
        . ', '
        . K_TABLE_SUBJECTS
        . ', '
        . K_TABLE_MODULES
        . '';
    // apply filters
    $sqlw = 'WHERE testlog_testuser_id=testuser_id';
    $sqlansw = 'WHERE logansw_answer_id=answer_id AND logansw_testlog_id=testlog_id AND testlog_testuser_id=testuser_id';
    if ($pubmode) {
        $test_ids_results = f_get_test_id_results($test_id, $user_id);
        $sqlw .= ' AND testuser_test_id IN (' . $test_ids_results . ') AND testuser_status>3';
        $sqlansw .= ' AND testuser_test_id IN (' . $test_ids_results . ') AND testuser_status>3';
    }

    if ($test_id > 0) {
        $sqlw .= ' AND testuser_test_id=' . $test_id . '';
        $sqlansw .= ' AND testuser_test_id=' . $test_id . '';
    }

    if ($user_id > 0) {
        $sqltot .= ', ' . K_TABLE_USERS;
        $sqltb .= ', ' . K_TABLE_USERS;
        $sqlm .= ', ' . K_TABLE_USERS;
        $sqlw .= ' AND testuser_user_id=user_id AND user_id=' . $user_id . '';
        $sqlansw .= ' AND testuser_user_id=user_id AND user_id=' . $user_id . '';
        if ($testuser_id > 0) {
            $sqlw .= ' AND testuser_id=' . $testuser_id . '';
            $sqlansw .= ' AND testuser_id=' . $testuser_id . '';
        }
    } elseif ($group_id > 0) {
        $sqltot .= ', ' . K_TABLE_USERS . ', ' . K_TABLE_USERGROUP;
        $sqltb .= ', ' . K_TABLE_USERS . ', ' . K_TABLE_USERGROUP;
        $sqlm .= ', ' . K_TABLE_USERS . ', ' . K_TABLE_USERGROUP;
        $sqlw .= ' AND testuser_user_id=user_id AND usrgrp_user_id=user_id AND usrgrp_group_id=' . $group_id . '';
        $sqlansw .= ' AND testuser_user_id=user_id AND usrgrp_user_id=user_id AND usrgrp_group_id=' . $group_id . '';
    }

    if (!empty($startdate)) {
        $startdate_time = strtotime($startdate);
        $startdate = date(K_TIMESTAMP_FORMAT, $startdate_time);
        $sqlw .= " AND testuser_creation_time>='" . $startdate . "'";
        $sqlansw .= " AND testuser_creation_time>='" . $startdate . "'";
    }

    if (!empty($enddate)) {
        $enddate_time = strtotime($enddate);
        $enddate = date(K_TIMESTAMP_FORMAT, $enddate_time);
        $sqlw .= " AND testuser_creation_time<='" . $enddate . "'";
        $sqlansw .= " AND testuser_creation_time<='" . $enddate . "'";
    }

    // check if a specific test is selected or not
    if ($test_id === 0) {
        $test_ids = [];
        $sqlt =
            'SELECT testuser_test_id FROM '
            . $sqltot
            . ' '
            . $sqlw
            . ' GROUP BY testuser_test_id ORDER BY testuser_test_id';
        if ($rt = F_db_query($sqlt, $db)) {
            while ($mt = F_db_fetch_assoc($rt)) {
                // check user's authorization
                if (f_is_authorized_user(K_TABLE_TESTS, 'test_id', $mt['testuser_test_id'], 'test_user_id')) {
                    $test_ids[] = $mt['testuser_test_id'];
                }
            }
        } else {
            F_display_db_error();
        }

        foreach ($test_ids as $tid) {
            // select test IDs
            $data = f_get_raw_test_stat($tid, $group_id, $user_id, $startdate, $enddate, $testuser_id, $data, $pubmode);
        }

        return $data;
    }

    $testdata = f_get_test_data($test_id);
    /** @var array<array-key,mixed> $testdata */
    // array to be returned
    if (!isset($data['qstats'])) {
        // total number of questions
        $data['qstats'] = [
            'recurrence' => 0,
            'recurrence_perc' => 0,
            'average_score' => 0,
            'average_score_perc' => 0,
            'average_time' => 0,
            'right' => 0,
            'right_perc' => 0,
            'wrong' => 0,
            'wrong_perc' => 0,
            'unanswered' => 0,
            'unanswered_perc' => 0,
            'undisplayed' => 0,
            'undisplayed_perc' => 0,
            'unrated' => 0,
            'unrated_perc' => 0,
            'qnum' => 0,
            'module' => [],
        ];
    }

    $sql = 'SELECT
		module_id,
		subject_id,
		question_id,
		module_name,
		subject_name,
		subject_description,
		question_description,';
    if ($user_id > 0 && $testuser_id > 0) {
        $sql .= ' testlog_score,
			testlog_user_ip,
			testlog_display_time,
			testlog_change_time,
			testlog_reaction_time,
			testlog_answer_text,
			question_type,
			question_explanation,';
    }

    $datetime_diff_sql = F_db_datetime_diff_seconds('testlog_display_time', 'testlog_change_time');
    $sql .=
        ' COUNT(question_id) AS recurrence,
		AVG(testlog_score) AS average_score,
		AVG('
        . $datetime_diff_sql
        . ') AS average_time,
		MIN(question_type) AS question_type,
		MIN(question_difficulty) AS question_difficulty';
    $sql .= ' FROM ' . $sqlm;
    $sql .= ' WHERE testlog_testuser_id=testuser_id AND question_id=testlog_question_id AND subject_id=question_subject_id AND module_id=subject_module_id';
    if ($test_id > 0) {
        $sql .= ' AND testuser_test_id=' . $test_id . '';
    }

    if ($testuser_id > 0) {
        $sql .= ' AND testuser_id=' . $testuser_id . '';
    }

    if ($user_id > 0) {
        $sql .= ' AND testuser_user_id=user_id AND user_id=' . $user_id . '';
    } elseif ($group_id > 0) {
        $sql .= ' AND testuser_user_id=user_id AND usrgrp_user_id=user_id AND usrgrp_group_id=' . $group_id . '';
    }

    if (!empty($startdate)) {
        $sql .= " AND testuser_creation_time>='" . $startdate . "'";
    }

    if (!empty($enddate)) {
        $sql .= " AND testuser_creation_time<='" . $enddate . "'";
    }

    $sql .= ' GROUP BY module_id, subject_id, question_id, module_name, subject_name, subject_description, question_description';
    if ($user_id > 0 && $testuser_id > 0) {
        $sql .= ', testlog_score, testlog_user_ip, testlog_display_time, testlog_change_time, testlog_reaction_time, testlog_answer_text, question_type, question_explanation';
    } else {
        $sql .= ' ORDER BY module_name, subject_name, question_description';
    }

    if ($r = F_db_query($sql, $db)) {
        while ($m = F_db_fetch_array($r)) {
            if (!isset($data['qstats']['module']["'" . $m['module_id'] . "'"])) {
                $data['qstats']['module']["'" . $m['module_id'] . "'"] = [
                    'id' => $m['module_id'],
                    'name' => $m['module_name'],
                    'recurrence' => 0,
                    'recurrence_perc' => 0,
                    'average_score' => 0,
                    'average_score_perc' => 0,
                    'average_time' => 0,
                    'right' => 0,
                    'right_perc' => 0,
                    'wrong' => 0,
                    'wrong_perc' => 0,
                    'unanswered' => 0,
                    'unanswered_perc' => 0,
                    'undisplayed' => 0,
                    'undisplayed_perc' => 0,
                    'unrated' => 0,
                    'unrated_perc' => 0,
                    'qnum' => 0,
                    'subject' => [],
                ];
            }

            if (
                !isset($data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'" . $m['subject_id'] . "'"])
            ) {
                $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'" . $m['subject_id'] . "'"] = [
                    'id' => $m['subject_id'],
                    'name' => $m['subject_name'],
                    'description' => $m['subject_description'],
                    'recurrence' => 0,
                    'recurrence_perc' => 0,
                    'average_score' => 0,
                    'average_score_perc' => 0,
                    'average_time' => 0,
                    'right' => 0,
                    'right_perc' => 0,
                    'wrong' => 0,
                    'wrong_perc' => 0,
                    'unanswered' => 0,
                    'unanswered_perc' => 0,
                    'undisplayed' => 0,
                    'undisplayed_perc' => 0,
                    'unrated' => 0,
                    'unrated_perc' => 0,
                    'qnum' => 0,
                    'question' => [],
                ];
            }

            $question_max_score = $testdata['test_score_right'] * $m['question_difficulty'];
            $question_half_score = $question_max_score / 2;
            $qright = F_count_rows(
                $sqltot,
                $sqlw
                . ' AND testlog_question_id='
                . $m['question_id']
                . ' AND testlog_score>'
                . $question_half_score
                . '',
            );
            $qwrong = F_count_rows(
                $sqltot,
                $sqlw
                . ' AND testlog_question_id='
                . $m['question_id']
                . ' AND testlog_change_time IS NOT NULL'
                . ' AND testlog_score IS NOT NULL'
                . ' AND testlog_score<='
                . $question_half_score
                . '',
            );
            $qunanswered = F_count_rows(
                $sqltot,
                $sqlw . ' AND testlog_question_id=' . $m['question_id'] . ' AND testlog_change_time IS NULL',
            );
            $qundisplayed = F_count_rows(
                $sqltot,
                $sqlw . ' AND testlog_question_id=' . $m['question_id'] . ' AND testlog_display_time IS NULL',
            );
            $qunrated = F_count_rows(
                $sqltot,
                $sqlw . ' AND testlog_question_id=' . $m['question_id'] . ' AND testlog_score IS NULL',
            );
            if (stripos($m['average_time'] ?? '', ':') !== false) {
                // PostgreSQL returns formatted time, while MySQL returns the number of seconds
                $m['average_time'] = strtotime($m['average_time']);
            }

            $num_all_answers = F_count_rows($sqltb, $sqlansw . ' AND testlog_question_id=' . $m['question_id']);
            if (
                !isset(
                    $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                        . $m['subject_id']
                        . "'"]['question']["'" . $m['question_id'] . "'"],
                )
            ) {
                $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                    . $m['subject_id']
                    . "'"]['question']["'" . $m['question_id'] . "'"] = [
                    'id' => $m['question_id'],
                    'description' => $m['question_description'],
                    'type' => $m['question_type'],
                    'difficulty' => $m['question_difficulty'],
                    'recurrence' => 0,
                    'recurrence_perc' => 0,
                    'average_score' => 0,
                    'average_score_perc' => 0,
                    'average_time' => 0,
                    'right' => 0,
                    'right_perc' => 0,
                    'wrong' => 0,
                    'wrong_perc' => 0,
                    'unanswered' => 0,
                    'unanswered_perc' => 0,
                    'undisplayed' => 0,
                    'undisplayed_perc' => 0,
                    'unrated' => 0,
                    'unrated_perc' => 0,
                    'qnum' => 0,
                    'anum' => 0,
                    'answer' => [],
                ];
            }

            // average score ratio
            $average_score_perc = $question_max_score > 0 ? $m['average_score'] / $question_max_score : 0;

            // sum values for questions
            ++$data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['qnum'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['recurrence'] += $m['recurrence'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['average_score'] += $m['average_score'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['average_score_perc'] += $average_score_perc;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['average_time'] += $m['average_time'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['right'] += $qright;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['wrong'] += $qwrong;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['unanswered'] += $qunanswered;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['undisplayed'] += $qundisplayed;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['unrated'] += $qunrated;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['question']["'" . $m['question_id'] . "'"]['anum'] += $num_all_answers;

            // sum values for subject
            ++$data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'" . $m['subject_id'] . "'"]['qnum'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['recurrence'] += $m['recurrence'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['average_score'] += $m['average_score'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['average_score_perc'] += $average_score_perc;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['average_time'] += $m['average_time'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'" . $m['subject_id'] . "'"]['right'] +=
                $qright;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'" . $m['subject_id'] . "'"]['wrong'] +=
                $qwrong;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['unanswered'] += $qunanswered;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['undisplayed'] += $qundisplayed;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                . $m['subject_id']
                . "'"]['unrated'] += $qunrated;

            // sum values for module
            ++$data['qstats']['module']["'" . $m['module_id'] . "'"]['qnum'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['recurrence'] += $m['recurrence'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['average_score'] += $m['average_score'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['average_score_perc'] += $average_score_perc;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['average_time'] += $m['average_time'];
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['right'] += $qright;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['wrong'] += $qwrong;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['unanswered'] += $qunanswered;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['undisplayed'] += $qundisplayed;
            $data['qstats']['module']["'" . $m['module_id'] . "'"]['unrated'] += $qunrated;

            // sum totals
            ++$data['qstats']['qnum'];
            $data['qstats']['recurrence'] += $m['recurrence'];
            $data['qstats']['average_score'] += $m['average_score'];
            $data['qstats']['average_score_perc'] += $average_score_perc;
            $data['qstats']['average_time'] += $m['average_time'];
            $data['qstats']['right'] += $qright;
            $data['qstats']['wrong'] += $qwrong;
            $data['qstats']['unanswered'] += $qunanswered;
            $data['qstats']['undisplayed'] += $qundisplayed;
            $data['qstats']['unrated'] += $qunrated;

            // get answer statistics
            $sqlaa = 'SELECT answer_id, answer_description, COUNT(answer_id) AS recurrence';
            if ($user_id > 0 && $testuser_id > 0) {
                $sqlaa .= ', logansw_position, logansw_selected, answer_isright, answer_position, answer_explanation';
            }

            $sqlaa .= ' FROM ' . $sqltb . '';
            $sqlaw = ' WHERE testlog_testuser_id=testuser_id
					AND logansw_testlog_id=testlog_id
					AND answer_id=logansw_answer_id
					AND answer_question_id=' . $m['question_id'] . '';
            if ($test_id > 0) {
                $sqlaw .= ' AND testuser_test_id=' . $test_id . '';
            }

            if ($user_id > 0) {
                $sqlaw .= ' AND testuser_user_id=' . $user_id . '';
            }

            if ($testuser_id > 0) {
                $sqlaw .= ' AND testuser_id=' . $testuser_id . '';
            }

            if ($user_id > 0) {
                $sqlaw .= ' AND testuser_user_id=user_id AND user_id=' . $user_id . '';
            } elseif ($group_id > 0) {
                $sqlaw .=
                    ' AND testuser_user_id=user_id AND usrgrp_user_id=user_id AND usrgrp_group_id=' . $group_id . '';
            }

            if (!empty($startdate)) {
                $sql .= " AND testuser_creation_time>='" . $startdate . "'";
            }

            if (!empty($enddate)) {
                $sql .= " AND testuser_creation_time<='" . $enddate . "'";
            }

            $sqlab = ' GROUP BY answer_id, answer_description';

            if ($user_id > 0 && $testuser_id > 0) {
                $sqlab .= ', logansw_position, logansw_selected, answer_isright, answer_position, answer_explanation';
            }

            $sqlab .= ' ORDER BY answer_description';
            $sqla = $sqlaa . $sqlaw . $sqlab;
            if ($ra = F_db_query($sqla, $db)) {
                while ($ma = F_db_fetch_array($ra)) {
                    $aright = F_count_rows(
                        $sqltb,
                        $sqlaw
                        . ' AND answer_id='
                        . $ma['answer_id']
                        . " AND ((answer_isright='0' AND logansw_selected=0) OR (answer_isright='1' AND logansw_selected=1) OR (answer_position IS NOT NULL AND logansw_position IS NOT NULL AND answer_position=logansw_position))",
                    );
                    $awrong = F_count_rows(
                        $sqltb,
                        $sqlaw
                        . ' AND answer_id='
                        . $ma['answer_id']
                        . " AND ((answer_isright='0' AND logansw_selected=1) OR (answer_isright='1' AND logansw_selected=0) OR (answer_position IS NOT NULL AND answer_position!=logansw_position))",
                    );
                    $aunanswered = F_count_rows(
                        $sqltb,
                        $sqlaw . ' AND answer_id=' . $ma['answer_id'] . ' AND logansw_selected=-1',
                    );
                    if (
                        !isset(
                            $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                                . $m['subject_id']
                                . "'"]['question']["'" . $m['question_id'] . "'"]['answer']["'"
                                . $ma['answer_id']
                                . "'"],
                        )
                    ) {
                        $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                            . $m['subject_id']
                            . "'"]['question']["'" . $m['question_id'] . "'"]['answer']["'" . $ma['answer_id'] . "'"] =
                            [
                                'id' => $ma['answer_id'],
                                'description' => $ma['answer_description'],
                                'recurrence' => 0,
                                'recurrence_perc' => 0,
                                'right' => 0,
                                'right_perc' => 0,
                                'wrong' => 0,
                                'wrong_perc' => 0,
                                'unanswered' => 0,
                                'unanswered_perc' => 0,
                            ];
                    }

                    $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                        . $m['subject_id']
                        . "'"]['question']["'" . $m['question_id'] . "'"]['answer']["'"
                        . $ma['answer_id']
                        . "'"]['recurrence'] += $ma['recurrence'];
                    $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                        . $m['subject_id']
                        . "'"]['question']["'" . $m['question_id'] . "'"]['answer']["'"
                        . $ma['answer_id']
                        . "'"]['right'] += $aright;
                    $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                        . $m['subject_id']
                        . "'"]['question']["'" . $m['question_id'] . "'"]['answer']["'"
                        . $ma['answer_id']
                        . "'"]['wrong'] += $awrong;
                    $data['qstats']['module']["'" . $m['module_id'] . "'"]['subject']["'"
                        . $m['subject_id']
                        . "'"]['question']["'" . $m['question_id'] . "'"]['answer']["'"
                        . $ma['answer_id']
                        . "'"]['unanswered'] += $aunanswered;
                }
            } else {
                F_display_db_error();
            }
        }
    } else {
        F_display_db_error();
    }

    return $data;
}

/**
 * Calculate average values from TestStat array
 * @param mixed $data Raw data.
 * @return mixed Processed data, or the unchanged input when statistics are unavailable.
 */
function f_normalize_test_stat_averages(mixed $data): mixed
{
    if (!isset($data['qstats']['recurrence']) || $data['qstats']['recurrence'] <= 0) {
        return $data;
    }

    // calculate totals and average values
    $data['qstats']['recurrence_perc'] = 100;
    $data['qstats']['average_score'] /= $data['qstats']['qnum'];
    $data['qstats']['average_score_perc'] = round(
        (100 * $data['qstats']['average_score_perc']) / $data['qstats']['recurrence'],
    );
    $data['qstats']['average_time'] /= $data['qstats']['qnum'];
    $data['qstats']['right_perc'] = round((100 * $data['qstats']['right']) / $data['qstats']['recurrence']);
    $data['qstats']['wrong_perc'] = round((100 * $data['qstats']['wrong']) / $data['qstats']['recurrence']);
    $data['qstats']['unanswered_perc'] = round((100 * $data['qstats']['unanswered']) / $data['qstats']['recurrence']);
    $data['qstats']['undisplayed_perc'] = round((100 * $data['qstats']['undisplayed']) / $data['qstats']['recurrence']);
    $data['qstats']['unrated_perc'] = round((100 * $data['qstats']['unrated']) / $data['qstats']['recurrence']);
    foreach ($data['qstats']['module'] as $mk => $mv) {
        $data['qstats']['module'][$mk]['recurrence_perc'] = round(
            (100 * $mv['recurrence']) / $data['qstats']['recurrence'],
        );
        $data['qstats']['module'][$mk]['average_score'] = $mv['average_score'] / $mv['qnum'];
        $data['qstats']['module'][$mk]['average_score_perc'] = round(
            (100 * $mv['average_score_perc']) / $mv['recurrence'],
        );
        $data['qstats']['module'][$mk]['average_time'] = $mv['average_time'] / $mv['qnum'];
        $data['qstats']['module'][$mk]['right_perc'] = round((100 * $mv['right']) / $mv['recurrence']);
        $data['qstats']['module'][$mk]['wrong_perc'] = round((100 * $mv['wrong']) / $mv['recurrence']);
        $data['qstats']['module'][$mk]['unanswered_perc'] = round((100 * $mv['unanswered']) / $mv['recurrence']);
        $data['qstats']['module'][$mk]['undisplayed_perc'] = round((100 * $mv['undisplayed']) / $mv['recurrence']);
        $data['qstats']['module'][$mk]['unrated_perc'] = round((100 * $mv['unrated']) / $mv['recurrence']);
        foreach ($mv['subject'] as $sk => $sv) {
            $data['qstats']['module'][$mk]['subject'][$sk]['recurrence_perc'] = round(
                (100 * $sv['recurrence']) / $data['qstats']['recurrence'],
            );
            $data['qstats']['module'][$mk]['subject'][$sk]['average_score'] = $sv['average_score'] / $sv['qnum'];
            $data['qstats']['module'][$mk]['subject'][$sk]['average_score_perc'] = round(
                (100 * $sv['average_score_perc']) / $sv['recurrence'],
            );
            $data['qstats']['module'][$mk]['subject'][$sk]['average_time'] = $sv['average_time'] / $sv['qnum'];
            $data['qstats']['module'][$mk]['subject'][$sk]['right_perc'] = round(
                (100 * $sv['right']) / $sv['recurrence'],
            );
            $data['qstats']['module'][$mk]['subject'][$sk]['wrong_perc'] = round(
                (100 * $sv['wrong']) / $sv['recurrence'],
            );
            $data['qstats']['module'][$mk]['subject'][$sk]['unanswered_perc'] = round(
                (100 * $sv['unanswered']) / $sv['recurrence'],
            );
            $data['qstats']['module'][$mk]['subject'][$sk]['undisplayed_perc'] = round(
                (100 * $sv['undisplayed']) / $sv['recurrence'],
            );
            $data['qstats']['module'][$mk]['subject'][$sk]['unrated_perc'] = round(
                (100 * $sv['unrated']) / $sv['recurrence'],
            );
            foreach ($sv['question'] as $qk => $qv) {
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['recurrence_perc'] = round(
                    (100 * $qv['recurrence']) / $data['qstats']['recurrence'],
                );
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['average_score'] =
                    $qv['average_score'] / $qv['qnum'];
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['average_score_perc'] = round(
                    (100 * $qv['average_score_perc']) / $qv['recurrence'],
                );
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['average_time'] =
                    $qv['average_time'] / $qv['qnum'];
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['right_perc'] = round(
                    (100 * $qv['right']) / $qv['recurrence'],
                );
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['wrong_perc'] = round(
                    (100 * $qv['wrong']) / $qv['recurrence'],
                );
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['unanswered_perc'] = round(
                    (100 * $qv['unanswered']) / $qv['recurrence'],
                );
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['undisplayed_perc'] = round(
                    (100 * $qv['undisplayed']) / $qv['recurrence'],
                );
                $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['unrated_perc'] = round(
                    (100 * $qv['unrated']) / $qv['recurrence'],
                );
                foreach ($qv['answer'] as $ak => $av) {
                    $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['answer'][$ak]['recurrence_perc'] = round(
                        (100 * $av['recurrence']) / $qv['anum'],
                    );
                    $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['answer'][$ak]['right_perc'] = round(
                        (100 * $av['right']) / $av['recurrence'],
                    );
                    $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['answer'][$ak]['wrong_perc'] = round(
                        (100 * $av['wrong']) / $av['recurrence'],
                    );
                    $data['qstats']['module'][$mk]['subject'][$sk]['question'][$qk]['answer'][$ak]['unanswered_perc'] = round(
                        (100 * $av['unanswered']) / $av['recurrence'],
                    );
                }
            }
        }
    }

    return $data;
}

/**
 * Returns test stats as HTML table
 * @param mixed $test_id Test ID.
 * @param mixed $group_id Group ID - if greater than zero, filter stats for the specified user group.
 * @param mixed $user_id User ID - if greater than zero, filter stats for the specified user.
 * @param mixed $startdate Start date - if greater than zero, filter stats for the specified starting date.
 * @param mixed $enddate End date - if greater than zero, filter stats for the specified ending date.
 * @param mixed $testuser_id Test-user ID - if greater than zero, filter stats for the specified test-user.
 * @param mixed $ts Statistics to print (leave empty to automatically generate new data).
 * @param mixed $display_mode Display mode: 0 = disabled; 1 = minimum; 2 = module; 3 = subject; 4 = question;
 *     5 = answer.
 * @param mixed $pubmode If true filter the results for the public interface.
 * @return string|null HTML table, or null when statistics are disabled or empty.
 */
function f_print_test_stat(
    mixed $test_id,
    mixed $group_id = 0,
    mixed $user_id = 0,
    mixed $startdate = 0,
    mixed $enddate = 0,
    mixed $testuser_id = 0,
    mixed $ts = [],
    mixed $display_mode = 2,
    mixed $pubmode = false,
): ?string {
    if ($display_mode < 2) {
        return null;
    }

    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_tcecode.php';
    global $db, $l;
    /**
     * @var array{
     *     a_meta_dir: string,
     *     h_answer_time: string,
     *     h_answers_right: string,
     *     h_answers_wrong: string,
     *     h_question_recurrence: string,
     *     h_questions_unanswered: string,
     *     h_questions_undisplayed: string,
     *     h_questions_unrated: string,
     *     h_score_average: string,
     *     t_answers_editor: string,
     *     t_modules_editor: string,
     *     t_questions_editor: string,
     *     t_subjects_editor: string,
     *     w_all: string,
     *     w_answer_time: string,
     *     w_answer: string,
     *     w_answers_right: string,
     *     w_answers_wrong: string,
     *     w_module: string,
     *     w_question: string,
     *     w_questions_unanswered: string,
     *     w_questions_undisplayed: string,
     *     w_questions_unrated: string,
     *     w_recurrence: string,
     *     w_score: string,
     *     w_statistics: string,
     *     w_subject: string
     * } $l
     */
    if (empty($ts['qstats']['recurrence'])) {
        return null;
    }

    $test_id = (int) $test_id;
    $user_id = (int) $user_id;
    $testuser_id = (int) $testuser_id;
    if (empty($ts)) {
        // get statistics array
        $ts = f_get_test_stat($test_id, $group_id, $user_id, $startdate, $enddate, $testuser_id, $pubmode);
    }
    /** @var array{qstats:array{recurrence:mixed,recurrence_perc:mixed,average_score_perc:mixed,average_time:mixed,right:mixed,right_perc:mixed,wrong:mixed,wrong_perc:mixed,unanswered:mixed,unanswered_perc:mixed,undisplayed:mixed,undisplayed_perc:mixed,unrated:mixed,unrated_perc:mixed,module:array<array-key,mixed>}} $ts */

    $txtdir = (($l['a_meta_dir'] <=> 'rtl') === 0) ? 'right' : 'left';

    $ret = '';
    $ret .= '<table class="userselect">' . K_NEWLINE;
    $ret .= '<caption class="sr-only">' . $l['w_statistics'] . '</caption>' . K_NEWLINE;
    $ret .=
        '<tr><td colspan="12" style="background-color:#DDDDDD;"><strong>'
        . $l['w_statistics']
        . ' ['
        . $l['w_all']
        . ' + '
        . $l['w_module']
        . '';
    if ($display_mode > 2) {
        $ret .= ' + ' . $l['w_subject'] . '';
        if ($display_mode > 3) {
            $ret .= ' + ' . $l['w_question'] . '';
            if ($display_mode > 4) {
                $ret .= ' + ' . $l['w_answer'] . '';
            }
        }
    }

    $ret .= ']</strong></td></tr>' . K_NEWLINE;
    $ret .= '<thead>' . K_NEWLINE;
    $ret .= '<tr>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['w_module'] . '">M#</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['w_subject'] . '">S#</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['w_question'] . '">Q#</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['w_answer'] . '">A#</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['h_question_recurrence'] . '">' . $l['w_recurrence'] . '</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['h_score_average'] . '">' . $l['w_score'] . '</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['h_answer_time'] . '">' . $l['w_answer_time'] . '</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['h_answers_right'] . '">' . $l['w_answers_right'] . '</th>' . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['h_answers_wrong'] . '">' . $l['w_answers_wrong'] . '</th>' . K_NEWLINE;
    $ret .=
        '<th scope="col" title="'
        . $l['h_questions_unanswered']
        . '">'
        . $l['w_questions_unanswered']
        . '</th>'
        . K_NEWLINE;
    $ret .=
        '<th scope="col" title="'
        . $l['h_questions_undisplayed']
        . '">'
        . $l['w_questions_undisplayed']
        . '</th>'
        . K_NEWLINE;
    $ret .=
        '<th scope="col" title="' . $l['h_questions_unrated'] . '">' . $l['w_questions_unrated'] . '</th>' . K_NEWLINE;
    $ret .= '</tr>' . K_NEWLINE;
    $ret .= '</thead>' . K_NEWLINE;
    $ret .= '<tr style="background-color:#FFEEEE;">';
    $ret .= '<td colspan="4">' . $l['w_all'] . '</td>' . K_NEWLINE;
    $ret .=
        '<td class="numeric">'
        . $ts['qstats']['recurrence']
        . ' '
        . f_format_percentage($ts['qstats']['recurrence_perc'], false)
        . '</td>'
        . K_NEWLINE;
    $ret .= '<td class="numeric">' . $ts['qstats']['average_score_perc'] . '%</td>' . K_NEWLINE;
    $ret .= '<td class="numeric">&nbsp;' . date('i:s', intval($ts['qstats']['average_time'])) . '</td>' . K_NEWLINE;
    $ret .=
        '<td class="numeric">'
        . $ts['qstats']['right']
        . ' '
        . f_format_percentage($ts['qstats']['right_perc'], false)
        . '</td>'
        . K_NEWLINE;
    $ret .=
        '<td class="numeric">'
        . $ts['qstats']['wrong']
        . ' '
        . f_format_percentage($ts['qstats']['wrong_perc'], false)
        . '</td>'
        . K_NEWLINE;
    $ret .=
        '<td class="numeric">'
        . $ts['qstats']['unanswered']
        . ' '
        . f_format_percentage($ts['qstats']['unanswered_perc'], false)
        . '</td>'
        . K_NEWLINE;
    $ret .=
        '<td class="numeric">'
        . $ts['qstats']['undisplayed']
        . ' '
        . f_format_percentage($ts['qstats']['undisplayed_perc'], false)
        . '</td>'
        . K_NEWLINE;
    $ret .=
        '<td class="numeric">'
        . $ts['qstats']['unrated']
        . ' '
        . f_format_percentage($ts['qstats']['unrated_perc'], false)
        . '</td>'
        . K_NEWLINE;
    $ret .= '</tr>' . K_NEWLINE;
    $num_module = 0;
    foreach ($ts['qstats']['module'] as $module) {
        ++$num_module;
        $ret .= '<tr style="background-color:#DDEEFF;">';
        if ($pubmode) {
            $ret .= '<td rowspan="2" valign="middle"><strong>M' . $num_module . '</strong></td>' . K_NEWLINE;
        } else {
            $ret .=
                '<td rowspan="2" valign="middle"><a href="tce_edit_module.php?module_id='
                . $module['id']
                . '" title="'
                . $l['t_modules_editor']
                . '"><strong>M'
                . $num_module
                . '</strong></a></td>'
                . K_NEWLINE;
        }

        $ret .= '<td rowspan="2" colspan="3">&nbsp;</td>' . K_NEWLINE;
        $ret .=
            '<td class="numeric">'
            . $module['recurrence']
            . ' '
            . f_format_percentage($module['recurrence_perc'], false)
            . '</td>'
            . K_NEWLINE;
        $ret .= '<td class="numeric">' . $module['average_score_perc'] . '%</td>' . K_NEWLINE;
        $ret .= '<td class="numeric">&nbsp;' . date('i:s', intval($module['average_time'])) . '</td>' . K_NEWLINE;
        $ret .=
            '<td class="numeric">'
            . $module['right']
            . ' '
            . f_format_percentage($module['right_perc'], false)
            . '</td>'
            . K_NEWLINE;
        $ret .=
            '<td class="numeric">'
            . $module['wrong']
            . ' '
            . f_format_percentage($module['wrong_perc'], false)
            . '</td>'
            . K_NEWLINE;
        $ret .=
            '<td class="numeric">'
            . $module['unanswered']
            . ' '
            . f_format_percentage($module['unanswered_perc'], false)
            . '</td>'
            . K_NEWLINE;
        $ret .=
            '<td class="numeric">'
            . $module['undisplayed']
            . ' '
            . f_format_percentage($module['undisplayed_perc'], false)
            . '</td>'
            . K_NEWLINE;
        $ret .=
            '<td class="numeric">'
            . $module['unrated']
            . ' '
            . f_format_percentage($module['unrated_perc'], false)
            . '</td>'
            . K_NEWLINE;
        $ret .= '</tr>' . K_NEWLINE;
        $ret .= '<tr>';
        $ret .=
            '<td colspan="8" align="'
            . $txtdir
            . '" style="background-color:white;">'
            . F_decode_tcecode($module['name'])
            . '</td>';
        $ret .= '</tr>' . K_NEWLINE;
        if ($display_mode > 2) {
            $num_subject = 0;
            foreach ($module['subject'] as $subject) {
                ++$num_subject;
                $ret .= '<tr style="background-color:#DDFFDD;">';
                $ret .= '<td rowspan="2" style="background-color:#DDEEFF;">M' . $num_module . '</td>' . K_NEWLINE;
                if ($pubmode) {
                    $ret .= '<td rowspan="2" valign="middle"><strong>S' . $num_subject . '</strong></td>' . K_NEWLINE;
                } else {
                    $ret .=
                        '<td rowspan="2" valign="middle"><a href="tce_edit_subject.php?subject_id='
                        . $subject['id']
                        . '" title="'
                        . $l['t_subjects_editor']
                        . '"><strong>S'
                        . $num_subject
                        . '</strong></a></td>'
                        . K_NEWLINE;
                }

                $ret .= '<td rowspan="2" colspan="2">&nbsp;</td>' . K_NEWLINE;
                $ret .=
                    '<td class="numeric">'
                    . $subject['recurrence']
                    . ' '
                    . f_format_percentage($subject['recurrence_perc'], false)
                    . '</td>'
                    . K_NEWLINE;
                $ret .= '<td class="numeric">' . $subject['average_score_perc'] . '%</td>' . K_NEWLINE;
                $ret .=
                    '<td class="numeric">&nbsp;' . date('i:s', intval($subject['average_time'])) . '</td>' . K_NEWLINE;
                $ret .=
                    '<td class="numeric">'
                    . $subject['right']
                    . ' '
                    . f_format_percentage($subject['right_perc'], false)
                    . '</td>'
                    . K_NEWLINE;
                $ret .=
                    '<td class="numeric">'
                    . $subject['wrong']
                    . ' '
                    . f_format_percentage($subject['wrong_perc'], false)
                    . '</td>'
                    . K_NEWLINE;
                $ret .=
                    '<td class="numeric">'
                    . $subject['unanswered']
                    . ' '
                    . f_format_percentage($subject['unanswered_perc'], false)
                    . '</td>'
                    . K_NEWLINE;
                $ret .=
                    '<td class="numeric">'
                    . $subject['undisplayed']
                    . ' '
                    . f_format_percentage($subject['undisplayed_perc'], false)
                    . '</td>'
                    . K_NEWLINE;
                $ret .=
                    '<td class="numeric">'
                    . $subject['unrated']
                    . ' '
                    . f_format_percentage($subject['unrated_perc'], false)
                    . '</td>'
                    . K_NEWLINE;
                $ret .= '</tr>' . K_NEWLINE;
                $ret .= '<tr>';
                $ret .=
                    '<td colspan="8" align="'
                    . $txtdir
                    . '" style="background-color:white;">'
                    . F_decode_tcecode($subject['name'])
                    . '</td>';
                $ret .= '</tr>' . K_NEWLINE;
                if ($display_mode > 3) {
                    $num_question = 0;
                    foreach ($subject['question'] as $question) {
                        ++$num_question;
                        $ret .= '<tr style="background-color:#FFFACD;">';
                        $ret .=
                            '<td rowspan="2" style="background-color:#DDEEFF;">M' . $num_module . '</td>' . K_NEWLINE;
                        $ret .=
                            '<td rowspan="2" style="background-color:#DDFFDD;">S' . $num_subject . '</td>' . K_NEWLINE;
                        if ($pubmode) {
                            $ret .=
                                '<td rowspan="2" valign="middle"><strong>Q'
                                . $num_question
                                . '</strong><br /><small>сложность: '
                                . f_format_float($question['difficulty'])
                                . '</small></td>'
                                . K_NEWLINE;
                        } else {
                            $ret .=
                                '<td rowspan="2" valign="middle"><a href="tce_edit_question.php?question_id='
                                . $question['id']
                                . '" title="'
                                . $l['t_questions_editor']
                                . '"><strong>Q'
                                . $num_question
                                . '</strong></a><br /><small>сложность: '
                                . f_format_float($question['difficulty'])
                                . '</small></td>'
                                . K_NEWLINE;
                        }

                        $ret .= '<td rowspan="2">&nbsp;</td>' . K_NEWLINE;
                        $ret .=
                            '<td class="numeric">'
                            . $question['recurrence']
                            . ' '
                            . f_format_percentage($question['recurrence_perc'], false)
                            . '</td>'
                            . K_NEWLINE;
                        $ret .= '<td class="numeric">' . $question['average_score_perc'] . '%</td>' . K_NEWLINE;
                        $ret .=
                            '<td class="numeric">&nbsp;'
                            . date('i:s', intval($question['average_time']))
                            . '</td>'
                            . K_NEWLINE;
                        $ret .=
                            '<td class="numeric">'
                            . $question['right']
                            . ' '
                            . f_format_percentage($question['right_perc'], false)
                            . '</td>'
                            . K_NEWLINE;
                        $ret .=
                            '<td class="numeric">'
                            . $question['wrong']
                            . ' '
                            . f_format_percentage($question['wrong_perc'], false)
                            . '</td>'
                            . K_NEWLINE;
                        $ret .=
                            '<td class="numeric">'
                            . $question['unanswered']
                            . ' '
                            . f_format_percentage($question['unanswered_perc'], false)
                            . '</td>'
                            . K_NEWLINE;
                        $ret .=
                            '<td class="numeric">'
                            . $question['undisplayed']
                            . ' '
                            . f_format_percentage($question['undisplayed_perc'], false)
                            . '</td>'
                            . K_NEWLINE;
                        $ret .=
                            '<td class="numeric">'
                            . $question['unrated']
                            . ' '
                            . f_format_percentage($question['unrated_perc'], false)
                            . '</td>'
                            . K_NEWLINE;
                        $ret .= '</tr>' . K_NEWLINE;
                        $ret .= '<tr>';
                        $ret .=
                            '<td colspan="8" align="'
                            . $txtdir
                            . '" style="background-color:white;">'
                            . F_decode_tcecode($question['description'])
                            . '</td>';
                        $ret .= '</tr>' . K_NEWLINE;
                        if ($display_mode > 4) {
                            $num_answer = 0;
                            foreach ($question['answer'] as $answer) {
                                ++$num_answer;
                                $ret .= '<tr style="">';
                                $ret .=
                                    '<td rowspan="2" style="background-color:#DDEEFF;">M'
                                    . $num_module
                                    . '</td>'
                                    . K_NEWLINE;
                                $ret .=
                                    '<td rowspan="2" style="background-color:#DDFFDD;">S'
                                    . $num_subject
                                    . '</td>'
                                    . K_NEWLINE;
                                $ret .=
                                    '<td rowspan="2" style="background-color:#FFFACD;">Q'
                                    . $num_question
                                    . '</td>'
                                    . K_NEWLINE;
                                if ($pubmode) {
                                    $ret .=
                                        '<td rowspan="2" valign="middle"><strong>A'
                                        . $num_answer
                                        . '</strong></td>'
                                        . K_NEWLINE;
                                } else {
                                    $ret .=
                                        '<td rowspan="2" valign="middle"><a href="tce_edit_answer.php?answer_id='
                                        . $answer['id']
                                        . '" title="'
                                        . $l['t_answers_editor']
                                        . '"><strong>A'
                                        . $num_answer
                                        . '</strong></a></td>'
                                        . K_NEWLINE;
                                }

                                $ret .=
                                    '<td class="numeric">'
                                    . $answer['recurrence']
                                    . ' '
                                    . f_format_percentage($answer['recurrence_perc'], false)
                                    . '</td>'
                                    . K_NEWLINE;
                                $ret .= '<td class="numeric">&nbsp;</td>' . K_NEWLINE;
                                $ret .= '<td class="numeric">&nbsp;</td>' . K_NEWLINE;
                                $ret .=
                                    '<td class="numeric">'
                                    . $answer['right']
                                    . ' '
                                    . f_format_percentage($answer['right_perc'], false)
                                    . '</td>'
                                    . K_NEWLINE;
                                $ret .=
                                    '<td class="numeric">'
                                    . $answer['wrong']
                                    . ' '
                                    . f_format_percentage($answer['wrong_perc'], false)
                                    . '</td>'
                                    . K_NEWLINE;
                                $ret .=
                                    '<td class="numeric">'
                                    . $answer['unanswered']
                                    . ' '
                                    . f_format_percentage($answer['unanswered_perc'], false)
                                    . '</td>'
                                    . K_NEWLINE;
                                $ret .= '<td class="numeric">&nbsp;</td>' . K_NEWLINE;
                                $ret .= '<td class="numeric">&nbsp;</td>' . K_NEWLINE;
                                $ret .= '</tr>' . K_NEWLINE;
                                $ret .= '<tr>';
                                $ret .=
                                    '<td colspan="8" align="'
                                    . $txtdir
                                    . '" style="background-color:white;">'
                                    . F_decode_tcecode($answer['description'])
                                    . '</td>';
                                $ret .= '</tr>' . K_NEWLINE;
                            } // end for answer
                        }
                    } // end for question
                }
            } // end for subject
        }
    }

    // end for module
    $ret .= '</table>' . K_NEWLINE;
    return $ret;
}

/**
 * Returns test stats as HTML table
 * @param mixed $data Test statistics.
 * @param mixed $nextorderdir Next order direction.
 * @param mixed $order_field Order fields.
 * @param mixed $filter Filter string for URLs.
 * @param mixed $pubmode If true filter the results for the public interface.
 * @param mixed $stats 2 = full stats; 1 = user stats; 0 = disabled stats.
 * @return string|null HTML table, or null when there are no records.
 */
function f_print_test_result_stat(
    mixed $data,
    mixed $nextorderdir,
    mixed $order_field,
    mixed $filter,
    mixed $pubmode = false,
    mixed $stats = 1,
): ?string {
    require_once '../config/tce_config.php';
    global $db, $l;
    /**
     * @var array{
     *     a_meta_charset: string,
     *     a_meta_dir: string,
     *     h_answers_right: string,
     *     h_answers_wrong: string,
     *     h_firstname: string,
     *     h_lastname: string,
     *     h_login_name: string,
     *     h_questions_unanswered: string,
     *     h_questions_undisplayed: string,
     *     h_questions_unrated: string,
     *     h_score_total: string,
     *     h_test_time: string,
     *     h_test: string,
     *     h_testcomment: string,
     *     h_time_begin: string,
     *     h_time_end: string,
     *     h_view_details: string,
     *     w_answers_right: string,
     *     w_answers_wrong: string,
     *     w_comment: string,
     *     w_firstname: string,
     *     w_lastname: string,
     *     w_locked: string,
     *     w_minutes: string,
     *     w_not_passed: string,
     *     w_passed: string,
     *     w_questions_unanswered: string,
     *     w_questions_undisplayed: string,
     *     w_questions_unrated: string,
     *     w_results: string,
     *     w_score: string,
     *     w_select: string,
     *     w_status: string,
     *     w_test: string,
     *     w_time_begin: string,
     *     w_time_end: string,
     *     w_time: string,
     *     w_unlocked: string,
     *     w_user: string,
     *     w_yes: string
     * } $l
     */
    if (empty($data['num_records'])) {
        return null;
    }

    if (($l['a_meta_dir'] <=> 'rtl') === 0) {
        $tdalignr = 'left';
        $tdalign = 'right';
    } else {
        $tdalignr = 'right';
        $tdalign = 'left';
    }

    $ret = '';
    $ret .= '<table class="userselect">' . K_NEWLINE;
    $ret .= '<caption class="sr-only">' . $l['w_results'] . '</caption>' . K_NEWLINE;
    $ret .= '<thead>' . K_NEWLINE;
    $ret .= '<tr>' . K_NEWLINE;
    $ret .= '<th scope="col">&nbsp;</th>' . K_NEWLINE;
    $ret .= '<th scope="col">#</th>' . K_NEWLINE;
    $ret .= F_select_table_header_element(
        'testuser_creation_time',
        $nextorderdir,
        $l['h_time_begin'],
        $l['w_time_begin'],
        $order_field,
        $filter,
    );
    //$ret .= F_select_table_header_element('testuser_end_time', $nextorderdir, $l['h_time_end'], $l['w_time_end'], $order_field, $filter);
    $ret .= '<th scope="col" title="' . $l['h_test_time'] . '">' . $l['w_time'] . '</th>' . K_NEWLINE;
    $ret .= F_select_table_header_element(
        'testuser_test_id',
        $nextorderdir,
        $l['h_test'],
        $l['w_test'],
        $order_field,
        $filter,
    );
    if (!$pubmode) {
        $ret .= F_select_table_header_element(
            'user_name',
            $nextorderdir,
            $l['h_login_name'],
            $l['w_user'],
            $order_field,
            $filter,
        );
        $ret .= F_select_table_header_element(
            'user_lastname',
            $nextorderdir,
            $l['h_lastname'],
            $l['w_lastname'],
            $order_field,
            $filter,
        );
        $ret .= F_select_table_header_element(
            'user_firstname',
            $nextorderdir,
            $l['h_firstname'],
            $l['w_firstname'],
            $order_field,
            $filter,
        );
    }

    $ret .= F_select_table_header_element(
        'total_score',
        $nextorderdir,
        $l['h_score_total'],
        $l['w_score'],
        $order_field,
        $filter,
    );
    if ($stats > 0) {
        $ret .= '<th scope="col" title="' . $l['h_answers_right'] . '">' . $l['w_answers_right'] . '</th>' . K_NEWLINE;
        $ret .= '<th scope="col" title="' . $l['h_answers_wrong'] . '">' . $l['w_answers_wrong'] . '</th>' . K_NEWLINE;
        $ret .=
            '<th scope="col" title="'
            . $l['h_questions_unanswered']
            . '">'
            . $l['w_questions_unanswered']
            . '</th>'
            . K_NEWLINE;
        $ret .=
            '<th scope="col" title="'
            . $l['h_questions_undisplayed']
            . '">'
            . $l['w_questions_undisplayed']
            . '</th>'
            . K_NEWLINE;
        $ret .=
            '<th scope="col" title="'
            . $l['h_questions_unrated']
            . '">'
            . $l['w_questions_unrated']
            . '</th>'
            . K_NEWLINE;
    }

    $ret .=
        '<th scope="col" title="'
        . $l['w_status']
        . ' ('
        . $l['w_time']
        . ' ['
        . $l['w_minutes']
        . '])">'
        . $l['w_status']
        . ' ('
        . $l['w_time']
        . ' ['
        . $l['w_minutes']
        . '])</th>'
        . K_NEWLINE;
    $ret .= '<th scope="col" title="' . $l['h_testcomment'] . '">' . $l['w_comment'] . '</th>' . K_NEWLINE;
    $ret .= '</tr>' . K_NEWLINE;
    $ret .= '</thead>' . K_NEWLINE;
    foreach ($data['testuser'] as $tu) {
        $tu['test']['test_name'] = unhtmlentities(strip_tags($tu['test']['test_name']));
        $ret .= '<tr>';
        $ret .= '<td>';
        $ret .=
            '<input type="checkbox" name="testuserid'
            . $tu['num']
            . '" id="testuserid'
            . $tu['num']
            . '" value="'
            . $tu['id']
            . '" title="'
            . $l['w_select']
            . '"';
        if (isset($_REQUEST['checkall']) && f_legacy_int_equals($_REQUEST['checkall'], 1)) {
            $ret .= ' checked="checked"';
        }

        $ret .= ' />';
        $ret .= '</td>' . K_NEWLINE;
        if (!$pubmode || f_get_boolean($tu['test']['test_report_to_users'])) {
            $ret .=
                '<td><a href="tce_show_result_user.php?testuser_id='
                . $tu['id']
                . '&amp;test_id='
                . $tu['test']['test_id']
                . '&amp;user_id='
                . $tu['user_id']
                . '" title="'
                . $l['h_view_details']
                . '">'
                . $tu['num']
                . '</a></td>'
                . K_NEWLINE;
        } else {
            $ret .= '<td>' . $tu['num'] . '</td>' . K_NEWLINE;
        }

        $ret .= '<td style="text-align:center;">' . $tu['testuser_creation_time'] . '</td>' . K_NEWLINE;
        //$ret .= '<td style="text-align:center;">'.$tu['testuser_end_time'].'</td>'.K_NEWLINE;
        $ret .= '<td style="text-align:center;">' . $tu['time_diff'] . '</td>' . K_NEWLINE;
        $passmsg = '';
        $passlabel = '';
        if ($tu['passmsg'] === true) {
            $passmsg = ' title="' . $l['w_passed'] . '" style="background-color:#BBFFBB;"';
            $passlabel = '<span class="sr-only">' . $l['w_passed'] . '</span> ';
        } elseif ($tu['passmsg'] === false) {
            $passmsg = ' title="' . $l['w_not_passed'] . '" style="background-color:#FFBBBB;"';
            $passlabel = '<span class="sr-only">' . $l['w_not_passed'] . '</span> ';
        }

        if ($pubmode) {
            $ret .= '<td style="text-align:' . $tdalign . ';">' . $tu['test']['test_name'] . '</td>' . K_NEWLINE;
        } else {
            $ret .=
                '<td style="text-align:'
                . $tdalign
                . ';"><a href="tce_edit_test.php?test_id='
                . $tu['test']['test_id']
                . '">'
                . $tu['test']['test_name']
                . '</a></td>'
                . K_NEWLINE;
            $ret .=
                '<td style="text-align:'
                . $tdalign
                . ';"><a href="tce_edit_user.php?user_id='
                . $tu['user_id']
                . '">'
                . $tu['user_name']
                . '</a></td>'
                . K_NEWLINE;
            $ret .= '<td style="text-align:' . $tdalign . ';">&nbsp;' . $tu['user_lastname'] . '</td>' . K_NEWLINE;
            $ret .= '<td style="text-align:' . $tdalign . ';">&nbsp;' . $tu['user_firstname'] . '</td>' . K_NEWLINE;
        }

        $ret .=
            '<td'
            . $passmsg
            . ' class="numeric">'
            . $passlabel
            . f_format_float($tu['total_score'])
            . '&nbsp;'
            . f_format_percentage($tu['total_score_perc'], false)
            . '</td>'
            . K_NEWLINE;
        if ($stats > 0) {
            $ret .=
                '<td class="numeric">'
                . $tu['right']
                . '&nbsp;'
                . f_format_percentage($tu['right_perc'], false)
                . '</td>'
                . K_NEWLINE;
            $ret .=
                '<td class="numeric">'
                . $tu['wrong']
                . '&nbsp;'
                . f_format_percentage($tu['wrong_perc'], false)
                . '</td>'
                . K_NEWLINE;
            $ret .=
                '<td class="numeric">'
                . $tu['unanswered']
                . '&nbsp;'
                . f_format_percentage($tu['unanswered_perc'], false)
                . '</td>'
                . K_NEWLINE;
            $ret .=
                '<td class="numeric">'
                . $tu['undisplayed']
                . '&nbsp;'
                . f_format_percentage($tu['undisplayed_perc'], false)
                . '</td>'
                . K_NEWLINE;
            $ret .=
                '<td class="numeric">'
                . $tu['unrated']
                . '&nbsp;'
                . f_format_percentage($tu['unrated_perc'], false)
                . '</td>'
                . K_NEWLINE;
        }

        if ($tu['locked']) {
            $ret .= '<td style="background-color:#FFBBBB;">' . $l['w_locked'];
        } else {
            $ret .= '<td style="background-color:#BBFFBB;">' . $l['w_unlocked'];
        }

        if ($tu['remaining_time'] < 0) {
            $ret .= ' (' . $tu['remaining_time'] . ')';
        }

        $ret .= '</td>' . K_NEWLINE;
        if (!empty($tu['user_comment'])) {
            $ret .=
                '<td title="'
                . substr(
                    f_compact_string(htmlspecialchars($tu['user_comment'], ENT_NOQUOTES, $l['a_meta_charset'])),
                    0,
                    255,
                )
                . '">'
                . $l['w_yes']
                . '</td>'
                . K_NEWLINE;
        } else {
            $ret .= '<td>&nbsp;</td>' . K_NEWLINE;
        }

        $ret .= '</tr>' . K_NEWLINE;
    }

    $ret .= '<tr>';
    $colspan = 16;
    if ($pubmode) {
        $colspan -= 3;
    }

    if (f_legacy_int_equals($stats, 0)) {
        $colspan -= 5;
    }

    $ret .=
        '<td colspan="'
        . $colspan
        . '" style="text-align:'
        . $tdalign
        . ';font-weight:bold;padding-right:10px;padding-left:10px;';
    if ($data['passed_perc'] > 50) {
        $ret .= ' background-color:#BBFFBB;"';
        $passratelabel = '<span class="sr-only">' . $l['w_passed'] . '</span> ';
    } else {
        $ret .= ' background-color:#FFBBBB;"';
        $passratelabel = '<span class="sr-only">' . $l['w_not_passed'] . '</span> ';
    }

    $ret .=
        '>'
        . $passratelabel
        . $l['w_passed']
        . ': '
        . $data['passed']
        . ' '
        . f_format_percentage($data['passed_perc'], false)
        . '</td>'
        . K_NEWLINE;
    $ret .= '</tr>';
    // print statistics
    $printstat = ['mean', 'median', 'mode', 'standard_deviation', 'skewness', 'kurtosi'];
    $noperc = ['skewness', 'kurtosi'];
    foreach ($data['statistics'] as $row => $col) {
        if (in_array($row, $printstat)) {
            $ret .= '<tr>';
            $scolspan = 8;
            if ($pubmode) {
                $scolspan -= 3;
            }

            $ret .=
                '<th scope="row" colspan="'
                . $scolspan
                . '" style="text-align:'
                . $tdalignr
                . ';">'
                // @mago-expect analysis:possibly-undefined-string-array-index -- row names map to required translation keys
                // @mago-expect analysis:possibly-null-operand -- every mapped translation value is a string
                . $l['w_' . $row]
                . '</th>'
                . K_NEWLINE;
            if (in_array($row, $noperc)) {
                $ret .= '<td class="numeric">' . f_format_float($col['score_perc']) . '</td>' . K_NEWLINE;
                if ($stats > 0) {
                    $ret .= '<td class="numeric">' . f_format_float($col['right_perc']) . '</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . f_format_float($col['wrong_perc']) . '</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . f_format_float($col['unanswered_perc']) . '</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . f_format_float($col['undisplayed_perc']) . '</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . f_format_float($col['unrated_perc']) . '</td>' . K_NEWLINE;
                }
            } else {
                $ret .= '<td class="numeric">' . round($col['score_perc']) . '%</td>' . K_NEWLINE;
                if ($stats > 0) {
                    $ret .= '<td class="numeric">' . round($col['right_perc']) . '%</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . round($col['wrong_perc']) . '%</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . round($col['unanswered_perc']) . '%</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . round($col['undisplayed_perc']) . '%</td>' . K_NEWLINE;
                    $ret .= '<td class="numeric">' . round($col['unrated_perc']) . '%</td>' . K_NEWLINE;
                }
            }

            $ret .= '<td colspan="2">&nbsp;</td>' . K_NEWLINE;
            $ret .= '</tr>';
        }
    }

    return $ret . ('</table>' . K_NEWLINE);
}

/**
 * Returns user test stats as HTML table
 * @param mixed $testuser_id Test-user ID - if greater than zero, filter stats for the specified test-user.
 * @return string HTML table.
 */
function f_print_user_test_stat(mixed $testuser_id): string
{
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_tcecode.php';
    global $db, $l;
    $testuser_id = (int) $testuser_id;

    $ret = '';

    // display user questions
    $sql =
        'SELECT *
		FROM '
        . K_TABLE_QUESTIONS
        . ', '
        . K_TABLE_TESTS_LOGS
        . ', '
        . K_TABLE_SUBJECTS
        . ', '
        . K_TABLE_MODULES
        . '
		WHERE question_id=testlog_question_id
			AND testlog_testuser_id='
        . $testuser_id
        . '
			AND question_subject_id=subject_id
			AND subject_module_id=module_id
		ORDER BY testlog_id';
    if ($r = F_db_query($sql, $db)) {
        $ret .= '<ol class="question">' . K_NEWLINE;
        while ($m = F_db_fetch_array($r)) {
            $ret .= '<li>' . K_NEWLINE;
            // display question stats
            $ret .= '<strong>[' . $m['testlog_score'] . ']' . K_NEWLINE;
            $ret .= ' (';
            $ret .= 'IP:' . get_ip_as_string($m['testlog_user_ip']) . K_NEWLINE;
            if (isset($m['testlog_display_time']) && strlen($m['testlog_display_time']) > 0) {
                $ret .= ' | ' . substr($m['testlog_display_time'], 11, 8) . K_NEWLINE;
            } else {
                $ret .= ' | --:--:--' . K_NEWLINE;
            }

            if (isset($m['testlog_change_time']) && strlen($m['testlog_change_time']) > 0) {
                $ret .= ' | ' . substr($m['testlog_change_time'], 11, 8) . K_NEWLINE;
            } else {
                $ret .= ' | --:--:--' . K_NEWLINE;
            }

            if (isset($m['testlog_display_time']) && isset($m['testlog_change_time'])) {
                $ret .=
                    ' | '
                    . date('i:s', strtotime($m['testlog_change_time']) - strtotime($m['testlog_display_time']))
                    . '';
            } else {
                $ret .= ' | --:--' . K_NEWLINE;
            }

            if (isset($m['testlog_reaction_time']) && $m['testlog_reaction_time'] > 0) {
                $ret .= ' | ' . ($m['testlog_reaction_time'] / 1000) . '';
            } else {
                $ret .= ' | ------' . K_NEWLINE;
            }

            $ret .= ')</strong>' . K_NEWLINE;
            $ret .= '<br />' . K_NEWLINE;
            // display question description
            $ret .= F_decode_tcecode($m['question_description']) . K_NEWLINE;
            if (K_ENABLE_QUESTION_EXPLANATION && !empty($m['question_explanation'])) {
                $ret .=
                    '<br /><span class="explanation">'
                    . $l['w_explanation']
                    . ':</span><br />'
                    . F_decode_tcecode($m['question_explanation'])
                    . ''
                    . K_NEWLINE;
            }

            if (f_legacy_int_equals($m['question_type'], 3)) {
                // TEXT
                $ret .= '<ul class="answer"><li>' . K_NEWLINE;
                $ret .= F_decode_tcecode($m['testlog_answer_text']);
                require_once __DIR__ . '/tce_functions_attachments.php';
                $ret .= F_tmf_attachment_html((int) $m['testlog_id']);
                $ret .= '&nbsp;</li></ul>' . K_NEWLINE;
            } else {
                $ret .= '<ol class="answer">' . K_NEWLINE;
                // display each answer option
                $sqla =
                    'SELECT *
					FROM '
                    . K_TABLE_LOG_ANSWER
                    . ', '
                    . K_TABLE_ANSWERS
                    . '
					WHERE logansw_answer_id=answer_id
						AND logansw_testlog_id=\''
                    . $m['testlog_id']
                    . '\'
					ORDER BY logansw_order';
                if ($ra = F_db_query($sqla, $db)) {
                    while ($ma = F_db_fetch_array($ra)) {
                        $ret .= '<li>';
                        if (in_array((int) $m['question_type'], [4, 5], true)) {
                            // ORDER / MATCHING
                            if ($ma['logansw_position'] > 0) {
                                if (f_legacy_int_equals(
                                    $ma['logansw_position'],
                                    (int) $ma['answer_position'],
                                )) {
                                    $ret .=
                                        '<abbr title="'
                                        . $l['h_answer_right']
                                        . '" class="okbox">'
                                        . $ma['logansw_position']
                                        . '</abbr>';
                                } else {
                                    $ret .=
                                        '<abbr title="'
                                        . $l['h_answer_wrong']
                                        . '" class="nobox">'
                                        . $ma['logansw_position']
                                        . '</abbr>';
                                }
                            } else {
                                $ret .= '<abbr title="' . $l['m_unanswered'] . '" class="offbox">&nbsp;</abbr>';
                            }
                        } elseif ($ma['logansw_selected'] > 0) {
                            if (f_get_boolean($ma['answer_isright'])) {
                                $ret .= '<abbr title="' . $l['h_answer_right'] . '" class="okbox">x</abbr>';
                            } else {
                                $ret .= '<abbr title="' . $l['h_answer_wrong'] . '" class="nobox">x</abbr>';
                            }
                        } elseif (f_legacy_int_equals($m['question_type'], 1)) {
                            // MCSA
                            $ret .= '<abbr title="-" class="offbox">&nbsp;</abbr>';
                        } elseif (f_legacy_int_equals($ma['logansw_selected'], 0)) {
                            if (f_get_boolean($ma['answer_isright'])) {
                                $ret .= '<abbr title="' . $l['h_answer_wrong'] . '" class="nobox">&nbsp;</abbr>';
                            } else {
                                $ret .= '<abbr title="' . $l['h_answer_right'] . '" class="okbox">&nbsp;</abbr>';
                            }
                        } else {
                            $ret .= '<abbr title="' . $l['m_unanswered'] . '" class="offbox">&nbsp;</abbr>';
                        }

                        $ret .= '&nbsp;';
                        if (in_array((int) $m['question_type'], [4, 5], true)) {
                            $ret .=
                                '<abbr title="'
                                . $l['w_position']
                                . '" class="onbox">'
                                . $ma['answer_position']
                                . '</abbr>';
                        } elseif (f_get_boolean($ma['answer_isright'])) {
                            $ret .= '<abbr title="' . $l['w_answers_right'] . '" class="onbox">&reg;</abbr>';
                        } else {
                            $ret .= '<abbr title="' . $l['w_answers_wrong'] . '" class="offbox">&nbsp;</abbr>';
                        }

                        $ret .= ' ';
                        $ret .= F_decode_tcecode($ma['answer_description']);
                        if (K_ENABLE_ANSWER_EXPLANATION && !empty($ma['answer_explanation'])) {
                            $ret .=
                                '<br /><span class="explanation">'
                                . $l['w_explanation']
                                . ':</span><br />'
                                . F_decode_tcecode($ma['answer_explanation'])
                                . ''
                                . K_NEWLINE;
                        }

                        $ret .= '</li>' . K_NEWLINE;
                    }
                } else {
                    F_display_db_error();
                }

                $ret .= '</ol>' . K_NEWLINE;
            } // end multiple answers
            // display teacher/supervisor comment to the question
            if (isset($m['testlog_comment']) && !empty($m['testlog_comment'])) {
                $ret .= '<ul class="answer"><li class="comment">' . K_NEWLINE;
                $ret .= F_decode_tcecode($m['testlog_comment']);
                $ret .= '&nbsp;</li></ul>' . K_NEWLINE;
            }

            $ret .= '<br /><br />' . K_NEWLINE;
            $ret .= '</li>' . K_NEWLINE;
        }

        $ret .= '</ol>' . K_NEWLINE;
    } else {
        F_display_db_error();
    }

    return $ret;
}

/**
 * Returns users statistic array for the selected test.
 * @param mixed $test_id Test ID.
 * @param mixed $group_id Group ID - if greater than zero, filter stats for the specified user group.
 * @param mixed $user_id User ID - if greater than zero, filter stats for the specified user.
 * @param mixed $startdate Start date - if greater than zero, filter stats for the specified starting date.
 * @param mixed $enddate End date - if greater than zero, filter stats for the specified ending date.
 * @param mixed $full_order_field Ordering fields for SQL query.
 * @param mixed $pubmode If true filter the results for the public interface.
 * @param mixed $stats 2 = full stats; 1 = user stats; 0 = disabled stats.
 * @return array<array-key, mixed> Test statistics.
 */
function f_get_all_users_test_stat(
    mixed $test_id,
    mixed $group_id = 0,
    mixed $user_id = 0,
    mixed $startdate = 0,
    mixed $enddate = 0,
    mixed $full_order_field = 'total_score',
    mixed $pubmode = false,
    mixed $stats = 2,
): array {
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_test.php';
    require_once '../../shared/code/tce_functions_statistics.php';
    global $db, $l;
    $test_id = (int) $test_id;
    $group_id = (int) $group_id;
    $user_id = (int) $user_id;
    $full_order_field = f_get_safe_users_test_stat_order_by($full_order_field);
    $data = [];
    $data['svgpoints'] = '';
    $data['testuser'] = [];
    $sqlr = 'SELECT
		testuser_id,
		testuser_test_id,
		testuser_creation_time,
		testuser_status,
		user_id,
		user_lastname,
		user_firstname,
		user_name,
		user_email,
		SUM(testlog_score) AS total_score,
		MAX(testlog_change_time) AS testuser_end_time
		FROM ' . K_TABLE_TESTS_LOGS . ', ' . K_TABLE_TEST_USER . ', ' . K_TABLE_USERS . '';
    if ($group_id > 0) {
        $sqlr .= ',' . K_TABLE_USERGROUP . '';
    }

    $sqlr .= ' WHERE testlog_testuser_id=testuser_id AND testuser_user_id=user_id';
    if ($pubmode) {
        $sqlr .=
            ' AND testuser_test_id IN ('
            . f_get_test_id_results($test_id, $user_id)
            . ') AND testuser_user_id='
            . $user_id
            . ' AND testuser_status>3';
    }

    if ($test_id > 0) {
        $sqlr .= ' AND testuser_test_id=' . $test_id . '';
    }

    if ($group_id > 0) {
        $sqlr .= ' AND usrgrp_user_id=user_id AND usrgrp_group_id=' . $group_id . '';
    }

    if ($user_id > 0) {
        $sqlr .= ' AND user_id=' . $user_id . '';
    }

    if (!empty($startdate)) {
        $startdate_time = strtotime($startdate);
        $startdate = date(K_TIMESTAMP_FORMAT, $startdate_time);
        $sqlr .= " AND testuser_creation_time>='" . $startdate . "'";
    }

    if (!empty($enddate)) {
        $enddate_time = strtotime($enddate);
        $enddate = date(K_TIMESTAMP_FORMAT, $enddate_time);
        $sqlr .= " AND testuser_creation_time<='" . $enddate . "'";
    }

    if ($stats > 1) {
        $data += f_get_test_stat($test_id, $group_id, $user_id, $startdate, $enddate, 0, $pubmode);
    }

    $itemcount = 0;
    $passed = 0;
    $statsdata = [];
    $statsdata['score'] = [];
    $statsdata['right'] = [];
    $statsdata['wrong'] = [];
    $statsdata['unanswered'] = [];
    $statsdata['undisplayed'] = [];
    $statsdata['unrated'] = [];
    $sqlr .= ' GROUP BY testuser_id, testuser_test_id, testuser_creation_time, user_id, user_lastname, user_firstname, user_name, user_email, testuser_status
		ORDER BY ' . $full_order_field . '';
    if ($rr = F_db_query($sqlr, $db)) {
        $statsdata['recurrence'] = [];
        while ($mr = F_db_fetch_array($rr)) {
            ++$itemcount;
            $usrtestdata = f_get_user_test_stat($mr['testuser_test_id'], $mr['user_id'], $mr['testuser_id']);
            /** @var array{test_max_score:mixed,test_duration_time:mixed,test_score_threshold:mixed,user_score:mixed,user_test_start_time:string,user_comment:mixed} $usrtestdata */
            if ($stats > 0) {
                $teststat = f_get_test_stat(
                    $mr['testuser_test_id'],
                    $group_id,
                    $mr['user_id'],
                    $startdate,
                    $enddate,
                    $mr['testuser_id'],
                    $pubmode,
                );
            }

            $data['testuser']["'" . $mr['testuser_id'] . "'"] = [];
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['test'] = $usrtestdata;
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['num'] = $itemcount;
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['id'] = $mr['testuser_id'];
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['user_id'] = $mr['user_id'];
            $halfscore = $usrtestdata['test_max_score'] / 2;
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['testuser_creation_time'] = $mr['testuser_creation_time'];
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['testuser_end_time'] = $mr['testuser_end_time'];
            if (
                $mr['testuser_end_time'] <= 0
                || strtotime($mr['testuser_end_time']) < strtotime($mr['testuser_creation_time'])
            ) {
                $time_diff = $usrtestdata['test_duration_time'] * K_SECONDS_IN_MINUTE;
            } else {
                $time_diff = strtotime($mr['testuser_end_time']) - strtotime($mr['testuser_creation_time']); //sec
            }

            $data['testuser']["'" . $mr['testuser_id'] . "'"]['time_diff'] = gmdate('H:i:s', $time_diff);
            $passmsg = false;
            if ($usrtestdata['test_score_threshold'] > 0) {
                if ($usrtestdata['user_score'] >= $usrtestdata['test_score_threshold']) {
                    $passmsg = true;
                    ++$passed;
                }
            } elseif ($usrtestdata['user_score'] > $halfscore) {
                $passmsg = true;
                ++$passed;
            }

            if ($usrtestdata['test_max_score'] > 0) {
                $total_score_perc = round((100 * $mr['total_score']) / $usrtestdata['test_max_score']);
            } else {
                $total_score_perc = 0;
            }

            $data['testuser']["'" . $mr['testuser_id'] . "'"]['passmsg'] = $passmsg;
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['user_name'] = $mr['user_name'];
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['user_email'] = $mr['user_email'];
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['user_lastname'] = $mr['user_lastname'];
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['user_firstname'] = $mr['user_firstname'];
            // Keep one canonical decimal representation across HTML, PDF and
            // machine-readable exporters. Do not route stored DECIMAL through
            // a binary float before it reaches XML/JSON/XLSX.
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['total_score'] = f_format_float($mr['total_score']);
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['total_score_perc'] = $total_score_perc;
            if ($stats > 0) {
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['recurrence'] = $teststat['qstats']['recurrence'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['right'] = $teststat['qstats']['right'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['right_perc'] = $teststat['qstats']['right_perc'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['wrong'] = $teststat['qstats']['wrong'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['wrong_perc'] = $teststat['qstats']['wrong_perc'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unanswered'] = $teststat['qstats']['unanswered'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unanswered_perc'] =
                    $teststat['qstats']['unanswered_perc'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['undisplayed'] = $teststat['qstats']['undisplayed'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['undisplayed_perc'] =
                    $teststat['qstats']['undisplayed_perc'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unrated'] = $teststat['qstats']['unrated'];
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unrated_perc'] = $teststat['qstats']['unrated_perc'];
            } else {
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['recurrence'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['right'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['right_perc'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['wrong'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['wrong_perc'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unanswered'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unanswered_perc'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['undisplayed'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['undisplayed_perc'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unrated'] = '';
                $data['testuser']["'" . $mr['testuser_id'] . "'"]['unrated_perc'] = '';
            }

            $data['testuser']["'" . $mr['testuser_id'] . "'"]['locked'] = $mr['testuser_status'] > 3;

            // remaining user time in minutes
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['remaining_time'] =
                round((time() - strtotime($usrtestdata['user_test_start_time'])) / K_SECONDS_IN_MINUTE)
                - $usrtestdata['test_duration_time'];
            $data['testuser']["'" . $mr['testuser_id'] . "'"]['user_comment'] = $usrtestdata['user_comment'];
            // SVG points
            $current_testuser = $data['testuser']["'" . $mr['testuser_id'] . "'"];
            /** @var array{total_score_perc:float|int,right_perc:float|int|string} $current_testuser */
            $data['svgpoints'] .=
                'x'
                . $current_testuser['total_score_perc']
                . 'v'
                . $current_testuser['right_perc'];
            // collects data for descriptive statistics
            // @mago-expect analysis:invalid-array-access -- active DAL fetches test-user statistic rows as arrays
            $statsdata['score'][] = $mr['total_score'];
            $statsdata['score_perc'][] = $total_score_perc;
            if ($stats > 0) {
                $statsdata['recurrence'][] = $teststat['qstats']['recurrence'];
                $statsdata['right'][] = $teststat['qstats']['right'];
                $statsdata['right_perc'][] = $teststat['qstats']['right_perc'];
                $statsdata['wrong'][] = $teststat['qstats']['wrong'];
                $statsdata['wrong_perc'][] = $teststat['qstats']['wrong_perc'];
                $statsdata['unanswered'][] = $teststat['qstats']['unanswered'];
                $statsdata['unanswered_perc'][] = $teststat['qstats']['unanswered_perc'];
                $statsdata['undisplayed'][] = $teststat['qstats']['undisplayed'];
                $statsdata['undisplayed_perc'][] = $teststat['qstats']['undisplayed_perc'];
                $statsdata['unrated'][] = $teststat['qstats']['unrated'];
                $statsdata['unrated_perc'][] = $teststat['qstats']['unrated_perc'];
            } else {
                $statsdata['recurrence'][] = '';
                $statsdata['right'][] = '';
                $statsdata['right_perc'][] = '';
                $statsdata['wrong'][] = '';
                $statsdata['wrong_perc'][] = '';
                $statsdata['unanswered'][] = '';
                $statsdata['unanswered_perc'][] = '';
                $statsdata['undisplayed'][] = '';
                $statsdata['undisplayed_perc'][] = '';
                $statsdata['unrated'][] = '';
                $statsdata['unrated_perc'][] = '';
            }
        }
    } else {
        F_display_db_error();
    }

    $data['passed'] = $passed;
    $passed_perc = 0;
    if ($itemcount > 0) {
        $passed_perc = round((100 * $passed) / $itemcount);
    }

    $data['passed_perc'] = $passed_perc;
    $data['num_records'] = $itemcount;
    if ($itemcount > 0) {
        // calculate statistics
        $data['statistics'] = f_get_array_statistics($statsdata);
    }

    return $data;
}

/**
 * Returns a safe ORDER BY clause for user test statistics queries.
 *
 * @param mixed $full_order_field Raw ORDER BY input.
 * @return string
 */
function f_get_safe_users_test_stat_order_by(mixed $full_order_field): string
{
    $allowed_fields = [
        'testuser_creation_time',
        'testuser_end_time',
        'user_name',
        'user_lastname',
        'user_firstname',
        'total_score',
        'testuser_test_id',
    ];
    $fallback_order = 'total_score, user_lastname, user_firstname';

    $safe_parts = [];
    foreach (explode(',', (string) $full_order_field) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        $matches = [];
        if (preg_match('/^([a-z_]+)(?:\s+(DESC))?$/i', $part, $matches) !== 1) {
            continue;
        }

        /** @var array{0:string,1:string,2?:string} $matches */
        $field = strtolower($matches[1]);
        if (!in_array($field, $allowed_fields, true)) {
            continue;
        }

        $direction = isset($matches[2]) ? ' DESC' : '';
        $safe_parts[] = $field . $direction;
    }

    if ($safe_parts === []) {
        return $fallback_order;
    }

    return implode(', ', $safe_parts);
}

/**
 * Lock the user's test.<br>
 * @param mixed $test_id Test ID
 * @param mixed $user_id User ID
 */
function f_lock_user_test(mixed $test_id, mixed $user_id): void
{
    require_once '../config/tce_config.php';
    global $db, $l;
    $test_id = (int) $test_id;
    $user_id = (int) $user_id;
    $sql =
        'UPDATE '
        . K_TABLE_TEST_USER
        . "
			SET testuser_status=4,
				testuser_close_reason='completed',
				testuser_last_activity='" . date(K_TIMESTAMP_FORMAT) . "'
			WHERE testuser_test_id="
        . $test_id
        . '
				AND testuser_user_id='
        . $user_id
        . '
				AND testuser_status<4';
    if (!($r = F_db_query($sql, $db))) {
        F_display_db_error();
    }
}

/**
 * Returns a comma separated string of test IDs with test_results_to_users enabled
 * @param mixed $test_id Test ID.
 * @param mixed $user_id User ID.
 * @return string
 */
function f_get_test_id_results(mixed $test_id, mixed $user_id): string
{
    return f_get_test_ids($test_id, $user_id, 'test_results_to_users');
}

/**
 * Returns a comma separated string of test IDs with test_results_to_users enabled
 * @param mixed $test_id Test ID.
 * @param mixed $user_id User ID.
 * @return string
 */
function f_get_test_id_reports(mixed $test_id, mixed $user_id): string
{
    return f_get_test_ids($test_id, $user_id, 'test_report_to_users');
}

/**
 * Returns a comma separated string of test IDs with test_results_to_users enabled
 * @param mixed $test_id Test ID.
 * @param mixed $user_id User ID.
 * @param mixed $filter Visibility field.
 * @return string
 */
function f_get_test_ids(mixed $test_id, mixed $user_id, mixed $filter = 'test_results_to_users'): string
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $str = '0'; // string to return
    $test_id = (int) $test_id;
    $user_id = (int) $user_id;
    $sql =
        'SELECT test_id FROM '
        . K_TABLE_TESTS
        . ' WHERE test_id IN (SELECT DISTINCT testuser_test_id FROM '
        . K_TABLE_TEST_USER
        . ' WHERE testuser_user_id='
        . (int) $user_id
        . ' AND testuser_status>3) AND '
        . $filter
        . '=1';
    if ($r = F_db_query($sql, $db)) {
        while ($m = F_db_fetch_assoc($r)) {
            // @mago-expect analysis:invalid-array-access -- active DAL fetches permitted test rows as arrays
            $str .= ',' . $m['test_id'];
        }
    } else {
        F_display_db_error();
    }

    return $str;
}
