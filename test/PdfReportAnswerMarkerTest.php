<?php

namespace Test;

use PHPUnit\Framework\TestCase;

if (!defined('PDF_MARGIN_LEFT')) {
    define('PDF_MARGIN_LEFT', 15.0);
}
if (!defined('PDF_MARGIN_TOP')) {
    define('PDF_MARGIN_TOP', 27.0);
}

require_once __DIR__ . '/../shared/code/tce_pdf_report.php';

final class PdfReportAnswerMarkerTest extends TestCase
{
    /**
     * @param array<string,int|string> $answer
     * @return array{0:string,1:bool,2:string,3:bool}
     */
    private function marker(int $type, array $answer, int $index): array
    {
        $report = new class extends \TcePdfReport {
            public function __construct()
            {
            }

            /**
             * @param array<string,int|string> $answer
             * @return array{0:string,1:bool,2:string,3:bool}
             */
            public function marker(int $type, array $answer, int $index): array
            {
                return $this->answerMarker($type, $answer, $index);
            }
        };
        return $report->marker($type, $answer, $index);
    }

    public function testOrderingMarkerAcceptsNumericDatabaseStrings(): void
    {
        self::assertSame(
            ['2', true, '2', true],
            $this->marker(4, [
                'answer_isright' => '1',
                'logansw_position' => '2',
                'answer_position' => 2,
            ], 1),
        );
    }

    public function testChoiceMarkersPreserveSelectedStateRules(): void
    {
        self::assertSame([' ', false, '1', true], $this->marker(1, [
            'answer_isright' => '1',
            'logansw_selected' => '0',
        ], 1));
        self::assertSame(['-', false, '2', true], $this->marker(2, [
            'answer_isright' => '1',
            'logansw_selected' => '0',
        ], 2));
        self::assertSame(['+', true, '3', true], $this->marker(2, [
            'answer_isright' => '1',
            'logansw_selected' => '1',
        ], 3));
    }
}
