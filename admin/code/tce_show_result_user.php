<?php

//============================================================+
// File name   : tce_show_result_user.php
// Begin       : 2004-06-10
// Last Update : 2026-03-08
//
// Description : Display test results for specified user.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display test results for specified user.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2004-06-10
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     a_meta_charset:string,h_add_five_minutes:string,h_answers_right:string,h_answers_wrong:string,h_cancel:string,
 *     h_delete:string,h_email_result:string,h_pdf:string,h_questions_unanswered:string,h_questions_undisplayed:string,
 *     h_questions_unrated:string,h_score_total:string,h_select_user:string,h_test:string,h_testcomment:string,
 *     h_time_begin:string,h_time_end:string,hp_result_user:string,m_authorization_denied:string,m_delete_confirm:string,
 *     m_deleted:string,m_updated:string,t_result_user:string,w_answers_right:string,w_answers_wrong:string,w_cancel:string,
 *     w_comment:string,w_delete:string,w_email_result:string,w_lock:string,w_not_passed:string,w_passed:string,w_pdf:string,
 *     w_questions_unanswered:string,w_questions_undisplayed:string,w_questions_unrated:string,w_score:string,w_select:string,
 *     w_stats:string,w_test:string,w_test_time:string,w_time_begin:string,w_time_end:string,w_unlock:string,w_user:string
 * } $l
 */
/** @var mixed $db */
$formstatus = f_tce_admin_result_user_bool($formstatus ?? false);
$menu_mode = f_tce_admin_result_user_string($menu_mode ?? '');
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;

$thispage_title = $l['t_result_user'];
require_once 'tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_test.php';
require_once '../../shared/code/tce_functions_test_stats.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_user_select.php';

// comma separated list of required fields
$_REQUEST['ff_required'] = '';
$_REQUEST['ff_required_labels'] = '';

$filter = '';

if (isset($_REQUEST['test_id']) && (int) $_REQUEST['test_id'] > 0) {
    $test_id = (int) $_REQUEST['test_id'];
    // check user's authorization
    if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }

    $filter .= '&amp;test_id=' . $test_id . '';
} else {
    $test_id = 0;
}

if (isset($_REQUEST['testuser_id'])) {
    $testuser_id = (int) $_REQUEST['testuser_id'];
    if ((int) ($_SESSION['session_user_level'] ?? 0) < K_AUTH_ADMINISTRATOR) {
        $sql =
            K_TABLE_TESTS
            . ', '
            . K_TABLE_TEST_USER
            . '
            WHERE testuser_test_id=test_id
                AND test_id='
            . $test_id
            . '
                AND testuser_id='
            . $testuser_id
            . '
            LIMIT 1';
        if (F_count_rows($sql) < 1) {
            F_print_error('ERROR', $l['m_authorization_denied'], true);
        }
    }
    $filter .= '&amp;testuser_id=' . $testuser_id;
} else {
    $testuser_id = 0;
}

if (isset($_REQUEST['user_id'])) {
    $user_id = (int) $_REQUEST['user_id'];
    if ((int) ($_SESSION['session_user_level'] ?? 0) < K_AUTH_ADMINISTRATOR) {
        $sql =
            K_TABLE_TESTS
            . ', '
            . K_TABLE_TEST_USER
            . '
            WHERE testuser_test_id=test_id
                AND test_id='
            . $test_id
            . '
                AND testuser_user_id='
            . $user_id
            . '
            LIMIT 1';
        if (F_count_rows($sql) < 1) {
            F_print_error('ERROR', $l['m_authorization_denied'], true);
        }
    }
    $filter .= '&amp;user_id=' . $user_id;
} else {
    $user_id = 0;
}

if (isset($_REQUEST['selectcategory'])) {
    $changecategory = 1;
}

if (isset($_POST['lock'])) {
    $menu_mode = 'lock';
} elseif (isset($_POST['unlock'])) {
    $menu_mode = 'unlock';
} elseif (isset($_POST['extendtime'])) {
    $menu_mode = 'extendtime';
}

switch ($menu_mode) {
    case 'delete':
        // ask confirmation
        F_print_error('WARNING', $l['m_delete_confirm']);
        echo '<div class="confirmbox">' . K_NEWLINE;
        echo
            '<form action="'
                . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
                . '" method="post" enctype="multipart/form-data" id="form_delete">'
                . K_NEWLINE
        ;
        echo '<div>' . K_NEWLINE;
        echo '<input type="hidden" name="testuser_id" id="testuser_id" value="' . $testuser_id . '" />' . K_NEWLINE;
        F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
        F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
        echo '</div>' . K_NEWLINE;
        echo f_tce_admin_result_user_string(f_get_csrf_token_field()) . K_NEWLINE;
        echo '</form>' . K_NEWLINE;
        echo '</div>' . K_NEWLINE;
        break;

    case 'forcedelete':
        // Delete
        if (f_form_option_is_selected($l['w_delete'], $_POST['forcedelete'] ?? '')) { //check if delete button has been pushed (redundant check)
            require_once '../../shared/code/tce_functions_attachments.php';
            F_tmf_attachment_delete_attempt((int) $testuser_id);
            $sql = 'DELETE FROM ' . K_TABLE_TEST_USER . '
					WHERE testuser_id=' . $testuser_id . '';
            $r = f_tce_admin_result_user_query_result(F_db_query($sql, $db));
            if (!$r) {
                F_display_db_error();
            } else {
                $testuser_id = false;
                F_print_error('MESSAGE', $l['m_deleted']);
            }
        }

        break;

    case 'extendtime':
        // extend the test time by 5 minutes
        // this time extension is obtained moving forward the test starting time
        $sqlu =
            'UPDATE '
            . K_TABLE_TEST_USER
            . '
			SET testuser_creation_time=\''
            . date(
                K_TIMESTAMP_FORMAT,
                (int) f_get_test_start_time($testuser_id) + (K_EXTEND_TIME_MINUTES * K_SECONDS_IN_MINUTE),
            )
            . '\'
			WHERE testuser_id='
            . f_tce_admin_result_user_string($testuser_id)
            . '';
        $ru = f_tce_admin_result_user_query_result(F_db_query($sqlu, $db));
        if (!$ru) {
            F_display_db_error();
        } else {
            F_print_error('MESSAGE', $l['m_updated']);
        }

        break;

    case 'lock':
        // update test mode to 4 = test locked
        $sqlu = 'UPDATE ' . K_TABLE_TEST_USER . '
			SET testuser_status=4
			WHERE testuser_id=' . $testuser_id . '';
        $ru = f_tce_admin_result_user_query_result(F_db_query($sqlu, $db));
        if (!$ru) {
            F_display_db_error();
        } else {
            F_print_error('MESSAGE', $l['m_updated']);
        }

        break;

    case 'unlock':
        // update test mode to 1 = test unlocked
        $sqlu = 'UPDATE ' . K_TABLE_TEST_USER . '
			SET testuser_status=1
			WHERE testuser_id=' . $testuser_id . '';
        $ru = f_tce_admin_result_user_query_result(F_db_query($sqlu, $db));
        if (!$ru) {
            F_display_db_error();
        } else {
            F_print_error('MESSAGE', $l['m_updated']);
        }

        break;

    default:
        break;
} //end of switch

// --- Initialize variables

$test_start_time = '';
$test_end_time = '';
$testuser_status = 0;
$test_duration_time = 0;
$teststat = null;

if ($test_id === 0 && f_form_option_is_selected(0, $testuser_id)) {
    // select default test ID
    $sql = f_tce_admin_result_user_string(F_select_executed_tests_sql()) . ' LIMIT 1';
    $r = f_tce_admin_result_user_query_result(F_db_query($sql, $db));
    if ($r) {
        if (($m = f_tce_admin_result_user_executed_test_row(F_db_fetch_array($r))) !== null) {
            $test_id = (int) $m['test_id'];
        }
    } else {
        F_display_db_error();
    }
}

if ($formstatus) {
    if (isset($changecategory) || $testuser_id === 0) {
        $sql =
            'SELECT testuser_id, testuser_test_id, testuser_user_id, testuser_creation_time, testuser_status, SUM(testlog_score) AS test_score, MAX(testlog_change_time) AS test_end_time
				FROM '
            . K_TABLE_TEST_USER
            . ', '
            . K_TABLE_TESTS_LOGS
            . '
				WHERE testlog_testuser_id=testuser_id
					AND testuser_test_id='
            . $test_id
            . '
					AND testuser_status>0
				GROUP BY testuser_id, testuser_test_id, testuser_user_id, testuser_creation_time, testuser_status
				ORDER BY testuser_test_id
				LIMIT 1';
    } else {
        $sql =
            'SELECT testuser_id, testuser_test_id, testuser_user_id, testuser_creation_time, testuser_status, MAX(testlog_change_time) AS test_end_time
			FROM '
            . K_TABLE_TEST_USER
            . ', '
            . K_TABLE_TESTS_LOGS
            . '
				WHERE testlog_testuser_id=testuser_id
					AND testuser_id='
            . f_tce_admin_result_user_string($testuser_id)
            . '
				AND testuser_status>0
			GROUP BY testuser_id, testuser_test_id, testuser_user_id, testuser_creation_time, testuser_status
			LIMIT 1';
    }

    $r = f_tce_admin_result_user_query_result(F_db_query($sql, $db));
    if ($r) {
        if (($m = f_tce_admin_result_user_attempt_row(F_db_fetch_array($r))) !== null) {
            $testuser_id = (int) $m['testuser_id'];
            $test_id = (int) $m['testuser_test_id'];
            $user_id = (int) $m['testuser_user_id'];
            $test_start_time = $m['testuser_creation_time'];
            $testuser_status = (int) $m['testuser_status'];
            $teststat = f_tce_admin_result_user_test_stat(
                f_get_test_stat($test_id, 0, $user_id, 0, 0, $testuser_id),
            );
            $test_end_time = f_tce_admin_result_user_string($m['test_end_time']);
        } else {
            $testuser_id = '';
            $test_id = '';
            $user_id = '';
            $test_start_time = '';
            $test_end_time = '';
            $testuser_status = 0;
        }
    } else {
        F_display_db_error();
    }
}

// get test basic score
$test_basic_score = 1;
$sql = 'SELECT test_score_right, test_duration_time	FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . (int) $test_id . '';
$r = f_tce_admin_result_user_query_result(F_db_query($sql, $db));
if ($r) {
    if (($m = f_tce_admin_result_user_basic_test_row(F_db_fetch_array($r))) !== null) {
        $test_basic_score = (float) $m['test_score_right'];
        $test_duration_time = (int) $m['test_duration_time'];
    }
} else {
    F_display_db_error();
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_resultuser">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="test_id">' . $l['w_test'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="testuser_id" id="testuser_id" value="'
    . f_tce_admin_result_user_string($testuser_id) . '" />' . K_NEWLINE;
echo '<input type="hidden" name="changecategory" id="changecategory" value="" />' . K_NEWLINE;
echo
    '<select name="test_id" id="test_id" onchange="document.getElementById(\'form_resultuser\').changecategory.value=1;document.getElementById(\'form_resultuser\').submit()" title="'
        . $l['h_test']
        . '">'
        . K_NEWLINE
;
$sql = f_tce_admin_result_user_string(F_select_executed_tests_sql());
$r = f_tce_admin_result_user_query_result(F_db_query($sql, $db));
if ($r) {
    while (($m = f_tce_admin_result_user_executed_test_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['test_id'] . '"';
        if (f_form_option_is_selected((int) $test_id, $m['test_id'])) {
            echo ' selected="selected"';
        }

        echo
            '>'
                . substr($m['test_begin_time'], 0, 10)
                . ' '
                . htmlspecialchars($m['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                . '</option>'
                . K_NEWLINE
        ;
    }
} else {
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;

// link for user selection popup
$jsaction = "selectWindow=window.open('tce_select_tests_popup.php?cid=test_id', 'selectWindow', 'dependent, height=600, width=800, menubar=no, resizable=yes, scrollbars=yes, status=no, toolbar=no'); return false;";
echo '<button type="button" onclick="' . $jsaction . '" class="xmlbutton" title="' . $l['w_select'] . '">...</button>';

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_admin_result_user_string(get_form_noscript_select('selectcategory'));

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="testuser_id">' . $l['w_user'] . ' - ' . $l['w_test'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="testuser_id" id="testuser_id" onchange="document.getElementById(\'form_resultuser\').submit()" title="'
        . $l['h_select_user']
        . '">'
        . K_NEWLINE
;
$sql =
    'SELECT testuser_id, user_lastname, user_firstname, user_name, testuser_creation_time FROM '
    . K_TABLE_TEST_USER
    . ', '
    . K_TABLE_USERS
    . ' WHERE testuser_user_id=user_id AND testuser_test_id='
    . (int) $test_id
    . '';
$sql .= ' ORDER BY user_lastname, user_firstname, user_name, testuser_creation_time DESC';
$r = f_tce_admin_result_user_query_result(F_db_query($sql, $db));
if ($r) {
    $usrcount = 1;
    while (($m = f_tce_admin_result_user_selection_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['testuser_id'] . '"';
        if (f_form_option_is_selected((int) $testuser_id, $m['testuser_id'])) {
            echo ' selected="selected"';
        }

        echo '>';
        echo '' . $usrcount . '. ';
        echo
            ''
                . htmlspecialchars(
                    $m['user_lastname']
                    . ' '
                    . $m['user_firstname']
                    . ' - '
                    . $m['user_name']
                    . ' ['
                    . $m['testuser_creation_time']
                    . ']',
                    ENT_NOQUOTES,
                    $l['a_meta_charset'],
                )
                . ''
        ;
        echo '</option>' . K_NEWLINE;
        ++$usrcount;
    }
} else {
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_admin_result_user_string(get_form_noscript_select('selectrecord'));

echo '<div class="row"><hr /></div>' . K_NEWLINE;

if ($teststat !== null && $teststat !== []) {
    $teststat['testinfo'] = f_tce_admin_result_user_test_info(
        f_get_user_test_stat((int) $test_id, (int) $user_id, (int) $testuser_id),
    );

    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<span title="' . $l['h_time_begin'] . '">' . $l['w_time_begin'] . ':</span>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo $test_start_time . ' ';
    if ((int) $test_id > 0 && (int) $user_id > 0) {
        F_submit_button('extendtime', '+' . K_EXTEND_TIME_MINUTES . ' min', $l['h_add_five_minutes']);
    }

    echo '&nbsp;' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    echo get_form_description_line($l['w_time_end'] . ':', $l['h_time_end'], $test_end_time);

    $test_end_timestamp = (int) strtotime($test_end_time);
    $test_start_timestamp = (int) strtotime($test_start_time);
    if (f_tce_admin_result_user_is_non_positive($test_end_time) || $test_end_timestamp < $test_start_timestamp) {
        $time_diff = $test_duration_time * 60;
    } else {
        $time_diff = $test_end_timestamp - $test_start_timestamp; //sec
    }

    $time_diff = gmdate('H:i:s', $time_diff);
    echo get_form_description_line($l['w_test_time'] . ':', $l['w_test_time'], $time_diff);
    $passmsg = '';
    if ((float) $teststat['testinfo']['test_score_threshold'] > 0) {
        if (
            (float) $teststat['testinfo']['user_score'] >= (float) $teststat['testinfo']['test_score_threshold']
        ) {
            $passmsg = ' - ' . $l['w_passed'];
        } else {
            $passmsg = ' - ' . $l['w_not_passed'];
        }
    }

    if ((float) $teststat['testinfo']['test_max_score'] > 0) {
        $score_all =
            $teststat['testinfo']['user_score']
            . ' / '
            . $teststat['testinfo']['test_max_score']
            . ' ('
            . round(
                (100 * (float) $teststat['testinfo']['user_score']) / (float) $teststat['testinfo']['test_max_score'],
            )
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
    echo
        get_form_description_line($l['w_questions_unanswered'] . ':', $l['h_questions_unanswered'], $score_unanswered_all)
    ;

    $score_undisplayed_all =
        $teststat['qstats']['undisplayed']
        . ' / '
        . $teststat['qstats']['recurrence']
        . ' ('
        . $teststat['qstats']['undisplayed_perc']
        . '%)';
    echo
        get_form_description_line(
            $l['w_questions_undisplayed'] . ':',
            $l['h_questions_undisplayed'],
            $score_undisplayed_all,
        )
    ;

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
            f_tce_admin_result_user_string(F_decode_tcecode($teststat['testinfo']['user_comment'])),
        )
    ;

    if ($testuser_id !== 0) {
        echo '<div class="rowl">' . K_NEWLINE;
        echo f_tce_admin_result_user_string(f_print_user_test_stat((int) $testuser_id));
        echo '</div>' . K_NEWLINE;

        // print statistics for modules and subjects
        echo '<div class="rowl">' . K_NEWLINE;
        echo '<hr />' . K_NEWLINE;
        echo '<h2>' . $l['w_stats'] . '</h2>';
        echo f_tce_admin_result_user_string(
            f_print_test_stat((int) $test_id, 0, (int) $user_id, 0, 0, (int) $testuser_id, $teststat, 2),
        );
        echo '<hr />' . K_NEWLINE;
        echo '</div>' . K_NEWLINE;
    }

    echo '<div class="row">' . K_NEWLINE;

    // show buttons by case
    if ((int) $test_id > 0 && (int) $user_id > 0 && (int) $testuser_id > 0) {
        F_submit_button('delete', $l['w_delete'], $l['h_delete']);

        if ((int) $testuser_status < 4) {
            // lock test button
            F_submit_button('lock', $l['w_lock'], $l['w_lock']);
        } else {
            // unlock test button
            F_submit_button('unlock', $l['w_unlock'], $l['w_unlock']);
            echo '<br /><br />';
            echo
                '<a href="tce_pdf_results.php?mode=3'
                    . $filter
                    . '" class="xmlbutton" title="'
                    . $l['h_pdf']
                    . '">'
                    . $l['w_pdf']
                    . '</a> '
            ;
            echo '<a href="tce_attempt_archive.php?testuser_id=' . (int) $testuser_id
                . '" class="xmlbutton" title="Скачать архив работы с вложениями">ZIP</a> ';
            echo
                '<a href="tce_email_results.php?mode=1&amp;menu_mode=startlongprocess'
                    . $filter
                    . '" class="xmlbutton" title="'
                    . $l['h_email_result']
                    . '">'
                    . $l['w_email_result']
                    . '</a> '
            ;
            echo
                '<a href="tce_email_results.php?mode=0&amp;menu_mode=startlongprocess'
                    . $filter
                    . '" class="xmlbutton" title="'
                    . $l['h_email_result']
                    . ' + PDF">'
                    . $l['w_email_result']
                    . ' + PDF</a> '
            ;
        }
    }

    echo '</div>' . K_NEWLINE;
}

echo f_tce_admin_result_user_string(f_get_csrf_token_field()) . K_NEWLINE;
echo '</form>' . K_NEWLINE;

echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_result_user'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

function f_tce_admin_result_user_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function f_tce_admin_result_user_bool(mixed $value): bool
{
    if (is_array($value)) {
        return $value !== [];
    }

    if (is_object($value) || is_resource($value)) {
        return true;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value) || is_string($value)) {
        return (bool) $value;
    }

    return false;
}

function f_tce_admin_result_user_is_non_positive(string $value): bool
{
    return $value <= 0;
}

/** @return object|resource|bool */
function f_tce_admin_result_user_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array{test_id:int|string,test_begin_time:string,test_name:string}|null */
function f_tce_admin_result_user_executed_test_row(mixed $row): ?array
{
    /** @var array{test_id:int|string,test_begin_time:string,test_name:string}|null $row */
    return $row;
}

/**
 * @return array{
 *     testuser_id:int|string,
 *     testuser_test_id:int|string,
 *     testuser_user_id:int|string,
 *     testuser_creation_time:string,
 *     testuser_status:int|string,
 *     test_end_time:string|null
 * }|null
 */
function f_tce_admin_result_user_attempt_row(mixed $row): ?array
{
    /**
     * @var array{
     *     testuser_id:int|string,
     *     testuser_test_id:int|string,
     *     testuser_user_id:int|string,
     *     testuser_creation_time:string,
     *     testuser_status:int|string,
     *     test_end_time:string|null
     * }|null $row
     */
    return $row;
}

/** @return array{test_score_right:int|float|string,test_duration_time:int|string}|null */
function f_tce_admin_result_user_basic_test_row(mixed $row): ?array
{
    /** @var array{test_score_right:int|float|string,test_duration_time:int|string}|null $row */
    return $row;
}

/**
 * @return array{
 *     testuser_id:int|string,
 *     user_lastname:string,
 *     user_firstname:string,
 *     user_name:string,
 *     testuser_creation_time:string
 * }|null
 */
function f_tce_admin_result_user_selection_row(mixed $row): ?array
{
    /**
     * @var array{
     *     testuser_id:int|string,
     *     user_lastname:string,
     *     user_firstname:string,
     *     user_name:string,
     *     testuser_creation_time:string
     * }|null $row
     */
    return $row;
}

/**
 * @return array{qstats:array{
 *     right:int|float|string,
 *     recurrence:int|float|string,
 *     right_perc:int|float|string,
 *     wrong:int|float|string,
 *     wrong_perc:int|float|string,
 *     unanswered:int|float|string,
 *     unanswered_perc:int|float|string,
 *     undisplayed:int|float|string,
 *     undisplayed_perc:int|float|string,
 *     unrated:int|float|string,
 *     unrated_perc:int|float|string
 * }}
 */
function f_tce_admin_result_user_test_stat(mixed $stat): array
{
    /**
     * @var array{qstats:array{
     *     right:int|float|string,
     *     recurrence:int|float|string,
     *     right_perc:int|float|string,
     *     wrong:int|float|string,
     *     wrong_perc:int|float|string,
     *     unanswered:int|float|string,
     *     unanswered_perc:int|float|string,
     *     undisplayed:int|float|string,
     *     undisplayed_perc:int|float|string,
     *     unrated:int|float|string,
     *     unrated_perc:int|float|string
     * }} $stat
     */
    return $stat;
}

/**
 * @return array{
 *     test_score_threshold:int|float|string,
 *     user_score:int|float|string,
 *     test_max_score:int|float|string,
 *     user_comment:string
 * }
 */
function f_tce_admin_result_user_test_info(mixed $info): array
{
    /**
     * @var array{
     *     test_score_threshold:int|float|string,
     *     user_score:int|float|string,
     *     test_max_score:int|float|string,
     *     user_comment:string
     * } $info
     */
    return $info;
}
