<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AlternativeAuthTest extends TestCase
{
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
                    . 'preg_match("/function (F_altLogin|f_alt_login)\\(/", '
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
