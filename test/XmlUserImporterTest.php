<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlUserImporterTest extends TestCase
{
    public function testPopulatedTsvCreatesUserGroupAndMembershipInOrder(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_TIMESTAMP_FORMAT", "Y-m-d H:i:s"); define("K_AUTH_ADMINISTRATOR", 10); '
                    . 'define("K_TABLE_USERS", "users"); define("K_TABLE_GROUPS", "groups"); '
                    . 'define("K_TABLE_USERGROUP", "user_groups"); $GLOBALS["db"] = "db"; '
                    . '$_SESSION = ["session_user_level" => 10, "session_user_id" => 1]; '
                    . '$_SERVER["REMOTE_ADDR"] = "127.0.0.1"; $GLOBALS["queries"] = []; '
                    . '$GLOBALS["rows"] = [false, false, false]; $GLOBALS["ids"] = [42, 7]; '
                    . '$GLOBALS["insert_calls"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_escape_sql($db, $value) { return $value; } '
                    . 'function f_empty_to_null($value) { return $value === "" ? "NULL" : "\'" . $value . "\'"; } '
                    . 'function get_password_hash($value) { return "hash:" . $value; } '
                    . 'function get_normalized_ip($value) { return $value; } '
                    . 'function F_get_user_groups($userId) { return []; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = '
                    . 'trim(preg_replace("/\\s+/", " ", $sql)); return "result"; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_db_insert_id($db, $table, $field) { '
                    . '$GLOBALS["insert_calls"][] = [$table, $field]; return array_shift($GLOBALS["ids"]); } '
                    . 'function F_display_db_error(...$arguments) { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_import_tsv_users/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval($function); '
                    . '$file = tempnam(sys_get_temp_dir(), "openvsosh-users-tsv-"); '
                    . '$row = ["", "alice", "secret", "alice@example.test", "2024-01-02 03:04:05", '
                    . '"192.0.2.1", "Alice", "Doe", "2000-01-01", "Town", "R-1", "S-1", '
                    . '"3", "verify", "otp", "Staff"]; '
                    . 'file_put_contents($file, "header\\n" . implode("\\t", $row) . "\\n"); '
                    . '$result = F_import_tsv_users($file); unlink($file); '
                    . 'echo json_encode([$result, $GLOBALS["queries"], $GLOBALS["insert_calls"], '
                    . '$GLOBALS["errors"]]);',
                dirname(__DIR__) . '/admin/code/XMLUserImporter.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertJson($output);
        /** @var array{
         *   0: bool,
         *   1: array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string},
         *   2: list<array{0: string, 1: string}>,
         *   3: int
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$result, $queries, $insertCalls, $errors] = $decoded;
        self::assertTrue($result);
        self::assertSame(0, $errors);
        self::assertCount(6, $queries);
        self::assertStringContainsString("WHERE user_name='alice'", $queries[0]);
        self::assertStringContainsString("'hash:secret'", $queries[1]);
        self::assertStringContainsString("WHERE group_name='Staff'", $queries[2]);
        self::assertStringContainsString('INSERT INTO groups', $queries[3]);
        self::assertStringContainsString("usrgrp_group_id='7'", $queries[4]);
        self::assertStringContainsString('42, 7', $queries[5]);
        self::assertSame([['users', 'user_id'], ['groups', 'group_id']], $insertCalls);
    }

    public function testHeaderOnlyTsvImportSucceeds(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_import_tsv_users/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . 'eval(substr($source, $match[0][1])); '
                    . '$file = tempnam(sys_get_temp_dir(), "openvsosh-users-tsv-"); '
                    . 'file_put_contents($file, "header\\n"); '
                    . '$result = F_import_tsv_users($file); unlink($file); '
                    . 'echo json_encode(["result" => $result]);',
                dirname(__DIR__) . '/admin/code/XMLUserImporter.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(['result' => true], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDestructionIgnoresAnAlreadyRemovedTemporaryFile(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "\nclass XMLUserImporter\n") + 1; '
                    . '$marker = "} // END OF CLASS"; $end = strpos($source, $marker, $start); '
                    . 'eval(substr($source, $start, $end - $start + strlen($marker))); '
                    . 'require_once "../config/tce_config.php"; restore_error_handler(); '
                    . 'error_reporting(E_ALL & ~E_DEPRECATED); '
                    . '$file = tempnam(sys_get_temp_dir(), "openvsosh-users-xml-"); '
                    . 'file_put_contents($file, "<users/>"); '
                    . '$importer = new XMLUserImporter($file); unlink($file); unset($importer); '
                    . 'echo "destroyed";',
                dirname(__DIR__) . '/admin/code/XMLUserImporter.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('destroyed', $output);
    }

    public function testEmptyDocumentParsesAndTemporaryFileIsDeletedOnDestruction(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "\nclass XMLUserImporter\n") + 1; '
                    . '$marker = "} // END OF CLASS"; $end = strpos($source, $marker, $start); '
                    . 'eval(substr($source, $start, $end - $start + strlen($marker))); '
                    . 'require_once "../config/tce_config.php"; restore_error_handler(); '
                    . 'error_reporting(E_ALL & ~E_DEPRECATED); '
                    . '$file = tempnam(sys_get_temp_dir(), "openvsosh-users-xml-"); '
                    . 'file_put_contents($file, "<users/>"); '
                    . '$importer = new XMLUserImporter($file); '
                    . 'echo $file;',
                dirname(__DIR__) . '/admin/code/XMLUserImporter.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringStartsWith('openvsosh-users-xml-', basename($output));
        self::assertFileDoesNotExist($output);
    }
}
