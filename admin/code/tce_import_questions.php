<?php

//============================================================+
// File name   : tce_import_questions.php
// Begin       : 2006-03-12
// Last Update : 2023-11-30
//
// Description : Import questions from an XML file.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Import questions from an XML file to a selected subject.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-12
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_IMPORT;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     t_question_importer:string,
 *     m_importing_complete:string,
 *     w_upload_file:string,
 *     h_upload_file:string,
 *     w_type:string,
 *     w_upload:string,
 *     h_submit_file:string,
 *     hp_import_xml_questions:string
 * } $l
 */
/** @var array{type?:mixed} $request */
$request = $_REQUEST;
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;
/** @var array{userfile?:array{name?:mixed}} $files */
$files = $_FILES;
$thispage_title = $l['t_question_importer'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';

$type = !isset($request['type']) || empty($request['type']) ? 1 : (int) $request['type'];

if (isset($menu_mode) && $menu_mode === 'upload' && !empty($files['userfile']['name'])) {
    require_once '../code/tce_functions_upload.php';
    // upload file
    /** @var string|false $uploadedfile */
    $uploadedfile = f_upload_file('userfile', K_PATH_CACHE);
    if ($uploadedfile !== false) {
        $qimp = false;
        switch ($type) {
            case 1:
                    // standard TCExam XML format
                    require_once '../code/tce_class_import_xml.php';
                    $qimp = new XMLQuestionImporter(K_PATH_CACHE . $uploadedfile);
                    break;
            case 2:
                    // standard TCExam TSV format
                    $qimp = f_tsv_question_importer(K_PATH_CACHE . $uploadedfile);
                    break;
            case 3:
                    // Custom TCExam XML format
                    require_once '../code/tce_import_custom.php';
                    $importer_class = 'CustomQuestionImporter';
                    $qimp = (new ReflectionClass($importer_class))->newInstance(K_PATH_CACHE . $uploadedfile);
                    break;
        }

        if ($qimp) {
            F_print_error('MESSAGE', $l['m_importing_complete']);
        }
    }
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_importquestions">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="userfile">' . $l['w_upload_file'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="MAX_FILE_SIZE" value="' . K_MAX_UPLOAD_SIZE . '" />' . K_NEWLINE;
echo '<input type="file" name="userfile" id="userfile" size="20" title="' . $l['h_upload_file'] . '" />' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '&nbsp;' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
echo '<div class="formw">' . K_NEWLINE;
echo '<fieldset class="noborder">' . K_NEWLINE;

echo '<legend title="' . $l['w_type'] . '">' . $l['w_type'] . '</legend>' . K_NEWLINE;
echo '<input type="radio" name="type" id="type_xml" value="1" title="TCExam XML Format"';
if ($type === 1) {
    echo ' checked="checked"';
}

echo ' />';
echo '<label for="type_xml">TCExam XML</label><br />' . K_NEWLINE;

echo '<input type="radio" name="type" id="type_tsv" value="2" title="TCExam TSV Format"' . K_NEWLINE;
if ($type === 2) {
    echo ' checked="checked"';
}

echo ' />';
echo '<label for="type_tsv">TCExam TSV</label>' . K_NEWLINE;

/** @var string $custom_import */
$custom_import = K_ENABLE_CUSTOM_IMPORT;
if ($custom_import !== '') {
    echo '<input type="radio" name="type" id="type_custom" value="3" title="' . $custom_import . '"' . K_NEWLINE;
    if ($type === 3) {
        echo ' checked="checked"';
    }

    echo ' />';
    echo '<label for="type_custom">' . $custom_import . '</label>' . K_NEWLINE;
}

echo '</fieldset>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<br />' . K_NEWLINE;

// show upload button
F_submit_button('upload', $l['w_upload'], $l['h_submit_file']);

echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_import_xml_questions'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

// ---------------------------------------------------------------------

/**
 * Import questions from TSV file (tab delimited text).
 * The format of TSV is the same obtained by exporting data from TCExam interface.
 * @param $tsvfile (string) TSV (tab delimited text) file name
 * @return bool|null TRUE in case of success, FALSE for an unreadable file, null for an incomplete TSV hierarchy.
 */
function f_tsv_question_importer(mixed $tsvfile): ?bool
{
    global $l, $db;
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_auth_sql.php';
    $qtype = [
        'S' => 1,
        'M' => 2,
        'T' => 3,
        'O' => 4,
        'C' => 5,
    ];
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
    $tsvfp = fopen((string) $tsvfile, 'r');
    if ($tsvfp === false) {
        return false;
    }

    $current_module_id = 0;
    $current_subject_id = 0;
    $current_question_id = 0;
    $current_answer_id = 0;
    $questionhash = [];
    // for each row
    while ($qdata = fgetcsv($tsvfp, 0, "\t", '"')) {
        // get user data into array
        $record_type = (string) ($qdata[0] ?? '');
        switch ($record_type) {
            case 'M':
                // MODULE
                    $current_module_id = 0;
                    if (!isset($qdata[2]) || empty($qdata[2])) {
                        break;
                    }

                    $module_enabled = (int) $qdata[1];
                    $module_name = F_escape_sql($db, f_tsv_to_text($qdata[2]), false);
                    // check if this module already exist
                    $sql = 'SELECT module_id
					FROM ' . K_TABLE_MODULES . '
					WHERE module_name=\'' . $module_name . '\'
					LIMIT 1';
                    if ($r = $normalize_query_result(F_db_query($sql, $db))) {
                        if ($m = $normalize_row(F_db_fetch_array($r))) {
                            /** @var array{module_id:int|string} $m */
                            // get existing module ID
                            if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $m['module_id'], 'module_user_id')) {
                                // unauthorized user
                                $current_module_id = 0;
                            } else {
                                $current_module_id = $m['module_id'];
                            }
                        } else {
                            // insert new module
                            /** @var array{session_user_id:int} $session */
                            $session = $_SESSION;
                            $sql =
                                'INSERT INTO '
                                . K_TABLE_MODULES
                                . ' (
							module_name,
							module_enabled,
							module_user_id
							) VALUES (
							\''
                                . $module_name
                                . '\',
							\''
                                . $module_enabled
                                . '\',
							\''
                                . $session['session_user_id']
                                . '\'
							)';
                            if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                                F_display_db_error();
                            } else {
                                // get new module ID
                                $current_module_id = F_db_insert_id($db, K_TABLE_MODULES, 'module_id');
                            }
                        }
                    } else {
                        F_display_db_error();
                    }

                    break;
            case 'S':
                // SUBJECT
                    $current_subject_id = 0;
                    if ($current_module_id === 0) {
                        return null;
                    }

                    if (!isset($qdata[2]) || empty($qdata[2])) {
                        break;
                    }

                    $subject_enabled = (int) $qdata[1];
                    $subject_name = F_escape_sql($db, f_tsv_to_text($qdata[2]), false);
                    $subject_description = '';
                    if (isset($qdata[3])) {
                        $subject_description = f_empty_to_null(f_tsv_to_text($qdata[3]));
                    }

                    // check if this subject already exist
                    $sql =
                        'SELECT subject_id
					FROM '
                        . K_TABLE_SUBJECTS
                        . '
					WHERE subject_name=\''
                        . $subject_name
                        . '\'
						AND subject_module_id='
                        . $current_module_id
                        . '
					LIMIT 1';
                    if ($r = $normalize_query_result(F_db_query($sql, $db))) {
                        if ($m = $normalize_row(F_db_fetch_array($r))) {
                            /** @var array{subject_id:int|string} $m */
                            // get existing subject ID
                            $current_subject_id = $m['subject_id'];
                        } else {
                            // insert new subject
                            /** @var array{session_user_id:int} $session */
                            $session = $_SESSION;
                            $sql =
                                'INSERT INTO '
                                . K_TABLE_SUBJECTS
                                . ' (
							subject_name,
							subject_description,
							subject_enabled,
							subject_user_id,
							subject_module_id
							) VALUES (
							\''
                                . $subject_name
                                . '\',
							'
                                . $subject_description
                                . ',
							\''
                                . $subject_enabled
                                . '\',
							\''
                                . $session['session_user_id']
                                . '\',
							'
                                . $current_module_id
                                . '
							)';
                            if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                                F_display_db_error();
                            } else {
                                // get new subject ID
                                $current_subject_id = F_db_insert_id($db, K_TABLE_SUBJECTS, 'subject_id');
                            }
                        }
                    } else {
                        F_display_db_error();
                    }

                    break;
            case 'Q':
                // QUESTION
                    $current_question_id = 0;
                    if ($current_module_id === 0 || $current_subject_id === 0) {
                        return null;
                    }

                    if (!isset($qdata[5])) {
                        break;
                    }

                    $question_enabled = (int) $qdata[1];
                    $question_description = F_escape_sql($db, f_tsv_to_text($qdata[2]), false);
                    $question_explanation = f_empty_to_null(f_tsv_to_text($qdata[3]));
                    /** @var 'S'|'M'|'T'|'O'|'C' $question_code */
                    $question_code = (string) $qdata[4];
                    $question_type = array_key_exists($question_code, $qtype) ? $qtype[$question_code] : null;
                    $question_difficulty = (int) $qdata[5];
                    $question_position = isset($qdata[6]) ? f_zero_to_null($qdata[6]) : f_zero_to_null(0);
                    /** @var string $database_type */
                    $database_type = K_DATABASE_TYPE;
                    /** @var bool $mysql_binary_uniquity */
                    $mysql_binary_uniquity = K_MYSQL_QA_BIN_UNIQUITY;

                    $question_timer = isset($qdata[7]) ? (int) $qdata[7] : 0;

                    $question_fullscreen = isset($qdata[8]) ? (int) $qdata[8] : 0;

                    $question_inline_answers = isset($qdata[9]) ? (int) $qdata[9] : 0;

                    $question_auto_next = isset($qdata[10]) ? (int) $qdata[10] : 0;
                    $question_shuffle_answers = isset($qdata[11]) ? (int) $qdata[11] : 0;

                    // check if this question already exist
                    $sql = 'SELECT question_id
					FROM ' . K_TABLE_QUESTIONS . '
					WHERE ';
                    if (f_legacy_literal_equals($database_type, 'ORACLE')) {
                        $sql .= "dbms_lob.instr(question_description,'" . $question_description . "',1,1)>0";
                    } elseif ($database_type === 'MYSQL' && $mysql_binary_uniquity) {
                        $sql .=
                            "question_description='"
                            . $question_description
                            . "' COLLATE "
                            . (defined('K_MYSQL_QA_BIN_COLLATION') ? K_MYSQL_QA_BIN_COLLATION : 'utf8_bin');
                    } else {
                        $sql .= "question_description='" . $question_description . "'";
                    }

                    $sql .= ' AND question_subject_id=' . $current_subject_id . ' LIMIT 1';
                    if ($r = $normalize_query_result(F_db_query($sql, $db))) {
                        if ($m = $normalize_row(F_db_fetch_array($r))) {
                            /** @var array{question_id:int|string} $m */
                            // get existing question ID
                            $current_question_id = (int) $m['question_id'];
                            break;
                        }
                    } else {
                        F_display_db_error();
                    }

                    $strkeylimit = 0;
                    if ($database_type === 'MYSQL') {
                        // this section is to avoid the problems on MySQL string comparison
                        $maxkey = 240;
                        $strkeylimit = min($maxkey, strlen($question_description));
                        $stop = $maxkey / 3;
                        while (
                            in_array(
                                md5(strtolower(substr($current_subject_id . $question_description, 0, $strkeylimit))),
                                $questionhash,
                            )
                            && $stop > 0
                        ) {
                            // a similar question was already imported, so we change it a little bit to avoid duplicate keys
                            $question_description = '_' . $question_description;
                            $strkeylimit = min($maxkey, $strkeylimit + 1);
                            --$stop; // variable used to avoid infinite loop
                        }

                        if (f_legacy_int_equals($stop, 0)) {
                            F_print_error('ERROR', 'Unable to get unique question ID');
                            return null;
                        }
                    }

                    $sql = 'START TRANSACTION';
                    if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                        F_display_db_error();
                    }

                    // insert question
                    $sql =
                        'INSERT INTO '
                        . K_TABLE_QUESTIONS
                        . ' (
					question_subject_id,
					question_description,
					question_explanation,
					question_type,
					question_difficulty,
					question_enabled,
					question_position,
					question_timer,
					question_fullscreen,
					question_inline_answers,
					question_auto_next,
					question_shuffle_answers
					) VALUES (
					'
                        . $current_subject_id
                        . ',
					\''
                        . $question_description
                        . '\',
					'
                        . $question_explanation
                        . ',
					\''
                        . (string) $question_type
                        . '\',
					\''
                        . $question_difficulty
                        . '\',
					\''
                        . $question_enabled
                        . '\',
					'
                        . $question_position
                        . ',
					\''
                        . $question_timer
                        . '\',
					\''
                        . $question_fullscreen
                        . '\',
					\''
                        . $question_inline_answers
                        . '\',
					\''
                        . $question_auto_next
                        . '\',
					\''
                        . $question_shuffle_answers
                        . '\'
					)';
                    if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                        F_display_db_error(false);
                    } else {
                        // get new question ID
                        $current_question_id = F_db_insert_id($db, K_TABLE_QUESTIONS, 'question_id');
                        if ($database_type === 'MYSQL') {
                            $questionhash[] = md5(strtolower(substr(
                                $current_subject_id . $question_description,
                                0,
                                $strkeylimit,
                            )));
                        }
                    }

                    $sql = 'COMMIT';
                    if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                        F_display_db_error();
                    }

                    break;
            case 'A':
                // ANSWER
                    $current_answer_id = 0;
                    if ($current_module_id === 0 || $current_subject_id === 0 || $current_question_id === 0) {
                        return null;
                    }

                    if (!isset($qdata[4])) {
                        break;
                    }

                    $answer_enabled = (int) $qdata[1];
                    $answer_description = F_escape_sql($db, f_tsv_to_text($qdata[2]), false);
                    $answer_explanation = f_empty_to_null(f_tsv_to_text($qdata[3]));
                    $answer_isright = (int) $qdata[4];
                    $answer_position = isset($qdata[5]) ? f_zero_to_null($qdata[5]) : f_zero_to_null(0);
                    /** @var string $database_type */
                    $database_type = K_DATABASE_TYPE;
                    /** @var bool $mysql_binary_uniquity */
                    $mysql_binary_uniquity = K_MYSQL_QA_BIN_UNIQUITY;

                    $answer_keyboard_key = isset($qdata[6])
                        ? f_empty_to_null(f_tsv_to_text($qdata[6]))
                        : f_empty_to_null('');
                    $answer_weight = isset($qdata[7]) && $qdata[7] !== ''
                        ? (string) max(0, min(100, (int) $qdata[7]))
                        : 'NULL';

                    // check if this answer already exist
                    $sql = 'SELECT answer_id
					FROM ' . K_TABLE_ANSWERS . '
					WHERE ';
                    if (f_legacy_literal_equals($database_type, 'ORACLE')) {
                        $sql .= "dbms_lob.instr(answer_description, '" . $answer_description . "',1,1)>0";
                    } elseif ($database_type === 'MYSQL' && $mysql_binary_uniquity) {
                        $sql .=
                            "answer_description='"
                            . $answer_description
                            . "' COLLATE "
                            . (defined('K_MYSQL_QA_BIN_COLLATION') ? K_MYSQL_QA_BIN_COLLATION : 'utf8_bin');
                    } else {
                        $sql .= "answer_description='" . $answer_description . "'";
                    }

                    $sql .= ' AND answer_question_id=' . $current_question_id . ' LIMIT 1';
                    if ($r = $normalize_query_result(F_db_query($sql, $db))) {
                        if ($m = $normalize_row(F_db_fetch_array($r))) {
                            /** @var array{answer_id:int|string} $m */
                            // get existing subject ID
                            $current_answer_id = $m['answer_id'];
                        } else {
                            $sql = 'START TRANSACTION';
                            if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                                F_display_db_error();
                            }

                            $sql =
                                'INSERT INTO '
                                . K_TABLE_ANSWERS
                                . ' (
							answer_question_id,
							answer_description,
							answer_explanation,
							answer_isright,
								answer_enabled,
								answer_position,
								answer_keyboard_key,
								answer_weight
							) VALUES (
							'
                                . $current_question_id
                                . ',
							\''
                                . $answer_description
                                . '\',
							'
                                . $answer_explanation
                                . ',
							\''
                                . $answer_isright
                                . '\',
							\''
                                . $answer_enabled
                                . '\',
							'
                                . $answer_position
                                . ',
							'
                                . $answer_keyboard_key
                                . ',
								'
                                . $answer_weight
                                . '
								)';
                            if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                                F_display_db_error(false);
                                $normalize_query_result(F_db_query('ROLLBACK', $db));
                            } else {
                                // get new answer ID
                                $current_answer_id = F_db_insert_id($db, K_TABLE_ANSWERS, 'answer_id');
                            }

                            $sql = 'COMMIT';
                            if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
                                F_display_db_error();
                            }
                        }
                    } else {
                        F_display_db_error();
                    }

                    break;
        } // end of switch
    }

    // end of while
    return true;
}
