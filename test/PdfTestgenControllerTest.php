<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdfTestgenControllerTest extends TestCase
{
    #[DataProvider('questionOrderModes')]
    public function testSingleChoicePaperTestKeepsQueriesQrDataAndOmrGeometry(string $randomQuestions): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_TEST_SUBJSET', 'test_subjset');
define('K_TABLE_SUBJECT_SET', 'subject_set');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_ANSWERS', 'answers');
define('K_DATABASE_TYPE', 'MYSQL');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_PATH_URL', '/base/');
define('K_TCEXAM_VERSION', '1.2.3');
define('PDF_AUTHOR', 'Author');
define('PDF_HEADER_TITLE', 'Header');
define('PDF_HEADER_STRING', 'Description');
define('PDF_HEADER_LOGO', 'logo.svg');
define('PDF_HEADER_LOGO_WIDTH', 12);
define('PDF_MARGIN_LEFT', 15.0);
define('PDF_MARGIN_RIGHT', 15.0);
define('PDF_MARGIN_TOP', 20.0);
define('PDF_MARGIN_FOOTER', 10.0);
define('PDF_FONT_NAME_DATA', 'helvetica');
define('PDF_FONT_SIZE_DATA', 8);
define('PDF_TEXTANSWER_HEIGHT', 50);
$db = 'db';
$l = [
    'w_test' => 'Paper test', 'h_test' => '<p>Instructions</p>', 'a_meta_dir' => 'ltr',
    'w_lastname' => 'Last name', 'w_firstname' => 'First name', 'w_code' => 'Code', 'w_score' => 'Score',
    'w_test_score_threshold' => 'Threshold', 'w_test_time' => 'Time', 'w_minutes' => 'minutes',
    'w_time_begin' => 'Begin', 'w_time_end' => 'End', 'w_score_right' => 'Right',
    'w_score_wrong' => 'Wrong', 'w_score_unanswered' => 'Blank', 'w_max_score' => 'Maximum',
    'w_true_acronym' => 'T', 'w_false_acronym' => 'F',
];
$_REQUEST = ['test_id' => '7', 'num' => '1'];
$GLOBALS['queries'] = [];
$GLOBALS['result_rows'] = [];
$GLOBALS['pdf_object'] = null;
final class PdfPart {
    public array $calls = [];
    public function insert(...$args) { $this->calls[] = ['insert', $args]; return ['out' => '<FONT>']; }
    public function getPdfColor($color) { return '<COLOR:' . $color . '>'; }
    public function getPageId() { return 1; }
    public function getPage($id) { return ['width' => 210.0, 'height' => 297.0]; }
    public function addContent($content) { $this->calls[] = ['addContent', $content]; }
    public function getRect(...$args) { $this->calls[] = ['rect', $args]; return '<RECT>'; }
    public function getCircle(...$args) { $this->calls[] = ['circle', $args]; return '<CIRCLE>'; }
    public function getBarcodeObj($type, $data) {
        $this->calls[] = ['barcodeObject', [$type, $data]];
        return new class($type) {
            public function __construct(private string $type) {}
            public function getArray() { return $this->type === 'QRCODE,L' ? ['ncols' => 21] : ['full_width' => 30]; }
        };
    }
}
final class TcePdfReport {
    public PdfPart $font;
    public PdfPart $color;
    public PdfPart $page;
    public PdfPart $graph;
    public PdfPart $barcode;
    public mixed $pon = null;
    public array $calls = [];
    public array $html = [];
    public function __construct() {
        $this->font = new PdfPart(); $this->color = new PdfPart(); $this->page = new PdfPart();
        $this->graph = new PdfPart(); $this->barcode = new PdfPart(); $GLOBALS['pdf_object'] = $this;
    }
    public function __call($name, $args) { $this->calls[] = [$name, $args]; }
    public function getTextCell(...$args) { return '<TEXT:' . ($args['txt'] ?? $args[0] ?? '') . '>'; }
    public function getBarcode(...$args) { $this->calls[] = ['getBarcode', $args]; return '<BARCODE>'; }
    public function writeReportHTML($html) { $this->html[] = $html; $this->calls[] = ['writeReportHTML', [$html]]; }
    public function outputReport($name) { $this->calls[] = ['outputReport', [$name]]; }
}
function f_is_authorized_user(...$arguments) { return true; }
function unhtmlentities($value) { return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function f_compact_string($value) { return trim(strip_tags((string) $value)); }
function f_get_boolean($value) { return $value === true || $value === 1 || $value === '1'; }
function f_legacy_literal_equals($value, $expected) { return (string) $value === $expected; }
function F_decode_tcecode($value) { return '[[decoded:' . $value . ']]'; }
function f_get_test_data($id) {
    return [
        'test_random_questions_select' => $GLOBALS['argv'][2], 'test_random_questions_order' => '0',
        'test_questions_order_mode' => '0', 'test_random_answers_select' => '0',
        'test_random_answers_order' => '0', 'test_answers_order_mode' => '0',
        'test_score_threshold' => '6', 'test_duration_time' => '45', 'test_score_right' => '2',
        'test_score_wrong' => '-1', 'test_score_unanswered' => '0', 'test_max_score' => '10',
        'test_name' => 'Algebra <A>', 'test_description' => 'Solve carefully',
    ];
}
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    $result = fopen('php://memory', 'r');
    $rows = match (true) {
        str_contains($sql, 'FROM test_subjset') => [[
            'tsubset_id' => '11', 'tsubset_type' => '1', 'tsubset_difficulty' => '2',
            'tsubset_answers' => '2', 'tsubset_quantity' => '1',
        ]],
        str_contains($sql, 'FROM subject_set') => [['subjset_subject_id' => '20']],
        str_starts_with($sql, 'SELECT DISTINCT answer_question_id') => [['answer_question_id' => '30']],
        str_starts_with($sql, 'SELECT answer_question_id') => [['answer_question_id' => '30']],
        str_contains($sql, 'SELECT question_id, question_type') => [[
            'question_id' => '30', 'question_type' => '1', 'question_difficulty' => '2',
            'question_position' => '1', 'question_description' => 'What is 2+2?',
        ]],
        str_contains($sql, 'WHERE answer_id=41') => [[
            'answer_id' => '41', 'answer_position' => '1', 'answer_isright' => '1', 'answer_description' => 'Four',
        ]],
        str_contains($sql, 'WHERE answer_id=42') => [[
            'answer_id' => '42', 'answer_position' => '2', 'answer_isright' => '0', 'answer_description' => 'Five',
        ]],
        default => [],
    };
    $GLOBALS['result_rows'][get_resource_id($result)] = $rows;
    return $result;
}
function F_db_fetch_array($result) {
    $id = get_resource_id($result);
    return array_shift($GLOBALS['result_rows'][$id]);
}
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function f_select_answers($questionId, $isRight) { return $isRight === 1 ? [1 => 41] : [2 => 42]; }
function f_encode_omr_test_data($data) { return json_encode($data, JSON_THROW_ON_ERROR); }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$noise = ob_get_clean();
$pdf = $GLOBALS['pdf_object'];
echo json_encode([
    'noise' => $noise, 'queries' => $GLOBALS['queries'], 'calls' => $pdf->calls, 'html' => $pdf->html,
    'pageCalls' => $pdf->page->calls, 'graphCalls' => $pdf->graph->calls, 'barcodeCalls' => $pdf->barcode->calls,
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/admin/code/tce_pdf_testgen.php',
                $randomQuestions,
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertJson($output, $output);
        /**
         * @var array{
         *     noise:string,queries:list<string>,calls:list<array{string,list<mixed>}>,html:array{0:string},
         *     pageCalls:list<array{string,list<mixed>}>,graphCalls:list<array{string,list<mixed>}>,
         *     barcodeCalls:list<array{string,list<mixed>}>
         * } $result
         */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('', $result['noise']);
        self::assertCount(7, $result['queries']);
        self::assertStringContainsString('FROM test_subjset WHERE tsubset_test_id=7', $result['queries'][0] ?? '');
        self::assertStringContainsString('question_subject_id IN (0,20)', $result['queries'][4] ?? '');
        self::assertStringContainsString('LIMIT 1', $result['queries'][4] ?? '');
        self::assertStringContainsString('WHERE answer_id=41 LIMIT 1', $result['queries'][5] ?? '');
        self::assertStringContainsString('WHERE answer_id=42 LIMIT 1', $result['queries'][6] ?? '');
        self::assertStringContainsString('Algebra &lt;A&gt;', $result['html'][0]);
        self::assertStringContainsString('[[decoded:What is 2+2?]]', $result['html'][0]);
        self::assertStringContainsString('[[decoded:Four]]', $result['html'][0]);
        self::assertStringContainsString('[[decoded:Five]]', $result['html'][0]);
        $qrData = self::stringValue($result['barcodeCalls'][0][1][1] ?? null);
        self::assertSame(
            [7, ['30', ['1' => 41, '2' => 42]]],
            json_decode($qrData, true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertSame(109, count(array_filter($result['graphCalls'], static fn(array $call): bool => $call[0] === 'rect')));
        self::assertSame(2, count(array_filter($result['graphCalls'], static fn(array $call): bool => $call[0] === 'circle')));
        $pageCalls = array_values(array_filter(
            $result['calls'],
            static fn(array $call): bool => $call[0] === 'addPage',
        ));
        self::assertCount(2, $pageCalls);
        self::assertSame(['format' => 'A4', 'orientation' => 'P'], $pageCalls[0][1][0] ?? null);
        $outputCalls = array_values(array_filter(
            $result['calls'],
            static fn(array $call): bool => $call[0] === 'outputReport',
        ));
        self::assertCount(1, $outputCalls);
        $filename = self::stringValue($outputCalls[0][1][0] ?? null);
        self::assertMatchesRegularExpression(
            '/^tcexam_test_7_\d{14}\.pdf$/',
            $filename,
        );
    }

    /** @return iterable<string,array{string}> */
    public static function questionOrderModes(): iterable
    {
        yield 'random selection' => ['1'];
        yield 'fixed order' => ['0'];
    }

    private static function stringValue(mixed $value): string
    {
        self::assertIsString($value);
        return $value;
    }
}
