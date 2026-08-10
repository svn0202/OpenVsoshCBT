<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EditBackupControllerTest extends TestCase
{
    public function testBackupListKeepsValidationSortingAndFormContract(): void
    {
        $result = self::runController(false);

        $newer = strpos($result['html'], '20260810130000_tcexam_backup.sql.gz');
        $older = strpos($result['html'], '20260810120000_tcexam_backup.sql.gz');
        self::assertIsInt($newer);
        self::assertIsInt($older);
        self::assertLessThan($older, $newer);
        self::assertStringNotContainsString('../invalid.sql.gz', $result['html']);
        self::assertStringContainsString('<button name="backup">Backup</button>', $result['html']);
        self::assertStringContainsString('<button name="restore">Restore</button>', $result['html']);
        self::assertStringContainsString('<button name="download">Download</button>', $result['html']);
        self::assertSame([], $result['calls']);
    }

    public function testForceRestoreCreatesSafetyBackupBeforeRestore(): void
    {
        $result = self::runController(true);

        self::assertSame([
            ['config'],
            ['create', ['driver' => 'pgsql'], '/backups/'],
            ['resolve', '/backups/', '20260810130000_tcexam_backup.sql.gz'],
            ['restore', ['driver' => 'pgsql'], '/backups/20260810130000_tcexam_backup.sql.gz'],
        ], $result['calls']);
        self::assertStringContainsString(
            '[[MESSAGE:Restore completed: 20260810130000_tcexam_backup.sql.gz]]',
            $result['html'],
        );
    }

    /** @return array{html: string, calls: list<list<mixed>>} */
    private static function runController(bool $restore): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_BACKUP', 10);
define('K_NEWLINE', "\n");
define('K_PATH_BACKUP', '/backups/');
define('K_DOWNLOAD_BACKUPS', true);
$l = [
    't_backup_editor' => 'Backup editor', 'm_restore_confirm' => 'Confirm restore',
    'w_restore' => 'Restore', 'h_restore' => 'Restore help', 'w_cancel' => 'Cancel',
    'h_cancel' => 'Cancel help', 'm_restore_completed' => 'Restore completed',
    'a_meta_charset' => 'UTF-8', 'm_backup_completed' => 'Backup completed',
    'w_backup_file' => 'Backup file', 'w_backup' => 'Backup', 'h_backup' => 'Backup help',
    'w_download' => 'Download', 'h_download' => 'Download help',
    'hp_edit_backups' => 'Backup page help',
];
$_REQUEST = [];
$_POST = [];
if ($argv[2] === '1') {
    $_REQUEST = ['backup_file' => '20260810130000_tcexam_backup.sql.gz'];
    $_POST = ['forcerestore' => 'Restore'];
}
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_edit_backup.php';
$GLOBALS['calls'] = [];
$GLOBALS['directory_entries'] = [
    '.', '..', '../invalid.sql.gz',
    '20260810120000_tcexam_backup.sql.gz', '20260810130000_tcexam_backup.sql.gz',
];
$GLOBALS['directory_index'] = 0;
class TmfBackupException extends \RuntimeException {}
function F_tmf_backup_file_is_valid($file) {
    return is_string($file) && preg_match('/^\d{14}_tcexam_backup\.(?:sql|tar)\.gz$/', $file) === 1;
}
function F_tmf_backup_config_from_constants() { $GLOBALS['calls'][] = ['config']; return ['driver' => 'pgsql']; }
function F_tmf_backup_create($config, $path) { $GLOBALS['calls'][] = ['create', $config, $path]; return $path . 'safety.sql.gz'; }
function F_tmf_backup_resolve_file($path, $file) { $GLOBALS['calls'][] = ['resolve', $path, $file]; return $path . $file; }
function F_tmf_backup_restore($config, $path) { $GLOBALS['calls'][] = ['restore', $config, $path]; }
function F_print_error($type, $message, $exit = false) { echo "[[$type:$message]]"; }
function F_submit_button($name, $label, $help) { echo '<button name="' . $name . '">' . $label . '</button>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
function get_form_noscript_select($name) { return '[[NOSCRIPT:' . $name . ']]'; }
function opendir($path) { return new \stdClass(); }
function readdir($handle) { return $GLOBALS['directory_entries'][$GLOBALS['directory_index']++] ?? false; }
function closedir($handle) { return true; }
function is_file($path) { return true; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'calls' => $GLOBALS['calls']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/admin/code/tce_edit_backup.php',
                $restore ? '1' : '0',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html: string, calls: list<list<mixed>>} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
