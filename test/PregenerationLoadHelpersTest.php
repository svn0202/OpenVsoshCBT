<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Test\Integration\PregenerationLoadHttpTest;

require_once __DIR__ . '/integration/AppHttpTestCase.php';
require_once __DIR__ . '/integration/PregenerationLoadHttpTest.php';

final class PregenerationLoadHelpersTest extends TestCase
{
    /**
     * @return iterable<string,array{list<float>,float,float}>
     */
    public static function percentileCases(): iterable
    {
        yield 'empty input' => [[], 0.95, 0.0];
        yield 'single value' => [[12.3456], 0.95, 12.346];
        yield 'median sorts values' => [[30.0, 10.0, 20.0], 0.50, 20.0];
        yield '95th percentile rounds up' => [[5.0, 1.0, 4.0, 2.0, 3.0], 0.95, 5.0];
    }

    /**
     * @param list<float> $values
     */
    #[DataProvider('percentileCases')]
    public function testPercentileBehavior(array $values, float $percentile, float $expected): void
    {
        $method = new ReflectionMethod(PregenerationLoadHttpTest::class, 'percentile');

        self::assertSame($expected, $method->invoke(null, $values, $percentile));
    }
}
