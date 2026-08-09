<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class DatabaseDalFunctionsTest extends TestCase
{
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
