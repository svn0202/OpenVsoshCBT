<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TsvQuestionImporterTest extends TestCase
{
    public function testImporterRejectsUnreadableFileBeforeDatabaseAccess(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["open_calls"] = []; '
                    . 'function fopen($path, $mode) { $GLOBALS["open_calls"][] = [$path, $mode]; return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_tsv_question_importer)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$function = substr($source, $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$result = $qualified("questions.tsv"); '
                    . 'echo json_encode([$result, $GLOBALS["open_calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_import_questions.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [false, [['questions.tsv', 'r']]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
