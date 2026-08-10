<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestSelectFunctionsTest extends TestCase
{
    public function testSelectionTableQueryStatusesAndPaginationRemainUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TABLE_TESTS', 'tests');
define('K_AUTH_ADMINISTRATOR', 10);
define('K_DATABASE_TYPE', 'MYSQL');
define('K_NEWLINE', "\n");
$db = 'db';
$l = [
    'a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'ltr', 'h_delete' => 'Delete selected',
    'h_test_description' => 'Description help', 'h_test_name' => 'Name help',
    'hp_select_tests' => 'Select tests help', 'm_databasempty' => 'No tests',
    'm_delete_confirm' => 'Confirm?', 'm_search_void' => 'No matches', 'w_check_all' => 'Check all',
    'w_datetime_format' => 'YYYY-MM-DD', 'w_delete' => 'Delete', 'w_description' => 'Description',
    'w_edit' => 'Edit', 'w_lock' => 'Lock', 'w_name' => 'Name', 'w_select' => 'Select',
    'w_tests' => 'Tests', 'w_time_begin' => 'Begin', 'w_time_end' => 'End', 'w_unlock' => 'Unlock',
];
$_SESSION = ['session_user_level' => 10, 'session_user_id' => 1];
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_select_tests.php';
$_REQUEST = ['checkall' => '1'];
$GLOBALS['queries'] = [];
$GLOBALS['row_index'] = 0;
$GLOBALS['navigator'] = null;
function F_escape_sql($db, $value) { return $value; }
function F_count_rows($table) { return 2; }
function f_legacy_literal_equals($left, $right) { return $left === $right; }
function f_legacy_int_equals($left, $right) { return (int) $left === (int) $right; }
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql));
    return fopen('php://memory', 'r');
}
function F_db_fetch_array($result) {
    $rows = [
        [
            'test_id' => 7, 'test_begin_time' => '2020-01-01 00:00:00',
            'test_end_time' => '1020-01-02 00:00:00', 'test_name' => 'Locked test',
            'test_description' => 'Locked description',
        ],
        [
            'test_id' => 8, 'test_begin_time' => '2999-01-01 00:00:00',
            'test_end_time' => '2999-01-02 00:00:00', 'test_name' => 'Future test',
            'test_description' => 'Future description',
        ],
    ];
    return $rows[$GLOBALS['row_index']++] ?? false;
}
function F_select_table_header_element($field, $direction, $help, $label, $order, $filter) {
    return "<HEADER:$field:$direction:$label:$order:$filter>";
}
function F_submit_button($name, $value, $title, $extra = '') { echo "<BUTTON:$name:$value:$title:$extra>"; }
function F_show_page_navigator(...$arguments) { $GLOBALS['navigator'] = $arguments; }
function F_print_error($type, $message) { echo "[[$type:$message]]"; }
function F_display_db_error() { echo '[[DB-ERROR]]'; }
function f_get_authorized_users($user_id) { return (string) $user_id; }
$source = file_get_contents($argv[1]);
preg_match('/function [Ff]_show_select_test\(/', $source, $match, PREG_OFFSET_CAPTURE);
$start = $match[0][1];
$end = strpos($source, "\n/**", $start);
$function = substr($source, $start, $end - $start);
$function = preg_replace('/^\s*require_once [^;]+;\n/m', '', $function);
ob_start();
eval('namespace Harness; ' . $function);
$result = F_show_select_test('invalid', 1, 3, 2, 'enabled=1', 'Math test');
$html = ob_get_clean();
echo json_encode([
    'result' => $result, 'html' => $html, 'queries' => $GLOBALS['queries'],
    'navigator' => $GLOBALS['navigator'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_functions_test_select.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{result:bool,html:string,queries:list<string>,navigator:array{string,string,int,int,string}} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($result['result']);
        self::assertSame(
            'SELECT * FROM tests WHERE (test_id>0) AND (enabled=1) '
                . 'ORDER BY test_begin_time DESC,test_name DESC LIMIT 2 OFFSET 3',
            $result['queries'][0] ?? null,
        );
        self::assertStringContainsString('name="testid4" id="testid4" value="7"', $result['html']);
        self::assertStringContainsString('checked="checked"', $result['html']);
        self::assertStringContainsString('record-status-locked">Заблокировано', $result['html']);
        self::assertStringContainsString('record-status-upcoming">Запланировано', $result['html']);
        self::assertStringContainsString('<BUTTON:delete:Delete:Delete selected:', $result['html']);
        self::assertStringNotContainsString('[[DB-ERROR]]', $result['html']);
        self::assertSame('/admin/code/tce_select_tests.php', $result['navigator'][0]);
        self::assertSame('SELECT count(*) AS total FROM tests WHERE (test_id>0) AND (enabled=1)', $result['navigator'][1]);
        self::assertSame(3, $result['navigator'][2]);
        self::assertSame(2, $result['navigator'][3]);
        self::assertSame(
            '&amp;order_field=test_begin_time+DESC%2Ctest_name&amp;orderdir=1'
                . '&amp;searchterms=Math+test&amp;submitted=1',
            $result['navigator'][4],
        );
    }

    public function testEmptyPopupTestSelectionReportsMessageAndReturnsFalse(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"]["m_databasempty"] = "No tests"; $GLOBALS["calls"] = []; '
                    . 'function F_escape_sql($db, $value) { $GLOBALS["calls"]["escaped"] = $value; return $value; } '
                    . 'function F_count_rows($table) { $GLOBALS["calls"]["table"] = $table; return 0; } '
                    . 'function F_print_error(...$arguments) { $GLOBALS["calls"]["message"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_show_select_test_popup\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$result = F_show_select_test_popup("invalid", "1", "4", "25", "enabled=1", "Math", "field"); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_test_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => false,
                'calls' => [
                    'escaped' => 'invalid',
                    'table' => 'tce_tests',
                    'message' => ['MESSAGE', 'No tests'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testEmptyTestSelectionReportsMessageAndReturnsFalse(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"]["m_databasempty"] = "No tests"; $GLOBALS["calls"] = []; '
                    . 'function F_escape_sql($db, $value) { $GLOBALS["calls"]["escaped"] = $value; return $value; } '
                    . 'function F_count_rows($table) { $GLOBALS["calls"]["table"] = $table; return 0; } '
                    . 'function F_print_error(...$arguments) { $GLOBALS["calls"]["message"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_show_select_test\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$result = F_show_select_test("invalid", "1", "4", "25", "enabled=1", "Math"); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_test_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => false,
                'calls' => [
                    'escaped' => 'invalid',
                    'table' => 'tce_tests',
                    'message' => ['MESSAGE', 'No tests'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testSelectTestWrapperForwardsArgumentsAndReturnsTrue(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["arguments"] = []; '
                    . 'function F_show_select_test(...$arguments) { $GLOBALS["arguments"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_select_test\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$result = F_select_test("name", "DESC", "4", "25", "enabled=1", "Math"); '
                    . 'echo json_encode(["result" => $result, "arguments" => $GLOBALS["arguments"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_test_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => true,
                'arguments' => ['name', 'DESC', '4', '25', 'enabled=1', 'Math'],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
