<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminResultUserControllerTest extends TestCase
{
    public function testCompletedAttemptRendersSelectionsStatisticsAndActions(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_RESULTS', 10);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_EXTEND_TIME_MINUTES', 5);
define('K_NEWLINE', "\n");
define('K_SECONDS_IN_MINUTE', 60);
define('K_TABLE_TEST_USER', 'test_users');
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_TESTS_LOGS', 'test_logs');
define('K_TABLE_USERS', 'users');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
$keys = [
    'a_meta_charset', 'h_add_five_minutes', 'h_answers_right', 'h_answers_wrong', 'h_cancel', 'h_delete',
    'h_email_result', 'h_pdf', 'h_questions_unanswered', 'h_questions_undisplayed', 'h_questions_unrated',
    'h_score_total', 'h_select_user', 'h_test', 'h_testcomment', 'h_time_begin', 'h_time_end', 'hp_result_user',
    'm_authorization_denied', 'm_delete_confirm', 'm_deleted', 'm_updated', 't_result_user', 'w_answers_right',
    'w_answers_wrong', 'w_cancel', 'w_comment', 'w_delete', 'w_email_result', 'w_lock', 'w_not_passed',
    'w_passed', 'w_pdf', 'w_questions_unanswered', 'w_questions_undisplayed', 'w_questions_unrated', 'w_score',
    'w_select', 'w_stats', 'w_test', 'w_test_time', 'w_time_begin', 'w_time_end', 'w_unlock', 'w_user',
];
$l = [];
foreach ($keys as $key) { $l[$key] = $key; }
$l['a_meta_charset'] = 'UTF-8';
$db = 'db';
$formstatus = true;
$menu_mode = '';
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_show_result_user.php'];
$_SESSION = ['session_user_level' => 10];
$_POST = [];
$_REQUEST = ['test_id' => '7', 'testuser_id' => '21', 'user_id' => '4'];
$GLOBALS['rows'] = [];
$GLOBALS['queries'] = [];
function f_is_authorized_user(...$arguments) { return true; }
function F_count_rows(...$arguments) { return 1; }
function F_select_executed_tests_sql() { return 'SELECT executed_tests'; }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        str_contains($sql, 'MAX(testlog_change_time) AS test_end_time') => [[
            'testuser_id' => '21', 'testuser_test_id' => '7', 'testuser_user_id' => '4',
            'testuser_creation_time' => '2026-08-10 10:00:00', 'testuser_status' => '4',
            'test_end_time' => '2026-08-10 11:00:00',
        ]],
        str_starts_with($sql, 'SELECT test_score_right') => [[
            'test_score_right' => '1', 'test_duration_time' => '90',
        ]],
        $sql === 'SELECT executed_tests' => [[
            'test_id' => '7', 'test_begin_time' => '2026-08-10 09:00:00', 'test_name' => 'Final & Test',
        ]],
        str_starts_with($sql, 'SELECT testuser_id, user_lastname') => [[
            'testuser_id' => '21', 'user_lastname' => 'Doe', 'user_firstname' => 'Jane',
            'user_name' => 'jane', 'testuser_creation_time' => '2026-08-10 10:00:00',
        ]],
        default => [],
    };
    $GLOBALS['rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) {
    return array_shift($GLOBALS['rows'][get_resource_id($result)]);
}
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error(...$arguments) { echo '<FORM-ERROR>'; }
function F_submit_button($name, $label, $title) { echo '<BUTTON:' . $name . ':' . $label . '>'; }
function f_form_option_is_selected($selected, $value) { return (string) $selected === (string) $value; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_description_line($label, $title, $value) { return '<DESC:' . $label . ':' . $value . '>'; }
function f_get_test_stat(...$arguments) {
    return ['qstats' => [
        'right' => 3, 'recurrence' => 5, 'right_perc' => 60, 'wrong' => 1, 'wrong_perc' => 20,
        'unanswered' => 1, 'unanswered_perc' => 20, 'undisplayed' => 0, 'undisplayed_perc' => 0,
        'unrated' => 0, 'unrated_perc' => 0,
    ]];
}
function f_get_user_test_stat(...$arguments) {
    return [
        'test_score_threshold' => 6, 'user_score' => 8, 'test_max_score' => 10,
        'user_comment' => 'Good work',
    ];
}
function F_decode_tcecode($value) { return '[[decoded:' . $value . ']]'; }
function f_print_user_test_stat($id) { return '<USER-STAT:' . $id . '>'; }
function f_print_test_stat(...$arguments) { return '<TEST-STAT>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_show_result_user.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:list<string>} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($result['queries']);
        $firstQuery = $result['queries'][0] ?? '';
        self::assertStringContainsString('WHERE testlog_testuser_id=testuser_id AND testuser_id=21', $firstQuery);
        self::assertStringContainsString(
            '<option value="7" selected="selected">2026-08-10 Final &amp; Test</option>',
            $result['html'],
        );
        self::assertStringContainsString(
            '<option value="21" selected="selected">1. Doe Jane - jane [2026-08-10 10:00:00]</option>',
            $result['html'],
        );
        self::assertStringContainsString('<DESC:w_test_time::01:00:00>', $result['html']);
        self::assertStringContainsString('<DESC:w_score::8 / 10 (80%) - w_passed>', $result['html']);
        self::assertStringContainsString('<DESC:w_answers_right::3 / 5 (60%)>', $result['html']);
        self::assertStringContainsString('<DESC:w_comment::[[decoded:Good work]]>', $result['html']);
        self::assertStringContainsString('<USER-STAT:21>', $result['html']);
        self::assertStringContainsString('<TEST-STAT>', $result['html']);
        self::assertStringContainsString('<BUTTON:unlock:w_unlock>', $result['html']);
        self::assertStringContainsString(
            'tce_pdf_results.php?mode=3&amp;test_id=7&amp;testuser_id=21&amp;user_id=4',
            $result['html'],
        );
        self::assertStringContainsString('tce_attempt_archive.php?testuser_id=21', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
    }
}
