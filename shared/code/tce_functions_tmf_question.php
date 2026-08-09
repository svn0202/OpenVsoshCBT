<?php

//============================================================+
// File name   : tce_functions_tmf_question.php
// Description : Pure helpers for independently implemented TMF question metadata.
// License     : AGPL-3.0-or-later (see LICENSE).
//============================================================+

/**
 * Read optional behavior markers embedded by the DOCX importer.
 *
 * @return array{checkbox: bool, headers: list<string>, max_selections: int, similarity_threshold: int, matching_positions: int, matching_reuse_positions: bool, audio_play_limit: int}
 */
function F_tmf_question_options(string $description): array
{
    $options = [
        'checkbox' => str_contains((string) $description, '<!--TMF_CHECKBOX-->'),
        'headers' => ['Утверждение', 'Верно', 'Неверно', 'Без ответа'],
        'max_selections' => 0,
        'similarity_threshold' => 0,
        'matching_positions' => 0,
        'matching_reuse_positions' => str_contains($description, '<!--TMF_MATCH_REUSE-->'),
        'audio_play_limit' => 0,
    ];

    if (preg_match('/<!--TMF_MAX_SEL:(\d+)-->/', (string) $description, $match)) {
        $options['max_selections'] = max(0, (int) $match[1]);
        $options['checkbox'] = $options['checkbox'] || $options['max_selections'] > 0;
    }

    if (preg_match('/<!--TMF_MCMA_HEADER:(.*?)-->/', (string) $description, $match)) {
        $headers = json_decode(html_entity_decode((string) $match[1], ENT_QUOTES, 'UTF-8'), true);
        if (is_array($headers) && count($headers) >= 4) {
            $options['headers'] = array_map(
                static fn($header): string => trim((string) $header),
                array_slice(array_values($headers), 0, 4),
            );
        }
    }

    if (preg_match('/<!--TMF_SIMILARITY:(\d{1,3})-->/', $description, $match)) {
        $options['similarity_threshold'] = max(0, min(100, (int) $match[1]));
    }
    if (preg_match('/<!--TMF_MATCH_POSITIONS:(\d{1,3})-->/', $description, $match)) {
        $options['matching_positions'] = max(0, min(100, (int) $match[1]));
    }
    if (preg_match('/<!--TMF_AUDIO_PLAYS:(\d{1,2})-->/', $description, $match)) {
        $options['audio_play_limit'] = max(0, min(99, (int) $match[1]));
    }

    return $options;
}

/**
 * Enable or disable repeated use of a left-side matching position.
 */
function F_tmf_set_matching_reuse_positions(string $description, bool $enabled): string
{
    $description = (string) preg_replace('/\s*<!--TMF_MATCH_REUSE-->/', '', $description);
    if ($enabled) {
        $description = rtrim($description) . '<!--TMF_MATCH_REUSE-->';
    }
    return $description;
}

/**
 * Replace the optional number of permitted audio starts.
 */
function F_tmf_set_audio_play_limit(string $description, int $limit): string
{
    $description = (string) preg_replace('/\s*<!--TMF_AUDIO_PLAYS:\d{1,2}-->/', '', $description);
    $limit = max(0, min(99, $limit));
    if ($limit > 0) {
        $description = rtrim($description) . '<!--TMF_AUDIO_PLAYS:' . $limit . '-->';
    }
    return $description;
}

/**
 * Replace the optional number of left-side matching positions.
 */
function F_tmf_set_matching_positions(string $description, int $positions): string
{
    $description = (string) preg_replace('/\s*<!--TMF_MATCH_POSITIONS:\d{1,3}-->/', '', $description);
    $positions = max(0, min(100, $positions));
    if ($positions > 0) {
        $description = rtrim($description) . '<!--TMF_MATCH_POSITIONS:' . $positions . '-->';
    }
    return $description;
}

/**
 * Move a matching question's numbered conditions from its description into
 * plain-text labels suitable for the position selectors.
 *
 * The list is removed only when its item count exactly matches the explicitly
 * configured number of matching positions. This prevents unrelated ordered
 * lists in the question text from being hidden.
 *
 * @return array{description: string, labels: list<string>}
 */
function F_tmf_matching_presentation(string $description, int $positions): array
{
    $result = ['description' => $description, 'labels' => []];
    if ($positions < 1) {
        return $result;
    }

    $candidate_pattern =
        '~<div\b[^>]*>\s*<ol\b[^>]*>.*?</ol>\s*</div>|<ol\b[^>]*>.*?</ol>~isu';
    if (!preg_match_all($candidate_pattern, $description, $candidates, PREG_OFFSET_CAPTURE)) {
        return $result;
    }

    for ($candidate_index = count($candidates[0]) - 1; $candidate_index >= 0; --$candidate_index) {
        [$candidate, $offset] = $candidates[0][$candidate_index];
        if (!preg_match_all('~<li\b[^>]*>(.*?)</li>~isu', $candidate, $items)) {
            continue;
        }
        if (count($items[1]) !== $positions) {
            continue;
        }

        $labels = [];
        foreach ($items[1] as $item) {
            $label = preg_replace('~<br\s*/?>~iu', ' ', (string) $item);
            $label = html_entity_decode(strip_tags((string) $label), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = str_replace("\u{00A0}", ' ', $label);
            $label = trim((string) preg_replace('/\s+/u', ' ', $label));
            if ($label === '') {
                continue 2;
            }
            $labels[] = $label;
        }

        $result['description'] = substr_replace($description, '', (int) $offset, strlen($candidate));
        $result['labels'] = $labels;
        return $result;
    }

    return $result;
}

/**
 * Replace the optional short-answer similarity marker in a question description.
 */
function F_tmf_set_similarity_threshold(string $description, int $threshold): string
{
    $description = (string) preg_replace('/\s*<!--TMF_SIMILARITY:\d{1,3}-->/', '', $description);
    $threshold = max(0, min(100, $threshold));
    if ($threshold > 0 && $threshold < 100) {
        $description = rtrim($description) . '<!--TMF_SIMILARITY:' . $threshold . '-->';
    }
    return $description;
}

/**
 * Return question content without metadata controlled by dedicated editor
 * fields. Saving the form adds these markers back from their controls.
 */
function F_tmf_question_editor_description(string $description): string
{
    $description = F_tmf_set_similarity_threshold($description, 0);
    $description = F_tmf_set_matching_positions($description, 0);
    $description = F_tmf_set_matching_reuse_positions($description, false);
    return F_tmf_set_audio_play_limit($description, 0);
}

/**
 * Normalize a short answer for a language-independent similarity comparison.
 */
function F_tmf_normalize_short_answer(string $value, bool $binary = false): string
{
    $value = trim($value);
    if (function_exists('normalizer_normalize')) {
        $normalized = normalizer_normalize($value, \Normalizer::FORM_C);
        if (is_string($normalized)) {
            $value = $normalized;
        }
    }
    if (!$binary) {
        $value = mb_strtolower($value, 'UTF-8');
    }
    $value = (string) preg_replace('/[\p{P}\p{Z}\s]+/u', ' ', $value);
    return trim(mb_substr($value, 0, 500, 'UTF-8'));
}

/**
 * Return Unicode-aware edit similarity in the inclusive 0–100 range.
 */
function F_tmf_text_similarity(string $left, string $right, bool $binary = false): float
{
    $left_chars = preg_split('//u', F_tmf_normalize_short_answer($left, $binary), -1, PREG_SPLIT_NO_EMPTY);
    $right_chars = preg_split('//u', F_tmf_normalize_short_answer($right, $binary), -1, PREG_SPLIT_NO_EMPTY);
    $left_chars = $left_chars === false ? [] : $left_chars;
    $right_chars = $right_chars === false ? [] : $right_chars;
    $left_count = count($left_chars);
    $right_count = count($right_chars);
    $maximum = max($left_count, $right_count);
    if ($maximum === 0) {
        return 100.0;
    }
    $previous = range(0, $right_count);
    for ($row = 1; $row <= $left_count; ++$row) {
        $current = [$row];
        for ($column = 1; $column <= $right_count; ++$column) {
            $cost = $left_chars[$row - 1] === $right_chars[$column - 1] ? 0 : 1;
            $current[$column] = min(
                $current[$column - 1] + 1,
                $previous[$column] + 1,
                $previous[$column - 1] + $cost,
            );
        }
        $previous = $current;
    }
    return round(100 * (1 - ($previous[$right_count] / $maximum)), 2);
}

/**
 * Score one non-empty short answer against enabled correct keys.
 *
 * A null result preserves the historical "needs manual review" behavior when no similarity
 * threshold is configured and none of the keys matches exactly.
 *
 * @param array<int,array{answer_description:string,answer_weight?:int|string|null}> $keys
 */
function F_tmf_short_answer_score(
    string $submitted,
    array $keys,
    bool $binary,
    int $threshold,
    float $right_score,
    float $wrong_score,
): ?float {
    $submitted_exact = trim($submitted);
    $best_similarity = -1.0;
    $best_weight = null;
    foreach ($keys as $key) {
        $accepted = (string) $key['answer_description'];
        $matches = $binary
            ? $submitted_exact === $accepted
            : mb_strtolower($submitted_exact, 'UTF-8') === mb_strtolower($accepted, 'UTF-8');
        if ($matches) {
            return F_tmf_answer_score(
                $key['answer_weight'] ?? null,
                true,
                $right_score,
                $wrong_score,
            );
        }
        if ($threshold > 0) {
            $similarity = F_tmf_text_similarity($submitted, $accepted, $binary);
            if ($similarity > $best_similarity) {
                $best_similarity = $similarity;
                $best_weight = $key['answer_weight'] ?? null;
            }
        }
    }
    if ($threshold <= 0) {
        return null;
    }
    return $best_similarity >= max(1, min(99, $threshold))
        ? F_tmf_answer_score($best_weight, true, $right_score, $wrong_score)
        : $wrong_score;
}

/**
 * Apply an optional answer percentage while preserving standard scoring when
 * no percentage was configured.
 */
function F_tmf_answer_score(int|string|null $weight, bool $is_right, float $right_score, float $wrong_score): float
{
    if ($weight === null || $weight === '') {
        return $is_right ? $right_score : $wrong_score;
    }

    return ($right_score * max(0, min(100, (int) $weight))) / 100;
}

/**
 * Validate a submitted checkbox set against a per-question selection limit.
 */
function F_tmf_selection_limit_is_valid(array $answers, int $maximum): bool
{
    if ($maximum <= 0) {
        return true;
    }

    $selected = 0;
    foreach ((array) $answers as $value) {
        if ((int) $value === 1 && ++$selected > $maximum) {
            return false;
        }
    }

    return true;
}
