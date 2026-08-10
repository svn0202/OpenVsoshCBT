<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class FileManagerControllerTest extends TestCase
{
    public function testDefaultAdminViewRendersUploadAndTable(): void
    {
        $result = self::runController('default');

        self::assertSame([], $result['operations']);
        self::assertStringContainsString('name="userfile"', $result['html']);
        self::assertStringContainsString('name="sendfile"', $result['html']);
        self::assertStringContainsString('<SUBMIT:viewmodev:Visual:Mode>', $result['html']);
        self::assertStringContainsString('<DIR-TABLE:', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
    }

    public function testRenameUsesSelectedDirectoryAndShowsRenamedFile(): void
    {
        $result = self::runController('rename');

        self::assertCount(1, $result['operations']);
        $operation = $result['operations'][0] ?? null;
        self::assertIsArray($operation);
        self::assertSame('rename', $operation[0] ?? null);
        self::assertStringEndsWith('/original.txt', $operation[1] ?? '');
        self::assertStringEndsWith('/renamed.txt', $operation[2] ?? '');
        self::assertStringContainsString('<MESSAGE:Renamed>', $result['html']);
        self::assertStringContainsString('value="renamed.txt"', $result['html']);
        self::assertStringContainsString('<PREVIEW:renamed.txt>', $result['html']);
    }

    /** @return array{html:string,operations:list<array<int,string>>} */
    private static function runController(string $mode): array
    {
        $script = <<<'PHP'
namespace Harness;
$root = sys_get_temp_dir() . '/tce-filemanager-controller-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700, true);
$root = realpath($root) . '/';
$original = $root . 'original.txt';
file_put_contents($original, 'content');
define('K_AUTH_ADMIN_FILEMANAGER', 10);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_AUTH_DELETE_MEDIAFILE', 8);
define('K_AUTH_RENAME_MEDIAFILE', 8);
define('K_AUTH_ADMIN_DIRS', 8);
define('K_PATH_CACHE', $root);
define('K_MAX_UPLOAD_SIZE', 1048576);
define('K_NEWLINE', "\n");
$l = [
    'a_meta_dir' => 'ltr', 'h_cancel' => 'Cancel', 'h_delete' => 'Delete',
    'h_upload_file' => 'Upload file', 'hp_filemanager' => 'Help',
    'm_authorization_denied' => 'Denied', 'm_delete_confirm' => 'Confirm',
    'm_delete_file_error' => 'Delete failed', 'm_deleted' => 'Deleted',
    'm_directory_create_error' => 'Directory failed', 'm_directory_created' => 'Directory created',
    'm_file_already_exist' => 'Exists', 'm_file_rename_error' => 'Rename failed',
    'm_file_renamed' => 'Renamed', 'm_form_missing_fields' => 'Missing', 'm_used_file' => 'Used',
    't_filemanager' => 'File manager', 'w_action' => 'Action', 'w_cancel' => 'Cancel',
    'w_create_directory' => 'Create directory', 'w_delete' => 'Delete', 'w_mode' => 'Mode',
    'w_name' => 'Name', 'w_new_directory' => 'New directory', 'w_position' => 'Position',
    'w_preview' => 'Preview', 'w_rename' => 'Rename', 'w_table' => 'Table',
    'w_upload_file' => 'Upload file', 'w_upload' => 'Upload', 'w_visual' => 'Visual',
];
$menu_mode = '';
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_filemanager.php';
$_SESSION = ['session_user_id' => 7, 'session_user_level' => 10];
$_FILES = [];
$_POST = [];
$_REQUEST = [];
if ($argv[2] === 'rename') {
    $_POST['rename'] = 'Rename';
    $_REQUEST = ['d' => $root, 'f' => $original, 'newname' => 'renamed.txt'];
}
$GLOBALS['operations'] = [];
function F_file_exists($path) { return file_exists($path); }
function f_get_authorized_dirs() { return [$GLOBALS['root'] ?? '']; }
function f_is_authorized_dir($path, $root, $authorized) { return is_string($path) && str_starts_with($path, $root); }
function f_form_option_is_selected($expected, $actual) { return (string) $expected === (string) $actual; }
function f_is_used_media_file($file) { return false; }
function f_delete_media_file($file) { return true; }
function f_rename_media_file($from, $to) { $GLOBALS['operations'][] = ['rename', $from, $to]; return true; }
function f_create_media_dir($dir) { return true; }
function f_delete_media_dir($dir) { return true; }
function f_get_file_info($file) {
    return ['tcename' => basename($file), 'extension' => 'txt', 'size' => 7, 'lastmod' => '2026-01-01'];
}
function F_objects_replacement($name, ...$arguments) { return '<PREVIEW:' . $name . '>'; }
function f_format_file_size($size) { return $size . ' B'; }
function f_get_media_dir_path_link($dir, $view) { return '<PATH:' . $dir . '>'; }
function f_get_dir_table($dir, ...$arguments) { return '<DIR-TABLE:' . $dir . '>'; }
function f_get_dir_visual_table($dir, ...$arguments) { return '<DIR-VISUAL:' . $dir . '>'; }
function F_print_error($type, $message, ...$arguments) { echo '<' . $type . ':' . $message . '>'; }
function F_submit_button($name, $value, $title, ...$arguments) { echo '<SUBMIT:' . $name . ':' . $value . ':' . $title . '>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$GLOBALS['root'] = $root;
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
@unlink($original);
@rmdir($root);
echo json_encode(['html' => $html, 'operations' => $GLOBALS['operations']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_filemanager.php', $mode],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,operations:list<array<int,string>>} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
