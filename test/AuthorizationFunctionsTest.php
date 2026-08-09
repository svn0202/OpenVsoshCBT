<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AuthorizationFunctionsTest extends TestCase
{
    public function testLogoutFormRenderingRemainsUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"] = ["d_logout_desc" => "Leave now?", "w_logout" => "Logout"]; '
                    . '$_SERVER["SCRIPT_NAME"] = "/public/code/logout.php"; '
                    . 'function F_getCSRFTokenField() { return "<input name=csrf />"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_logout_form\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . 'echo F_logout_form();',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            "\n<div class=\"container\">\n<div class=\"tceformbox\">\n"
                . '<form action="../code/tce_logout.php" method="post" id="form_logout" '
                . "enctype=\"multipart/form-data\">\n<div class=\"row\">\nLeave now?\n</div>\n"
                . "<div class=\"row\">\n"
                . '<input type="hidden" name="current_page" id="current_page" '
                . "value=\"/public/code/logout.php\" />\n"
                . "<input type=\"hidden\" name=\"logaction\" id=\"logaction\" value=\"\" />\n"
                . "<input type=\"submit\" name=\"login\" id=\"login\" value=\"Logout\" />\n"
                . "</div>\n<input name=csrf />\n</form>\n</div>\n",
            $output,
        );
    }
}
