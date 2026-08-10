<?php

//============================================================+
// File name   : tce_pdf_all_questions.php
// Begin       : 2004-06-10
// Last Update : 2026-06-22
//
// Description : Creates a PDF document containing exported questions.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Creates a PDF document containing exported questions.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2005-07-06
 * @param $_REQUEST['subject_id'] (int) topic ID
 */

// Use the generated tc-lib-pdf fonts for this document (set before the config defines the legacy default).
require_once __DIR__ . '/../../vendor/autoload.php';
define('K_PATH_FONTS', realpath(__DIR__ . '/../../vendor/tecnickcom/tc-lib-pdf-font/target/fonts'));

require_once '../config/tce_config.php';
/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/config/tce_pdf.php';
require_once '../../shared/code/tce_pdf_report.php';

/**
 * @var array{
 *     t_questions_list:string,
 *     hp_select_all_questions:string,
 *     a_meta_dir:string,
 *     w_explanation:string
 * } $l
 */
/** @var mixed $db */
/** @var array{expmode?:int|string,module_id?:int|string,subject_id?:int|string,hide_answers?:mixed} $request */
$request = $_REQUEST;

if (
    !isset($request['expmode'])
    || $request['expmode'] <= 0
    || !isset($request['module_id'])
    || $request['module_id'] <= 0
    || (!isset($request['subject_id']) || $request['subject_id'] <= 0)
) {
    exit();
}

$expmode = (int) $request['expmode'];
$module_id = (int) $request['module_id'];
$subject_id = (int) $request['subject_id'];

// check user's authorization for module
if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $module_id, 'module_user_id')) {
    exit();
}

$show_answers = true;
if (isset($request['hide_answers']) && f_legacy_int_equals($request['hide_answers'], 1)) {
    $show_answers = false;
}

$doc_title = unhtmlentities($l['t_questions_list']);
$doc_description = f_compact_string(unhtmlentities($l['hp_select_all_questions']));

$question_type_text = static fn (int $value): string => match ($value) {
    1 => 'S',
    2 => 'M',
    3 => 'T',
    4 => 'O',
    5 => 'C',
    default => '',
};
$right_answer_mark = static fn (mixed $value): string => match ((int) f_get_boolean($value)) {
    0 => ' ',
    1 => '*',
};
$normalize_query_result = static function (mixed $result): mixed {
    if (
        is_bool($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
};
/** @return array<array-key,mixed>|null */
$normalize_row = static fn (mixed $row): ?array => is_array($row) ? $row : null;
/** @var bool $question_explanation_enabled */
$question_explanation_enabled = K_ENABLE_QUESTION_EXPLANATION;
/** @var bool $answer_explanation_enabled */
$answer_explanation_enabled = K_ENABLE_ANSWER_EXPLANATION;

// --- create the PDF document (tc-lib-pdf) ---

$pdf = new TcePdfReport();

// header back-link QR-Code
switch ($expmode) {
    case 1:
        $pdf->setTCExamBackLink(
            K_PATH_URL
            . 'admin/code/tce_show_all_questions.php?subject_module_id='
            . $module_id
            . '&subject_id='
            . $subject_id,
        );
        break;
    case 2:
        $pdf->setTCExamBackLink(K_PATH_URL . 'admin/code/tce_show_all_questions.php?subject_module_id=' . $module_id);
        break;
    case 3:
        $pdf->setTCExamBackLink(K_PATH_URL . 'admin/code/tce_show_all_questions.php');
        break;
}

// document metadata
$pdf->setCreator('TCExam ver.' . (string) K_TCEXAM_VERSION);
$pdf->setAuthor(PDF_AUTHOR);
$pdf->setTitle($doc_title);
$pdf->setSubject($doc_description);
$pdf->setKeywords('TCExam, ' . $doc_title);
$pdf->setLanguageArray($l);

// page header content (title, description, logo)
$pdf->setReportHeader(PDF_HEADER_TITLE, PDF_HEADER_STRING, PDF_HEADER_LOGO, (float) PDF_HEADER_LOGO_WIDTH);

$rtl = $l['a_meta_dir'] === 'rtl';

// ---- module
$andmodwhere = '';
if ($expmode < 3) {
    $andmodwhere = 'module_id=' . $module_id;
}

$sqlm = F_select_modules_sql($andmodwhere);
if ($rm = $normalize_query_result(F_db_query($sqlm, $db))) {
    while ($mm = $normalize_row(F_db_fetch_array($rm))) {
        /** @var array{module_id:int,module_name:string} $mm */
        $module_id = $mm['module_id'];
        $module_name = $mm['module_name'];

        // ---- topic
        $where_sqls = 'subject_module_id=' . $module_id;
        if ($expmode < 2) {
            $where_sqls .= ' AND subject_id=' . $subject_id;
        }

        $sqls = F_select_subjects_sql($where_sqls);
        if ($rs = $normalize_query_result(F_db_query($sqls, $db))) {
            while ($ms = $normalize_row(F_db_fetch_array($rs))) {
                /** @var array{subject_id:int,subject_name:string,subject_description:mixed} $ms */
                $subject_id = $ms['subject_id'];
                $subject_name = $ms['subject_name'];
                $subject_description = F_decode_tcecode($ms['subject_description']);

                $pdf->addReportPage();

                // subject header block
                $html =
                    '<h1 style="text-align:center;font-size:13pt;">' . htmlspecialchars($doc_title) . '</h1>';
                $html .=
                    '<div style="background-color:#cccccc;font-weight:bold;padding:2px;">'
                    . htmlspecialchars($module_name . ' :: ' . $subject_name)
                    . '</div>';
                $html .=
                    '<div style="font-size:8pt;border:0.5px solid #000000;padding:2px;">'
                    . $subject_description
                    . '</div>';

                // ---- questions
                $sqlq =
                    'SELECT * FROM '
                    . K_TABLE_QUESTIONS
                    . '
					WHERE question_subject_id='
                    . $subject_id
                    . '
					ORDER BY question_enabled DESC, question_position, question_description';
                if ($rq = $normalize_query_result(F_db_query($sqlq, $db))) {
                    $itemcount = 1;
                    while ($mq = $normalize_row(F_db_fetch_array($rq))) {
                        /**
                         * @var array{
                         *     question_id:int,
                         *     question_enabled:mixed,
                         *     question_type:1|2|3|4|5,
                         *     question_difficulty:int|float|string,
                         *     question_position:int,
                         *     question_timer:int,
                         *     question_fullscreen:mixed,
                         *     question_inline_answers:mixed,
                         *     question_auto_next:mixed,
                         *     question_description:mixed,
                         *     question_explanation:mixed
                         * } $mq
                         */
                        $disabled = !f_get_boolean($mq['question_enabled']);
                        $rowstyle = $disabled ? 'color:#999999;' : '';
                        $flags =
                            (f_get_boolean($mq['question_fullscreen']) ? 'F' : '')
                            . (f_get_boolean($mq['question_inline_answers']) ? 'I' : '')
                            . (f_get_boolean($mq['question_auto_next']) ? 'A' : '');
                        $pos = $mq['question_position'] > 0 ? $mq['question_position'] : '';
                        $timer = $mq['question_timer'] > 0 ? $mq['question_timer'] : '';

                        // question metadata row: number, type, difficulty, position, flags, timer
                        $html .=
                            '<table border="0.5" cellpadding="2" style="font-size:7pt;'
                            . $rowstyle
                            . '"><tr style="text-align:center;">';
                        foreach ([
                            '#' . $itemcount,
                            $question_type_text($mq['question_type']),
                            $mq['question_difficulty'],
                            $pos,
                            $flags,
                            $timer,
                        ] as $c) {
                            $html .= '<td>' . htmlspecialchars((string) $c) . '</td>';
                        }
                        $html .= '</tr></table>';

                        $html .=
                            '<div style="font-size:8pt;'
                            . $rowstyle
                            . '">'
                            . F_decode_tcecode($mq['question_description'])
                            . '</div>';
                        if ($question_explanation_enabled && !empty($mq['question_explanation'])) {
                            $html .=
                                '<div style="font-size:7pt;border:0.5px solid #000000;"><b><i><u>'
                                . htmlspecialchars($l['w_explanation'])
                                . '</u></i></b><br/>'
                                . F_decode_tcecode($mq['question_explanation'])
                                . '</div>';
                        }

                        if ($show_answers) {
                            $sqla =
                                'SELECT * FROM '
                                . K_TABLE_ANSWERS
                                . '
								WHERE answer_question_id=\''
                                . $mq['question_id']
                                . '\'
								ORDER BY answer_position,answer_isright DESC';
                            if ($ra = $normalize_query_result(F_db_query($sqla, $db))) {
                                $html .= '<table border="0.5" cellpadding="2" style="font-size:7pt;">';
                                $idx = 0;
                                while ($ma = $normalize_row(F_db_fetch_array($ra))) {
                                    /**
                                     * @var array{
                                     *     answer_enabled:mixed,
                                     *     answer_isright:mixed,
                                     *     answer_position:int,
                                     *     answer_keyboard_key:int,
                                     *     answer_description:mixed,
                                     *     answer_explanation:mixed
                                     * } $ma
                                     */
                                    ++$idx;
                                    $adisabled = !f_get_boolean($ma['answer_enabled']);
                                    $astyle = $adisabled ? 'color:#999999;' : '';
                                    $rightmark = !in_array((int) $mq['question_type'], [4, 5], true)
                                        ? $right_answer_mark($ma['answer_isright'])
                                        : '';
                                    $apos = $ma['answer_position'] > 0 ? $ma['answer_position'] : '';
                                    $akey = $ma['answer_keyboard_key'] > 0
                                        ? f_text_to_xml(chr($ma['answer_keyboard_key']))
                                        : '';
                                    $html .= '<tr style="' . $astyle . '">';
                                    $html .= '<td style="text-align:center;">' . $idx . '</td>';
                                    $html .=
                                        '<td style="text-align:center;">'
                                        . htmlspecialchars($rightmark)
                                        . '</td>';
                                    $html .=
                                        '<td style="text-align:center;">' . htmlspecialchars((string) $apos) . '</td>';
                                    $html .=
                                        '<td style="text-align:center;">' . htmlspecialchars($akey) . '</td>';
                                    $html .= '<td>' . F_decode_tcecode($ma['answer_description']) . '</td>';
                                    $html .= '</tr>';
                                    if ($answer_explanation_enabled && !empty($ma['answer_explanation'])) {
                                        $html .=
                                            '<tr><td colspan="5" style="font-size:6pt;"><b><i><u>'
                                            . htmlspecialchars($l['w_explanation'])
                                            . '</u></i></b><br/>'
                                            . F_decode_tcecode($ma['answer_explanation'])
                                            . '</td></tr>';
                                    }
                                }
                                $html .= '</table>';
                            } else {
                                F_display_db_error();
                            }
                        }
                        ++$itemcount;
                    } // end while questions
                } else {
                    F_display_db_error();
                }

                if ($rtl) {
                    $html = '<div dir="rtl">' . $html . '</div>';
                }
                $pdf->writeReportHTML($html);
            } // end while topics
        } else {
            F_display_db_error();
        }
    } // end while modules
} else {
    F_display_db_error();
}

// build the download file name
$pdf_filename = match ($expmode) {
    1 => 'tcexam_subject_' . $subject_id . '_' . date('YmdHi') . '.pdf',
    2 => 'tcexam_module_' . $module_id . '_' . date('YmdHi') . '.pdf',
    3 => 'tcexam_all_modules_' . date('YmdHi') . '.pdf',
    default => 'tcexam_export_' . date('YmdHi') . '.pdf',
};

$pdf->outputReport($pdf_filename);
