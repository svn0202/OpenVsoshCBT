<?php

//============================================================+
// File name   : tce_functions_questions.php
// Begin       : 2008-11-26
// Last Update : 2023-11-30
//
// Description : Functions to manipulate questions.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to manipulate questions.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2008-11-26
 */

/**
 * Enable/Disable selected question
 * @author Nicola Asuni
 * @since 2008-11-26
 * @param $question_id (int) question ID
 * @param $enabled (boolean) if true enables question, false otherwise
 */
function f_question_set_enabled(mixed $question_id, mixed $enabled = true): void
{
    global $l, $db;
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    $question_id = (int) $question_id;
    $sql =
        'UPDATE '
        . K_TABLE_QUESTIONS
        . ' SET
		question_enabled=\''
        . (int) $enabled
        . '\'
		WHERE question_id='
        . $question_id
        . '';
    if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
        F_display_db_error(false);
    }
}

/**
 * Get question position
 * @author Nicola Asuni
 * @since 2008-11-26
 * @param $question_id (int) question ID
 * @return int|string|null question position
 */
function f_question_get_position(mixed $question_id): mixed
{
    global $l, $db;
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    $question_id = (int) $question_id;
    $question_position = 0;
    $sql = 'SELECT question_position
		FROM ' . K_TABLE_QUESTIONS . '
		WHERE question_id=' . $question_id . '
		LIMIT 1';
    $r = f_tmf_questions_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tmf_questions_row(F_db_fetch_array($r));
        if ($m !== null) {
            /** @var array{question_position: int|string} $m */
            $question_position = $m['question_position'];
        }
    } else {
        F_display_db_error();
    }

    return $question_position;
}

/**
 * Get question data
 * @author Nicola Asuni
 * @since 2008-11-26
 * @param $question_id (int) question ID
 * @return array<array-key, mixed>|false selected question data, false in case of error
 */
function f_question_get_data(mixed $question_id): array|false
{
    global $l, $db;
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    $question_id = (int) $question_id;
    /** @var callable(mixed): (\mysqli_result|\PgSql\Result|resource|bool|string) $normalize_result */
    $normalize_result = static function (mixed $result): mixed {
        if (
            is_bool($result)
            || is_string($result)
            || is_resource($result)
            || $result instanceof \mysqli_result
            || $result instanceof \PgSql\Result
        ) {
            return $result;
        }
        return false;
    };
    /** @var callable(mixed): ?array<array-key, mixed> $normalize_row */
    $normalize_row = static fn (mixed $row): ?array => is_array($row) ? $row : null;
    $sql = 'SELECT *
		FROM ' . K_TABLE_QUESTIONS . '
		WHERE question_id=' . $question_id . '
		LIMIT 1';
    $r = $normalize_result(F_db_query($sql, $db));
    if ($r) {
        $m = $normalize_row(F_db_fetch_array($r));
        if ($m !== null) {
            return $m;
        }
    } else {
        F_display_db_error();
    }

    return false;
}

/**
 * Delete selected question (or disable it if used)
 * @author Nicola Asuni
 * @since 2008-11-26
 * @param $question_id (int) question ID
 * @param $subject_id (int) subject ID
 */
function f_question_delete(mixed $question_id, mixed $subject_id): void
{
    global $l, $db;
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    $question_id = (int) $question_id;
    $subject_id = (int) $subject_id;
    // check if this record is used (test_log)
    if (!F_check_unique(K_TABLE_TESTS_LOGS, 'testlog_question_id=' . $question_id . '')) {
        f_question_set_enabled($question_id, false);
    } else {
        $sql = 'START TRANSACTION';
        if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
            F_display_db_error();
        }

        // get question position (if defined)
        $question_position = f_question_get_position($question_id);
        // delete question
        $sql = 'DELETE FROM ' . K_TABLE_QUESTIONS . ' WHERE question_id=' . $question_id . '';
        if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
            F_display_db_error(false);
            F_db_query('ROLLBACK', $db); // rollback transaction
        } else {
            // adjust questions ordering
            if ((float) $question_position > 0) {
                $sql =
                    'UPDATE '
                    . K_TABLE_QUESTIONS
                    . ' SET
					question_position=question_position-1
					WHERE question_subject_id='
                    . $subject_id
                    . '
						AND question_position>'
                    . (string) $question_position
                    . '';
                if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
                    F_display_db_error(false);
                    F_db_query('ROLLBACK', $db); // rollback transaction
                }
            }

            $sql = 'COMMIT';
            if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
                F_display_db_error();
            }
        }
    }
}

/**
 * Copy selected question to another topic
 * @author Nicola Asuni
 * @since 2008-11-26
 * @param $question_id (int) question ID
 * @param $new_subject_id (int) new subject ID
 */
function f_question_copy(mixed $question_id, mixed $new_subject_id): void
{
    global $l, $db;
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    $question_id = (int) $question_id;
    $new_subject_id = (int) $new_subject_id;
    // check authorization
    $sql = 'SELECT subject_module_id FROM ' . K_TABLE_SUBJECTS . ' WHERE subject_id=' . $new_subject_id . ' LIMIT 1';
    $r = f_tmf_questions_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tmf_questions_row(F_db_fetch_array($r));
        if ($m !== null) {
            /** @var array{subject_module_id: scalar|null} $m */
            $subject_module_id = $m['subject_module_id'];
            // check user's authorization for parent module
            if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $subject_module_id, 'module_user_id')) {
                return;
            }
        }
    } else {
        F_display_db_error();
        return;
    }
    /** @var scalar|null $subject_module_id */

    $q = F_question_get_data($question_id);
    if ($q !== false) {
        /** @var array{
         *     question_description: scalar|null,
         *     question_explanation: scalar|null,
         *     question_type: int|string,
         *     question_difficulty: int|string,
         *     question_enabled: int|string,
         *     question_position: int|string,
         *     question_timer: int|string,
         *     question_fullscreen: int|string,
         *     question_inline_answers: int|string,
         *     question_auto_next: int|string,
         *     question_shuffle_answers: int|string
         * } $q
         */
        $database_type = f_tmf_questions_database_type();
        if (strcmp($database_type, 'ORACLE') === 0) {
            $chksql =
                "dbms_lob.instr(question_description,'" . F_escape_sql($db, $q['question_description']) . "',1,1)>0";
        } elseif ($database_type === 'MYSQL' && f_tmf_questions_mysql_binary_uniquity()) {
            $chksql =
                "question_description='"
                . F_escape_sql($db, $q['question_description'])
                . "' COLLATE "
                . (defined('K_MYSQL_QA_BIN_COLLATION') ? K_MYSQL_QA_BIN_COLLATION : 'utf8_bin');
        } else {
            $chksql = "question_description='" . F_escape_sql($db, $q['question_description']) . "'";
        }

        if (F_check_unique(K_TABLE_QUESTIONS, $chksql . ' AND question_subject_id=' . $new_subject_id . '')) {
            $sql = 'START TRANSACTION';
            if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
                F_display_db_error(false);
                return;
            }

            // adjust questions ordering
            if ($q['question_position'] > 0) {
                $sql =
                    'UPDATE '
                    . K_TABLE_QUESTIONS
                    . ' SET
					question_position=question_position+1
					WHERE question_subject_id='
                    . $new_subject_id
                    . '
						AND question_position>='
                    . $q['question_position']
                    . '';
                if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
                    F_display_db_error(false);
                    F_db_query('ROLLBACK', $db); // rollback transaction
                }
            }

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
                . $new_subject_id
                . ',
				\''
                . F_escape_sql($db, $q['question_description'])
                . '\',
				\''
                . F_escape_sql($db, $q['question_explanation'])
                . '\',
				\''
                . $q['question_type']
                . '\',
				\''
                . $q['question_difficulty']
                . '\',
				\''
                . $q['question_enabled']
                . '\',
				'
                . f_zero_to_null($q['question_position'])
                . ',
				\''
                . $q['question_timer']
                . '\',
				\''
                . $q['question_fullscreen']
                . '\',
				\''
                . $q['question_inline_answers']
                . '\',
				\''
                . $q['question_auto_next']
                . '\',
				\''
                . $q['question_shuffle_answers']
                . '\'
				)';
            /** @var int|string $new_question_id */
            if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
                F_display_db_error(false);
            } else {
                $new_question_id = F_db_insert_id($db, K_TABLE_QUESTIONS, 'question_id');
            }

            // copy associated answers
            $sql = 'SELECT *
				FROM ' . K_TABLE_ANSWERS . '
				WHERE answer_question_id=' . $question_id . '';
            $r = f_tmf_questions_query_result(F_db_query($sql, $db));
            if ($r) {
                while (($m = f_tmf_questions_row(F_db_fetch_array($r))) !== null) {
                    /** @var array{
                     *     answer_description: scalar|null,
                     *     answer_explanation: scalar|null,
                     *     answer_isright: int|string,
                     *     answer_enabled: int|string,
                     *     answer_position: scalar|null,
                     *     answer_keyboard_key: scalar|null,
                     *     answer_weight: scalar|null
                     * } $m
                     */
                    $sqli =
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
                        . $new_question_id
                        . ',
						\''
                        . F_escape_sql($db, $m['answer_description'])
                        . '\',
						\''
                        . F_escape_sql($db, $m['answer_explanation'])
                        . '\',
						\''
                        . $m['answer_isright']
                        . '\',
						\''
                        . $m['answer_enabled']
                        . '\',
						'
                        . f_zero_to_null($m['answer_position'])
                        . ',
						'
                        . f_empty_to_null($m['answer_keyboard_key'])
                        . ',
							'
                        . ($m['answer_weight'] === null ? 'NULL' : (string) (int) $m['answer_weight'])
                        . '
							)';
                    if (!f_tmf_questions_query_succeeded(F_db_query($sqli, $db))) {
                        F_display_db_error(false);
                        F_db_query('ROLLBACK', $db); // rollback transaction
                    }
                }
            } else {
                F_display_db_error();
            }

            $sql = 'COMMIT';
            if (!f_tmf_questions_query_succeeded(F_db_query($sql, $db))) {
                F_display_db_error(false);
                return;
            }
        }
    }
}

/** @return array<array-key, mixed>|null */
function f_tmf_questions_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

function f_tmf_questions_query_succeeded(mixed $result): bool
{
    return (bool) f_tmf_questions_query_result($result);
}

/** @return \mysqli_result|\PgSql\Result|resource|bool|string */
function f_tmf_questions_query_result(mixed $result): mixed
{
    if (
        is_bool($result)
        || is_string($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
}

function f_tmf_questions_database_type(): string
{
    return K_DATABASE_TYPE;
}

function f_tmf_questions_mysql_binary_uniquity(): bool
{
    /** @mago-expect analysis:redundant-logical-operation */
    return defined('K_MYSQL_QA_BIN_UNIQUITY') && K_MYSQL_QA_BIN_UNIQUITY;
}
