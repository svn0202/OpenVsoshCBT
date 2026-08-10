<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class PregenerateEndpointTest extends TestCase
{
    public function testPregenerationPagePreservesSelectedTestSummary(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_AUTH_ADMIN_TESTS', 7);
define('K_TABLE_TEST_USER', 'test_users');
define('TMF_PREGENERATION_BATCH_MAX', 25);
define('K_NEWLINE', "\n");
$db = 'db-link';
$l = ['m_authorization_denied' => 'Denied', 'a_meta_charset' => 'UTF-8'];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [
    'tests-result' => [['test_id' => '17', 'test_name' => 'Alpha & Beta'], false],
    'counts-result' => [
        ['testuser_user_id' => '101', 'testuser_pregenerated' => '1'],
        ['testuser_user_id' => '102', 'testuser_pregenerated' => '0'],
        ['testuser_user_id' => '999', 'testuser_pregenerated' => '1'],
        false,
    ],
];
function F_select_tests_sql(): string { return 'TESTS SQL'; }
function F_db_query($sql, $db)
{
    $GLOBALS['queries'][] = [preg_replace('/\s+/', ' ', trim($sql)), $db];
    return $sql === 'TESTS SQL' ? 'tests-result' : 'counts-result';
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][$result]); }
function F_tmf_pregeneration_eligible_users(int $testId): array
{
    $GLOBALS['eligible_test_id'] = $testId;
    return [101, 102, 103];
}
function f_get_boolean($value): bool { return (string) $value === '1'; }
function f_openvsosh_admin_test_context(int $testId, string $section): string
{
    return '<CONTEXT:' . $testId . ':' . $section . '>';
}
function f_get_csrf_token_field(): string { return '<CSRF>'; }
function F_print_error(...$arguments): void { $GLOBALS['error'] = $arguments; }
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-pregenerate-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/admin/code/tce_pregenerate.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'foreach (["tce_authorization.php", "tce_functions_form.php", '
                    . '"tce_functions_auth_sql.php", "tce_functions_test.php"] as $file) '
                    . '{ file_put_contents($root . "/shared/code/" . $file, "<?php"); } '
                    . 'file_put_contents($root . "/admin/code/tce_page_header.php", "<?php echo \\"<HEADER>\\\\n\\";"); '
                    . 'file_put_contents($root . "/admin/code/tce_page_footer.php", "<?php echo \\"<FOOTER>\\\\n\\";"); '
                    . '$_REQUEST = ["test_id" => "17"]; $_POST = []; chdir($root . "/admin/code"); '
                    . 'ob_start(); require "tce_pregenerate.php"; $page = ob_get_clean(); '
                    . '$result = [$page, $GLOBALS["queries"], $GLOBALS["eligible_test_id"], '
                    . '$test_id, $thispage_title, isset($GLOBALS["error"])]; '
                    . 'foreach (["/admin/code/tce_pregenerate.php", "/admin/code/tce_page_header.php", '
                    . '"/admin/code/tce_page_footer.php", "/admin/config/tce_config.php", '
                    . '"/shared/code/tce_authorization.php", "/shared/code/tce_functions_form.php", '
                    . '"/shared/code/tce_functions_auth_sql.php", "/shared/code/tce_functions_test.php"] as $file) '
                    . '{ unlink($root . $file); } rmdir($root . "/admin/code"); rmdir($root . "/admin/config"); '
                    . 'rmdir($root . "/admin"); rmdir($root . "/shared/code"); rmdir($root . "/shared"); '
                    . 'rmdir($root); echo json_encode($result);',
                dirname(__DIR__) . '/admin/code/tce_pregenerate.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: string,
         *     1: array{array{string, string}, array{string, string}},
         *     2: int,
         *     3: int,
         *     4: string,
         *     5: bool
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('<HEADER>' . "\n<div class=\"monitor-panel\">\n", $decoded[0]);
        self::assertStringContainsString('<CONTEXT:17:generation>', $decoded[0]);
        self::assertStringContainsString(
            '<option value="17" selected="selected">Alpha &amp; Beta</option>',
            $decoded[0],
        );
        self::assertStringContainsString('<strong>3</strong><small>Всего участников</small>', $decoded[0]);
        self::assertStringContainsString('<strong>1</strong><small>Подготовлено</small>', $decoded[0]);
        self::assertStringContainsString('<strong>1</strong><small>Уже начали</small>', $decoded[0]);
        self::assertStringContainsString('<strong>1</strong><small>Ожидают генерации</small>', $decoded[0]);
        self::assertStringContainsString('<CSRF><button type="submit" name="pregenerate" value="1">', $decoded[0]);
        self::assertStringNotContainsString('monitor-message', $decoded[0]);
        self::assertStringContainsString('<FOOTER>' . "\n", $decoded[0]);
        self::assertSame(['TESTS SQL', 'db-link'], $decoded[1][0]);
        self::assertStringContainsString('FROM test_users WHERE testuser_test_id=17', $decoded[1][1][0]);
        self::assertSame('db-link', $decoded[1][1][1]);
        self::assertSame(17, $decoded[2]);
        self::assertSame(17, $decoded[3]);
        self::assertSame('Предварительная генерация вариантов', $decoded[4]);
        self::assertFalse($decoded[5]);
    }
}
