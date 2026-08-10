<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestCommentFunctionsTest extends TestCase
{
    public function testTestCommentPreservesDisabledRowsAndQueryErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_user"); '
                    . 'define("K_ANSWER_TEXTAREA_COLS", 40); define("K_NEWLINE", "\\n"); '
                    . '$_SESSION["session_user_id"] = "11"; $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["enabled"] = [false, true, true, true]; '
                    . '$GLOBALS["query_results"] = [true, true, false]; '
                    . '$GLOBALS["rows"] = [["testuser_comment" => "<unsafe>"], false]; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . '$GLOBALS["l"] = ["w_comment" => "Comment", "h_testcomment" => "Test comment"]; '
                    . 'function f_get_test_data($testId) { return ["test_comment_enabled" => '
                    . 'array_shift($GLOBALS["enabled"])]; } '
                    . 'function f_get_boolean($value) { return (bool) $value; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_test_comment)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("7"), $qualified("8"), $qualified("9"), $qualified("10")], '
                    . '$GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                ['', $this->commentMarkup('<unsafe>'), $this->commentMarkup(''), $this->commentMarkup('')],
                1,
                [
                    $this->commentQuery(8),
                    $this->commentQuery(9),
                    $this->commentQuery(10),
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

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
                    . '$result = f_update_test_comment("23", "teacher\'s note"); '
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

    private function commentMarkup(string $comment): string
    {
        return '<label for="testcomment">Comment</label><br />'
            . '<textarea cols="40" rows="4" name="testcomment" id="testcomment" class="answertext" '
            . 'title="Test comment">' . $comment . "</textarea><br />\n";
    }

    private function commentQuery(int $testId): string
    {
        return "SELECT testuser_comment\n\t\tFROM test_user\n\t\tWHERE testuser_user_id=11\n"
            . "\t\t\tAND testuser_test_id=" . $testId . "\n\t\t\tAND testuser_status<4\n\t\tLIMIT 1";
    }
}
