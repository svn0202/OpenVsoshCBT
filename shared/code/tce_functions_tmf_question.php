<?php

//============================================================+
// File name   : tce_functions_tmf_question.php
// Description : Pure helpers for independently implemented TMF question metadata.
// License     : AGPL-3.0-or-later (see LICENSE).
//============================================================+

/**
 * Read optional behavior markers embedded by the DOCX importer.
 *
 * @return array{checkbox: bool, headers: list<string>, max_selections: int}
 */
function F_tmf_question_options(string $description): array
{
    $options = [
        'checkbox' => str_contains((string) $description, '<!--TMF_CHECKBOX-->'),
        'headers' => ['Утверждение', 'Верно', 'Неверно', 'Без ответа'],
        'max_selections' => 0,
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

    return $options;
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
