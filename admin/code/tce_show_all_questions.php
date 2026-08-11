<?php

//============================================================+
// File name   : tce_show_all_questions.php
// Begin       : 2005-07-06
// Last Update : 2024-12-13
//
// Description : Display all questions grouped by topic.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display all questions grouped by topic.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2005-07-06
 */

require_once '../config/tce_config.php';

$menu_action = f_tce_show_questions_string($_POST['menu_action'] ?? '');
$new_subject_id = (int) ($_POST['new_subject_id'] ?? 0);

// read request inputs (former register-globals emulation)
$subject_id = isset($_REQUEST['subject_id']) ? (int) $_REQUEST['subject_id'] : 0;
$subject_module_id = isset($_REQUEST['subject_module_id']) ? (int) $_REQUEST['subject_module_id'] : 0;
$hide_answers = (bool) ($_REQUEST['hide_answers'] ?? false);
$firstrow = isset($_REQUEST['firstrow']) ? (int) $_REQUEST['firstrow'] : 0;
$rowsperpage = isset($_REQUEST['rowsperpage']) ? (int) $_REQUEST['rowsperpage'] : (int) K_MAX_ROWS_PER_PAGE;
$orderdir = isset($_REQUEST['orderdir']) ? (int) $_REQUEST['orderdir'] : 0;

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     a_meta_charset:string,a_meta_dir:string,h_answer_keyboard_key:string,h_answer_right:string,h_answer_wrong:string,
 *     h_pdf:string,h_position:string,h_question_difficulty:string,h_question_timer:string,h_subject:string,
 *     h_tsv_export:string,h_update:string,h_xml_export:string,hp_select_all_questions:string,
 *     m_authorization_denied:string,m_databasempty:string,m_updated:string,m_with_selected:string,
 *     t_answers_editor:string,t_questions_editor:string,t_questions_list:string,w_all:string,w_auto_next:string,
 *     w_check_all:string,w_copy:string,w_delete:string,w_disable:string,w_disabled:string,w_edit:string,
 *     w_enable:string,w_enabled:string,w_explanation:string,w_free_answer:string,w_fullscreen:string,
 *     w_hide_answers:string,w_inline_answers:string,w_matching_answer:string,w_module:string,w_move:string,
 *     w_multiple_answers:string,w_ordering_answer:string,w_questions:string,w_select:string,w_single_answer:string,
 *     w_subject:string,w_uncheck_all:string,w_update:string
 * } $l
 */
/** @var mixed $db */
/** @var string $menu_mode */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;

$thispage_title = $l['t_questions_list'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_questions.php';

// --- Initialize variables

// set default values
$wherequery = '';
$order_field = 'question_enabled DESC, question_position,';
if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
    $order_field .= ' CAST(question_description as varchar2(100))';
} else {
    $order_field .= ' question_description';
}

if (isset($_POST['selectmodule'])) {
    $changemodule = 1;
}

if (isset($_POST['selectcategory'])) {
    $changecategory = 1;
}

if (isset($changemodule) || isset($changecategory)) {
    $wherequery = '';
    $firstrow = 0;
    $orderdir = 0;
    $order_field = 'question_enabled DESC, question_position,';
    if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
        $order_field .= ' CAST(question_description as varchar2(100))';
    } else {
        $order_field .= ' question_description';
    }
}

if ($subject_module_id === 0) {
    // select default module/subject (if not specified)
    $sql = f_tce_show_questions_string(F_select_modules_sql()) . ' LIMIT 1';
    $r = f_tce_show_questions_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tce_show_questions_module_row(F_db_fetch_array($r));
        $subject_module_id = $m === null ? 0 : (int) $m['module_id'];
    } else {
        F_display_db_error();
    }
}

// check user's authorization
if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $subject_module_id, 'module_user_id')) {
    F_print_error('ERROR', $l['m_authorization_denied']);
    require_once '../code/tce_page_footer.php';
    exit();
}

// select subject
if (isset($changemodule) || $subject_id <= 0) {
    $sql = f_tce_show_questions_string(F_select_subjects_sql('subject_module_id=' . $subject_module_id)) . ' LIMIT 1';
    $r = f_tce_show_questions_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tce_show_questions_subject_row(F_db_fetch_array($r));
        $subject_id = $m === null ? 0 : (int) $m['subject_id'];
    } else {
        F_display_db_error();
    }
}

if (f_legacy_literal_equals($menu_mode, 'update') && $menu_action !== '') {
    $istart = 1 + $firstrow;
    $iend = $rowsperpage + $firstrow;
    for ($i = $istart; $i <= $iend; ++$i) {
        // for each selected question
        $keyname = 'questionid' . $i;
        if (isset($_POST[$keyname])) {
            $question_id = (int) $_POST[$keyname];
            switch ($menu_action) {
                case 'move':
                        if ($new_subject_id > 0) {
                            f_question_copy($question_id, $new_subject_id);
                            f_question_delete($question_id, $subject_id);
                        }

                        break;
                case 'copy':
                        if ($new_subject_id > 0) {
                            f_question_copy($question_id, $new_subject_id);
                        }

                        break;
                case 'delete':
                        f_question_delete($question_id, $subject_id);
                        break;
                case 'disable':
                        f_question_set_enabled($question_id, false);
                        break;
                case 'enable':
                        f_question_set_enabled($question_id, true);
                        break;
            } // end of switch
        }
    }

    F_print_error('MESSAGE', $l['m_updated']);
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_selectquestions">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="subject_module_id">' . $l['w_module'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="changemodule" id="changemodule" value="" />' . K_NEWLINE;
echo
    '<select name="subject_module_id" id="subject_module_id" onchange="document.getElementById(\'form_selectquestions\').changemodule.value=1;document.getElementById(\'form_selectquestions\').changecategory.value=1; document.getElementById(\'form_selectquestions\').submit();" title="'
        . $l['w_module']
        . '">'
        . K_NEWLINE
;
$sql = f_tce_show_questions_string(F_select_modules_sql());
$r = f_tce_show_questions_query_result(F_db_query($sql, $db));
if ($r) {
    $countitem = 1;
    $m = f_tce_show_questions_module_row(F_db_fetch_array($r));
    if ($m === null) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
    while ($m !== null) {
        echo '<option value="' . $m['module_id'] . '"';
        if (f_legacy_int_equals($m['module_id'], (int) $subject_module_id)) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ';
        if (f_get_boolean($m['module_enabled'])) {
            echo '+';
        } else {
            echo '-';
        }

        echo
            ' '
                . htmlspecialchars($m['module_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                . '&nbsp;</option>'
                . K_NEWLINE
        ;
        ++$countitem;
        $m = f_tce_show_questions_module_row(F_db_fetch_array($r));
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_show_questions_string(get_form_noscript_select('selectmodule'));

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="subject_id">' . $l['w_subject'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="changecategory" id="changecategory" value="" />' . K_NEWLINE;
echo
    '<select name="subject_id" id="subject_id" onchange="document.getElementById(\'form_selectquestions\').changecategory.value=1;document.getElementById(\'form_selectquestions\').submit()" title="'
        . $l['h_subject']
        . '">'
        . K_NEWLINE
;
$sql = f_tce_show_questions_string(F_select_subjects_sql('subject_module_id=' . $subject_module_id));
$r = f_tce_show_questions_query_result(F_db_query($sql, $db));
if ($r) {
    $countitem = 1;
    while (($m = f_tce_show_questions_subject_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['subject_id'] . '"';
        if (f_legacy_int_equals($m['subject_id'], (int) $subject_id)) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ';
        if (f_get_boolean($m['subject_enabled'])) {
            echo '+';
        } else {
            echo '-';
        }

        echo ' ' . htmlspecialchars($m['subject_name'], ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
        ++$countitem;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_show_questions_string(get_form_noscript_select('selectcategory'));

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="checkbox" name="hide_answers" id="hide_answers" value="1"';
if ($hide_answers) {
    echo ' checked="checked"';
}

echo ' title="' . $l['w_hide_answers'] . '" onchange="document.getElementById(\'form_selectquestions\').submit()" />';
echo '<label for="hide_answers">' . $l['w_hide_answers'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo f_tce_show_questions_string(get_form_noscript_select('selectrecord'));

echo '<div class="row"><hr /></div>' . K_NEWLINE;

// display questions statistics
$qtype = [
    '<abbr class="offbox" title="' . $l['w_single_answer'] . '">S</abbr>',
    '<abbr class="offbox" title="' . $l['w_multiple_answers'] . '">M</abbr>',
    '<abbr class="offbox" title="' . $l['w_free_answer'] . '">T</abbr>',
    '<abbr class="offbox" title="' . $l['w_ordering_answer'] . '">O</abbr>',
    '<abbr class="offbox" title="' . $l['w_matching_answer'] . '">C</abbr>',
]; // question types
$qstat = '';
$nqsum = 0;
$sql = 'SELECT question_type, COUNT(*) as numquestions
	FROM ' . K_TABLE_QUESTIONS . '
	WHERE question_subject_id=' . $subject_id . '
	GROUP BY question_type';
$r = f_tce_show_questions_query_result(F_db_query($sql, $db));
if ($r) {
    while (($m = f_tce_show_questions_stat_row(F_db_fetch_array($r))) !== null) {
        $numquestions = (int) $m['numquestions'];
        $question_type_index = (int) $m['question_type'] - 1;
        $nqsum += $numquestions;
        $qstat .= ' + ' . $numquestions . ' ' . ($qtype[$question_type_index] ?? '') . '';
    }
} else {
    F_display_db_error();
}

echo '<div class="rowl">';
echo '<span>' . $l['w_questions'] . ': ' . $nqsum . ' = ' . $qstat . '</span><br />' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo '<div class="rowl">' . K_NEWLINE;

if ($subject_id > 0) {
    F_show_select_questions(
        $wherequery,
        $subject_module_id,
        $subject_id,
        $order_field,
        $orderdir,
        $firstrow,
        $rowsperpage,
        $hide_answers,
    );
}

echo '&nbsp;' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;

// show buttons by case
if ($subject_id > 0) {
    $pdflink = 'tce_pdf_all_questions.php';
    $pdflink .= '?module_id=' . $subject_module_id;
    $pdflink .= '&amp;subject_id=' . $subject_id;
    $pdflink .= '&amp;hide_answers=' . (int) $hide_answers; // hide answers option
    echo '<a href="' . $pdflink . '&amp;expmode=1" class="xmlbutton" title="' . $l['h_pdf'] . '">PDF</a>';
    echo
        '<a href="'
            . $pdflink
            . '&amp;expmode=2" class="xmlbutton" title="'
            . $l['h_pdf']
            . '">PDF '
            . $l['w_module']
            . '</a>'
    ;
    echo
        '<a href="'
            . $pdflink
            . '&amp;expmode=3" class="xmlbutton" title="'
            . $l['h_pdf']
            . '">PDF '
            . $l['w_all']
            . '</a>'
    ;
    $xmllink = 'tce_xml_questions.php';
    $xmllink .= '?module_id=' . $subject_module_id;
    $xmllink .= '&amp;subject_id=' . $subject_id;
    echo ' <a href="' . $xmllink . '&amp;expmode=1" class="xmlbutton" title="' . $l['h_xml_export'] . '">XML</a>';
    echo
        '<a href="'
            . $xmllink
            . '&amp;expmode=2" class="xmlbutton" title="'
            . $l['h_xml_export']
            . '">XML '
            . $l['w_module']
            . '</a>'
    ;
    echo
        '<a href="'
            . $xmllink
            . '&amp;expmode=3" class="xmlbutton" title="'
            . $l['h_xml_export']
            . '">XML '
            . $l['w_all']
            . '</a>'
    ;
    echo ' <a href="' . $xmllink . '&amp;expmode=1&amp;format=JSON" class="xmlbutton" title="JSON">JSON</a>';
    echo
        '<a href="'
            . $xmllink
            . '&amp;expmode=2&amp;format=JSON" class="xmlbutton" title="JSON">JSON '
            . $l['w_module']
            . '</a>'
    ;
    echo
        '<a href="'
            . $xmllink
            . '&amp;expmode=3&amp;format=JSON" class="xmlbutton" title="JSON">JSON '
            . $l['w_all']
            . '</a>'
    ;
    $tsvlink = 'tce_tsv_questions.php';
    $tsvlink .= '?module_id=' . $subject_module_id;
    $tsvlink .= '&amp;subject_id=' . $subject_id;
    echo ' <a href="' . $tsvlink . '&amp;expmode=1" class="xmlbutton" title="' . $l['h_tsv_export'] . '">TSV</a>';
    echo
        '<a href="'
            . $tsvlink
            . '&amp;expmode=2" class="xmlbutton" title="'
            . $l['h_tsv_export']
            . '">TSV '
            . $l['w_module']
            . '</a>'
    ;
    echo
        '<a href="'
            . $tsvlink
            . '&amp;expmode=3" class="xmlbutton" title="'
            . $l['h_tsv_export']
            . '">TSV '
            . $l['w_all']
            . '</a>'
    ;
}

echo '&nbsp;' . K_NEWLINE;
echo '<input type="hidden" name="firstrow" id="firstrow" value="' . $firstrow . '" />' . K_NEWLINE;
echo '<input type="hidden" name="order_field" id="order_field" value="' . $order_field . '" />' . K_NEWLINE;
echo '<input type="hidden" name="orderdir" id="orderdir" value="' . $orderdir . '" />' . K_NEWLINE;
echo '<input type="hidden" name="submitted" id="submitted" value="0" />' . K_NEWLINE;
echo '<input type="hidden" name="usersearch" id="usersearch" value="" />' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo f_tce_show_questions_string(f_get_csrf_token_field()) . K_NEWLINE;
echo '</form>' . K_NEWLINE;

echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_select_all_questions'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

// ------------------------------

/**
 * Display a list of selected questions.
 * @author Nicola Asuni
 * @since 2005-07-06
 * @param $wherequery (string) question selection query
 * @param $subject_module_id (string) module ID
 * @param $subject_id (string) topic ID
 * @param $order_field (string) order by column name
 * @param $orderdir (int) oreder direction
 * @param $firstrow (int) number of first row to display
 * @param $rowsperpage (int) number of rows per page
 * @param $hide_answers (boolean) if true hide answers
 * @return bool false in case of empty database, true otherwise
 */
function f_show_select_questions(
    string $wherequery,
    int $subject_module_id,
    int $subject_id,
    string $order_field,
    int $orderdir,
    int $firstrow,
    int $rowsperpage,
    bool $hide_answers = false,
): bool {
    global $l, $db;
    /**
     * @var array{
     *     a_meta_charset:string,a_meta_dir:string,h_answer_keyboard_key:string,h_answer_right:string,
     *     h_answer_wrong:string,h_position:string,h_question_difficulty:string,h_question_timer:string,
     *     h_subject:string,h_update:string,m_databasempty:string,m_with_selected:string,t_answers_editor:string,
     *     t_questions_editor:string,w_auto_next:string,w_check_all:string,w_copy:string,w_delete:string,
     *     w_disable:string,w_disabled:string,w_edit:string,w_enable:string,w_enabled:string,w_explanation:string,
     *     w_free_answer:string,w_fullscreen:string,w_inline_answers:string,w_matching_answer:string,w_move:string,
     *     w_multiple_answers:string,w_ordering_answer:string,w_select:string,w_single_answer:string,w_subject:string,
     *     w_uncheck_all:string,w_update:string
     * } $l
     */
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_page.php';

    $subject_module_id = (int) $subject_module_id;
    $subject_id = (int) $subject_id;
    $orderdir = (int) $orderdir;
    $firstrow = (int) $firstrow;
    $rowsperpage = (int) $rowsperpage;
    if (
        empty($order_field)
        || !in_array($order_field, [
            'question_id',
            'question_subject_id',
            'question_description',
            'question_explanation',
            'question_type',
            'question_difficulty',
            'question_enabled',
            'question_position',
            'question_timer',
            'question_fullscreen',
            'question_inline_answers',
            'question_auto_next',
            'question_enabled DESC, question_position, CAST(question_description as varchar2(100))',
            'question_enabled DESC, question_position, question_description',
        ], true)
    ) {
        $order_field = 'question_description';
    }

    if ($orderdir === 0) {
        $nextorderdir = 1;
        $full_order_field = $order_field;
    } else {
        $nextorderdir = 0;
        $full_order_field = $order_field . ' DESC';
    }

    if (!F_count_rows(K_TABLE_QUESTIONS)) { //if the table is void (no items) display message
        F_print_error('MESSAGE', $l['m_databasempty']);
        return false;
    }

    if (empty($wherequery)) {
        $wherequery = 'WHERE question_subject_id=' . $subject_id . '';
    } else {
        $wherequery = F_escape_sql($db, $wherequery);
        $wherequery .= ' AND question_subject_id=' . $subject_id . '';
    }

    $sql = 'SELECT *
		FROM ' . K_TABLE_QUESTIONS . '
		' . $wherequery . '
		ORDER BY ' . $full_order_field;
    if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
        $sql =
            'SELECT * FROM ('
            . $sql
            . ') WHERE rownum BETWEEN '
            . $firstrow
            . ' AND '
            . ($firstrow + $rowsperpage)
            . '';
    } else {
        $sql .= ' LIMIT ' . $rowsperpage . ' OFFSET ' . $firstrow . '';
    }

    $r = f_tce_show_questions_query_result(F_db_query($sql, $db));
    if ($r) {
        $questlist = '';
        $itemcount = $firstrow;
        while (($m = f_tce_show_questions_question_row(F_db_fetch_array($r))) !== null) {
            ++$itemcount;
            $question_enabled = f_get_boolean($m['question_enabled']);
            $questlist .= '<li class="question-card'
                . ($question_enabled ? '' : ' is-disabled')
                . '" id="qid_'
                . (int) $m['question_id']
                . '">'
                . K_NEWLINE;
            $questlist .= '<div class="question-card__meta">' . K_NEWLINE;
            $questlist .=
                '<input class="question-card__select" type="checkbox" name="questionid'
                . $itemcount
                . '" id="questionid'
                . $itemcount
                . '" value="'
                . $m['question_id']
                . '" title="'
                . $l['w_select']
                . '" aria-label="'
                . $l['w_select']
                . '"';
            if (isset($_REQUEST['checkall']) && f_legacy_int_equals($_REQUEST['checkall'], 1)) {
                $questlist .= ' checked="checked"';
            }

            $questlist .= ' />';
            $questlist .= '<strong class="question-card__number"># ' . $itemcount . '</strong> ';
            // display question description
            if ($question_enabled) {
                $questlist .= '<abbr class="onbox" title="' . $l['w_enabled'] . '">+</abbr>';
            } else {
                $questlist .= '<abbr class="offbox" title="' . $l['w_disabled'] . '">-</abbr>';
            }

            $question_type_label = '';
            switch ((int) $m['question_type']) {
                case 1:
                        $question_type_label = $l['w_single_answer'];
                        break;
                case 2:
                        $question_type_label = $l['w_multiple_answers'];
                        break;
                case 3:
                        $question_type_label = $l['w_free_answer'];
                        break;
                case 4:
                        $question_type_label = $l['w_ordering_answer'];
                        break;
                case 5:
                        $question_type_label = $l['w_matching_answer'];
                        break;
            }

            if ($question_type_label !== '') {
                $questlist .=
                    '<span class="question-card__type">'
                    . f_text_to_xml($question_type_label)
                    . '</span>';
            }

            $questlist .= '<div class="question-card__flags">';
            $questlist .=
                ' <abbr class="question-card__difficulty" title="'
                . $l['h_question_difficulty']
                . '">'
                . $m['question_difficulty']
                . '</abbr>';
            if ((int) $m['question_position'] > 0) {
                $questlist .=
                    ' <abbr class="onbox" title="'
                    . $l['h_position']
                    . '">'
                    . (int) $m['question_position']
                    . '</abbr>';
            }

            if (f_get_boolean($m['question_fullscreen'])) {
                $questlist .=
                    ' <abbr class="onbox" title="' . $l['w_fullscreen'] . ': ' . $l['w_enabled'] . '">F</abbr>';
            }

            if (f_get_boolean($m['question_inline_answers'])) {
                $questlist .=
                    ' <abbr class="onbox" title="' . $l['w_inline_answers'] . ': ' . $l['w_enabled'] . '">I</abbr>';
            }

            if (f_get_boolean($m['question_auto_next'])) {
                $questlist .=
                    ' <abbr class="onbox" title="' . $l['w_auto_next'] . ': ' . $l['w_enabled'] . '">A</abbr>';
            }

            if ((int) $m['question_timer'] > 0) {
                $questlist .=
                    ' <abbr class="onbox" title="'
                    . $l['h_question_timer']
                    . '">'
                    . (int) $m['question_timer']
                    . '</abbr>';
            }
            $questlist .= '</div>';

            $questlist .=
                ' <a href="tce_edit_question.php?subject_module_id='
                . $subject_module_id
                . '&amp;question_subject_id='
                . $subject_id
                . '&amp;question_id='
                . $m['question_id']
                . '&amp;firstrow='
                . $firstrow
                . '" title="'
                . $l['t_questions_editor']
                . ' [ID = '
                . $m['question_id']
                . ']" class="xmlbutton">'
                . $l['w_edit']
                . '</a>';

            $questlist .= '</div>' . K_NEWLINE;
            $questlist .= '<div class="question-card__body">' . K_NEWLINE;
            $questlist .=
                '<div class="question-card__description">'
                . f_tce_show_questions_string(F_decode_tcecode($m['question_description']))
                . '</div>'
                . K_NEWLINE;
            if (f_tce_show_questions_bool(K_ENABLE_QUESTION_EXPLANATION) && !empty($m['question_explanation'])) {
                $questlist .=
                    '<div class="paddingleft"><br /><span class="explanation">'
                    . $l['w_explanation']
                    . ':</span><br />'
                    . f_tce_show_questions_string(F_decode_tcecode($m['question_explanation']))
                    . '</div>'
                    . K_NEWLINE;
            }

            if (!$hide_answers) {
                // display alternative answers
                $sqla =
                    'SELECT *
					FROM '
                    . K_TABLE_ANSWERS
                    . '
					WHERE answer_question_id=\''
                    . $m['question_id']
                    . '\'
					ORDER BY answer_enabled DESC,answer_position,answer_isright DESC';
                $ra = f_tce_show_questions_query_result(F_db_query($sqla, $db));
                if ($ra) {
                    $answlist = '';
                    $answer_index = 0;
                    while (($ma = f_tce_show_questions_answer_row(F_db_fetch_array($ra))) !== null) {
                        ++$answer_index;
                        $answer_enabled = f_get_boolean($ma['answer_enabled']);
                        $answer_correct = !in_array((int) $m['question_type'], [4, 5], true)
                            && f_get_boolean($ma['answer_isright']);
                        $answer_label = $answer_index <= 26 ? chr(64 + $answer_index) : (string) $answer_index;
                        $answlist .= '<li class="answer-card'
                            . ($answer_correct ? ' is-correct' : '')
                            . ($answer_enabled ? '' : ' is-disabled')
                            . '">';
                        $answlist .=
                            '<div class="answer-card__label"><strong>'
                            . $answer_label
                            . '</strong><small title="'
                            . $l['h_position']
                            . '">'
                            . ($ma['answer_position'] > 0 ? (int) $ma['answer_position'] : $answer_index)
                            . '</small></div>';
                        $answlist .=
                            '<div class="answer-content">'
                            . f_tce_show_questions_string(F_decode_tcecode($ma['answer_description']))
                            . '</div>'
                            . K_NEWLINE;
                        $answlist .= '<div class="answer-card__actions">';
                        if (!in_array((int) $m['question_type'], [4, 5], true)) {
                            $answlist .=
                                '<abbr class="answer-card__correctness" title="'
                                . ($answer_correct ? $l['h_answer_right'] : $l['h_answer_wrong'])
                                . '">'
                                . ($answer_correct ? '&#10003;' : '&#8212;')
                                . '</abbr>';
                        }

                        if ((int) $ma['answer_keyboard_key'] > 0) {
                            $answlist .=
                                '<abbr class="answer-card__key" title="'
                                . $l['h_answer_keyboard_key']
                                . '">'
                                . f_tce_show_questions_string(f_text_to_xml(chr((int) $ma['answer_keyboard_key'])))
                                . '</abbr>';
                        }

                        $answlist .=
                            '<a href="tce_edit_answer.php?subject_module_id='
                            . $subject_module_id
                            . '&amp;question_subject_id='
                            . $subject_id
                            . '&amp;answer_question_id='
                            . $m['question_id']
                            . '&amp;answer_id='
                            . $ma['answer_id']
                            . '&amp;firstrow='
                            . $firstrow
                            . '" title="'
                            . $l['t_answers_editor']
                            . ' [ID = '
                            . $ma['answer_id']
                            . ']" aria-label="'
                            . $l['w_edit']
                            . '" class="xmlbutton answer-card__edit">&#9998;</a>';
                        $answlist .= '</div>';
                        if (f_tce_show_questions_bool(K_ENABLE_ANSWER_EXPLANATION) && !empty($ma['answer_explanation'])) {
                            $answlist .=
                                '<div class="answer-card__explanation"><span class="explanation">'
                                . $l['w_explanation']
                                . ':</span><br />'
                                . f_tce_show_questions_string(F_decode_tcecode($ma['answer_explanation']))
                                . '</div>'
                                . K_NEWLINE;
                        }

                        $answlist .= '</li>' . K_NEWLINE;
                    }

                    if (strlen($answlist) > 0) {
                        $questlist .= "<ol class=\"answer admin-answer-list\">\n" . $answlist . "</ol>\n";
                    }
                } else {
                    F_display_db_error();
                }
            }

            // end if hide_answers
            $questlist .= '</div></li>' . K_NEWLINE;
        }

        if (strlen($questlist) > 0) {
            // display the list
            echo '<ul class="question admin-question-list">' . K_NEWLINE;
            echo $questlist;
            echo '</ul>' . K_NEWLINE;
            echo '<div class="row"><hr /></div>' . K_NEWLINE;
            // check/uncheck all options
            echo '<span dir="' . $l['a_meta_dir'] . '">';
            echo
                '<input type="radio" name="checkall" id="checkall1" value="1" onchange="document.getElementById(\'form_selectquestions\').submit()" />'
            ;
            echo '<label for="checkall1">' . $l['w_check_all'] . '</label> ';
            echo
                '<input type="radio" name="checkall" id="checkall0" value="0" onchange="document.getElementById(\'form_selectquestions\').submit()" />'
            ;
            echo '<label for="checkall0">' . $l['w_uncheck_all'] . '</label>';
            echo '</span>' . K_NEWLINE;
            echo '&nbsp;';
            $arr = (($l['a_meta_dir'] <=> 'rtl') === 0) ? '&larr;' : '&rarr;';
            /**
             * @var array{
             *     m_with_selected: string,
             *     w_enable: string,
             *     w_disable: string,
             *     w_delete: string,
             *     w_copy: string,
             *     w_move: string,
             *     h_subject: string,
             *     w_subject: string,
             *     a_meta_charset: string,
             *     w_update: string,
             *     h_update: string
             * } $l
             */

            // action options
            echo '<select name="menu_action" id="menu_action">' . K_NEWLINE;
            echo '<option value="0" style="color:gray">' . $l['m_with_selected'] . '</option>' . K_NEWLINE;
            echo '<option value="enable">' . $l['w_enable'] . '</option>' . K_NEWLINE;
            echo '<option value="disable">' . $l['w_disable'] . '</option>' . K_NEWLINE;
            echo '<option value="delete">' . $l['w_delete'] . '</option>' . K_NEWLINE;
            echo '<option value="copy">' . $l['w_copy'] . ' ' . $arr . '</option>' . K_NEWLINE;
            echo '<option value="move">' . $l['w_move'] . ' ' . $arr . '</option>' . K_NEWLINE;
            echo '</select>' . K_NEWLINE;
            // select new topic (for copy or move action)
            echo
                '<select name="new_subject_id" id="new_subject_id" title="'
                    . $l['h_subject']
                    . '" aria-label="'
                    . $l['h_subject']
                    . '">'
                    . K_NEWLINE
            ;
            $sql = f_tce_show_questions_string(
                F_select_module_subjects_sql("module_enabled='1' AND subject_enabled='1'"),
            );
            $r = f_tce_show_questions_query_result(F_db_query($sql, $db));
            if ($r) {
                echo '<option value="0" style="color:gray">' . $l['w_subject'] . '</option>' . K_NEWLINE;
                $prev_module_id = 0;
                while (($m = f_tce_show_questions_module_subject_row(F_db_fetch_array($r))) !== null) {
                    if (!f_legacy_int_equals($m['module_id'], (int) $prev_module_id)) {
                        $prev_module_id = $m['module_id'];
                        echo
                            '<option value="0" style="color:gray;font-weight:bold;" disabled="disabled">* '
                                . htmlspecialchars($m['module_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                                . '</option>'
                                . K_NEWLINE
                        ;
                    }

                    echo
                        '<option value="'
                            . $m['subject_id']
                            . '">&nbsp;&nbsp;&nbsp;&nbsp;'
                            . htmlspecialchars($m['subject_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                            . '</option>'
                            . K_NEWLINE
                    ;
                }
            } else {
                echo '</select>' . K_NEWLINE;
                F_display_db_error();
            }

            echo '</select>' . K_NEWLINE;
            // submit button
            F_submit_button('update', $l['w_update'], $l['h_update']);
        }

        // ---------------------------------------------------------------
        // -- page jumper (menu for successive pages)
        if ($rowsperpage > 0) {
            $sql = 'SELECT count(*) AS total FROM ' . K_TABLE_QUESTIONS . ' ' . $wherequery . '';
            $param_array = '';
            $param_array = '&amp;order_field=' . urlencode($order_field) . '';

            if ($orderdir !== 0) {
                $param_array .= '&amp;orderdir=' . $orderdir . '';
            }

            if (!empty($hide_answers)) {
                $param_array .= '&amp;hide_answers=' . (int) $hide_answers . '';
            }

            $param_array .= '&amp;subject_module_id=' . $subject_module_id . '';
            $param_array .= '&amp;subject_id=' . $subject_id . '';
            $param_array .= '&amp;submitted=1';
            $script_name = f_tce_show_questions_string($_SERVER['SCRIPT_NAME']);
            F_show_page_navigator($script_name, $sql, $firstrow, $rowsperpage, $param_array);
        }
    } else {
        F_display_db_error();
    }

    return true;
}

function f_tce_show_questions_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function f_tce_show_questions_bool(mixed $value): bool
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

/** @return object|resource|bool */
function f_tce_show_questions_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array{module_id:int|string,module_name:string,module_enabled:int|string|bool}|null */
function f_tce_show_questions_module_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /** @var array{module_id:int|string,module_name:string,module_enabled:int|string|bool} $row */
    return $row;
}

/** @return array{subject_id:int|string,subject_name:string,subject_enabled:int|string|bool}|null */
function f_tce_show_questions_subject_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /** @var array{subject_id:int|string,subject_name:string,subject_enabled:int|string|bool} $row */
    return $row;
}

/** @return array{question_type:int|string,numquestions:int|string}|null */
function f_tce_show_questions_stat_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /** @var array{question_type:int|string,numquestions:int|string} $row */
    return $row;
}

/**
 * @return array{
 *     question_id:int|string,
 *     question_enabled:int|string|bool,
 *     question_type:int|string,
 *     question_description:string,
 *     question_explanation:string,
 *     question_difficulty:int|float|string,
 *     question_position:int|string,
 *     question_fullscreen:int|string|bool,
 *     question_inline_answers:int|string|bool,
 *     question_auto_next:int|string|bool,
 *     question_timer:int|string
 * }|null
 */
function f_tce_show_questions_question_row(mixed $row): ?array
{
    /**
     * @var array{
     *     question_id:int|string,
     *     question_enabled:int|string|bool,
     *     question_type:int|string,
     *     question_description:string,
     *     question_explanation:string,
     *     question_difficulty:int|float|string,
     *     question_position:int|string,
     *     question_fullscreen:int|string|bool,
     *     question_inline_answers:int|string|bool,
     *     question_auto_next:int|string|bool,
     *     question_timer:int|string
     * }|null $row
     */
    return $row;
}

/**
 * @return array{
 *     answer_id:int|string,
 *     answer_enabled:int|string|bool,
 *     answer_isright:int|string|bool,
 *     answer_position:int|string,
 *     answer_keyboard_key:int|string,
 *     answer_description:string,
 *     answer_explanation:string
 * }|null
 */
function f_tce_show_questions_answer_row(mixed $row): ?array
{
    /**
     * @var array{
     *     answer_id:int|string,
     *     answer_enabled:int|string|bool,
     *     answer_isright:int|string|bool,
     *     answer_position:int|string,
     *     answer_keyboard_key:int|string,
     *     answer_description:string,
     *     answer_explanation:string
     * }|null $row
     */
    return $row;
}

/** @return array{module_id:int|string,module_name:string,subject_id:int|string,subject_name:string}|null */
function f_tce_show_questions_module_subject_row(mixed $row): ?array
{
    /** @var array{module_id:int|string,module_name:string,subject_id:int|string,subject_name:string}|null $row */
    return $row;
}
