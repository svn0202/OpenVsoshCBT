<?php

/**
 * Persist parsed TMF Word questions in one database transaction.
 *
 * Set $commit to false in integration tests to exercise the real SQL while
 * guaranteeing that the transaction is rolled back.
 *
 * @return array{module_name:string,subject_name:string,questions:int,answers:int,committed:bool}
 */
function F_tmf_import_word_questions(array $data, $commit = true)
{
    global $db;
    if (empty($data['module']) || empty($data['topic']) || empty($data['questions'])) {
        throw new TmfWordImportException('Неполные данные импорта.');
    }

    $module_name = trim($data['module']);
    $subject_name = trim($data['topic']);
    if (mb_strlen($module_name, 'UTF-8') > 255 || mb_strlen($subject_name, 'UTF-8') > 255) {
        throw new TmfWordImportException('Название модуля или темы длиннее 255 символов.');
    }

    $user_id = intval($_SESSION['session_user_id']);
    $module_id = 0;
    $question_count = 0;
    $answer_count = 0;

    if (!F_db_query('START TRANSACTION', $db)) {
        throw new TmfWordImportException('Не удалось начать транзакцию.');
    }
    try {
        $escaped_module = F_escape_sql($db, $module_name, false);
        $sql =
            'SELECT module_id FROM '
            . K_TABLE_MODULES
            . ' WHERE module_name=\''
            . $escaped_module
            . '\' ORDER BY module_id LIMIT 1';
        $result = F_db_query($sql, $db);
        if (
            $result && ($row = F_db_fetch_array($result))
            && f_is_authorized_user(K_TABLE_MODULES, 'module_id', $row['module_id'], 'module_user_id')
        ) {
            $module_id = intval($row['module_id']);
        }
        if ($module_id === 0) {
            $sql =
                'INSERT INTO '
                . K_TABLE_MODULES
                . ' (module_name,module_enabled,module_user_id) VALUES ('
                . '\''
                . $escaped_module
                . '\',\'1\','
                . $user_id
                . ')';
            if (!F_db_query($sql, $db)) {
                throw new TmfWordImportException('Не удалось создать модуль.');
            }
            $module_id = intval(F_db_insert_id($db, K_TABLE_MODULES, 'module_id'));
        }

        $base_subject_name = $subject_name;
        $suffix = date('Y-m-d_H-i-s');
        $attempt = 0;
        do {
            $escaped_subject = F_escape_sql($db, $subject_name, false);
            $sql =
                'SELECT subject_id FROM '
                . K_TABLE_SUBJECTS
                . ' WHERE subject_module_id='
                . $module_id
                . ' AND subject_name=\''
                . $escaped_subject
                . '\' LIMIT 1';
            $existing = F_db_query($sql, $db);
            $collision = $existing && F_db_fetch_array($existing);
            if ($collision) {
                ++$attempt;
                $subject_name = $base_subject_name . ' ' . $suffix . ($attempt > 1 ? '-' . $attempt : '');
            }
        } while ($collision);

        $escaped_subject = F_escape_sql($db, $subject_name, false);
        $sql =
            'INSERT INTO '
            . K_TABLE_SUBJECTS
            . ' (subject_name,subject_description,subject_enabled,subject_user_id,subject_module_id) VALUES ('
            . '\''
            . $escaped_subject
            . '\',NULL,\'1\','
            . $user_id
            . ','
            . $module_id
            . ')';
        if (!F_db_query($sql, $db)) {
            throw new TmfWordImportException('Не удалось создать тему.');
        }
        $subject_id = intval(F_db_insert_id($db, K_TABLE_SUBJECTS, 'subject_id'));

        foreach ($data['questions'] as $position => $question) {
            $description = F_escape_sql($db, $question['description'], false);
            $sql =
                'INSERT INTO '
                . K_TABLE_QUESTIONS
                . ' ('
                . 'question_subject_id,question_description,question_explanation,question_type,'
                . 'question_difficulty,question_enabled,question_position,question_timer,'
                . 'question_fullscreen,question_inline_answers,question_auto_next) VALUES ('
                . $subject_id
                . ',\''
                . $description
                . '\',NULL,'
                . intval($question['type'])
                . ','
                . intval($question['difficulty'])
                . ',\'1\','
                . ($position + 1)
                . ','
                . intval($question['timer'])
                . ',\''
                . intval($question['fullscreen'])
                . '\',\''
                . intval($question['inline_answers'])
                . '\',\''
                . intval($question['auto_next'])
                . '\')';
            if (!F_db_query($sql, $db)) {
                throw new TmfWordImportException(
                    'Не удалось создать вопрос ' . intval($question['source_number']) . '.',
                );
            }
            $question_id = intval(F_db_insert_id($db, K_TABLE_QUESTIONS, 'question_id'));
            ++$question_count;

            foreach ($question['answers'] as $answer_position => $answer) {
                $is_right = in_array(strtoupper($answer['key']), $question['right_keys'], true) ? 1 : 0;
                $weight = $answer['weight'] === null ? 'NULL' : (string) intval($answer['weight']);
                $answer_description = F_escape_sql($db, $answer['description'], false);
                $keyboard_key = ord(strtoupper($answer['key']));
                $sql =
                    'INSERT INTO '
                    . K_TABLE_ANSWERS
                    . ' ('
                    . 'answer_question_id,answer_description,answer_explanation,answer_isright,'
                    . 'answer_enabled,answer_position,answer_keyboard_key,answer_weight) VALUES ('
                    . $question_id
                    . ',\''
                    . $answer_description
                    . '\',NULL,\''
                    . $is_right
                    . '\',\'1\','
                    . ($answer_position + 1)
                    . ','
                    . $keyboard_key
                    . ','
                    . $weight
                    . ')';
                if (!F_db_query($sql, $db)) {
                    throw new TmfWordImportException('Не удалось создать вариант ответа.');
                }
                ++$answer_count;
            }
        }

        if ($commit) {
            if (!F_db_query('COMMIT', $db)) {
                throw new TmfWordImportException('Не удалось зафиксировать импорт.');
            }
        } else {
            if (!F_db_query('ROLLBACK', $db)) {
                throw new TmfWordImportException('Не удалось откатить тестовый импорт.');
            }
        }
    } catch (Throwable $exception) {
        F_db_query('ROLLBACK', $db);
        throw $exception;
    }

    return array(
        'module_name' => $module_name,
        'subject_name' => $subject_name,
        'questions' => $question_count,
        'answers' => $answer_count,
        'committed' => (bool) $commit,
    );
}
