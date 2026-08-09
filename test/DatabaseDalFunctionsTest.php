<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class DatabaseDalFunctionsTest extends TestCase
{
    public function testDatabaseErrorBehaviorRemainsDriverSpecific(): void
    {
        $expectations = [
            'mysql' => ['value' => ''],
            'mysqli' => ['value' => ''],
            'oracle' => ['value' => '[7]: broken'],
            'postgresql' => ['exception' => 'Error', 'message' => 'No PostgreSQL connection opened yet'],
        ];

        foreach ($expectations as $driver => $expected) {
            $prelude = $driver === 'oracle'
                ? 'function oci_error() { return ["code" => 7, "message" => "broken"]; } '
                : '';
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    'error_reporting(E_ALL & ~E_DEPRECATED); ' . $prelude
                        . '$source = file_get_contents($argv[1]); '
                        . 'preg_match("/function [Ff]_db_error/", $source, $match, PREG_OFFSET_CAPTURE); '
                        . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                        . 'eval(substr($source, $start, $end - $start)); '
                        . 'try { echo json_encode(["value" => F_db_error(null)]); } '
                        . 'catch (Throwable $exception) { echo json_encode(["exception" => get_class($exception), '
                        . '"message" => $exception->getMessage()]); }',
                    dirname(__DIR__) . '/shared/code/tce_db_dal_' . $driver . '.php',
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $driver . ': ' . $output);
            self::assertSame($expected, json_decode($output, true, 512, JSON_THROW_ON_ERROR), $driver);
        }
    }

    public function testDatetimeDifferenceExpressionRemainsDriverSpecific(): void
    {
        $expectations = [
            'mysql' => 'TIMESTAMPDIFF(SECOND, start_at, end_at)',
            'mysqli' => 'TIMESTAMPDIFF(SECOND, start_at, end_at)',
            'postgresql' => 'EXTRACT(EPOCH FROM (end_at - start_at))',
            'oracle' => '(end_at – start_at)*86400',
        ];

        foreach ($expectations as $driver => $expected) {
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    '$source = file_get_contents($argv[1]); '
                        . 'preg_match("/function [Ff]_db_datetime_diff_seconds.*?\\n\\}/s", $source, $match); '
                        . 'eval($match[0]); echo F_db_datetime_diff_seconds("start_at", "end_at");',
                    dirname(__DIR__) . '/shared/code/tce_db_dal_' . $driver . '.php',
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $driver . ': ' . $output);
            self::assertSame($expected, $output, $driver);
        }
    }
}
