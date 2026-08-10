<?php

//============================================================+
// File name   : tce_edit_test.php
// Begin       : 2004-04-27
// Last Update : 2023-11-30
//
// Description : Edit Tests
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Edit test.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2004-04-27
 */

require_once '../config/tce_config.php';

// explicit reads of POST inputs (formerly provided by register-globals emulation)
$forcedelete = $_POST['forcedelete'] ?? '';
$new_test_password = isset($_POST['new_test_password']) && is_string($_POST['new_test_password'])
    ? $_POST['new_test_password']
    : '';
$test_password = isset($_POST['test_password']) && is_string($_POST['test_password']) ? $_POST['test_password'] : '';
$sslcerts = isset($_POST['sslcerts']) && is_array($_POST['sslcerts']) ? $_POST['sslcerts'] : [];
/** @var list<int|string> $sslcerts */
$user_groups = $_POST['user_groups'] ?? [];
/** @var list<int|string> $user_groups */
$test_name = $_REQUEST['test_name'] ?? '';
/** @var string $test_name */
$test_description = isset($_REQUEST['test_description']) && is_string($_REQUEST['test_description'])
    ? $_REQUEST['test_description']
    : '';
// the native datetime-local control submits an ISO 'T' separator; store it space-separated
$test_begin_time_input = $_REQUEST['test_begin_time'] ?? '';
/** @var array<array-key,string>|string $test_begin_time_input */
$test_begin_time = str_replace('T', ' ', $test_begin_time_input);
$test_end_time_input = $_REQUEST['test_end_time'] ?? '';
/** @var array<array-key,string>|string $test_end_time_input */
$test_end_time = str_replace('T', ' ', $test_end_time_input);
$test_ip_range = $_REQUEST['test_ip_range'] ?? '';
// Number of subject-set rows submitted by the form; bounds the deletesubject checkbox loop.
$subjcount = isset($_REQUEST['subjcount']) ? (int) $_REQUEST['subjcount'] : 0;

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_TESTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/config/tce_user_registration.php';

/** @var mixed $db */
/**
 * @var array{
 *     a_meta_charset:string,d_password_length:string,h_add:string,h_add_questions:string,h_answers_order_mode:string,
 *     h_cancel:string,h_clear:string,h_delete:string,h_ip_range:string,h_num_answers:string,h_num_questions:string,
 *     h_pdf_offline_test:string,h_question_difficulty:string,h_question_type:string,h_questions_order_mode:string,
 *     h_random_answers:string,h_random_questions:string,h_score_right:string,h_score_unanswered:string,
 *     h_score_wrong:string,h_subjects:string,h_test:string,h_test_description:string,h_test_name:string,
 *     h_test_password:string,h_test_score_threshold:string,h_test_time:string,h_update:string,hp_edit_test:string,
 *     m_authorization_denied:string,m_delete_confirm_test:string,m_deleted:string,m_duplicate_name:string,
 *     m_form_missing_fields:string,m_unavailable_questions:string,m_update_restrict:string,m_updated:string,
 *     t_subjects_editor:string,t_tests_editor:string,w_add:string,w_add_questions:string,w_all:string,
 *     w_alphabetic:string,w_cancel:string,w_clear:string,w_confirm:string,w_datetime_format:string,w_delete:string,
 *     w_description:string,w_enable_comment:string,w_enable_menu:string,w_enable_noanswer:string,w_free_answer:string,
 *     w_generate:string,w_groups:string,w_id:string,w_ip_range:string,w_lock:string,w_logout_on_timeout:string,
 *     w_matching_answer:string,w_max_score:string,w_mcma_partial_score:string,w_mcma_radio:string,w_minutes:string,
 *     w_multiple_answers:string,w_name:string,w_no:string,w_num_answers:string,w_num_questions:string,
 *     w_order:string,w_order_by:string,w_ordering_answer:string,w_password:string,w_pdf_offline_test:string,
 *     w_position:string,w_question_difficulty:string,w_questions:string,w_random_answers:string,
 *     w_random_questions:string,w_repeatable:string,w_report_to_users:string,w_results_to_users:string,
 *     w_score_right:string,w_score_unanswered:string,w_score_wrong:string,w_search:string,w_select:string,
 *     w_single_answer:string,w_sslcerts:string,w_subject:string,w_subjects:string,w_test:string,
 *     w_test_score_threshold:string,w_test_time:string,w_time_begin:string,w_time_end:string,w_type:string,
 *     w_unlock:string,w_update:string
 * } $l
 */
$thispage_title = $l['t_tests_editor'];
require_once 'tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once 'tce_functions_tcecode_editor.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once '../../shared/code/tce_functions_test.php';
require_once 'tce_functions_user_select.php';
require_once 'tce_functions_test_select.php';

/** @var bool $formstatus */
/** @var string $menu_mode */

$matching_reuse_condition = f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')
    ? "dbms_lob.instr(question_description,'<!--TMF_MATCH_REUSE-->',1,1)>0"
    : "question_description LIKE '%<!--TMF_MATCH_REUSE-->%'";

// comma separated list of required fields
$_REQUEST['ff_required'] = 'test_name,test_description,test_ip_range,test_duration_time,test_score_right';
$_REQUEST['ff_required_labels'] = htmlspecialchars(
    $l['w_name']
    . ','
    . $l['w_description']
    . ','
    . $l['w_ip_range']
    . ','
    . $l['w_test_time']
    . ','
    . $l['w_score_right'],
    ENT_COMPAT,
    $l['a_meta_charset'],
);

// set default values
if (!isset($_REQUEST['test_results_to_users']) || empty($_REQUEST['test_results_to_users'])) {
    $test_results_to_users = false;
} else {
    $test_results_to_users = f_get_boolean($_REQUEST['test_results_to_users']);
}

if (!isset($_REQUEST['test_report_to_users']) || empty($_REQUEST['test_report_to_users'])) {
    $test_report_to_users = false;
} else {
    $test_report_to_users = f_get_boolean($_REQUEST['test_report_to_users']);
}

$subject_id = !isset($_REQUEST['subject_id']) || empty($_REQUEST['subject_id']) ? [] : $_REQUEST['subject_id'];
/** @var list<non-empty-string> $subject_id */

if (!isset($_REQUEST['tsubset_type']) || empty($_REQUEST['tsubset_type'])) {
    $tsubset_type = 0;
} else {
    $tsubset_type = (int) $_REQUEST['tsubset_type'];
}

$tsubset_difficulty = isset($_REQUEST['tsubset_difficulty']) ? (int) $_REQUEST['tsubset_difficulty'] : 1;

if (!isset($_REQUEST['tsubset_quantity']) || empty($_REQUEST['tsubset_quantity'])) {
    $tsubset_quantity = 1;
} else {
    $tsubset_quantity = (int) $_REQUEST['tsubset_quantity'];
}

if (!isset($_REQUEST['tsubset_answers']) || empty($_REQUEST['tsubset_answers'])) {
    $tsubset_answers = 2;
} else {
    $tsubset_answers = (int) $_REQUEST['tsubset_answers'];
}

if (isset($_REQUEST['tsubset_id'])) {
    $tsubset_id = (int) $_REQUEST['tsubset_id'];
}

if (isset($_REQUEST['test_duration_time'])) {
    $test_duration_time = (int) $_REQUEST['test_duration_time'];
}
/** @var int $test_duration_time */

if (isset($_REQUEST['group_id'])) {
    $group_id = (int) $_REQUEST['group_id'];
}

if (!isset($_REQUEST['test_score_right']) || empty($_REQUEST['test_score_right'])) {
    $test_score_right = 0;
} else {
    $test_score_right_input = $_REQUEST['test_score_right'];
    /** @var int|float|numeric-string $test_score_right_input */
    $test_score_right = (float) $test_score_right_input;
}

if (!isset($_REQUEST['test_score_wrong']) || empty($_REQUEST['test_score_wrong'])) {
    $test_score_wrong = 0;
} else {
    $test_score_wrong_input = $_REQUEST['test_score_wrong'];
    /** @var int|float|numeric-string $test_score_wrong_input */
    $test_score_wrong = (float) $test_score_wrong_input;
}

if (!isset($_REQUEST['test_score_unanswered']) || empty($_REQUEST['test_score_unanswered'])) {
    $test_score_unanswered = 0;
} else {
    $test_score_unanswered_input = $_REQUEST['test_score_unanswered'];
    /** @var int|float|numeric-string $test_score_unanswered_input */
    $test_score_unanswered = (float) $test_score_unanswered_input;
}

if (!isset($_REQUEST['test_score_threshold']) || empty($_REQUEST['test_score_threshold'])) {
    $test_score_threshold = 0;
} else {
    $test_score_threshold_input = $_REQUEST['test_score_threshold'];
    /** @var int|float|numeric-string $test_score_threshold_input */
    $test_score_threshold = (float) $test_score_threshold_input;
}

if (!isset($_REQUEST['test_random_questions_select']) || empty($_REQUEST['test_random_questions_select'])) {
    $test_random_questions_select = false;
} else {
    $test_random_questions_select = f_get_boolean($_REQUEST['test_random_questions_select']);
}

if (!isset($_REQUEST['test_random_questions_order']) || empty($_REQUEST['test_random_questions_order'])) {
    $test_random_questions_order = false;
} else {
    $test_random_questions_order = f_get_boolean($_REQUEST['test_random_questions_order']);
}

if (!isset($_REQUEST['test_questions_order_mode']) || empty($_REQUEST['test_questions_order_mode'])) {
    $test_questions_order_mode = 0;
} else {
    $test_questions_order_mode = max(0, min(3, (int) $_REQUEST['test_questions_order_mode']));
}

if (!isset($_REQUEST['test_random_answers_select']) || empty($_REQUEST['test_random_answers_select'])) {
    $test_random_answers_select = false;
} else {
    $test_random_answers_select = f_get_boolean($_REQUEST['test_random_answers_select']);
}

if (!isset($_REQUEST['test_random_answers_order']) || empty($_REQUEST['test_random_answers_order'])) {
    $test_random_answers_order = false;
} else {
    $test_random_answers_order = f_get_boolean($_REQUEST['test_random_answers_order']);
}

if (!isset($_REQUEST['test_answers_order_mode']) || empty($_REQUEST['test_answers_order_mode'])) {
    $test_answers_order_mode = 0;
} else {
    $test_answers_order_mode = max(0, min(2, (int) $_REQUEST['test_answers_order_mode']));
}

if (!isset($_REQUEST['test_comment_enabled']) || empty($_REQUEST['test_comment_enabled'])) {
    $test_comment_enabled = false;
} else {
    $test_comment_enabled = f_get_boolean($_REQUEST['test_comment_enabled']);
}

if (!isset($_REQUEST['test_menu_enabled']) || empty($_REQUEST['test_menu_enabled'])) {
    $test_menu_enabled = false;
} else {
    $test_menu_enabled = f_get_boolean($_REQUEST['test_menu_enabled']);
}

if (!isset($_REQUEST['test_noanswer_enabled']) || empty($_REQUEST['test_noanswer_enabled'])) {
    $test_noanswer_enabled = false;
} else {
    $test_noanswer_enabled = f_get_boolean($_REQUEST['test_noanswer_enabled']);
}

if (!isset($_REQUEST['test_mcma_radio']) || empty($_REQUEST['test_mcma_radio'])) {
    $test_mcma_radio = false;
} else {
    $test_mcma_radio = f_get_boolean($_REQUEST['test_mcma_radio']);
}

if (!isset($_REQUEST['test_repeatable']) || empty($_REQUEST['test_repeatable'])) {
    $test_repeatable = 0;
} else {
    $test_repeatable = (int) $_REQUEST['test_repeatable'];
}

if (!isset($_REQUEST['test_mcma_partial_score']) || empty($_REQUEST['test_mcma_partial_score'])) {
    $test_mcma_partial_score = false;
} else {
    $test_mcma_partial_score = f_get_boolean($_REQUEST['test_mcma_partial_score']);
}

if (!isset($_REQUEST['test_logout_on_timeout']) || empty($_REQUEST['test_logout_on_timeout'])) {
    $test_logout_on_timeout = false;
} else {
    $test_logout_on_timeout = f_get_boolean($_REQUEST['test_logout_on_timeout']);
}

$test_max_score_input = $_REQUEST['test_max_score'] ?? 0;
/** @var int|float|numeric-string $test_max_score_input */
$test_max_score = (float) $test_max_score_input;

$test_max_score_new = 0; // test max score
$qtype = ['S', 'M', 'T', 'O', 'C']; // question types
$qordmode = [$l['w_position'], $l['w_alphabetic'], $l['w_id'], $l['w_type'], $l['w_subject']];
$aordmode = [$l['w_position'], $l['w_alphabetic'], $l['w_id']];

$test_fieldset_name = '';

$test_id_request = $_REQUEST['test_id'] ?? null;
if (f_legacy_is_positive($test_id_request)) {
    $test_id = (int) $test_id_request;
    // check user's authorization
    if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
} else {
    $test_id = 0;
}

if (isset($_POST['lock'])) {
    $menu_mode = 'lock';
} elseif (isset($_POST['unlock'])) {
    $menu_mode = 'unlock';
}

/** @var string $test_end_time */
switch ($menu_mode) {
    case 'lock':
        // lock test by changing end date (subtract 1000 years)
            $sql =
                'UPDATE '
                . K_TABLE_TESTS
                . ' SET
			test_end_time='
                . f_empty_to_null('' . ((int) substr($test_end_time, 0, 1) - 1) . substr($test_end_time, 1))
                . '
			WHERE test_id='
                . $test_id
                . '';
            if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                F_display_db_error(false);
            } else {
                F_print_error('MESSAGE', $l['m_updated']);
            }

            break;

    case 'unlock':
        // unlock test by restoring original end date (add 1000 years)
            $sql =
                'UPDATE '
                . K_TABLE_TESTS
                . ' SET
			test_end_time='
                . f_empty_to_null('' . ((int) substr($test_end_time, 0, 1) + 1) . substr($test_end_time, 1))
                . '
			WHERE test_id='
                . $test_id
                . '';
            if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                F_display_db_error(false);
            } else {
                F_print_error('MESSAGE', $l['m_updated']);
            }

            break;

    case 'deletesubject':
        // delete subject
            // check referential integrity (NOTE: mysql do not support "ON UPDATE" constraint)
            if (!F_check_unique(K_TABLE_TEST_USER, 'testuser_test_id=' . $test_id . '')) {
                F_print_error('WARNING', $l['m_update_restrict']);

                break;
            }

            // for all selected subjects
            for ($i = 0; $i < $subjcount; ++$i) {
                $selected_subject = $_POST['selectsubject' . $i] ?? null;
                /** @var int|string|null $selected_subject */
                if (!empty($selected_subject)) {
                    $selected_tsubset_id = (int) $selected_subject;
                    if ($selected_tsubset_id > 0) {
                        $sql =
                            'DELETE FROM '
                            . K_TABLE_TEST_SUBJSET
                            . ' WHERE tsubset_test_id='
                            . $test_id
                            . ' AND tsubset_id='
                            . $selected_tsubset_id
                            . '';
                        if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                            F_display_db_error(false);
                        } else {
                            F_print_error('MESSAGE', $l['m_deleted']);
                        }
                    }
                }
            }

            break;

    case 'addquestion':
        // Add question type
            // check referential integrity (NOTE: mysql do not support "ON UPDATE" constraint)
            if (!F_check_unique(K_TABLE_TEST_USER, 'testuser_test_id=' . $test_id . '')) {
                F_print_error('WARNING', $l['m_update_restrict']);
                $formstatus = false;

                break;
            }

            if (
                ($formstatus = F_check_form_fields())
                && !empty($subject_id)
            ) {
                if ($tsubset_type === 3) {
                    // free-text questions do not have alternative answers to display
                    $tsubset_answers = 0;
                } elseif ($tsubset_answers < 2 && $tsubset_difficulty > 0) {
                    // questions must have at least 2 alternative answers
                    $tsubset_answers = 2;
                }

                // create a comma separated list of subjects IDs
                $subjids = '';
                foreach ($subject_id as $subid) {
                    if (f_legacy_literal_equals($subid[0], '#')) {
                        // module ID
                        $modid = (int) substr($subid, 1);
                        $sqlsm = F_select_subjects_sql('subject_module_id=' . $modid . '');
                        if ($rsm = f_legacy_db_query_result(F_db_query($sqlsm, $db))) {
                            while (($msm = f_tce_edit_test_subject_id_row(F_db_fetch_array($rsm))) !== null) {
                                $subjids .= $msm['subject_id'] . ',';
                            }
                        } else {
                            F_display_db_error();
                        }
                    } else {
                        $subjids .= (int) $subid . ',';
                    }
                }

                $subjids = substr($subjids, 0, -1);
                $subject_id = explode(',', $subjids);
                $subjids = '(' . $subjids . ')';
                $sql_answer_position = '';
                $sql_questions_position = '';
                if (!$test_random_questions_order && $test_questions_order_mode === 0) {
                    $sql_questions_position = ' AND question_position>0';
                }

                if (!$test_random_answers_order && $test_answers_order_mode === 0) {
                    $sql_answer_position = ' AND answer_position>0';
                }

                // check here if the selected number of questions are available for the current set
                // NOTE: if the same subject is used in multiple sets this control may fail.
                $sqlq = 'SELECT COUNT(*) AS numquestions FROM ' . K_TABLE_QUESTIONS . '';
                $sqlq .=
                    ' WHERE question_subject_id IN '
                    . $subjids
                    . '
					AND question_difficulty='
                    . $tsubset_difficulty
                    . '
					AND question_enabled=\'1\'';
                if ($tsubset_type > 0) {
                    $sqlq .= ' AND question_type=' . $tsubset_type . '';
                } else {
                    // Keep malformed MATCHING questions out of mixed-type sets.
                    $sqlq .= ' AND (question_type<>5 OR question_id IN (
							SELECT answer_question_id
							FROM ' . K_TABLE_ANSWERS . '
							WHERE answer_enabled=\'1\'
							AND answer_position>0
							GROUP BY answer_question_id
							HAVING (COUNT(answer_id)>1)
							AND ((COUNT(answer_id)=COUNT(DISTINCT answer_position))
								OR answer_question_id IN (
									SELECT question_id FROM ' . K_TABLE_QUESTIONS . '
									WHERE ' . $matching_reuse_condition . '
								))))';
                }

                if ($tsubset_type === 1) {
                    // single question (MCSA)
                    // check if the selected question has enough answers
                    $sqlq .=
                        ' AND question_id IN (
							SELECT answer_question_id
							FROM ' . K_TABLE_ANSWERS . '
							WHERE answer_enabled=\'1\' AND answer_isright=\'1\'';
                    $sqlq .= $sql_answer_position;
                    $sqlq .= ' GROUP BY answer_question_id
							HAVING (COUNT(answer_id)>0))';
                    $sqlq .= ' AND question_id IN (
							SELECT answer_question_id
							FROM ' . K_TABLE_ANSWERS . '
							WHERE answer_enabled=\'1\'
							AND answer_isright=\'0\'';
                    $sqlq .= $sql_answer_position;
                    $sqlq .= ' GROUP BY answer_question_id';
                    if ($tsubset_answers > 0) {
                        $sqlq .= ' HAVING (COUNT(answer_id)>=' . ($tsubset_answers - 1) . ')';
                    }

                    $sqlq .= ' )';
                } elseif ($tsubset_type === 2) {
                    // multiple question (MCMA)
                    // check if the selected question has enough answers
                    $sqlq .= ' AND question_id IN (
							SELECT answer_question_id
							FROM ' . K_TABLE_ANSWERS . '
							WHERE answer_enabled=\'1\'';
                    $sqlq .= $sql_answer_position;
                    $sqlq .= ' GROUP BY answer_question_id';
                    if ($tsubset_answers > 0) {
                        $sqlq .= ' HAVING (COUNT(answer_id)>=' . $tsubset_answers . ')';
                    }

                    $sqlq .= ' )';
                } elseif (in_array((int) $tsubset_type, [4, 5], true)) {
                    // ordering or matching question
                    // check if the selected question has enough answers
                    $sqlq .= ' AND question_id IN (
							SELECT answer_question_id
							FROM ' . K_TABLE_ANSWERS . '
							WHERE answer_enabled=\'1\'
							AND answer_position>0
							GROUP BY answer_question_id
							HAVING (COUNT(answer_id)>1)';
                    if ((int) $tsubset_type === 5) {
                        $sqlq .= ' AND ((COUNT(answer_id)=COUNT(DISTINCT answer_position))'
                            . ' OR answer_question_id IN (SELECT question_id FROM '
                            . K_TABLE_QUESTIONS
                            . ' WHERE ' . $matching_reuse_condition . '))';
                    }

                    $sqlq .= ')';
                }

                $sqlq .= $sql_questions_position;
                if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
                    $sqlq = 'SELECT * FROM (' . $sqlq . ') WHERE rownum <= ' . $tsubset_quantity . '';
                } else {
                    $sqlq .= ' LIMIT ' . $tsubset_quantity . '';
                }

                $numofrows = 0;
                if ($rq = f_legacy_db_query_result(F_db_query($sqlq, $db))) {
                    if ($mq = F_db_fetch_array($rq)) {
                        $numofrows = $mq['numquestions'];
                    }
                } else {
                    F_display_db_error();
                }

                if ($numofrows < $tsubset_quantity) {
                    F_print_error('WARNING', $l['m_unavailable_questions']);
                    break;
                }

                if ($subject_id !== []) {
                    // insert new subject
                    $sql =
                        'INSERT INTO '
                        . K_TABLE_TEST_SUBJSET
                        . ' (tsubset_test_id,
						tsubset_type,
						tsubset_difficulty,
						tsubset_quantity,
						tsubset_answers
						) VALUES (
						\''
                        . $test_id
                        . '\',
						\''
                        . $tsubset_type
                        . '\',
						\''
                        . $tsubset_difficulty
                        . '\',
						\''
                        . $tsubset_quantity
                        . '\',
						\''
                        . $tsubset_answers
                        . '\'
						)';
                    if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                        F_display_db_error(false);
                    } else {
                        $tsubset_id = F_db_insert_id($db, K_TABLE_TEST_SUBJSET, 'tsubset_id');
                        // add selected subject_id
                        foreach ($subject_id as $subid) {
                            $sql =
                                'INSERT INTO '
                                . K_TABLE_SUBJECT_SET
                                . ' (
								subjset_tsubset_id,
								subjset_subject_id
								) VALUES (
								\''
                                . $tsubset_id
                                . '\',
								\''
                                . $subid
                                . '\'
								)';
                            if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                                F_display_db_error(false);
                            }
                        }
                    }
                }
            }

            break;

    case 'delete':
            // ask confirmation
            F_print_error('WARNING', $l['m_delete_confirm_test']);
            ?>
        <div class="confirmbox">
        <form action="<?php echo
            htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        ; ?>" method="post" enctype="multipart/form-data" id="form_delete">
        <div>
        <input type="hidden" name="test_id" id="test_id" value="<?php echo $test_id; ?>" />
        <input type="hidden" name="test_name" id="test_name" value="<?php echo
            htmlspecialchars($test_name, ENT_QUOTES, $l['a_meta_charset'])
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

        break;

    case 'forcedelete':
            // Delete
            if ($forcedelete === $l['w_delete']) { //check if delete button has been pushed (redundant check)
                // delete test
                $sql = 'DELETE FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . $test_id . '';
                if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                    F_display_db_error(false);
                } else {
                    $test_id = false;
                    F_print_error('MESSAGE', $test_name . ': ' . $l['m_deleted']);
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
                $formstatus = false;

                break;
            }

            if ($formstatus = F_check_form_fields()) {
                // check referential integrity (NOTE: mysql do not support "ON UPDATE" constraint)
                if (!F_check_unique(K_TABLE_TEST_USER, 'testuser_test_id=' . $test_id . '')) {
                    F_print_error('WARNING', $l['m_update_restrict']);
                    $formstatus = false;

                    break;
                }

                // check if name is unique
                if (!F_check_unique(
                    K_TABLE_TESTS,
                    "test_name='" . F_escape_sql($db, $test_name) . "'",
                    'test_id',
                    $test_id,
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                if (!empty($new_test_password)) {
                    $test_password = get_password_hash($new_test_password);
                }

                if ($test_score_threshold > $test_max_score) {
                    $test_score_threshold = 0.6 * $test_max_score;
                }

                $sql =
                    'UPDATE '
                    . K_TABLE_TESTS
                    . ' SET
				test_name=\''
                    . F_escape_sql($db, $test_name)
                    . '\',
				test_description=\''
                    . F_escape_sql($db, $test_description)
                    . '\',
				test_begin_time='
                    . f_empty_to_null($test_begin_time)
                    . ',
				test_end_time='
                    . f_empty_to_null($test_end_time)
                    . ',
				test_duration_time=\''
                    . $test_duration_time
                    . '\',
				test_ip_range=\''
                    . F_escape_sql($db, $test_ip_range)
                    . '\',
				test_results_to_users=\''
                    . (int) $test_results_to_users
                    . '\',
				test_report_to_users=\''
                    . (int) $test_report_to_users
                    . '\',
				test_score_right=\''
                    . $test_score_right
                    . '\',
				test_score_wrong=\''
                    . $test_score_wrong
                    . '\',
				test_score_unanswered=\''
                    . $test_score_unanswered
                    . '\',
				test_max_score=\''
                    . $test_max_score
                    . '\',
				test_score_threshold=\''
                    . $test_score_threshold
                    . '\',
				test_random_questions_select=\''
                    . (int) $test_random_questions_select
                    . '\',
				test_random_questions_order=\''
                    . (int) $test_random_questions_order
                    . '\',
				test_questions_order_mode=\''
                    . $test_questions_order_mode
                    . '\',
				test_random_answers_select=\''
                    . (int) $test_random_answers_select
                    . '\',
				test_random_answers_order=\''
                    . (int) $test_random_answers_order
                    . '\',
				test_answers_order_mode=\''
                    . $test_answers_order_mode
                    . '\',
				test_comment_enabled=\''
                    . (int) $test_comment_enabled
                    . '\',
				test_menu_enabled=\''
                    . (int) $test_menu_enabled
                    . '\',
				test_noanswer_enabled=\''
                    . (int) $test_noanswer_enabled
                    . '\',
				test_mcma_radio=\''
                    . (int) $test_mcma_radio
                    . '\',
				test_repeatable=\''
                    . (int) $test_repeatable
                    . '\',
				test_mcma_partial_score=\''
                    . (int) $test_mcma_partial_score
                    . '\',
				test_logout_on_timeout=\''
                    . (int) $test_logout_on_timeout
                    . '\',
				test_password='
                    . f_empty_to_null($test_password)
                    . '
				WHERE test_id='
                    . $test_id
                    . '';
                if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                    F_display_db_error(false);
                } else {
                    F_print_error('MESSAGE', $l['m_updated']);
                }

                // delete previous groups
                $sql = 'DELETE FROM ' . K_TABLE_TEST_GROUPS . '
				WHERE tstgrp_test_id=' . $test_id . '';
                if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                    F_display_db_error(false);
                }

                // update authorized groups
                if (!empty($user_groups)) {
                    foreach ($user_groups as $group_id) {
                        $sql =
                            'INSERT INTO '
                            . K_TABLE_TEST_GROUPS
                            . ' (
						tstgrp_test_id,
						tstgrp_group_id
						) VALUES (
						\''
                            . $test_id
                            . '\',
						\''
                            . (int) $group_id
                            . '\'
						)';
                        if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                            F_display_db_error(false);
                        }
                    }
                }

                // delete previous SSL certificates
                $sql = 'DELETE FROM ' . K_TABLE_TEST_SSLCERTS . '
				WHERE tstssl_test_id=' . $test_id . '';
                if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                    F_display_db_error(false);
                }

                // update authorized SSL certificates
                if (!empty($sslcerts)) {
                    foreach ($sslcerts as $ssl_id) {
                        $sql =
                            'INSERT INTO '
                            . K_TABLE_TEST_SSLCERTS
                            . ' (
						tstssl_test_id,
						tstssl_ssl_id
						) VALUES (
						\''
                            . $test_id
                            . '\',
						\''
                            . (int) $ssl_id
                            . '\'
						)';
                        if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                            F_display_db_error(false);
                        }
                    }
                }
            }

            break;

    case 'add':
        // Add
            if ($formstatus = F_check_form_fields()) {
                // check if name is unique
                if (!F_check_unique(K_TABLE_TESTS, "test_name='" . F_escape_sql($db, $test_name) . "'")) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                if (f_legacy_is_positive($test_id)) {
                    // save previous test_id.
                    $old_test_id = $test_id;
                }

                if (!empty($new_test_password)) {
                    $test_password = get_password_hash($new_test_password);
                }

                /** @var array{session_user_id:int|string} $session */
                $session = $_SESSION;
                $sql =
                    'INSERT INTO '
                    . K_TABLE_TESTS
                    . ' (
			test_name,
				test_description,
				test_begin_time,
				test_end_time,
				test_duration_time,
				test_ip_range,
				test_results_to_users,
				test_report_to_users,
				test_score_right,
				test_score_wrong,
				test_score_unanswered,
				test_max_score,
				test_user_id,
				test_score_threshold,
				test_random_questions_select,
				test_random_questions_order,
				test_questions_order_mode,
				test_random_answers_select,
				test_random_answers_order,
				test_answers_order_mode,
				test_comment_enabled,
				test_menu_enabled,
				test_noanswer_enabled,
				test_mcma_radio,
				test_repeatable,
				test_mcma_partial_score,
				test_logout_on_timeout,
				test_password
				) VALUES (
				\''
                    . F_escape_sql($db, $test_name)
                    . '\',
				\''
                    . F_escape_sql($db, $test_description)
                    . '\',
				'
                    . f_empty_to_null($test_begin_time)
                    . ',
				'
                    . f_empty_to_null($test_end_time)
                    . ',
				\''
                    . $test_duration_time
                    . '\',
				\''
                    . F_escape_sql($db, $test_ip_range)
                    . '\',
				\''
                    . (int) $test_results_to_users
                    . '\',
				\''
                    . (int) $test_report_to_users
                    . '\',
				\''
                    . $test_score_right
                    . '\',
				\''
                    . $test_score_wrong
                    . '\',
				\''
                    . $test_score_unanswered
                    . '\',
				\''
                    . $test_max_score
                    . '\',
				\''
					. (int) $session['session_user_id']
                    . '\',
				\''
                    . $test_score_threshold
                    . '\',
				\''
                    . (int) $test_random_questions_select
                    . '\',
				\''
                    . (int) $test_random_questions_order
                    . '\',
				\''
                    . $test_questions_order_mode
                    . '\',
				\''
                    . (int) $test_random_answers_select
                    . '\',
				\''
                    . (int) $test_random_answers_order
                    . '\',
				\''
                    . $test_answers_order_mode
                    . '\',
				\''
                    . (int) $test_comment_enabled
                    . '\',
				\''
                    . (int) $test_menu_enabled
                    . '\',
				\''
                    . (int) $test_noanswer_enabled
                    . '\',
				\''
                    . (int) $test_mcma_radio
                    . '\',
				\''
                    . (int) $test_repeatable
                    . '\',
				\''
                    . (int) $test_mcma_partial_score
                    . '\',
				\''
                    . (int) $test_logout_on_timeout
                    . '\',
				'
                    . f_empty_to_null($test_password)
                    . '
				)';
                if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                    F_display_db_error(false);
                } else {
                    $test_id = F_db_insert_id($db, K_TABLE_TESTS, 'test_id');
                }

                // add authorized user's groups
                if (!empty($user_groups)) {
                    foreach ($user_groups as $group_id) {
                        $sql =
                            'INSERT INTO '
                            . K_TABLE_TEST_GROUPS
                            . ' (
						tstgrp_test_id,
						tstgrp_group_id
						) VALUES (
						\''
                            . $test_id
                            . '\',
						\''
                            . (int) $group_id
                            . '\'
						)';
                        if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                            F_display_db_error(false);
                        }
                    }
                }

                // update authorized SSL certificates
                if (!empty($sslcerts)) {
                    foreach ($sslcerts as $ssl_id) {
                        $sql =
                            'INSERT INTO '
                            . K_TABLE_TEST_SSLCERTS
                            . ' (
						tstssl_test_id,
						tstssl_ssl_id
						) VALUES (
						\''
                            . $test_id
                            . '\',
						\''
                            . (int) $ssl_id
                            . '\'
						)';
                        if (!($r = f_legacy_db_query_result(F_db_query($sql, $db)))) {
                            F_display_db_error(false);
                        }
                    }
                }

                if (isset($old_test_id) && $old_test_id > 0) {
                    // copy here previous selected questions to this new test
                    $sql = 'SELECT *
					FROM ' . K_TABLE_TEST_SUBJSET . '
					WHERE tsubset_test_id=\'' . $old_test_id . "'";
                    if ($r = f_legacy_db_query_result(F_db_query($sql, $db))) {
                        while ($m = F_db_fetch_array($r)) {
                            // insert new subject
                            $sqlu =
                                'INSERT INTO '
                                . K_TABLE_TEST_SUBJSET
                                . ' (
							tsubset_test_id,
							tsubset_type,
							tsubset_difficulty,
							tsubset_quantity,
							tsubset_answers
							) VALUES (
							\''
                                . $test_id
                                . '\',
							\''
                                . $m['tsubset_type']
                                . '\',
							\''
                                . $m['tsubset_difficulty']
                                . '\',
							\''
                                . $m['tsubset_quantity']
                                . '\',
							\''
                                . $m['tsubset_answers']
                                . '\'
							)';
                            if (!($ru = f_legacy_db_query_result(F_db_query($sqlu, $db)))) {
                                F_display_db_error();
                            } else {
                                $tsubset_id = F_db_insert_id($db, K_TABLE_TEST_SUBJSET, 'tsubset_id');
                                $sqls =
                                    'SELECT *
								FROM '
                                    . K_TABLE_SUBJECT_SET
                                    . '
								WHERE subjset_tsubset_id=\''
                                    . $m['tsubset_id']
                                    . "'";
                                if ($rs = f_legacy_db_query_result(F_db_query($sqls, $db))) {
                                    while ($ms = F_db_fetch_array($rs)) {
                                        $sqlp =
                                            'INSERT INTO '
                                            . K_TABLE_SUBJECT_SET
                                            . ' (
										subjset_tsubset_id,
										subjset_subject_id
										) VALUES (
										\''
                                            . $tsubset_id
                                            . '\',
										\''
                                            . $ms['subjset_subject_id']
                                            . '\'
										)';
                                        if (!($rp = f_legacy_db_query_result(F_db_query($sqlp, $db)))) {
                                            F_display_db_error();
                                        }
                                    }
                                } else {
                                    F_display_db_error();
                                }
                            }
                        }
                    } else {
                        F_display_db_error();
                    }
                }
            }

            break;

    case 'clear':
        // Clear form fields
            $test_name = '';
            $test_description = '';
            $test_begin_time = date(K_TIMESTAMP_FORMAT);
            $default_test_end_timestamp = time() + K_SECONDS_IN_DAY;
            /** @var int $default_test_end_timestamp */
            $test_end_time = date(K_TIMESTAMP_FORMAT, $default_test_end_timestamp);
            $test_duration_time = 60;
            $test_ip_range = '*';
            $test_results_to_users = false;
            $test_report_to_users = false;
            $test_score_right = 1;
            $test_score_wrong = 0;
            $test_score_unanswered = 0;
            $test_max_score = 0;
            $test_score_threshold = 0;
            $test_random_questions_select = true;
            $test_random_questions_order = true;
            $test_questions_order_mode = 0;
            $test_random_answers_select = true;
            $test_random_answers_order = true;
            $test_answers_order_mode = 0;
            $test_comment_enabled = true;
            $test_menu_enabled = true;
            $test_noanswer_enabled = true;
            $test_mcma_radio = true;
            $test_repeatable = 0;
            $test_mcma_partial_score = true;
            $test_logout_on_timeout = false;
            $test_password = '';
            break;

    default:
            break;
} //end of switch

// --- Initialize variables

if (!isset($test_num) || !empty($test_num)) {
    $test_num = 1; // default number of PDF tests to generate
}

if ($formstatus && $menu_mode !== 'clear') {
    if ($test_id === 0) {
        $test_id = 0;
        $test_name = '';
        $test_description = '';
        $test_begin_time = date(K_TIMESTAMP_FORMAT);
        $default_test_end_timestamp = time() + K_SECONDS_IN_DAY;
        /** @var int $default_test_end_timestamp */
        $test_end_time = date(K_TIMESTAMP_FORMAT, $default_test_end_timestamp);
        $test_duration_time = 60;
        $test_ip_range = '*';
        $test_results_to_users = false;
        $test_report_to_users = false;
        $test_score_right = 1;
        $test_score_wrong = 0;
        $test_score_unanswered = 0;
        $test_max_score = 0;
        $test_score_threshold = 0;
        $test_random_questions_select = true;
        $test_random_questions_order = true;
        $test_questions_order_mode = 0;
        $test_random_answers_select = true;
        $test_random_answers_order = true;
        $test_answers_order_mode = 0;
        $test_comment_enabled = true;
        $test_menu_enabled = true;
        $test_noanswer_enabled = true;
        $test_mcma_radio = true;
        $test_repeatable = 0;
        $test_mcma_partial_score = true;
        $test_logout_on_timeout = false;
        $test_password = '';
    } else {
        $sql =
            'SELECT * FROM '
            . K_TABLE_TESTS
            . ' WHERE test_id='
            . f_general_string($test_id)
            . ' LIMIT 1';
        if ($r = f_legacy_db_query_result(F_db_query($sql, $db))) {
            if (($m = f_tce_edit_test_record_row(F_db_fetch_array($r))) !== null) {
                $test_id = $m['test_id'];
                $test_name = $m['test_name'];
                $test_description = $m['test_description'] ?? '';
                $test_begin_time = $m['test_begin_time'];
                $test_end_time = $m['test_end_time'];
                $test_duration_time = $m['test_duration_time'];
                $test_ip_range = $m['test_ip_range'];
                $test_results_to_users = f_get_boolean($m['test_results_to_users']);
                $test_report_to_users = f_get_boolean($m['test_report_to_users']);
                $test_score_right = $m['test_score_right'];
                $test_score_wrong = $m['test_score_wrong'];
                $test_score_unanswered = $m['test_score_unanswered'];
                $test_max_score = $m['test_max_score'];
                $test_score_threshold = $m['test_score_threshold'];
                $test_random_questions_select = f_get_boolean($m['test_random_questions_select']);
                $test_random_questions_order = f_get_boolean($m['test_random_questions_order']);
                $test_questions_order_mode = (int) $m['test_questions_order_mode'];
                $test_random_answers_select = f_get_boolean($m['test_random_answers_select']);
                $test_random_answers_order = f_get_boolean($m['test_random_answers_order']);
                $test_answers_order_mode = (int) $m['test_answers_order_mode'];
                $test_comment_enabled = f_get_boolean($m['test_comment_enabled']);
                $test_menu_enabled = f_get_boolean($m['test_menu_enabled']);
                $test_noanswer_enabled = f_get_boolean($m['test_noanswer_enabled']);
                $test_mcma_radio = f_get_boolean($m['test_mcma_radio']);
                $test_repeatable = $m['test_repeatable'];
                $test_mcma_partial_score = f_get_boolean($m['test_mcma_partial_score']);
                $test_logout_on_timeout = f_get_boolean($m['test_logout_on_timeout']);
                $test_password = $m['test_password'];
            } else {
                $test_name = '';
                $test_description = '';
                $test_begin_time = date(K_TIMESTAMP_FORMAT);
                $default_test_end_timestamp = time() + K_SECONDS_IN_DAY;
                /** @var int $default_test_end_timestamp */
                $test_end_time = date(K_TIMESTAMP_FORMAT, $default_test_end_timestamp);
                $test_duration_time = 60;
                $test_ip_range = '*';
                $test_results_to_users = false;
                $test_report_to_users = false;
                $test_score_right = 1;
                $test_score_wrong = 0;
                $test_score_unanswered = 0;
                $test_max_score = 0;
                $test_score_threshold = 0;
                $test_random_questions_select = true;
                $test_random_questions_order = true;
                $test_questions_order_mode = 0;
                $test_random_answers_select = true;
                $test_random_answers_order = true;
                $test_answers_order_mode = 0;
                $test_comment_enabled = true;
                $test_menu_enabled = true;
                $test_noanswer_enabled = true;
                $test_mcma_radio = true;
                $test_repeatable = 0;
                $test_mcma_partial_score = true;
                $test_logout_on_timeout = false;
                $test_password = '';
            }
        } else {
            F_display_db_error();
        }
    }
}

$millennium = substr(date('Y'), 0, 1);

echo '<div class="container">' . K_NEWLINE;

echo f_openvsosh_admin_test_context((int) $test_id, 'settings');

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_testeditor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="test_id">' . $l['w_test'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="test_id" id="test_id" onchange="document.getElementById(\'form_testeditor\').submit()" title="'
        . $l['h_test']
        . '">'
        . K_NEWLINE
;
echo '<option value="0" style="background-color:#009900;color:white;"';
if (f_legacy_int_equals($test_id, 0)) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
$sql = F_select_tests_sql();
if ($r = f_legacy_db_query_result(F_db_query($sql, $db))) {
    $countitem = 1;
    while (($m = f_tce_edit_test_list_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['test_id'] . '"';
        $listed_test_id = $m['test_id'];
        if (f_legacy_int_equals($test_id, (int) $listed_test_id)) {
            echo ' selected="selected"';
            $test_fieldset_name =
                ''
                . substr($m['test_begin_time'], 0, 10)
                . ' '
                . htmlspecialchars($m['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                . '';
        }

        echo '>' . $countitem . '. ';
        if (substr($m['test_end_time'], 0, 1) < $millennium) {
            echo '* ';
        }

        echo
            substr($m['test_begin_time'], 0, 10)
                . ' '
                . htmlspecialchars($m['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                . '</option>'
                . K_NEWLINE
        ;
        ++$countitem;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;

// link for user selection popup
$jsaction = "selectWindow=window.open('tce_select_tests_popup.php?cid=test_id', 'selectWindow', 'dependent, height=600, width=800, menubar=no, resizable=yes, scrollbars=yes, status=no, toolbar=no');return false;";
echo '<button type="button" onclick="' . $jsaction . '" class="xmlbutton" title="' . $l['w_select'] . '">...</button>';

echo '</span>' . K_NEWLINE;
echo '<br /><br />' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectrecord');

echo '<nav class="editor-section-nav" aria-label="Разделы настроек">'
    . '<a href="#editor-basics">Основное</a><a href="#editor-audience">Участники и доступ</a>'
    . '<a href="#editor-scoring">Оценивание</a><a href="#editor-behaviour">Проведение</a>'
    . (f_legacy_is_positive($test_id) ? '<a href="#editor-questions">Вопросы</a>' : '') . '</nav>' . K_NEWLINE;

echo '<fieldset class="test-editor-main">' . K_NEWLINE;
echo '<legend>' . $l['w_test'] . '</legend>' . K_NEWLINE;
echo '<h2 class="editor-section-heading" id="editor-basics">Основное</h2>' . K_NEWLINE;

echo get_form_row_text_input('test_name', $l['w_name'], $l['h_test_name'], '', $test_name, '', 255, false, false, false);
echo
    get_form_row_text_box(
        'test_description',
        $l['w_description'],
        $l['h_test_description'],
        $test_description,
        false,
        '',
        true,
    )
;
echo
    get_form_row_text_input(
        'test_begin_time',
        $l['w_time_begin'],
        $l['w_time_begin'] . ' ' . $l['w_datetime_format'],
        '',
        $test_begin_time,
        '',
        19,
        false,
        true,
        false,
    )
;
echo
    get_form_row_text_input(
        'test_end_time',
        $l['w_time_end'],
        $l['w_time_end'] . ' ' . $l['w_datetime_format'],
        '',
        $test_end_time,
        '',
        19,
        false,
        true,
        false,
    )
;
echo
    get_form_row_text_input(
        'test_duration_time',
        $l['w_test_time'],
        $l['h_test_time'],
        '[' . $l['w_minutes'] . ']',
        $test_duration_time,
        '^([0-9]*)$',
        20,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'test_ip_range',
        $l['w_ip_range'],
        $l['h_ip_range'],
        '',
        $test_ip_range,
        '^([0-9a-fA-F,\:\.\*-]*)$',
        255,
        false,
        false,
        false,
    )
;

echo '<h2 class="editor-section-heading" id="editor-audience">Участники и доступ</h2>' . K_NEWLINE;
echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="user_groups">' . $l['w_groups'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<input type="search" id="user_groups_filter" placeholder="'
        . htmlspecialchars($l['w_search'] . ': ' . $l['w_groups'], ENT_COMPAT, $l['a_meta_charset'])
        . '" aria-label="'
        . htmlspecialchars($l['w_search'] . ': ' . $l['w_groups'], ENT_COMPAT, $l['a_meta_charset'])
        . '" aria-controls="user_groups" autocomplete="off" />'
        . K_NEWLINE
;
echo '<select name="user_groups[]" id="user_groups" size="5" multiple="multiple">' . K_NEWLINE;
//$sql = F_user_group_select_sql();
$sql = 'SELECT * FROM ' . K_TABLE_GROUPS . ' ORDER BY group_name';
if ($r = f_legacy_db_query_result(F_db_query($sql, $db))) {
    while ($m = F_db_fetch_array($r)) {
        echo '<option value="' . $m['group_id'] . '"';
        if (f_legacy_is_positive($test_id) && f_is_test_on_group($test_id, $m['group_id'])) {
            echo ' selected="selected"';
        }

        echo '>' . htmlspecialchars($m['group_name'], ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="sslcerts">' . $l['w_sslcerts'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<select name="sslcerts[]" id="sslcerts" size="5" multiple="multiple">' . K_NEWLINE;
$sql = 'SELECT * FROM ' . K_TABLE_SSLCERTS . ' ORDER BY ssl_name';
if ($r = f_legacy_db_query_result(F_db_query($sql, $db))) {
    while ($m = F_db_fetch_array($r)) {
        echo '<option value="' . $m['ssl_id'] . '"';
        if (f_legacy_is_positive($test_id) && f_is_test_on_ssl_certs($test_id, $m['ssl_id'])) {
            echo ' selected="selected"';
        }

        echo
            '>'
                . htmlspecialchars(
                    $m['ssl_name'] . ' (' . substr($m['ssl_end_date'], 0, 10) . ')',
                    ENT_NOQUOTES,
                    $l['a_meta_charset'],
                )
                . '</option>'
                . K_NEWLINE
        ;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<h2 class="editor-section-heading" id="editor-scoring">Оценивание</h2>' . K_NEWLINE;
echo
    get_form_row_text_input(
        'test_score_right',
        $l['w_score_right'],
        $l['h_score_right'],
        '',
        $test_score_right,
        '^([0-9\+\-]*)([\.]?)([0-9]*)$',
        20,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'test_score_wrong',
        $l['w_score_wrong'],
        $l['h_score_wrong'],
        '',
        $test_score_wrong,
        '^([0-9\+\-]*)([\.]?)([0-9]*)$',
        20,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'test_score_unanswered',
        $l['w_score_unanswered'],
        $l['h_score_unanswered'],
        '',
        $test_score_unanswered,
        '^([0-9\+\-]*)([\.]?)([0-9]*)$',
        20,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'test_score_threshold',
        $l['w_test_score_threshold'],
        $l['h_test_score_threshold'],
        '',
        $test_score_threshold,
        '^([0-9\+\-]*)([\.]?)([0-9]*)$',
        20,
        false,
        false,
        false,
    )
;

echo '<h2 class="editor-section-heading" id="editor-behaviour">Проведение и отображение</h2>' . K_NEWLINE;
echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="test_random_questions_select">' . $l['w_random_questions'] . ':</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="checkbox" name="test_random_questions_select" id="test_random_questions_select" value="1"';
if ($test_random_questions_select) {
    echo ' checked="checked"';
}

echo ' onchange="JF_check_random_boxes()"';
echo ' title="' . $l['h_random_questions'] . '" />';
echo ' <label for="test_random_questions_select">' . $l['w_select'] . '</label>' . K_NEWLINE;

echo ' <input type="checkbox" name="test_random_questions_order" id="test_random_questions_order" value="1"';
if ($test_random_questions_order) {
    echo ' checked="checked"';
}

echo ' onchange="JF_check_random_boxes()"';
echo ' title="' . $l['w_order'] . '" />';
echo ' <label for="test_random_questions_order">' . $l['w_order'] . '</label>' . K_NEWLINE;

echo '<span id="select_questions_order_mode" style="visibility:visible;">' . K_NEWLINE;
echo ' | <label for="test_questions_order_mode">' . $l['w_order_by'] . '</label>' . K_NEWLINE;
echo
    ' <select name="test_questions_order_mode" id="test_questions_order_mode" size="1" title="'
        . $l['h_questions_order_mode']
        . '">'
        . K_NEWLINE
;
foreach ($qordmode as $ok => $ov) {
    echo '<option value="' . $ok . '"';
    if ($test_questions_order_mode === $ok) {
        echo ' selected="selected"';
    }

    echo '>' . htmlspecialchars($ov, ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="test_random_answers_select">' . $l['w_random_answers'] . ':</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="checkbox" name="test_random_answers_select" id="test_random_answers_select" value="1"';
if ($test_random_answers_select) {
    echo ' checked="checked"';
}

echo ' onchange="JF_check_random_boxes()"';
echo ' title="' . $l['h_random_answers'] . '" />';
echo ' <label for="test_random_answers_select">' . $l['w_select'] . '</label>' . K_NEWLINE;

echo ' <input type="checkbox" name="test_random_answers_order" id="test_random_answers_order" value="1"';
if ($test_random_answers_order) {
    echo ' checked="checked"';
}

echo ' onchange="JF_check_random_boxes()"';
echo ' title="' . $l['w_order'] . '" />';
echo ' <label for="test_random_answers_order">' . $l['w_order'] . '</label>' . K_NEWLINE;

echo '<span id="select_answers_order_mode" style="visibility:visible;">' . K_NEWLINE;
echo ' | <label for="test_answers_order_mode">' . $l['w_order_by'] . '</label>' . K_NEWLINE;
echo
    ' <select name="test_answers_order_mode" id="test_answers_order_mode" size="1" title="'
        . $l['h_answers_order_mode']
        . '">'
        . K_NEWLINE
;
foreach ($aordmode as $ok => $ov) {
    echo '<option value="' . $ok . '"';
    if ($test_answers_order_mode === $ok) {
        echo ' selected="selected"';
    }

    echo '>' . htmlspecialchars($ov, ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_row_checkbox('test_mcma_radio', $l['w_mcma_radio'], '', '', 1, $test_mcma_radio, false);
echo
    get_form_row_checkbox(
        'test_mcma_partial_score',
        $l['w_mcma_partial_score'],
        '',
        '',
        1,
        $test_mcma_partial_score,
        false,
    )
;
echo get_form_row_checkbox('test_noanswer_enabled', $l['w_enable_noanswer'], '', '', 1, $test_noanswer_enabled, false);
echo get_form_row_checkbox('test_menu_enabled', $l['w_enable_menu'], '', '', 1, $test_menu_enabled, false);
echo get_form_row_checkbox('test_comment_enabled', $l['w_enable_comment'], '', '', 1, $test_comment_enabled, false);
echo get_form_row_checkbox('test_results_to_users', $l['w_results_to_users'], '', '', 1, $test_results_to_users, false);
echo get_form_row_checkbox('test_report_to_users', $l['w_report_to_users'], '', '', 1, $test_report_to_users, false);

$repeat_options = [
    0 => $l['w_no'],
    1 => $l['w_repeatable'],
];
for ($i = 2; $i <= 127; ++$i) {
    $repeat_options[$i] = $i;
}

echo get_form_row_select_box('test_repeatable', $l['w_repeatable'], '', '', $test_repeatable, $repeat_options, '');

echo get_form_row_checkbox('test_logout_on_timeout', $l['w_logout_on_timeout'], '', '', 1, $test_logout_on_timeout, false);

echo
    get_form_row_text_input(
        'new_test_password',
        $l['w_password'],
        $l['h_test_password'],
        ' (' . $l['d_password_length'] . ')',
        '',
        K_USRREG_PASSWORD_RE,
        255,
        false,
        false,
        true,
    )
;

echo '<div class="row editor-sticky-actions">' . K_NEWLINE;
echo '<br />' . K_NEWLINE;
echo
    '<input type="hidden" name="test_password" id="test_password" value="'
        . htmlspecialchars((string) $test_password, ENT_QUOTES, $l['a_meta_charset'])
        . '" />'
        . K_NEWLINE
;

// show buttons by case

if (f_legacy_is_positive($test_id)) {
    echo '<span class="editor-confirm-update">';
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
    echo '<label for="confirmupdate">Подтвердить сохранение</label>';
    F_submit_button('update', $l['w_update'], $l['h_update']);
    echo '</span>';
}

F_submit_button('add', $l['w_add'], $l['h_add']);
if (f_legacy_is_positive($test_id)) {
    F_submit_button('delete', $l['w_delete'], $l['h_delete']);
    if (substr($test_end_time, 0, 1) < $millennium) {
        F_submit_button('unlock', $l['w_unlock'], $l['w_unlock']);
    } else {
        F_submit_button('lock', $l['w_lock'], $l['w_lock']);
    }
}

F_submit_button('clear', $l['w_clear'], $l['h_clear']);
echo '<span class="editor-save-state" data-editor-save-state aria-live="polite">Изменений нет</span>';

echo '<br /><br />' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '</fieldset>' . K_NEWLINE;

// display a list of selected subject_id (topics)
if (f_legacy_is_positive($test_id)) {
    echo '<div class="row"><br /></div>' . K_NEWLINE;

    echo '<fieldset id="editor-questions">' . K_NEWLINE;
    echo '<legend>' . $l['w_questions'] . '</legend>' . K_NEWLINE;

    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
    echo '<span class="formw">' . $test_fieldset_name . '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<label for="subject_id">' . $l['w_subjects'] . '</label>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo
        '<input type="search" id="subject_filter" placeholder="'
            . htmlspecialchars($l['w_search'] . ': ' . $l['w_subjects'], ENT_COMPAT, $l['a_meta_charset'])
            . '" aria-label="'
            . htmlspecialchars($l['w_search'] . ': ' . $l['w_subjects'], ENT_COMPAT, $l['a_meta_charset'])
            . '" aria-controls="subject_id" autocomplete="off" />'
            . K_NEWLINE
    ;
    echo
        '<select name="subject_id[]" id="subject_id" size="10" multiple="multiple" title="'
            . $l['h_subjects']
            . '">'
            . K_NEWLINE
    ;
    // select subject_id
    $sql = F_select_module_subjects_sql("module_enabled='1' AND subject_enabled='1'");
    if ($r = f_legacy_db_query_result(F_db_query($sql, $db))) {
        $prev_module_id = 0;
        while (($m = f_tce_edit_test_module_subject_row(F_db_fetch_array($r))) !== null) {
            /** @var int|numeric-string $raw_module_id */
            $raw_module_id = $m['module_id'];
            $module_id = (int) $raw_module_id;
            if ($module_id !== $prev_module_id) {
                $prev_module_id = $module_id;
                echo
                    '<option value="#'
                        . $m['module_id']
                        . '" style="background-color:#DDEEFF;font-weight:bold">* '
                        . htmlspecialchars($m['module_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</option>'
                        . K_NEWLINE
                ;
            }

            echo '<option value="' . $m['subject_id'] . '"';
            if (in_array($m['subject_id'], $subject_id)) {
                echo ' selected="selected"';
            }

            echo
                '>&nbsp;&nbsp;&nbsp;' . htmlspecialchars($m['subject_name'], ENT_NOQUOTES, $l['a_meta_charset']) . ' ['
            ;
            // count available questions for each type
            $qstat = '';
            $sqln =
                'SELECT question_type, question_difficulty, COUNT(*) as numquestions
				FROM '
                . K_TABLE_QUESTIONS
                . '
				WHERE question_subject_id='
                . $m['subject_id']
                . '
					AND question_enabled=\'1\'
				GROUP BY question_type, question_difficulty';
            if ($rn = f_legacy_db_query_result(F_db_query($sqln, $db))) {
                while ($mn = F_db_fetch_array($rn)) {
                    $qstat .= ' ' . $mn['numquestions'] . $qtype[$mn['question_type'] - 1] . $mn['question_difficulty'];
                    // count min and max alternative answers
                    $amin = 999_999;
                    $amax = 0;
                    $sqla =
                        'SELECT question_id, COUNT(*) as numanswers
						FROM '
                        . K_TABLE_QUESTIONS
                        . ','
                        . K_TABLE_ANSWERS
                        . '
						WHERE answer_question_id=question_id
							AND question_subject_id='
                        . $m['subject_id']
                        . '
							AND question_type='
                        . $mn['question_type']
                        . '
							AND question_difficulty='
                        . $mn['question_difficulty']
                        . '
							AND question_enabled=\'1\'
							AND answer_enabled=\'1\'
						GROUP BY question_id';
                    if ($ra = f_legacy_db_query_result(F_db_query($sqla, $db))) {
                        while ($ma = F_db_fetch_array($ra)) {
                            if ($ma['numanswers'] < $amin) {
                                $amin = $ma['numanswers'];
                            }

                            if ($ma['numanswers'] > $amax) {
                                $amax = $ma['numanswers'];
                            }
                        }
                    } else {
                        F_display_db_error();
                    }

                    if ($amin === 999_999) {
                        $amin = 0;
                    }

                    // display minimum alternative answers
                    $qstat .= ':' . $amin;
                    if ($amax > $amin) {
                        $qstat .= '-' . $amax;
                    }
                }
            } else {
                F_display_db_error();
            }

            echo $qstat . ' ]</option>' . K_NEWLINE;
        }
    } else {
        echo '</select></span></div>' . K_NEWLINE;
        F_display_db_error();
    }

    echo '</select>' . K_NEWLINE;
    echo '<div class="subject-bulk-actions">'
        . '<button type="button" class="minibutton" id="select_all_subjects">Выбрать все темы</button>'
        . '<button type="button" class="minibutton" id="clear_all_subjects">Снять выбор</button>'
        . '</div>' . K_NEWLINE;
    echo '<script type="text/javascript">'
        . '(function(){var list=document.getElementById("subject_id");'
        . 'var filter=document.getElementById("subject_filter");'
        . 'function setVisible(option,visible){option.hidden=!visible;option.style.display=visible?"":"none";}'
        . 'function filterSubjects(){var query=filter.value.trim().toLocaleLowerCase();'
        . 'var heading=null;var moduleMatches=false;'
        . 'Array.prototype.forEach.call(list.options,function(option){'
        . 'var matches=query===""||option.text.toLocaleLowerCase().indexOf(query)!==-1;'
        . 'if(option.value.charAt(0)==="#"){heading=option;moduleMatches=matches;setVisible(option,matches);return;}'
        . 'var visible=query===""||moduleMatches||matches;setVisible(option,visible);'
        . 'if(visible&&heading){setVisible(heading,true);}});}'
        . 'function setAll(selected){Array.prototype.forEach.call(list.options,function(option){'
        . 'option.selected=selected&&option.value.charAt(0)!=="#";});}'
        . 'filter.addEventListener("input",filterSubjects);'
        . 'document.getElementById("select_all_subjects").addEventListener("click",function(){setAll(true);});'
        . 'document.getElementById("clear_all_subjects").addEventListener("click",function(){setAll(false);});'
        . '}());'
        . '</script>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    echo
        get_form_row_text_input(
            'tsubset_quantity',
            $l['w_num_questions'],
            $l['h_num_questions'],
            '',
            $tsubset_quantity,
            '^([0-9]*)$',
            20,
            false,
            false,
            false,
        )
    ;

    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<label for="tsubset_type">' . $l['w_type'] . '</label>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo '<select name="tsubset_type" id="tsubset_type" title="' . $l['h_question_type'] . '">' . K_NEWLINE;
    echo '<option value="0"';
    if ($tsubset_type === 0) {
        echo ' selected="selected"';
    }

    echo '>*** ' . $l['w_all'] . ' ***</option>' . K_NEWLINE;
    echo '<option value="1"';
    if ($tsubset_type === 1) {
        echo ' selected="selected"';
    }

    echo '>' . $l['w_single_answer'] . '</option>' . K_NEWLINE;
    echo '<option value="2"';
    if ($tsubset_type === 2) {
        echo ' selected="selected"';
    }

    echo '>' . $l['w_multiple_answers'] . '</option>' . K_NEWLINE;
    echo '<option value="3"';
    if ($tsubset_type === 3) {
        echo ' selected="selected"';
    }

    echo '>' . $l['w_free_answer'] . '</option>' . K_NEWLINE;
    echo '<option value="4"';
    if ($tsubset_type === 4) {
        echo ' selected="selected"';
    }

    echo '>' . $l['w_ordering_answer'] . '</option>' . K_NEWLINE;
    echo '<option value="5"';
    if ($tsubset_type === 5) {
        echo ' selected="selected"';
    }

    echo '>' . $l['w_matching_answer'] . '</option>' . K_NEWLINE;
    echo '</select>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<label for="tsubset_difficulty">' . $l['w_question_difficulty'] . '</label>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo
        '<select name="tsubset_difficulty" id="tsubset_difficulty" title="'
            . $l['h_question_difficulty']
            . '">'
            . K_NEWLINE
    ;
    for ($i = 0; $i <= K_QUESTION_DIFFICULTY_LEVELS; ++$i) {
        echo '<option value="' . $i . '"';
        if ($i === $tsubset_difficulty) {
            echo ' selected="selected"';
        }

        echo '>' . $i . '</option>' . K_NEWLINE;
    }

    echo '</select>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    echo
        get_form_row_text_input(
            'tsubset_answers',
            $l['w_num_answers'],
            $l['h_num_answers'],
            '',
            $tsubset_answers,
            '^([0-9]*)$',
            20,
            false,
            false,
            false,
        )
    ;

    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    F_submit_button('addquestion', $l['w_add_questions'], $l['h_add_questions']);
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    echo '<div class="rowl" title="' . $l['h_subjects'] . '">' . K_NEWLINE;
    echo '<br />' . K_NEWLINE;
    echo '<div class="preview">' . K_NEWLINE;
    $subjlist = '';
    $sql = 'SELECT * FROM ' . K_TABLE_TEST_SUBJSET . '
		WHERE tsubset_test_id=\'' . f_general_string($test_id) . '\'
		ORDER BY tsubset_id';
    if ($r = f_legacy_db_query_result(F_db_query($sql, $db))) {
        $subjcount = 0;
        while (($m = f_tce_edit_test_subject_set_row(F_db_fetch_array($r))) !== null) {
            $subjlist .= '<li>';
            $subjects_list = '';
            $sqls =
                'SELECT subject_id,subject_name
				FROM '
                . K_TABLE_SUBJECTS
                . ', '
                . K_TABLE_SUBJECT_SET
                . '
				WHERE subject_id=subjset_subject_id
					AND subjset_tsubset_id=\''
                . $m['tsubset_id']
                . '\'
				ORDER BY subject_name';
            if ($rs = f_legacy_db_query_result(F_db_query($sqls, $db))) {
                while (($ms = f_tce_edit_test_subject_row(F_db_fetch_array($rs))) !== null) {
                    $subjects_list .=
                        '<a href="tce_edit_subject.php?subject_id='
                        . $ms['subject_id']
                        . '" title="'
                        . $l['t_subjects_editor']
                        . '">'
                        . htmlspecialchars($ms['subject_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</a>, ';
                }
            } else {
                F_display_db_error();
            }

            // remove last comma + space
            $subjlist .= substr($subjects_list, 0, -2);
            $subjlist .= '<br />' . K_NEWLINE;
            $subjlist .=
                '<input type="checkbox" name="selectsubject'
                . $subjcount
                . '" id="selectsubject'
                . $subjcount
                . '" value="'
                . $m['tsubset_id']
                . '" title="'
                . $l['w_select']
                . '" />';
            ++$subjcount;
            $subjlist .=
                '<abbr class="offbox" title="' . $l['h_num_questions'] . '">' . $m['tsubset_quantity'] . '</abbr> ';
            $subjlist .= '<abbr class="offbox" title="' . $l['h_question_type'] . '">';
            if ($m['tsubset_type'] > 0) {
                $subjlist .= $qtype[$m['tsubset_type'] - 1];
            } else {
                // all question types
                $subjlist .= '*';
            }

            $subjlist .= '</abbr> ';
            $subjlist .=
                '<abbr class="offbox" title="'
                . $l['h_question_difficulty']
                . '">'
                . $m['tsubset_difficulty']
                . '</abbr> ';
            $subjlist .=
                '<abbr class="offbox" title="' . $l['h_num_answers'] . '">' . $m['tsubset_answers'] . '</abbr> ';
            $subjlist .= '</li>' . K_NEWLINE;

            // update test_max_score
            $test_max_score_new += $test_score_right * $m['tsubset_difficulty'] * $m['tsubset_quantity'];
            if ($test_max_score_new !== $test_max_score) {
                $test_max_score = $test_max_score_new;
                // update max score on test table
                $sqlup =
                    'UPDATE '
                    . K_TABLE_TESTS
                    . ' SET test_max_score='
                    . $test_max_score
                    . ' WHERE test_id='
                    . f_general_string($test_id)
                    . '';
                if (!($rup = f_legacy_db_query_result(F_db_query($sqlup, $db)))) {
                    F_display_db_error(false);
                }
            }
        }

        if ($subjcount > 0) {
            echo '<ul>' . K_NEWLINE . $subjlist . '</ul>' . K_NEWLINE;
            echo '<input type="hidden" name="subjcount" id="subjcount" value="' . $subjcount . '" />';
            F_submit_button('deletesubject', $l['w_delete'], $l['h_delete']);
            echo '<br />' . K_NEWLINE;
        }
    } else {
        F_display_db_error();
    }

    echo '&nbsp;' . K_NEWLINE;

    echo $l['w_max_score'] . ': ' . $test_max_score_new;
    echo '<input type="hidden" name="test_max_score" id="test_max_score" value="' . $test_max_score_new . '" />';

    echo '</div>' . K_NEWLINE;
    echo '<br /><br />' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    echo '</fieldset>' . K_NEWLINE;

    echo '<div class="row"><br /></div>' . K_NEWLINE;

    if ($test_max_score_new > 0) {
        echo '<div class="row">' . K_NEWLINE;
        echo '<span class="label">' . K_NEWLINE;
        echo '<label for="test_num">' . $l['w_pdf_offline_test'] . '</label>' . K_NEWLINE;
        echo '</span>' . K_NEWLINE;
        echo '<span class="formw">' . K_NEWLINE;
        echo
            '<input type="text" name="test_num" id="test_num" value="'
                . $test_num
                . '" size="4" maxlength="10" title="'
                . $l['h_pdf_offline_test']
                . '" />'
                . K_NEWLINE
        ;
        echo
            '<a href="tce_pdf_testgen.php?test_id='
                . f_general_string($test_id)
                . '&amp;num='
                . $test_num
                . '" title="'
                . $l['h_pdf_offline_test']
                . '" class="xmlbutton" onclick="pdfWindow=window.open(\'tce_pdf_testgen.php?test_id='
                . f_general_string($test_id)
                . '&amp;num=\' + document.getElementById(\'form_testeditor\').test_num.value + \'\',\'pdfWindow\',\'dependent,menubar=yes,resizable=yes,scrollbars=yes,status=yes,toolbar=yes\'); return false;">'
                . $l['w_generate']
                . '</a>'
        ;
        echo '</span>&nbsp;' . K_NEWLINE;
        echo '</div>' . K_NEWLINE;
    }
}

echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;

echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_test'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

// javascript controls
echo '<script type="text/javascript">' . K_NEWLINE;
echo '//<![CDATA[' . K_NEWLINE;
echo 'function JF_check_random_boxes() {' . K_NEWLINE;
echo
    " if (document.getElementById('test_random_questions_select').checked==true){document.getElementById('test_random_questions_order').checked=true;}"
        . K_NEWLINE
;
echo
    " if ((document.getElementById('test_random_questions_order').checked==false)&&(document.getElementById('test_random_questions_select').checked==true)){document.getElementById('test_random_questions_order').checked=true;}"
        . K_NEWLINE
;
echo
    ' if (document.getElementById(\'test_random_questions_order\').checked==false){document.getElementById(\'select_questions_order_mode\').style.visibility="visible";}else{document.getElementById(\'select_questions_order_mode\').style.visibility="hidden";}'
        . K_NEWLINE
;
echo
    " if (document.getElementById('test_random_answers_select').checked==true){document.getElementById('test_random_answers_order').checked=true;}"
        . K_NEWLINE
;
echo
    " if ((document.getElementById('test_random_answers_order').checked==false)&&(document.getElementById('test_random_answers_select').checked==true)){document.getElementById('test_random_answers_order').checked=true;}"
        . K_NEWLINE
;
echo
    ' if (document.getElementById(\'test_random_answers_order\').checked==false){document.getElementById(\'select_answers_order_mode\').style.visibility="visible";}else{document.getElementById(\'select_answers_order_mode\').style.visibility="hidden";}'
        . K_NEWLINE
;
echo '}' . K_NEWLINE;
echo 'JF_check_random_boxes();' . K_NEWLINE;
echo 'function JF_filter_user_groups() {' . K_NEWLINE;
echo " var filter=document.getElementById('user_groups_filter');" . K_NEWLINE;
echo " var groups=document.getElementById('user_groups');" . K_NEWLINE;
echo ' if (!filter || !groups) {return;}' . K_NEWLINE;
echo ' var query=filter.value.trim().toLocaleLowerCase();' . K_NEWLINE;
echo ' for (var i=0;i<groups.options.length;i++) {' . K_NEWLINE;
echo '  var option=groups.options[i];' . K_NEWLINE;
echo '  var visible=(query==="" || option.text.toLocaleLowerCase().indexOf(query)!==-1);' . K_NEWLINE;
echo '  option.hidden=!visible;' . K_NEWLINE;
echo '  option.style.display=visible?"":"none";' . K_NEWLINE;
echo ' }' . K_NEWLINE;
echo '}' . K_NEWLINE;
echo
    "document.getElementById('user_groups_filter').addEventListener('input', JF_filter_user_groups);"
        . K_NEWLINE
;
echo '//]]>' . K_NEWLINE;
echo '</script>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/**
 * @return array{
 *     tsubset_id:int|numeric-string,tsubset_quantity:int|numeric-string,tsubset_type:int|numeric-string,
 *     tsubset_difficulty:int|numeric-string,tsubset_answers:int|numeric-string
 * }|null
 */
function f_tce_edit_test_subject_set_row(mixed $row): ?array
{
    /**
     * @var array{
     *     tsubset_id:int|numeric-string,tsubset_quantity:int|numeric-string,tsubset_type:int|numeric-string,
     *     tsubset_difficulty:int|numeric-string,tsubset_answers:int|numeric-string
     * }|null $row
     */
    return $row;
}

/**
 * @return array{
 *     module_id:int|string,module_name:string,subject_id:int|string,subject_name:string
 * }|null
 */
function f_tce_edit_test_module_subject_row(mixed $row): ?array
{
    /**
     * @var array{
     *     module_id:int|string,module_name:string,subject_id:int|string,subject_name:string
     * }|null $row
     */
    return $row;
}

/** @return array{subject_id:int|string,subject_name:string}|null */
function f_tce_edit_test_subject_row(mixed $row): ?array
{
    /** @var array{subject_id:int|string,subject_name:string}|null $row */
    return $row;
}

/** @return array{subject_id:int|string}|null */
function f_tce_edit_test_subject_id_row(mixed $row): ?array
{
    /** @var array{subject_id:int|string}|null $row */
    return $row;
}

/**
 * @return array{
 *     test_id:int|numeric-string,test_begin_time:string,test_end_time:string,test_name:string
 * }|null
 */
function f_tce_edit_test_list_row(mixed $row): ?array
{
    /**
     * @var array{
     *     test_id:int|numeric-string,test_begin_time:string,test_end_time:string,test_name:string
     * }|null $row
     */
    return $row;
}

/**
 * @return array{
 *     test_id:int|string,test_name:string,test_description:string|null,test_begin_time:string,test_end_time:string,
 *     test_duration_time:int|numeric-string,test_ip_range:string,test_results_to_users:mixed,
 *     test_report_to_users:mixed,test_score_right:int|float|numeric-string,
 *     test_score_wrong:int|float|numeric-string,test_score_unanswered:int|float|numeric-string,
 *     test_max_score:int|float|numeric-string,test_score_threshold:int|float|numeric-string,
 *     test_random_questions_select:mixed,test_random_questions_order:mixed,test_questions_order_mode:int|string,
 *     test_random_answers_select:mixed,test_random_answers_order:mixed,test_answers_order_mode:int|string,
 *     test_comment_enabled:mixed,test_menu_enabled:mixed,test_noanswer_enabled:mixed,test_mcma_radio:mixed,
 *     test_repeatable:int|string,test_mcma_partial_score:mixed,test_logout_on_timeout:mixed,test_password:string|null
 * }|null
 */
function f_tce_edit_test_record_row(mixed $row): ?array
{
    /**
     * @var array{
     *     test_id:int|string,test_name:string,test_description:string|null,test_begin_time:string,test_end_time:string,
     *     test_duration_time:int|numeric-string,test_ip_range:string,test_results_to_users:mixed,
     *     test_report_to_users:mixed,test_score_right:int|float|numeric-string,
     *     test_score_wrong:int|float|numeric-string,test_score_unanswered:int|float|numeric-string,
     *     test_max_score:int|float|numeric-string,test_score_threshold:int|float|numeric-string,
     *     test_random_questions_select:mixed,test_random_questions_order:mixed,test_questions_order_mode:int|string,
     *     test_random_answers_select:mixed,test_random_answers_order:mixed,test_answers_order_mode:int|string,
     *     test_comment_enabled:mixed,test_menu_enabled:mixed,test_noanswer_enabled:mixed,test_mcma_radio:mixed,
     *     test_repeatable:int|string,test_mcma_partial_score:mixed,test_logout_on_timeout:mixed,
     *     test_password:string|null
     * }|null $row
     */
    return $row;
}
