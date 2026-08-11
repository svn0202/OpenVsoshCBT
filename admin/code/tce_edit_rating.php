<?php

//============================================================+
// File name   : tce_edit_rating.php
// Begin       : 2004-06-09
// Last Update : 2026-03-08
//
// Description : Editor to manually rate free text answers.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display form to manually rate free-text questions.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2004-06-09
 */

require_once '../config/tce_config.php';

if (isset($_POST['selectcategory'])) {
    $selectcategory = 1;
}

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_RATING;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     a_meta_charset:string,h_answer:string,h_display_all:string,h_display_user_info:string,
 *     h_question_description:string,h_score_right:string,h_score_unanswered:string,h_score_wrong:string,
 *     h_score:string,h_select_answer:string,h_test:string,h_update:string,hp_edit_rating:string,
 *     m_authorization_denied:string,m_score_higher_than_max:string,m_updated:string,t_rating_editor:string,
 *     w_answer:string,w_comment:string,w_display_all:string,w_display_user_info:string,w_explanation:string,
 *     w_order:string,w_question:string,w_score_right:string,w_score_unanswered:string,w_score_wrong:string,
 *     w_score:string,w_select:string,w_test:string,w_time:string,w_update:string,w_user:string
 * } $l
 */
/** @var mixed $db */
/** @var string $menu_mode */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;
/** @var array{session_user_level:int|string} $session */
$session = $_SESSION;
$session_user_level = (int) $session['session_user_level'];

$thispage_title = $l['t_rating_editor'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';

if (isset($selectcategory)) {
    $changecategory = 1;
}

// explicit form inputs (register_globals emulation removed)
$testlog_score = f_tce_edit_rating_string($_REQUEST['testlog_score'] ?? '');
$testlog_comment = isset($_REQUEST['testlog_comment']) && is_string($_REQUEST['testlog_comment'])
    ? $_REQUEST['testlog_comment']
    : '';
$max_score = f_tce_edit_rating_string($_REQUEST['max_score'] ?? '');
$display_user_info = (bool) ($_REQUEST['display_user_info'] ?? false);
$display_all = (bool) ($_REQUEST['display_all'] ?? false);
$sqlordermode = (int) ($_REQUEST['sqlordermode'] ?? 0);

if (!empty($_REQUEST['test_id'])) {
    $test_id = (int) $_REQUEST['test_id'];
    // check user's authorization
    if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
} else {
    $test_id = 0;
}

if (!empty($_REQUEST['testlog_id'])) {
    $testlog_id = (int) $_REQUEST['testlog_id'];
    if ($session_user_level < f_tce_edit_rating_int(K_AUTH_ADMINISTRATOR)) {
        $sql =
            K_TABLE_TESTS
            . ', '
            . K_TABLE_TEST_USER
            . ', '
            . K_TABLE_TESTS_LOGS
            . '
            WHERE testuser_test_id=test_id
                AND testlog_testuser_id=testuser_id
                AND test_id='
            . $test_id
            . '
                AND testlog_id='
            . $testlog_id
            . '
            LIMIT 1';
        if (F_count_rows($sql) < 1) {
            F_print_error('ERROR', $l['m_authorization_denied'], true);
        }
    }
} else {
    $testlog_id = 0;
}

// comma separated list of required fields
$_REQUEST['ff_required'] = 'testlog_score';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_score'], ENT_COMPAT, $l['a_meta_charset']);

switch ($menu_mode) {
    case 'update':
        // Update
            $formstatus = F_check_form_fields();
            if ($formstatus) {
                // score cannot be greater than max_score
                $testlog_score = f_tce_edit_rating_float($testlog_score);

                $max_score = 0.0;
                $sql =
                    'SELECT test_score_right, question_difficulty
            FROM '
                    . K_TABLE_TESTS
                    . ', '
                    . K_TABLE_TEST_USER
                    . ', '
                    . K_TABLE_TESTS_LOGS
                    . ', '
                    . K_TABLE_QUESTIONS
                    . '
            WHERE testuser_test_id=test_id
                AND testlog_testuser_id=testuser_id
                AND testlog_question_id=question_id
                AND testlog_id='
                    . $testlog_id
                    . '
            LIMIT 1';
                $r = f_tce_edit_rating_query_result(F_db_query($sql, $db));
                if ($r) {
                    $m = f_tce_edit_rating_score_row(F_db_fetch_array($r));
                    if ($m !== null) {
                        $max_score = round(
                            f_tce_edit_rating_float($m['test_score_right'])
                                * f_tce_edit_rating_float($m['question_difficulty']),
                            3,
                        );
                    }
                } else {
                    F_display_db_error();
                }

                if ($testlog_score > $max_score) {
                    F_print_error('WARNING', $l['m_score_higher_than_max'], false);
                    break;
                }

                $sql =
                    'UPDATE '
                    . K_TABLE_TESTS_LOGS
                    . ' SET
					testlog_score='
                    . $testlog_score
                    . ',
					testlog_comment=\''
                    . F_escape_sql($db, $testlog_comment)
                    . '\'
					WHERE testlog_id='
                    . $testlog_id
                    . '';
                $r = f_tce_edit_rating_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    F_print_error('MESSAGE', $l['m_updated']);
                    $testlog_score = '';
                    $testlog_id = 0;
                    $testlog_comment = '';
                }
            }

            break;
    default:
            break;
} //end of switch

// --- Initialize variables

$sqlfilter = '';
if (empty($display_all)) {
    $sqlfilter = ' AND testlog_score IS NULL';
}

switch ($sqlordermode) {
    case 2:
            // ordered by test and question creation time
            $sqlorder = 'ORDER BY testuser_test_id, testlog_id';
            break;
    case 1:
            // ordered by test and question
            $sqlorder = 'ORDER BY testuser_test_id, testlog_question_id, testlog_testuser_id';
            break;
    default:
    case 0:
            // ordered by test and users
            $sqlorder = 'ORDER BY testuser_test_id, testlog_testuser_id, testlog_id';
            break;
}

$test_score_right = 1.0;
$test_score_wrong = 0.0;
$test_score_unanswered = 0.0;
$question = '';
$explanation = '';
$answer = '';

if ($test_id === 0) {
    // select one executed test
    $sql = f_tce_edit_rating_string(F_select_executed_tests_sql()) . ' LIMIT 1';
    $r = f_tce_edit_rating_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tce_edit_rating_executed_test_row(F_db_fetch_array($r));
        if ($m !== null) {
            /** @var int|numeric-string $default_test_id */
            $default_test_id = $m['test_id'];
            $test_id = (int) $default_test_id;
        } else {
            $test_id = 0;
        }
    } else {
        F_display_db_error();
    }
}

if (isset($changecategory) && f_tce_edit_rating_is_positive($changecategory) || empty($testlog_id)) {
    $sql =
        'SELECT test_id, test_score_right, test_score_wrong, test_score_unanswered, testlog_id, testlog_score, testlog_answer_text, testlog_comment, question_description, question_difficulty, question_explanation
		FROM '
        . K_TABLE_TESTS
        . ', '
        . K_TABLE_TEST_USER
        . ', '
        . K_TABLE_TESTS_LOGS
        . ', '
        . K_TABLE_QUESTIONS
        . '
		WHERE testuser_test_id=test_id
			AND testlog_testuser_id=testuser_id
			AND testlog_question_id=question_id
			AND testuser_test_id='
        . $test_id
        . '
			AND testuser_status>0
			AND testuser_status<5
			AND question_type=3
			'
        . $sqlfilter
        . '
		'
        . $sqlorder
        . '
		LIMIT 1';
} else {
    $sql =
        'SELECT test_id, test_score_right, test_score_wrong, test_score_unanswered, testlog_id, testlog_score, testlog_answer_text, testlog_comment, question_description, question_difficulty, question_explanation
		FROM '
        . K_TABLE_TESTS
        . ', '
        . K_TABLE_TEST_USER
        . ', '
        . K_TABLE_TESTS_LOGS
        . ', '
        . K_TABLE_QUESTIONS
        . '
		WHERE testuser_test_id=test_id
			AND testlog_testuser_id=testuser_id
			AND testlog_question_id=question_id
			AND testlog_id='
        . $testlog_id
        . '
		LIMIT 1';
}

$r = f_tce_edit_rating_query_result(F_db_query($sql, $db));
if ($r) {
    $m = f_tce_edit_rating_detail_row(F_db_fetch_array($r));
    if ($m !== null) {
            /** @var int|numeric-string $stored_testlog_id */
            $stored_testlog_id = $m['testlog_id'];
            /** @var int|numeric-string $stored_test_id */
            $stored_test_id = $m['test_id'];
            $testlog_id = (int) $stored_testlog_id;
            $test_id = (int) $stored_test_id;
            $testlog_score = f_tce_edit_rating_string($m['testlog_score']);
            $testlog_comment = f_tce_edit_rating_string($m['testlog_comment']);
            $difficulty = f_tce_edit_rating_float($m['question_difficulty']);
            $test_score_right = round(f_tce_edit_rating_float($m['test_score_right']) * $difficulty, 3);
            $test_score_wrong = round(f_tce_edit_rating_float($m['test_score_wrong']) * $difficulty, 3);
            $test_score_unanswered = round(f_tce_edit_rating_float($m['test_score_unanswered']) * $difficulty, 3);
            $question = f_tce_edit_rating_string(F_decode_tcecode($m['question_description']));
            $explanation = f_tce_edit_rating_string(F_decode_tcecode($m['question_explanation']));
            $answer = f_tce_edit_rating_string(F_decode_tcecode($m['testlog_answer_text']));
    } else {
        $testlog_id = 0;
        $testlog_score = '';
        $test_score_right = 1;
        $test_score_wrong = 0;
        $test_score_unanswered = 0;
        $question = '';
        $explanation = '';
        $answer = '';
        $testlog_comment = '';
    }
} else {
    F_display_db_error();
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_ratingeditor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="test_id">' . $l['w_test'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="changecategory" id="changecategory" value="" />' . K_NEWLINE;
echo
    '<select name="test_id" id="test_id" onchange="document.getElementById(\'form_ratingeditor\').changecategory.value=1;document.getElementById(\'form_ratingeditor\').submit()" title="'
        . $l['h_test']
        . '">'
        . K_NEWLINE
;
$sql = f_tce_edit_rating_string(F_select_executed_tests_sql());
$r = f_tce_edit_rating_query_result(F_db_query($sql, $db));
if ($r) {
    $m = f_tce_edit_rating_executed_test_row(F_db_fetch_array($r));
    if ($m === null) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
    while ($m !== null) {
        echo '<option value="' . $m['test_id'] . '"';
        /** @var int|numeric-string $listed_test_id */
        $listed_test_id = $m['test_id'];
        if ((int) $listed_test_id === $test_id) {
            echo ' selected="selected"';
        }

        echo
            '>'
                . substr($m['test_begin_time'], 0, 10)
                . ' : '
                . htmlspecialchars($m['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                . '</option>'
                . K_NEWLINE
        ;
        $m = f_tce_edit_rating_executed_test_row(F_db_fetch_array($r));
    }
} else {
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;

// link for user selection popup
$jsaction = "selectWindow=window.open('tce_select_tests_popup.php?cid=test_id', 'selectWindow', 'dependent, height=600, width=800, menubar=no, resizable=yes, scrollbars=yes, status=no, toolbar=no');return false;";
echo '<button type="button" onclick="' . $jsaction . '" class="xmlbutton" title="' . $l['w_select'] . '">...</button>';

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_edit_rating_string(get_form_noscript_select('selectcategory'));

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="testlog_id">' . $l['w_answer'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="testlog_id" id="testlog_id" onchange="document.getElementById(\'form_ratingeditor\').submit()" title="'
        . $l['h_select_answer']
        . '">'
        . K_NEWLINE
;
$sql =
    'SELECT testlog_id, testlog_score, user_lastname, user_firstname, user_name, question_description FROM '
    . K_TABLE_TESTS_LOGS
    . ', '
    . K_TABLE_TEST_USER
    . ', '
    . K_TABLE_USERS
    . ', '
    . K_TABLE_QUESTIONS
    . ' WHERE testlog_testuser_id=testuser_id AND testuser_user_id=user_id AND testlog_question_id=question_id AND testuser_test_id='
    . (int) $test_id
    . ' AND testuser_status>0 AND testuser_status<5 AND question_type=3 '
    . $sqlfilter
    . ' '
    . $sqlorder
    . '';
$r = f_tce_edit_rating_query_result(F_db_query($sql, $db));
if ($r) {
    $m = f_tce_edit_rating_answer_row(F_db_fetch_array($r));
    if ($m === null) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
    while ($m !== null) {
        echo '<option value="' . $m['testlog_id'] . '"';
        /** @var int|numeric-string $listed_testlog_id */
        $listed_testlog_id = $m['testlog_id'];
        if ((int) $listed_testlog_id === $testlog_id) {
            echo ' selected="selected"';
        }

        echo '>';
        if (!empty($m['testlog_score'])) {
            echo '+';
        } else {
            echo '-';
        }

        echo ' ' . $m['testlog_id'] . '';
        if ($display_user_info) {
            echo
                ' :: '
                    . htmlspecialchars(
                        $m['user_lastname'] . ' ' . $m['user_firstname'] . ' - ' . $m['user_name'] . '',
                        ENT_NOQUOTES,
                        $l['a_meta_charset'],
                    )
                    . ''
            ;
        }

        echo '</option>' . K_NEWLINE;
        $m = f_tce_edit_rating_answer_row(F_db_fetch_array($r));
    }
} else {
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_edit_rating_string(get_form_noscript_select('selectrecord'));

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="sqlordermode">' . $l['w_order'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="sqlordermode" id="sqlordermode" onchange="document.getElementById(\'form_ratingeditor\').submit()" title="'
        . $l['w_order']
        . '">'
        . K_NEWLINE
;
echo '<option value="0"';
if (f_legacy_int_equals($sqlordermode, 0)) {
    echo ' selected="selected"';
}

echo '>' . $l['w_user'] . '</option>' . K_NEWLINE;
echo '<option value="1"';
if (f_legacy_int_equals($sqlordermode, 1)) {
    echo ' selected="selected"';
}

echo '>' . $l['w_question'] . '</option>' . K_NEWLINE;
echo '<option value="2"';
if (f_legacy_int_equals($sqlordermode, 2)) {
    echo ' selected="selected"';
}

echo '>' . $l['w_time'] . '</option>' . K_NEWLINE;
echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_edit_rating_string(get_form_noscript_select('selectmode'));

echo f_tce_edit_rating_string(
    get_form_row_checkbox(
        'display_user_info',
        $l['w_display_user_info'],
        $l['h_display_user_info'],
        '',
        1,
        $display_user_info,
        false,
        '',
    ),
);
echo f_tce_edit_rating_string(
    get_form_row_checkbox('display_all', $l['w_display_all'], $l['h_display_all'], '', 1, $display_all, false, ''),
);

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<span title="' . $l['h_question_description'] . '">' . $l['w_question'] . '</span>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo $question;
echo '&nbsp;' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

if (f_tce_edit_rating_bool(K_ENABLE_QUESTION_EXPLANATION) && !empty($explanation)) {
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<span title="' . $l['w_explanation'] . '">' . $l['w_explanation'] . '</span>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo $explanation . '&nbsp;' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<span title="' . $l['h_answer'] . '">' . $l['w_answer'] . '</span>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo $answer . '&nbsp;<br />&nbsp;' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_edit_rating_string(
    get_form_row_text_input(
        'testlog_score',
        $l['w_score'],
        $l['h_score'],
        '',
        $testlog_score,
        '^([0-9\+\-]*)([\.]?)([0-9]*)$',
    ),
);

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="max_score" id="max_score" value="' . $test_score_right . '" />' . K_NEWLINE;
echo
    '<input type="radio" name="default_score" id="default_score_correct" value="0" onchange="document.getElementById(\'form_ratingeditor\').testlog_score.value=\''
        . $test_score_right
        . '\'" title="'
        . $l['h_score_right']
        . '" /><label for="default_score_correct">'
        . $l['w_score_right']
        . ' ['
        . $test_score_right
        . ']</label>'
        . K_NEWLINE
;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

$fractional_scores = [
    '3/4' => round($test_score_right * 3 / 4, 3),
    '1/2' => round($test_score_right / 2, 3),
    '1/4' => round($test_score_right / 4, 3),
];
foreach ($fractional_scores as $fraction_label => $fraction_score) {
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo '<button type="button" class="minibutton quick-essay-score" data-fraction="'
        . $fraction_label . '" onclick="document.getElementById(\'testlog_score\').value=\''
        . $fraction_score . '\'" title="Установить ' . $fraction_label . ' максимального балла">'
        . $fraction_label . ' [' . $fraction_score . ']</button>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<input type="radio" name="default_score" id="default_score_wrong" value="0" onchange="document.getElementById(\'form_ratingeditor\').testlog_score.value=\''
        . $test_score_wrong
        . '\'" title="'
        . $l['h_score_wrong']
        . '" /><label for="default_score_wrong">'
        . $l['w_score_wrong']
        . ' ['
        . $test_score_wrong
        . ']</label>'
        . K_NEWLINE
;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<input type="radio" name="default_score" id="default_score_unanswered" value="0" onchange="document.getElementById(\'form_ratingeditor\').testlog_score.value=\''
        . $test_score_unanswered
        . '\'" title="'
        . $l['h_score_unanswered']
        . '" /><label for="default_score_unanswered">'
        . $l['w_score_unanswered']
        . ' ['
        . $test_score_unanswered
        . ']</label>'
        . K_NEWLINE
;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_edit_rating_string(
    get_form_row_text_box('testlog_comment', $l['w_comment'], $l['w_comment'], $testlog_comment),
);

echo '<div class="row">' . K_NEWLINE;

// show buttons by case
if ($testlog_id > 0) {
    F_submit_button('update', $l['w_update'], $l['h_update']);
}

echo '</div>' . K_NEWLINE;
echo f_tce_edit_rating_string(f_get_csrf_token_field()) . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_rating'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_edit_rating_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function f_tce_edit_rating_int(mixed $value): int
{
    return (int) $value;
}

function f_tce_edit_rating_float(int|float|string|null $value): float
{
    return (float) $value;
}

function f_tce_edit_rating_bool(mixed $value): bool
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

function f_tce_edit_rating_is_positive(mixed $value): bool
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

/** @return object|resource|bool */
function f_tce_edit_rating_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array{test_score_right:int|float|string,question_difficulty:int|float|string}|null */
function f_tce_edit_rating_score_row(mixed $row): ?array
{
    /** @var array{test_score_right:int|float|string,question_difficulty:int|float|string}|null $row */
    return $row;
}

/**
 * @return array{
 *     test_id:int|string,test_score_right:int|float|string,test_score_wrong:int|float|string,
 *     test_score_unanswered:int|float|string,testlog_id:int|string,testlog_score:int|float|string|null,
 *     testlog_answer_text:string,testlog_comment:string|null,question_description:string,
 *     question_difficulty:int|float|string,question_explanation:string
 * }|null
 */
function f_tce_edit_rating_detail_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /**
     * @var array{
     *     test_id:int|string,test_score_right:int|float|string,test_score_wrong:int|float|string,
     *     test_score_unanswered:int|float|string,testlog_id:int|string,testlog_score:int|float|string|null,
     *     testlog_answer_text:string,testlog_comment:string|null,question_description:string,
     *     question_difficulty:int|float|string,question_explanation:string
     * } $row
     */
    return $row;
}

/** @return array{test_id:int|string,test_begin_time:string,test_name:string}|null */
function f_tce_edit_rating_executed_test_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /** @var array{test_id:int|string,test_begin_time:string,test_name:string} $row */
    return $row;
}

/**
 * @return array{
 *     testlog_id:int|string,testlog_score:int|float|string|null,user_lastname:string,user_firstname:string,
 *     user_name:string,question_description:string
 * }|null
 */
function f_tce_edit_rating_answer_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /**
     * @var array{
     *     testlog_id:int|string,testlog_score:int|float|string|null,user_lastname:string,user_firstname:string,
     *     user_name:string,question_description:string
     * } $row
     */
    return $row;
}
