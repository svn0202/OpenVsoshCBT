<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlResultsExportTest extends TestCase
{
    public function testResultsExportStructureAndArgumentsRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["arguments"] = []; '
                    . 'function f_get_all_users_test_stat(...$arguments) { '
                    . '$GLOBALS["arguments"] = $arguments; return ["data"]; } '
                    . 'function get_data_xml($data) { return "<data />\\n"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_results\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . 'eval("namespace Harness; " . substr($source, $match[0][1])); '
                    . '$xml = F_xml_export_results("5", "6", "7", "8", "9", "4"); '
                    . 'echo json_encode($GLOBALS["arguments"]), "\\n---\\n", $xml;',
                dirname(__DIR__) . '/admin/code/tce_xml_results.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertMatchesRegularExpression(
            '~^\["5","6","7","8","9","total_score",false,"4"\]\n---\n'
                . '<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamresults version="[^"]+">\n\t<header lang="ru" '
                . 'date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t\t<test_id>5</test_id>\n\t\t<group_id>6</group_id>\n'
                . '\t\t<user_id>7</user_id>\n\t\t<startdate>8</startdate>\n'
                . '\t\t<enddate>9</enddate>\n\t</header>\n\t<body>\n'
                . '<data />\n\t</body>\n</tcexamresults>\n$~',
            $output,
        );
    }
}
