<?php

/**
 * Read and write the locally configured introductory tests.
 *
 * The small JSON file keeps this installation-specific choice out of the
 * upstream TCExam database schema.
 */
function F_getOnboardingConfig()
{
    $defaults = ['instruction_test_id' => 0, 'demo_test_id' => 0];
    $path = dirname(__DIR__) . '/config/tce_onboarding.json';
    if (!is_file($path)) {
        return $defaults;
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return $defaults;
    }

    return [
        'instruction_test_id' => max(0, (int) ($data['instruction_test_id'] ?? 0)),
        'demo_test_id' => max(0, (int) ($data['demo_test_id'] ?? 0)),
    ];
}

function F_saveOnboardingConfig($instruction_test_id, $demo_test_id)
{
    $path = dirname(__DIR__) . '/config/tce_onboarding.json';
    $payload = json_encode(
        [
            'instruction_test_id' => max(0, (int) $instruction_test_id),
            'demo_test_id' => max(0, (int) $demo_test_id),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );

    return file_put_contents($path, $payload . "\n", LOCK_EX) !== false;
}

function F_getPendingOnboardingTests($user_id)
{
    global $db, $l;
    $config = F_getOnboardingConfig();
    $labels = [
        'instruction_test_id' => [
            'kind' => 'instruction',
            'eyebrow' => $l['ov_onboarding_instruction_eyebrow'],
            'label' => $l['ov_onboarding_instruction_label'],
        ],
        'demo_test_id' => [
            'kind' => 'demo',
            'eyebrow' => $l['ov_onboarding_demo_eyebrow'],
            'label' => $l['ov_onboarding_demo_label'],
        ],
    ];
    $pending = [];

    foreach ($labels as $key => $meta) {
        $test_id = (int) $config[$key];
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
        if ($r = F_db_query($sql, $db)) {
            if ($test = F_db_fetch_array($r)) {
                $pending[] = $meta + [
                    'test_id' => (int) $test['test_id'],
                    'test_name' => $test['test_name'],
                ];
            }
        }
    }

    return $pending;
}
