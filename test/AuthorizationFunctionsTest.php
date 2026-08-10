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

    public function testSslCertificateHashPreservesParsedFieldOrderAndEndDate(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TIMESTAMP_FORMAT", "fmt"); '
                    . 'function openssl_x509_parse($cert) { return ["serialNumber" => "255", '
                    . '"issuer" => ["C" => "IT", "CN" => "CA"], '
                    . '"subject" => ["CN" => "user"], "validTo_time_t" => 1]; } '
                    . 'function bcdechex($serial) { return "ff"; } '
                    . 'function date($format, $time) { return $format . ":" . $time; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_ssl_certificate_hash)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode($qualified("certificate"));',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [md5('ffITCAuser1'), 'fmt:1'],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testLoginFormPreservesStructureFieldsAndFallbackContent(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_NEWLINE", "\\n"); define("K_OTP_LOGIN", false); '
                    . '$GLOBALS["l"] = ["ov_rcoko_alt" => "Logo", "a_meta_charset" => "UTF-8", '
                    . '"ov_login_intro" => "Welcome", "ov_login_intro_organization" => "School", '
                    . '"w_username" => "User", "h_login_name" => "Login", '
                    . '"ov_username_placeholder" => "Username", "w_password" => "Password", '
                    . '"h_password" => "Secret", "ov_password_placeholder" => "Password", '
                    . '"ov_show_password" => "Show", "w_login" => "Sign in", '
                    . '"h_login_button" => "Submit", "ov_login_support" => "Ask support", '
                    . '"ov_results_site" => "Results"]; '
                    . 'function openvsosh_get_access_settings() { return ["registration_enabled" => false, '
                    . '"password_reset_enabled" => false, "access_help" => ""]; } '
                    . 'function openvsosh_get_site_settings() { return ["site_name" => "Test site", '
                    . '"welcome" => "", "site_description" => "", "login_instruction" => "", '
                    . '"site_contact" => ""]; } '
                    . 'function openvsosh_site_asset_metadata($type) { return null; } '
                    . 'function get_form_row_text_input($field) { return "<FIELD:" . $field . ">"; } '
                    . 'function f_get_csrf_token_field() { return "<CSRF>"; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_login_form_markup)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo $qualified("/login", "login-form", "post", "multipart/form-data", "alice");',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringContainsString(
            '<form action="/login" method="post" id="login-form" enctype="multipart/form-data">',
            $output,
        );
        self::assertStringContainsString('<FIELD:xuser_name><FIELD:xuser_password>', $output);
        self::assertStringContainsString('<CSRF>', $output);
        self::assertStringContainsString('../../images/vsosh-logo.png', $output);
        self::assertStringContainsString('<p>Test site</p>', $output);
        self::assertStringContainsString('<p>Ask support</p>', $output);
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

    public function testRecordAuthorizationPreservesAllDecisionBranches(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_AUTH_ADMINISTRATOR", 10); '
                    . 'define("K_TABLE_USERGROUP", "user_groups"); $_SESSION["session_user_id"] = "11"; '
                    . '$GLOBALS["db"] = "db"; $GLOBALS["counts"] = [1, 0, 1, 0, 0]; '
                    . '$GLOBALS["query_results"] = [true, true, false]; $GLOBALS["rows"] = [[12], [1]]; '
                    . '$GLOBALS["count_calls"] = []; $GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_escape_sql($db, $value) { return $value; } '
                    . 'function F_count_rows($where) { $GLOBALS["count_calls"][] = $where; '
                    . 'return array_shift($GLOBALS["counts"]); } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_is_authorized_user)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $results = []; '
                    . '$_SESSION["session_user_level"] = 10; '
                    . '$results[] = $qualified("records", "record_id", "7", "owner_id"); '
                    . '$_SESSION["session_user_level"] = 0; '
                    . 'foreach ([8, 9, 10, 11] as $id) { '
                    . '$results[] = $qualified("records", "record_id", (string) $id, "owner_id"); } '
                    . 'echo json_encode([$results, $GLOBALS["errors"], '
                    . '$GLOBALS["count_calls"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [true, true, true, false, false],
                1,
                [
                    'records WHERE record_id=8 AND owner_id=11 LIMIT 1',
                    'records WHERE record_id=9 AND owner_id=11 LIMIT 1',
                    "user_groups AS ta, user_groups AS tb\n"
                        . "\t\tWHERE ta.usrgrp_group_id=tb.usrgrp_group_id\n"
                        . "\t\t\tAND ta.usrgrp_user_id=12\n\t\t\tAND tb.usrgrp_user_id=11\n\t\t\tLIMIT 1",
                    'records WHERE record_id=10 AND owner_id=11 LIMIT 1',
                    'records WHERE record_id=11 AND owner_id=11 LIMIT 1',
                ],
                [
                    'SELECT owner_id FROM records WHERE record_id=9 LIMIT 1',
                    'SELECT owner_id FROM records WHERE record_id=10 LIMIT 1',
                    'SELECT owner_id FROM records WHERE record_id=11 LIMIT 1',
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAuthorizedUserListPreservesRowsOrderAndQueryErrors(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_USERGROUP", "user_groups"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["query_results"] = [true, false]; '
                    . '$GLOBALS["rows"] = [[3], [7], false]; $GLOBALS["queries"] = []; $GLOBALS["errors"] = 0; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; '
                    . 'return array_shift($GLOBALS["query_results"]); } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_get_authorized_users)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . 'echo json_encode([[$qualified("011"), $qualified("12")], '
                    . '$GLOBALS["errors"], $GLOBALS["queries"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                ['3,7,11', '12'],
                1,
                [
                    "SELECT tb.usrgrp_user_id\n\t\tFROM user_groups AS ta, user_groups AS tb\n"
                        . "\t\tWHERE ta.usrgrp_group_id=tb.usrgrp_group_id\n\t\t\tAND ta.usrgrp_user_id=11",
                    "SELECT tb.usrgrp_user_id\n\t\tFROM user_groups AS ta, user_groups AS tb\n"
                        . "\t\tWHERE ta.usrgrp_group_id=tb.usrgrp_group_id\n\t\t\tAND ta.usrgrp_user_id=12",
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUserGroupSynchronizationAddsOnlyMissingUniqueGroups(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_USERGROUP", "user_groups"); $GLOBALS["db"] = "db"; '
                    . '$GLOBALS["queries"] = []; $GLOBALS["rows"] = [["usrgrp_group_id" => 2], false]; '
                    . '$GLOBALS["errors"] = 0; '
                    . 'function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["queries"][] = $sql; return true; } '
                    . 'function F_db_fetch_array($result) { return array_shift($GLOBALS["rows"]); } '
                    . 'function F_display_db_error() { ++$GLOBALS["errors"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_sync_user_groups)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . '$function = substr($source, $start, $end - $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; $qualified(11, "2,2,3"); '
                    . 'echo json_encode([$GLOBALS["queries"], $GLOBALS["errors"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_authorization.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertJson($output);
        self::assertSame(
            [
                [
                    'SELECT usrgrp_group_id FROM user_groups WHERE usrgrp_user_id=11',
                    "INSERT INTO user_groups (\n\t\t\t\tusrgrp_user_id,\n\t\t\t\tusrgrp_group_id\n"
                        . "\t\t\t\t) VALUES (\n\t\t\t\t'11',\n\t\t\t\t'3'\n\t\t\t\t)",
                ],
                0,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
