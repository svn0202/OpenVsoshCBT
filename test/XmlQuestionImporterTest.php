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
}
