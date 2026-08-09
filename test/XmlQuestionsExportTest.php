<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlQuestionsExportTest extends TestCase
{
    public function testEmptyQuestionsExportStructureAndSelectionRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["where"] = ""; $GLOBALS["query"] = ""; '
                    . 'function F_select_modules_sql($where) { $GLOBALS["where"] = $where; return "modules-query"; } '
                    . 'function F_db_query($query, $db) { $GLOBALS["query"] = $query; return "result"; } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_questions\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$xml = F_xml_export_questions("5x", "6x", "1x"); '
                    . 'echo $GLOBALS["where"], "\\n", $GLOBALS["query"], "\\n---\\n", $xml;',
                dirname(__DIR__) . '/admin/code/tce_xml_questions.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertMatchesRegularExpression(
            '~^module_id=5\nmodules-query\n---\n'
                . '<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamquestions version="[^"]+">\n\t<header lang="ru" '
                . 'date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t</header>\n\t<body>\n\t</body>\n</tcexamquestions>\n$~',
            $output,
        );
    }
}
