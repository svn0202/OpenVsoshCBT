<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once __DIR__ . '/../admin/code/tce_class_import_xml.php';

final class XmlQuestionImporterTest extends TestCase
{
    public function testStartElementHandlerReturnsNothing(): void
    {
        $class = new ReflectionClass(\XMLQuestionImporter::class);
        $importer = $class->newInstanceWithoutConstructor();
        $unusedPath = sys_get_temp_dir() . '/tce-unused-import-' . hrtime(true) . '.xml';
        $class->getProperty('xmlfile')->setValue($importer, $unusedPath);
        $handler = $class->getMethod('startElementHandler');

        self::assertNull($handler->invoke($importer, null, 'metadata', []));
    }

    public function testSegmentContentHandlerAccumulatesDataAndReturnsNothing(): void
    {
        $class = new ReflectionClass(\XMLQuestionImporter::class);
        $importer = $class->newInstanceWithoutConstructor();
        $unusedPath = sys_get_temp_dir() . '/tce-unused-import-' . hrtime(true) . '.xml';
        $class->getProperty('xmlfile')->setValue($importer, $unusedPath);
        $class->getProperty('current_element')->setValue($importer, 'question_description');
        $handler = $class->getMethod('segContentHandler');

        self::assertNull($handler->invoke($importer, null, 'first'));
        self::assertNull($handler->invoke($importer, null, ' second'));
        self::assertSame('first second', $class->getProperty('current_data')->getValue($importer));
    }

    public function testEndElementHandlerIgnoresUnrelatedElement(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_class_import_xml.php"; '
                    . '$class = new ReflectionClass(XMLQuestionImporter::class); '
                    . '$importer = $class->newInstanceWithoutConstructor(); '
                    . '$path = tempnam(sys_get_temp_dir(), "tce-import-"); '
                    . '$class->getProperty("xmlfile")->setValue($importer, $path); '
                    . '$handler = $class->getMethod("endElementHandler"); '
                    . 'echo json_encode($handler->invoke($importer, null, "metadata"));',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('null', $output);
    }
}
