<?php

require_once __DIR__ . '/tce_functions_tmf_question.php';

/**
 * Recalculate one recorded objective answer from its persisted selections.
 *
 * @param array<string,mixed> $test
 * @param array<string,mixed> $question
 * @param array<int,array<string,mixed>> $answers
 */
function f_tmf_recorded_answer_score(array $test, array $question, array $answers): float
{
    $type = (int) $question['question_type'];
    $difficulty = (float) $question['question_difficulty'];
    $right = (float) $test['test_score_right'] * $difficulty;
    $wrong = (float) $test['test_score_wrong'] * $difficulty;
    $unanswered = (float) $test['test_score_unanswered'] * $difficulty;
    if ($type === 1) {
        foreach ($answers as $answer) {
            if ((int) $answer['logansw_selected'] === 1) {
                return round(F_tmf_answer_score(
                    $answer['answer_weight'] ?? null,
                    f_get_boolean($answer['answer_isright']),
                    $right,
                    $wrong,
                ), 3);
            }
        }
        return round($unanswered, 3);
    }

    $total = 0.0;
    foreach ($answers as $answer) {
        $selected = (int) $answer['logansw_selected'];
        if ($type === 2) {
            if ($selected === -1) {
                $total += $unanswered;
            } elseif (
                f_get_boolean($answer['answer_isright']) && $selected === 1
                || !f_get_boolean($answer['answer_isright']) && $selected === 0
            ) {
                $total += $right;
            } else {
                $total += $wrong;
            }
        } elseif ($type === 4 || $type === 5) {
            if ($selected !== 1 || (int) $answer['logansw_position'] < 1) {
                $total += $unanswered;
            } elseif ((int) $answer['logansw_position'] === (int) $answer['answer_position']) {
                $total += $right;
            } else {
                $total += $wrong;
            }
        }
    }
    $count = count($answers);
    if ($count < 1) {
        return round($unanswered, 3);
    }
    if (f_get_boolean($test['test_mcma_partial_score'])) {
        return round($total / $count, 3);
    }
    if ($total >= ($right * $count)) {
        return round($right, 3);
    }
    if ($total === ($unanswered * $count)) {
        return round($unanswered, 3);
    }
    return round($wrong, 3);
}

/**
 * Regrade objective and keyed short answers. Essays without enabled correct keys are deliberately
 * excluded so manually assigned essay scores and comments remain untouched.
 */
function f_tmf_regrade_test(int $test_id): int
{
    global $db;
    $test_result = F_db_query(
        'SELECT test_score_right,test_score_wrong,test_score_unanswered,test_mcma_partial_score'
        . ' FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . $test_id . ' LIMIT 1',
        $db,
    );
    if (!$test_result || !($test = F_db_fetch_array($test_result))) {
        throw new RuntimeException('Тест не найден.');
    }
    $logs_result = F_db_query(
        'SELECT tl.testlog_id,tl.testlog_answer_text,tl.testlog_change_time,'
        . 'q.question_id,q.question_type,q.question_difficulty,q.question_description'
        . ' FROM ' . K_TABLE_TESTS_LOGS . ' tl'
        . ' INNER JOIN ' . K_TABLE_TEST_USER . ' tu ON tu.testuser_id=tl.testlog_testuser_id'
        . ' INNER JOIN ' . K_TABLE_QUESTIONS . ' q ON q.question_id=tl.testlog_question_id'
        . ' WHERE tu.testuser_test_id=' . $test_id,
        $db,
    );
    if (!$logs_result || !F_db_query('START TRANSACTION', $db)) {
        throw new RuntimeException('Не удалось начать пересчёт.');
    }
    $updated = 0;
    try {
        while ($log = F_db_fetch_array($logs_result)) {
            if ((int) $log['question_type'] === 3) {
                $keys_result = F_db_query(
                    'SELECT answer_description,answer_weight FROM ' . K_TABLE_ANSWERS
                    . ' WHERE answer_question_id=' . (int) $log['question_id']
                    . " AND answer_enabled='1' AND answer_isright='1' ORDER BY answer_position",
                    $db,
                );
                if (!$keys_result) {
                    throw new RuntimeException('Не удалось прочитать ключи краткого ответа.');
                }
                $keys = [];
                while ($key = F_db_fetch_array($keys_result)) {
                    $keys[] = $key;
                }
                if ($keys === []) {
                    continue;
                }
                $right = (float) $test['test_score_right'] * (float) $log['question_difficulty'];
                $wrong = (float) $test['test_score_wrong'] * (float) $log['question_difficulty'];
                $unanswered = (float) $test['test_score_unanswered'] * (float) $log['question_difficulty'];
                $text = (string) ($log['testlog_answer_text'] ?? '');
                if ($log['testlog_change_time'] === null || $text === '') {
                    $score = $unanswered;
                } else {
                    $options = F_tmf_question_options((string) $log['question_description']);
                    $score = F_tmf_short_answer_score(
                        $text,
                        $keys,
                        K_SHORT_ANSWERS_BINARY,
                        (int) $options['similarity_threshold'],
                        $right,
                        $wrong,
                    );
                }
                $score_sql = $score === null ? 'NULL' : number_format($score, 3, '.', '');
                if (!F_db_query(
                    'UPDATE ' . K_TABLE_TESTS_LOGS . ' SET testlog_score=' . $score_sql
                    . ' WHERE testlog_id=' . (int) $log['testlog_id'],
                    $db,
                )) {
                    throw new RuntimeException('Не удалось записать пересчитанный балл.');
                }
                ++$updated;
                continue;
            }
            $answers_result = F_db_query(
                'SELECT la.logansw_selected,la.logansw_position,a.answer_position,'
                . 'a.answer_isright,a.answer_weight FROM ' . K_TABLE_LOG_ANSWER . ' la'
                . ' INNER JOIN ' . K_TABLE_ANSWERS . ' a ON a.answer_id=la.logansw_answer_id'
                . ' WHERE la.logansw_testlog_id=' . (int) $log['testlog_id']
                . ' ORDER BY la.logansw_order',
                $db,
            );
            if (!$answers_result) {
                throw new RuntimeException('Не удалось прочитать сохранённые ответы.');
            }
            $answers = [];
            while ($answer = F_db_fetch_array($answers_result)) {
                $answers[] = $answer;
            }
            $score = F_tmf_recorded_answer_score($test, $log, $answers);
            if (!F_db_query(
                'UPDATE ' . K_TABLE_TESTS_LOGS . ' SET testlog_score='
                . number_format($score, 3, '.', '') . ' WHERE testlog_id=' . (int) $log['testlog_id'],
                $db,
            )) {
                throw new RuntimeException('Не удалось записать пересчитанный балл.');
            }
            ++$updated;
        }
        if (!F_db_query('COMMIT', $db)) {
            throw new RuntimeException('Не удалось завершить пересчёт.');
        }
    } catch (Throwable $exception) {
        F_db_query('ROLLBACK', $db);
        throw $exception;
    }
    return $updated;
}
