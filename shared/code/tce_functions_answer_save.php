<?php

/**
 * Versioned, idempotent persistence for an answer submitted during a test.
 */

const TMF_ANSWER_OPERATION_PATTERN = '/^[a-f0-9]{32}$/';

/**
 * Return answer-save labels with language-aware defaults for installations
 * whose instance-local translation file predates the save-status interface.
 *
 * @param array<array-key, string> $translations
 * @return array{saving:string, saved:string, error:string, conflict:string,
 *     unsaved:string, retrying:string, save:string}
 */
function f_tmf_answer_status_labels(array $translations): array
{
    $labels = match (strtolower($translations['a_meta_language'] ?? 'en')) {
        'ru' => [
            'saving' => 'Сохраняется…',
            'saved' => 'Сохранено',
            'error' => 'Не сохранено',
            'conflict' => 'Более новый ответ уже сохранён. Проверьте текущий ответ и повторите попытку.',
            'unsaved' => 'Есть несохранённые изменения',
            'retrying' => 'Связь потеряна. Повтор {attempt} из {maximum} через {seconds} с…',
            'save' => 'Сохранить',
        ],
        default => [
            'saving' => 'Saving…',
            'saved' => 'Saved',
            'error' => 'Not saved',
            'conflict' => 'A newer answer was already saved. Review the current answer and try again.',
            'unsaved' => 'Unsaved changes',
            'retrying' => 'Connection lost. Retry {attempt} of {maximum} in {seconds} s…',
            'save' => 'Save',
        ],
    };
    $translation_keys = [
        'saving' => 'ov_answer_saving',
        'saved' => 'ov_answer_saved',
        'error' => 'ov_answer_not_saved',
        'conflict' => 'ov_answer_save_conflict',
        'unsaved' => 'ov_answer_unsaved',
        'retrying' => 'ov_answer_retrying',
        'save' => 'ov_save',
    ];
    foreach ($translation_keys as $label => $translation_key) {
        if (array_key_exists($translation_key, $translations)) {
            $labels[$label] = $translations[$translation_key];
        }
    }
    return $labels;
}

function f_tmf_answer_operation_is_valid(string $operation_id): bool
{
    return preg_match(TMF_ANSWER_OPERATION_PATTERN, $operation_id) === 1;
}

/**
 * @return string "save", "duplicate", "conflict" or "invalid"
 */
function f_tmf_answer_save_decision(
    int $current_version,
    ?string $current_operation,
    int $expected_version,
    string $operation_id,
): string {
    if ($expected_version < 0 || !f_tmf_answer_operation_is_valid($operation_id)) {
        return 'invalid';
    }
    if ($current_operation !== null && hash_equals($current_operation, $operation_id)) {
        return 'duplicate';
    }
    if ($expected_version !== $current_version) {
        return 'conflict';
    }
    return 'save';
}

/**
 * Save an answer atomically and reject stale or replayed operations.
 *
 * @param array<array-key, mixed> $answer_positions
 * @return array{status:string,version:int,live_score?:float}
 */
function f_tmf_save_question_answer(
    int $test_id,
    int $testlog_id,
    array $answer_positions,
    string $answer_text,
    int $reaction_time,
    int $expected_version,
    string $operation_id,
): array {
    require_once '../config/tce_config.php';
    global $db;
    $query_succeeded = static fn(mixed $result): bool => $result !== false;
    $normalize_row = static fn(mixed $row): ?array => is_array($row) && $row !== [] ? $row : null;

    if (!f_tmf_answer_operation_is_valid($operation_id) || $expected_version < 0) {
        return ['status' => 'invalid', 'version' => $expected_version];
    }
    if (!$query_succeeded(F_db_query('START TRANSACTION', $db))) {
        return ['status' => 'error', 'version' => $expected_version];
    }

    try {
        $sql = 'SELECT testlog_testuser_id, testlog_answer_version, testlog_answer_operation
			FROM ' . K_TABLE_TESTS_LOGS . '
			WHERE testlog_id=' . $testlog_id . '
			FOR UPDATE';
        $result = F_db_query($sql, $db);
        /** @var \mysqli_result|\PgSql\Result|false $result */
        $row = $result === false ? null : $normalize_row(F_db_fetch_array($result));
        if ($row === null) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error', 'version' => $expected_version];
        }

        $current_version = (int) ($row['testlog_answer_version'] ?? 0);
        /** @var mixed $operation_value */
        $operation_value = $row['testlog_answer_operation'] ?? null;
        $current_operation = $operation_value === null
            ? null
            : (string) $operation_value;
        $decision = F_tmf_answer_save_decision(
            $current_version,
            $current_operation,
            $expected_version,
            $operation_id,
        );
        if ($decision === 'duplicate') {
            F_db_query('COMMIT', $db);
            return ['status' => 'saved', 'version' => $current_version];
        }
        if ($decision !== 'save') {
            F_db_query('ROLLBACK', $db);
            return ['status' => $decision, 'version' => $current_version];
        }

        if (!f_update_question_log($test_id, $testlog_id, $answer_positions, $answer_text, $reaction_time)) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error', 'version' => $current_version];
        }

        $new_version = $current_version + 1;
        $testuser_id = (int) ($row['testlog_testuser_id'] ?? 0);
        $saved_at = date(K_TIMESTAMP_FORMAT);
        $sql = 'UPDATE ' . K_TABLE_TESTS_LOGS . '
			SET testlog_answer_version=' . $new_version . ",
				testlog_answer_operation='" . $operation_id . "',
				testlog_answer_saved_at='" . $saved_at . "'
			WHERE testlog_id=" . $testlog_id . '
				AND testlog_answer_version=' . $current_version;
        $activity_sql = 'UPDATE ' . K_TABLE_TEST_USER . "
			SET testuser_last_activity='" . $saved_at . "'
			WHERE testuser_id=" . $testuser_id . '
				AND testuser_status<4';
        if (!$query_succeeded(F_db_query($sql, $db))) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error', 'version' => $current_version];
        }
        if (!$query_succeeded(F_db_query($activity_sql, $db))) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error', 'version' => $current_version];
        }
        if (!$query_succeeded(F_db_query('COMMIT', $db))) {
            F_db_query('ROLLBACK', $db);
            return ['status' => 'error', 'version' => $current_version];
        }

        $response = ['status' => 'saved', 'version' => $new_version];
        if (function_exists('F_tmf_live_score')) {
            $live_score = F_tmf_live_score($test_id, $testuser_id);
            if ($live_score !== null) {
                $response['live_score'] = $live_score;
            }
        }
        return $response;
    } catch (Throwable) {
        F_db_query('ROLLBACK', $db);
        return ['status' => 'error', 'version' => $expected_version];
    }
}
