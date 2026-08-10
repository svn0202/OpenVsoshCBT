<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PdfAllQuestionsControllerTest extends TestCase
{
    public function testPdfExportPreservesQueriesMetadataAndRenderedContent(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_RESULTS', 10);
define('K_TABLE_MODULES', 'modules');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_ANSWERS', 'answers');
define('K_PATH_URL', 'https://example.test/');
define('K_TCEXAM_VERSION', '9.9');
define('PDF_AUTHOR', 'OpenVsosh');
define('PDF_HEADER_TITLE', 'Header title');
define('PDF_HEADER_STRING', 'Header string');
define('PDF_HEADER_LOGO', 'logo.svg');
define('PDF_HEADER_LOGO_WIDTH', 12.5);
define('K_ENABLE_QUESTION_EXPLANATION', true);
define('K_ENABLE_ANSWER_EXPLANATION', true);
$db = 'db';
$l = [
    't_questions_list' => 'Questions &amp; answers',
    'hp_select_all_questions' => '<p>Question catalogue</p>',
    'a_meta_dir' => 'ltr',
    'w_explanation' => 'Explanation',
];
$_REQUEST = ['expmode' => '1', 'module_id' => '5', 'subject_id' => '6'];
$GLOBALS['queries'] = [];
$GLOBALS['kinds'] = [];
$GLOBALS['indexes'] = [];
$GLOBALS['pdf_object'] = null;
class TcePdfReport {
    public array $calls = [];
    public array $html = [];
    public string $filename = '';
    public function __construct() { $GLOBALS['pdf_object'] = $this; }
    public function setTCExamBackLink($value) { $this->calls['backlink'] = $value; }
    public function setCreator($value) { $this->calls['creator'] = $value; }
    public function setAuthor($value) { $this->calls['author'] = $value; }
    public function setTitle($value) { $this->calls['title'] = $value; }
    public function setSubject($value) { $this->calls['subject'] = $value; }
    public function setKeywords($value) { $this->calls['keywords'] = $value; }
    public function setLanguageArray($value) { $this->calls['language'] = $value; }
    public function setReportHeader(...$value) { $this->calls['header'] = $value; }
    public function addReportPage() { $this->calls['pages'] = ($this->calls['pages'] ?? 0) + 1; }
    public function writeReportHTML($value) { $this->html[] = $value; }
    public function outputReport($value) { $this->filename = $value; }
}
function f_is_authorized_user($table, $id_field, $id, $user_field) { return true; }
function unhtmlentities($value) { return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function f_compact_string($value) { return trim(strip_tags($value)); }
function F_select_modules_sql($where) { return 'modules:' . $where; }
function F_select_subjects_sql($where) { return 'subjects:' . $where; }
function F_db_query($query, $db) {
    $query = preg_replace('/\s+/', ' ', trim($query));
    $GLOBALS['queries'][] = $query;
    $kind = match (true) {
        str_starts_with($query, 'modules:') => 'modules',
        str_starts_with($query, 'subjects:') => 'subjects',
        str_contains($query, 'FROM ' . K_TABLE_QUESTIONS) => 'questions',
        str_contains($query, 'FROM ' . K_TABLE_ANSWERS) => 'answers',
        default => 'empty',
    };
    $result = fopen('php://memory', 'r');
    $GLOBALS['kinds'][get_resource_id($result)] = $kind;
    $GLOBALS['indexes'][$kind] = 0;
    return $result;
}
function F_db_fetch_array($result) {
    $kind = $GLOBALS['kinds'][get_resource_id($result)];
    $rows = [
        'modules' => [['module_id' => 5, 'module_name' => 'Algebra & Geometry']],
        'subjects' => [[
            'subject_id' => 6, 'subject_name' => 'Angles <90',
            'subject_description' => 'Subject body',
        ]],
        'questions' => [[
            'question_id' => 7, 'question_enabled' => 0, 'question_type' => 1,
            'question_difficulty' => 2, 'question_position' => 3, 'question_timer' => 45,
            'question_fullscreen' => 1, 'question_inline_answers' => 1,
            'question_auto_next' => 1, 'question_description' => 'Question body',
            'question_explanation' => 'Question reason',
        ]],
        'answers' => [[
            'answer_enabled' => 0, 'answer_isright' => 1, 'answer_position' => 4,
            'answer_keyboard_key' => 65, 'answer_description' => 'Answer body',
            'answer_explanation' => 'Answer reason',
        ]],
        'empty' => [],
    ];
    return $rows[$kind][$GLOBALS['indexes'][$kind]++] ?? false;
}
function f_get_boolean($value) { return (bool) $value; }
function F_decode_tcecode($value) { return '[[decoded:' . $value . ']]'; }
function f_text_to_xml($value) { return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function F_display_db_error() { echo '[[DB_ERROR]]'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
$pdf = $GLOBALS['pdf_object'];
echo json_encode([
    'calls' => $pdf->calls,
    'html' => $pdf->html,
    'filename' => $pdf->filename,
    'queries' => $GLOBALS['queries'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_pdf_all_questions.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     calls:array{
         *         backlink:string,
         *         creator:string,
         *         title:string,
         *         subject:string,
         *         header:array{0:string,1:string,2:string,3:float},
         *         pages:int
         *     },
         *     html:array{0:string},
         *     filename:string,
         *     queries:array{0:string,1:string,2:string,3:string}
         * } $result
         */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            'https://example.test/admin/code/tce_show_all_questions.php?subject_module_id=5&subject_id=6',
            $result['calls']['backlink'],
        );
        self::assertSame('TCExam ver.9.9', $result['calls']['creator']);
        self::assertSame('Questions & answers', $result['calls']['title']);
        self::assertSame('Question catalogue', $result['calls']['subject']);
        self::assertSame(['Header title', 'Header string', 'logo.svg', 12.5], $result['calls']['header']);
        self::assertSame(1, $result['calls']['pages']);
        self::assertSame('modules:module_id=5', $result['queries'][0]);
        self::assertSame('subjects:subject_module_id=5 AND subject_id=6', $result['queries'][1]);
        self::assertStringContainsString('WHERE question_subject_id=6', $result['queries'][2]);
        self::assertStringContainsString("WHERE answer_question_id='7'", $result['queries'][3]);
        self::assertStringContainsString('Algebra &amp; Geometry :: Angles &lt;90', $result['html'][0]);
        self::assertStringContainsString('color:#999999;', $result['html'][0]);
        self::assertStringContainsString('<td>S</td><td>2</td><td>3</td><td>FIA</td><td>45</td>', $result['html'][0]);
        self::assertStringContainsString('[[decoded:Question reason]]', $result['html'][0]);
        self::assertStringContainsString('<td style="text-align:center;">*</td>', $result['html'][0]);
        self::assertStringContainsString('<td style="text-align:center;">A</td>', $result['html'][0]);
        self::assertStringContainsString('[[decoded:Answer reason]]', $result['html'][0]);
        self::assertMatchesRegularExpression('/^tcexam_subject_6_\d{12}\.pdf$/', $result['filename']);
        self::assertStringNotContainsString('[[DB_ERROR]]', $output);
    }
}
