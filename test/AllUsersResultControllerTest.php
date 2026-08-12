<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AllUsersResultControllerTest extends TestCase
{
    public function testTestSelectorUsesReadOnlyNavigation(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../admin/code/tce_show_result_allusers.php');

        self::assertStringContainsString(
            "window.location.assign(\\'tce_show_result_allusers.php?test_id=",
            $controller,
        );
        self::assertStringNotContainsString(
            "form_resultallusers\\').changecategory.value=1",
            $controller,
        );
    }

    public function testFiltersAndSelectionsAreRenderedWithoutLoadingStatistics(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_RESULTS', 10);
define('K_NEWLINE', "\n");
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_TABLE_GROUPS', 'groups');
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_TEST_USER', 'test_users');
define('K_TABLE_USERS', 'users');
define('K_TABLE_USERGROUP', 'user_groups');
$keys = [
    'a_meta_charset', 'h_test', 'hp_result_alluser', 't_result_all_users', 'w_answer', 'w_disabled',
    'w_group', 'w_graph', 'w_minimum', 'w_mode', 'w_module', 'w_question', 'w_result_graph',
    'w_select', 'w_stats', 'w_subject', 'w_test', 'w_time_begin', 'w_time_end', 'w_user',
    'w_datetime_format', 'm_authorization_denied',
];
$l = [];
foreach ($keys as $key) { $l[$key] = $key; }
$l['a_meta_charset'] = 'UTF-8';
$db = 'db';
$menu_mode = '';
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_show_result_allusers.php'];
$_SESSION = [];
$_POST = [];
$_REQUEST = [
    'test_id' => '7', 'group_id' => '3', 'user_id' => '4',
    'startdate' => '2026-01-02 03:04:05', 'enddate' => '2026-08-09 10:11:12',
    'display_mode' => '2', 'show_graph' => '1', 'order_field' => 'user_name', 'orderdir' => '1',
];
$GLOBALS['rows'] = [];
function f_is_authorized_user(...$arguments) { return true; }
function f_get_test_groups($testId) { return '3,4'; }
function F_select_executed_tests_sql() { return 'SELECT executed_tests'; }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        $sql === 'SELECT executed_tests' => [[
            'test_id' => '7', 'test_begin_time' => '2026-02-03 12:00:00', 'test_name' => 'Final & Test',
        ]],
        str_starts_with($sql, 'SELECT * FROM groups') => [['group_id' => '3', 'group_name' => 'Group & Three']],
        str_starts_with($sql, 'SELECT user_id') => [[
            'user_id' => '4', 'user_lastname' => 'Doe', 'user_firstname' => 'Jane', 'user_name' => 'jane',
        ]],
        default => [],
    };
    $GLOBALS['rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) {
    $id = get_resource_id($result);
    return array_shift($GLOBALS['rows'][$id]);
}
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error(...$arguments) { echo '<FORM-ERROR>'; }
function f_form_option_is_selected($selected, $value) { return (int) $selected === (int) $value; }
function f_openvsosh_admin_test_context($testId, $section) { return '<CONTEXT:' . $testId . ':' . $section . '>'; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_text_input($name, $label, $title, $required, $value, ...$arguments) {
    return '<TEXT:' . $name . ':' . $value . '>';
}
function get_form_row_checkbox($name, $label, $title, $required, $value, $checked) {
    return '<CHECKBOX:' . $name . ':' . (int) $checked . '>';
}
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode($html, JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_show_result_allusers.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var string $html */
        $html = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsString($html);
        self::assertStringContainsString('<CONTEXT:7:results>', $html);
        self::assertStringContainsString('<option value="7" selected="selected">2026-02-03 Final &amp; Test</option>', $html);
        self::assertStringContainsString('<option value="3" selected="selected">Group &amp; Three</option>', $html);
        self::assertStringContainsString('<option value="4" selected="selected">1. Doe Jane - jane</option>', $html);
        self::assertStringContainsString('<TEXT:startdate:2026-01-02 03:04:05>', $html);
        self::assertStringContainsString('<TEXT:enddate:2026-08-09 10:11:12>', $html);
        self::assertStringContainsString('<option value="2" selected="selected">w_module</option>', $html);
        self::assertStringContainsString('<CHECKBOX:show_graph:1>', $html);
        self::assertStringContainsString('name="order_field" id="order_field" value="user_name"', $html);
        self::assertStringContainsString('name="orderdir" id="orderdir" value="1"', $html);
        self::assertStringContainsString('<CSRF>', $html);
        self::assertStringNotContainsString('<DB-ERROR>', $html);
    }
}
