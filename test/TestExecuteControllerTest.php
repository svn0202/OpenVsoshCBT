<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestExecuteControllerTest extends TestCase
{
    public function testNullableTestNamePreservesEmptyTitleSuffix(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["thispage_title"] = "Execute test"; '
                    . 'function f_get_test_name($id) { return null; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "\\$thispage_title .="); '
                    . '$end = strpos($source, ";", $start) + 1; '
                    . '$statement = substr($source, $start, $end - $start); '
                    . '$test_id = 7; eval("namespace Harness; " . $statement); '
                    . 'echo json_encode($GLOBALS["thispage_title"]);',
                dirname(__DIR__) . '/public/code/tce_test_execute.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('Execute test: ', json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testPageWithoutSelectedTestKeepsCurrentOutputAndContext(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_PUBLIC_TEST_EXECUTE', 3);
define('K_NEWLINE', "\n");
$l = [
    't_test_execute' => 'Execute test',
    'hp_test_execute' => 'Choose a test to begin.',
];
PHP;
        $headerSource = <<<'PHP'
<?php
$GLOBALS['header_context'] = [$pagelevel, $thispage_title, $thispage_description];
echo "<HEADER>\n";
PHP;
        $script = <<<'PHP'
$root = sys_get_temp_dir() . '/openvsosh-test-execute-' . uniqid();
mkdir($root . '/public/code', 0700, true);
mkdir($root . '/public/config', 0700);
mkdir($root . '/shared/code', 0700, true);
copy($argv[1], $root . '/public/code/tce_test_execute.php');
file_put_contents($root . '/public/config/tce_config.php', base64_decode($argv[2], true));
file_put_contents($root . '/public/code/tce_page_header.php', base64_decode($argv[3], true));
file_put_contents($root . '/public/code/tce_page_footer.php', "<?php echo \"<FOOTER>\\n\";");
foreach (['tce_authorization.php', 'tce_functions_form.php', 'tce_functions_test.php'] as $file) {
    file_put_contents($root . '/shared/code/' . $file, '<?php');
}
$_REQUEST = [];
$_POST = [];
chdir($root . '/public/code');
ob_start();
require 'tce_test_execute.php';
$page = ob_get_clean();
echo json_encode([$page, $GLOBALS['header_context'], $test_id, $testlog_id], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/public/code/tce_test_execute.php',
                base64_encode($configSource),
                base64_encode($headerSource),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string, array{int, string, string}, int, int} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            "<HEADER>\n<div class=\"container\">\n"
                . "<div class=\"pagehelp\">Choose a test to begin.</div>\n"
                . "</div>\n<FOOTER>\n",
            $decoded[0],
        );
        self::assertSame([3, 'Execute test', 'Choose a test to begin.'], $decoded[1]);
        self::assertSame(0, $decoded[2]);
        self::assertSame(0, $decoded[3]);
    }
}
