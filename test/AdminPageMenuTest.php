<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminPageMenuTest extends TestCase
{
    public function testMenuStructureAndRoleMappingRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . '$source = preg_replace("/^require_once [^;]+;\\n/m", "", $source); '
                    . 'preg_match_all("/K_AUTH_[A-Z_]+/", $source, $constants); '
                    . 'foreach (array_unique($constants[0]) as $constant) { define($constant, 1); } '
                    . 'define("K_DATABASE_TYPE", "MYSQL"); define("K_NEWLINE", "\\n"); '
                    . 'preg_match_all("/\\[\'([a-z][a-z0-9_]*)\'\\]/", $source, $labels); '
                    . '$l = array_fill_keys(array_unique($labels[1]), "label"); '
                    . 'unset($l["ov_instance_settings"]); '
                    . '$_SESSION["session_user_level"] = 1; '
                    . 'function openvsosh_admin_required_level($script, $fallback) { '
                    . 'return $script === "tce_monitor.php" ? 7 : $fallback; } '
                    . 'function F_menu_link($link, $data, $level = 0) { '
                    . 'echo json_encode([$link, $data["level"], $data["enabled"], '
                    . 'array_keys($data["sub"] ?? []), $data["name"]]), "\\n"; } '
                    . 'eval("?>" . $source);',
                dirname(__DIR__) . '/admin/code/tce_page_menu.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringNotContainsString('Undefined array key', $output);
        self::assertStringStartsWith("<span id=\"menusection\"></span>\n<ul class=\"menu\">\n", $output);
        self::assertStringContainsString('["index.php",1,true,[],"label"]', $output);
        self::assertStringContainsString(
            '["tce_menu_tests.php",1,true,["tce_test_access_rules.php","tce_monitor.php",',
            $output,
        );
        self::assertStringContainsString(
            (string) json_encode(['tce_onboarding_settings.php', 1, true, [], 'Настройки системы']),
            $output,
        );
        self::assertStringContainsString('["tce_logout.php",1,true,[],"label"]', $output);
        self::assertStringContainsString('["tce_login.php",0,false,[],"label"]', $output);
        self::assertStringEndsWith("</ul>\n", $output);
    }
}
