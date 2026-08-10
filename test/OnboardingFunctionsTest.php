<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_onboarding.php';

final class OnboardingFunctionsTest extends TestCase
{
    public function testConfigurationHasNormalizedTestIdentifiers(): void
    {
        $config = \f_get_onboarding_config();

        self::assertSame(['instruction_test_id', 'demo_test_id'], array_keys($config));
        self::assertIsInt($config['instruction_test_id']);
        self::assertGreaterThanOrEqual(0, $config['instruction_test_id']);
        self::assertIsInt($config['demo_test_id']);
        self::assertGreaterThanOrEqual(0, $config['demo_test_id']);
    }

    public function testConfigurationSaveAndLoadNormalizeIdentifiers(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-onboarding-" . uniqid(); '
                    . 'mkdir($root . "/shared/code", 0700, true); mkdir($root . "/shared/config", 0700); '
                    . 'copy($argv[1], $root . "/shared/code/tce_functions_onboarding.php"); '
                    . 'require $root . "/shared/code/tce_functions_onboarding.php"; '
                    . '$saved = f_save_onboarding_config(-4, 17); $config = f_get_onboarding_config(); '
                    . 'unlink($root . "/shared/config/tce_onboarding.json"); '
                    . 'unlink($root . "/shared/code/tce_functions_onboarding.php"); '
                    . 'rmdir($root . "/shared/config"); rmdir($root . "/shared/code"); '
                    . 'rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode([$saved, $config]);',
                dirname(__DIR__) . '/shared/code/tce_functions_onboarding.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [true, ['instruction_test_id' => 0, 'demo_test_id' => 17]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testPendingTestsIncludeOnlyIncompleteConfiguredTests(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_TABLE_TEST_USER", "test_users"); '
                    . 'define("K_TABLE_TESTS", "tests"); $GLOBALS["db"] = "db-link"; '
                    . '$GLOBALS["l"] = ['
                    . '"ov_onboarding_instruction_eyebrow" => "First", '
                    . '"ov_onboarding_instruction_label" => "Instructions", '
                    . '"ov_onboarding_demo_eyebrow" => "Next", '
                    . '"ov_onboarding_demo_label" => "Demo"]; '
                    . 'function f_get_onboarding_config() { '
                    . 'return ["instruction_test_id" => 11, "demo_test_id" => 22]; } '
                    . 'function F_count_rows($table, $where) { $GLOBALS["counts"][] = [$table, $where]; '
                    . 'return str_contains($where, "testuser_test_id=22") ? 1 : 0; } '
                    . 'function F_db_query($sql, $db) { $GLOBALS["query"] = [$sql, $db]; return $sql; } '
                    . 'function F_db_fetch_array($result) { return ["test_id" => "11", "test_name" => "Welcome"]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "function f_get_pending_onboarding_tests"); '
                    . 'eval("namespace Harness; " . substr($source, $start)); '
                    . 'echo json_encode([f_get_pending_onboarding_tests(7), '
                    . '$GLOBALS["counts"], $GLOBALS["query"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_onboarding.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [[
                    'kind' => 'instruction',
                    'eyebrow' => 'First',
                    'label' => 'Instructions',
                    'test_id' => 11,
                    'test_name' => 'Welcome',
                ]],
                [
                    ['test_users', 'WHERE testuser_test_id=11 AND testuser_user_id=7 AND testuser_status>=4'],
                    ['test_users', 'WHERE testuser_test_id=22 AND testuser_user_id=7 AND testuser_status>=4'],
                ],
                ['SELECT test_id, test_name FROM tests WHERE test_id=11', 'db-link'],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testSettingsTestSelectorEscapesNamesAndSkipsInvalidRows(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; define("K_NEWLINE", "\\n"); '
                    . '$source = file_get_contents($argv[1]); '
                    . '$start = strpos($source, "function f_onboarding_test_select"); '
                    . '$end = strpos($source, "\\necho " . chr(39) . "<div class=" . chr(34) . "container", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . 'ob_start(); f_onboarding_test_select("demo_test_id", 22, ['
                    . '["test_id" => 11, "test_name" => "Welcome <all>"], "invalid", '
                    . '["test_id" => "22", "test_name" => "Demo & practice"]], "UTF-8"); '
                    . 'echo ob_get_clean();',
                dirname(__DIR__) . '/admin/code/tce_onboarding_settings.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            "<select name=\"demo_test_id\" id=\"demo_test_id\">\n"
                . "<option value=\"0\">— не назначен —</option>\n"
                . "<option value=\"11\">Welcome &lt;all&gt;</option>\n"
                . "<option value=\"22\" selected=\"selected\">Demo &amp; practice</option>\n"
                . "</select>\n",
            $output,
        );
    }
}
