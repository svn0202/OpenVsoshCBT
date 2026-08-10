<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicResultUserControllerTest extends TestCase
{
    public function testPublishedResultKeepsSummaryStatisticsAndPdfLink(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_PUBLIC_TEST_RESULTS', 1);
define('K_NEWLINE', "\n");
define('K_TABLE_TEST_USER', 'test_users');
define('K_ENABLE_PUBLIC_PDF', true);
$l = [
    't_test_results' => 'Test results', 'a_meta_charset' => 'UTF-8', 'w_user' => 'User',
    'w_test' => 'Test', 'w_time_begin' => 'Start', 'h_time_begin' => 'Start time',
    'w_time_end' => 'End', 'h_time_end' => 'End time', 'w_test_time' => 'Duration',
    'w_passed' => 'Passed', 'w_not_passed' => 'Not passed', 'w_score' => 'Score',
    'h_score_total' => 'Total score', 'w_answers_right' => 'Right', 'h_answers_right' => 'Right answers',
    'w_answers_wrong' => 'Wrong', 'h_answers_wrong' => 'Wrong answers',
    'w_questions_unanswered' => 'Unanswered', 'h_questions_unanswered' => 'Unanswered questions',
    'w_questions_undisplayed' => 'Undisplayed', 'h_questions_undisplayed' => 'Undisplayed questions',
    'w_questions_unrated' => 'Unrated', 'h_questions_unrated' => 'Unrated questions',
    'w_comment' => 'Comment', 'h_testcomment' => 'Test comment', 'w_stats' => 'Statistics',
    'h_pdf' => 'PDF', 'w_pdf' => 'PDF', 'h_index' => 'Index', 'w_index' => 'Index',
    'hp_result_user' => 'Result help',
];
$db = 'db';
$_SESSION = ['session_user_id' => 7];
$_REQUEST = ['testuser_id' => '21', 'test_id' => '9'];
$GLOBALS['queries'] = [];
$GLOBALS['locked'] = null;
function F_db_query($sql, $db) { $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql)); return fopen('php://memory', 'r'); }
function F_db_fetch_assoc($result) { static $done = false; if ($done) { return false; } $done = true; return ['testuser_user_id' => '7']; }
function F_display_db_error() { echo '<DB-ERROR>'; }
function f_get_user_data($userId) { return ['user_name' => 'sam', 'user_firstname' => 'Sam', 'user_lastname' => 'Student']; }
function f_get_test_stat(...$arguments) {
    return ['qstats' => [
        'right' => 3, 'recurrence' => 5, 'right_perc' => 60, 'wrong' => 1, 'wrong_perc' => 20,
        'unanswered' => 1, 'unanswered_perc' => 20, 'undisplayed' => 0, 'undisplayed_perc' => 0,
        'unrated' => 0, 'unrated_perc' => 0,
    ]];
}
function f_get_user_test_stat(...$arguments) {
    return [
        'test_id' => 9, 'test_name' => 'Algebra <A>', 'test_description' => 'Final test',
        'user_test_start_time' => '2026-01-10 10:00:00', 'user_test_end_time' => '2026-01-10 11:00:00',
        'test_duration_time' => 90, 'test_score_threshold' => 6, 'user_score' => 8,
        'test_max_score' => 10, 'user_comment' => 'Good work', 'test_report_to_users' => '1',
        'test_results_anonymized' => '0', 'test_results_to_users' => '1',
    ];
}
function F_tmf_results_are_published($testInfo) { return true; }
function f_lock_user_test($testId, $userId) { $GLOBALS['locked'] = [$testId, $userId]; }
function f_get_boolean($value) { return $value === true || $value === 1 || $value === '1'; }
function F_tmf_result_identity($user, $anonymous) { return $anonymous ? 'Anonymous' : 'Sam Student (sam)'; }
function get_form_description_line($label, $title, $value) { return '<DESC:' . $label . ':' . $value . '>'; }
function F_decode_tcecode($value) { return '[[decoded:' . $value . ']]'; }
function f_print_user_test_stat($id) { return '<USER-STAT:' . $id . '>'; }
function f_print_test_stat(...$arguments) { $GLOBALS['stat_arguments'] = $arguments; return '<TEST-STAT>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([
    'html' => $html, 'queries' => $GLOBALS['queries'], 'locked' => $GLOBALS['locked'],
    'statArguments' => $GLOBALS['stat_arguments'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/tce_show_result_user.php'],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     html:string,queries:array{0:string},locked:array{int,int},
         *     statArguments:array{int,int,int,int,int,int,array<string,mixed>,int,bool}
         * } $result
         */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('FROM test_users WHERE testuser_test_id=9 AND testuser_id=21', $result['queries'][0]);
        self::assertSame([9, 7], $result['locked']);
        self::assertStringContainsString('<DESC:User::Sam Student (sam)>', $result['html']);
        self::assertStringContainsString('Algebra &lt;A&gt;', $result['html']);
        self::assertStringContainsString('<DESC:Duration::01:00:00>', $result['html']);
        self::assertStringContainsString('<DESC:Score::8 / 10 (80%) - Passed>', $result['html']);
        self::assertStringContainsString('<DESC:Right::3 / 5 (60%)>', $result['html']);
        self::assertStringContainsString('<DESC:Comment::[[decoded:Good work]]>', $result['html']);
        self::assertStringContainsString('<USER-STAT:21>', $result['html']);
        self::assertStringContainsString('<TEST-STAT>', $result['html']);
        self::assertStringContainsString(
            'tce_pdf_results.php?mode=3&amp;test_id=9&amp;user_id=7&amp;testuser_id=21',
            $result['html'],
        );
        self::assertSame([9, 0, 7, 0, 0, 21], array_slice($result['statArguments'], 0, 6));
        self::assertSame(2, $result['statArguments'][7]);
        self::assertTrue($result['statArguments'][8]);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
    }
}
