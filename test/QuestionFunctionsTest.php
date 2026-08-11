<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class QuestionFunctionsTest extends TestCase
{
    public function testMysqlBinaryUniquitySupportsOptionalBooleanConfiguration(): void
    {
        foreach (['undefined' => false, 'false' => false, 'true' => true] as $configuration => $expected) {
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    <<<'PHP'
namespace Harness;
if ($argv[2] !== 'undefined') {
    define('K_MYSQL_QA_BIN_UNIQUITY', $argv[2] === 'true');
}
$source = file_get_contents($argv[1]);
preg_match('/function f_tmf_questions_mysql_binary_uniquity\(\): bool\s*\{.*?\n\}/s', $source, $match);
eval('namespace Harness; ' . $match[0]);
echo json_encode(f_tmf_questions_mysql_binary_uniquity(), JSON_THROW_ON_ERROR);
PHP,
                    dirname(__DIR__) . '/admin/code/tce_functions_questions.php',
                    $configuration,
                ],
                dirname(__DIR__) . '/admin/code',
            );

            self::assertSame(0, $status, $output);
            self::assertSame($expected, json_decode($output, true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function testQuestionDataQueryAndResultRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["query"] = ""; '
                    . 'function F_db_query($query, $db) { $GLOBALS["query"] = $query; return "result"; } '
                    . 'function F_db_fetch_array($result) { return ["question_id" => 17]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_question_get_data/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$result = F_question_get_data("17x"); '
                    . 'echo json_encode(["result" => $result, "query" => $GLOBALS["query"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_questions.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => ['question_id' => 17],
                'query' => "SELECT *\n\t\tFROM tce_questions\n\t\tWHERE question_id=17\n\t\tLIMIT 1",
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
