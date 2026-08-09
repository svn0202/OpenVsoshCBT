<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlUsersExportTest extends TestCase
{
    public function testEmptyUserExportStructureAndQueryRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["query"] = ""; '
                    . 'function F_db_query($query, $db) { $GLOBALS["query"] = $query; return "result"; } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_users\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . 'eval("namespace Harness; " . substr($source, $match[0][1])); '
                    . 'require_once "../config/tce_config.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; '
                    . '$xml = F_xml_export_users(); echo $GLOBALS["query"], "\\n---\\n", $xml;',
                dirname(__DIR__) . '/admin/code/tce_xml_users.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertMatchesRegularExpression(
            '~^SELECT \* FROM tce_users WHERE \(user_id>1\) '
                . 'ORDER BY user_lastname,user_firstname,user_name\n---\n'
                . '<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamusers version="[^"]+">\n\t<header lang="ru" '
                . 'date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t</header>\n\t<body>\n\t</body>\n</tcexamusers>\n$~',
            $output,
        );
    }
}
