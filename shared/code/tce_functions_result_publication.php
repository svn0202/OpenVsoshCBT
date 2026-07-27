<?php

/**
 * Return true when participant results are enabled and the publication window is open.
 *
 * @param array<string,mixed> $test
 */
function F_tmf_results_are_published(array $test, ?int $now = null): bool
{
    if (!F_getBoolean($test['test_results_to_users'] ?? false)) {
        return false;
    }

    $now ??= time();
    $publish_at = trim((string) ($test['test_results_publish_at'] ?? ''));
    $unpublish_at = trim((string) ($test['test_results_unpublish_at'] ?? ''));
    $publish_time = $publish_at === '' ? false : strtotime($publish_at);
    $unpublish_time = $unpublish_at === '' ? false : strtotime($unpublish_at);

    return ($publish_time === false || $now >= $publish_time)
        && ($unpublish_time === false || $now < $unpublish_time);
}

/**
 * Format the participant identity without exposing account data in anonymous mode.
 *
 * @param array<string,mixed> $user
 */
function F_tmf_result_identity(array $user, bool $anonymized): string
{
    if ($anonymized) {
        return 'Участник #' . (int) ($user['user_id'] ?? 0);
    }

    return trim(
        (string) ($user['user_lastname'] ?? '')
        . ' '
        . (string) ($user['user_firstname'] ?? '')
        . ' - '
        . (string) ($user['user_name'] ?? ''),
    );
}
