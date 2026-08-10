<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlQuestionsExportTest extends TestCase
{
    public function testExportPreservesNestedQuestionAndAnswerData(): void
    {
        $script = <<<'PHP'
namespace Harness;
require_once '../config/tce_config.php';
$GLOBALS['queries'] = [];
$GLOBALS['kinds'] = [];
$GLOBALS['indexes'] = [];
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
        'modules' => [[
            'module_id' => 5, 'module_name' => 'Algebra & Geometry', 'module_enabled' => 1,
        ]],
        'subjects' => [[
            'subject_id' => 6, 'subject_name' => 'Angles <90',
            'subject_description' => 'Use & prove', 'subject_enabled' => 1,
        ]],
        'questions' => [[
            'question_id' => 7, 'question_enabled' => 1, 'question_type' => 1,
            'question_difficulty' => 2, 'question_position' => 3, 'question_timer' => 45,
            'question_fullscreen' => 0, 'question_inline_answers' => 1,
            'question_auto_next' => 0, 'question_shuffle_answers' => 1,
            'question_description' => 'Is 2 < 3?', 'question_explanation' => 'Because 2 &lt; 3',
        ]],
        'answers' => [[
            'answer_enabled' => 1, 'answer_isright' => 1, 'answer_position' => 1,
            'answer_keyboard_key' => 65, 'answer_weight' => '1.50',
            'answer_description' => 'Yes & always', 'answer_explanation' => 'Natural order',
        ]],
        'empty' => [],
    ];
    return $rows[$kind][$GLOBALS['indexes'][$kind]++] ?? false;
}
function f_get_boolean($value) { return (bool) $value; }
function f_text_to_xml($value) { return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function F_display_db_error() { echo '[[DB_ERROR]]'; }
$source = file_get_contents($argv[1]);
preg_match('/function [Ff]_xml_export_questions\(/', $source, $match, PREG_OFFSET_CAPTURE);
$function = substr($source, $match[0][1]);
$function = preg_replace('/^\s*require_once [^;]+;\n/m', '', $function);
eval('namespace Harness; ' . $function);
$xml = F_xml_export_questions(5, 6, 1);
echo json_encode(['xml' => $xml, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_xml_questions.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{xml:string,queries:array{0:string,1:string,2:string,3:string}} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('modules:module_id=5', $result['queries'][0]);
        self::assertSame('subjects:subject_module_id=5 AND subject_id=6', $result['queries'][1]);
        self::assertStringContainsString('WHERE question_subject_id=6', $result['queries'][2]);
        self::assertStringContainsString("WHERE answer_question_id='7'", $result['queries'][3]);
        self::assertStringContainsString('<name>Algebra &amp; Geometry</name>', $result['xml']);
        self::assertStringContainsString('<name>Angles &lt;90</name>', $result['xml']);
        self::assertStringContainsString('<type>single</type>', $result['xml']);
        self::assertStringContainsString('<description>Is 2 &lt; 3?</description>', $result['xml']);
        self::assertStringContainsString('<weight>1.50</weight>', $result['xml']);
        self::assertStringContainsString('<description>Yes &amp; always</description>', $result['xml']);
        self::assertStringNotContainsString('[[DB_ERROR]]', $output);
    }

    public function testEmptyQuestionsExportStructureAndSelectionRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["where"] = ""; $GLOBALS["query"] = ""; '
                    . 'function F_select_modules_sql($where) { $GLOBALS["where"] = $where; return "modules-query"; } '
                    . 'function F_db_query($query, $db) { $GLOBALS["query"] = $query; '
                    . 'return fopen("php://memory", "r"); } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_questions\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$xml = F_xml_export_questions("5x", "6x", "1x"); '
                    . 'echo $GLOBALS["where"], "\\n", $GLOBALS["query"], "\\n---\\n", $xml;',
                dirname(__DIR__) . '/admin/code/tce_xml_questions.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertMatchesRegularExpression(
            '~^module_id=5\nmodules-query\n---\n'
                . '<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamquestions version="[^"]+">\n\t<header lang="ru" '
                . 'date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t</header>\n\t<body>\n\t</body>\n</tcexamquestions>\n$~',
            $output,
        );
    }
}
