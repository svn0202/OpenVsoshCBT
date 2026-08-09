<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UserSelectFunctionsTest extends TestCase
{
    public function testUserEditorAuthorizationBranchesRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; $GLOBALS["queries"] = []; '
                    . 'function F_count_rows($query) { '
                    . '$GLOBALS["queries"][] = preg_replace("/\\s+/", " ", trim($query)); return 1; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (F_isAuthorizedEditorForUser|f_is_authorized_editor_for_user)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualifiedName = __NAMESPACE__ . "\\\\" . $name; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; $admin = $qualifiedName(17); '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR - 1; '
                    . '$_SESSION["session_user_id"] = "12x"; '
                    . '$new = $qualifiedName(0); $editor = $qualifiedName("17x"); '
                    . 'echo json_encode(["admin" => $admin, "new" => $new, '
                    . '"editor" => $editor, "queries" => $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'admin' => true,
                'new' => true,
                'editor' => true,
                'queries' => [
                    'tce_usrgroups AS ta, tce_usrgroups AS tb '
                        . 'WHERE ta.usrgrp_group_id=tb.usrgrp_group_id '
                        . 'AND ta.usrgrp_user_id=17 AND tb.usrgrp_user_id=12 LIMIT 1',
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testGroupEditorAuthorizationBranchesRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; $GLOBALS["calls"] = []; '
                    . 'function f_is_user_on_group($user, $group) { '
                    . '$GLOBALS["calls"][] = [$user, $group]; return [$user, $group]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (F_isAuthorizedEditorForGroup|f_is_authorized_editor_for_group)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualifiedName = __NAMESPACE__ . "\\\\" . $name; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; '
                    . '$admin = $qualifiedName(7); '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR - 1; '
                    . '$_SESSION["session_user_id"] = 12; '
                    . '$empty = $qualifiedName(0); $editor = $qualifiedName(7); '
                    . 'echo json_encode(["admin" => $admin, "empty" => $empty, '
                    . '"editor" => $editor, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'admin' => true,
                'empty' => true,
                'editor' => [12, 7],
                'calls' => [[12, 7]],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testGroupMembershipLookupsRemainUnchanged(): void
    {
        $expectations = [
            'test' => [
                'old' => 'F_isTestOnGroup',
                'new' => 'f_is_test_on_group',
                'query' => 'SELECT tstgrp_test_id FROM tce_testgroups '
                    . 'WHERE tstgrp_test_id=12 AND tstgrp_group_id=3 LIMIT 1',
            ],
            'user' => [
                'old' => 'F_isUserOnGroup',
                'new' => 'f_is_user_on_group',
                'query' => 'SELECT usrgrp_user_id FROM tce_usrgroups '
                    . 'WHERE usrgrp_user_id=12 AND usrgrp_group_id=3 LIMIT 1',
            ],
        ];

        foreach ($expectations as $kind => $expected) {
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    'namespace Harness; require_once "../config/tce_config.php"; $GLOBALS["query"] = ""; '
                        . 'function F_db_query($query, $db) { '
                        . '$GLOBALS["query"] = preg_replace("/\\s+/", " ", trim($query)); return "result"; } '
                        . 'function F_db_fetch_array($result) { return ["matched" => true]; } '
                        . '$source = file_get_contents($argv[1]); $old = $argv[2]; $new = $argv[3]; '
                        . 'preg_match("/function (" . $old . "|" . $new . ")\\(/", '
                        . '$source, $match, PREG_OFFSET_CAPTURE); '
                        . '$name = $match[1][0]; $start = $match[0][1]; '
                        . '$end = strpos($source, "\\n/**", $start); '
                        . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                        . '$qualifiedName = __NAMESPACE__ . "\\\\" . $name; $result = $qualifiedName("12x", "3x"); '
                        . 'echo json_encode(["result" => $result, "query" => $GLOBALS["query"]]);',
                    dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
                    $expected['old'],
                    $expected['new'],
                ],
                dirname(__DIR__) . '/admin/code',
            );

            self::assertSame(0, $status, $kind . ': ' . $output);
            self::assertSame(
                ['result' => true, 'query' => $expected['query']],
                json_decode($output, true, 512, JSON_THROW_ON_ERROR),
                $kind,
            );
        }
    }

    public function testUserIdLookupByRegistrationNumberRemainsUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; $GLOBALS["calls"] = []; '
                    . 'function F_escape_sql($db, $value) { $GLOBALS["calls"]["escaped"] = $value; return "safe"; } '
                    . 'function F_db_query($query, $db) { $GLOBALS["calls"]["query"] = $query; return "result"; } '
                    . 'function F_db_fetch_array($result) { return ["user_id" => "42"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (F_getUIDfromRegnum|f_get_uid_from_regnum)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . 'eval("namespace Harness; " . substr($source, $start)); '
                    . '$qualifiedName = __NAMESPACE__ . "\\\\" . $name; $result = $qualifiedName("REG-7"); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => '42',
                'calls' => [
                    'escaped' => 'REG-7',
                    'query' => "SELECT user_id FROM tce_users WHERE user_regnumber='safe' LIMIT 1",
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testEmptyPopupUserSelectionReportsMessageAndReturnsFalse(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"]["m_databasempty"] = "No users"; $GLOBALS["calls"] = []; '
                    . 'function F_escape_sql($db, $value) { $GLOBALS["calls"]["escaped"] = $value; return $value; } '
                    . 'function F_count_rows($table) { $GLOBALS["calls"]["table"] = $table; return 0; } '
                    . 'function F_print_error(...$arguments) { $GLOBALS["calls"]["message"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_show_select_user_popup\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$result = F_show_select_user_popup('
                    . '"invalid", "1", "4", "25", "7", "active=1", "Ada", "field"); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => false,
                'calls' => [
                    'escaped' => 'invalid',
                    'table' => 'tce_users',
                    'message' => ['MESSAGE', 'No users'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testEmptyUserSelectionReportsMessageAndReturnsFalse(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"]["m_databasempty"] = "No users"; $GLOBALS["calls"] = []; '
                    . 'function F_escape_sql($db, $value) { $GLOBALS["calls"]["escaped"] = $value; return $value; } '
                    . 'function F_count_rows($table) { $GLOBALS["calls"]["table"] = $table; return 0; } '
                    . 'function F_print_error(...$arguments) { $GLOBALS["calls"]["message"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_show_select_user\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$result = F_show_select_user("invalid", "1", "4", "25", "7", "active=1", "Ada"); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_user_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => false,
                'calls' => [
                    'escaped' => 'invalid',
                    'table' => 'tce_users',
                    'message' => ['MESSAGE', 'No users'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

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
