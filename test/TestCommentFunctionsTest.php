<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestCommentFunctionsTest extends TestCase
{
    public function testUpdateTestCommentPreservesQueryAndReturnsNothing(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_TABLE_TEST_USER", "tce_tests_users"); '
                    . 'function F_escape_sql($db, $value) { return str_replace("\'", "\'\'", $value); } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["captured_sql"] = $sql; return true; } '
                    . '$db = new stdClass(); $l = []; $_SESSION["session_user_id"] = "17"; '
                    . 'require "tce_functions_test.php"; '
                    . '$result = F_updateTestComment("23", "teacher\'s note"); '
                    . 'echo json_encode([$result, preg_replace("/\\s+/", " ", trim($captured_sql))]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            '[null,"UPDATE tce_tests_users SET testuser_comment=\'teacher\'\'s note\' '
                . 'WHERE testuser_test_id=23 AND testuser_user_id=17 AND testuser_status<4"]',
            $output,
        );
    }
}
