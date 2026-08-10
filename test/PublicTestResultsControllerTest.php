<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicTestResultsControllerTest extends TestCase
{
    public function testPublishedDetailedResultKeepsQuestionsAnswersAndTopicSummary(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_PUBLIC_TEST_RESULTS', 1);
define('K_ENABLE_ANSWER_EXPLANATION', true);
define('K_ENABLE_PUBLIC_PDF', true);
define('K_ENABLE_QUESTION_EXPLANATION', true);
define('K_NEWLINE', "\n");
define('K_TABLE_ANSWERS', 'answers');
define('K_TABLE_LOG_ANSWER', 'log_answers');
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_SUBJECTS', 'subjects');
define('K_TABLE_TESTS_LOGS', 'test_logs');
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
preg_match_all("/\['([a-z][a-z0-9_]*)'\]/", $source, $labels);
$l = array_fill_keys(array_unique($labels[1]), 'label');
$l['a_meta_charset'] = 'UTF-8';
$l['w_passed'] = 'Passed';
$l['w_not_passed'] = 'Not passed';
$l['w_score'] = 'Score';
$l['w_answers_right'] = 'Right';
$l['w_answers_wrong'] = 'Wrong';
$l['w_explanation'] = 'Explanation';
$l['w_position'] = 'Position';
$l['m_unanswered'] = 'Unanswered';
$l['h_answer_right'] = 'Correct';
$l['h_answer_wrong'] = 'Incorrect';
$l['h_pdf'] = 'PDF title';
$l['w_pdf'] = 'PDF report';
$l['h_index'] = 'Back';
$l['hp_result_user'] = 'Result help';
$db = 'db';
$_SESSION = ['session_user_id' => '7'];
$_REQUEST = ['testid' => '9'];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['locked'] = null;
function f_get_test_data($testId) {
    return [
        'test_name' => 'Algebra <A>', 'test_description' => 'Final & complete',
        'test_score_right' => '2', 'test_results_anonymized' => '0',
        'test_duration_time' => '90', 'test_report_to_users' => '1',
    ];
}
function F_tmf_results_are_published($testData) { return true; }
function f_lock_user_test($testId, $userId) { $GLOBALS['locked'] = [$testId, $userId]; }
function f_get_user_test_stat(...$arguments) {
    return [
        'test_start_time' => '2026-08-01 10:00:00', 'test_end_time' => '2026-08-01 10:12:34',
        'score_threshold' => '1', 'score' => '2', 'max_score' => '2', 'right' => '1', 'all' => '1',
        'comment' => 'Well done', 'testuser_id' => '21',
    ];
}
function f_get_user_data($userId) {
    return ['user_name' => 'sam', 'user_firstname' => 'Sam', 'user_lastname' => 'Student'];
}
function f_get_boolean($value) { return $value === true || $value === 1 || $value === '1'; }
function F_tmf_result_identity($user, $anonymous) { return $anonymous ? 'Anonymous' : 'Sam Student (sam)'; }
function get_form_description_line($label, $title, $value) { return '<DESC:' . $label . ':' . $value . '>'; }
function F_decode_tcecode($value) { return '[[decoded:' . $value . ']]'; }
function get_ip_as_string($value) { return '192.0.2.10'; }
function pdfLink(...$arguments) { $GLOBALS['pdf_arguments'] = $arguments; return 'result.pdf'; }
function F_display_db_error() { echo '<DB-ERROR>'; }
function F_db_query($sql, $db) {
    $normalized = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $normalized;
    $result = fopen('php://memory', 'r');
    $rows = str_contains($normalized, 'FROM questions, test_logs, subjects, modules')
        ? [[
            'question_type' => '1', 'module_id' => '3', 'module_name' => 'Numbers',
            'subject_id' => '4', 'subject_name' => 'Addition', 'question_difficulty' => '1',
            'testlog_score' => '2', 'testlog_change_time' => '2026-08-01 10:02:03',
            'testlog_display_time' => '2026-08-01 10:01:00', 'testlog_user_ip' => '3221225994',
            'testlog_reaction_time' => '1500', 'question_description' => 'What is 1 + 1?',
            'question_explanation' => 'Add the units', 'testlog_answer_text' => '',
            'testlog_id' => '31', 'testlog_comment' => 'Teacher note',
        ]]
        : [[
            'logansw_position' => '0', 'answer_position' => '1', 'logansw_selected' => '1',
            'answer_isright' => '1', 'answer_description' => 'Two', 'answer_explanation' => 'Correct sum',
        ], [
            'logansw_position' => '0', 'answer_position' => '2', 'logansw_selected' => '0',
            'answer_isright' => '0', 'answer_description' => 'Three', 'answer_explanation' => '',
        ]];
    $GLOBALS['rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][get_resource_id($result)]); }
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([
    'html' => $html, 'queries' => $GLOBALS['queries'], 'locked' => $GLOBALS['locked'],
    'pdfArguments' => $GLOBALS['pdf_arguments'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/tce_test_results.php'],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     html:string,queries:array{string,string},locked:array{int,string},
         *     pdfArguments:array{int,int,int,int,string,int}
         * } $result
         */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([9, '7'], $result['locked']);
        self::assertCount(2, $result['queries']);
        self::assertStringContainsString('AND testlog_testuser_id=21', $result['queries'][0]);
        self::assertStringContainsString("AND logansw_testlog_id='31'", $result['queries'][1]);
        self::assertStringContainsString('<DESC:label::Sam Student (sam)>', $result['html']);
        self::assertStringContainsString('Algebra &lt;A&gt;', $result['html']);
        self::assertStringContainsString('Final &amp; complete', $result['html']);
        self::assertStringContainsString('<DESC:label::00:12:34>', $result['html']);
        self::assertStringContainsString('<DESC:Score::2 / 2 (100%) - Passed>', $result['html']);
        self::assertStringContainsString('data-result-state="correct"', $result['html']);
        self::assertStringContainsString('IP:192.0.2.10', $result['html']);
        self::assertStringContainsString('[[decoded:What is 1 + 1?]]', $result['html']);
        self::assertStringContainsString('[[decoded:Add the units]]', $result['html']);
        self::assertStringContainsString('class="okbox">x</abbr>', $result['html']);
        self::assertStringContainsString('[[decoded:Two]]', $result['html']);
        self::assertStringContainsString('[[decoded:Three]]', $result['html']);
        self::assertStringContainsString('[[decoded:Teacher note]]', $result['html']);
        self::assertStringContainsString('2 / 2 (100%)', $result['html']);
        self::assertStringContainsString('<strong>Numbers</strong>', $result['html']);
        self::assertStringContainsString(' Addition</li>', $result['html']);
        self::assertStringContainsString('href="result.pdf"', $result['html']);
        self::assertSame([3, 9, 0, 7, '', 0], $result['pdfArguments']);
        self::assertStringContainsString('Result help', $result['html']);
        self::assertStringNotContainsString('<DB-ERROR>', $result['html']);
    }
}
