<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PageFunctionsTest extends TestCase
{
    public function testNavigatorPreservesCountQueryLinksAndReturnValue(): void
    {
        $configSource = <<<'PHP'
<?php
$db = 'db-link';
$l = ['m_search_void' => 'No records', 'w_page' => 'Page', 'w_previous' => 'Previous', 'w_next' => 'Next'];
function F_db_query($sql, $db)
{
    $GLOBALS['queries'][] = [$sql, $db];
    return 'result';
}
function F_db_num_rows($result): int { return 1; }
function F_db_fetch_array($result): array
{
    $GLOBALS['fetches'][] = $result;
    return ['55'];
}
function F_display_db_error(): void { $GLOBALS['db_error'] = true; }
function F_print_error($type, $message): void { echo '<' . $type . ':' . $message . '>'; }
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-page-functions-" . uniqid(); '
                    . 'mkdir($root . "/shared/code", 0700, true); mkdir($root . "/shared/config", 0700); '
                    . 'copy($argv[1], $root . "/shared/code/tce_functions_page.php"); '
                    . 'file_put_contents($root . "/shared/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'chdir($root . "/shared/code"); require "tce_functions_page.php"; ob_start(); '
                    . '$count = F_show_page_navigator("list.php", "SELECT COUNT(*) FROM users", '
                    . '20, 10, "&amp;filter=x"); $page = ob_get_clean(); '
                    . '$result = [$page, $count, $GLOBALS["queries"], $GLOBALS["fetches"], '
                    . 'isset($GLOBALS["db_error"])]; unlink($root . "/shared/code/tce_functions_page.php"); '
                    . 'unlink($root . "/shared/config/tce_config.php"); rmdir($root . "/shared/code"); '
                    . 'rmdir($root . "/shared/config"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/shared/code/tce_functions_page.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: string,
         *     1: string,
         *     2: array{array{string, string}},
         *     3: array{string},
         *     4: bool
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            '<div class="pageselector">Page: '
                . '<a href="list.php?filter=x&amp;firstrow=0">1</a> | '
                . '<a href="list.php?filter=x&amp;firstrow=10" title="Previous">&lt;</a> | '
                . '<a href="list.php?filter=x&amp;firstrow=10" title="2">2</a> | 3 | '
                . '<a href="list.php?filter=x&amp;firstrow=30" title="4">4</a> | '
                . '<a href="list.php?filter=x&amp;firstrow=40" title="5">5</a> | '
                . '<a href="list.php?filter=x&amp;firstrow=30" title="Next">&gt;</a> | '
                . '<a href="list.php?filter=x&amp;firstrow=50" title="6">6</a></div>',
            $decoded[0],
        );
        self::assertSame('55', $decoded[1]);
        self::assertSame([['SELECT COUNT(*) FROM users', 'db-link']], $decoded[2]);
        self::assertSame(['result'], $decoded[3]);
        self::assertFalse($decoded[4]);
    }

    public function testNavigatorRejectsEmptyQueryWithoutDatabaseAccess(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_page.php"; '
                    . 'echo json_encode(F_show_page_navigator("list.php", "", 0, 20, ""));',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }
}
