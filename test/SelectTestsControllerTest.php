<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class SelectTestsControllerTest extends TestCase
{
    public function testSearchAndLockContractsRemainUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_TESTS', 10);
define('K_MAX_ROWS_PER_PAGE', 50);
define('K_NEWLINE', "\n");
define('K_TABLE_TESTS', 'tests');
$l = [
    't_test_select' => 'Select tests',
    'a_meta_charset' => 'UTF-8',
    'w_search' => 'Search',
    'm_updated' => 'Updated',
];
$db = 'db';
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_select_tests.php';
$_REQUEST = ['searchterms' => 'alpha 2026-08-10', 'rowsperpage' => '1'];
$_POST = ['lock' => '1', 'testid1' => '7'];
$GLOBALS['queries'] = [];
$GLOBALS['selected'] = null;
$GLOBALS['messages'] = [];
function F_submit_button($name, $value, $title) { echo '<SUBMIT:' . $name . ':' . $value . ':' . $title . '>'; }
function get_form_noscript_select() { return '<NOSCRIPT>'; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function f_is_authorized_user(...$arguments) { return true; }
function F_db_query($sql, $db) { $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql)); return true; }
function F_display_db_error(...$arguments) { $GLOBALS['messages'][] = 'db-error'; }
function F_print_error($type, $message) { $GLOBALS['messages'][] = [$type, $message]; }
function F_select_test(...$arguments) { $GLOBALS['selected'] = $arguments; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['queries'], $GLOBALS['selected'], $GLOBALS['messages']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_select_tests.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string,list<string>,array{string,int,int,int,string,string},list<mixed>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $queries, $selected, $messages] = $decoded;
        self::assertStringContainsString('value="alpha 2026-08-10"', $html);
        self::assertStringContainsString('<SUBMIT:search:Search:Search>', $html);
        self::assertStringContainsString('<NOSCRIPT>', $html);
        self::assertStringContainsString("<CSRF>\n</form>", $html);
        self::assertSame(
            ['UPDATE tests SET test_end_time=test_end_time-10000000000000 WHERE test_id=7'],
            $queries,
        );
        self::assertSame('user_lastname,user_firstname', $selected[0]);
        self::assertSame(0, $selected[1]);
        self::assertSame(0, $selected[2]);
        self::assertSame(1, $selected[3]);
        self::assertSame(
            "(( (test_name LIKE '%alpha%') OR (test_description LIKE '%alpha%'))"
                . " AND ( (test_name LIKE '%2026-08-10%') OR (test_description LIKE '%2026-08-10%')"
                . " OR ((test_begin_time <= '2026-08-10') AND (test_end_time >= '2026-08-10'))))",
            preg_replace('/\s+/', ' ', $selected[4]),
        );
        self::assertSame('alpha 2026-08-10', $selected[5]);
        self::assertSame([['MESSAGE', 'Updated']], $messages);
    }
}
