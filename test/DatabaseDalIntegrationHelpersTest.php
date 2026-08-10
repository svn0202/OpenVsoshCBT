<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Test\Integration\DatabaseDalIntegrationTest;

require_once __DIR__ . '/integration/DatabaseDalIntegrationTest.php';

final class DatabaseDalIntegrationHelpersTest extends TestCase
{
    public function testEscapedSqlRoundTripUsesEscapedValueAndOriginalResult(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'function F_escape_sql($db, $value, $strip) { '
                    . '$GLOBALS["escape"] = [$db, $value, $strip]; return "escaped-value"; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["query"] = [$sql, $db]; return "result"; } '
                    . 'function F_db_fetch_assoc($result) { return ["v" => "O\'Brien \\"quote\\" \\\\ end"]; } '
                    . 'require $argv[1]; require $argv[2]; '
                    . '$test = new Test\\Integration\\DatabaseDalIntegrationTest('
                    . '"testEscapeSqlRoundTripsThroughTheServer"); '
                    . '$property = new ReflectionProperty($test, "db"); $property->setValue($test, "db-link"); '
                    . '$test->testEscapeSqlRoundTripsThroughTheServer(); '
                    . 'echo json_encode(["escape" => $GLOBALS["escape"], "query" => $GLOBALS["query"]]);',
                dirname(__DIR__) . '/vendor/autoload.php',
                __DIR__ . '/integration/DatabaseDalIntegrationTest.php',
            ],
            dirname(__DIR__),
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'escape' => ['db-link', "O'Brien \"quote\" \\ end", false],
                'query' => ["SELECT 'escaped-value' AS v", 'db-link'],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testSeededUsersCheckBuildsNamedLevelMap(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$GLOBALS["rows"] = [['
                    . '"user_name" => "anonymous", "user_level" => "0"], ['
                    . '"user_name" => "admin", "user_level" => "10"]]; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["sql"] = $sql; return "result"; } '
                    . 'function F_db_fetch_assoc($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_db_num_rows($result) { return 2; } '
                    . 'define("K_TABLE_USERS", "tce_users"); require $argv[1]; require $argv[2]; '
                    . '$test = new Test\\Integration\\DatabaseDalIntegrationTest("testSeededUsersArePresent"); '
                    . '$property = new ReflectionProperty($test, "db"); $property->setValue($test, "db-link"); '
                    . '$test->testSeededUsersArePresent(); echo $GLOBALS["sql"];',
                dirname(__DIR__) . '/vendor/autoload.php',
                __DIR__ . '/integration/DatabaseDalIntegrationTest.php',
            ],
            dirname(__DIR__),
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            'SELECT user_name, user_level FROM tce_users ORDER BY user_level',
            $output,
        );
    }

    public function testPipeReaderReturnsContentsAndClosesResource(): void
    {
        $pipe = fopen('php://temp', 'w+');
        self::assertIsResource($pipe);
        fwrite($pipe, 'migration output');
        rewind($pipe);
        $method = new ReflectionMethod(DatabaseDalIntegrationTest::class, 'readAndClosePipe');

        self::assertSame('migration output', $method->invoke(null, $pipe, 'Test'));
        $this->expectException(\TypeError::class);
        fread($pipe, 1);
    }

    /**
     * @return iterable<string,array{array<int,int|string>|false,int|string|null}>
     */
    public static function databaseScalarCases(): iterable
    {
        yield 'string column' => [['42'], '42'];
        yield 'integer column' => [[42], 42];
        yield 'no row' => [false, null];
    }

    /**
     * @param array<int,int|string>|false $row
     */
    #[DataProvider('databaseScalarCases')]
    public function testDatabaseScalarBehavior(array|false $row, int|string|null $expected): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$GLOBALS["row"] = json_decode($argv[3], true); '
                    . '$GLOBALS["calls"] = []; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["calls"][] = ["query", $sql, $db]; '
                    . 'return "result"; } '
                    . 'function F_db_fetch_array($result) { $GLOBALS["calls"][] = ["fetch", $result]; '
                    . 'return $GLOBALS["row"]; } '
                    . 'require $argv[1]; require $argv[2]; '
                    . '$test = new Test\\Integration\\DatabaseDalIntegrationTest("testConnectionAndIdentity"); '
                    . '$property = new ReflectionProperty($test, "db"); $property->setValue($test, "db-link"); '
                    . '$method = new ReflectionMethod($test, "dbScalar"); '
                    . 'echo json_encode(["value" => $method->invoke($test, "SELECT value"), '
                    . '"calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/vendor/autoload.php',
                __DIR__ . '/integration/DatabaseDalIntegrationTest.php',
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
