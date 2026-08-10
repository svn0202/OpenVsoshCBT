<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminIndexControllerTest extends TestCase
{
    public function testDashboardWithoutLimitsKeepsCurrentCardsAndActions(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_INDEX', 1);
define('K_AUTH_ADMIN_USERS', 4);
define('K_AUTH_ADMIN_RESULTS', 5);
define('K_AUTH_ADMIN_TESTS', 6);
define('K_AUTH_ADMIN_IMPORT', 7);
define('K_AUTH_IMPORT_USERS', 8);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_TABLE_USERS', 'users');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_SESSIONS', 'sessions');
define('K_TABLE_TESTUSER_STAT', 'testuser_stat');
define('K_REMAINING_TESTS', false);
define('K_MAX_TESTS_DAY', false);
define('K_MAX_TESTS_MONTH', false);
define('K_MAX_TESTS_YEAR', false);
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_SECONDS_IN_DAY', 86400);
define('K_SECONDS_IN_MONTH', 2592000);
define('K_SECONDS_IN_YEAR', 31536000);
define('K_NEWLINE', "\n");
$l = ['a_meta_charset' => 'UTF-8', 'd_admin_index' => '<p>Dashboard help</p>'];
$_SESSION = ['session_user_level' => 10];
$GLOBALS['counts'] = ['users' => 12, 'questions' => 34, 'tests' => 5, 'sessions' => 2];
function F_count_rows($table, $where = '') { return $GLOBALS['counts'][$table] ?? 0; }
function f_menu_icon_svg($icon) { return '<ICON:' . $icon . '>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $pagelevel, $thispage_title], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/index.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string, int, string} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $pageLevel, $title] = $result;
        self::assertSame(1, $pageLevel);
        self::assertSame('Панель управления', $title);
        self::assertStringContainsString('<strong>12</strong><small>Участники</small>', $html);
        self::assertStringContainsString('<strong>34</strong><small>Вопросы</small>', $html);
        self::assertStringContainsString('<strong>5</strong><small>Испытания</small>', $html);
        self::assertStringContainsString('<strong>2</strong><small>Активные сессии</small>', $html);
        self::assertStringContainsString('href="tce_edit_test.php"', $html);
        self::assertStringContainsString('href="tce_onboarding_settings.php"', $html);
        self::assertStringContainsString('<section class="dashboard-guide"><h2>Справка администратора</h2><p>Dashboard help</p></section>', $html);
        self::assertStringNotContainsString('<table', $html);
    }
}
