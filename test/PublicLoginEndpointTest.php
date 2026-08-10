<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicLoginEndpointTest extends TestCase
{
    public function testPublicLoginPreservesMainPageRedirectAndLinkEncoding(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_NEWLINE', "\n");
define('K_MAIN_PAGE', '/public/code/index.php?next=one%20two&group=3');
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
                '$root = sys_get_temp_dir() . "/openvsosh-public-login-" . uniqid(); '
                    . 'mkdir($root . "/public/code", 0700, true); mkdir($root . "/public/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/public/code/tce_login.php"); '
                    . 'file_put_contents($root . "/public/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'file_put_contents($root . "/shared/code/tce_authorization.php", "<?php"); '
                    . 'chdir($root . "/public/code"); ob_start(); require "tce_login.php"; $page = ob_get_clean(); '
                    . '$result = [$page, $pagelevel]; unlink($root . "/public/code/tce_login.php"); '
                    . 'unlink($root . "/public/config/tce_config.php"); '
                    . 'unlink($root . "/shared/code/tce_authorization.php"); '
                    . 'rmdir($root . "/public/code"); rmdir($root . "/public/config"); rmdir($root . "/public"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/public/code/tce_login.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                '<!DOCTYPE html>' . "\n"
                    . '<html lang="en" dir="ltr">' . "\n" . '<head>' . "\n"
                    . '<meta charset="UTF-8" />' . "\n"
                    . '<title>Log &quot;in&quot; &amp; enter</title>' . "\n"
                    . '<meta http-equiv="refresh" content="0;url=/public/code/index.php?next=one%20two&group=3" />'
                    . "\n" . '</head>' . "\n" . '<body>' . "\n" . '<main id="maincontent">' . "\n"
                    . '<a href="/public/code/index.php?next=one two&amp;group=3">Log "in" & enter...</a>' . "\n"
                    . '</main>' . "\n" . '</body>' . "\n" . '</html>' . "\n",
                1,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
