<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UserSelectFunctionsTest extends TestCase
{
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
