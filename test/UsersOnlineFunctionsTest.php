<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UsersOnlineFunctionsTest extends TestCase
{
    public function testOnlineUserListPreservesEmptyAndPopulatedRendering(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_SESSIONS", "sessions"); '
                    . 'define("K_DATABASE_TYPE", "MYSQLI"); define("K_NEWLINE", "\\n"); '
                    . '$GLOBALS["db"] = "db-link"; $GLOBALS["l"] = ['
                    . '"m_databasempty" => "Empty", "t_online_users" => "Online users", '
                    . '"w_user" => "User", "w_level" => "Level", "w_ip" => "IP", '
                    . '"hp_online_users" => "Help"]; $_SERVER["SCRIPT_NAME"] = "/admin/code/tce_users_online.php"; '
                    . 'function F_count_rows($table) { $GLOBALS["calls"]["count"][] = $table; '
                    . 'return $GLOBALS["mode"] === "populated" ? 1 : 0; } '
                    . 'function F_escape_sql($db, $value) { $GLOBALS["calls"]["escape"][] = [$db, $value]; '
                    . 'return $value; } function F_db_query($sql, $db) { '
                    . '$GLOBALS["calls"]["query"][] = [$sql, $db]; return "result"; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_session_string_to_array($data) { return $GLOBALS["session"]; } '
                    . 'function unhtmlentities($value) { return $value; } '
                    . 'function f_is_authorized_editor_for_user($userId) { '
                    . '$GLOBALS["calls"]["authorized"][] = $userId; return true; } '
                    . 'function F_display_db_error() { $GLOBALS["calls"]["db_error"] = true; } '
                    . 'function F_show_page_navigator(...$arguments) { '
                    . '$GLOBALS["calls"]["navigator"][] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "function f_list_online_users"); '
                    . '$function = substr($source, $start); '
                    . '$function = preg_replace("/^\\s*require_once .*;$/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$GLOBALS["mode"] = "empty"; ob_start(); '
                    . '$emptyResult = f_list_online_users("", "", 0, 0, 10); $emptyOutput = ob_get_clean(); '
                    . '$GLOBALS["mode"] = "populated"; $GLOBALS["rows"] = [["cpsession_data" => "encoded"], false]; '
                    . '$GLOBALS["session"] = ["session_user_lastname" => "Doe", '
                    . '"session_user_firstname" => "Jane", "session_user_name" => "jane", '
                    . '"session_user_id" => "17", "session_user_level" => "5", '
                    . '"session_user_ip" => "127.0.0.1"]; ob_start(); '
                    . '$listResult = f_list_online_users("WHERE active=1", "cpsession_id", 1, 5, 10); '
                    . '$listOutput = ob_get_clean(); echo json_encode([[$emptyResult, $emptyOutput], '
                    . '[$listResult, $listOutput], $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_users_online.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: array{0: false, 1: string},
         *     1: array{0: true, 1: string},
         *     2: array{
         *         count: list<string>,
         *         escape: list<array{0: string, 1: string}>,
         *         query: list<array{0: string, 1: string}>,
         *         authorized: list<int>,
         *         navigator: list<array{0: string, 1: string, 2: int, 3: int, 4: string}>,
         *         db_error?: bool
         *     }
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([false, '<h2>Empty</h2>'], $decoded[0]);
        self::assertTrue($decoded[1][0]);
        self::assertSame(
            '<div class="container">' . "\n"
                . '<table class="userselect">' . "\n"
                . '<caption class="sr-only">Online users</caption>' . "\n"
                . '<thead>' . "\n<tr>\n"
                . '<th scope="col">User</th>' . "\n"
                . '<th scope="col">Level</th>' . "\n"
                . '<th scope="col">IP</th>' . "\n</tr>\n</thead>\n"
                . '<tr><td align="left"><a href="tce_edit_user.php?user_id=17">'
                . 'Doe, Jane (jane)</a></td><td>5</td><td>127.0.0.1</td></tr>' . "\n"
                . '</table>' . "\n"
                . '<div class="pagehelp">Help</div>' . "\n</div>\n",
            $decoded[1][1],
        );
        self::assertSame(['sessions', 'sessions'], $decoded[2]['count']);
        self::assertSame([['db-link', 'WHERE active=1']], $decoded[2]['escape']);
        self::assertSame(
            [['SELECT * FROM sessions WHERE active=1 ORDER BY cpsession_id DESC LIMIT 10 OFFSET 5', 'db-link']],
            $decoded[2]['query'],
        );
        self::assertSame([17], $decoded[2]['authorized']);
        self::assertSame(
            [[
                '/admin/code/tce_users_online.php',
                'SELECT count(*) AS total FROM sessions WHERE active=1',
                5,
                10,
                '&amp;order_field=cpsession_id&amp;orderdir=1&amp;submitted=1',
            ]],
            $decoded[2]['navigator'],
        );
        self::assertArrayNotHasKey('db_error', $decoded[2]);
    }
}
