<?php

//============================================================+
// File name   : tce_pdf_testgen.php
// Begin       : 2004-06-13
// Last Update : 2026-06-22
//
// Description : Creates PDF documents for offline (pen-and-paper) testing,
//               including the OMR (Optical Mark Recognition) answer sheet.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Creates PDF documents for Pen-and-Paper testing.
 *
 * NOTE: the OMR answer-sheet grid (alignment marks, answer bubbles, barcodes) is
 * read back by a physical scanner via tce_functions_omr.php, so its coordinates must
 * stay byte-for-byte identical to the legacy layout. The question sheet (not scanned)
 * is rendered with the tc-lib-pdf HTML engine.
 *
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2004-06-13
 * @param $_REQUEST['test_id'] (int) test ID
 * @param $_REQUEST['num'] (int) number of tests to generate
 */

// Use the generated tc-lib-pdf fonts for this document (set before the config defines the legacy default).
require_once __DIR__ . '/../../vendor/autoload.php';
define('K_PATH_FONTS', realpath(__DIR__ . '/../../vendor/tecnickcom/tc-lib-pdf-font/target/fonts'));

require_once '../config/tce_config.php';
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_test.php';
require_once 'tce_functions_omr.php';
require_once '../../shared/config/tce_pdf.php';
require_once '../../shared/code/tce_pdf_report.php';

/** @var mixed $db */
/**
 * @var array{
 *     w_test:string,h_test:string,a_meta_dir:string,w_lastname:string,w_firstname:string,w_code:string,
 *     w_score:string,w_test_score_threshold:string,w_test_time:string,w_minutes:string,w_time_begin:string,
 *     w_time_end:string,w_score_right:string,w_score_wrong:string,w_score_unanswered:string,w_max_score:string,
 *     w_true_acronym:string,w_false_acronym:string
 * } $l
 */

// --- Initialize variables
$requested_test_id = $_REQUEST['test_id'] ?? null;
if ($requested_test_id !== null && f_tce_pdf_testgen_is_positive($requested_test_id)) {
    $test_id = (int) $requested_test_id;
    // check user's authorization
    if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
        exit();
    }
} else {
    exit();
}

$test_num = isset($_REQUEST['num']) ? (int) $_REQUEST['num'] : 1;

$doc_title = unhtmlentities($l['w_test']);
$doc_description = f_compact_string(unhtmlentities($l['h_test']));
$database_type = f_tce_pdf_testgen_string(K_DATABASE_TYPE);
$matching_reuse_condition = $database_type === 'ORACLE'
    ? "dbms_lob.instr(question_description,'<!--TMF_MATCH_REUSE-->',1,1)>0"
    : "question_description LIKE '%<!--TMF_MATCH_REUSE-->%'";

$rtl_doc = $l['a_meta_dir'] === 'rtl';
$dirlabel = $rtl_doc ? 'left' : 'right';
$dirvalue = $rtl_doc ? 'right' : 'left';

// --- OMR grid geometry (millimetres) — DO NOT change: read back by the scanner.
$grid_color = [255, 0, 0];
$grid_bg_color = [255, 205, 205];
$circle_bg_color = [255, 255, 255];
$line_width = 0.177; // about half point
$circle_radius = $line_width * 11;
$circle_width = (2 * $circle_radius) + $line_width;
$circle_shift = $circle_width + $line_width;
$circle_half_width = $circle_width / 2;
$align_mark_color = [0, 0, 0];
$align_mark_width = $line_width * 7;
$align_mark_length = $line_width * 22;
$align_mark_shift = $line_width * 8;
$row_height = $circle_width + (8 * $line_width);

$grid_hex = f_tce_pdf_testgen_rgb($grid_color);
$grid_bg_hex = f_tce_pdf_testgen_rgb($grid_bg_color);
$circle_bg_hex = f_tce_pdf_testgen_rgb($circle_bg_color);
$align_hex = f_tce_pdf_testgen_rgb($align_mark_color);
$omr_line_style = ['lineWidth' => $line_width, 'lineColor' => $grid_hex];

// get test data
$testdata = f_tce_pdf_testgen_test_data(f_get_test_data($test_id));
$test_random_questions_select = f_get_boolean($testdata['test_random_questions_select']);
$test_random_questions_order = f_get_boolean($testdata['test_random_questions_order']);
$test_questions_order_mode = (int) $testdata['test_questions_order_mode'];
$test_random_answers_select = f_get_boolean($testdata['test_random_answers_select']);
$test_random_answers_order = f_get_boolean($testdata['test_random_answers_order']);
$test_answers_order_mode = (int) $testdata['test_answers_order_mode'];
$random_questions = $test_random_questions_select || $test_random_questions_order;
$sql_answer_position = '';
if (!$test_random_answers_order && $test_answers_order_mode === 0) {
    $sql_answer_position = ' AND answer_position>0';
}

$sql_questions_order_by = '';
switch ($test_questions_order_mode) {
    case 0: // position
        $sql_questions_order_by = ' AND question_position>0 ORDER BY question_position';
        break;
    case 1: // alphabetic
        $sql_questions_order_by = ' ORDER BY question_description';
        break;
    case 2: // ID
        $sql_questions_order_by = ' ORDER BY question_id';
        break;
    case 3: // type
        $sql_questions_order_by = ' ORDER BY question_type';
        break;
    case 4: // subject ID
        $sql_questions_order_by = ' ORDER BY question_subject_id';
        break;
}

// --- create the PDF document (tc-lib-pdf) ---

$pdf = new TcePdfReport();
$pdf->setCreator('TCExam ver.' . f_tce_pdf_testgen_string(K_TCEXAM_VERSION));
$pdf->setAuthor(PDF_AUTHOR);
$pdf->setTitle($doc_title);
$pdf->setSubject($doc_description);
$pdf->setKeywords('TCExam, ' . $doc_title);
$pdf->setLanguageArray($l);
$pdf->setReportHeader(PDF_HEADER_TITLE, PDF_HEADER_STRING, PDF_HEADER_LOGO, (float) PDF_HEADER_LOGO_WIDTH);

// Draw a text cell at an absolute position (used for the coordinate-exact OMR pages).
$omrText = static function (
    string $txt,
    float $px,
    float $py,
    float $w,
    float $h,
    string $halign,
    string $fname,
    string $fstyle,
    int $fsize,
    string $colorhex,
) use ($pdf): string {
    // @mago-expect analysis:unhandled-thrown-type -- configured PDF fonts are required for this export
    $fnt = $pdf->font->insert($pdf->pon, $fname, $fstyle, $fsize);
    // @mago-expect analysis:unhandled-thrown-type -- PDF rendering failures retain the legacy fail-fast behavior
    // @mago-expect analysis:unhandled-thrown-type -- Unicode rendering failures retain the legacy fail-fast behavior
    return (
        $fnt['out']
        . $pdf->color->getPdfColor($colorhex)
        . $pdf->getTextCell(
            txt: $txt,
            posx: $px,
            posy: $py,
            width: $w,
            height: $h,
            offset: 0,
            linespace: 0,
            valign: 'C',
            halign: $halign,
        )
    );
};

// NOTE: PDF tests are always random

for ($item = 1; $item <= $test_num; ++$item) {
    // generate $test_num tests

    // data to be printed as QR-Code to be later used as input from scanner/image
    $barcode_test_data = [];
    $barcode_test_data[0] = $test_id;

    $test_ref = $test_id . ':' . $item . ':' . date(K_TIMESTAMP_FORMAT);

    // ====================================================================
    // QUESTION SHEET (HTML; not scanned, so rendered with the HTML engine)
    // ====================================================================
    $pdf->enablePageDecoration(true);
    $pdf->setTCExamBackLink(K_PATH_URL . 'admin/code/tce_edit_test.php?test_id=' . $test_id);
    $pdf->addReportPage();

    $html =
        '<h2 style="text-align:center;background-color:#cccccc;border:0.5px solid #000000;">'
        . htmlspecialchars($doc_title)
        . '</h2>';
    $html .= '<div style="text-align:center;color:#ff0000;font-size:7pt;">[' . htmlspecialchars($test_ref) . ']</div>';

    // user data input boxes
    $html .=
        '<table border="0.5" cellpadding="3" style="font-size:8pt;text-align:center;">'
        . '<tr style="background-color:#cccccc;font-weight:bold;"><td width="25%">'
        . htmlspecialchars($l['w_lastname'])
        . '</td>'
        . '<td width="25%">'
        . htmlspecialchars($l['w_firstname'])
        . '</td>'
        . '<td width="25%">'
        . htmlspecialchars($l['w_code'])
        . '</td>'
        . '<td width="25%">'
        . htmlspecialchars($l['w_score'])
        . '</td></tr>'
        . '<tr><td style="padding-top:10px;padding-bottom:10px;">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></table>';

    // test info box
    $threshold = '';
    if ($testdata['test_score_threshold'] > 0) {
        $threshold =
            '<tr><td style="font-weight:bold;" align="'
            . $dirlabel
            . '">'
            . htmlspecialchars($l['w_test_score_threshold'])
            . ': </td><td align="'
            . $dirvalue
            . '">'
            . htmlspecialchars((string) $testdata['test_score_threshold'])
            . '</td></tr>';
    }
    $info = [
        $l['w_test_time'] . ' [' . $l['w_minutes'] . ']' => $testdata['test_duration_time'],
        $l['w_time_begin'] => '',
        $l['w_time_end'] => '',
        $l['w_score_right'] => $testdata['test_score_right'],
        $l['w_score_wrong'] => $testdata['test_score_wrong'],
        $l['w_score_unanswered'] => $testdata['test_score_unanswered'],
        $l['w_max_score'] => $testdata['test_max_score'],
    ];
    $html .= '<table border="0.5" cellpadding="2" style="font-size:8pt;">';
    $html .=
        '<tr style="background-color:#cccccc;font-weight:bold;"><td colspan="2">'
        . htmlspecialchars($l['w_test'] . ': ' . $testdata['test_name'])
        . '</td></tr>';
    foreach ($info as $k => $v) {
        $html .=
            '<tr><td style="font-weight:bold;" width="40%" align="'
            . $dirlabel
            . '">'
            . htmlspecialchars($k)
            . ': </td><td align="'
            . $dirvalue
            . '">'
            . htmlspecialchars((string) $v)
            . '</td></tr>';
    }
    $html .= $threshold . '</table>';
    if (!empty($testdata['test_description'])) {
        $html .=
            '<div style="font-size:8pt;border:0.5px solid #000000;padding:2px;">'
            . F_decode_tcecode($testdata['test_description'])
            . '</div>';
    }

    // count questions
    $itemcount = 1;

    // selected questions IDs
    $right_answers_mcsa_questions_ids = '';
    $wrong_answers_mcsa_questions_ids = [];
    $answers_mcma_questions_ids = [];
    $answers_order_questions_ids = [4 => null, 5 => null];
    $selected_questions = '0';

    // 2. for each set of subjects
    $sql =
        'SELECT *
		FROM '
        . K_TABLE_TEST_SUBJSET
        . '
		WHERE tsubset_test_id='
        . $test_id
        . '
		ORDER BY tsubset_type, tsubset_difficulty, tsubset_answers DESC';
    $questions_data = [];
    $r = f_tce_pdf_testgen_query_result(F_db_query($sql, $db));
    if ($r) {
        while ($m = f_tce_pdf_testgen_row(F_db_fetch_array($r))) {
            /**
             * @var array{
             *     tsubset_type:int|string,tsubset_id:int|string,tsubset_difficulty:int|float|numeric-string,
             *     tsubset_answers:int|numeric-string,tsubset_quantity:int|numeric-string
             * } $m
             */
            /** @var int|numeric-string $raw_subset_type */
            $raw_subset_type = $m['tsubset_type'];
            $subset_type = (int) $raw_subset_type;
            // 3. select the subjects IDs
            $selected_subjects = '0';
            $sqlt =
                'SELECT subjset_subject_id FROM '
                . K_TABLE_SUBJECT_SET
                . ' WHERE subjset_tsubset_id='
                . $m['tsubset_id'];
            $rt = f_tce_pdf_testgen_query_result(F_db_query($sqlt, $db));
            if ($rt) {
                while ($mt = f_tce_pdf_testgen_row(F_db_fetch_array($rt))) {
                    /** @var array{subjset_subject_id:int|string} $mt */
                    $selected_subjects .= ',' . $mt['subjset_subject_id'];
                }
            }

            // 4. select questions
            $sqlq = 'SELECT question_id, question_type, question_difficulty, question_position, question_description
				FROM ' . K_TABLE_QUESTIONS;
            $sqlq .=
                ' WHERE question_subject_id IN ('
                . $selected_subjects
                . ')
				AND question_difficulty='
                . $m['tsubset_difficulty']
                . '
				AND question_enabled=\'1\'
				AND question_id NOT IN ('
                . $selected_questions
                . ')';
            if ($subset_type > 0) {
                $sqlq .= ' AND question_type=' . $subset_type;
            } else {
                // Keep malformed MATCHING questions out of mixed-type sets.
                $sqlq .=
                    ' AND (question_type<>5 OR question_id IN (
					SELECT answer_question_id FROM '
                    . K_TABLE_ANSWERS
                    . " WHERE answer_enabled='1' AND answer_position>0
					GROUP BY answer_question_id
					HAVING (COUNT(answer_id)>1)
					AND ((COUNT(answer_id)=COUNT(DISTINCT answer_position))
						OR answer_question_id IN (
							SELECT question_id FROM "
                    . K_TABLE_QUESTIONS
                    . ' WHERE '
                    . $matching_reuse_condition
                    . "
						))))";
            }

            if ($subset_type === 1) {
                // (MCSA : Multiple Choice Single Answer)
                if ($right_answers_mcsa_questions_ids === '') {
                    $right_answers_mcsa_questions_ids = '0';
                    $sqlt =
                        'SELECT DISTINCT answer_question_id FROM '
                        . K_TABLE_ANSWERS
                        . " WHERE answer_enabled='1' AND answer_isright='1'"
                        . $sql_answer_position;
                    $rt = f_tce_pdf_testgen_query_result(F_db_query($sqlt, $db));
                    if ($rt) {
                        while ($mt = f_tce_pdf_testgen_row(F_db_fetch_array($rt))) {
                            /** @var array{answer_question_id:int|string} $mt */
                            $right_answers_mcsa_questions_ids .= ',' . $mt['answer_question_id'];
                        }
                    }
                }

                $sqlq .= ' AND question_id IN (' . $right_answers_mcsa_questions_ids . ')';
                if ($m['tsubset_answers'] > 0) {
                    $answers_key = "'" . $m['tsubset_answers'] . "'";
                    $wrong_answer_ids = $wrong_answers_mcsa_questions_ids[$answers_key] ?? '0';
                    if (!isset($wrong_answers_mcsa_questions_ids[$answers_key])) {
                        $sqlt =
                            'SELECT answer_question_id FROM '
                            . K_TABLE_ANSWERS
                            . " WHERE answer_enabled='1' AND answer_isright='0'"
                            . $sql_answer_position
                            . ' GROUP BY answer_question_id HAVING (COUNT(answer_id)>='
                            . ($m['tsubset_answers'] - 1)
                            . ')';
                        $rt = f_tce_pdf_testgen_query_result(F_db_query($sqlt, $db));
                        if ($rt) {
                            while ($mt = f_tce_pdf_testgen_row(F_db_fetch_array($rt))) {
                                /** @var array{answer_question_id:int|string} $mt */
                                $wrong_answer_ids .= ',' . $mt['answer_question_id'];
                            }
                        }
                        $wrong_answers_mcsa_questions_ids[$answers_key] = $wrong_answer_ids;
                    }

                    $sqlq .= ' AND question_id IN (' . $wrong_answer_ids . ')';
                }
            } elseif ($subset_type === 2) {
                // (MCMA : Multiple Choice Multiple Answers)
                if ($m['tsubset_answers'] > 0) {
                    $answers_key = "'" . $m['tsubset_answers'] . "'";
                    $multiple_answer_ids = $answers_mcma_questions_ids[$answers_key] ?? '0';
                    if (!isset($answers_mcma_questions_ids[$answers_key])) {
                        $sqlt =
                            'SELECT answer_question_id FROM '
                            . K_TABLE_ANSWERS
                            . " WHERE answer_enabled='1'"
                            . $sql_answer_position
                            . ' GROUP BY answer_question_id HAVING (COUNT(answer_id)>='
                            . $m['tsubset_answers']
                            . ')';
                        $rt = f_tce_pdf_testgen_query_result(F_db_query($sqlt, $db));
                        if ($rt) {
                            while ($mt = f_tce_pdf_testgen_row(F_db_fetch_array($rt))) {
                                /** @var array{answer_question_id:int|string} $mt */
                                $multiple_answer_ids .= ',' . $mt['answer_question_id'];
                            }
                        }
                        $answers_mcma_questions_ids[$answers_key] = $multiple_answer_ids;
                    }

                    $sqlq .= ' AND question_id IN (' . $multiple_answer_ids . ')';
                }
            } elseif (in_array($subset_type, [4, 5], true)) {
                // ORDERING / MATCHING
                $position_type = $subset_type;
                $answer_question_ids = $position_type === 4
                    ? $answers_order_questions_ids[4]
                    : $answers_order_questions_ids[5];
                if ($answer_question_ids === null) {
                    $answer_question_ids = '0';
                    $matching_having = $position_type === 5
                        ? ' AND ((COUNT(answer_id)=COUNT(DISTINCT answer_position))'
                            . ' OR answer_question_id IN (SELECT question_id FROM '
                            . K_TABLE_QUESTIONS
                            . ' WHERE ' . $matching_reuse_condition . '))'
                        : '';
                    $sqlt =
                        'SELECT answer_question_id FROM '
                        . K_TABLE_ANSWERS
                        . " WHERE answer_enabled='1' AND answer_position>0 GROUP BY answer_question_id HAVING (COUNT(answer_id)>1)"
                        . $matching_having;
                    $rt = f_tce_pdf_testgen_query_result(F_db_query($sqlt, $db));
                    if ($rt) {
                        while ($mt = f_tce_pdf_testgen_row(F_db_fetch_array($rt))) {
                            /** @var array{answer_question_id:int|string} $mt */
                            $answer_question_ids .= ',' . $mt['answer_question_id'];
                        }
                    }

                    if ($position_type === 4) {
                        $answers_order_questions_ids[4] = $answer_question_ids;
                    } else {
                        $answers_order_questions_ids[5] = $answer_question_ids;
                    }
                }

                $sqlq .= ' AND question_id IN (' . $answer_question_ids . ')';
            }

            if ($random_questions) {
                $sqlq .= ' ORDER BY RAND()';
            } else {
                $sqlq .= $sql_questions_order_by;
            }

            if (f_legacy_literal_equals($database_type, 'ORACLE')) {
                $sqlq = 'SELECT * FROM (' . $sqlq . ') WHERE rownum <= ' . $m['tsubset_quantity'];
            } else {
                $sqlq .= ' LIMIT ' . $m['tsubset_quantity'];
            }

            $rq = f_tce_pdf_testgen_query_result(F_db_query($sqlq, $db));
            if ($rq) {
                while ($mq = f_tce_pdf_testgen_row(F_db_fetch_array($rq))) {
                    /**
                     * @var array{
                     *     question_id:int|string,question_type:int|string,question_difficulty:int|float|numeric-string,
                     *     question_position:int|string,question_description:string
                     * } $mq
                     */
                    /** @var int|numeric-string $raw_question_type */
                    $raw_question_type = $mq['question_type'];
                    /** @var int<1, 5> $normalized_question_type */
                    $normalized_question_type = (int) $raw_question_type;
                    $tmp_data = [
                        'id' => $mq['question_id'],
                        'type' => $normalized_question_type,
                        'difficulty' => $mq['question_difficulty'],
                        'description' => $mq['question_description'],
                        'answers' => $m['tsubset_answers'],
                        'score' => $testdata['test_score_unanswered'] * $mq['question_difficulty'],
                    ];
                    if ($random_questions || $test_questions_order_mode !== 0) {
                        $questions_data[] = $tmp_data;
                    } else {
                        $questions_data[$mq['question_position']] = $tmp_data;
                    }

                    $selected_questions .= ',' . $mq['question_id'];
                }
            } else {
                F_display_db_error(false);
                return false;
            }
        } // end while for each set of subjects

        // 5. STORE QUESTIONS AND ANSWERS
        if ($random_questions) {
            shuffle($questions_data);
        } else {
            ksort($questions_data);
        }
        $questions_data = array_values($questions_data);

        // 4. PRINT QUESTIONS (build HTML; page-break-inside:avoid replaces the legacy transaction logic)
        $question_order = 0;
        foreach ($questions_data as $key => $q) {
            ++$question_order;

            // add question ID to QR-Code data
            $barcode_test_data[$question_order] = [
                0 => $q['id'],
                1 => [],
            ];

            $block = '<div style="page-break-inside:avoid;">';
            // question number + type, max points, description
            // width:100% + explicit per-column widths (summing to 100%) so the row spans
            // the full content width up to the margin; an auto description column would
            // otherwise default to availableWidth/cols, leaving the table narrow and
            // letting wide images overflow past the cell. (See tce_pdf_report.php.)
            $block .=
                '<table border="0.5" cellpadding="2" style="width:100%;font-size:8pt;"><tr>'
                . '<td align="right" style="width:8%;">'
                . htmlspecialchars($itemcount . ' ' . f_tce_pdf_testgen_question_type_label($q['type']))
                . '</td>'
                . '<td align="right" style="width:8%;">'
                . htmlspecialchars((string) ($q['difficulty'] * $testdata['test_score_right']))
                . '</td>'
                . '<td style="width:84%;">'
                . F_decode_tcecode($q['description'])
                . '</td></tr></table>';

            ++$itemcount;

            if ($q['type'] === 3) {
                // free-text question: print a writing area; the correct short answers are
                // printed in hidden white (visible only via "Replace Document Colors").
                $shortanswers = '';
                $sqlsa =
                    'SELECT answer_description FROM '
                    . K_TABLE_ANSWERS
                    . '
					WHERE answer_question_id='
                    . $q['id']
                    . " AND answer_enabled='1' AND answer_isright='1'";
                $rsa = f_tce_pdf_testgen_query_result(F_db_query($sqlsa, $db));
                if ($rsa) {
                    while ($msa = f_tce_pdf_testgen_row(F_db_fetch_array($rsa))) {
                        /** @var array{answer_description:string} $msa */
                        $shortanswers .= $msa['answer_description'] . ' ; ';
                    }
                } else {
                    F_display_db_error();
                }
                $block .=
                    '<div style="border:0.5px solid #000000;height:'
                    . (int) PDF_TEXTANSWER_HEIGHT
                    . 'px;color:#ffffff;font-size:7pt;">'
                    . htmlspecialchars($shortanswers)
                    . '</div>';
            } else {
                // select answers (identical logic to the legacy generator)
                $randorder = $test_random_answers_order;
                $answers_ids = [];
                switch ($q['type']) {
                    case 1: // MCSA
                        $answers_ids += f_tce_pdf_testgen_answer_ids(
                            f_select_answers($q['id'], 1, false, 1, 0, $randorder, $test_answers_order_mode),
                        );
                        $answers_ids += f_tce_pdf_testgen_answer_ids(f_select_answers(
                            $q['id'],
                            0,
                            false,
                            $q['answers'] - 1,
                            1,
                            $randorder,
                            $test_answers_order_mode,
                        ));
                        break;
                    case 2: // MCMA
                        $answers_ids += f_tce_pdf_testgen_answer_ids(f_select_answers(
                            $q['id'],
                            '',
                            false,
                            $q['answers'],
                            0,
                            $randorder,
                            $test_answers_order_mode,
                        ));
                        break;
                    case 4: // ORDERING
                    case 5: // MATCHING
                        $randorder = true;
                        $answers_ids += f_tce_pdf_testgen_answer_ids(
                            f_select_answers($q['id'], '', true, 0, 0, $randorder, $test_answers_order_mode),
                        );
                        break;
                }

                if ($randorder) {
                    shuffle($answers_ids);
                } else {
                    ksort($answers_ids);
                }

                // width:100% so the answer rows span the full content width up to the margin
                // (explicit per-column widths below); otherwise the table auto-sizes narrow and
                // wide answer images overflow the cell. (Same fix as tce_pdf_report.php.)
                $block .= '<table border="0.5" cellpadding="2" style="width:100%;font-size:8pt;">';
                $answ_id = 0;
                foreach ($answers_ids as $key2 => $answer_id) {
                    ++$answ_id;
                    // add answer ID to QR-Code data
                    $barcode_test_data[$question_order][1][$answ_id] = $answer_id;

                    $sqla = 'SELECT * FROM ' . K_TABLE_ANSWERS . ' WHERE answer_id=' . $answer_id . ' LIMIT 1';
                    $ra = f_tce_pdf_testgen_query_result(F_db_query($sqla, $db));
                    if ($ra) {
                        $ma = f_tce_pdf_testgen_row(F_db_fetch_array($ra));
                        if ($ma) {
                            /** @var array{answer_position:int|string,answer_isright:mixed,answer_description:string} $ma */
                            $rightanswer = '';
                            if (in_array((int) $q['type'], [4, 5], true)) {
                                $rightanswer = $ma['answer_position'];
                            } elseif (f_get_boolean($ma['answer_isright'])) {
                                $rightanswer = 'X';
                            }
                            // hidden white correct-answer marker + answer number + description
                            $block .=
                                '<tr>'
                                . '<td align="center" style="width:8%;color:#ffffff;">'
                                . htmlspecialchars((string) $rightanswer)
                                . '</td>'
                                . '<td align="right" style="width:8%;">'
                                . $answ_id
                                . '</td>'
                                . '<td style="width:84%;">'
                                . F_decode_tcecode($ma['answer_description'])
                                . '</td></tr>';
                        }
                    } else {
                        F_display_db_error();
                    }
                }
                $block .= '</table>';
            }
            $block .= '</div>';
            $html .= $block;
        } // end foreach questions
    } else {
        F_display_db_error();
    }

    if ($rtl_doc) {
        $html = '<div dir="rtl">' . $html . '</div>';
    }
    $pdf->writeReportHTML($html);

    // ====================================================================
    // OMR DATA PAGE — encoded test data as a large centred QR-Code (no header)
    // ====================================================================
    $pdf->enablePageDecoration(false);
    $pdf->addPage(['format' => 'A4', 'orientation' => 'P']);
    $pg = f_tce_pdf_testgen_page($pdf->page->getPage($pdf->page->getPageId()));
    $pw = $pg['width'];
    $ph = $pg['height'];
    $cw = $pw - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;

    $out = $omrText(
        'OMR DATA',
        PDF_MARGIN_LEFT,
        PDF_MARGIN_TOP,
        $cw,
        6,
        'C',
        PDF_FONT_NAME_DATA,
        '',
        (int) round(PDF_FONT_SIZE_DATA * 1.5),
        $grid_hex,
    );
    $out .= $omrText(
        '[' . $test_ref . ']',
        PDF_MARGIN_LEFT,
        PDF_MARGIN_TOP + 6,
        $cw,
        5,
        'C',
        PDF_FONT_NAME_DATA,
        '',
        PDF_FONT_SIZE_DATA,
        $grid_hex,
    );
    $pdf->page->addContent($out);

    // encode data to be printed on the QR-Code (used to create test logs)
    $qr_test_data = f_encode_omr_test_data($barcode_test_data);
    // render at natural module size (unstretched) and centre it — a stretched QR-Code scans poorly
    $qr_data = f_tce_pdf_testgen_barcode_data(
        $pdf->barcode->getBarcodeObj('QRCODE,L', $qr_test_data)->getArray(),
    );
    $qrw = (float) $qr_data['ncols'];
    $qry = ($ph - $qrw) / 2; // vertically centred
    $qrx = ($pw - $qrw) / 2; // horizontally centred
    $pdf->page->addContent($pdf->getBarcode(
        'QRCODE,L',
        $qr_test_data,
        $qrx,
        $qry,
        (int) round($qrw),
        (int) round($qrw),
        style: ['fillColor' => $align_hex],
    ));

    // ====================================================================
    // OMR ANSWER SHEET — coordinate-exact grid (scanner-read; do not alter layout)
    // Supports up to 30 questions per sheet and up to 12 answers per question (MCSA/MCMA).
    // ====================================================================
    $num_questions = count($barcode_test_data) - 1;
    $num_omr_pages = (int) ceil($num_questions / 30);

    // centre the block on the page (legacy magic dimensions)
    $start_x = ($pw - 173.964) / 2;
    $start_y = ($ph - 178.062) / 2;
    $bcy = $ph - PDF_MARGIN_FOOTER - 12;

    for ($omrpage = 0; $omrpage < $num_omr_pages; ++$omrpage) {
        $pdf->addPage(['format' => 'A4', 'orientation' => 'P']);

        $head = $omrText(
            'OMR ANSWER SHEET ' . ($omrpage + 1),
            PDF_MARGIN_LEFT,
            PDF_MARGIN_TOP,
            $cw,
            6,
            'C',
            PDF_FONT_NAME_DATA,
            '',
            (int) round(PDF_FONT_SIZE_DATA * 1.5),
            $grid_hex,
        );
        $head .= $omrText(
            '[' . $test_ref . ']',
            PDF_MARGIN_LEFT,
            PDF_MARGIN_TOP + 6,
            $cw,
            5,
            'C',
            PDF_FONT_NAME_DATA,
            '',
            PDF_FONT_SIZE_DATA,
            $grid_hex,
        );
        $pdf->page->addContent($head);

        // starting (first) question number on this sheet
        $first_question = 1 + (30 * $omrpage);
        $qnum = sprintf('%04d', $first_question);

        $out = '';

        // top alignment marks for columns
        $x = $start_x;
        $y = $start_y;
        $out .= $pdf->graph->getRect($x, $y, $align_mark_length, $align_mark_length, 'F', ['all' => [
            'fillColor' => $align_hex,
        ]]);
        $x += $align_mark_length + 9;
        for ($i = 0; $i < 12; ++$i) {
            $x += $circle_shift;
            $out .= $pdf->graph->getRect(
                $x + $align_mark_shift,
                $y,
                $align_mark_width,
                $align_mark_length,
                'F',
                ['all' => ['fillColor' => $align_hex]],
            );
            $x += $circle_shift;
            $out .= $pdf->graph->getRect(
                $x + $align_mark_shift,
                $y,
                $align_mark_width,
                $align_mark_length,
                'F',
                ['all' => ['fillColor' => $align_hex]],
            );
            $x += $circle_shift;
        }

        $y += $row_height + $circle_half_width;

        for ($rr = 0; $rr < 30; ++$rr) {
            $current_question = $first_question + $rr;
            $x = $start_x;
            $cy = $y + $circle_half_width;
            // left alignment mark for row
            $out .= $pdf->graph->getRect(
                $x,
                $y + $align_mark_shift,
                $align_mark_length,
                $align_mark_width,
                'F',
                ['all' => ['fillColor' => $align_hex]],
            );
            $x += $align_mark_length;
            if ($current_question <= $num_questions) {
                if (($rr % 2) !== 0) {
                    // row background
                    $out .= $pdf->graph->getRect(
                        $x,
                        $y - (4 * $line_width),
                        166.176,
                        $circle_width + (8 * $line_width),
                        'F',
                        ['all' => ['fillColor' => $grid_bg_hex]],
                    );
                }

                // print question number (courier bold)
                $out .= $omrText(
                    (string) $current_question,
                    $x,
                    $y,
                    8,
                    $circle_width,
                    'R',
                    'courier',
                    'B',
                    10,
                    $grid_hex,
                );
                $x += 9;
                $omr_question = $questions_data[$current_question - 1]
                    ?? throw new UnexpectedValueException('Missing generated question data');
                $question_type = $omr_question['type'];
                if ($question_type < 3) { // MCSA or MCMA
                    /** @var array{0:int|string,1:array<positive-int,int|string>} $barcode_question */
                    // @mago-expect analysis:possibly-undefined-int-array-index -- populated with every generated question
                    // @mago-expect analysis:possibly-undefined-int-array-index -- entry zero is the test identifier, questions start at one
                    $barcode_question = $barcode_test_data[$current_question];
                    $num_answers = count($barcode_question[1]);
                    for ($i = 1; $i <= 12; ++$i) {
                        if ($i <= $num_answers) {
                            // print answer number
                            $out .= $omrText(
                                (string) $i,
                                $x,
                                $y,
                                $circle_shift,
                                $circle_width,
                                'R',
                                'helvetica',
                                '',
                                8,
                                $grid_hex,
                            );
                            $x += $circle_shift;
                            // "true" select circle
                            $out .= $pdf->graph->getCircle(
                                $x + $circle_half_width,
                                $cy,
                                $circle_radius,
                                0,
                                360,
                                'DF',
                                $omr_line_style + ['fillColor' => $circle_bg_hex],
                            );
                            $out .= $omrText(
                                $l['w_true_acronym'],
                                $x,
                                $y,
                                $circle_width,
                                $circle_width,
                                'C',
                                PDF_FONT_NAME_DATA,
                                '',
                                6,
                                $grid_bg_hex,
                            );
                            $x += $circle_shift;
                            if ($question_type === 2) { // MCMA: add a "false" circle
                                $out .= $pdf->graph->getCircle(
                                    $x + $circle_half_width,
                                    $cy,
                                    $circle_radius,
                                    0,
                                    360,
                                    'DF',
                                    $omr_line_style + ['fillColor' => $circle_bg_hex],
                                );
                                $out .= $omrText(
                                    $l['w_false_acronym'],
                                    $x,
                                    $y,
                                    $circle_width,
                                    $circle_width,
                                    'C',
                                    PDF_FONT_NAME_DATA,
                                    '',
                                    6,
                                    $grid_bg_hex,
                                );
                            }
                        } else {
                            $x += 2 * $circle_shift;
                        }
                        $x += $circle_shift;
                    }
                } else {
                    $x += 36 * $circle_shift;
                }
            } else {
                $x += 9 + (36 * $circle_shift);
            }

            $x += $circle_shift;
            // right alignment mark for row
            $out .= $pdf->graph->getRect(
                $x,
                $y + $align_mark_shift,
                $align_mark_length,
                $align_mark_width,
                'F',
                ['all' => ['fillColor' => $align_hex]],
            );
            $y += $row_height;
        }

        // bottom alignment marks for columns
        $x = $start_x + $align_mark_length + 9;
        $y += $circle_half_width;
        for ($i = 0; $i < 12; ++$i) {
            $x += $circle_shift;
            $out .= $pdf->graph->getRect(
                $x + $align_mark_shift,
                $y,
                $align_mark_width,
                $align_mark_length,
                'F',
                ['all' => ['fillColor' => $align_hex]],
            );
            $x += $circle_shift;
            $out .= $pdf->graph->getRect(
                $x + $align_mark_shift,
                $y,
                $align_mark_width,
                $align_mark_length,
                'F',
                ['all' => ['fillColor' => $align_hex]],
            );
            $x += $circle_shift;
        }

        // barcode identifying the starting question number — natural width (legacy 0.8 mm/module),
        // centred horizontally; it must NOT be stretched to the content width or the scanner can misread it
        $barcode_data = f_tce_pdf_testgen_barcode_data($pdf->barcode->getBarcodeObj('C128C', $qnum)->getArray());
        $bcw = $barcode_data['full_width'] * 0.8;
        $out .= $pdf->getBarcode('C128C', $qnum, ($pw - $bcw) / 2, $bcy, (int) round($bcw), 10, style: [
            'fillColor' => $align_hex,
        ]);

        $pdf->page->addContent($out);
    } // end for each OMR page
} // end for each test

$pdf->outputReport('tcexam_test_' . $test_id . '_' . date('YmdHis') . '.pdf');

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_pdf_testgen_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** @param array{0:int,1:int,2:int} $color */
function f_tce_pdf_testgen_rgb(array $color): string
{
    return sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2]);
}

function f_tce_pdf_testgen_question_type_label(int $type): string
{
    return match ($type) {
        1 => 'S',
        2 => 'M',
        3 => 'T',
        4 => 'O',
        5 => 'C',
        default => '',
    };
}

/**
 * Preserve the legacy positive-ID request comparison.
 *
 * @param int|string|float|bool|array<array-key, mixed>|null $value
 */
function f_tce_pdf_testgen_is_positive(int|string|float|bool|array|null $value): bool
{
    if (is_array($value)) {
        return true;
    }

    return $value !== null && $value > 0;
}

/**
 * @return array{
 *     test_random_questions_select:mixed,test_random_questions_order:mixed,
 *     test_questions_order_mode:int|string,test_random_answers_select:mixed,test_random_answers_order:mixed,
 *     test_answers_order_mode:int|string,test_score_threshold:int|float|numeric-string,
 *     test_duration_time:int|float|string,test_score_right:int|float|numeric-string,
 *     test_score_wrong:int|float|numeric-string,test_score_unanswered:int|float|numeric-string,
 *     test_max_score:int|float|numeric-string,test_name:string,test_description:string
 * }
 */
function f_tce_pdf_testgen_test_data(mixed $data): array
{
    /** @var array{
     *     test_random_questions_select:mixed,test_random_questions_order:mixed,
     *     test_questions_order_mode:int|string,test_random_answers_select:mixed,test_random_answers_order:mixed,
     *     test_answers_order_mode:int|string,test_score_threshold:int|float|numeric-string,
     *     test_duration_time:int|float|string,test_score_right:int|float|numeric-string,
     *     test_score_wrong:int|float|numeric-string,test_score_unanswered:int|float|numeric-string,
     *     test_max_score:int|float|numeric-string,test_name:string,test_description:string
     * } $data
     */
    return $data;
}

/**
 * Preserve the active DAL result type across mutually exclusive database implementations.
 *
 * @return object|resource|bool
 */
function f_tce_pdf_testgen_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<array-key, mixed>|null */
function f_tce_pdf_testgen_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/** @return array<int, int|string> */
function f_tce_pdf_testgen_answer_ids(mixed $answers): array
{
    /** @var array<int, int|string> $answers */
    return $answers;
}

/** @return array{width:float,height:float} */
function f_tce_pdf_testgen_page(mixed $page): array
{
    /** @var array{width:float,height:float} $page */
    return $page;
}

/** @return array{ncols:int|float,full_width:int|float} */
function f_tce_pdf_testgen_barcode_data(mixed $data): array
{
    /** @var array{ncols:int|float,full_width:int|float} $data */
    return $data;
}
