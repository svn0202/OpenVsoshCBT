<?php

//============================================================+
// File name   : tce_tsv_questions.php
// Begin       : 2006-03-06
// Last Update : 2023-11-30
//
// Description : Functions to export questions using CVS format.
//               (tab-separated values)
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display all questions grouped by topic in TSV format.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2012-12-31
 */

require_once '../config/tce_config.php';
/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';

/** @var int|string $expmode_request */
$expmode_request = $_REQUEST['expmode'] ?? 0;
/** @var int|string $module_id_request */
$module_id_request = $_REQUEST['module_id'] ?? 0;
/** @var int|string $subject_id_request */
$subject_id_request = $_REQUEST['subject_id'] ?? 0;
if (
    $expmode_request <= 0
    || $module_id_request <= 0
    || $subject_id_request <= 0
) {
    exit();
}

$expmode = (int) $expmode_request;
$module_id = (int) $module_id_request;
$subject_id = (int) $subject_id_request;

// check user's authorization for module
if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $module_id, 'module_user_id')) {
    exit();
}

// set TSV file name
$tsv_filename = match ($expmode) {
    1 => 'tcexam_subject_' . $subject_id . '_' . date('YmdHi') . '.tsv',
    2 => 'tcexam_module_' . $module_id . '_' . date('YmdHi') . '.tsv',
    3 => 'tcexam_all_modules_' . date('YmdHi') . '.tsv',
    default => 'tcexam_export_' . date('YmdHi') . '.tsv',
};

// send TSV headers
header('Content-Description: TSV File Transfer');
header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
// force download dialog
header('Content-Type: application/force-download');
header('Content-Type: application/octet-stream', false);
header('Content-Type: application/download', false);
header('Content-Type: text/tab-separated-values', false);
// use the Content-Disposition header to supply a recommended filename
header('Content-Disposition: attachment; filename=' . $tsv_filename . ';');
header('Content-Transfer-Encoding: binary');

echo f_tsv_export_questions($module_id, $subject_id, $expmode);

/**
 * Export all questions of the selected subject to TSV.
 * @param int $module_id module ID
 * @param int $subject_id topic ID
 * @param int $expmode export mode: 1 = selected topic; 2 = selected module; 3 = all modules.
 * @return string TSV data
 */
function f_tsv_export_questions(int $module_id, int $subject_id, int $expmode): string
{
    global $l, $db;
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_authorization.php';
    require_once '../../shared/code/tce_functions_auth_sql.php';
    $module_id = (int) $module_id;
    $subject_id = (int) $subject_id;
    $expmode = (int) $expmode;
    $tsv = ''; // TSV data to be returned

    // headers

    $tsv .= 'M=MODULE'; // MODULE
    $tsv .= K_TAB . 'module_enabled';
    $tsv .= K_TAB . 'module_name';
    $tsv .= K_NEWLINE;

    $tsv .= 'S=SUBJECT'; // SUBJECT
    $tsv .= K_TAB . 'subject_enabled';
    $tsv .= K_TAB . 'subject_name';
    $tsv .= K_TAB . 'subject_description';
    $tsv .= K_NEWLINE;

    $tsv .= 'Q=QUESTION'; // QUESTION
    $tsv .= K_TAB . 'question_enabled';
    $tsv .= K_TAB . 'question_description';
    $tsv .= K_TAB . 'question_explanation';
    $tsv .= K_TAB . 'question_type';
    $tsv .= K_TAB . 'question_difficulty';
    $tsv .= K_TAB . 'question_position';
    $tsv .= K_TAB . 'question_timer';
    $tsv .= K_TAB . 'question_fullscreen';
    $tsv .= K_TAB . 'question_inline_answers';
    $tsv .= K_TAB . 'question_auto_next';
    $tsv .= K_TAB . 'question_shuffle_answers';
    $tsv .= K_NEWLINE;

    $tsv .= 'A=ANSWER'; // ANSWER
    $tsv .= K_TAB . 'answer_enabled';
    $tsv .= K_TAB . 'answer_description';
    $tsv .= K_TAB . 'answer_explanation';
    $tsv .= K_TAB . 'answer_isright';
    $tsv .= K_TAB . 'answer_position';
    $tsv .= K_TAB . 'answer_keyboard_key';
    $tsv .= K_TAB . 'answer_weight';
    $tsv .= K_NEWLINE;

    $tsv .= K_NEWLINE;

    // ---- module
    $andmodwhere = '';
    if ($expmode < 3) {
        $andmodwhere = 'module_id=' . $module_id . '';
    }

    $sqlm = F_select_modules_sql($andmodwhere);
    $rm = f_tmf_tsv_questions_query_result(F_db_query($sqlm, $db));
    if ($rm) {
        while ($mm = f_tmf_tsv_questions_row(F_db_fetch_array($rm))) {
            /** @var array{module_id:int|string,module_enabled:mixed,module_name:string} $mm */
            $tsv .= 'M'; // MODULE
            $tsv .= K_TAB . (int) f_get_boolean($mm['module_enabled']);
            $tsv .= K_TAB . f_text_to_tsv($mm['module_name']);
            $tsv .= K_NEWLINE;
            // ---- topic
            $where_sqls = 'subject_module_id=' . $mm['module_id'] . '';
            if ($expmode < 2) {
                $where_sqls .= ' AND subject_id=' . $subject_id . '';
            }

            $sqls = F_select_subjects_sql($where_sqls);
            $rs = f_tmf_tsv_questions_query_result(F_db_query($sqls, $db));
            if ($rs) {
                while ($ms = f_tmf_tsv_questions_row(F_db_fetch_array($rs))) {
                    /** @var array{
                     *     subject_id:int|string,
                     *     subject_enabled:mixed,
                     *     subject_name:string,
                     *     subject_description:string
                     * } $ms
                     */
                    $tsv .= 'S'; // SUBJECT
                    $tsv .= K_TAB . (int) f_get_boolean($ms['subject_enabled']);
                    $tsv .= K_TAB . f_text_to_tsv($ms['subject_name']);
                    $tsv .= K_TAB . f_text_to_tsv($ms['subject_description']);
                    $tsv .= K_NEWLINE;
                    // ---- questions
                    $sql =
                        'SELECT *
						FROM '
                        . K_TABLE_QUESTIONS
                        . '
						WHERE question_subject_id='
                        . $ms['subject_id']
                        . '
						ORDER BY question_enabled DESC, question_position, question_description';
                    $r = f_tmf_tsv_questions_query_result(F_db_query($sql, $db));
                    if ($r) {
                        while ($m = f_tmf_tsv_questions_row(F_db_fetch_array($r))) {
                            /** @var array{
                             *     question_id:int|string,
                             *     question_enabled:mixed,
                             *     question_description:string,
                             *     question_explanation:string,
                             *     question_type:int<1,5>,
                             *     question_difficulty:int|string,
                             *     question_position:int|string,
                             *     question_timer:int|string,
                             *     question_fullscreen:mixed,
                             *     question_inline_answers:mixed,
                             *     question_auto_next:mixed,
                             *     question_shuffle_answers:mixed
                             * } $m
                             */
                            $tsv .= 'Q'; // QUESTION
                            $tsv .= K_TAB . (int) f_get_boolean($m['question_enabled']);
                            $tsv .= K_TAB . f_text_to_tsv($m['question_description']);
                            $tsv .= K_TAB . f_text_to_tsv($m['question_explanation']);
                            $question_type = match ((int) $m['question_type']) {
                                1 => 'S',
                                2 => 'M',
                                3 => 'T',
                                4 => 'O',
                                5 => 'C',
                                default => '',
                            };
                            $tsv .= K_TAB . $question_type;
                            $tsv .= K_TAB . $m['question_difficulty'];
                            $tsv .= K_TAB . $m['question_position'];
                            $tsv .= K_TAB . $m['question_timer'];
                            $tsv .= K_TAB . (int) f_get_boolean($m['question_fullscreen']);
                            $tsv .= K_TAB . (int) f_get_boolean($m['question_inline_answers']);
                            $tsv .= K_TAB . (int) f_get_boolean($m['question_auto_next']);
                            $tsv .= K_TAB . (int) f_get_boolean($m['question_shuffle_answers']);
                            $tsv .= K_NEWLINE;
                            // display alternative answers
                            $sqla =
                                'SELECT *
								FROM '
                                . K_TABLE_ANSWERS
                                . '
								WHERE answer_question_id=\''
                                . $m['question_id']
                                . '\'
								ORDER BY answer_position,answer_isright DESC';
                            $ra = f_tmf_tsv_questions_query_result(F_db_query($sqla, $db));
                            if ($ra) {
                                while ($ma = f_tmf_tsv_questions_row(F_db_fetch_array($ra))) {
                                    /** @var array{
                                     *     answer_enabled:mixed,
                                     *     answer_description:string,
                                     *     answer_explanation:string,
                                     *     answer_isright:mixed,
                                     *     answer_position:int|string,
                                     *     answer_keyboard_key:int|string,
                                     *     answer_weight:mixed
                                     * } $ma
                                     */
                                    $tsv .= 'A'; // ANSWER
                                    $tsv .= K_TAB . (int) f_get_boolean($ma['answer_enabled']);
                                    $tsv .= K_TAB . f_text_to_tsv($ma['answer_description']);
                                    $tsv .= K_TAB . f_text_to_tsv($ma['answer_explanation']);
                                    $tsv .= K_TAB . (int) f_get_boolean($ma['answer_isright']);
                                    $tsv .= K_TAB . $ma['answer_position'];
                                    $tsv .= K_TAB . $ma['answer_keyboard_key'];
                                    $tsv .= K_TAB . (string) $ma['answer_weight'];
                                    $tsv .= K_NEWLINE;
                                }
                            } else {
                                F_display_db_error();
                            }
                        } // end while for questions
                    } else {
                        F_display_db_error();
                    }
                } // end while for topics
            } else {
                F_display_db_error();
            }
        } // end while for module
    } else {
        F_display_db_error();
    }

    return $tsv;
}

/** @return array<array-key,mixed>|null */
function f_tmf_tsv_questions_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool */
function f_tmf_tsv_questions_query_result(mixed $result): mixed
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
