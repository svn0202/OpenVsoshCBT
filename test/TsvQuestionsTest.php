<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TsvQuestionsTest extends TestCase
{
    public function testNestedQuestionExportKeepsExactTsvAndQueryContract(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TAB', "\t");
define('K_NEWLINE', "\n");
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_ANSWERS', 'answers');
$db = 'db';
$GLOBALS['queries'] = [];
$GLOBALS['kinds'] = [];
$GLOBALS['indexes'] = ['modules' => 0, 'subjects' => 0, 'questions' => 0, 'answers' => 0];
function F_select_modules_sql($where) { return 'MODULES ' . $where; }
function F_select_subjects_sql($where) { return 'SUBJECTS ' . $where; }
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    $kind = str_starts_with($sql, 'MODULES') ? 'modules'
        : (str_starts_with($sql, 'SUBJECTS') ? 'subjects'
            : (str_contains($sql, 'FROM questions') ? 'questions' : 'answers'));
    $result = fopen('php://memory', 'r');
    $GLOBALS['kinds'][get_resource_id($result)] = $kind;
    return $result;
}
function F_db_fetch_array($result) {
    $kind = $GLOBALS['kinds'][get_resource_id($result)];
    $rows = [
        'modules' => [['module_id' => '7', 'module_enabled' => '1', 'module_name' => 'Module A']],
        'subjects' => [['subject_id' => '9', 'subject_enabled' => '1', 'subject_name' => 'Topic A',
            'subject_description' => 'Topic description']],
        'questions' => [[
            'question_id' => '11', 'question_enabled' => '1', 'question_description' => 'Question?',
            'question_explanation' => 'Because', 'question_type' => 2, 'question_difficulty' => '3',
            'question_position' => '4', 'question_timer' => '60', 'question_fullscreen' => '0',
            'question_inline_answers' => '1', 'question_auto_next' => '0', 'question_shuffle_answers' => '1',
        ]],
        'answers' => [
            ['answer_enabled' => '1', 'answer_description' => 'First', 'answer_explanation' => 'Yes',
                'answer_isright' => '1', 'answer_position' => '1', 'answer_keyboard_key' => 'A', 'answer_weight' => '1.5'],
            ['answer_enabled' => '1', 'answer_description' => 'Second', 'answer_explanation' => 'No',
                'answer_isright' => '0', 'answer_position' => '2', 'answer_keyboard_key' => 'B', 'answer_weight' => '0'],
        ],
    ];
    return $rows[$kind][$GLOBALS['indexes'][$kind]++] ?? false;
}
function f_get_boolean($value) { return (bool) $value; }
function f_text_to_tsv($value) { return str_replace(["\t", "\r", "\n"], ' ', $value); }
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
$source = file_get_contents($argv[1]);
$start = strpos($source, 'function f_tsv_export_questions');
$function = substr($source, $start);
$function = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $function);
eval('namespace Harness; ' . $function);
$tsv = f_tsv_export_questions(7, 9, 1);
echo json_encode([$tsv, $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_tsv_questions.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string,list<string>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$tsv, $queries] = $decoded;
        $expected = "M=MODULE\tmodule_enabled\tmodule_name\n"
            . "S=SUBJECT\tsubject_enabled\tsubject_name\tsubject_description\n"
            . "Q=QUESTION\tquestion_enabled\tquestion_description\tquestion_explanation\tquestion_type"
            . "\tquestion_difficulty\tquestion_position\tquestion_timer\tquestion_fullscreen"
            . "\tquestion_inline_answers\tquestion_auto_next\tquestion_shuffle_answers\n"
            . "A=ANSWER\tanswer_enabled\tanswer_description\tanswer_explanation\tanswer_isright"
            . "\tanswer_position\tanswer_keyboard_key\tanswer_weight\n\n"
            . "M\t1\tModule A\n"
            . "S\t1\tTopic A\tTopic description\n"
            . "Q\t1\tQuestion?\tBecause\tM\t3\t4\t60\t0\t1\t0\t1\n"
            . "A\t1\tFirst\tYes\t1\t1\tA\t1.5\n"
            . "A\t1\tSecond\tNo\t0\t2\tB\t0\n";
        self::assertSame($expected, $tsv);
        self::assertSame('MODULES module_id=7', $queries[0] ?? null);
        self::assertSame('SUBJECTS subject_module_id=7 AND subject_id=9', $queries[1] ?? null);
        self::assertStringContainsString('FROM questions WHERE question_subject_id=9', $queries[2] ?? '');
        self::assertStringContainsString("FROM answers WHERE answer_question_id='11'", $queries[3] ?? '');
    }
}
