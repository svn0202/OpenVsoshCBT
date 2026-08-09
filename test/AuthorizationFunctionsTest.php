<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AuthorizationFunctionsTest extends TestCase
{
    public function testSslCertificateValidityRequiresSuccessfulUnexpiredClientCertificate(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_is_ssl_certificate_valid)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval(substr($source, $start, $end - $start)); '
                    . '$_SERVER = ["SSL_CLIENT_M_SERIAL" => "01", "SSL_CLIENT_I_DN" => "issuer", '
                    . '"SSL_CLIENT_V_END" => "future", "SSL_CLIENT_VERIFY" => "SUCCESS", '
                    . '"SSL_CLIENT_V_REMAIN" => 1]; $valid = $name(); '
                    . '$_SERVER["SSL_CLIENT_V_REMAIN"] = 0; $expired = $name(); '
                    . 'echo json_encode([$valid, $expired]);',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame([true, false], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSslClientHashPreservesCertificateFieldOrderAndNormalization(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_ssl_client_hash)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'if ($end === false) { $end = strlen($source); } '
                    . 'eval(substr($source, $start, $end - $start)); '
                    . '$_SERVER = ["SSL_CLIENT_M_SERIAL" => "ab", "SSL_CLIENT_I_DN_C" => "IT", '
                    . '"SSL_CLIENT_S_DN_CN" => "user", '
                    . '"SSL_CLIENT_V_END" => "1970-01-01 00:00:01 UTC"]; '
                    . 'echo $name();',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(md5('ABITuser1'), $output);
    }

    public function testLogoutPageSetsTitleRendersFormAndTerminates(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"] = ["t_logout_form" => "Sign out"]; '
                    . 'function F_logout_form() { return "FORM"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_logout_page\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . 'register_shutdown_function(static function (): void { '
                    . 'echo json_encode(["title" => $GLOBALS["thispage_title"] ?? null]); }); '
                    . 'F_logout_page(); echo "unreachable";',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('FORM{"title":"Sign out"}', $output);
    }

    public function testAdminLoginRedirectPreservesRequestedUri(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_login_form\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\n/**", $start); '
                    . 'eval(substr($source, $start, $end - $start)); '
                    . '$_SERVER["SCRIPT_NAME"] = "/app/admin/code/users.php"; '
                    . '$_SERVER["REQUEST_METHOD"] = "GET"; '
                    . '$_SERVER["REQUEST_URI"] = "/app/admin/code/users.php?group=7"; '
                    . '$_SESSION["session_user_level"] = 0; '
                    . 'register_shutdown_function(static function (): void { '
                    . 'echo json_encode(["redirect" => $_SESSION["session_login_redirect"] ?? null]); }); '
                    . 'F_login_form();',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            ['redirect' => '/app/admin/code/users.php?group=7'],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testLogoutFormRenderingRemainsUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; '
                    . '$GLOBALS["l"] = ["d_logout_desc" => "Leave now?", "w_logout" => "Logout"]; '
                    . '$_SERVER["SCRIPT_NAME"] = "/public/code/logout.php"; '
                    . 'function f_get_csrf_token_field() { return "<input name=csrf />"; } '
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
