<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestAccessRulesControllerTest extends TestCase
{
    public function testRulePersistenceAndRenderedFormRemainUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_TESTS', 10);
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
$l = ['m_authorization_denied' => 'Denied', 'a_meta_charset' => 'UTF-8'];
$db = 'db';
$_REQUEST = ['test_id' => '1'];
$_POST = [
    'save_rules' => '1', 'test_id' => '1', 'csrf_token' => 'token',
    'required_finished' => '2', 'required_passed' => '0', 'minimum_duration' => '7',
    'require_all_answers' => '1', 'live_score' => '1', 'results_to_users' => '1',
    'results_publish_at' => '2026-07-27T10:00', 'results_unpublish_at' => '2026-07-28T10:00',
    'completion_message' => 'Готово безопасно',
];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [
    'tests-list' => [['test_id' => 1, 'test_name' => 'Current'], ['test_id' => 2, 'test_name' => 'Required'], false],
    'rules' => [[
        'test_required_finished_id' => 2, 'test_required_passed_id' => 0,
        'test_minimum_duration_time' => 7, 'test_require_all_answers' => 1,
        'test_block_finish_below_threshold' => 0, 'test_live_score' => 1,
        'test_auto_fullscreen' => 0, 'test_hide_exam_info' => 0, 'test_results_to_users' => 1,
        'test_results_publish_at' => '2026-07-27 10:00:00',
        'test_results_unpublish_at' => '2026-07-28 10:00:00', 'test_results_anonymized' => 0,
        'test_disable_previous' => 0, 'test_disable_next' => 0, 'test_hide_editor' => 0,
        'test_completion_message' => 'Готово безопасно',
    ]],
];
function F_select_tests_sql() { return 'AUTHORIZED TESTS'; }
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql));
    if ($sql === 'AUTHORIZED TESTS') { return 'tests-list'; }
    if (str_starts_with($sql, 'SELECT *')) { return 'rules'; }
    return true;
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][$result]); }
function F_print_error(...$arguments) { echo '<ERROR>'; }
function check_csrf_token($token) { return $token === 'token'; }
function F_tmf_test_prerequisite_would_cycle($testId, $ids) { return false; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function f_openvsosh_admin_test_context($testId, $section) { return "<CONTEXT:$testId:$section>"; }
function f_get_boolean($value) { return (bool) $value; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_test_access_rules.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string,list<string>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $queries] = $decoded;
        self::assertCount(3, $queries);
        self::assertSame('AUTHORIZED TESTS', $queries[0] ?? null);
        self::assertStringContainsString('UPDATE tests SET test_required_finished_id=2,', $queries[1] ?? '');
        self::assertStringContainsString('test_minimum_duration_time=7,', $queries[1] ?? '');
        self::assertStringContainsString("test_require_all_answers='1',", $queries[1] ?? '');
        self::assertStringContainsString("test_results_publish_at='2026-07-27 10:00:00',", $queries[1] ?? '');
        self::assertStringContainsString("test_completion_message='Готово безопасно' WHERE test_id=1", $queries[1] ?? '');
        self::assertSame('SELECT * FROM tests WHERE test_id=1 LIMIT 1', $queries[2] ?? null);
        self::assertStringContainsString('<p role="status">Настройки сохранены.</p>', $html);
        self::assertStringContainsString('<CONTEXT:1:access>', $html);
        self::assertStringContainsString('<option value="2" selected="selected">Required</option>', $html);
        self::assertStringContainsString('name="minimum_duration" id="minimum_duration" min="0" max="1440" value="7"', $html);
        self::assertStringContainsString('name="live_score" id="live_score" value="1" checked="checked"', $html);
        self::assertStringContainsString('>Готово безопасно</textarea>', $html);
        self::assertStringContainsString('<CSRF></form>', $html);
        self::assertStringNotContainsString('<ERROR>', $html);
    }
}
