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
