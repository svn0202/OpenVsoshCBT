<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UserSelectFunctionsTest extends TestCase
{
    public function testSelectUserWrapperForwardsArgumentsAndReturnsTrue(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["arguments"] = []; '
                    . 'function F_show_select_user(...$arguments) { $GLOBALS["arguments"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_select_user\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$result = F_select_user("name", "DESC", "4", "25", 7, "active=1", "Ada"); '
                    . 'echo json_encode(["result" => $result, "arguments" => $GLOBALS["arguments"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => true,
                'arguments' => ['name', 'DESC', '4', '25', 7, 'active=1', 'Ada'],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserGroupSelectRenderingRemainsUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["l"] = ["a_meta_charset" => "UTF-8", "w_group" => "Group"]; '
                    . '$GLOBALS["db"] = "connection"; $GLOBALS["query"] = ""; '
                    . '$GLOBALS["rows"] = [["group_id" => 3, "group_name" => "Alpha&Beta"]]; '
                    . 'function F_user_group_select_sql() { return "groups-query"; } '
                    . 'function F_db_query($query, $db) { $GLOBALS["query"] = $query; return "result"; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_user_group_select\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$html = F_user_group_select("team"); '
                    . 'echo json_encode(["html" => $html, "query" => $GLOBALS["query"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'html' => '<select name="team" id="team" title="группа" aria-label="группа">' . "\n"
                    . '<option value="0" style="color:gray" selected="selected">группа</option>' . "\n"
                    . '<option value="3"> Alpha&amp;Beta&nbsp;</option>' . "\n</select>\n",
                'query' => 'groups-query',
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserGroupSelectionSqlRemainsRoleSpecific(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_user_group_select_sql/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval(substr($source, $start, $end - $start)); '
                    . 'require_once "../config/tce_config.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; '
                    . '$admin = F_user_group_select_sql("group_id>2"); '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR - 1; '
                    . '$_SESSION["session_user_id"] = 17; '
                    . '$user = F_user_group_select_sql("group_id>2"); '
                    . 'echo json_encode(["admin" => $admin, "user" => $user]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'admin' => 'SELECT * FROM tce_user_groups WHERE group_id>2 ORDER BY group_name',
                'user' => 'SELECT group_id,group_name FROM tce_user_groups, tce_usrgroups '
                    . 'WHERE group_id=usrgrp_group_id AND usrgrp_user_id=17 AND group_id>2 ORDER BY group_name',
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserGroupsQueryAndResultRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["query"] = ""; '
                    . '$GLOBALS["rows"] = [["usrgrp_group_id" => 3], ["usrgrp_group_id" => "7"]]; '
                    . 'function F_db_query($query, $db) { $GLOBALS["query"] = $query; return "result"; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_get_user_groups/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$result = F_get_user_groups("12x"); '
                    . 'echo json_encode(["result" => $result, "query" => $GLOBALS["query"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => [3, '7'],
                'query' => "SELECT usrgrp_group_id\n\t\tFROM tce_usrgroups\n\t\tWHERE usrgrp_user_id=12",
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
