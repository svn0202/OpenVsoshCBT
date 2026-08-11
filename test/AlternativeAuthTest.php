<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AlternativeAuthTest extends TestCase
{
    public function testLdapSearchFailureReturnsNullWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-n',
                '-r',
                'namespace Harness; $GLOBALS["calls"] = []; '
                    . 'function ldap_search($connection, $base, $filter, $attributes) {'
                    . '$GLOBALS["calls"][] = [get_class($connection), $base, $filter, $attributes]; '
                    . 'trigger_error("directory unavailable", E_USER_WARNING); return false; } '
                    . 'function ldap_unbind($connection) { return true; } '
                    . 'function ldap_bind($connection, $dn, $password) { return true; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "function f_tmf_alt_ldap_search"); '
                    . 'eval("namespace Harness; " . substr($source, $start)); '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$connection = \\ldap_connect("ldap://127.0.0.1:1"); '
                    . '$value = f_tmf_alt_ldap_search($connection, "dc=example", "uid=alice", ["cn", "dn"]); '
                    . 'restore_error_handler(); echo json_encode([$value, $warnings, $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_altauth.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            '[null,[],[["LDAP\\\\Connection","dc=example","uid=alice",["cn","dn"]]]]',
            $output,
        );
    }

    public function testLdapCredentialFailureReturnsFalseWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-n',
                '-r',
                'namespace Harness; $GLOBALS["calls"] = []; '
                    . 'function ldap_bind($connection, $dn, $password) {'
                    . '$GLOBALS["calls"][] = [get_class($connection), $dn, $password]; '
                    . 'trigger_error("invalid credentials", E_USER_WARNING); return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "function f_tmf_alt_ldap_bind_silently"); '
                    . 'if ($start === false) { exit(42); } '
                    . 'eval("namespace Harness; " . substr($source, $start)); '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$connection = \\ldap_connect("ldap://127.0.0.1:1"); '
                    . '$value = f_tmf_alt_ldap_bind_silently($connection, "uid=alice", "wrong"); '
                    . 'restore_error_handler(); echo json_encode([$value, $warnings, $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_altauth.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            '[false,[],[["LDAP\\\\Connection","uid=alice","wrong"]]]',
            $output,
        );
    }

    public function testLdapUnbindFailureReturnsFalseWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-n',
                '-r',
                'namespace Harness; $GLOBALS["calls"] = []; '
                    . 'function ldap_unbind($connection) {'
                    . '$GLOBALS["calls"][] = get_class($connection); '
                    . 'trigger_error("unbind failed", E_USER_WARNING); return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "function f_tmf_alt_ldap_unbind_silently"); '
                    . 'if ($start === false) { exit(42); } '
                    . 'eval("namespace Harness; " . substr($source, $start)); '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$connection = \\ldap_connect("ldap://127.0.0.1:1"); '
                    . '$value = f_tmf_alt_ldap_unbind_silently($connection); '
                    . 'restore_error_handler(); echo json_encode([$value, $warnings, $GLOBALS["calls"]]);',
                dirname(__DIR__) . '/shared/code/tce_altauth.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('[false,[],["LDAP\\\\Connection"]]', $output);
    }

    public function testHttpBasicLoginPopulatesCredentialsAndProfile(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_SSL_ENABLED", false); '
                    . 'define("K_HTTPBASIC_ENABLED", true); define("K_HTTPBASIC_USER_LEVEL", 4); '
                    . 'define("K_HTTPBASIC_USER_GROUP_ID", 9); define("K_CAS_ENABLED", false); '
                    . 'define("K_SHIBBOLETH_ENABLED", false); define("K_RADIUS_ENABLED", false); '
                    . 'define("K_LDAP_ENABLED", false); define("K_CUSTOM_AUTH_METHODS", false); '
                    . '$_SESSION = ["session_user_name" => "old-user"]; '
                    . '$_SERVER = ["AUTH_TYPE" => "Basic", "PHP_AUTH_USER" => "alice", '
                    . '"PHP_AUTH_PW" => "secret"]; $_POST = []; '
                    . 'function f_legacy_literal_equals($left, $right) { return $left === $right; } '
                    . 'function f_legacy_equals($left, $right) { return $left == $right; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_alt_login)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$function = substr($source, $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$result = $qualified(); echo json_encode([$result, $_POST]);',
                dirname(__DIR__) . '/shared/code/tce_altauth.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [
                    'user_email' => '',
                    'user_firstname' => '',
                    'user_lastname' => '',
                    'user_birthdate' => '',
                    'user_birthplace' => '',
                    'user_regnumber' => '',
                    'user_ssn' => '',
                    'user_level' => 4,
                    'usrgrp_group_id' => 9,
                ],
                ['xuser_name' => 'alice', 'xuser_password' => 'secret', 'logaction' => 'login'],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAlternativeLoginReturnsFalseWhenAllProvidersAreDisabled(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_SSL_ENABLED", false); '
                    . 'define("K_HTTPBASIC_ENABLED", false); define("K_CAS_ENABLED", false); '
                    . 'define("K_SHIBBOLETH_ENABLED", false); define("K_RADIUS_ENABLED", false); '
                    . 'define("K_LDAP_ENABLED", false); define("K_CUSTOM_AUTH_METHODS", false); '
                    . '$_SESSION = []; $_SERVER = []; $_POST = []; '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_alt_login)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$function = substr($source, $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$result = $qualified(); echo json_encode([$result, $_POST]);',
                dirname(__DIR__) . '/shared/code/tce_altauth.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame([false, []], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }
}
