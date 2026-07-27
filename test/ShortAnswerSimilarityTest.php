<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_tmf_question.php';

final class ShortAnswerSimilarityTest extends TestCase
{
    public function testUnicodeSimilarityHandlesCyrillicCharacters(): void
    {
        self::assertSame(80.0, \F_tmf_text_similarity('маска', 'миска'));
        self::assertSame(100.0, \F_tmf_text_similarity('  Москва! ', 'москва'));
        self::assertLessThan(100.0, \F_tmf_text_similarity('Москва', 'москва', true));
    }

    public function testSimilarityMarkerIsValidatedAndReplaceable(): void
    {
        $description = \F_tmf_set_similarity_threshold('<p>Ответ</p>', 85);
        self::assertSame(85, \F_tmf_question_options($description)['similarity_threshold']);
        self::assertSame(
            70,
            \F_tmf_question_options(\F_tmf_set_similarity_threshold($description, 70))['similarity_threshold']
        );
        self::assertSame(
            0,
            \F_tmf_question_options(\F_tmf_set_similarity_threshold($description, 0))['similarity_threshold']
        );
    }

    public function testShortAnswerScoringUsesClosestWeightedKey(): void
    {
        $keys = [
            ['answer_description' => 'Екатеринбург', 'answer_weight' => 50],
            ['answer_description' => 'Свердловск', 'answer_weight' => 100],
        ];
        self::assertSame(5.0, \F_tmf_short_answer_score('екатеринбург', $keys, false, 0, 10, -2));
        self::assertSame(10.0, \F_tmf_short_answer_score('Свердловскк', $keys, false, 85, 10, -2));
        self::assertSame(-2.0, \F_tmf_short_answer_score('Москва', $keys, false, 85, 10, -2));
        self::assertNull(\F_tmf_short_answer_score('Москва', $keys, false, 0, 10, -2));
    }
}
