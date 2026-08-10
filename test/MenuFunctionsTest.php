<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_menu.php';

final class MenuFunctionsTest extends TestCase
{
    private string $originalScriptName;

    protected function setUp(): void
    {
        $this->originalScriptName = $_SERVER['SCRIPT_NAME'];
        $_SERVER['SCRIPT_NAME'] = '/public/code/current.php';
    }

    protected function tearDown(): void
    {
        $_SERVER['SCRIPT_NAME'] = $this->originalScriptName;
    }

    public function testMenuLinkRendersEnabledItem(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_menu.php"; '
                    . '$GLOBALS["l"] = ["a_meta_charset" => "UTF-8"]; '
                    . '$_SESSION["session_user_level"] = 0; $_SERVER["SCRIPT_NAME"] = "/current.php"; '
                    . 'echo F_menu_link("other.php", ['
                    . '"enabled" => true, "level" => 0, "link" => "/other.php", '
                    . '"title" => "Other", "name" => "Other", "key" => "", "icon" => ""]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame(
            '<li><a href="/other.php" title="Other"><span class="menu-label">Other</span></a></li>' . "\n",
            $output,
        );
    }

    public function testMenuLinkPreservesActiveSubmenuAndAccessRules(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_menu.php"; '
                    . '$GLOBALS["l"] = ["a_meta_charset" => "UTF-8"]; '
                    . '$_SESSION["session_user_level"] = 5; $_SERVER["SCRIPT_NAME"] = "/current.php"; '
                    . '$active = F_menu_link("current.php", ['
                    . '"enabled" => true, "level" => 1, "link" => "/current.php?x=1&y=2", '
                    . '"title" => "Dashboard", "name" => "Home", "key" => "", "icon" => "", '
                    . '"sub" => ["child.php" => ["enabled" => true, "level" => 1, '
                    . '"link" => "/child.php", "title" => "Child", "name" => "Child", '
                    . '"key" => "c", "icon" => ""]]], 1); '
                    . '$disabled = F_menu_link("hidden.php", ["enabled" => true, "level" => 6, '
                    . '"link" => "/hidden.php", "title" => "Hidden", "name" => "Hidden"]); '
                    . 'echo json_encode([$active, $disabled]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                '<li><span class="active" data-menu-link="/current.php?x=1&amp;y=2" aria-current="page">'
                    . '<span class="menu-label">Home<small class="menu-description">Dashboard</small>'
                    . '</span></span>' . "\n<ul>\n"
                    . '<li><a href="/child.php" title="Child" accesskey="c">'
                    . '<span class="menu-label">Child</span></a></li>' . "\n</ul>\n</li>\n",
                null,
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testChildActivityRecognizesDirectChild(): void
    {
        $menu = [
            'sub' => [
                'current.php' => [],
            ],
        ];

        self::assertTrue(f_menu_is_child_active($menu));
    }

    public function testChildActivityRecognizesNestedChild(): void
    {
        $menu = [
            'sub' => [
                'section.php' => [
                    'sub' => [
                        'current.php' => [],
                    ],
                ],
            ],
        ];

        self::assertTrue(f_menu_is_child_active($menu));
    }

    public function testChildActivityRejectsUnrelatedTree(): void
    {
        $menu = [
            'sub' => [
                'section.php' => [
                    'sub' => [
                        'other.php' => [],
                    ],
                ],
            ],
        ];

        self::assertFalse(f_menu_is_child_active($menu));
    }
}
