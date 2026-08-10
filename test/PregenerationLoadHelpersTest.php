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
     * @return iterable<string,array{array<int,string>|false,string|null}>
     */
    public static function databaseScalarCases(): iterable
    {
        yield 'first column' => [['42', 'ignored'], '42'];
        yield 'no row' => [false, null];
    }

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

    /**
     * @param array<int,string>|false $row
     */
    #[DataProvider('databaseScalarCases')]
    public function testDatabaseScalarBehavior(array|false $row, ?string $expected): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$GLOBALS["row"] = json_decode($argv[4], true); '
                    . '$GLOBALS["calls"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["calls"][] = ["query", $sql, $db]; '
                    . 'return "result"; } '
                    . 'function F_db_fetch_array($result) { $GLOBALS["calls"][] = ["fetch", $result]; '
                    . 'return $GLOBALS["row"]; } '
                    . 'require $argv[1]; require $argv[2]; require $argv[3]; '
                    . '$test = new Test\\Integration\\PregenerationLoadHttpTest('
                    . '"testConcurrentStartsWithAndWithoutPregeneration"); '
                    . '$property = new ReflectionProperty($test, "db"); $property->setValue($test, "db-link"); '
                    . '$method = new ReflectionMethod($test, "dbScalar"); '
                    . 'echo json_encode(["value" => $method->invoke($test, "SELECT value"), '
                    . '"calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/vendor/autoload.php',
                __DIR__ . '/integration/AppHttpTestCase.php',
                __DIR__ . '/integration/PregenerationLoadHttpTest.php',
                json_encode($row, JSON_THROW_ON_ERROR),
            ],
            dirname(__DIR__),
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'value' => $expected,
                'calls' => [
                    ['query', 'SELECT value', 'db-link'],
                    ['fetch', 'result'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
