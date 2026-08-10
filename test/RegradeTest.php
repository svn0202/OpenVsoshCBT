<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_tmf_question.php';
require_once __DIR__ . '/../shared/code/tce_functions_regrade.php';

final class RegradeTest extends TestCase
{
    /**
     * @var array{
     *     test_score_right: int,
     *     test_score_wrong: int,
     *     test_score_unanswered: int,
     *     test_mcma_partial_score: int
     * }
     */
    private array $test = [
        'test_score_right' => 4,
        'test_score_wrong' => -1,
        'test_score_unanswered' => 0,
        'test_mcma_partial_score' => 1,
    ];

    public function testSingleChoiceUsesCurrentAnswerWeight(): void
    {
        $score = \F_tmf_recorded_answer_score(
            $this->test,
            ['question_type' => 1, 'question_difficulty' => 2],
            [
                ['logansw_selected' => 1, 'answer_isright' => 1, 'answer_weight' => 50],
                ['logansw_selected' => 0, 'answer_isright' => 0, 'answer_weight' => null],
            ],
        );
        self::assertSame(4.0, $score);
    }

    public function testMultipleChoicePartialScoreIsRebuilt(): void
    {
        $score = \F_tmf_recorded_answer_score(
            $this->test,
            ['question_type' => 2, 'question_difficulty' => 1],
            [
                ['logansw_selected' => 1, 'answer_isright' => 1],
                ['logansw_selected' => 1, 'answer_isright' => 0],
            ],
        );
        self::assertSame(1.5, $score);
    }

    public function testOrderingAndMatchingUsePersistedPositions(): void
    {
        $score = \F_tmf_recorded_answer_score(
            $this->test,
            ['question_type' => 5, 'question_difficulty' => 1],
            [
                ['logansw_selected' => 1, 'logansw_position' => 2, 'answer_position' => 2],
                ['logansw_selected' => 1, 'logansw_position' => 1, 'answer_position' => 2],
                ['logansw_selected' => -1, 'logansw_position' => 0, 'answer_position' => 3],
            ],
        );
        self::assertSame(1.0, $score);
    }

    public function testMatchingScoresRepeatedCorrectPositionsIndependently(): void
    {
        $score = \F_tmf_recorded_answer_score(
            $this->test,
            ['question_type' => 5, 'question_difficulty' => 1],
            [
                ['logansw_selected' => 1, 'logansw_position' => 1, 'answer_position' => 1],
                ['logansw_selected' => 1, 'logansw_position' => 1, 'answer_position' => 1],
                ['logansw_selected' => 1, 'logansw_position' => 2, 'answer_position' => 2],
            ],
        );
        self::assertSame(4.0, $score);
    }

    public function testAllUnansweredUsesConfiguredUnansweredScore(): void
    {
        $test = $this->test;
        $test['test_score_unanswered'] = 2;
        $test['test_mcma_partial_score'] = 0;

        $score = \F_tmf_recorded_answer_score(
            $test,
            ['question_type' => 2, 'question_difficulty' => 1],
            [
                ['logansw_selected' => -1, 'answer_isright' => 1],
                ['logansw_selected' => -1, 'answer_isright' => 0],
            ],
        );

        self::assertSame(2.0, $score);
    }

    public function testRegradeKeepsObjectiveShortAnswerAndCommitContract(): void
    {
        $result = self::runRegrade(false);

        self::assertSame(2, $result['updated']);
        self::assertNull($result['error']);
        self::assertContains('START TRANSACTION', $result['queries']);
        self::assertContains(
            'UPDATE test_logs SET testlog_score=4.000 WHERE testlog_id=101',
            $result['queries'],
        );
        self::assertContains(
            'UPDATE test_logs SET testlog_score=3.250 WHERE testlog_id=102',
            $result['queries'],
        );
        $queries = $result['queries'];
        self::assertSame('COMMIT', array_pop($queries));
        self::assertNotContains('ROLLBACK', $result['queries']);
    }

    public function testRegradeRollsBackWhenScoreCannotBeWritten(): void
    {
        $result = self::runRegrade(true);

        self::assertNull($result['updated']);
        self::assertSame('Не удалось записать пересчитанный балл.', $result['error']);
        $queries = $result['queries'];
        self::assertSame('ROLLBACK', array_pop($queries));
        self::assertNotContains('COMMIT', $result['queries']);
    }

    /** @return array{updated: int|null, error: string|null, queries: list<string>} */
    private static function runRegrade(bool $failUpdate): array
    {
        $script = <<<'PHP'
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_TESTS_LOGS', 'test_logs');
define('K_TABLE_TEST_USER', 'test_users');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_ANSWERS', 'answers');
define('K_TABLE_LOG_ANSWER', 'log_answers');
define('K_SHORT_ANSWERS_BINARY', false);
$db = 'db';
$GLOBALS['queries'] = [];
$GLOBALS['kinds'] = [];
$GLOBALS['indexes'] = [];
$GLOBALS['fail_update'] = $argv[2] === '1';
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    if ($GLOBALS['fail_update'] && str_starts_with($sql, 'UPDATE test_logs')) { return false; }
    $kind = match (true) {
        str_starts_with($sql, 'SELECT test_score_right') => 'test',
        str_starts_with($sql, 'SELECT tl.testlog_id') => 'logs',
        str_starts_with($sql, 'SELECT la.logansw_selected') => 'objective_answers',
        str_starts_with($sql, 'SELECT answer_description') => 'short_keys',
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
        'test' => [[
            'test_score_right' => 4, 'test_score_wrong' => -1,
            'test_score_unanswered' => 0, 'test_mcma_partial_score' => 1,
        ]],
        'logs' => [[
            'testlog_id' => 101, 'testlog_answer_text' => null, 'testlog_change_time' => null,
            'question_id' => 201, 'question_type' => 2, 'question_difficulty' => 1,
            'question_description' => '',
        ], [
            'testlog_id' => 102, 'testlog_answer_text' => 'answer', 'testlog_change_time' => '2026-01-01',
            'question_id' => 202, 'question_type' => 3, 'question_difficulty' => 1,
            'question_description' => 'threshold',
        ]],
        'objective_answers' => [[
            'logansw_selected' => 1, 'logansw_position' => 0, 'answer_position' => 1,
            'answer_isright' => 1, 'answer_weight' => null,
        ]],
        'short_keys' => [['answer_description' => 'answer', 'answer_weight' => null]],
    ];
    return $rows[$kind][$GLOBALS['indexes'][$kind]++] ?? false;
}
function f_get_boolean($value) { return (bool) $value; }
function F_tmf_answer_score($weight, $isRight, $right, $wrong) { return $isRight ? $right : $wrong; }
function F_tmf_question_options($description) { return ['similarity_threshold' => 90]; }
function F_tmf_short_answer_score(...$arguments) { return 3.25; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval($source);
$updated = null;
$error = null;
try { $updated = f_tmf_regrade_test(7); } catch (\Throwable $exception) { $error = $exception->getMessage(); }
echo json_encode(['updated' => $updated, 'error' => $error, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/shared/code/tce_functions_regrade.php',
                $failUpdate ? '1' : '0',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{updated: int|null, error: string|null, queries: list<string>} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
