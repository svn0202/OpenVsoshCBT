<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class OracleDalTest extends TestCase
{
    public function testOracleInsertIdPreservesNumericString(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("OCI_NUM", 8); define("OCI_NO_AUTO_COMMIT", 16); '
                    . 'define("OCI_COMMIT_ON_SUCCESS", 32); '
                    . 'function oci_parse($connection, $sql) { return "statement"; } '
                    . 'function oci_execute($statement, $mode) { return true; } '
                    . 'function oci_fetch_array($result, $mode) { return ["41"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$source = preg_replace("/^<\\?php\\s*/", "", $source); '
                    . 'eval("namespace Harness; " . $source); '
                    . '$id = f_db_insert_id("connection", "users"); '
                    . 'echo json_encode([$id, gettype($id)]);',
                dirname(__DIR__) . '/shared/code/tce_db_dal_oracle.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            ['41', 'string'],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testOracleRowsPreserveWeakScalarConversionWithoutWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("OCI_BOTH", 1); define("OCI_RETURN_NULLS", 2); '
                    . 'define("OCI_RETURN_LOBS", 4); define("OCI_NUM", 8); '
                    . 'define("OCI_NO_AUTO_COMMIT", 16); define("OCI_COMMIT_ON_SUCCESS", 32); '
                    . 'function oci_fetch_array($result, $mode) { '
                    . 'return [0 => null, "COUNT" => 42, "FLAG" => false]; } '
                    . 'function oci_fetch_assoc($result) { return ["NAME" => null, "COUNT" => 42]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$source = preg_replace("/^<\\?php\\s*/", "", $source); '
                    . 'eval("namespace Harness; " . $source); '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$arrayRow = f_db_fetch_array("result"); $assocRow = f_db_fetch_assoc("result"); '
                    . 'restore_error_handler(); echo json_encode([$arrayRow, $assocRow, $warnings]);',
                dirname(__DIR__) . '/shared/code/tce_db_dal_oracle.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [[0 => '', 'count' => '42', 'flag' => ''], ['name' => '', 'count' => '42'], []],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testOracleDalPreservesConnectionRowsAndCounts(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("OCI_BOTH", 1); define("OCI_RETURN_NULLS", 2); '
                    . 'define("OCI_RETURN_LOBS", 4); define("OCI_NUM", 8); '
                    . 'define("OCI_NO_AUTO_COMMIT", 16); define("OCI_COMMIT_ON_SUCCESS", 32); '
                    . '$GLOBALS["queries"] = []; '
                    . 'function oci_connect(...$arguments) { $GLOBALS["connect"] = $arguments; return "connection"; } '
                    . 'function oci_parse($connection, $sql) { '
                    . '$GLOBALS["queries"][] = [$sql, $connection]; return "statement"; } '
                    . 'function oci_execute($statement, $mode) { return true; } '
                    . 'function oci_fetch_array($result, $mode) { return [0 => "A\\\\B", "CODE" => "C\\\\D"]; } '
                    . 'function oci_fetch_assoc($result) { return ["NAME" => "E\\\\F"]; } '
                    . 'function oci_num_rows($result) { return 7; } '
                    . 'function oci_fetch_all($result, &$output) { $output = ["TOTAL" => ["9"]]; return 1; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$source = preg_replace("/^<\\?php\\s*/", "", $source); '
                    . 'eval("namespace Harness; " . $source); '
                    . '$connection = f_db_connect("db.example", "1522", "user", "secret", "exam"); '
                    . '$arrayRow = f_db_fetch_array("result"); $assocRow = f_db_fetch_assoc("result"); '
                    . '$affected = f_db_affected_rows("connection", "result"); '
                    . '$count = f_db_num_rows("result"); '
                    . 'echo json_encode([$connection, $GLOBALS["connect"], $GLOBALS["queries"], '
                    . '$arrayRow, $assocRow, $affected, $count]);',
                dirname(__DIR__) . '/shared/code/tce_db_dal_oracle.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'connection',
                ['user', 'secret', '//db.example:1522/exam', 'UTF8'],
                [["ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'", 'connection']],
                [0 => 'AB', 'code' => 'CD'],
                ['name' => 'EF'],
                7,
                '9',
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
