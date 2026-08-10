<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminLoginEndpointTest extends TestCase
{
    public function testAdminLoginPreservesAuthorizationLevelAndRedirectPage(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_NEWLINE', "\n");
$l = [
    'a_meta_language' => 'en',
    'a_meta_dir' => 'ltr',
    'a_meta_charset' => 'UTF-8',
    'w_login' => 'Log "in" & enter',
];
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-admin-login-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_login.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'file_put_contents($root . "/shared/code/tce_authorization.php", "<?php"); '
                    . 'chdir($root . "/admin/code"); ob_start(); require "tce_login.php"; $page = ob_get_clean(); '
                    . '$result = [$page, $pagelevel]; unlink($root . "/admin/code/tce_login.php"); '
                    . 'unlink($root . "/admin/config/tce_config.php"); '
                    . 'unlink($root . "/shared/code/tce_authorization.php"); '
                    . 'rmdir($root . "/admin/code"); rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_login.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                '<!DOCTYPE html>' . "\n"
                    . '<html lang="en" dir="ltr">' . "\n" . '<head>' . "\n"
                    . '<meta charset="UTF-8" />' . "\n"
                    . '<title>Log &quot;in&quot; &amp; enter</title>' . "\n"
                    . '<meta http-equiv="refresh" content="0;url=index.php" />' . "\n"
                    . '</head>' . "\n" . '<body>' . "\n" . '<main id="maincontent">' . "\n"
                    . '<a href="index.php">Log "in" & enter...</a>' . "\n"
                    . '</main>' . "\n" . '</body>' . "\n" . '</html>' . "\n",
                1,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
