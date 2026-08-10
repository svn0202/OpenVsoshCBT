<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EmailResultsEndpointTest extends TestCase
{
    public function testEmailResultsPreserveFiltersAndProgressPage(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_ADMIN_RESULTS', 8);
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_NEWLINE', "\n");
$l = [
    't_email_result' => 'Email results',
    'hp_email_result' => 'Email result description',
    'm_authorization_denied' => 'Denied',
    'hp_sending_in_progress' => 'Sending',
    'm_process_completed' => 'Completed',
];
function F_print_error($type, $message): void
{
    $GLOBALS['messages'][] = [$type, $message];
    echo '<MESSAGE:' . $type . ':' . $message . '>';
}
PHP;
        $userSelectSource = <<<'PHP'
<?php
function f_is_authorized_user(...$arguments): bool
{
    $GLOBALS['authorization'] = $arguments;
    return true;
}
PHP;
        $reportSource = <<<'PHP'
<?php
function f_send_report_emails(...$arguments): void
{
    $GLOBALS['report_arguments'] = $arguments;
    echo '<REPORT>';
}
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-email-results-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_email_results.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'file_put_contents($root . "/shared/code/tce_authorization.php", "<?php"); '
                    . 'file_put_contents($root . "/admin/code/tce_functions_user_select.php", base64_decode($argv[3], true)); '
                    . 'file_put_contents($root . "/admin/code/tce_functions_email_reports.php", base64_decode($argv[4], true)); '
                    . 'file_put_contents($root . "/admin/code/tce_page_header.php", '
                    . '"<?php \\$GLOBALS[\\"header_context\\"] = [\\$pagelevel, \\$thispage_title, '
                    . '\\$thispage_description]; echo \\"<HEADER>\\\\n\\";"); '
                    . 'file_put_contents($root . "/admin/code/tce_page_footer.php", "<?php echo \\"<FOOTER>\\\\n\\";"); '
                    . '$_REQUEST = ["test_id" => "17", "user_id" => "9", "testuser_id" => "25", '
                    . '"group_id" => "4", "startdate" => "2026-08-01 10:20:30", '
                    . '"enddate" => "2026-08-02 11:22:33", "mode" => "2", '
                    . '"display_mode" => "9", "show_graph" => "1"]; '
                    . 'chdir($root . "/admin/code"); ob_start(); require "tce_email_results.php"; $page = ob_get_clean(); '
                    . '$result = [$page, $GLOBALS["header_context"], $GLOBALS["authorization"], '
                    . '$GLOBALS["report_arguments"], $GLOBALS["messages"]]; '
                    . 'foreach (["/admin/code/tce_email_results.php", "/admin/code/tce_functions_user_select.php", '
                    . '"/admin/code/tce_functions_email_reports.php", "/admin/code/tce_page_header.php", '
                    . '"/admin/code/tce_page_footer.php", "/admin/config/tce_config.php", '
                    . '"/shared/code/tce_authorization.php"] as $file) { unlink($root . $file); } '
                    . 'rmdir($root . "/admin/code"); rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_email_results.php',
                base64_encode($configSource),
                base64_encode($userSelectSource),
                base64_encode($reportSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: string,
         *     1: array{int, string, string},
         *     2: array{string, string, int, string},
         *     3: array{int, int, int, int, string, string, int, int, int},
         *     4: array{array{string, string}}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            '<HEADER>' . "\n<div class=\"popupcontainer\">\n"
                . '<div class="pagehelp">Sending</div>' . "\n"
                . '<REPORT><MESSAGE:MESSAGE:Completed></div>' . "\n<FOOTER>\n",
            $decoded[0],
        );
        self::assertSame([8, 'Email results', 'Email result description'], $decoded[1]);
        self::assertSame(['tests', 'test_id', 17, 'test_user_id'], $decoded[2]);
        self::assertSame(
            [17, 9, 25, 4, '2026-08-01 10:20:30', '2026-08-02 11:22:33', 2, 5, 1],
            $decoded[3],
        );
        self::assertSame([['MESSAGE', 'Completed']], $decoded[4]);
    }
}
