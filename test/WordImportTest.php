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
            if (in_array($question['type'], [1, 2], true)) {
                self::assertNotEmpty(
                    $question['right_keys'],
                    $filename . ' question ' . $question['source_number'] . ' has no RIGHT key',
                );
                $answerKeys = array_column($question['answers'], 'key');
                foreach ($question['right_keys'] as $rightKey) {
                    self::assertContains(
                        $rightKey,
                        $answerKeys,
                        $filename . ' question ' . $question['source_number'] . ' has an unknown RIGHT key',
                    );
                }
            }
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
        (new TmfWordImporter($filename))->parse();
    }

    public function testMatchingMarkerCreatesIndependentMatchingQuestion(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is not installed.');
        }
        $filename = $this->temporaryDirectory . '/matching.docx';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($filename, ZipArchive::CREATE));
        $paragraphs = [
            'MODULE:=Matching audit',
            'TOPIC:=Matching topic',
            'Q:1) [[MATCHING]] Установите соответствие',
            'A:) Первый элемент',
            'B:) Второй элемент',
            'C:) Третий элемент',
        ];
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= '<w:p><w:r><w:t>'
                . htmlspecialchars($paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '</w:t></w:r></w:p>';
        }
        self::assertTrue($zip->addFromString(
            'word/document.xml',
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body . '</w:body></w:document>',
        ));
        $zip->close();

        $data = (new TmfWordImporter($filename))->parse();
        self::assertCount(1, $data['questions']);
        self::assertSame(5, $data['questions'][0]['type']);
        self::assertSame(3, count($data['questions'][0]['answers']));
        self::assertSame(
            3,
            \F_tmf_question_options($data['questions'][0]['description'])['matching_positions'],
        );
        self::assertStringNotContainsString('[[MATCHING]]', $data['questions'][0]['description']);
    }

    public function testDownloadableTemplateRoundTripsEveryQuestionType(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is not installed.');
        }
        $filename = $this->temporaryDirectory . '/downloaded-template.docx';
        self::assertNotFalse(file_put_contents($filename, \F_tmf_word_import_template()));

        $data = (new TmfWordImporter($filename))->parse();
        self::assertSame('Тестовый модуль', $data['module']);
        self::assertSame('Тестовая тема', $data['topic']);
        self::assertCount(6, $data['questions']);
        self::assertSame([1, 2, 3, 4, 5, 3], array_column($data['questions'], 'type'));
        self::assertSame(['A', 'C'], $data['questions'][1]['right_keys']);
        self::assertSame(
            3,
            \F_tmf_question_options($data['questions'][4]['description'])['matching_positions'],
        );
    }

    public function testQuestionMetadataAndScoringHelpers(): void
    {
        $options = \F_tmf_question_options(
            '<!--TMF_CHECKBOX--><!--TMF_MAX_SEL:2-->'
            . '<!--TMF_SIMILARITY:85-->'
            . '<!--TMF_MATCH_POSITIONS:5-->'
            . '<!--TMF_AUDIO_PLAYS:2-->'
            . '<!--TMF_MCMA_HEADER:[&quot;Факт&quot;,&quot;Да&quot;,&quot;Нет&quot;,&quot;Пропуск&quot;]-->',
        );

        self::assertTrue($options['checkbox']);
        self::assertSame(2, $options['max_selections']);
        self::assertSame(85, $options['similarity_threshold']);
        self::assertSame(5, $options['matching_positions']);
        self::assertSame(2, $options['audio_play_limit']);
        self::assertSame(['Факт', 'Да', 'Нет', 'Пропуск'], $options['headers']);
        self::assertTrue(\F_tmf_selection_limit_is_valid([1, 0, 1], 2));
        self::assertFalse(\F_tmf_selection_limit_is_valid([1, 1, 1], 2));
        self::assertSame(4.0, \F_tmf_answer_score(50, false, 8.0, -2.0));
        self::assertSame(0.0, \F_tmf_answer_score(0, true, 8.0, -2.0));
        self::assertSame(8.0, \F_tmf_answer_score(null, true, 8.0, -2.0));
        self::assertSame(-2.0, \F_tmf_answer_score(null, false, 8.0, -2.0));
        self::assertSame(
            7,
            \F_tmf_question_options(\F_tmf_set_matching_positions('Question', 7))['matching_positions'],
        );
        self::assertSame(
            3,
            \F_tmf_question_options(\F_tmf_set_audio_play_limit('Question', 3))['audio_play_limit'],
        );
    }

    public function testStalePreviewCleanupRemovesOnlyAbandonedMedia(): void
    {
        $staleBatch = str_repeat('a', 32);
        $freshBatch = str_repeat('b', 32);
        $confirmedBatch = str_repeat('c', 32);
        $previewDirectory = $this->temporaryDirectory . '/wordimport-preview';
        self::assertTrue(mkdir($previewDirectory, 0o700, true));

        foreach ([$staleBatch, $freshBatch, $confirmedBatch] as $batch) {
            $mediaDirectory = $this->temporaryDirectory . '/wordimport/' . $batch;
            self::assertTrue(mkdir($mediaDirectory, 0o700, true));
            self::assertNotFalse(file_put_contents($mediaDirectory . '/image.png', 'image'));
        }
        self::assertNotFalse(file_put_contents($previewDirectory . '/' . $staleBatch . '.php', 'preview'));
        self::assertNotFalse(file_put_contents($previewDirectory . '/' . $freshBatch . '.php', 'preview'));
        self::assertTrue(touch($previewDirectory . '/' . $staleBatch . '.php', 100));
        self::assertTrue(touch($previewDirectory . '/' . $freshBatch . '.php', 190));

        self::assertSame(1, \F_tmf_word_import_cleanup_stale($this->temporaryDirectory, 50, 200));
        self::assertFileDoesNotExist($previewDirectory . '/' . $staleBatch . '.php');
        self::assertDirectoryDoesNotExist($this->temporaryDirectory . '/wordimport/' . $staleBatch);
        self::assertFileExists($previewDirectory . '/' . $freshBatch . '.php');
        self::assertDirectoryExists($this->temporaryDirectory . '/wordimport/' . $freshBatch);
        self::assertDirectoryExists($this->temporaryDirectory . '/wordimport/' . $confirmedBatch);
    }

    public function testConfirmedBatchCleanupKeepsImportedMedia(): void
    {
        $batch = str_repeat('d', 32);
        $previewDirectory = $this->temporaryDirectory . '/wordimport-preview';
        $mediaDirectory = $this->temporaryDirectory . '/wordimport/' . $batch;
        self::assertTrue(mkdir($previewDirectory, 0o700, true));
        self::assertTrue(mkdir($mediaDirectory, 0o700, true));
        self::assertNotFalse(file_put_contents($previewDirectory . '/' . $batch . '.php', 'preview'));
        self::assertNotFalse(file_put_contents($mediaDirectory . '/image.png', 'image'));

        self::assertTrue(\F_tmf_word_import_cleanup_batch($this->temporaryDirectory, $batch, false));
        self::assertFileDoesNotExist($previewDirectory . '/' . $batch . '.php');
        self::assertFileExists($mediaDirectory . '/image.png');
        self::assertFalse(
            \F_tmf_word_import_cleanup_batch($this->temporaryDirectory, '../../unsafe', true),
        );
    }
}
