<?php

//============================================================+
// File name   : tce_edit_answer.php
// Begin       : 2004-04-27
// Last Update : 2023-11-30
//
// Description : Edit answers.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display form to edit exam answers.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2004-04-27
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_ANSWERS;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     a_meta_charset:string,h_add:string,h_answer:string,h_answer_isright:string,h_answer_keyboard_key:string,
 *     h_cancel:string,h_clear:string,h_delete:string,h_enabled:string,h_explanation:string,h_position:string,
 *     h_preview:string,h_question:string,h_subject:string,h_update:string,hp_edit_answer:string,
 *     m_authorization_denied:string,m_delete_confirm:string,m_deleted:string,m_disabled_vs_deleted:string,
 *     m_duplicate_answer:string,m_form_missing_fields:string,m_update_restrict:string,m_updated:string,
 *     t_answers_editor:string,t_questions_editor:string,t_questions_list:string,w_add:string,w_answer:string,
 *     w_cancel:string,w_clear:string,w_confirm:string,w_delete:string,w_description:string,w_disabled:string,
 *     w_enabled:string,w_explanation:string,w_hide:string,w_keyboard_key:string,w_list:string,w_module:string,
 *     w_position:string,w_preview:string,w_question:string,w_record_status:string,w_right:string,w_show:string,
 *     w_subject:string,w_update:string
 * } $l
 */
/** @var mixed $db */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;

$thispage_title = $l['t_answers_editor'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_tmf_question.php';
require_once '../code/tce_functions_tcecode_editor.php';
require_once '../../shared/code/tce_functions_auth_sql.php';

$formstatus = f_tce_edit_answer_bool($formstatus ?? false);
$menu_mode = f_tce_edit_answer_string($menu_mode ?? '');

// upload multimedia files
$uploadedfile = [];
for ($id = 0; $id < 2; ++$id) {
    $userfile = $_FILES['userfile' . $id] ?? null;
    if (isset($_POST['sendfile' . $id]) && is_array($userfile) && !empty($userfile['name'])) {
        require_once '../code/tce_functions_upload.php';
        $uploadedfile["'" . $id . "'"] = f_upload_file('userfile' . $id, K_PATH_CACHE);
    }
}

// comma separated list of required fields
$_REQUEST['ff_required'] = 'answer_description';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_description'], ENT_COMPAT, $l['a_meta_charset']);
// set default values
$subject_module_id = isset($_REQUEST['subject_module_id']) ? (int) $_REQUEST['subject_module_id'] : 0;

$question_subject_id = isset($_REQUEST['question_subject_id']) ? (int) $_REQUEST['question_subject_id'] : 0;

$answer_id = isset($_REQUEST['answer_id']) ? (int) $_REQUEST['answer_id'] : 0;

$answer_list_firstrow = isset($_REQUEST['firstrow']) ? max(0, (int) $_REQUEST['firstrow']) : 0;

if (!isset($_REQUEST['answer_isright']) || empty($_REQUEST['answer_isright'])) {
    $answer_isright = false;
} else {
    $answer_isright = f_get_boolean($_REQUEST['answer_isright']);
}

if (!isset($_REQUEST['answer_enabled']) || empty($_REQUEST['answer_enabled'])) {
    $answer_enabled = false;
} else {
    $answer_enabled = f_get_boolean($_REQUEST['answer_enabled']);
}

if (isset($_REQUEST['changemodule']) && f_tce_edit_answer_is_positive($_REQUEST['changemodule'])) {
    $changemodule = 1;
} elseif (isset($_REQUEST['selectmodule'])) {
    $changemodule = 1;
} else {
    $changemodule = 0;
}

if (isset($_REQUEST['changesubject']) && f_tce_edit_answer_is_positive($_REQUEST['changesubject'])) {
    $changesubject = 1;
} elseif (isset($_REQUEST['selectsubject'])) {
    $changesubject = 1;
} else {
    $changesubject = 0;
}

if (isset($_REQUEST['changecategory']) && f_tce_edit_answer_is_positive($_REQUEST['changecategory'])) {
    $changecategory = 1;
} elseif (isset($_REQUEST['selectcategory'])) {
    $changecategory = 1;
} else {
    $changecategory = 0;
}

if (!isset($_REQUEST['answer_position']) || empty($_REQUEST['answer_position'])) {
    $answer_position = 0;
} else {
    $answer_position = (int) $_REQUEST['answer_position'];
}

if (!isset($_REQUEST['max_position']) || empty($_REQUEST['max_position'])) {
    $max_position = 0;
} else {
    $max_position = (int) $_REQUEST['max_position'];
}

$prev_answer_position = isset($_REQUEST['prev_answer_position']) ? (int) $_REQUEST['prev_answer_position'] : 0;

$subject_id = isset($_REQUEST['subject_id']) ? (int) $_REQUEST['subject_id'] : 0;

$answer_question_id = isset($_REQUEST['answer_question_id']) ? (int) $_REQUEST['answer_question_id'] : 0;
$answer_description = '';

$matching_reuse_positions = false;
if ($answer_question_id > 0) {
    $question_options_sql = 'SELECT question_type,question_description FROM ' . K_TABLE_QUESTIONS
        . ' WHERE question_id=' . $answer_question_id . ' LIMIT 1';
    if ($question_options_result = f_tce_edit_answer_query($question_options_sql, $db)) {
        if (($question_options_row = f_tce_edit_answer_question_options_row(F_db_fetch_array($question_options_result))) !== null) {
            $matching_reuse_positions = (int) $question_options_row['question_type'] === 5
                && F_tmf_question_options(
                    $question_options_row['question_description'],
                )['matching_reuse_positions'];
        }
    } else {
        F_display_db_error();
    }
}

$answer_keyboard_key = !isset($_REQUEST['answer_keyboard_key']) || empty($_REQUEST['answer_keyboard_key'])
    ? ''
    : (int) $_REQUEST['answer_keyboard_key'];

$answer_weight = isset($_REQUEST['answer_weight']) && $_REQUEST['answer_weight'] !== ''
    ? max(0, min(100, (int) $_REQUEST['answer_weight']))
    : null;

if (isset($_REQUEST['answer_description'])) {
    $answer_description = f_tce_edit_answer_string(utrim(f_tce_edit_answer_string($_REQUEST['answer_description'])));
    if (function_exists('normalizer_normalize')) {
        // normalize UTF-8 string based on settings
        $answer_description = f_tce_edit_answer_string(f_utf8_normalizer($answer_description, K_UTF8_NORMALIZATION_MODE));
    }
}

$answer_explanation = isset($_REQUEST['answer_explanation'])
    ? f_tce_edit_answer_string(utrim(f_tce_edit_answer_string($_REQUEST['answer_explanation'])))
    : '';

$qtype = ['S', 'M', 'T', 'O', 'C']; // question types

// check user's authorization
if ($answer_id > 0) {
    $sql =
        'SELECT subject_module_id,question_subject_id,answer_question_id
		FROM '
        . K_TABLE_SUBJECTS
        . ', '
        . K_TABLE_QUESTIONS
        . ', '
        . K_TABLE_ANSWERS
        . '
		WHERE subject_id=question_subject_id
			AND question_id=answer_question_id
			AND answer_id='
        . $answer_id
        . '
		LIMIT 1';
    if ($r = f_tce_edit_answer_query($sql, $db)) {
        // check user's authorization for parent module
        if (
            ($m = f_tce_edit_answer_authorization_row(F_db_fetch_array($r)))
            && (
                !f_is_authorized_user(K_TABLE_MODULES, 'module_id', $m['subject_module_id'], 'module_user_id')
                && !f_is_authorized_user(K_TABLE_SUBJECTS, 'subject_id', $m['question_subject_id'], 'subject_user_id')
            )
        ) {
            F_print_error('ERROR', $l['m_authorization_denied'], true);
        }
    } else {
        F_display_db_error();
    }
}

switch ($menu_mode) {
    case 'delete':
            // check if this record is used (test_log)
            if (!F_check_unique(K_TABLE_LOG_ANSWER, 'logansw_answer_id=' . $answer_id . '')) {
                //this record will be only disabled and not deleted because it's used
                $sql = 'UPDATE ' . K_TABLE_ANSWERS . ' SET
				answer_enabled=\'0\'
				WHERE answer_id=' . $answer_id . '';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error();
                }

                F_print_error('WARNING', $l['m_disabled_vs_deleted']);
            } else {
                // ask confirmation
                F_print_error('WARNING', $l['m_delete_confirm']);
                ?>
            <div class="confirmbox">
            <form action="<?php echo
                htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
            ; ?>" method="post" enctype="multipart/form-data" id="form_delete">
            <div>
            <input type="hidden" name="answer_id" id="answer_id" value="<?php echo $answer_id; ?>" />
            <input type="hidden" name="subject_module_id" id="subject_module_id" value="<?php echo
                $subject_module_id
            ; ?>" />
            <input type="hidden" name="question_subject_id" id="question_subject_id" value="<?php echo
                $question_subject_id
            ; ?>" />
            <input type="hidden" name="answer_question_id" id="answer_question_id" value="<?php echo
                $answer_question_id
            ; ?>" />
            <input type="hidden" name="answer_description" id="answer_description" value="<?php echo
                htmlspecialchars($answer_description, ENT_QUOTES, $l['a_meta_charset'])
            ; ?>" />
            <input type="hidden" name="answer_explanation" id="answer_explanation" value="<?php echo
                htmlspecialchars($answer_explanation, ENT_QUOTES, $l['a_meta_charset'])
            ; ?>" />
            <?php

            F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
            F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
            echo f_get_csrf_token_field() . K_NEWLINE;
            ?>
            </div>
            </form>
            </div>
        <?php
            }

            break;

    case 'forcedelete':
            // Delete
            if (($_POST['forcedelete'] ?? '') === $l['w_delete']) { //check if delete button has been pushed (redundant check)
                $sql = 'START TRANSACTION';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    break;
                }

                // get answer position (if defined)
                $sql = 'SELECT answer_position
				FROM ' . K_TABLE_ANSWERS . '
					WHERE answer_id=' . f_tce_edit_answer_string($answer_id) . '
				LIMIT 1';
                if ($r = f_tce_edit_answer_query($sql, $db)) {
                    if (($m = f_tce_edit_answer_position_row(F_db_fetch_array($r))) !== null) {
                        $answer_position = (int) $m['answer_position'];
                    }
                } else {
                    F_display_db_error();
                }

                // delete answer
                $sql = 'DELETE FROM ' . K_TABLE_ANSWERS . ' WHERE answer_id=' . $answer_id . '';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    f_tce_edit_answer_query('ROLLBACK', $db); // rollback transaction
                } else {
                    $answer_id = false;
                    // adjust questions ordering
                    if ($answer_position > 0 && !$matching_reuse_positions) {
                        $sql =
                            'UPDATE '
                            . K_TABLE_ANSWERS
                            . ' SET
						answer_position=answer_position-1
						WHERE answer_question_id='
                            . $answer_question_id
                            . '
							AND answer_position>'
                            . $answer_position
                            . '';
                        if (!($r = f_tce_edit_answer_query($sql, $db))) {
                            F_display_db_error(false);
                            f_tce_edit_answer_query('ROLLBACK', $db); // rollback transaction
                        }
                    }

                    $sql = 'COMMIT';
                    if (!($r = f_tce_edit_answer_query($sql, $db))) {
                        F_display_db_error(false);
                        break;
                    }

                    F_print_error('MESSAGE', $l['m_deleted']);
                }
            }

            break;

    case 'update':
        // Update
            // check if the confirmation chekbox has been selected
            if (!isset($_REQUEST['confirmupdate']) || !f_legacy_int_equals($_REQUEST['confirmupdate'], 1)) {
                F_print_error(
                    'WARNING',
                    $l['m_form_missing_fields'] . ': ' . $l['w_confirm'] . ' &rarr; ' . $l['w_update'],
                );

                break;
            }

            if ($formstatus = F_check_form_fields()) {
                // get previous answer position (if defined)
                $prev_answer_position = 0;
                $sql = 'SELECT answer_position
				FROM ' . K_TABLE_ANSWERS . '
				WHERE answer_id=' . $answer_id . '
				LIMIT 1';
                if ($r = f_tce_edit_answer_query($sql, $db)) {
                    if (($m = f_tce_edit_answer_position_row(F_db_fetch_array($r))) !== null) {
                        $prev_answer_position = (int) $m['answer_position'];
                    }
                } else {
                    F_display_db_error();
                }

                // check referential integrity (NOTE: mysql do not support "ON UPDATE" constraint)
                if (!F_check_unique(K_TABLE_LOG_ANSWER, 'logansw_answer_id=' . $answer_id . '')) {
                    F_print_error('WARNING', $l['m_update_restrict']);
                    // when the answer is disabled, the position is discarded
                    $answer_position = $answer_enabled ? $prev_answer_position : 0;

                    // enable or disable record
                    $sql =
                        'UPDATE '
                        . K_TABLE_ANSWERS
                        . ' SET
					answer_enabled=\''
                        . f_tce_edit_answer_string($answer_enabled)
                        . '\',
					answer_position='
                        . f_zero_to_null($answer_position)
                        . '
					WHERE answer_id='
                        . $answer_id
                        . '';
                    if (!($r = f_tce_edit_answer_query($sql, $db))) {
                        F_display_db_error(false);
                    } else {
                        $strmsg = $l['w_record_status'] . ': ';
                        if ($answer_enabled) {
                            $strmsg .= $l['w_enabled'];
                        } else {
                            $strmsg .= $l['w_disabled'];
                        }

                        F_print_error('MESSAGE', $strmsg);
                    }

                    $formstatus = false;

                    break;
                }

                // check if alternate key is unique
                if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
                    $chksql =
                        "dbms_lob.instr(answer_description,'" . F_escape_sql($db, $answer_description) . "',1,1)>0";
                } elseif (
                    f_legacy_literal_equals(K_DATABASE_TYPE, 'MYSQL')
                    && f_tce_edit_answer_bool(K_MYSQL_QA_BIN_UNIQUITY)
                ) {
                    $chksql =
                        "answer_description='"
                        . F_escape_sql($db, $answer_description)
                        . "' COLLATE "
                        . (defined('K_MYSQL_QA_BIN_COLLATION') ? K_MYSQL_QA_BIN_COLLATION : 'utf8_bin');
                } else {
                    $chksql = "answer_description='" . F_escape_sql($db, $answer_description) . "'";
                }

                if ($answer_position > 0) {
                    $chksql .= ' AND answer_position=' . $answer_position;
                }

                if (!F_check_unique(
                    K_TABLE_ANSWERS,
                    $chksql . ' AND answer_question_id=' . $answer_question_id,
                    'answer_id',
                    $answer_id,
                )) {
                    F_print_error('WARNING', $l['m_duplicate_answer']);
                    $formstatus = false;

                    break;
                }

                $sql = 'START TRANSACTION';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    break;
                }

                // when the answer is disabled, the position is discarded
                if (!$answer_enabled) {
                    $answer_position = 0;
                }

                if ($answer_position > $max_position) {
                    $answer_position = $max_position;
                }

                // arrange positions if necessary
                if (!$matching_reuse_positions && $answer_position !== $prev_answer_position) {
                    if ($answer_position > 0) {
                        if ($prev_answer_position > 0) {
                            // swap positions
                            $sql =
                                'UPDATE '
                                . K_TABLE_ANSWERS
                                . ' SET
							answer_position='
                                . $prev_answer_position
                                . '
							WHERE answer_question_id='
                                . $answer_question_id
                                . '
								AND answer_position='
                                . $answer_position
                                . '';
                        } elseif ($prev_answer_position === 0) {
                            // right shift positions
                            $sql =
                                'UPDATE '
                                . K_TABLE_ANSWERS
                                . ' SET
							answer_position=answer_position+1
							WHERE answer_question_id='
                                . $answer_question_id
                                . '
								AND answer_position>='
                                . $answer_position
                                . '';
                        }
                    } else {
                        // left shift positions
                        $sql =
                            'UPDATE '
                            . K_TABLE_ANSWERS
                            . ' SET
						answer_position=answer_position-1
						WHERE answer_question_id='
                            . $answer_question_id
                            . '
							AND answer_position>'
                            . $prev_answer_position
                            . '';
                    }

                    if (!($r = f_tce_edit_answer_query($sql, $db))) {
                        F_display_db_error(false);
                        f_tce_edit_answer_query('ROLLBACK', $db); // rollback transaction
                    }
                }

                // update field
                $sql =
                    'UPDATE '
                    . K_TABLE_ANSWERS
                    . ' SET
				answer_question_id='
                    . $answer_question_id
                    . ',
				answer_description=\''
                    . F_escape_sql($db, $answer_description)
                    . '\',
				answer_explanation='
                    . f_empty_to_null($answer_explanation)
                    . ',
				answer_isright=\''
                    . (int) $answer_isright
                    . '\',
				answer_enabled=\''
                    . (int) $answer_enabled
                    . '\',
				answer_position='
                    . f_zero_to_null($answer_position)
                    . ',
					answer_keyboard_key='
                    . f_empty_to_null($answer_keyboard_key)
                    . ',
					answer_weight='
                    . ($answer_weight === null ? 'NULL' : (string) $answer_weight)
                    . '
					WHERE answer_id='
                    . $answer_id
                    . '';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    f_tce_edit_answer_query('ROLLBACK', $db); // rollback transaction
                } else {
                    F_print_error('MESSAGE', $l['m_updated']);
                }

                $sql = 'COMMIT';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    break;
                }
            }

            break;

    case 'add':
        // Add
            if ($formstatus = F_check_form_fields()) {
                // check if alternate key is unique
                if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
                    $chksql =
                        "dbms_lob.instr(answer_description,'" . F_escape_sql($db, $answer_description) . "',1,1)>0";
                } elseif (
                    f_legacy_literal_equals(K_DATABASE_TYPE, 'MYSQL')
                    && f_tce_edit_answer_bool(K_MYSQL_QA_BIN_UNIQUITY)
                ) {
                    $chksql =
                        "answer_description='"
                        . F_escape_sql($db, $answer_description)
                        . "' COLLATE "
                        . (defined('K_MYSQL_QA_BIN_COLLATION') ? K_MYSQL_QA_BIN_COLLATION : 'utf8_bin');
                } else {
                    $chksql = "answer_description='" . F_escape_sql($db, $answer_description) . "'";
                }

                if ($answer_position > 0) {
                    $chksql .= ' AND answer_position=' . $answer_position;
                }

                if (!F_check_unique(K_TABLE_ANSWERS, $chksql . ' AND answer_question_id=' . $answer_question_id)) {
                    F_print_error('WARNING', $l['m_duplicate_answer']);
                    $formstatus = false;

                    break;
                }

                $sql = 'START TRANSACTION';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    break;
                }

                // adjust questions ordering
                if ($answer_position > 0 && !$matching_reuse_positions) {
                    $sql =
                        'UPDATE '
                        . K_TABLE_ANSWERS
                        . ' SET
					answer_position=answer_position+1
					WHERE answer_question_id='
                        . $answer_question_id
                        . '
						AND answer_position>='
                        . $answer_position
                        . '';
                    if (!($r = f_tce_edit_answer_query($sql, $db))) {
                        F_display_db_error(false);
                        f_tce_edit_answer_query('ROLLBACK', $db); // rollback transaction
                    }
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
                    . $answer_question_id
                    . ',
				\''
                    . F_escape_sql($db, $answer_description)
                    . '\',
				'
                    . f_empty_to_null($answer_explanation)
                    . ',
				\''
                    . (int) $answer_isright
                    . '\',
				\''
                    . (int) $answer_enabled
                    . '\',
				'
                    . f_zero_to_null($answer_position)
                    . ',
				'
                    . f_empty_to_null($answer_keyboard_key)
                    . ',
					'
                    . ($answer_weight === null ? 'NULL' : (string) $answer_weight)
                    . '
					)';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    f_tce_edit_answer_query('ROLLBACK', $db); // rollback transaction
                } else {
                    $answer_id = F_db_insert_id($db, K_TABLE_ANSWERS, 'answer_id');
                }

                $sql = 'COMMIT';
                if (!($r = f_tce_edit_answer_query($sql, $db))) {
                    F_display_db_error(false);
                    break;
                }
            }

            break;

    case 'clear':
        // Clear form fields
            $answer_description = '';
            $answer_explanation = '';
            $answer_isright = false;
            $answer_enabled = true;
            $answer_position = 0;
            $answer_keyboard_key = '';
            $answer_weight = null;
            break;

    default:
            break;
} //end of switch

// select default module/subject (if not specified)
if ($subject_module_id <= 0) {
    $sql = F_select_modules_sql() . ' LIMIT 1';
    if ($r = f_tce_edit_answer_query($sql, $db)) {
        if (($m = f_tce_edit_answer_module_id_row(F_db_fetch_array($r))) !== null) {
            $default_module_id = $m['module_id'];
            $subject_module_id = (int) $default_module_id;
        } else {
            $subject_module_id = 0;
        }
    } else {
        F_display_db_error();
    }
}

// select default subject
if ($changemodule > 0 || $question_subject_id <= 0) {
    $sql = F_select_subjects_sql('subject_module_id=' . $subject_module_id . '') . ' LIMIT 1';
    if ($r = f_tce_edit_answer_query($sql, $db)) {
        if (($m = f_tce_edit_answer_subject_id_row(F_db_fetch_array($r))) !== null) {
            $default_subject_id = $m['subject_id'];
            $question_subject_id = (int) $default_subject_id;
        } else {
            $question_subject_id = 0;
        }
    } else {
        F_display_db_error();
    }
}

// select default question
if ($changesubject > 0 || $changemodule > 0 || $answer_question_id <= 0) {
    $sql = 'SELECT question_id
		FROM ' . K_TABLE_QUESTIONS . '
		WHERE question_subject_id=' . $question_subject_id . '
		ORDER BY ';
    if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
        $sql .= 'CAST(question_description as varchar2(100))';
    } else {
        $sql .= 'question_description LIMIT 1';
    }

    if ($r = f_tce_edit_answer_query($sql, $db)) {
        if (($m = f_tce_edit_answer_question_id_row(F_db_fetch_array($r))) !== null) {
            $default_question_id = $m['question_id'];
            $answer_question_id = (int) $default_question_id;
        } else {
            $answer_question_id = 0;
        }
    } else {
        F_display_db_error();
    }
}

// --- Initialize variables
if ($formstatus && $menu_mode !== 'clear') {
    if ($changemodule > 0 || $changesubject > 0 || $changecategory > 0 || $answer_id === 0) {
        $answer_id = 0;
        $answer_description = '';
        $answer_explanation = '';
        $answer_isright = false;
        $answer_enabled = true;
        $answer_position = 0;
        $answer_keyboard_key = '';
        $answer_weight = null;
    } else {
        $sql = 'SELECT *
					FROM ' . K_TABLE_ANSWERS . '
					WHERE answer_id=' . f_tce_edit_answer_string($answer_id) . '
				LIMIT 1';
        if ($r = f_tce_edit_answer_query($sql, $db)) {
            if (($m = f_tce_edit_answer_record_row(F_db_fetch_array($r))) !== null) {
                $stored_answer_id = $m['answer_id'];
                $stored_question_id = $m['answer_question_id'];
                $stored_answer_position = $m['answer_position'];
                $stored_keyboard_key = $m['answer_keyboard_key'];
                $answer_id = (int) $stored_answer_id;
                $answer_question_id = (int) $stored_question_id;
                $answer_description = f_tce_edit_answer_string($m['answer_description']);
                $answer_explanation = f_tce_edit_answer_string($m['answer_explanation']);
                $answer_isright = f_get_boolean($m['answer_isright']);
                $answer_enabled = f_get_boolean($m['answer_enabled']);
                $answer_position = (int) $stored_answer_position;
                $answer_keyboard_key = $stored_keyboard_key === null ? '' : (int) $stored_keyboard_key;
                $answer_weight = $m['answer_weight'] === null ? null : (int) $m['answer_weight'];
            } else {
                $answer_description = '';
                $answer_explanation = '';
                $answer_isright = false;
                $answer_enabled = true;
                $answer_position = 0;
                $answer_keyboard_key = '';
                $answer_weight = null;
            }
        } else {
            F_display_db_error();
        }
    }
}

if ($subject_module_id <= 0 || $question_subject_id <= 0 || $answer_question_id <= 0) {
    echo '<div class="container">' . K_NEWLINE;
    echo
        '<p><a href="tce_edit_question.php" title="'
            . $l['t_questions_editor']
            . '" class="xmlbutton">&lt; '
            . $l['t_questions_editor']
            . '</a></p>'
            . K_NEWLINE
    ;
    echo '<div class="pagehelp">' . $l['hp_edit_answer'] . '</div>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
    require_once '../code/tce_page_footer.php';
    exit();
}

echo '<script src="' . K_PATH_SHARED_JSCRIPTS . 'inserttag.js" type="text/javascript"></script>' . K_NEWLINE;

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_answereditor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="subject_module_id">' . $l['w_module'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="changemodule" id="changemodule" value="" />' . K_NEWLINE;
echo
    '<select name="subject_module_id" id="subject_module_id" onchange="document.getElementById(\'form_answereditor\').changemodule.value=1; document.getElementById(\'form_answereditor\').submit();" title="'
        . $l['w_module']
        . '">'
        . K_NEWLINE
;
$sql = F_select_modules_sql();
if ($r = f_tce_edit_answer_query($sql, $db)) {
    $countitem = 1;
    while (($m = f_tce_edit_answer_module_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['module_id'] . '"';
        $listed_module_id = $m['module_id'];
        if ((int) $listed_module_id === $subject_module_id) {
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
    }

    if (f_tce_edit_answer_is_first_item($countitem)) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectmodule');

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="question_subject_id">' . $l['w_subject'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="changesubject" id="changesubject" value="" />' . K_NEWLINE;
echo
    '<select name="question_subject_id" id="question_subject_id" onchange="document.getElementById(\'form_answereditor\').changesubject.value=1; document.getElementById(\'form_answereditor\').submit();" title="'
        . $l['h_subject']
        . '">'
        . K_NEWLINE
;
$countitem = 1; //number of already inserted answers
$sql = F_select_subjects_sql('subject_module_id=' . $subject_module_id);
if ($r = f_tce_edit_answer_query($sql, $db)) {
    while (($m = f_tce_edit_answer_subject_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['subject_id'] . '"';
        $listed_subject_id = $m['subject_id'];
        if ((int) $listed_subject_id === $question_subject_id) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ';
        if (f_get_boolean($m['subject_enabled'])) {
            echo '+';
        } else {
            echo '-';
        }

        echo
            ' '
                . htmlspecialchars(f_remove_tcecode($m['subject_name']), ENT_NOQUOTES, $l['a_meta_charset'])
                . '</option>'
                . K_NEWLINE
        ;
        ++$countitem;
    }

    if (f_tce_edit_answer_is_first_item($countitem)) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectsubject');

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="answer_question_id">' . $l['w_question'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="changecategory" id="changecategory" value="" />' . K_NEWLINE;
echo
    '<select name="answer_question_id" id="answer_question_id" onchange="document.getElementById(\'form_answereditor\').changecategory.value=1; document.getElementById(\'form_answereditor\').submit()" title="'
        . $l['h_question']
        . '">'
        . K_NEWLINE
;
$sql =
    'SELECT * FROM '
    . K_TABLE_QUESTIONS
    . ' WHERE question_subject_id='
    . (int) $question_subject_id
    . ' ORDER BY question_enabled DESC, question_position,';
if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
    $sql .= 'CAST(question_description as varchar2(100))';
} else {
    $sql .= 'question_description';
}

if ($r = f_tce_edit_answer_query($sql, $db)) {
    $countitem = 1;
    while (($m = f_tce_edit_answer_question_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['question_id'] . '"';
        $listed_question_id = $m['question_id'];
        if ((int) $listed_question_id === $answer_question_id) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ';
        if (!f_get_boolean($m['question_enabled'])) {
            echo '-';
        } else {
            echo f_tce_edit_answer_question_type_label($qtype, $m['question_type']);
        }

        echo
            ' '
                . htmlspecialchars(
                    f_substr_utf8(f_remove_tcecode($m['question_description']), 0, K_SELECT_SUBSTRING),
                    ENT_NOQUOTES,
                    $l['a_meta_charset'],
                )
                . '</option>'
                . K_NEWLINE
        ;
        ++$countitem;
    }

    if (f_tce_edit_answer_is_first_item($countitem)) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
} else {
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectcategory');

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="answer_id">' . $l['w_answer'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="answer_id" id="answer_id" onchange="document.getElementById(\'form_answereditor\').submit()" title="'
        . $l['h_answer']
        . '">'
        . K_NEWLINE
;
echo '<option value="0" style="background-color:#009900;color:white;"';
if ($answer_id === 0) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
$sql =
    'SELECT * FROM '
    . K_TABLE_ANSWERS
    . ' WHERE answer_question_id='
    . (int) $answer_question_id
    . ' ORDER BY answer_position, answer_enabled DESC, answer_isright DESC,';
if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
    $sql .= 'CAST(answer_description as varchar2(100))';
} else {
    $sql .= 'answer_description';
}

if ($r = f_tce_edit_answer_query($sql, $db)) {
    $countitem = 1;
    while (($m = f_tce_edit_answer_list_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['answer_id'] . '"';
        $listed_answer_id = $m['answer_id'];
        if ((int) $listed_answer_id === $answer_id) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ';
        if (!f_get_boolean($m['answer_enabled'])) {
            echo '-';
        } elseif (f_get_boolean($m['answer_isright'])) {
            echo 'T';
        } else {
            echo 'F';
        }

        echo
            ' '
                . htmlspecialchars(
                    f_substr_utf8(f_remove_tcecode($m['answer_description']), 0, K_SELECT_SUBSTRING),
                    ENT_NOQUOTES,
                    $l['a_meta_charset'],
                )
                . '</option>'
                . K_NEWLINE
        ;
        ++$countitem;
    }

    if (f_tce_edit_answer_is_first_item($countitem)) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
} else {
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="answer_description">' . $l['w_answer'] . '</label>' . K_NEWLINE;
echo '<br />' . K_NEWLINE;

echo get_rich_content_editor_button('answer_description') . K_NEWLINE;

echo '</span>' . K_NEWLINE;
echo '<span class="formw" style="border:1px solid #808080;">' . K_NEWLINE;
echo
    '<textarea cols="50" rows="10" name="answer_description" id="answer_description" aria-required="true" title="'
        . $l['h_answer']
        . '"'
;

echo '>' . htmlspecialchars($answer_description, ENT_NOQUOTES, $l['a_meta_charset']) . '</textarea>' . K_NEWLINE;
echo '<br />' . K_NEWLINE;
echo '<div class="tcecode-toolbar">';
echo tcecode_editor_tag_buttons('form_answereditor', 'answer_description');
echo '</div>';
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

if (f_tce_edit_answer_bool(K_ENABLE_ANSWER_EXPLANATION)) {
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<label for="answer_explanation">' . $l['w_explanation'] . '</label>' . K_NEWLINE;
    echo '<br />' . K_NEWLINE;
    $showexplanationarea = "javascript:if(document.getElementById('explanationarea').style.display=='none'){document.getElementById('explanationarea').style.display='block';document.getElementById('showexplanationarea').style.display='none';document.getElementById('hideexplanationarea').style.display='block';}; return false;";
    echo
        '<span id="showexplanationarea"><button type="button" class="xmlbutton" onclick="'
            . $showexplanationarea
            . '" title="'
            . $l['w_show']
            . '">'
            . $l['w_show']
            . ' &rarr;</button></span>'
    ;
    $hideexplanationarea = "javascript:if(document.getElementById('explanationarea').style.display=='block'){document.getElementById('explanationarea').style.display='none';document.getElementById('showexplanationarea').style.display='block';document.getElementById('hideexplanationarea').style.display='none';}; return false;";
    echo '<span id="hideexplanationarea" style="display:none;">';
    echo
        get_rich_content_editor_button('answer_explanation')
            . K_NEWLINE
    ;
    echo
        '<button type="button" class="xmlbutton" onclick="'
            . $hideexplanationarea
            . '" title="'
            . $l['w_hide']
            . '">'
            . $l['w_hide']
            . '</button> '
    ;
    echo '</span>';
    echo '</span>' . K_NEWLINE;
    echo '<span id="explanationarea" class="formw" style="display:none;border:1px solid #808080;">' . K_NEWLINE;
    echo
        '<textarea cols="50" rows="10" name="answer_explanation" id="answer_explanation" title="'
            . $l['h_explanation']
            . '"'
    ;

    echo '>' . htmlspecialchars($answer_explanation, ENT_NOQUOTES, $l['a_meta_charset']) . '</textarea>' . K_NEWLINE;
    echo '<br />' . K_NEWLINE;
    echo '<div class="tcecode-toolbar">';
    echo tcecode_editor_tag_buttons('form_answereditor', 'answer_explanation');
    echo '</div>';
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}

echo get_form_row_checkbox('answer_isright', $l['w_right'], $l['h_answer_isright'], '', 1, $answer_isright, false, '');
echo get_form_row_text_input(
    'answer_weight',
    'Вес ответа (%)',
    'Доля максимального балла за выбор или точное совпадение; пусто — стандартное оценивание',
    '0–100',
    $answer_weight,
    '^([0-9]{1,3})?$',
    3,
    false,
    false,
    false,
    '',
    false,
    '',
    'number',
);
echo get_form_row_checkbox('answer_enabled', $l['w_enabled'], $l['h_enabled'], '', 1, $answer_enabled, false, '');

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="answer_position">' . $l['w_position'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<select name="answer_position" id="answer_position" title="' . $l['h_position'] . '">' . K_NEWLINE;
$matching_position_limit = 0;
if ((int) $answer_id > 0) {
    $max_position =
        1
        + (int) F_count_rows(
            K_TABLE_ANSWERS,
            'WHERE answer_question_id='
            . $answer_question_id
            . ' AND answer_position>0 AND answer_id<>'
            . f_tce_edit_answer_string($answer_id)
            . '',
        );
} else {
    $max_position = 0;
}
$matching_question_sql = 'SELECT question_type,question_description FROM ' . K_TABLE_QUESTIONS
    . ' WHERE question_id=' . (int) $answer_question_id . ' LIMIT 1';
if ($matching_question_result = f_tce_edit_answer_query($matching_question_sql, $db)) {
    if (($matching_question = f_tce_edit_answer_question_options_row(F_db_fetch_array($matching_question_result))) !== null) {
        $configured_positions = (int) F_tmf_question_options(
            $matching_question['question_description'],
        )['matching_positions'];
        if ((int) $matching_question['question_type'] === 5 && $configured_positions > 0) {
            $matching_position_limit = (int) $configured_positions;
            $max_position = max($max_position, $matching_position_limit);
        } elseif ((int) $matching_question['question_type'] === 5 && $matching_reuse_positions) {
            $maximum_position_sql = 'SELECT MAX(answer_position) AS maximum_position FROM '
                . K_TABLE_ANSWERS . ' WHERE answer_question_id=' . (int) $answer_question_id
                . ' AND answer_enabled=\'1\'';
            if ($maximum_position_result = f_tce_edit_answer_query($maximum_position_sql, $db)) {
                if (($maximum_position_row = f_tce_edit_answer_maximum_position_row(
                    F_db_fetch_array($maximum_position_result),
                )) !== null) {
                    $max_position = max($max_position, (int) $maximum_position_row['maximum_position']);
                }
            } else {
                F_display_db_error();
            }
        }
    }
}

echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
for ($pos = 1; $pos <= $max_position; ++$pos) {
    echo '<option value="' . $pos . '"';
    if ($pos === $answer_position) {
        echo ' selected="selected"';
    }

    echo '>' . $pos . '</option>' . K_NEWLINE;
}

if ($matching_position_limit === 0) {
    echo
        '<option value="' . ($max_position + 1) . '" style="color:#ff0000">'
        . ($max_position + 1)
        . '</option>'
        . K_NEWLINE
    ;
}
echo '</select>' . K_NEWLINE;
echo '<input type="hidden" name="max_position" id="max_position" value="' . $max_position . '" />' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="answer_keyboard_key">' . $l['w_keyboard_key'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="answer_keyboard_key" id="answer_keyboard_key" title="'
        . $l['h_answer_keyboard_key']
        . '">'
        . K_NEWLINE
;
echo '<option value="">&nbsp;</option>' . K_NEWLINE;
for ($ascii = 32; $ascii <= 126; ++$ascii) {
    echo '<option value="' . $ascii . '"';
    if ($ascii === $answer_keyboard_key) {
        echo ' selected="selected"';
    }

    echo '>';
    if ($ascii === 32) {
        echo 'SP';
    } else {
        echo htmlspecialchars(chr($ascii), ENT_NOQUOTES, $l['a_meta_charset']);
    }

    echo '</option>' . K_NEWLINE;
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;

// show buttons by case

if ((int) $answer_id > 0) {
    echo '<span style="background-color:#999999;">';
    echo
        '<input type="checkbox" name="confirmupdate" id="confirmupdate" value="1" title="'
            . $l['w_confirm']
            . ' &rarr; '
            . $l['w_update']
            . '" aria-label="'
            . $l['w_confirm']
            . ' &rarr; '
            . $l['w_update']
            . '" />'
    ;
    F_submit_button('update', $l['w_update'], $l['h_update']);
    echo '</span>';
    F_submit_button('add', $l['w_add'], $l['h_add']);
    F_submit_button('delete', $l['w_delete'], $l['h_delete']);
} else {
    F_submit_button('add', $l['w_add'], $l['h_add']);
}

F_submit_button('clear', $l['w_clear'], $l['h_clear']);

echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="left">' . K_NEWLINE;
echo '&nbsp;' . K_NEWLINE;

if ($answer_question_id > 0) {
    echo
        '<a href="tce_edit_question.php?subject_module_id='
            . $subject_module_id
            . '&amp;question_subject_id='
            . $question_subject_id
            . '&amp;question_id='
            . $answer_question_id
            . '&amp;firstrow='
            . $answer_list_firstrow
            . '" title="'
            . $l['t_questions_editor']
            . '" class="xmlbutton">&lt; '
            . $l['t_questions_editor']
            . '</a>'
    ;
}

if ($question_subject_id > 0) {
    $answer_list_url =
        'tce_show_all_questions.php?subject_module_id='
        . $subject_module_id
        . '&amp;subject_id='
        . $question_subject_id
        . '&amp;submitted=1&amp;firstrow='
        . $answer_list_firstrow;
    if ($answer_question_id > 0) {
        $answer_list_url .= '#qid_' . $answer_question_id;
    }

    echo
        '<a href="'
        . $answer_list_url
        . '" title="'
        . $l['t_questions_list']
        . '" class="xmlbutton question-list-return">&lt; '
        . $l['w_list']
        . '</a>'
    ;
}

echo '</span>' . K_NEWLINE;
echo '&nbsp;' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo '<div class="rowl" title="' . $l['h_preview'] . '">' . K_NEWLINE;
echo $l['w_preview'];
echo '<div class="preview">' . K_NEWLINE;

echo F_decode_tcecode($answer_description);

echo '&nbsp;' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_answer'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_edit_answer_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function f_tce_edit_answer_bool(mixed $value): bool
{
    if (is_array($value)) {
        return $value !== [];
    }
    if (is_object($value) || is_resource($value)) {
        return true;
    }
    return is_bool($value) || is_int($value) || is_float($value) || is_string($value)
        ? (bool) $value
        : false;
}

function f_tce_edit_answer_is_first_item(int $value): bool
{
    return $value === 1;
}

/** Preserve legacy positive-value comparisons. */
function f_tce_edit_answer_is_positive(mixed $value): bool
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
function f_tce_edit_answer_query(string $sql, mixed $db): mixed
{
    return f_tce_edit_answer_query_result(F_db_query($sql, $db));
}

/** @return object|resource|bool */
function f_tce_edit_answer_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array{question_type:int|string,question_description:string}|null */
function f_tce_edit_answer_question_options_row(mixed $row): ?array
{
    /** @var array{question_type:int|string,question_description:string}|null $row */
    return is_array($row) ? $row : null;
}

/** @return array{subject_module_id:int|string,question_subject_id:int|string,answer_question_id:int|string}|null */
function f_tce_edit_answer_authorization_row(mixed $row): ?array
{
    /** @var array{subject_module_id:int|string,question_subject_id:int|string,answer_question_id:int|string}|null $row */
    return is_array($row) ? $row : null;
}

/** @return array{answer_position:int|string|null}|null */
function f_tce_edit_answer_position_row(mixed $row): ?array
{
    /** @var array{answer_position:int|string|null}|null $row */
    return is_array($row) ? $row : null;
}

/** @return array{module_id:int|string}|null */
function f_tce_edit_answer_module_id_row(mixed $row): ?array
{
    /** @var array{module_id:int|string}|null $row */
    return is_array($row) ? $row : null;
}

/** @return array{subject_id:int|string}|null */
function f_tce_edit_answer_subject_id_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /** @var array{subject_id:int|string} $row */
    return $row;
}

/** @return array{question_id:int|string}|null */
function f_tce_edit_answer_question_id_row(mixed $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    /** @var array{question_id:int|string} $row */
    return $row;
}

/**
 * @return array{
 *     answer_id:int|string,answer_question_id:int|string,answer_description:string,answer_explanation:string|null,
 *     answer_isright:mixed,answer_enabled:mixed,answer_position:int|string|null,
 *     answer_keyboard_key:int|string|null,answer_weight:int|string|null
 * }|null
 */
function f_tce_edit_answer_record_row(mixed $row): ?array
{
    /**
     * @var array{
     *     answer_id:int|string,answer_question_id:int|string,answer_description:string,answer_explanation:string|null,
     *     answer_isright:mixed,answer_enabled:mixed,answer_position:int|string|null,
     *     answer_keyboard_key:int|string|null,answer_weight:int|string|null
     * }|null $row
     */
    return is_array($row) ? $row : null;
}

/** @return array{module_id:int|string,module_enabled:mixed,module_name:string}|null */
function f_tce_edit_answer_module_row(mixed $row): ?array
{
    /** @var array{module_id:int|string,module_enabled:mixed,module_name:string}|null $row */
    return is_array($row) ? $row : null;
}

/** @return array{subject_id:int|string,subject_enabled:mixed,subject_name:string}|null */
function f_tce_edit_answer_subject_row(mixed $row): ?array
{
    /** @var array{subject_id:int|string,subject_enabled:mixed,subject_name:string}|null $row */
    return is_array($row) ? $row : null;
}

/**
 * @return array{
 *     question_id:int|string,question_enabled:mixed,question_type:int|string,question_description:string
 * }|null
 */
function f_tce_edit_answer_question_row(mixed $row): ?array
{
    /**
     * @var array{
     *     question_id:int|string,question_enabled:mixed,question_type:int|string,question_description:string
     * }|null $row
     */
    return is_array($row) ? $row : null;
}

/**
 * @return array{
 *     answer_id:int|string,answer_enabled:mixed,answer_isright:mixed,answer_description:string
 * }|null
 */
function f_tce_edit_answer_list_row(mixed $row): ?array
{
    /**
     * @var array{
     *     answer_id:int|string,answer_enabled:mixed,answer_isright:mixed,answer_description:string
     * }|null $row
     */
    return is_array($row) ? $row : null;
}

/** @return array{maximum_position:int|string|null}|null */
function f_tce_edit_answer_maximum_position_row(mixed $row): ?array
{
    /** @var array{maximum_position:int|string|null}|null $row */
    return is_array($row) ? $row : null;
}

/** @param list{string,string,string,string,string} $types */
function f_tce_edit_answer_question_type_label(array $types, mixed $question_type): string
{
    $index = (int) $question_type - 1;
    return f_tce_edit_answer_string($types[$index] ?? null);
}
