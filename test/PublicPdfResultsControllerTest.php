<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicPdfResultsControllerTest extends TestCase
{
    public function testSessionUserIsReadOnlyAfterAuthorization(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/public/code/tce_pdf_results.php');

        self::assertIsString($source);
        $authorization = strpos($source, "require_once '../../shared/code/tce_authorization.php';");
        $userId = strpos($source, "\$_SESSION['session_user_id']");

        self::assertIsInt($authorization);
        self::assertIsInt($userId);
        self::assertLessThan($userId, $authorization);
    }

    public function testSummaryReportKeepsPublicFiltersAndRenderingContract(): void
    {
        $calls = self::runController([
            'mode' => '1', 'test_id' => '7', 'group_id' => '3',
            'display_mode' => '2', 'show_graph' => '1', 'order_field' => 'user_name', 'orderdir' => '1',
        ]);

        self::assertSame(
            '/base/public/code/tce_test_allresults.php?sel=1&amp;test_id=7&amp;group_id=3'
                . '&amp;display_mode=2&amp;show_graph=1&amp;order_field=user_name&amp;orderdir=1',
            $calls[0][1][0] ?? null,
        );
        self::assertSame(
            [
                'setTCExamBackLink', 'setCreator', 'setAuthor', 'setTitle', 'setSubject', 'setKeywords',
                'setLanguageArray', 'setReportHeader', 'addReportPage', 'writeReportHTML',
                'printTestResultStat', 'printSVGStatsGraph', 'setBookmark', 'printQuestionStats', 'outputReport',
            ],
            array_column($calls, 0),
        );
        self::assertSame('All results', $calls[3][1][0] ?? null);
        self::assertSame('stats-svg', $calls[11][1][0] ?? null);
        self::assertSame(['question-stats'], $calls[13][1][0] ?? null);
        self::assertSame('tcexam_report_1_2_7_3_9_0.pdf', $calls[14][1][0] ?? null);
    }

    public function testDetailedReportOnlyPrintsAttemptsReleasedToUsers(): void
    {
        $calls = self::runController(['mode' => '4', 'test_id' => '7']);

        self::assertSame(
            [
                'setTCExamBackLink', 'setCreator', 'setAuthor', 'setTitle', 'setSubject', 'setKeywords',
                'setLanguageArray', 'setReportHeader', 'addReportPage', 'writeReportHTML',
                'printTestResultStat', 'addReportPage', 'printTestUserInfo', 'outputReport',
            ],
            array_column($calls, 0),
        );
        self::assertSame(23, $calls[12][1][0]['attempt'] ?? null);
        self::assertFalse($calls[12][1][1] ?? true);
        self::assertSame('tcexam_report_4_1_7_0_9_0.pdf', $calls[13][1][0] ?? null);
    }

    public function testDetailedReportSkipsMissingRequestedAttempt(): void
    {
        $calls = self::runController(['mode' => '3', 'test_id' => '7', 'testuser_id' => '99']);

        self::assertNotContains('printTestUserInfo', array_column($calls, 0));
        $lastCall = end($calls);
        self::assertIsArray($lastCall);
        self::assertSame('outputReport', $lastCall[0]);
        self::assertSame('tcexam_report_3_1_7_0_9_99.pdf', $lastCall[1][0] ?? null);
    }

    /**
     * @param array<string, string> $request
     * @return list<array{string, list<mixed>}>
     */
    private static function runController(array $request): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_RANDOM_SECURITY', 'configured-secret');
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_PATH_URL', '/base/');
define('K_TCEXAM_VERSION', '1.2.3');
define('PDF_AUTHOR', 'Author');
define('PDF_HEADER_TITLE', 'Header');
define('PDF_HEADER_STRING', 'Description');
define('PDF_HEADER_LOGO', 'logo.svg');
define('PDF_HEADER_LOGO_WIDTH', 12);
$l = [
    'm_authorization_denied' => 'Denied', 't_result_all_users' => 'All results',
    'hp_result_alluser' => 'All description', 't_result_user' => 'User result',
    'hp_result_user' => 'User description', 'w_statistics' => 'Statistics',
];
$_REQUEST = json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$_SESSION = ['session_user_id' => 9];
$GLOBALS['calls'] = [];
class TcePdfReport {
    public function __call($name, $arguments) { $GLOBALS['calls'][] = [$name, $arguments]; }
}
function f_is_random_security_configured() { return true; }
function check_password($plain, $hash) { return true; }
function f_is_authorized_user(...$arguments) { return true; }
function F_print_error(...$arguments) { $GLOBALS['calls'][] = ['F_print_error', $arguments]; }
function unhtmlentities($value) { return $value; }
function f_compact_string($value) { return $value; }
function f_get_boolean($value) { return (bool) $value; }
function f_get_all_users_test_stat(...$arguments) {
    return [
        'num_records' => 2, 'svgpoints' => 'stats-svg', 'qstats' => ['question-stats'],
        'testuser' => [
            "'23'" => ['attempt' => 23, 'test' => ['test_report_to_users' => 1]],
            "'24'" => ['attempt' => 24, 'test' => ['test_report_to_users' => 0]],
        ],
    ];
}
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
echo json_encode($GLOBALS['calls'], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/public/code/tce_pdf_results.php',
                base64_encode(json_encode($request, JSON_THROW_ON_ERROR)),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /** @var list<array{string, list<mixed>}> */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
