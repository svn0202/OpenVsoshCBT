<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicPageHeaderTest extends TestCase
{
    public function testApplicationHeaderPreservesNavigationProfileAndSchedule(): void
    {
        $result = self::renderHeader(false);

        self::assertStringContainsString('class="app-menu-toggle"', $result['html']);
        self::assertStringContainsString('class="app-vsosh-wordmark"', $result['html']);
        self::assertStringContainsString('class="tmf-theme-toggle"', $result['html']);
        self::assertStringContainsString('class="tmf-admin-shortcut"', $result['html']);
        self::assertStringContainsString('class="tmf-user-panel"', $result['html']);
        self::assertStringContainsString('<dd>student&lt;1&gt;</dd>', $result['html']);
        self::assertStringContainsString("Monday<br />\nTuesday", $result['html']);
        self::assertStringContainsString('<h1>Dashboard &lt;safe&gt;</h1>', $result['html']);
        self::assertStringContainsString('[[TIMER]]', $result['html']);
        self::assertStringContainsString('[[MENU]]', $result['html']);
        self::assertSame(['SELECT user_schedule FROM users WHERE user_id=17 LIMIT 1'], $result['queries']);
    }

    public function testLoginHeaderPreservesPublicBrandAndLinks(): void
    {
        $result = self::renderHeader(true);

        self::assertStringContainsString('class="login-menu-toggle"', $result['html']);
        self::assertStringContainsString('class="login-vsosh-wordmark"', $result['html']);
        self::assertStringContainsString('href="https://vsoshlk.irro.ru"', $result['html']);
        self::assertStringNotContainsString('class="tmf-theme-toggle"', $result['html']);
        self::assertStringNotContainsString('class="tmf-user-panel"', $result['html']);
        self::assertSame([], $result['queries']);
    }

    /** @return array{html:string,queries:list<string>} */
    private static function renderHeader(bool $login): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_NEWLINE', "\n");
define('K_PATH_URL', 'https://example.test/');
define('K_ADMIN_LINK', 10);
define('K_OPENVSOSHCBT_SOURCE_URL', 'https://code.example.test/project');
define('K_TABLE_USERS', 'users');
$db = 'db';
$is_login_page = $argv[2] === '1';
$thispage_title = 'Dashboard <safe>';
$_SESSION = [
    'session_user_level' => $is_login_page ? 0 : 10,
    'session_user_id' => 17,
    'session_user_name' => 'student<1>',
    'session_user_firstname' => 'Ada%20Lovelace',
];
$l = [
    'a_meta_charset' => 'UTF-8', 'ov_open_menu' => 'Open menu',
    'ov_vsosh_name' => 'Olympiad', 'ov_vsosh_abbreviation_prefix' => 'VS',
    'ov_vsosh_abbreviation_suffix' => 'H', 'ov_vsosh_caption_line_1' => 'All',
    'ov_vsosh_caption_line_2' => 'school', 'ov_vsosh_caption_line_3' => 'contest',
    'ov_switch_theme' => 'Switch theme', 'ov_theme_dark' => 'Dark', 'w_user' => 'User',
    'w_jump_menu' => 'Jump menu', 'ov_close_menu' => 'Close menu',
    'ov_rcoko_alt' => 'Organisation logo', 'ov_testing_platform' => 'Testing platform',
    'ov_organization_name' => 'Organisation', 'ov_olympiad_results' => 'Results',
    'ov_about_platform' => 'About', 'ov_source_code' => 'Source', 'w_license' => 'License',
    'ov_user_information' => 'User information', 'ov_close' => 'Close', 'w_level' => 'Level',
    'w_username' => 'Username', 'w_name' => 'Name', 'w_logout' => 'Logout',
    'ov_logout_question' => 'Sign out',
];
$GLOBALS['queries'] = [];
function F_db_query($query, $db) {
    $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($query));
    return fopen('php://memory', 'r');
}
function F_db_fetch_array($result) { return ['user_schedule' => "Monday\nTuesday"]; }
function f_tmf_user_photo_path($id) { return sys_get_temp_dir() . '/missing-user-photo-' . $id; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = str_replace("include '../../shared/code/tce_page_timer.php';", "echo '[[TIMER]]';", $source);
$source = str_replace("require_once __DIR__ . '/tce_page_menu.php';", "echo '[[MENU]]';", $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/tce_page_header.php', $login ? '1' : '0'],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:list<string>} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
