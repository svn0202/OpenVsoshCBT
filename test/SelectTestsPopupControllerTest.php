<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class SelectTestsPopupControllerTest extends TestCase
{
    public function testSanitizedSelectionAndSearchContractsRemainUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_TESTS', 10);
define('K_MAX_ROWS_PER_PAGE', 50);
define('K_NEWLINE', "\n");
$l = ['t_test_select' => 'Select tests', 'a_meta_charset' => 'UTF-8', 'w_search' => 'Search'];
$db = 'db';
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_select_tests_popup.php';
$_REQUEST = [
    'searchterms' => 'alpha 2026-08-10', 'rowsperpage' => '25',
    'cid' => 'Field-42_bad!', 'tids' => 'x1x02badx3',
];
$GLOBALS['shown'] = null;
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function get_form_noscript_select() { return '<NOSCRIPT>'; }
function F_show_select_test_popup(...$arguments) { $GLOBALS['shown'] = $arguments; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['shown']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_select_tests_popup.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string,array{string,int,int,int,string,string,string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $shown] = $decoded;
        self::assertStringContainsString('name="cid" id="cid" value="ield42_bad"', $html);
        self::assertStringContainsString('name="tids" id="tids" value="x1x02x3"', $html);
        self::assertStringContainsString('value="alpha 2026-08-10"', $html);
        self::assertStringContainsString('<SUBMIT:search:Search:Search>', $html);
        self::assertStringContainsString('<NOSCRIPT>', $html);
        self::assertStringContainsString("<CSRF>\n</form>", $html);
        self::assertSame('test_begin_time DESC,test_name', $shown[0]);
        self::assertSame(0, $shown[1]);
        self::assertSame(0, $shown[2]);
        self::assertSame(25, $shown[3]);
        self::assertSame(
            "(( (test_name LIKE '%alpha%') OR (test_description LIKE '%alpha%'))"
                . " AND ( (test_name LIKE '%2026-08-10%') OR (test_description LIKE '%2026-08-10%')"
                . " OR ((test_begin_time <= '2026-08-10') AND (test_end_time >= '2026-08-10'))))"
                . ' AND (test_id IN (0,1,2,3))',
            preg_replace('/\s+/', ' ', $shown[4]),
        );
        self::assertSame('alpha 2026-08-10', $shown[5]);
        self::assertSame('ield42_bad', $shown[6]);
    }
}
