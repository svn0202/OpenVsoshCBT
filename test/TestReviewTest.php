<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_test.php';

final class TestReviewTest extends TestCase
{
    public function testTwoColumnRowPreservesExactMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        self::assertSame(
            '<div class="row"><span class="label"><span title="Description">Label: '
                . '</span></span><span class="value">Value</span></div>' . K_NEWLINE,
            \F_twoColRow('Label', 'Description', 'Value'),
        );
    }

    #[DataProvider('reviewValues')]
    public function testReviewValueNormalization(mixed $value, int $expected): void
    {
        self::assertSame($expected, \f_tmf_review_value($value));
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function reviewValues(): array
    {
        return [
            'string one' => ['1', 1],
            'integer one' => [1, 1],
            'float one' => [1.0, 1],
            'boolean true' => [true, 1],
            'zero' => ['0', 0],
            'missing value' => [null, 0],
            'array input' => [['1'], 0],
        ];
    }
}
