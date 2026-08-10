<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlQuestionStatsExportTest extends TestCase
{
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
                    . '$GLOBALS["calls"]["query"] = preg_replace("/\\s+/", " ", trim($query)); return "result"; } '
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
