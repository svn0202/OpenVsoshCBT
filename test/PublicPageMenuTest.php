<?php

namespace Test;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class PublicPageMenuTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testGuestSeesLoginButNotAuthenticatedLinks(): void
    {
        $root = sys_get_temp_dir() . '/tce-menu-' . (string) getmypid() . '-' . hrtime(true);
        $code_dir = $root . '/public/code';
        $shared_dir = $root . '/shared/code';
        mkdir($code_dir, 0o777, true);
        mkdir($shared_dir, 0o777, true);
        copy(__DIR__ . '/../public/code/tce_page_menu.php', $code_dir . '/tce_page_menu.php');
        file_put_contents($shared_dir . '/tce_functions_menu.php', <<<'PHP'
<?php
function F_menu_link($link, $data, $level = 0): ?string
{
    if (!$data['enabled'] || $_SESSION['session_user_level'] < $data['level']) {
        return null;
    }
    return $data['link'] . "\n";
}
PHP);

        $l = array_fill_keys([
            'h_index', 'w_index', 't_all_results_user', 'w_results', 'w_user',
            'h_admin_link', 'w_admin', 'h_logout_link', 'w_logout',
            'h_login_link', 'w_login', 't_user_change_email', 'w_change_email',
            't_user_change_password', 'w_change_password',
        ], 'label');
        $_SESSION['session_user_level'] = 0;
        $_SERVER['SCRIPT_NAME'] = '/public/code/index.php';
        define('K_AUTH_PUBLIC_INDEX', 1);
        define('K_AUTH_PUBLIC_TEST_RESULTS', 1);
        define('K_AUTH_PAGE_USER', 1);
        define('K_ADMIN_LINK', 5);
        define('K_AUTH_USER_CHANGE_EMAIL', 1);
        define('K_AUTH_USER_CHANGE_PASSWORD', 1);
        define('K_NEWLINE', "\n");

        $previous_dir = getcwd();
        self::assertIsString($previous_dir);
        chdir($code_dir);
        ob_start();
        require $code_dir . '/tce_page_menu.php';
        $output = (string) ob_get_clean();
        chdir($previous_dir);

        self::assertStringContainsString("tce_login.php\n", $output);
        self::assertStringNotContainsString("tce_logout.php\n", $output);
        self::assertStringNotContainsString("tce_test_allresults.php\n", $output);

        unlink($shared_dir . '/tce_functions_menu.php');
        unlink($code_dir . '/tce_page_menu.php');
        rmdir($shared_dir);
        rmdir($root . '/shared');
        rmdir($code_dir);
        rmdir($root . '/public');
        rmdir($root);
    }
}
