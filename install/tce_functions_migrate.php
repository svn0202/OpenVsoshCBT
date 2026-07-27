<?php

/**
 * Split the migration files shipped by OpenVsoshCBT into executable statements.
 * The Oracle files may contain trigger bodies terminated by END;;.
 *
 * @return array<int,string>
 */
function F_tmf_migration_statements(string $sql, string $database_type): array
{
    $protected = [];
    if ($database_type === 'ORACLE') {
        $sql = preg_replace_callback(
            '/CREATE\\s+OR\\s+REPLACE\\s+TRIGGER\\b.*?END;;/is',
            static function (array $match) use (&$protected): string {
                $key = '__OPENVSOSH_PLSQL_' . count($protected) . '__';
                $protected[$key] = rtrim(substr($match[0], 0, -1));
                return $key . ';';
            },
            $sql,
        );
    }
    $statements = [];
    $buffer = '';
    $quoted = false;
    $length = strlen($sql);
    for ($index = 0; $index < $length; ++$index) {
        $character = $sql[$index];
        if ($character === "'") {
            if ($quoted && ($sql[$index + 1] ?? '') === "'") {
                $buffer .= "''";
                ++$index;
                continue;
            }
            $quoted = !$quoted;
        }
        if ($character === ';' && !$quoted) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $protected[$statement] ?? $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $character;
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $protected[$tail] ?? $tail;
    }
    return $statements;
}

/**
 * Return migration files in their release order.
 *
 * @return array<int,string>
 */
function F_tmf_migration_files(string $directory): array
{
    $order = [
        'openvsosh_access_settings.sql',
        'openvsosh_word_import.sql',
        'openvsosh_answer_save.sql',
        'openvsosh_monitoring.sql',
        'openvsosh_pregeneration.sql',
        'openvsosh_offline.sql',
        'openvsosh_test_access.sql',
        'openvsosh_essay_attachments.sql',
        'openvsosh_question_shuffle.sql',
        'openvsosh_review_flag.sql',
        'openvsosh_exam_display.sql',
        'openvsosh_user_card.sql',
        'openvsosh_result_publication.sql',
    ];
    return array_values(array_filter(
        array_map(static fn (string $name): string => $directory . '/' . $name, $order),
        'is_file',
    ));
}
