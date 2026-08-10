<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicAllResultsControllerTest extends TestCase
{
    public function testSelectedFiltersAndReportRenderingRemainUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_PUBLIC_TEST_RESULTS', 1);
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_GROUPS', 'groups');
define('K_TABLE_USERGROUP', 'usergroups');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_NEWLINE', "\n");
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
preg_match_all("/\['([a-z][a-z0-9_]*)'\]/", $source, $labels);
$l = array_fill_keys(array_unique($labels[1]), 'label');
$l['a_meta_charset'] = 'UTF-8';
$_REQUEST = json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$_SESSION = ['session_user_id' => 9];
$_SERVER = ['SCRIPT_NAME' => '/public/code/tce_test_allresults.php'];
$db = null;
$GLOBALS['calls'] = [];
function f_is_authorized_user(...$arguments) { return true; }
function F_print_error(...$arguments) { $GLOBALS['calls'][] = ['error', $arguments]; }
function f_get_test_groups($testId) { return '3'; }
function f_get_test_id_results(...$arguments) { $GLOBALS['calls'][] = ['ids', $arguments]; return '7'; }
function F_db_query($sql, $db) { $GLOBALS['calls'][] = ['query', [preg_replace('/\s+/', ' ', $sql)]]; return fopen('php://memory', 'r'); }
function F_db_fetch_array($result) { return false; }
function F_display_db_error() { $GLOBALS['calls'][] = ['db-error', []]; }
function f_form_option_is_selected($selected, $value) { return $selected === $value; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_text_input(...$arguments) { return '<TEXT:' . $arguments[0] . ':' . $arguments[4] . '>'; }
function get_form_row_checkbox(...$arguments) { return '<CHECK:' . $arguments[0] . ':' . $arguments[5] . '>'; }
function f_get_all_users_test_stat(...$arguments) { $GLOBALS['calls'][] = ['stats', $arguments]; return ['num_records' => 2, 'svgpoints' => ':x:x']; }
function f_print_test_result_stat(...$arguments) { $GLOBALS['calls'][] = ['summary', $arguments]; return '<SUMMARY>'; }
function f_print_test_stat(...$arguments) { $GLOBALS['calls'][] = ['details', $arguments]; return '<DETAILS>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['calls']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/public/code/tce_test_allresults.php',
                base64_encode(json_encode([
                    'test_id' => '7',
                    'group_id' => '3',
                    'startdate' => '2026-08-01 10:20:30',
                    'enddate' => '2026-08-02 11:22:33',
                    'display_mode' => '2',
                    'show_graph' => '1',
                    'order_field' => 'user_name',
                    'orderdir' => '1',
                ], JSON_THROW_ON_ERROR)),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        self::assertJson($output);
        /** @var array{string,list<array{string,list<mixed>}>} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $calls] = $result;
        self::assertStringContainsString('<SUMMARY>', $html);
        self::assertStringContainsString('<DETAILS>', $html);
        self::assertStringContainsString('tce_svg_graph.php?w=800&amp;h=300&amp;p=x:x', $html);
        self::assertStringContainsString(
            'tce_pdf_results.php?mode=1user_id=9&amp;test_id=7&amp;group_id=3'
                . '&amp;startdate=2026-08-01+10%3A20%3A30&amp;enddate=2026-08-02+11%3A22%3A33'
                . '&amp;display_mode=2&amp;display_mode=2&amp;show_graph=1&amp;order_field=user_name&amp;orderdir=1',
            $html,
        );
        $stats = array_values(array_filter($calls, static fn (array $call): bool => $call[0] === 'stats'));
        self::assertSame(
            [7, 3, 9, '2026-08-01 10:20:30', '2026-08-02 11:22:33', 'user_name DESC', true, 2],
            $stats[0][1] ?? null,
        );
    }
}
