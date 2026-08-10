<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class QuestionFunctionsMutationTest extends TestCase
{
    public function testDeleteKeepsTransactionAndPositionCompactionContract(): void
    {
        $queries = self::runOperation('delete');

        self::assertSame('START TRANSACTION', $queries[0] ?? null);
        self::assertStringContainsString('SELECT question_position FROM questions WHERE question_id=17 LIMIT 1', $queries[1] ?? '');
        self::assertSame('DELETE FROM questions WHERE question_id=17', $queries[2] ?? null);
        self::assertStringContainsString('question_position=question_position-1', $queries[3] ?? '');
        self::assertStringContainsString('WHERE question_subject_id=9 AND question_position>3', $queries[3] ?? '');
        self::assertSame('COMMIT', $queries[4] ?? null);
    }

    public function testCopyKeepsQuestionAnswerAndTransactionContract(): void
    {
        $queries = self::runOperation('copy');

        self::assertStringContainsString('SELECT subject_module_id FROM subjects WHERE subject_id=9 LIMIT 1', $queries[0] ?? '');
        self::assertStringContainsString('SELECT * FROM questions WHERE question_id=17 LIMIT 1', $queries[1] ?? '');
        self::assertSame('START TRANSACTION', $queries[2] ?? null);
        self::assertStringContainsString('question_position=question_position+1', $queries[3] ?? '');
        self::assertStringContainsString('INSERT INTO questions', $queries[4] ?? '');
        self::assertStringContainsString("'Question', 'Explanation', '2', '1', '1', 3", $queries[4] ?? '');
        self::assertStringContainsString('SELECT * FROM answers WHERE answer_question_id=17', $queries[5] ?? '');
        self::assertStringContainsString('INSERT INTO answers', $queries[6] ?? '');
        self::assertStringContainsString("99, 'Answer', 'Why', '1', '1', 1, NULL, 50", $queries[6] ?? '');
        self::assertSame('COMMIT', $queries[7] ?? null);
    }

    /** @return list<string> */
    private static function runOperation(string $operation): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_TESTS_LOGS', 'test_logs');
define('K_TABLE_SUBJECTS', 'subjects');
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_ANSWERS', 'answers');
define('K_DATABASE_TYPE', 'MYSQL');
define('K_MYSQL_QA_BIN_UNIQUITY', false);
$db = 'db';
$l = [];
$GLOBALS['queries'] = [];
$GLOBALS['kinds'] = [];
$GLOBALS['indexes'] = [];
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    $kind = match (true) {
        str_starts_with($sql, 'SELECT question_position') => 'position',
        str_starts_with($sql, 'SELECT subject_module_id') => 'subject',
        str_starts_with($sql, 'SELECT * FROM questions') => 'question',
        str_starts_with($sql, 'SELECT * FROM answers') => 'answers',
        default => null,
    };
    if ($kind === null) { return true; }
    $result = fopen('php://memory', 'r');
    $GLOBALS['kinds'][get_resource_id($result)] = $kind;
    $GLOBALS['indexes'][$kind] = 0;
    return $result;
}
function F_db_fetch_array($result) {
    $kind = $GLOBALS['kinds'][get_resource_id($result)];
    $rows = [
        'position' => [['question_position' => 3]],
        'subject' => [['subject_module_id' => 5]],
        'question' => [[
            'question_description' => 'Question', 'question_explanation' => 'Explanation',
            'question_type' => 2, 'question_difficulty' => 1, 'question_enabled' => 1,
            'question_position' => 3, 'question_timer' => 30, 'question_fullscreen' => 0,
            'question_inline_answers' => 1, 'question_auto_next' => 0, 'question_shuffle_answers' => 1,
        ]],
        'answers' => [[
            'answer_description' => 'Answer', 'answer_explanation' => 'Why',
            'answer_isright' => 1, 'answer_enabled' => 1, 'answer_position' => 1,
            'answer_keyboard_key' => '', 'answer_weight' => 50,
        ]],
    ];
    return $rows[$kind][$GLOBALS['indexes'][$kind]++] ?? false;
}
function F_check_unique(...$arguments) { return true; }
function f_is_authorized_user(...$arguments) { return true; }
function F_escape_sql($db, $value) { return str_replace("'", "''", (string) $value); }
function F_db_insert_id($db, $table, $column) { return 99; }
function f_zero_to_null($value) { return (int) $value === 0 ? 'NULL' : (string) (int) $value; }
function f_empty_to_null($value) { return $value === '' ? 'NULL' : "'" . $value . "'"; }
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
if ($argv[2] === 'delete') { f_question_delete('17x', '9x'); } else { f_question_copy('17x', '9x'); }
echo json_encode($GLOBALS['queries'], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/admin/code/tce_functions_questions.php',
                $operation,
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringNotContainsString('[[DB-ERROR]]', $output);
        /** @var list<string> */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
