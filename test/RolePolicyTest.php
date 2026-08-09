<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_roles.php';

final class RolePolicyTest extends TestCase
{
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
}
