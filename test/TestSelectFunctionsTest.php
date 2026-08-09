<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestSelectFunctionsTest extends TestCase
{
    public function testSelectTestWrapperForwardsArgumentsAndReturnsTrue(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["arguments"] = []; '
                    . 'function F_show_select_test(...$arguments) { $GLOBALS["arguments"] = $arguments; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_select_test\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$result = F_select_test("name", "DESC", "4", "25", "enabled=1", "Math"); '
                    . 'echo json_encode(["result" => $result, "arguments" => $GLOBALS["arguments"]]);',
                dirname(__DIR__) . '/admin/code/tce_functions_test_select.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => true,
                'arguments' => ['name', 'DESC', '4', '25', 'enabled=1', 'Math'],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
