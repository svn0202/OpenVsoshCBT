<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlResultsExportTest extends TestCase
{
    public function testJsonEndpointPreservesFiltersAndDocumentStructure(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_ADMIN_RESULTS', 8);
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_NEWLINE', "\n");
define('K_TAB', "\t");
define('K_TCEXAM_VERSION', '1.2.3');
define('K_USER_LANG', 'ru');
function f_is_authorized_user(...$arguments): bool
{
    $GLOBALS['authorization'] = $arguments;
    return true;
}
function f_get_all_users_test_stat(...$arguments): array
{
    $GLOBALS['stat_arguments'] = $arguments;
    return ['result' => 'ok'];
}
function get_data_xml(mixed $data): string { return "<data>" . $data['result'] . "</data>\n"; }
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-xml-results-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_xml_results.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'foreach (["tce_authorization.php", "tce_functions_test_stats.php"] as $file) '
                    . '{ file_put_contents($root . "/shared/code/" . $file, "<?php"); } '
                    . '$_REQUEST = ["test_id" => "17", "group_id" => "4", "user_id" => "9", '
                    . '"startdate" => "2026-08-01 10:20:30", "enddate" => "2026-08-02 11:22:33", '
                    . '"display_mode" => "9", "format" => "json"]; '
                    . 'chdir($root . "/admin/code"); ob_start(); require "tce_xml_results.php"; '
                    . '$body = ob_get_clean(); $result = [$body, $GLOBALS["authorization"], '
                    . '$GLOBALS["stat_arguments"]]; '
                    . 'foreach (["/admin/code/tce_xml_results.php", "/admin/config/tce_config.php", '
                    . '"/shared/code/tce_authorization.php", "/shared/code/tce_functions_test_stats.php"] '
                    . 'as $file) { unlink($root . $file); } rmdir($root . "/admin/code"); '
                    . 'rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_xml_results.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: string, 1: array{string, string, int, string}, 2: array<mixed>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['tests', 'test_id', 17, 'test_user_id'], $decoded[1]);
        self::assertSame(
            [17, 4, 9, '2026-08-01 10:20:30', '2026-08-02 11:22:33', 'total_score', false, 5],
            $decoded[2],
        );
        /**
         * @var array{
         *     '@attributes': array{version: string},
         *     header: array{
         *         '@attributes': array{lang: string, date: string},
         *         test_id: string,
         *         group_id: string,
         *         user_id: string,
         *         startdate: string,
         *         enddate: string
         *     },
         *     body: array{data: string}
         * } $document
         */
        $document = json_decode($decoded[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('1.2.3', $document['@attributes']['version']);
        self::assertSame('ru', $document['header']['@attributes']['lang']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $document['header']['@attributes']['date']);
        self::assertSame(['17', '4', '9', '2026-08-01 10:20:30', '2026-08-02 11:22:33'], [
            $document['header']['test_id'],
            $document['header']['group_id'],
            $document['header']['user_id'],
            $document['header']['startdate'],
            $document['header']['enddate'],
        ]);
        self::assertSame('ok', $document['body']['data']);
    }

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
