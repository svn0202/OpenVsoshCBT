<?php

//============================================================+
// File name   : tce_xml_questions.php
// Begin       : 2006-03-06
// Last Update : 2023-11-30
//
// Description : Functions to export questions using XML or JSON format.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display all questions grouped by topic in XML or JSON format.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-11
 */

require_once '../config/tce_config.php';
/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';

/** @var array{expmode?:int|string,module_id?:int|string,subject_id?:int|string,format?:string} $request */
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

$output_format = isset($request['format']) ? strtoupper($request['format']) : 'XML';

// check user's authorization for module
if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $module_id, 'module_user_id')) {
    exit();
}

// set XML file name
$out_filename = match ($expmode) {
    1 => 'tcexam_subject_' . $subject_id,
    2 => 'tcexam_module_' . $module_id,
    3 => 'tcexam_all_modules',
    default => 'tcexam_export',
};
$out_filename .= '_' . date('YmdHi');

// get the XML code
$xml = F_xml_export_questions($module_id, $subject_id, $expmode);

switch ($output_format) {
    case 'JSON':
        header('Content-Description: JSON File Transfer');
        header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Thu, 04 Jan 1973 00:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        // force download dialog
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream', false);
        header('Content-Type: application/download', false);
        header('Content-Type: application/json', false);
        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: attachment; filename=' . $out_filename . '.json;');
        header('Content-Transfer-Encoding: binary');
        $xmlobj = new SimpleXMLElement($xml);
        echo json_encode($xmlobj, JSON_THROW_ON_ERROR);
        break;

    case 'XML':
    default:
        header('Content-Description: XML File Transfer');
        header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Thu, 04 Jan 1973 00:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        // force download dialog
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream', false);
        header('Content-Type: application/download', false);
        header('Content-Type: application/xml', false);
        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: attachment; filename=' . $out_filename . '.xml;');
        header('Content-Transfer-Encoding: binary');
        echo $xml;
        break;
}

/**
 * Export all questions of the selected subject to XML.
 * @author Nicola Asuni
 * @since 2006-03-06
 * @param $module_id (int)  module ID
 * @param $subject_id (int) topic ID
 * @param $expmode (int) export mode: 1 = selected topic; 2 = selected module; 3 = all modules.
 * @return string XML data
 */
function f_xml_export_questions(mixed $module_id, mixed $subject_id, mixed $expmode): string
{
    global $l, $db;
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_authorization.php';
    require_once '../../shared/code/tce_functions_auth_sql.php';
    $module_id = (int) $module_id;
    $subject_id = (int) $subject_id;
    $expmode = (int) $expmode;

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
    $boolean_text = static fn (mixed $value): string => match ((int) f_get_boolean($value)) {
            0 => 'false',
            1 => 'true',
        };
    $question_type = static fn (int $value): string => match ($value) {
            1 => 'single',
            2 => 'multiple',
            3 => 'text',
            4 => 'ordering',
            5 => 'matching',
            default => '',
        };

    $xml = ''; // XML data to be returned

    $xml .= '<?xml version="1.0" encoding="UTF-8" ?>' . K_NEWLINE;
    $xml .= '<tcexamquestions version="' . (string) K_TCEXAM_VERSION . '">' . K_NEWLINE;
    $xml .= K_TAB . '<header';
    $xml .= ' lang="' . (string) K_USER_LANG . '"';
    $xml .= ' date="' . date(K_TIMESTAMP_FORMAT) . '">' . K_NEWLINE;
    $xml .= K_TAB . '</header>' . K_NEWLINE;
    $xml .= K_TAB . '<body>' . K_NEWLINE;

    // ---- module
    $andmodwhere = '';
    if ($expmode < 3) {
        $andmodwhere = 'module_id=' . $module_id . '';
    }

    $sqlm = F_select_modules_sql($andmodwhere);
    if ($rm = $normalize_query_result(F_db_query($sqlm, $db))) {
        while ($mm = $normalize_row(F_db_fetch_array($rm))) {
            /** @var array{module_id:int|string,module_name:mixed,module_enabled:mixed} $mm */
            $xml .= K_TAB . K_TAB . '<module>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . '<name>';
            $xml .= f_text_to_xml($mm['module_name']);
            $xml .= '</name>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . '<enabled>';
            $xml .= $boolean_text($mm['module_enabled']);
            $xml .= '</enabled>' . K_NEWLINE;

            // ---- topic
            $where_sqls = 'subject_module_id=' . $mm['module_id'] . '';
            if ($expmode < 2) {
                $where_sqls .= ' AND subject_id=' . $subject_id . '';
            }

            $sqls = F_select_subjects_sql($where_sqls);
            if ($rs = $normalize_query_result(F_db_query($sqls, $db))) {
                while ($ms = $normalize_row(F_db_fetch_array($rs))) {
                    /**
                     * @var array{
                     *     subject_id:int|string,
                     *     subject_name:mixed,
                     *     subject_description:mixed,
                     *     subject_enabled:mixed
                     * } $ms
                     */
                    $xml .= K_TAB . K_TAB . K_TAB . '<subject>' . K_NEWLINE;

                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<name>';
                    $xml .= f_text_to_xml($ms['subject_name']);
                    $xml .= '</name>' . K_NEWLINE;

                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<description>';
                    $xml .= f_text_to_xml($ms['subject_description']);
                    $xml .= '</description>' . K_NEWLINE;

                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<enabled>';
                    $xml .= $boolean_text($ms['subject_enabled']);
                    $xml .= '</enabled>' . K_NEWLINE;

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
                    if ($r = $normalize_query_result(F_db_query($sql, $db))) {
                        while ($m = $normalize_row(F_db_fetch_array($r))) {
                            /**
                             * @var array{
                             *     question_id:int|string,
                             *     question_enabled:mixed,
                             *     question_type:int,
                             *     question_difficulty:int|string,
                             *     question_position:int|string,
                             *     question_timer:int|string,
                             *     question_fullscreen:mixed,
                             *     question_inline_answers:mixed,
                             *     question_auto_next:mixed,
                             *     question_shuffle_answers:mixed,
                             *     question_description:mixed,
                             *     question_explanation:mixed
                             * } $m
                             */
                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<question>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<enabled>';
                            $xml .= $boolean_text($m['question_enabled']);
                            $xml .= '</enabled>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<type>';
                            $xml .= $question_type($m['question_type']);
                            $xml .= '</type>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<difficulty>';
                            $xml .= $m['question_difficulty'];
                            $xml .= '</difficulty>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<position>';
                            $xml .= $m['question_position'];
                            $xml .= '</position>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<timer>';
                            $xml .= $m['question_timer'];
                            $xml .= '</timer>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<fullscreen>';
                            $xml .= $boolean_text($m['question_fullscreen']);
                            $xml .= '</fullscreen>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<inline_answers>';
                            $xml .= $boolean_text($m['question_inline_answers']);
                            $xml .= '</inline_answers>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<auto_next>';
                            $xml .= $boolean_text($m['question_auto_next']);
                            $xml .= '</auto_next>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<shuffle_answers>';
                            $xml .= $boolean_text($m['question_shuffle_answers']);
                            $xml .= '</shuffle_answers>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<description>';
                            $xml .= f_text_to_xml($m['question_description']);
                            $xml .= '</description>' . K_NEWLINE;

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<explanation>';
                            $xml .= f_text_to_xml($m['question_explanation']);
                            $xml .= '</explanation>' . K_NEWLINE;

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
                            if ($ra = $normalize_query_result(F_db_query($sqla, $db))) {
                                while ($ma = $normalize_row(F_db_fetch_array($ra))) {
                                    /**
                                     * @var array{
                                     *     answer_enabled:mixed,
                                     *     answer_isright:mixed,
                                     *     answer_position:int|string,
                                     *     answer_keyboard_key:int|string,
                                     *     answer_weight:int|float|string,
                                     *     answer_description:mixed,
                                     *     answer_explanation:mixed
                                     * } $ma
                                     */
                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<answer>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<enabled>';
                                    $xml .= $boolean_text($ma['answer_enabled']);
                                    $xml .= '</enabled>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<isright>';
                                    $xml .= $boolean_text($ma['answer_isright']);
                                    $xml .= '</isright>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<position>';
                                    $xml .= $ma['answer_position'];
                                    $xml .= '</position>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<keyboard_key>';
                                    $xml .= $ma['answer_keyboard_key'];
                                    $xml .= '</keyboard_key>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<weight>';
                                    $xml .= (string) $ma['answer_weight'];
                                    $xml .= '</weight>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<description>';
                                    $xml .= f_text_to_xml($ma['answer_description']);
                                    $xml .= '</description>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '<explanation>';
                                    $xml .= f_text_to_xml($ma['answer_explanation']);
                                    $xml .= '</explanation>' . K_NEWLINE;

                                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . K_TAB . '</answer>' . K_NEWLINE;
                                }
                            } else {
                                F_display_db_error();
                            }

                            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '</question>' . K_NEWLINE;
                        } // end while for questions
                    } else {
                        F_display_db_error();
                    }

                    $xml .= K_TAB . K_TAB . K_TAB . '</subject>' . K_NEWLINE;
                } // end while for topics
            } else {
                F_display_db_error();
            }

            $xml .= K_TAB . K_TAB . '</module>' . K_NEWLINE;
        } // end while for module
    } else {
        F_display_db_error();
    }

    $xml .= K_TAB . '</body>' . K_NEWLINE;

    return $xml . ('</tcexamquestions>' . K_NEWLINE);
}
