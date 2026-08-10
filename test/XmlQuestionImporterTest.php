<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once __DIR__ . '/../admin/code/tce_class_import_xml.php';

final class XmlQuestionImporterTest extends TestCase
{
    public function testPopulatedImportCreatesCompleteQuestionHierarchy(): void
    {
        $script = <<<'PHP'
namespace Harness;
class_alias(\XMLParser::class, __NAMESPACE__ . '\\XMLParser');
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_SUBJECTS', 'subjects');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_ANSWERS', 'answers');
define('K_DATABASE_TYPE', 'POSTGRESQL');
define('K_UTF8_NORMALIZATION_MODE', 'NFC');
$db = 'db';
$_SESSION = ['session_user_id' => 17];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [false, false, false, false];
function utrim($value) { return trim((string) $value); }
function f_xml_to_text($value) { return $value; }
function f_utf8_normalizer($value, $mode) { return $value; }
function F_escape_sql($db, $value, $strip = true) { return str_replace("'", "''", (string) $value); }
function f_empty_to_null($value) { return $value === '' ? 'NULL' : "'" . $value . "'"; }
function f_zero_to_null($value) { return $value === 0 ? 'NULL' : (string) $value; }
function f_is_authorized_user(...$arguments) { return true; }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    return str_starts_with($sql, 'SELECT') ? fopen('php://memory', 'r') : true;
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows']); }
function F_db_insert_id($db, $table, $field) {
    return ['modules' => 10, 'subjects' => 20, 'questions' => 30, 'answers' => 40][$table];
}
function F_display_db_error(...$arguments) { echo '[[DB_ERROR]]'; }
function F_print_error(...$arguments) { echo '[[ERROR]]'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
$path = tempnam(sys_get_temp_dir(), 'questions-');
file_put_contents($path, <<<'XML'
<root><module><name>Module A</name><enabled>true</enabled>
<subject><name>Subject A</name><description>Subject text</description><enabled>true</enabled>
<question><description>Question?</description><type>single</type><difficulty>2</difficulty><enabled>true</enabled>
<answer><description>Answer.</description><isright>true</isright><enabled>true</enabled></answer>
</question></subject></module></root>
XML);
$importer = new XMLQuestionImporter($path);
unset($importer);
echo json_encode($GLOBALS['queries'], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/XMLQuestionImporter.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var list<string> $queries */
        $queries = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(12, $queries);
        self::assertStringContainsString("WHERE module_name='Module A'", $queries[0] ?? '');
        self::assertStringStartsWith('INSERT INTO modules', $queries[1] ?? '');
        self::assertStringContainsString('subject_module_id=10', $queries[2] ?? '');
        self::assertStringStartsWith('INSERT INTO subjects', $queries[3] ?? '');
        self::assertStringContainsString('question_subject_id=20', $queries[4] ?? '');
        self::assertSame('START TRANSACTION', $queries[5] ?? '');
        self::assertStringStartsWith('INSERT INTO questions', $queries[6] ?? '');
        self::assertSame('COMMIT', $queries[7] ?? '');
        self::assertStringContainsString('answer_question_id=30', $queries[8] ?? '');
        self::assertSame('START TRANSACTION', $queries[9] ?? '');
        self::assertStringStartsWith('INSERT INTO answers', $queries[10] ?? '');
        self::assertSame('COMMIT', $queries[11] ?? '');
        self::assertStringNotContainsString('[[DB_ERROR]]', $output);
    }

    public function testConstructorParsesAndDeletesMinimalXmlFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tce-import-');
        self::assertIsString($path);
        self::assertNotFalse(file_put_contents($path, '<metadata/>'));
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_class_import_xml.php"; '
                    . '$importer = new XMLQuestionImporter($argv[1]); $importer->__destruct(); '
                    . 'echo json_encode(file_exists($argv[1]));',
                $path,
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }

    public function testDestructorDeletesUploadedFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tce-import-');
        self::assertIsString($path);

        $class = new ReflectionClass(\XMLQuestionImporter::class);
        $importer = $class->newInstanceWithoutConstructor();
        $class->getProperty('xmlfile')->setValue($importer, $path);

        unset($importer);

        self::assertFileDoesNotExist($path);
    }

    public function testStartElementHandlerReturnsNothing(): void
    {
        $class = new ReflectionClass(\XMLQuestionImporter::class);
        $importer = $class->newInstanceWithoutConstructor();
        $unusedPath = sys_get_temp_dir() . '/tce-unused-import-' . hrtime(true) . '.xml';
        $class->getProperty('xmlfile')->setValue($importer, $unusedPath);
        $handler = $class->getMethod('startElementHandler');
        $parser = xml_parser_create();

        self::assertNull($handler->invoke($importer, $parser, 'metadata', []));
    }

    public function testSegmentContentHandlerAccumulatesDataAndReturnsNothing(): void
    {
        $class = new ReflectionClass(\XMLQuestionImporter::class);
        $importer = $class->newInstanceWithoutConstructor();
        $unusedPath = sys_get_temp_dir() . '/tce-unused-import-' . hrtime(true) . '.xml';
        $class->getProperty('xmlfile')->setValue($importer, $unusedPath);
        $class->getProperty('current_element')->setValue($importer, 'question_description');
        $handler = $class->getMethod('segContentHandler');
        $parser = xml_parser_create();

        self::assertNull($handler->invoke($importer, $parser, 'first'));
        self::assertNull($handler->invoke($importer, $parser, ' second'));
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
                    . '$parser = xml_parser_create(); '
                    . 'echo json_encode($handler->invoke($importer, $parser, "metadata"));',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('null', $output);
    }

    public function testEndElementHandlerStoresQuestionDescription(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; '
                    . 'require_once "../../shared/code/tce_functions_general.php"; '
                    . 'require_once "tce_class_import_xml.php"; '
                    . '$class = new ReflectionClass(XMLQuestionImporter::class); '
                    . '$importer = $class->newInstanceWithoutConstructor(); '
                    . '$path = tempnam(sys_get_temp_dir(), "tce-import-"); '
                    . '$class->getProperty("xmlfile")->setValue($importer, $path); '
                    . '$class->getProperty("level")->setValue($importer, "question"); '
                    . '$class->getProperty("current_element")->setValue($importer, "question_description"); '
                    . '$class->getProperty("current_data")->setValue($importer, " description "); '
                    . '$parser = xml_parser_create(); '
                    . '$class->getMethod("endElementHandler")->invoke($importer, $parser, "description"); '
                    . '$data = $class->getProperty("level_data")->getValue($importer); '
                    . 'echo json_encode($data["question"]["question_description"]);',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('"description"', $output);
    }
}
