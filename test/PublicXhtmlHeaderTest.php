<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicXhtmlHeaderTest extends TestCase
{
    public function testLoginPageDefaultsRenderAppearanceBackgroundAndTranslations(): void
    {
        $html = self::renderHeader([]);

        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"ru\" dir=\"ltr\">\n", $html);
        self::assertStringContainsString('<title>Default &lt;Site&gt;</title>', $html);
        self::assertStringContainsString('<meta name="tcexam_level" content="0" />', $html);
        self::assertStringContainsString('Default description [security-key]', $html);
        self::assertStringContainsString('href="../styles/picoman.css?v=20260718-2"', $html);
        self::assertStringContainsString('navigator.serviceWorker.register("../sw.js",{scope:"../"})', $html);
        self::assertStringContainsString('<body class="login-page ui-font-system"', $html);
        self::assertStringContainsString('--login-background-position:center center;', $html);
        self::assertStringContainsString('--login-background-size:cover;', $html);
        self::assertStringContainsString('--login-background-overlay:0.35', $html);
        self::assertStringContainsString('data-open-menu="Open menu"', $html);
        self::assertStringContainsString('<a href="#maincontent" class="skiplink"', $html);
        self::assertStringNotContainsString('[[WARNING:', $html);
    }

    public function testExamPageUsesRtlThemeAppClassesAndLoginWarning(): void
    {
        $html = self::renderHeader([
            'direction' => 'rtl',
            'pagelevel' => 7,
            'title' => 'Custom exam',
            'script' => '/public/code/tce_test_execute.php',
            'user_level' => 3,
            'login_error' => true,
        ]);

        self::assertStringContainsString('<html lang="ru" dir="rtl">', $html);
        self::assertStringContainsString('<title>Custom exam</title>', $html);
        self::assertStringContainsString('<meta name="tcexam_level" content="7" />', $html);
        self::assertStringContainsString('href="../styles/picoman_rtl.css?v=20260718-2"', $html);
        self::assertStringContainsString('<body class="app-page theme-light ui-font-system exam-page"', $html);
        self::assertStringNotContainsString('--login-background-image:', $html);
        self::assertStringEndsWith("[[WARNING:Wrong login]]", $html);
    }

    /** @param array<string,int|string|bool> $settings */
    private static function renderHeader(array $settings): string
    {
        $script = <<<'PHP'
namespace Harness;
define('K_NEWLINE', "\n");
define('K_SITE_TITLE', 'Default <Site>');
define('K_SITE_DESCRIPTION', 'Default description');
define('K_SITE_AUTHOR', 'Default author');
define('K_SITE_REPLY', 'reply@example.test');
define('K_SITE_KEYWORDS', 'tests,public');
define('K_SITE_ICON', 'default.ico');
define('K_SITE_STYLE', '../styles/default.css');
define('K_SITE_STYLE_RTL', '../styles/default-rtl.css');
define('K_PATH_STYLE_SHEETS', '../styles/');
define('K_KEY_SECURITY', 'c2VjdXJpdHkta2V5');
$settings = json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$l = [
    'a_meta_dir' => $settings['direction'] ?? 'ltr', 'a_meta_language' => 'ru',
    'a_meta_charset' => 'UTF-8', 't_login_form' => 'Login',
    'ov_open_menu' => 'Open menu', 'ov_close_menu' => 'Close menu',
    'ov_show_password' => 'Show password', 'ov_hide_password' => 'Hide password',
    'ov_theme_dark' => 'Dark', 'ov_theme_light' => 'Light',
    'ov_enable_dark_theme' => 'Enable dark', 'ov_enable_light_theme' => 'Enable light',
    'w_skip_navigation' => 'Skip navigation', 'm_login_wrong' => 'Wrong login',
];
$_SERVER['SCRIPT_NAME'] = $settings['script'] ?? '/public/code/tce_login.php';
$_SESSION = ['session_user_level' => $settings['user_level'] ?? 0];
if (isset($settings['pagelevel'])) { $pagelevel = $settings['pagelevel']; }
if (isset($settings['title'])) { $thispage_title = $settings['title']; }
if (!empty($settings['login_error'])) { $GLOBALS['login_error'] = true; }
function openvsosh_get_appearance_settings() {
    return [
        'ui_font' => 'system', 'login_background_overlay' => 35,
        'login_background_position' => 'center center', 'login_background_size' => 'cover',
    ];
}
function openvsosh_site_asset_metadata($type) { return $type === 'background'; }
function F_print_error($type, $message) { echo "[[$type:$message]]"; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/public/code/tce_xhtml_header.php',
                base64_encode(json_encode($settings, JSON_THROW_ON_ERROR)),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        return $output;
    }
}
