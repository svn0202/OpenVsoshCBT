<?php

//============================================================+
// File name   : tce_show_result_user.php
// Begin       : 2004-06-10
// Last Update : 2023-11-30
//
// Description : Display test results to the current user.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display test results to the current user.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2004-06-10
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_PUBLIC_TEST_RESULTS;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     t_test_results:string,a_meta_charset:string,w_user:string,w_test:string,w_time_begin:string,h_time_begin:string,
 *     w_time_end:string,h_time_end:string,w_test_time:string,w_passed:string,w_not_passed:string,w_score:string,
 *     h_score_total:string,w_answers_right:string,h_answers_right:string,w_answers_wrong:string,h_answers_wrong:string,
 *     w_questions_unanswered:string,h_questions_unanswered:string,w_questions_undisplayed:string,
 *     h_questions_undisplayed:string,w_questions_unrated:string,h_questions_unrated:string,w_comment:string,
 *     h_testcomment:string,w_stats:string,h_pdf:string,w_pdf:string,h_index:string,w_index:string,hp_result_user:string
 * } $l
 */
/** @var mixed $db */
/** @var array{session_user_id:int|string} $session */
$session = $_SESSION;

$thispage_title = $l['t_test_results'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_test.php';
require_once '../../shared/code/tce_functions_test_stats.php';
require_once '../../shared/code/tce_functions_result_publication.php';

$user_id = (int) $session['session_user_id'];

$requested_testuser_id = $_REQUEST['testuser_id'] ?? null;
if ($requested_testuser_id !== null && f_tce_public_result_is_positive($requested_testuser_id)) {
    $testuser_id = (int) $requested_testuser_id;
} else {
    header('Location: index.php'); //redirect browser to public main page
    exit();
}

$requested_test_id = $_REQUEST['test_id'] ?? null;
if ($requested_test_id !== null && f_tce_public_result_is_positive($requested_test_id)) {
    $test_id = (int) $requested_test_id;
} else {
    header('Location: index.php'); //redirect browser to public main page
    exit();
}

// security check
$checkid = -1;
$sqlt =
    'SELECT testuser_user_id FROM '
    . K_TABLE_TEST_USER
    . ' WHERE testuser_test_id='
    . $test_id
    . ' AND testuser_id='
    . $testuser_id
    . ' AND testuser_status>3';
$rt = f_tce_public_result_query_result(F_db_query($sqlt, $db));
if ($rt) {
    $mt = f_tce_public_result_row(F_db_fetch_assoc($rt));
    if ($mt) {
        /** @var array{testuser_user_id:int|string} $mt */
        $checkid = (int) $mt['testuser_user_id'];
    }
} else {
    F_display_db_error();
}

if ($user_id !== $checkid) {
    header('Location: index.php'); //redirect browser to public main page
    exit();
}

// get user's test stats
$userdata = f_tce_public_result_user(f_get_user_data($user_id));
$teststat = f_tce_public_result_statistics(f_get_test_stat($test_id, 0, $user_id, 0, 0, $testuser_id, true));

$teststat['testinfo'] = f_tce_public_result_test_info(f_get_user_test_stat($test_id, $user_id, $testuser_id, true));
$test_id = (int) $teststat['testinfo']['test_id'];

if (!F_tmf_results_are_published($teststat['testinfo'])) {
    header('Location: index.php'); //redirect browser to public main page
    exit();
}

//lock user's test
f_lock_user_test($test_id, $user_id);

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;

$usr_all = htmlspecialchars(
    F_tmf_result_identity($userdata, f_get_boolean($teststat['testinfo']['test_results_anonymized'] ?? false)),
    ENT_NOQUOTES,
    $l['a_meta_charset'],
);
echo get_form_description_line($l['w_user'] . ':', $l['w_user'], $usr_all);

$test_all =
    '<strong>'
    . htmlspecialchars($teststat['testinfo']['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
    . '</strong><br />'
    . K_NEWLINE;
$test_all .= htmlspecialchars($teststat['testinfo']['test_description'], ENT_NOQUOTES, $l['a_meta_charset']);
echo get_form_description_line($l['w_test'] . ':', $l['w_test'], $test_all);

echo
    get_form_description_line($l['w_time_begin'] . ':', $l['h_time_begin'], $teststat['testinfo']['user_test_start_time'])
;
echo get_form_description_line($l['w_time_end'] . ':', $l['h_time_end'], $teststat['testinfo']['user_test_end_time']);

$user_test_start_time = f_tce_public_result_string($teststat['testinfo']['user_test_start_time']);
$user_test_end_time = $teststat['testinfo']['user_test_end_time'];
if (
    !f_tce_public_result_is_positive($user_test_end_time)
    || (int) strtotime(f_tce_public_result_string($user_test_end_time)) < (int) strtotime($user_test_start_time)
) {
    $time_diff = $teststat['testinfo']['test_duration_time'] * 60;
} else {
    $time_diff =
        (int) strtotime(f_tce_public_result_string($user_test_end_time))
        - (int) strtotime($user_test_start_time); //sec
}

$time_diff = gmdate('H:i:s', (int) $time_diff);
echo get_form_description_line($l['w_test_time'] . ':', $l['w_test_time'], $time_diff);

$passmsg = '';
if ($teststat['testinfo']['test_score_threshold'] > 0) {
    if ($teststat['testinfo']['user_score'] >= $teststat['testinfo']['test_score_threshold']) {
        $passmsg = ' - ' . $l['w_passed'];
    } else {
        $passmsg = ' - ' . $l['w_not_passed'];
    }
}

if ($teststat['testinfo']['test_max_score'] > 0) {
    $score_all =
        $teststat['testinfo']['user_score']
        . ' / '
        . $teststat['testinfo']['test_max_score']
        . ' ('
        . round((100 * $teststat['testinfo']['user_score']) / $teststat['testinfo']['test_max_score'])
        . '%)';
} else {
    $score_all = $teststat['testinfo']['user_score'];
}

echo get_form_description_line($l['w_score'] . ':', $l['h_score_total'], $score_all . $passmsg);

$score_right_all =
    $teststat['qstats']['right']
    . ' / '
    . $teststat['qstats']['recurrence']
    . ' ('
    . $teststat['qstats']['right_perc']
    . '%)';
echo get_form_description_line($l['w_answers_right'] . ':', $l['h_answers_right'], $score_right_all);

$score_wrong_all =
    $teststat['qstats']['wrong']
    . ' / '
    . $teststat['qstats']['recurrence']
    . ' ('
    . $teststat['qstats']['wrong_perc']
    . '%)';
echo get_form_description_line($l['w_answers_wrong'] . ':', $l['h_answers_wrong'], $score_wrong_all);

$score_unanswered_all =
    $teststat['qstats']['unanswered']
    . ' / '
    . $teststat['qstats']['recurrence']
    . ' ('
    . $teststat['qstats']['unanswered_perc']
    . '%)';
echo get_form_description_line($l['w_questions_unanswered'] . ':', $l['h_questions_unanswered'], $score_unanswered_all);

$score_undisplayed_all =
    $teststat['qstats']['undisplayed']
    . ' / '
    . $teststat['qstats']['recurrence']
    . ' ('
    . $teststat['qstats']['undisplayed_perc']
    . '%)';
echo get_form_description_line($l['w_questions_undisplayed'] . ':', $l['h_questions_undisplayed'], $score_undisplayed_all);

$score_unrated_all =
    $teststat['qstats']['unrated']
    . ' / '
    . $teststat['qstats']['recurrence']
    . ' ('
    . $teststat['qstats']['unrated_perc']
    . '%)';
echo get_form_description_line($l['w_questions_unrated'] . ':', $l['h_questions_unrated'], $score_unrated_all);

echo
    get_form_description_line(
        $l['w_comment'] . ':',
        $l['h_testcomment'],
        F_decode_tcecode($teststat['testinfo']['user_comment']),
    )
;

if (f_get_boolean($teststat['testinfo']['test_report_to_users'])) {
    echo '<div class="rowl">' . K_NEWLINE;
    echo f_tce_public_result_string(f_print_user_test_stat($testuser_id));
    echo '</div>' . K_NEWLINE;

    // print statistics for modules and subjects
    echo '<div class="rowl">' . K_NEWLINE;
    echo '<hr />' . K_NEWLINE;
    echo '<h2>' . $l['w_stats'] . '</h2>';
    echo f_tce_public_result_string(f_print_test_stat($test_id, 0, $user_id, 0, 0, $testuser_id, $teststat, 2, true));
    echo '<hr />' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    if (f_tce_public_result_bool(K_ENABLE_PUBLIC_PDF)) {
        echo '<div class="row">' . K_NEWLINE;
        // PDF button
        echo
            '<a href="tce_pdf_results.php?mode=3&amp;test_id='
                . $test_id
                . '&amp;user_id='
                . $user_id
                . '&amp;testuser_id='
                . $testuser_id
                . '" class="xmlbutton" title="'
                . $l['h_pdf']
                . '">'
                . $l['w_pdf']
                . '</a> '
        ;
        echo '</div>' . K_NEWLINE;
    }
}

echo '</div>' . K_NEWLINE;

echo '<a href="index.php" title="' . $l['h_index'] . '">&lt; ' . $l['w_index'] . '</a>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_result_user'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_public_result_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** Preserve legacy positive-value comparisons. */
function f_tce_public_result_is_positive(mixed $value): bool
{
    if (is_array($value) || is_object($value)) {
        return true;
    }

    if (is_resource($value)) {
        return (int) $value > 0;
    }

    if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
        return $value > 0;
    }

    return false;
}

function f_tce_public_result_bool(bool $value): bool
{
    return $value;
}

/** @return object|resource|bool */
function f_tce_public_result_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<array-key,mixed>|null */
function f_tce_public_result_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/** @return array<string,mixed> */
function f_tce_public_result_user(mixed $user): array
{
    /** @var array<string,mixed> $user */
    return $user;
}

/**
 * @return array{qstats:array{
 *     right:int|float,recurrence:int|float,right_perc:int|float,wrong:int|float,wrong_perc:int|float,
 *     unanswered:int|float,unanswered_perc:int|float,undisplayed:int|float,undisplayed_perc:int|float,
 *     unrated:int|float,unrated_perc:int|float
 * },testinfo?:array<array-key,mixed>}
 */
function f_tce_public_result_statistics(mixed $statistics): array
{
    /**
     * @var array{qstats:array{
     *     right:int|float,recurrence:int|float,right_perc:int|float,wrong:int|float,wrong_perc:int|float,
     *     unanswered:int|float,unanswered_perc:int|float,undisplayed:int|float,undisplayed_perc:int|float,
     *     unrated:int|float,unrated_perc:int|float
     * },testinfo?:array<array-key,mixed>} $statistics
     */
    return $statistics;
}

/**
 * @return array{
 *     test_id:int|string,test_name:string,test_description:string,user_test_start_time:string,
 *     user_test_end_time:int|string|null,test_duration_time:int|float,test_score_threshold:int|float,
 *     user_score:int|float,test_max_score:int|float,user_comment:string,test_report_to_users:mixed,
 *     test_results_anonymized?:mixed
 * }
 */
function f_tce_public_result_test_info(mixed $test_info): array
{
    /**
     * @var array{
     *     test_id:int|string,test_name:string,test_description:string,user_test_start_time:string,
     *     user_test_end_time:int|string|null,test_duration_time:int|float,test_score_threshold:int|float,
     *     user_score:int|float,test_max_score:int|float,user_comment:string,test_report_to_users:mixed,
     *     test_results_anonymized?:mixed
     * } $test_info
     */
    return $test_info;
}
