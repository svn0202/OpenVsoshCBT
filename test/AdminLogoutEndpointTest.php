<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminLogoutEndpointTest extends TestCase
{
    /** @return iterable<string, array{string|null, string}> */
    public static function currentPageProvider(): iterable
    {
        yield 'default page' => [null, '../code/index.php?logout=1'];
        yield 'page without query' => ['/admin/code/users.php', '/admin/code/users.php?logout=1'];
        yield 'page with query' => ['/admin/code/users.php?group=3', '/admin/code/users.php?group=3&amp;logout=1'];
    }

    #[DataProvider('currentPageProvider')]
    public function testAdminLogoutPreservesCurrentPageRedirect(?string $currentPage, string $redirect): void
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
    'w_logout' => 'Admin "out" & leave',
];
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-admin-logout-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_logout.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[3], true)); '
                    . 'file_put_contents($root . "/shared/code/tce_functions_session.php", "<?php"); '
                    . '$requestedPage = json_decode($argv[2], true); '
                    . 'if (is_string($requestedPage)) { $current_page = $requestedPage; } '
                    . 'chdir($root . "/admin/code"); session_start(); $_SESSION["user"] = 17; '
                    . 'ob_start(); require "tce_logout.php"; $page = ob_get_clean(); '
                    . '$result = [$page, session_status(), $_SESSION, $current_page]; '
                    . 'unlink($root . "/admin/code/tce_logout.php"); '
                    . 'unlink($root . "/admin/config/tce_config.php"); '
                    . 'unlink($root . "/shared/code/tce_functions_session.php"); '
                    . 'rmdir($root . "/admin/code"); rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_logout.php',
                json_encode($currentPage, JSON_THROW_ON_ERROR),
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{0: string, 1: int, 2: array<array-key, mixed>, 3: string} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            '<!DOCTYPE html>' . "\n"
                . '<html lang="en" dir="ltr">' . "\n" . '<head>' . "\n"
                . '<meta charset="UTF-8" />' . "\n"
                . '<title>Admin &quot;out&quot; &amp; leave</title>' . "\n"
                . '<meta http-equiv="refresh" content="0;url=' . $redirect . '" />' . "\n"
                . '</head>' . "\n" . '<body>' . "\n" . '<main id="maincontent">' . "\n"
                . '<a href="' . $redirect . '">Admin "out" & leave...</a>' . "\n"
                . '</main>' . "\n" . '</body>' . "\n" . '</html>' . "\n",
            $decoded[0],
        );
        self::assertSame(PHP_SESSION_NONE, $decoded[1]);
        self::assertSame([], $decoded[2]);
        self::assertSame($redirect, $decoded[3]);
    }
}
