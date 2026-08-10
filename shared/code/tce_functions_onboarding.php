<?php

/**
 * Read and write the locally configured introductory tests.
 *
 * The small JSON file keeps this installation-specific choice out of the
 * upstream TCExam database schema.
 *
 * @return array{instruction_test_id: int, demo_test_id: int}
 */
function f_get_onboarding_config(): array
{
    $defaults = ['instruction_test_id' => 0, 'demo_test_id' => 0];
    $path = dirname(__DIR__) . '/config/tce_onboarding.json';
    if (!is_file($path)) {
        return $defaults;
    }

    /** @var mixed $data */
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return $defaults;
    }

    return [
        'instruction_test_id' => max(0, (int) ($data['instruction_test_id'] ?? 0)),
        'demo_test_id' => max(0, (int) ($data['demo_test_id'] ?? 0)),
    ];
}

function f_save_onboarding_config(int $instruction_test_id, int $demo_test_id): bool
{
    $path = dirname(__DIR__) . '/config/tce_onboarding.json';
    $payload = json_encode(
        [
            'instruction_test_id' => max(0, (int) $instruction_test_id),
            'demo_test_id' => max(0, (int) $demo_test_id),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );

    return file_put_contents($path, $payload . "\n", LOCK_EX) !== false;
}

/**
 * @return list<array{kind:string,eyebrow:string,label:string,test_id:int,test_name:string}>
 */
function f_get_pending_onboarding_tests(int $user_id): array
{
    global $db, $l;
    /**
     * @var array{
     *     ov_onboarding_instruction_eyebrow: mixed,
     *     ov_onboarding_instruction_label: mixed,
     *     ov_onboarding_demo_eyebrow: mixed,
     *     ov_onboarding_demo_label: mixed
     * } $l
     */
    $config = f_get_onboarding_config();
    $labels = [
        'instruction_test_id' => [
            'kind' => 'instruction',
            'eyebrow' => (string) $l['ov_onboarding_instruction_eyebrow'],
            'label' => (string) $l['ov_onboarding_instruction_label'],
        ],
        'demo_test_id' => [
            'kind' => 'demo',
            'eyebrow' => (string) $l['ov_onboarding_demo_eyebrow'],
            'label' => (string) $l['ov_onboarding_demo_label'],
        ],
    ];
    $pending = [];
    $normalize_row = static fn(mixed $row): ?array => is_array($row) && $row !== [] ? $row : null;

    foreach ($labels as $key => $meta) {
        $test_id = (int) ($config[$key] ?? 0);
        if ($test_id < 1) {
            continue;
        }
        $completed = F_count_rows(
            K_TABLE_TEST_USER,
            'WHERE testuser_test_id=' . $test_id
                . ' AND testuser_user_id=' . (int) $user_id
                . ' AND testuser_status>=4',
        );
        if ($completed > 0) {
            continue;
        }
        $sql = 'SELECT test_id, test_name FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . $test_id;
        $result = F_db_query($sql, $db);
        /** @var \mysqli_result|\PgSql\Result|false $result */
        $test = $result === false ? null : $normalize_row(F_db_fetch_array($result));
        if ($test !== null) {
            $pending[] = $meta + [
                'test_id' => (int) ($test['test_id'] ?? 0),
                'test_name' => (string) ($test['test_name'] ?? ''),
            ];
        }
    }

    return $pending;
}
