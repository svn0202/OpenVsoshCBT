<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class SelectMediaFileControllerTest extends TestCase
{
    public function testVisualModeKeepsSanitizedCallerAndDirectoryArguments(): void
    {
        $cache = sys_get_temp_dir() . '/' . uniqid('openvsosh-media-select-', true);
        mkdir($cache, 0o700);

        try {
            $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_FILEMANAGER', 8);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_AUTH_DELETE_MEDIAFILE', 7);
define('K_AUTH_RENAME_MEDIAFILE', 6);
define('K_AUTH_ADMIN_DIRS', 9);
define('K_NEWLINE', "\n");
define('K_PATH_CACHE', $argv[2] . '/');
define('K_PATH_SHARED_JSCRIPTS', '/shared/js/');
define('K_MAX_UPLOAD_SIZE', 1000);
$l = [
    't_select_media_file' => 'Select media', 'm_directory_create_error' => 'Directory error',
    'm_authorization_denied' => 'Denied', 'm_delete_confirm' => 'Confirm delete', 'w_delete' => 'Delete',
    'h_delete' => 'Delete file', 'w_cancel' => 'Cancel', 'h_cancel' => 'Cancel', 'm_used_file' => 'Used',
    'm_deleted' => 'Deleted', 'm_delete_file_error' => 'Delete error', 'm_form_missing_fields' => 'Missing',
    'm_file_already_exist' => 'Exists', 'm_file_renamed' => 'Renamed', 'm_file_rename_error' => 'Rename error',
    'm_directory_created' => 'Created', 'w_action' => 'Action', 'w_preview' => 'Preview', 'w_name' => 'Name',
    'w_rename' => 'Rename', 'w_width' => 'Width', 'w_height' => 'Height', 'w_description' => 'Description',
    'h_object_width' => 'Object width', 'h_object_height' => 'Object height', 'w_add' => 'Add',
    'h_add_object' => 'Add object', 'w_upload_file' => 'Upload file', 'h_upload_file' => 'Upload',
    'w_upload' => 'Upload', 'a_meta_dir' => 'ltr', 'w_mode' => 'Mode', 'w_visual' => 'Visual',
    'w_table' => 'Table', 'w_position' => 'Position', 'w_new_directory' => 'New directory',
    'w_create_directory' => 'Create directory', 'hp_select_media_file' => 'Media help',
];
$menu_mode = '';
$_SESSION = ['session_user_level' => 10, 'session_user_id' => 7];
$_REQUEST = ['frm' => 'form-1<script>', 'fld' => 'field-2[]', 'd' => $argv[2]];
$_POST = [];
$_FILES = [];
$GLOBALS['visual_arguments'] = null;
function F_file_exists($path) { return file_exists($path); }
function f_get_authorized_dirs() { return ['/allowed']; }
function f_is_authorized_dir($path, ...$arguments) { return !str_contains((string) $path, '/admin/code/'); }
function F_print_error($type, $message) { echo "<$type:$message>"; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
function f_form_option_is_selected($expected, $actual) { return (string) $expected === (string) $actual; }
function f_get_media_dir_path_link($dir, $viewmode) { return '<PATH:' . $dir . ':' . (int) $viewmode . '>'; }
function f_get_dir_visual_table(...$arguments) { $GLOBALS['visual_arguments'] = $arguments; return '<VISUAL>'; }
function f_get_dir_table(...$arguments) { return '<TABLE>'; }
function f_is_used_media_file($file) { return false; }
function f_delete_media_file($file) { return true; }
function f_rename_media_file($from, $to) { return true; }
function f_create_media_dir($dir) { return true; }
function f_delete_media_dir($dir) { return true; }
function f_get_file_info($file) { return []; }
function F_objects_replacement(...$arguments) { return '<OBJECT>'; }
function f_format_file_size($size) { return (string) $size; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'visualArguments' => $GLOBALS['visual_arguments']], JSON_THROW_ON_ERROR);
PHP;

            [$status, $output] = \F_tcecode_run_process(
                [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_select_mediafile.php', $cache],
                dirname(__DIR__) . '/admin/code',
            );

            self::assertSame(0, $status, $output);
            self::assertJson($output, $output);
            /** @var array{html:string,visualArguments:array{string,string,string,string,array{0:string}}} $result */
            $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $directory = self::existingPath($cache) . '/';
            self::assertStringContainsString('name="frm" id="frm" value="form1script"', $result['html']);
            self::assertStringContainsString('name="fld" id="fld" value="field2"', $result['html']);
            self::assertStringContainsString('<PATH:' . $directory . ':0>', $result['html']);
            self::assertStringContainsString('<VISUAL>', $result['html']);
            self::assertStringContainsString('<SUBMIT:newdir:Create directory:New directory>', $result['html']);
            self::assertStringContainsString('<SUBMIT:deldir:Delete:Delete>', $result['html']);
            self::assertStringContainsString('<CSRF>', $result['html']);
            self::assertSame($directory, $result['visualArguments'][0]);
            self::assertSame('', $result['visualArguments'][1]);
            self::assertSame('&amp;frm=form1script&amp;fld=field2', $result['visualArguments'][2]);
            self::assertSame($cache . '/', $result['visualArguments'][3]);
            self::assertSame(['/allowed'], $result['visualArguments'][4]);
        } finally {
            rmdir($cache);
        }
    }

    private static function existingPath(string $path): string
    {
        $resolved = realpath($path);
        self::assertIsString($resolved);
        return $resolved;
    }
}
