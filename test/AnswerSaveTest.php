<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_answer_save.php';

final class AnswerSaveTest extends TestCase
{
    public function testNewOperationCanSaveAtCurrentVersion(): void
    {
        self::assertSame(
            'save',
            \F_tmf_answer_save_decision(4, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 4, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
        );
    }

    public function testRepeatedOperationIsIdempotent(): void
    {
        self::assertSame(
            'duplicate',
            \F_tmf_answer_save_decision(5, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 4, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
        );
    }

    public function testStaleVersionCannotOverwriteNewerAnswer(): void
    {
        self::assertSame(
            'conflict',
            \F_tmf_answer_save_decision(5, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 4, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
        );
    }

    public function testInvalidOperationIsRejected(): void
    {
        self::assertSame('invalid', \F_tmf_answer_save_decision(0, null, 0, '../../invalid'));
        self::assertSame('invalid', \F_tmf_answer_save_decision(0, null, -1, str_repeat('a', 32)));
    }
}
