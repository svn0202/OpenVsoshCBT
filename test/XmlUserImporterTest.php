<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlUserImporterTest extends TestCase
{
    public function testHeaderOnlyTsvImportSucceeds(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_import_tsv_users/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . 'eval(substr($source, $match[0][1])); '
                    . '$file = tempnam(sys_get_temp_dir(), "openvsosh-users-tsv-"); '
                    . 'file_put_contents($file, "header\\n"); '
                    . '$result = F_import_tsv_users($file); unlink($file); '
                    . 'echo json_encode(["result" => $result]);',
                dirname(__DIR__) . '/admin/code/XMLUserImporter.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(['result' => true], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDestructionIgnoresAnAlreadyRemovedTemporaryFile(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "\nclass XMLUserImporter\n") + 1; '
                    . '$marker = "} // END OF CLASS"; $end = strpos($source, $marker, $start); '
                    . 'eval(substr($source, $start, $end - $start + strlen($marker))); '
                    . 'require_once "../config/tce_config.php"; restore_error_handler(); '
                    . 'error_reporting(E_ALL & ~E_DEPRECATED); '
                    . '$file = tempnam(sys_get_temp_dir(), "openvsosh-users-xml-"); '
                    . 'file_put_contents($file, "<users/>"); '
                    . '$importer = new XMLUserImporter($file); unlink($file); unset($importer); '
                    . 'echo "destroyed";',
                dirname(__DIR__) . '/admin/code/XMLUserImporter.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('destroyed', $output);
    }

    public function testEmptyDocumentParsesAndTemporaryFileIsDeletedOnDestruction(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "\nclass XMLUserImporter\n") + 1; '
                    . '$marker = "} // END OF CLASS"; $end = strpos($source, $marker, $start); '
                    . 'eval(substr($source, $start, $end - $start + strlen($marker))); '
                    . 'require_once "../config/tce_config.php"; restore_error_handler(); '
                    . 'error_reporting(E_ALL & ~E_DEPRECATED); '
                    . '$file = tempnam(sys_get_temp_dir(), "openvsosh-users-xml-"); '
                    . 'file_put_contents($file, "<users/>"); '
                    . '$importer = new XMLUserImporter($file); '
                    . 'echo $file;',
                dirname(__DIR__) . '/admin/code/XMLUserImporter.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringStartsWith('openvsosh-users-xml-', basename($output));
        self::assertFileDoesNotExist($output);
    }
}
