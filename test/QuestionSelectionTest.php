<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class QuestionSelectionTest extends TestCase
{
    public function testEmptyQuestionSelectionReportsMessageAndReturnsFalse(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"]["m_databasempty"] = "No questions"; $GLOBALS["calls"] = []; '
                    . 'function F_count_rows($table) { $GLOBALS["calls"]["table"] = $table; return 0; } '
                    . 'function F_print_error(...$arguments) { $GLOBALS["calls"]["message"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_show_select_questions\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$function = substr($source, $match[0][1]); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$result = F_show_select_questions('
                    . '"enabled=1", "2", "3", "invalid", "1", "4", "25", true); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_show_all_questions.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => false,
                'calls' => [
                    'table' => 'tce_questions',
                    'message' => ['MESSAGE', 'No questions'],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
