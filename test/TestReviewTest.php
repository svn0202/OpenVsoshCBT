<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_test.php';

final class TestReviewTest extends TestCase
{
    public function testTwoColumnRowPreservesExactMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        self::assertSame(
            '<div class="row"><span class="label"><span title="Description">Label: '
                . '</span></span><span class="value">Value</span></div>' . K_NEWLINE,
            \f_two_col_row('Label', 'Description', 'Value'),
        );
    }

    public function testTestInfoLinkPreservesPopupOptionsAndPlainCaption(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TEST_INFO_HEIGHT", 600); define("K_TEST_INFO_WIDTH", 800); '
                    . '$GLOBALS["l"] = ["m_new_window_link" => "New window", "w_info" => "Info"]; '
                    . 'require $argv[2]; $source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_test_info_link)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo $qualified(7, "<b>A &amp; B</b>");',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
                dirname(__DIR__) . '/shared/code/tce_functions_general.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            '<a href="tce_popup_test_info.php?testid=7" '
                . 'onclick="infoTestWindow=window.open(\'tce_popup_test_info.php?testid=7\''
                . ',\'infoTestWindow\',\'dependent,height=600,width=800,menubar=no,resizable=yes,'
                . 'scrollbars=yes,status=no,toolbar=no\');return false;" title="New window">A & B</a>',
            $output,
        );
    }


    #[DataProvider('reviewValues')]
    public function testReviewValueNormalization(mixed $value, int $expected): void
    {
        self::assertSame($expected, \f_tmf_review_value($value));
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function reviewValues(): array
    {
        return [
            'string one' => ['1', 1],
            'integer one' => [1, 1],
            'float one' => [1.0, 1],
            'boolean true' => [true, 1],
            'zero' => ['0', 0],
            'missing value' => [null, 0],
            'array input' => [['1'], 0],
        ];
    }
}
