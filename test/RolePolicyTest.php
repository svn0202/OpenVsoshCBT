<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_roles.php';

final class RolePolicyTest extends TestCase
{
    /** @return iterable<string,array{array<string,string>|false,bool}> */
    public static function defaultGroupRows(): iterable
    {
        yield 'default group' => [['group_name' => 'default'], true];
        yield 'ordinary group' => [['group_name' => 'teachers'], false];
        yield 'missing group' => [false, false];
    }

    /** @return iterable<string,array{bool}> */
    public static function queryOutcomes(): iterable
    {
        yield 'insert succeeds' => [true];
        yield 'insert fails' => [false];
    }

    /** @return array<string, array{string, int}> */
    public static function controllerLevels(): array
    {
        return [
            'observer' => ['tce_monitor.php', 5],
            'profile' => ['tce_self_profile.php', 5],
            'results' => ['tce_show_result_allusers.php', 6],
            'questions' => ['tce_edit_question.php', 7],
            'tests' => ['tce_edit_test.php', 8],
            'documents' => ['tce_import_omr_bulk.php', 9],
            'users' => ['tce_edit_user.php', 10],
            'settings' => ['tce_onboarding_settings.php', 10],
        ];
    }

    #[DataProvider('controllerLevels')]
    public function testControllerUsesExpectedCumulativeRole(string $script, int $expected): void
    {
        self::assertSame($expected, \openvsosh_admin_required_level($script, 1));
    }

    public function testUnknownControllerKeepsConfiguredFallback(): void
    {
        self::assertSame(4, \openvsosh_admin_required_level('custom_extension.php', 4));
    }

    /** @param array<string,string>|false $row */
    #[DataProvider('defaultGroupRows')]
    public function testDefaultGroupUsesFetchedName(array|false $row, bool $expected): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$GLOBALS["row"] = json_decode($argv[2], true); $GLOBALS["db"] = "db-link"; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["query"] = [$sql, $db]; return "result"; } '
                    . 'function F_db_fetch_array($result) { return $GLOBALS["row"]; } '
                    . 'define("K_TABLE_GROUPS", "groups"); require $argv[1]; '
                    . 'echo json_encode([openvsosh_is_default_group(7), $GLOBALS["query"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_roles.php',
                json_encode($row, JSON_THROW_ON_ERROR),
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [$expected, ['SELECT group_name FROM groups WHERE group_id=7 LIMIT 1', 'db-link']],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    #[DataProvider('queryOutcomes')]
    public function testEnsureAdminDefaultGroupReturnsQueryOutcome(bool $queryOutcome): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$GLOBALS["outcome"] = $argv[2] === "1"; $GLOBALS["db"] = "db-link"; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["query"] = [$sql, $db]; '
                    . 'return $GLOBALS["outcome"]; } '
                    . 'define("K_TABLE_USERGROUP", "user_groups"); define("K_TABLE_USERS", "users"); '
                    . 'define("K_TABLE_GROUPS", "groups"); require $argv[1]; '
                    . 'echo json_encode([openvsosh_ensure_admin_default_group(17), $GLOBALS["query"]]);',
                dirname(__DIR__) . '/shared/code/tce_functions_roles.php',
                $queryOutcome ? '1' : '0',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{bool,array{string,string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$actual, $query] = $decoded;
        self::assertSame($queryOutcome, $actual);
        self::assertSame('db-link', $query[1]);
        self::assertStringContainsString('INSERT INTO user_groups', $query[0]);
        self::assertStringContainsString('u.user_id=17', $query[0]);
        self::assertStringContainsString("g.group_name='default'", $query[0]);
    }
}
