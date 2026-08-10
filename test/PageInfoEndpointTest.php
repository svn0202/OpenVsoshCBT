<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PageInfoEndpointTest extends TestCase
{
    public function testInformationPagePreservesCreditsAndTranslations(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_ADMIN_INFO', 6);
define('K_NEWLINE', "\n");
$l = [
    't_page_info' => 'Information',
    'd_tcexam_desc' => 'Exam description',
    'w_author' => 'Author',
    'm_new_window_link' => 'New window',
    'w_license' => 'License',
    't_third_parties' => 'Third parties',
    't_translations' => 'Translations',
];
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-page-info-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_page_info.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'file_put_contents($root . "/shared/code/tce_authorization.php", "<?php"); '
                    . 'file_put_contents($root . "/admin/code/tce_page_header.php", '
                    . '"<?php \\$GLOBALS[\\"header_context\\"] = [\\$pagelevel, \\$thispage_title]; '
                    . 'echo \\"<HEADER>\\\\n\\";"); '
                    . 'file_put_contents($root . "/admin/code/tce_page_footer.php", "<?php echo \\"<FOOTER>\\\\n\\";"); '
                    . 'chdir($root . "/admin/code"); ob_start(); require "tce_page_info.php"; '
                    . '$page = ob_get_clean(); $result = [$page, $GLOBALS["header_context"]]; '
                    . 'foreach (["/admin/code/tce_page_info.php", "/admin/code/tce_page_header.php", '
                    . '"/admin/code/tce_page_footer.php", "/admin/config/tce_config.php", '
                    . '"/shared/code/tce_authorization.php"] as $file) { unlink($root . $file); } '
                    . 'rmdir($root . "/admin/code"); rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_page_info.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: string, 1: array{int, string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([6, 'Information'], $decoded[1]);
        self::assertStringStartsWith("<HEADER>\n<div class=\"container\">\nExam description<br />\n", $decoded[0]);
        self::assertStringContainsString(
            '<a href="../../LICENSE" target="_blank" rel="noopener noreferrer" title="New window">LICENSE</a>',
            $decoded[0],
        );
        self::assertStringContainsString('<li>[RU] Russian : Andrey, Sergey C., Sergey Nikitin</li>', $decoded[0]);
        self::assertStringEndsWith("</div>\n<FOOTER>\n", $decoded[0]);
        self::assertSame('f7c70ac837a7ac889908d66094188b197ad2b66f4bed16a3758f1bc038a96043', hash('sha256', $decoded[0]));
    }
}
