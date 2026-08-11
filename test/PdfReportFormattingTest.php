<?php

namespace Test;

use PHPUnit\Framework\TestCase;

if (!defined('PDF_MARGIN_LEFT')) {
    define('PDF_MARGIN_LEFT', 15.0);
}
if (!defined('PDF_MARGIN_TOP')) {
    define('PDF_MARGIN_TOP', 27.0);
}

require_once __DIR__ . '/../shared/code/tce_pdf_report.php';
require_once __DIR__ . '/Support/FormattingReport.php';

final class PdfReportFormattingTest extends TestCase
{
    private FormattingReport $report;

    protected function setUp(): void
    {
        // @mago-expect lint:no-global -- PDF report methods read their translations from global $l
        $GLOBALS['l'] = [
            'a_meta_dir' => 'ltr', 'w_all' => 'All', 'w_answer' => 'Answer', 'w_answer_time' => 'Time',
            'w_answers_right' => 'Right', 'w_answers_right_th' => 'Right', 'w_answers_wrong' => 'Wrong',
            'w_answers_wrong_th' => 'Wrong', 'w_comment' => 'Comment', 'w_firstname' => 'First name',
            'w_kurtosi' => 'Kurtosis', 'w_lastname' => 'Last name', 'w_mean' => 'Mean', 'w_median' => 'Median',
            'w_mode' => 'Mode', 'w_module' => 'Module', 'w_not_passed' => 'Not passed', 'w_passed' => 'Passed',
            'w_question' => 'Question', 'w_questions_unanswered' => 'Unanswered',
            'w_questions_unanswered_th' => 'Unanswered', 'w_questions_undisplayed' => 'Undisplayed',
            'w_questions_undisplayed_th' => 'Undisplayed', 'w_questions_unrated' => 'Unrated',
            'w_questions_unrated_th' => 'Unrated', 'w_recurrence' => 'Count', 'w_results' => 'Results',
            'w_score' => 'Score', 'w_skewness' => 'Skewness', 'w_standard_deviation' => 'Deviation',
            'w_statistics' => 'Statistics', 'w_subject' => 'Subject', 'w_test' => 'Test',
            'w_test_score_threshold' => 'Threshold', 'w_time' => 'Time', 'w_time_begin' => 'Started',
            'w_time_end' => 'Finished', 'w_user' => 'User',
        ];
        $this->report = new FormattingReport();
    }

    public function testMalformedOptionalFileOptionsFallBackWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("PDF_MARGIN_LEFT", 15.0); define("PDF_MARGIN_TOP", 27.0); '
                    . 'define("K_PDF_ALLOWED_PATHS", "not-serialized"); '
                    . 'define("K_PDF_ALLOWED_HOSTS", "also-not-serialized"); '
                    . 'require $argv[1]; class InspectablePdfReport extends TcePdfReport {'
                    . 'public static function fileOptions() { return parent::buildFileOptions(); }} '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$options = InspectablePdfReport::fileOptions(); restore_error_handler(); '
                    . 'echo json_encode([$options, $warnings]);',
                dirname(__DIR__) . '/shared/code/tce_pdf_report.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('[null,[]]', $output);
    }

    /** @throws \Throwable */
    public function testQuestionStatisticsKeepLabelsAndFormattedValues(): void
    {
        $this->report->printQuestionStats([
            'recurrence' => '5', 'recurrence_perc' => '100', 'average_score' => '1.25',
            'average_score_perc' => '62.5', 'average_time' => '65', 'right' => '3', 'right_perc' => '60',
            'wrong' => '1', 'wrong_perc' => '20', 'unanswered' => '1', 'unanswered_perc' => '20',
            'undisplayed' => '0', 'undisplayed_perc' => '0', 'unrated' => '0', 'unrated_perc' => '0',
            'module' => [],
        ], 2);

        self::assertCount(1, $this->report->htmlBlocks);
        $html = $this->report->htmlBlocks[0] ?? '';
        self::assertStringContainsString('Statistics [All + Module]', $html);
        self::assertStringContainsString('1.250', $html);
        self::assertStringContainsString('01:05', $html);
        self::assertStringContainsString('3', $html);
    }

    /** @throws \Throwable */
    public function testResultSummaryKeepsIdentityScoresAndDistribution(): void
    {
        $this->report->printTestResultStat([
            'testuser' => [[
                'passmsg' => 'Passed', 'num' => '1', 'testuser_creation_time' => '2026-08-10 10:00:00',
                'time_diff' => '01:00:00', 'test' => ['test_name' => 'Final & Test'], 'user_name' => 'jane',
                'user_lastname' => 'Doe', 'user_firstname' => 'Jane', 'total_score' => '8',
                'total_score_perc' => '80', 'right' => '3', 'right_perc' => '60', 'wrong' => '1',
                'wrong_perc' => '20', 'unanswered' => '1', 'unanswered_perc' => '20', 'undisplayed' => '0',
                'undisplayed_perc' => '0', 'unrated' => '0', 'unrated_perc' => '0',
            ]],
            'passed' => '1', 'passed_perc' => '75',
            'statistics' => ['mean' => [
                'score_perc' => '75', 'right_perc' => '60', 'wrong_perc' => '20',
                'unanswered_perc' => '20', 'undisplayed_perc' => '0', 'unrated_perc' => '0',
            ]],
        ], false, 2);

        self::assertSame(['Results'], $this->report->bookmarks);
        $html = $this->report->htmlBlocks[0] ?? '';
        self::assertStringContainsString('Final &amp; Test', $html);
        self::assertStringContainsString('jane - Doe, Jane', $html);
        self::assertStringContainsString('8.000', $html);
        self::assertStringContainsString('Passed: 1', $html);
        self::assertStringContainsString('Mean', $html);
    }

    /** @throws \Throwable */
    public function testUserInfoKeepsElapsedTimeScoreAndDetailsCall(): void
    {
        $data = [
            'id' => 21, 'user_id' => 4, 'user_lastname' => 'Doe', 'user_firstname' => 'Jane',
            'user_name' => 'jane', 'total_score' => '8', 'total_score_perc' => '80', 'recurrence' => '5',
            'right' => '3', 'right_perc' => '60', 'wrong' => '1', 'wrong_perc' => '20',
            'unanswered' => '1', 'unanswered_perc' => '20', 'undisplayed' => '0',
            'undisplayed_perc' => '0', 'unrated' => '0', 'unrated_perc' => '0',
            'test' => [
                'test_id' => 7, 'test_name' => 'Final test', 'user_test_start_time' => '2026-08-10 10:00:00',
                'user_test_end_time' => '2026-08-10 11:00:00', 'test_duration_time' => '90',
                'test_score_threshold' => '6', 'test_max_score' => '10', 'test_description' => '', 'user_comment' => '',
            ],
        ];

        $this->report->printTestUserInfo($data, true);

        self::assertSame(['Doe Jane (jane), 8 ( 80%)'], $this->report->bookmarks);
        $html = $this->report->htmlBlocks[0] ?? '';
        self::assertStringContainsString('01:00:00', $html);
        self::assertStringContainsString('8 / 10', $html);
        self::assertStringContainsString('Passed', $html);
        self::assertSame($data, $this->report->detailsData);
        self::assertTrue($this->report->detailsOnlyText);
    }
}
