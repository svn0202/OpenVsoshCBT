<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use ZipArchive;

require_once __DIR__ . '/../shared/code/tce_functions_users_xlsx.php';

final class XlsxTest extends TestCase
{
    public function testNativeWorkbookRoundTripsTypesAndTreatsFormulaTextAsText(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is not installed.');
        }
        $bytes = \F_tmf_xlsx_build([[
            'name' => 'Проверка / Excel',
            'rows' => [
                ['login', 'score', 'note'],
                ['student-1', ['value' => 12.5, 'type' => 'number'], '=HYPERLINK("https://bad.test")'],
                ['  пробелы  ', 0, 'обычный текст'],
            ],
        ]]);
        $file = tempnam(sys_get_temp_dir(), 'openvsosh-xlsx-test-');
        self::assertNotFalse($file);
        file_put_contents($file, $bytes);
        try {
            $rows = \F_tmf_xlsx_read($file);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
        self::assertSame('student-1', $rows[1][0]);
        self::assertSame('12.5', $rows[1][1]);
        self::assertSame('=HYPERLINK("https://bad.test")', $rows[1][2]);
        self::assertSame('  пробелы  ', $rows[2][0]);
    }

    public function testReaderRejectsWorkbookFormulaCells(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is not installed.');
        }
        $bytes = \F_tmf_xlsx_build([['rows' => [['header'], ['safe']]]]);
        $file = tempnam(sys_get_temp_dir(), 'openvsosh-xlsx-formula-');
        self::assertNotFalse($file);
        file_put_contents($file, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($file) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet = str_replace(
            '<c r="A2" t="inlineStr" s="0"><is><t>safe</t></is></c>',
            '<c r="A2"><f>1+1</f><v>2</v></c>',
            $sheet,
        );
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Formulas are not accepted');
            \F_tmf_xlsx_read($file);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testUserPreviewReportsDuplicatesUnknownGroupsAndInvalidRows(): void
    {
        $rows = [
            \TMF_USERS_XLSX_HEADERS,
            ['new-user', 'long-password', 'valid@example.test', 'Имя', 'Фамилия', '40212',
                '', '', '', '1', 'default'],
            ['new-user', 'short', 'invalid', '', '', '03.02.2010', '', '', '', '99', 'missing'],
            ['existing', 'long-password', '', '', '', '', '', '', '', '1', 'default'],
        ];
        $result = \F_tmf_users_xlsx_validate(
            $rows,
            ['existing' => true],
            ['default' => 1],
            10,
        );
        self::assertArrayHasKey(2, $result['records']);
        self::assertArrayHasKey(3, $result['errors']);
        self::assertArrayHasKey(4, $result['errors']);
        self::assertStringContainsString('повторяется', implode(' ', $result['errors'][3]));
        self::assertStringContainsString('Неизвестная группа', implode(' ', $result['errors'][3]));
        self::assertStringContainsString('уже существует', implode(' ', $result['errors'][4]));
        self::assertStringStartsWith('$', $result['records'][2]['password_hash']);
        self::assertSame('2010-02-03', $result['records'][2]['birth_date']);
    }
}
