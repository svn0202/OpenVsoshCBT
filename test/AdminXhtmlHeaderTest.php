<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminXhtmlHeaderTest extends TestCase
{
    public function testDefaultsRenderVersionedAdminResourcesAndAppearanceClasses(): void
    {
        $html = self::renderHeader([]);

        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"ru\" dir=\"ltr\">\n", $html);
        self::assertStringContainsString('<title>Default &lt;Admin&gt;</title>', $html);
        self::assertStringContainsString('<meta name="tcexam_level" content="0" />', $html);
        self::assertStringContainsString('Default description [security-key]', $html);
        self::assertStringContainsString('href="../styles/default.css?v=123"', $html);
        self::assertStringContainsString('href="../styles/admin-responsive.css?v=123"', $html);
        self::assertStringContainsString('src="../jscripts/admin-navigation.js?v=123"', $html);
        self::assertStringContainsString('src="../jscripts/rich-content-editor.js?v=123"', $html);
        self::assertStringContainsString(
            '<body class="admin-page admin-palette-night admin-density-compact ui-font-system">',
            $html,
        );
        self::assertStringContainsString('<a href="#maincontent" class="skiplink"', $html);
        self::assertStringNotContainsString('[[WARNING:', $html);
    }

    public function testProvidedValuesAndLoginErrorKeepRenderedContract(): void
    {
        $html = self::renderHeader([
            'pagelevel' => 7,
            'thispage_title' => 'Custom title',
            'thispage_style' => '../styles/custom.css?theme=dark',
            'thispage_icon' => 'custom.ico',
            'direction' => 'rtl',
            'login_error' => true,
        ]);

        self::assertStringContainsString('<html lang="ru" dir="rtl">', $html);
        self::assertStringContainsString('<title>Custom title</title>', $html);
        self::assertStringContainsString('<meta name="tcexam_level" content="7" />', $html);
        self::assertStringContainsString('href="../styles/custom.css?theme=dark&amp;amp;v=123"', $html);
        self::assertStringContainsString('<link rel="icon" href="custom.ico" />', $html);
        self::assertStringEndsWith("[[WARNING:Wrong login]]", $html);
    }

    /** @param array<string, int|string|bool> $settings */
    private static function renderHeader(array $settings): string
    {
        $script = <<<'PHP'
namespace Harness;
define('K_NEWLINE', "\n");
define('K_TCEXAM_TITLE', 'Default <Admin>');
define('K_TCEXAM_DESCRIPTION', 'Default description');
define('K_TCEXAM_AUTHOR', 'Default author');
define('K_TCEXAM_REPLY_TO', 'admin@example.test');
define('K_TCEXAM_KEYWORDS', 'tests,admin');
define('K_TCEXAM_ICON', 'default.ico');
define('K_TCEXAM_STYLE', '../styles/default.css');
define('K_TCEXAM_STYLE_RTL', '../styles/default-rtl.css');
define('K_KEY_SECURITY', 'c2VjdXJpdHkta2V5');
$settings = json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$l = [
    'a_meta_dir' => $settings['direction'] ?? 'ltr', 'a_meta_language' => 'ru',
    'a_meta_charset' => 'UTF-8', 'w_skip_navigation' => 'Skip navigation',
    'm_login_wrong' => 'Wrong login',
];
if (isset($settings['pagelevel'])) { $pagelevel = $settings['pagelevel']; }
if (isset($settings['thispage_title'])) { $thispage_title = $settings['thispage_title']; }
if (isset($settings['thispage_style'])) { $thispage_style = $settings['thispage_style']; }
if (isset($settings['thispage_icon'])) { $thispage_icon = $settings['thispage_icon']; }
if (!empty($settings['login_error'])) { $GLOBALS['login_error'] = true; }
function realpath($path) { return '/virtual/' . basename($path); }
function is_file($path) { return true; }
function filemtime($path) { return 123; }
function openvsosh_get_appearance_settings() {
    return ['admin_palette' => 'night', 'admin_density' => 'compact', 'ui_font' => 'system'];
}
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
                dirname(__DIR__) . '/admin/code/tce_xhtml_header.php',
                base64_encode(json_encode($settings, JSON_THROW_ON_ERROR)),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        return $output;
    }
}
