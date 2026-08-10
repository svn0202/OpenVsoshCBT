<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlUserResultsExportTest extends TestCase
{
    public function testEmptyUserResultsExportStructureAndQueriesRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["queries"] = []; '
                    . 'function F_db_query($query, $db) { '
                    . '$GLOBALS["queries"][] = preg_replace("/\\s+/", " ", trim($query)); return "result"; } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . 'function F_escape_sql($db, $value) { return $value; } '
                    . 'function f_get_array_statistics($data) { return []; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_user_results\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . 'eval("namespace Harness; " . substr($source, $match[0][1])); '
                    . 'require_once "../config/tce_config.php"; '
                    . 'restore_error_handler(); error_reporting(E_ALL & ~E_WARNING); '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; '
                    . '$xml = F_xml_export_user_results(7, "2026-01-01", "2026-01-31", "invalid"); '
                    . 'echo json_encode($GLOBALS["queries"]), "\\n---\\n", $xml;',
                dirname(__DIR__) . '/admin/code/tce_xml_user_results.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertMatchesRegularExpression(
            '~^\["SELECT user_name, user_lastname, user_firstname FROM tce_users WHERE user_id=7",'
                . '"SELECT testuser_id, test_id, test_name, testuser_creation_time, testuser_status, '
                . 'SUM\(testlog_score\) AS total_score, MAX\(testlog_change_time\) AS testuser_end_time '
                . 'FROM tce_tests_logs, tce_tests_users, tce_tests WHERE testuser_status>0 '
                . 'AND testuser_creation_time>=\\x{27}2026-01-01\\x{27} '
                . 'AND testuser_creation_time<=\\x{27}2026-01-31\\x{27} AND testuser_user_id=7 '
                . 'AND testlog_testuser_id=testuser_id AND testuser_test_id=test_id '
                . 'GROUP BY testuser_id, test_id, test_name, testuser_creation_time, testuser_status '
                . 'ORDER BY testuser_creation_time"\]\n---\n'
                . '<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamuserresults version="[^"]+">\n\t<header lang="ru" '
                . 'date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t\t<user_id>7</user_id>\n\t\t<date_from>2026-01-01</date_from>\n'
                . '\t\t<date_to>2026-01-31</date_to>\n\t</header>\n\t<body>\n'
                . '\t\t<teststatistics>\n\t\t\t<passed>0</passed>\n'
                . '\t\t\t<passed_percent>0</passed_percent>\n'
                . '\t\t</teststatistics>\n\t</body>\n</tcexamuserresults>\n$~',
            $output,
        );
    }

    public function testPopulatedUserResultKeepsScoresStatusAndStatistics(): void
    {
        $script = <<<'PHP'
namespace Harness;
$GLOBALS['queries'] = [];
$GLOBALS['row_indexes'] = ['user' => 0, 'tests' => 0];
function F_db_query($query, $db) {
    $query = preg_replace('/\s+/', ' ', trim($query));
    $GLOBALS['queries'][] = $query;
    return str_starts_with($query, 'SELECT user_name') ? 'user' : 'tests';
}
function F_db_fetch_array($result) {
    $rows = [
        'user' => [[
            'user_name' => 'student', 'user_lastname' => 'Student', 'user_firstname' => 'Sam',
        ]],
        'tests' => [[
            'testuser_id' => '21', 'test_id' => '9', 'test_name' => 'Algebra & geometry',
            'testuser_creation_time' => '2026-01-10 10:00:00', 'testuser_end_time' => '2026-01-10 11:00:00',
            'testuser_status' => '4', 'total_score' => '8.25',
        ]],
    ];
    return $rows[$result][$GLOBALS['row_indexes'][$result]++] ?? false;
}
function F_escape_sql($db, $value) { return $value; }
function f_text_to_xml($value) { return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function f_get_user_test_stat($testId, $userId) {
    return [
        'max_score' => 10, 'score_threshold' => 6, 'score' => 8, 'right' => 3, 'wrong' => 1,
        'unanswered' => 1, 'undisplayed' => 0, 'unrated' => 0, 'all' => 5, 'comment' => 'Good & steady',
    ];
}
function f_get_array_statistics($data) {
    return [
        'number' => ['score' => 1, 'right' => 1, 'wrong' => 1, 'unanswered' => 1, 'undisplayed' => 1, 'unrated' => 1],
        'mean' => ['score' => 0.825, 'right' => 0.6, 'wrong' => 0.2, 'unanswered' => 0.2, 'undisplayed' => 0, 'unrated' => 0],
    ];
}
$source = file_get_contents($argv[1]);
preg_match('/function [Ff]_xml_export_user_results\(/', $source, $match, PREG_OFFSET_CAPTURE);
eval('namespace Harness; ' . substr($source, $match[0][1]));
require_once '../config/tce_config.php';
restore_error_handler();
error_reporting(E_ALL & ~E_WARNING);
$l = ['w_locked' => 'Locked', 'w_unlocked' => 'Unlocked'];
$_SESSION = ['session_user_level' => K_AUTH_ADMINISTRATOR, 'session_user_id' => 1];
$xml = F_xml_export_user_results(7, '2026-01-01', '2026-01-31', 'total_score');
echo json_encode(['queries' => $GLOBALS['queries'], 'xml' => $xml], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_xml_user_results.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{queries:array{0:string,1:string},xml:string} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('ORDER BY total_score', $result['queries'][1]);
        self::assertStringContainsString('<user_name>student</user_name>', $result['xml']);
        self::assertStringContainsString("<test id='9'>", $result['xml']);
        self::assertStringContainsString('<time>01:00:00</time>', $result['xml']);
        self::assertStringContainsString('<name>Algebra &amp; geometry</name>', $result['xml']);
        self::assertStringContainsString('<passed>true</passed>', $result['xml']);
        self::assertStringContainsString('<score>8.25</score>', $result['xml']);
        self::assertStringContainsString('<score_percent>80</score_percent>', $result['xml']);
        self::assertStringContainsString('<right_percent>60</right_percent>', $result['xml']);
        self::assertStringContainsString('<status>Locked</status>', $result['xml']);
        self::assertStringContainsString('<comment>Good &amp; steady</comment>', $result['xml']);
        self::assertStringContainsString('<passed>1</passed>', $result['xml']);
        self::assertStringContainsString('<passed_percent>0</passed_percent>', $result['xml']);
        self::assertStringContainsString('<mean>', $result['xml']);
    }
}
