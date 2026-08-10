<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class SharedPageUserbarTest extends TestCase
{
    public function testAuthorizedUserBarPreservesLogoutAndLanguageSelector(): void
    {
        $html = self::renderUserbar(4, '/public/code/index.php');

        self::assertStringContainsString('User: student&lt;admin&gt;', $html);
        self::assertStringContainsString('class="logoutbutton"', $html);
        self::assertStringContainsString('<span class="selected" title="Русский" aria-current="true">RU</span>', $html);
        self::assertStringContainsString(
            '<a href="/public/code/index.php?lang=en" class="langselector" title="English">EN</a>',
            $html,
        );
        self::assertStringContainsString('href="#timersection" accesskey="3"', $html);
        self::assertStringContainsString('href="https://code.example.test/project"', $html);
        self::assertStringContainsString('TCExam ver. 9.9', $html);
        self::assertStringContainsString('Support &amp; service: olymp@gia66.ru', $html);
    }

    public function testGuestExecutionPagePreservesLoginAndHidesLanguageSelector(): void
    {
        $html = self::renderUserbar(0, '/public/code/tce_test_execute.php');

        self::assertStringContainsString('class="loginbutton"', $html);
        self::assertStringNotContainsString('class="logoutbutton"', $html);
        self::assertStringNotContainsString('class="langselector"', $html);
    }

    private static function renderUserbar(int $level, string $scriptName): string
    {
        $script = <<<'PHP'
namespace Harness;
define('K_NEWLINE', "\n");
define('K_LANGUAGE_SELECTOR', true);
define('K_AVAILABLE_LANGUAGES', serialize(['ru' => 'Русский', 'en' => 'English']));
define('K_USER_LANG', 'ru');
define('K_OPENVSOSHCBT_SOURCE_URL', 'https://code.example.test/project');
define('K_TCEXAM_VERSION', '9.9');
define('K_PATH_URL', 'https://example.test/');
$_SESSION = ['session_user_level' => (int) $argv[2], 'session_user_name' => 'student<admin>'];
$_SERVER['SCRIPT_NAME'] = $argv[3];
$l = [
    'w_jump_timer' => 'Jump timer', 'w_jump_menu' => 'Jump menu',
    'h_user_info' => 'User information', 'w_user' => 'User',
    'h_logout_link' => 'Logout link', 'w_logout' => 'Logout',
    'h_login_button' => 'Login button', 'w_login' => 'Login',
    'w_language' => 'Language', 'a_meta_charset' => 'UTF-8',
    'ov_institution_copyright' => 'Institution',
    'ov_support_service' => 'Support & service', 'ov_based_on' => 'Based on',
    'ov_no_warranty' => 'No warranty',
];
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
eval('namespace Harness; ' . $source);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/shared/code/tce_page_userbar.php',
                (string) $level,
                $scriptName,
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        return $output;
    }
}
