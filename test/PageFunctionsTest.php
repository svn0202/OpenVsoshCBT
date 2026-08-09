<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PageFunctionsTest extends TestCase
{
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
