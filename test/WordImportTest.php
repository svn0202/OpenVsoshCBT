<?php

//============================================================+
// File name   : WordImportTest.php
// Description : Unit and fixture tests for the independent DOCX importer.
// License     : AGPL-3.0-or-later (see LICENSE).
//============================================================+

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TmfWordImporter;
use TmfWordImportException;
use ZipArchive;

require_once __DIR__ . '/../admin/code/tmf_word_import_lib.php';
require_once __DIR__ . '/../shared/code/tce_functions_tmf_question.php';

final class WordImportTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/openvsosh-word-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->temporaryDirectory, 0700, true));
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->temporaryDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $path) {
            $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        rmdir($this->temporaryDirectory);
    }

    public static function fixtureProvider(): array
    {
        return [
            'recommended template' => [
                'tmf2023_word_template.docx',
                11,
                1,
                1,
                1,
                [1 => 3, 2 => 3, 3 => 3, 4 => 2],
            ],
            'tables and formulas' => [
                'Template_Format_Soal (1).docx',
                5,
                0,
                3,
                7,
                [1 => 1, 2 => 2, 3 => 2],
            ],
            'full test' => [
                'File Format - MS Word For TCExam Simulasi A02-1.docx',
                50,
                9,
                0,
                0,
                [1 => 40, 2 => 3, 3 => 7],
            ],
            'mathematics test' => [
                'Matematika AM (form aplikasi).docx',
                40,
                9,
                2,
                19,
                [1 => 32, 2 => 4, 4 => 4],
            ],
        ];
    }

    #[DataProvider('fixtureProvider')]
    public function testRealWordExamples(
        string $filename,
        int $expectedQuestions,
        int $expectedImages,
        int $expectedTables,
        int $expectedMath,
        array $expectedTypes,
    ): void {
        $fixtureDirectory = getenv('TMF_WORD_FIXTURES_DIR');
        if ($fixtureDirectory === false || $fixtureDirectory === '') {
            self::markTestSkipped('TMF_WORD_FIXTURES_DIR is not configured');
        }

        $path = rtrim($fixtureDirectory, '/') . '/' . $filename;
        if (!is_file($path)) {
            self::markTestSkipped('Optional Word fixture is missing: ' . $filename);
        }

        $slug = hash('sha256', $filename);
        $parser = new TmfWordImporter($path, $this->temporaryDirectory . '/' . $slug, '/cache/word-test/' . $slug);
        $data = $parser->parse();
        $combinedHtml = '';
        $actualTypes = [];
        foreach ($data['questions'] as $question) {
            $combinedHtml .= $question['description'];
            $actualTypes[$question['type']] = ($actualTypes[$question['type']] ?? 0) + 1;
            foreach ($question['answers'] as $answer) {
                $combinedHtml .= $answer['description'];
            }
        }
        ksort($actualTypes);

        self::assertCount($expectedQuestions, $data['questions']);
        self::assertSame($expectedImages, $data['statistics']['images']);
        self::assertSame($expectedTables, substr_count($combinedHtml, '<table'));
        self::assertSame($expectedMath, substr_count($combinedHtml, '<math'));
        self::assertSame($expectedTypes, $actualTypes);
    }

    public function testUnsafeArchivePathIsRejected(): void
    {
        $filename = $this->temporaryDirectory . '/unsafe.docx';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($filename, ZipArchive::CREATE));
        $zip->addFromString(
            'word/document.xml',
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body /></w:document>',
        );
        $zip->addFromString('../escape.txt', 'must not escape');
        $zip->close();

        $this->expectException(TmfWordImportException::class);
        new TmfWordImporter($filename)->parse();
    }

    public function testQuestionMetadataAndScoringHelpers(): void
    {
        $options = \F_tmf_question_options(
            '<!--TMF_CHECKBOX--><!--TMF_MAX_SEL:2-->'
            . '<!--TMF_MCMA_HEADER:[&quot;Факт&quot;,&quot;Да&quot;,&quot;Нет&quot;,&quot;Пропуск&quot;]-->',
        );

        self::assertTrue($options['checkbox']);
        self::assertSame(2, $options['max_selections']);
        self::assertSame(['Факт', 'Да', 'Нет', 'Пропуск'], $options['headers']);
        self::assertTrue(\F_tmf_selection_limit_is_valid([1, 0, 1], 2));
        self::assertFalse(\F_tmf_selection_limit_is_valid([1, 1, 1], 2));
        self::assertSame(4.0, \F_tmf_answer_score(50, false, 8.0, -2.0));
        self::assertSame(0.0, \F_tmf_answer_score(0, true, 8.0, -2.0));
        self::assertSame(8.0, \F_tmf_answer_score(null, true, 8.0, -2.0));
        self::assertSame(-2.0, \F_tmf_answer_score(null, false, 8.0, -2.0));
    }
}
