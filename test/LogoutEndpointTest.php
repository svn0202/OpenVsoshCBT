<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class LogoutEndpointTest extends TestCase
{
    public function testPublicLogoutDestroysSessionAndPreservesRedirectPage(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_NEWLINE', "\n");
define('K_COOKIE_PATH', '/');
define('K_COOKIE_DOMAIN', '');
define('K_COOKIE_SECURE', false);
define('K_COOKIE_HTTPONLY', true);
define('K_COOKIE_SAMESITE', 'Lax');
$l = [
    'a_meta_language' => 'en',
    'a_meta_dir' => 'ltr',
    'a_meta_charset' => 'UTF-8',
    'w_logout' => 'Sign "out" & go',
];
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-logout-" . uniqid(); '
                    . 'mkdir($root . "/public/code", 0700, true); mkdir($root . "/public/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/public/code/tce_logout.php"); '
                    . 'file_put_contents($root . "/public/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'file_put_contents($root . "/shared/code/tce_functions_session.php", "<?php"); '
                    . 'chdir($root . "/public/code"); session_start(); $_SESSION["user"] = 17; '
                    . 'ob_start(); require "tce_logout.php"; $page = ob_get_clean(); '
                    . '$result = [$page, session_status(), $_SESSION]; '
                    . 'unlink($root . "/public/code/tce_logout.php"); '
                    . 'unlink($root . "/public/config/tce_config.php"); '
                    . 'unlink($root . "/shared/code/tce_functions_session.php"); '
                    . 'rmdir($root . "/public/code"); rmdir($root . "/public/config"); '
                    . 'rmdir($root . "/public"); rmdir($root . "/shared/code"); '
                    . 'rmdir($root . "/shared"); rmdir($root); echo json_encode($result);',
                dirname(__DIR__) . '/public/code/tce_logout.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                '<!DOCTYPE html>' . "\n"
                    . '<html lang="en" dir="ltr">' . "\n<head>\n"
                    . '<meta charset="UTF-8" />' . "\n"
                    . '<title>Sign &quot;out&quot; &amp; go</title>' . "\n"
                    . '<meta http-equiv="refresh" content="0;url=../code/index.php?logout=1" />' . "\n"
                    . '</head>' . "\n" . '<body>' . "\n" . '<main id="maincontent">' . "\n"
                    . '<a href="../code/index.php?logout=1">Sign "out" & go...</a>' . "\n"
                    . '</main>' . "\n" . '</body>' . "\n" . '</html>' . "\n",
                PHP_SESSION_NONE,
                [],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
