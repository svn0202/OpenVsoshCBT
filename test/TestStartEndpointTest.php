<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestStartEndpointTest extends TestCase
{
    /** @return iterable<string, array{array<string, string>, string, int}> */
    public static function requestProvider(): iterable
    {
        yield 'no selected test' => [
            [],
            '<HEADER>' . "\n<div class=\"popupcontainer\">\n</div>\n<FOOTER>\n",
            0,
        ];
        yield 'selected repeat test' => [
            ['testid' => '17', 'repeat' => '1'],
            '<HEADER>' . "\n<div class=\"popupcontainer\">\n"
                . '<INFO:17:false><br />' . "\n<div class=\"row\">\n"
                . '<a href="tce_test_execute.php?testid=17&amp;repeat=1" title="Execute now" '
                . 'class="xmlbutton">Execute</a> '
                . '<a href="index.php" title="Cancel test" class="xmlbutton">Cancel</a>'
                . "</div>\n</div>\n<FOOTER>\n",
            17,
        ];
    }

    /** @param array<string, string> $request */
    #[DataProvider('requestProvider')]
    public function testStartPagePreservesRequestModes(array $request, string $expectedPage, int $expectedTestId): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_PUBLIC_TEST_EXECUTE', 3);
define('K_NEWLINE', "\n");
$l = [
    't_test_info' => 'Test information',
    'hp_test_info' => 'Description',
    'h_execute' => 'Execute now',
    'w_execute' => 'Execute',
    'h_cancel' => 'Cancel test',
    'w_cancel' => 'Cancel',
];
PHP;
        $headerSource = <<<'PHP'
<?php
$GLOBALS['header_context'] = [$pagelevel, $thispage_title, $thispage_description];
echo "<HEADER>\n";
PHP;
        $functionsSource = <<<'PHP'
<?php
function f_print_test_info(int $testId, bool $showTitle): string
{
    return '<INFO:' . $testId . ':' . ($showTitle ? 'true' : 'false') . '>';
}
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-test-start-" . uniqid(); '
                    . 'mkdir($root . "/public/code", 0700, true); mkdir($root . "/public/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/public/code/tce_test_start.php"); '
                    . 'file_put_contents($root . "/public/config/tce_config.php", base64_decode($argv[3], true)); '
                    . 'file_put_contents($root . "/public/code/tce_page_header.php", base64_decode($argv[4], true)); '
                    . 'file_put_contents($root . "/public/code/tce_page_footer.php", "<?php echo \\"<FOOTER>\\\\n\\";"); '
                    . 'file_put_contents($root . "/shared/code/tce_authorization.php", "<?php"); '
                    . 'file_put_contents($root . "/shared/code/tce_functions_test.php", base64_decode($argv[5], true)); '
                    . '$_REQUEST = json_decode($argv[2], true); chdir($root . "/public/code"); '
                    . 'ob_start(); require "tce_test_start.php"; $page = ob_get_clean(); '
                    . '$result = [$page, $GLOBALS["header_context"], $test_id]; '
                    . 'foreach (["/public/code/tce_test_start.php", "/public/code/tce_page_header.php", '
                    . '"/public/code/tce_page_footer.php", "/public/config/tce_config.php", '
                    . '"/shared/code/tce_authorization.php", "/shared/code/tce_functions_test.php"] as $file) '
                    . '{ unlink($root . $file); } rmdir($root . "/public/code"); rmdir($root . "/public/config"); '
                    . 'rmdir($root . "/public"); rmdir($root . "/shared/code"); rmdir($root . "/shared"); '
                    . 'rmdir($root); echo json_encode($result);',
                dirname(__DIR__) . '/public/code/tce_test_start.php',
                json_encode($request, JSON_THROW_ON_ERROR),
                base64_encode($configSource),
                base64_encode($headerSource),
                base64_encode($functionsSource),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: string, 1: array{int, string, string}, 2: int} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expectedPage, $decoded[0]);
        self::assertSame([3, 'Test information', 'Description'], $decoded[1]);
        self::assertSame($expectedTestId, $decoded[2]);
    }
}
