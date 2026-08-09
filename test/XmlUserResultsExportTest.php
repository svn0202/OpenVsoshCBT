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
}
