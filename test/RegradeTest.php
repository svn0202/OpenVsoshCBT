<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_tmf_question.php';
require_once __DIR__ . '/../shared/code/tce_functions_regrade.php';

final class RegradeTest extends TestCase
{
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
}
