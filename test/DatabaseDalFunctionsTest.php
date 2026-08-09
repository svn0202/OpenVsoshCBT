<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class DatabaseDalFunctionsTest extends TestCase
{
    public function testDatabaseQueryBehaviorRemainsDriverSpecific(): void
    {
        $expectations = [
            'mysql' => ['value' => ['SELECT * FROM t ORDER BY RAND() LIMIT 1', 'connection']],
            'mysqli' => ['exception' => 'Error'],
            'oracle' => [
                'start' => true,
                'value' => 'statement',
                'commit' => true,
                'query' => 'SELECT * FROM t ORDER BY dbms_random.random ',
                'mode' => 1,
            ],
            'postgresql' => ['exception' => 'TypeError'],
        ];

        foreach ($expectations as $driver => $expected) {
            $prelude = match ($driver) {
                'mysql' => 'function mysql_query($query, $link) { return [$query, $link]; } ',
                'oracle' => 'define("OCI_NO_AUTO_COMMIT", 1); define("OCI_COMMIT_ON_SUCCESS", 2); '
                    . '$GLOBALS["query"] = ""; $GLOBALS["mode"] = 0; '
                    . 'function oci_parse($link, $query) { $GLOBALS["query"] = $query; return "statement"; } '
                    . 'function oci_execute($statement, $mode) { $GLOBALS["mode"] = $mode; return true; } '
                    . 'function oci_commit($link) { return true; } function oci_rollback($link) { return true; } ',
                default => '',
            };
            $invocation = match ($driver) {
                'mysql' => 'echo json_encode(["value" => F_db_query('
                    . '"SELECT * FROM t ORDER BY RAND() LIMIT 1", "connection")]);',
                'mysqli' => 'try { F_db_query("SELECT 1", mysqli_init()); } '
                    . 'catch (Throwable $exception) { echo json_encode(["exception" => get_class($exception)]); }',
                'oracle' => '$link = new stdClass(); $start = F_db_query("START TRANSACTION", $link); '
                    . '$value = F_db_query("SELECT * FROM t ORDER BY RAND() LIMIT 1", $link); '
                    . '$commit = F_db_query("COMMIT", $link); echo json_encode(["start" => $start, "value" => $value, '
                    . '"commit" => $commit, "query" => $GLOBALS["query"], "mode" => $GLOBALS["mode"]]);',
                'postgresql' => 'try { F_db_query("SELECT 1 ORDER BY RAND()", null); } '
                    . 'catch (Throwable $exception) { echo json_encode(["exception" => get_class($exception)]); }',
            };
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    'error_reporting(E_ALL & ~E_DEPRECATED); ' . $prelude
                        . '$source = file_get_contents($argv[1]); '
                        . 'preg_match("/function [Ff]_db_query/", $source, $match, PREG_OFFSET_CAPTURE); '
                        . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                        . 'eval(substr($source, $start, $end - $start)); ' . $invocation,
                    dirname(__DIR__) . '/shared/code/tce_db_dal_' . $driver . '.php',
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $driver . ': ' . $output);
            self::assertSame($expected, json_decode($output, true, 512, JSON_THROW_ON_ERROR), $driver);
        }
    }

    public function testDatabaseAffectedRowsBehaviorRemainsDriverSpecific(): void
    {
        $expectations = [
            'mysql' => ['value' => 3],
            'mysqli' => ['exception' => 'Error'],
            'oracle' => ['value' => 5],
            'postgresql' => ['exception' => 'TypeError'],
        ];

        foreach ($expectations as $driver => $expected) {
            $prelude = match ($driver) {
                'mysql' => 'function mysql_affected_rows($link) { return $link === "connection" ? 3 : 0; } ',
                'oracle' => 'function oci_num_rows($result) { return $result === "rows" ? 5 : 0; } ',
                default => '',
            };
            $link = match ($driver) {
                'mysqli' => 'mysqli_init()',
                'postgresql' => 'null',
                default => '"connection"',
            };
            $result = ($driver === 'mysql' || $driver === 'oracle') ? '"rows"' : 'null';
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    $prelude . '$source = file_get_contents($argv[1]); '
                        . 'preg_match("/function [Ff]_db_affected_rows.*?\\n\\}/s", $source, $match); eval($match[0]); '
                        . 'try { echo json_encode(["value" => F_db_affected_rows(' . $link . ', ' . $result . ')]); } '
                        . 'catch (Throwable $exception) { echo json_encode(["exception" => get_class($exception)]); }',
                    dirname(__DIR__) . '/shared/code/tce_db_dal_' . $driver . '.php',
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $driver . ': ' . $output);
            self::assertSame($expected, json_decode($output, true, 512, JSON_THROW_ON_ERROR), $driver);
        }
    }

    public function testDatabaseFetchBehaviorRemainsDriverSpecific(): void
    {
        $expectations = [
            'mysql' => [
                'array' => [0 => 'zero\\', 'NAME' => "O\\'Brien"],
                'assoc' => ['NAME' => "O\\'Brien"],
            ],
            'mysqli' => [
                'array' => ['exception' => 'TypeError'],
                'assoc' => ['exception' => 'TypeError'],
            ],
            'oracle' => [
                'array' => [0 => 'zero', 'name' => "O'Brien"],
                'assoc' => ['name' => "O'Brien"],
            ],
            'postgresql' => [
                'array' => ['exception' => 'TypeError'],
                'assoc' => ['exception' => 'TypeError'],
            ],
        ];

        foreach ($expectations as $driver => $expected) {
            $prelude = match ($driver) {
                'mysql' => 'function mysql_fetch_array($result) { return [0 => "zero\\\\", "NAME" => "O\\\\\'Brien"]; } '
                    . 'function mysql_fetch_assoc($result) { return ["NAME" => "O\\\\\'Brien"]; } ',
                'oracle' => 'define("OCI_BOTH", 1); define("OCI_RETURN_NULLS", 2); define("OCI_RETURN_LOBS", 4); '
                    . 'function oci_fetch_array($result, $mode) { return [0 => "zero\\\\", "NAME" => "O\\\\\'Brien"]; } '
                    . 'function oci_fetch_assoc($result) { return ["NAME" => "O\\\\\'Brien"]; } ',
                default => '',
            };
            $result = ($driver === 'mysql' || $driver === 'oracle') ? '"rows"' : 'null';
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    $prelude . '$source = file_get_contents($argv[1]); '
                        . 'foreach (["fetch_array", "fetch_assoc"] as $suffix) { '
                        . 'preg_match("/function [Ff]_db_" . $suffix . "/", $source, $match, PREG_OFFSET_CAPTURE); '
                        . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                        . 'eval(substr($source, $start, $end - $start)); } '
                        . '$values = []; foreach (["array", "assoc"] as $suffix) { try { '
                        . '$function = "F_db_fetch_" . $suffix; $values[$suffix] = $function(' . $result . '); } '
                        . 'catch (Throwable $exception) { $values[$suffix] = ["exception" => get_class($exception)]; } } '
                        . 'echo json_encode($values);',
                    dirname(__DIR__) . '/shared/code/tce_db_dal_' . $driver . '.php',
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $driver . ': ' . $output);
            self::assertSame($expected, json_decode($output, true, 512, JSON_THROW_ON_ERROR), $driver);
        }
    }

    public function testDatabaseRowCountBehaviorRemainsDriverSpecific(): void
    {
        $expectations = [
            'mysql' => ['value' => 4],
            'mysqli' => ['exception' => 'TypeError'],
            'oracle' => ['value' => 5],
            'postgresql' => ['exception' => 'TypeError'],
        ];

        foreach ($expectations as $driver => $expected) {
            $prelude = match ($driver) {
                'mysql' => 'function mysql_num_rows($result) { return $result === "rows" ? 4 : 0; } ',
                'oracle' => 'function oci_fetch_all($result, &$output) { $output = ["TOTAL" => [5]]; return 1; } '
                    . 'function oci_num_rows($result) { return 9; } ',
                default => '',
            };
            $result = ($driver === 'mysql' || $driver === 'oracle') ? '"rows"' : 'null';
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    $prelude . '$source = file_get_contents($argv[1]); '
                        . 'preg_match("/function [Ff]_db_num_rows/", $source, $match, PREG_OFFSET_CAPTURE); '
                        . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                        . 'eval(substr($source, $start, $end - $start)); '
                        . 'try { echo json_encode(["value" => F_db_num_rows(' . $result . ')]); } '
                        . 'catch (Throwable $exception) { echo json_encode(["exception" => get_class($exception)]); }',
                    dirname(__DIR__) . '/shared/code/tce_db_dal_' . $driver . '.php',
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $driver . ': ' . $output);
            self::assertSame($expected, json_decode($output, true, 512, JSON_THROW_ON_ERROR), $driver);
        }
    }

    public function testDatabaseCloseBehaviorRemainsDriverSpecific(): void
    {
        $expectations = [
            'mysql' => ['value' => true],
            'mysqli' => ['value' => true],
            'oracle' => ['value' => true],
            'postgresql' => ['exception' => 'Error'],
        ];

        foreach ($expectations as $driver => $expected) {
            $prelude = match ($driver) {
                'mysql' => 'function mysql_close($link) { return $link === "connection"; } ',
                'oracle' => 'function oci_close($link) { return $link === "connection"; } ',
                default => '',
            };
            $link = match ($driver) {
                'mysqli' => 'mysqli_init()',
                'postgresql' => 'null',
                default => '"connection"',
            };
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    'error_reporting(E_ALL & ~E_DEPRECATED); ' . $prelude
                        . '$source = file_get_contents($argv[1]); '
                        . 'preg_match("/function [Ff]_db_close.*?\\n\\}/s", $source, $match); eval($match[0]); '
                        . 'try { echo json_encode(["value" => F_db_close(' . $link . ')]); } '
                        . 'catch (Throwable $exception) { echo json_encode(["exception" => get_class($exception)]); }',
                    dirname(__DIR__) . '/shared/code/tce_db_dal_' . $driver . '.php',
                ],
                dirname(__DIR__) . '/shared/code',
            );

            self::assertSame(0, $status, $driver . ': ' . $output);
            self::assertSame($expected, json_decode($output, true, 512, JSON_THROW_ON_ERROR), $driver);
        }
    }

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
