<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlQuestionStatsExportTest extends TestCase
{
    public function testQuestionStatsExportKeepsPopulatedQuestionValues(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . 'function f_get_test_data($id) { return ["test_score_right" => 2]; } '
                    . 'function F_count_rows($tables, $where) { '
                    . 'if (!str_contains($where, "testlog_question_id=7")) { return 4; } '
                    . 'if (str_contains($where, "testlog_score>")) { return 2; } '
                    . 'if (str_contains($where, "testlog_score<=")) { return 1; } '
                    . 'if (str_contains($where, "testlog_change_time IS NULL")) { return 1; } '
                    . 'if (str_contains($where, "testlog_display_time IS NULL")) { return 0; } '
                    . 'if (str_contains($where, "testlog_score IS NULL")) { return 0; } '
                    . 'return 4; } '
                    . 'function F_getQuestionTestStat($testId, $questionId) { return ['
                    . '"num" => 4, "right" => 2, "wrong" => 1, "unanswered" => 1, '
                    . '"undisplayed" => 0, "unrated" => 0]; } '
                    . 'function F_db_query($query, $db) { '
                    . '$result = fopen("php://memory", "r"); '
                    . 'if (str_contains($query, "GROUP BY question_id")) { '
                    . '$rows = [["question_id" => 7, "recurrence" => 4, "average_score" => 1.5, '
                    . '"average_time" => 65, "question_difficulty" => 2]]; '
                    . '} elseif (str_contains($query, "SELECT question_description")) { '
                    . '$rows = [["question_description" => "Two & two?"]]; '
                    . '} else { $rows = []; } '
                    . '$GLOBALS["rows"][(int) $result] = $rows; return $result; } '
                    . 'function F_db_fetch_array($result) { '
                    . '$key = (int) $result; return array_shift($GLOBALS["rows"][$key]); } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_question_stats\\\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\\\s*require_once [^;]+;\\\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); echo F_xml_export_question_stats(5);',
                dirname(__DIR__) . '/admin/code/tce_xml_question_stats.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringContainsString("\t\t\t<id>7</id>\n", $output);
        self::assertStringContainsString("\t\t\t<description>Two &amp; two?</description>\n", $output);
        self::assertStringContainsString("\t\t\t<recurrence>4</recurrence>\n", $output);
        self::assertStringContainsString("\t\t\t<points>1.500</points>\n", $output);
        self::assertStringContainsString("\t\t\t<time>01:05</time>\n", $output);
        self::assertStringContainsString("\t\t\t<correct>2</correct>\n", $output);
        self::assertStringContainsString("\t\t\t<wrong>1</wrong>\n", $output);
        self::assertStringContainsString("\t\t\t<unanswered>1</unanswered>\n", $output);
        self::assertStringContainsString("\t\t\t<undisplayed>0</undisplayed>\n", $output);
        self::assertStringContainsString("\t\t\t<unrated>0</unrated>\n", $output);
    }

    public function testEmptyQuestionStatsExportStructureAndQueryRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; $GLOBALS["calls"] = []; '
                    . 'function f_get_test_data($id) { $GLOBALS["calls"]["test"] = $id; return []; } '
                    . 'function F_count_rows($tables, $where) { '
                    . '$GLOBALS["calls"]["count"] = [$tables, $where]; return 0; } '
                    . 'function F_db_query($query, $db) { '
                    . '$GLOBALS["calls"]["query"] = preg_replace("/\\s+/", " ", trim($query)); '
                    . 'return fopen("php://memory", "r"); } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_question_stats\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$xml = F_xml_export_question_stats(5); '
                    . 'echo json_encode($GLOBALS["calls"]), "\\n---\\n", $xml;',
                dirname(__DIR__) . '/admin/code/tce_xml_question_stats.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringStartsWith('{"test":5,"count":["tce_tests_logs, tce_tests_users",', $output);
        self::assertStringContainsString('"query":"SELECT question_id, COUNT(question_id) AS recurrence,', $output);
        self::assertStringContainsString('AND testuser_test_id=5 GROUP BY question_id ', $output);
        self::assertMatchesRegularExpression(
            '~\n---\n<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamquestionstats version="[^"]+">\n\t<header lang="ru" '
                . 'date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t</header>\n\t<body>\n\t</body>\n</tcexamquestionstats>\n$~',
            $output,
        );
    }
}
