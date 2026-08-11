<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AdminOfflineControllerTest extends TestCase
{
    public function testSelectedTestRendersAttemptsPackagesAndQueryContract(): void
    {
        $result = self::runController(false);

        self::assertStringContainsString('<option value="7" selected="selected">Algebra</option>', $result['html']);
        self::assertStringContainsString('<option value="31">Lovelace Ada (student)</option>', $result['html']);
        self::assertStringContainsString('<td>student</td><td>2026-08-10 10:00:00</td>', $result['html']);
        self::assertStringContainsString('tce_offline.php?test_id=7', $result['html']);
        self::assertSame('SELECT TESTS', $result['queries'][0] ?? null);
        self::assertStringContainsString('WHERE tu.testuser_test_id=7', $result['queries'][1] ?? '');
        self::assertStringContainsString('WHERE p.offline_test_id=7', $result['queries'][2] ?? '');
        self::assertNull($result['imported']);
    }

    public function testValidResultImportRendersAcceptedStatus(): void
    {
        $result = self::runController(true);

        self::assertSame('offline-result', $result['imported']);
        self::assertStringContainsString('Результат принят: imported', $result['html']);
        self::assertStringNotContainsString('Операция не выполнена', $result['html']);
    }

    public function testNonStringUploadPathIsRejectedWithoutImport(): void
    {
        $result = self::runController(false, true);

        self::assertNull($result['imported']);
        self::assertStringContainsString('Операция не выполнена: invalid_upload', $result['html']);
    }

    /**
     * @return array{html: string, queries: list<string>, imported: string|null}
     */
    private static function runController(bool $import, bool $malformedUpload = false): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_TESTS', 10);
define('K_TABLE_TEST_USER', 'test_users');
define('K_TABLE_USERS', 'users');
define('TMF_OFFLINE_MAX_RESULT_BYTES', 10000);
$db = 'db';
$l = ['m_authorization_denied' => 'Denied', 'a_meta_charset' => 'UTF-8'];
$_REQUEST = ['test_id' => '7'];
$_POST = [];
$_FILES = [];
$upload_path = null;
if ($argv[2] === '1') {
    $_POST = ['import_offline' => '1', 'csrf_token' => 'valid'];
    $upload_path = tempnam(sys_get_temp_dir(), 'offline-result-');
    file_put_contents($upload_path, 'offline-result');
    $_FILES = ['result_file' => ['error' => UPLOAD_ERR_OK, 'size' => 14, 'tmp_name' => $upload_path]];
} elseif ($argv[2] === '2') {
    $_POST = ['import_offline' => '1', 'csrf_token' => 'valid'];
    $_FILES = ['result_file' => ['error' => UPLOAD_ERR_OK, 'size' => 14, 'tmp_name' => ['nested']]];
}
$GLOBALS['queries'] = [];
$GLOBALS['kinds'] = [];
$GLOBALS['indexes'] = [];
$GLOBALS['captured_import'] = null;
function F_select_tests_sql() { return 'SELECT TESTS'; }
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    $kind = match (true) {
        $sql === 'SELECT TESTS' => 'tests',
        str_contains($sql, 'FROM test_users') => 'attempts',
        str_contains($sql, 'FROM offline_packages') => 'packages',
        default => 'empty',
    };
    $result = fopen('php://memory', 'r');
    $GLOBALS['kinds'][get_resource_id($result)] = $kind;
    $GLOBALS['indexes'][$kind] = 0;
    return $result;
}
function F_db_fetch_array($result) {
    $kind = $GLOBALS['kinds'][get_resource_id($result)];
    $rows = [
        'tests' => [['test_id' => 7, 'test_name' => 'Algebra']],
        'attempts' => [[
            'testuser_id' => 31, 'testuser_status' => 1, 'testuser_pregenerated' => 0,
            'user_name' => 'student', 'user_firstname' => 'Ada', 'user_lastname' => 'Lovelace',
        ]],
        'packages' => [[
            'user_name' => 'student', 'offline_issued_at' => '2026-08-10 10:00:00',
            'offline_expires_at' => '2026-08-10 12:00:00', 'offline_status' => 'issued',
        ]],
        'empty' => [],
    ];
    return $rows[$kind][$GLOBALS['indexes'][$kind]++] ?? false;
}
function check_csrf_token($token) { return $token === 'valid'; }
function is_uploaded_file($path) { return is_string($path) && $path !== ''; }
function F_tmf_offline_import($contents) { $GLOBALS['captured_import'] = $contents; return ['status' => 'imported']; }
function F_tmf_offline_issue($id) { return ['status' => 'not-used']; }
function F_tmf_offline_html($envelope) { return 'not-used'; }
function F_tmf_offline_table() { return 'offline_packages'; }
function f_openvsosh_admin_test_context($id, $section) { return "[[CONTEXT:$id:$section]]"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
function F_print_error($type, $message, $exit = false) { echo "[[$type:$message]]"; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
if (is_string($upload_path)) { unlink($upload_path); }
echo json_encode([
    'html' => $html, 'queries' => $GLOBALS['queries'], 'imported' => $GLOBALS['captured_import'],
], JSON_THROW_ON_ERROR);
PHP;

        $mode = '0';
        if ($import) {
            $mode = '1';
        }
        if ($malformedUpload) {
            $mode = '2';
        }

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/admin/code/tce_offline.php',
                $mode,
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html: string, queries: list<string>, imported: string|null} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
