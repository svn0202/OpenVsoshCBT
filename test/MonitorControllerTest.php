<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class MonitorControllerTest extends TestCase
{
    public function testPageWithoutSelectedTestKeepsFiltersAndAvailableTests(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_OPERATOR', 5);
define('K_NEWLINE', "\n");
$l = ['a_meta_charset' => 'UTF-8', 'm_authorization_denied' => 'Denied'];
$db = 'db';
$_REQUEST = [];
$_GET = [];
$_POST = [];
$GLOBALS['rows'] = [[
    'test_id' => 17, 'test_name' => 'Олимпиада & <финал>', 'test_user_id' => 4,
    'test_duration_time' => 60, 'test_password' => '',
], false];
$GLOBALS['queries'] = [];
function F_select_tests_sql() { return 'AUTHORIZED TESTS'; }
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = $sql;
    return fopen('php://memory', 'r');
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows']); }
function F_print_error(...$arguments) { echo '<ERROR>'; }
function f_openvsosh_admin_test_context($testId, $section) { return "<CONTEXT:$testId:$section>"; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['queries'], $pagelevel, $thispage_title], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_monitor.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string, list<string>, int, string} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $queries, $pageLevel, $title] = $result;
        self::assertSame(5, $pageLevel);
        self::assertSame('Наблюдение за тестированием', $title);
        self::assertSame(['AUTHORIZED TESTS'], $queries);
        self::assertStringContainsString('<CONTEXT:0:monitor>', $html);
        self::assertStringContainsString(
            '<option value="17">Олимпиада &amp; &lt;финал&gt;</option>',
            $html,
        );
        self::assertStringContainsString('<option value="">Все</option>', $html);
        self::assertStringContainsString('name="search" id="search" value=""', $html);
        self::assertStringNotContainsString('monitor-summary', $html);
        self::assertStringNotContainsString('<ERROR>', $html);
    }
}
