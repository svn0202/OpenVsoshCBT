<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PublicIndexControllerTest extends TestCase
{
    public function testSessionReferenceIsBoundAfterAuthorizationStartsTheSession(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../public/code/index.php');
        $authorization = strpos($source, "require_once '../../shared/code/tce_authorization.php';");
        $sessionBinding = strpos($source, '$session = &$_SESSION;');

        self::assertNotFalse($authorization);
        self::assertNotFalse($sessionBinding);
        self::assertGreaterThan($authorization, $sessionBinding);
    }

    public function testCatalogPreservesWelcomeOnboardingTranslationsAndFlashMessage(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_PUBLIC_INDEX', 1);
define('K_NEWLINE', "\n");
$_SESSION = ['session_user_id' => 17, 'session_test_completion_message' => "Done <safe>\nNext"];
$l = [
    't_test_list' => 'Tests', 'hp_public_index' => '<p>Help</p>', 'a_meta_charset' => 'UTF-8',
    'ov_catalog_welcome_title' => 'Fallback title', 'ov_catalog_welcome_text' => 'Fallback welcome',
    'ov_onboarding_kicker' => 'Start', 'ov_onboarding_title' => 'Introduction',
    'ov_onboarding_description' => 'Complete these first', 'ov_catalog_search_label' => 'Search',
    'ov_catalog_search_placeholder' => 'Find a test', 'a_meta_language' => 'ru_RU',
    'ov_status_available' => 'Available', 'ov_status_progress' => 'In progress',
    'ov_status_repeat' => 'Repeat', 'ov_status_upcoming' => 'Upcoming', 'ov_status_closed' => 'Closed',
    'ov_test_unavailable' => 'Unavailable', 'ov_section_active' => 'Active',
    'ov_section_active_description' => 'Active tests', 'ov_section_future' => 'Future',
    'ov_section_future_description' => 'Future tests', 'ov_section_past' => 'Past',
    'ov_section_past_description' => 'Past tests', 'ov_test_count' => '%d tests',
    'ov_search_count' => '%d found',
];
function f_get_pending_onboarding_tests($userId) {
    $GLOBALS['onboarding_user'] = $userId;
    return [[
        'test_id' => 5, 'eyebrow' => 'Step <1>', 'label' => 'Rules & demo',
    ]];
}
function openvsosh_get_site_settings() {
    return ['site_name' => 'Olympiad <2026>', 'welcome' => "Welcome & learn\nCarefully"];
}
function f_get_user_tests() { return '<table class="testlist"><tbody></tbody></table>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([
    'html' => $html,
    'session' => $_SESSION,
    'onboarding_user' => $GLOBALS['onboarding_user'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/public/code/index.php'],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,session:array{session_user_id:int},onboarding_user:int} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(17, $result['onboarding_user']);
        self::assertSame(['session_user_id' => 17], $result['session']);
        self::assertStringContainsString("Done &lt;safe&gt;<br />\nNext", $result['html']);
        self::assertStringContainsString('<p>Olympiad &lt;2026&gt;</p>', $result['html']);
        self::assertStringContainsString("Welcome &amp; learn<br />\nCarefully", $result['html']);
        self::assertStringContainsString('data-onboarding-test="5"', $result['html']);
        self::assertStringContainsString('Step &lt;1&gt;', $result['html']);
        self::assertStringContainsString('Rules &amp; demo', $result['html']);
        self::assertStringContainsString('<table class="testlist"><tbody></tbody></table>', $result['html']);
        self::assertStringContainsString('"locale":"ru-ru"', $result['html']);
        self::assertStringContainsString('<div class="pagehelp"><p>Help</p></div>', $result['html']);
    }
}
