<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class ImportOmrBulkControllerTest extends TestCase
{
    public function testSuccessfulSheetImportAndFormRenderingRemainUnchanged(): void
    {
        $cache = sys_get_temp_dir() . '/tce-omr-' . uniqid('', true);
        mkdir($cache . '/OMR', 0o777, true);
        file_put_contents($cache . '/OMR/OMR_REG42_QR.png', 'qr');
        file_put_contents($cache . '/OMR/OMR_REG42_A1.png', 'answer');

        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_OMR_IMPORT', 9);
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_PATH_CACHE', $argv[2] . '/');
define('K_NEWLINE', "\n");
$l = [
    't_omr_bulk_importer' => 'OMR importer', 'm_omr_wrong_test_data' => 'Wrong test data',
    'm_omr_wrong_answer_sheet' => 'Wrong answer sheet', 'm_import_ok' => 'Imported',
    't_result_user' => 'User result', 'w_results' => 'Results', 'm_import_error' => 'Import error',
    'w_select' => 'Select', 'w_date' => 'Date', 'w_datetime_format' => 'YYYY-MM-DD HH:MM:SS',
    'w_omr_dir' => 'OMR directory', 'h_omr_dir' => 'Choose directory', 'w_overwrite' => 'Overwrite',
    'h_omr_overwrite' => 'Replace answers', 'w_upload' => 'Upload', 'h_submit_file' => 'Submit',
    'hp_omr_bulk_importer' => 'OMR help',
];
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_import_omr_bulk.php';
$_REQUEST = ['date' => '2026-08-10 12:34:56', 'overwrite' => '1'];
$menu_mode = 'upload';
$GLOBALS['messages'] = [];
$GLOBALS['imported'] = null;
function f_get_boolean($value) { return $value === '1'; }
function F_file_exists($path) { return file_exists($path); }
function f_omr_open_dir_silently($path) { return opendir($path); }
function f_decode_omr_test_data_qr_code($filename) { return [7, ['question' => 11]]; }
function f_decode_omr_page($filename) { return [1 => 2]; }
function f_get_uid_from_regnum($registration) { return $registration === 'REG42' ? 31 : 0; }
function f_is_authorized_editor_for_user($user_id) { return $user_id === 31; }
function f_import_omr_test_data($user_id, $date, $testdata, $answers, $overwrite) {
    $GLOBALS['imported'] = [$user_id, $date, $testdata, $answers, $overwrite];
    return true;
}
function F_print_error($type, $message, $exit = false) { $GLOBALS['messages'][] = [$type, $message, $exit]; }
function get_form_row_text_input(...$arguments) { return '<DATE:' . $arguments[4] . '>'; }
function get_form_row_select_box(...$arguments) { return '<DIRECTORY:' . $arguments[4] . '>'; }
function get_form_row_checkbox(...$arguments) { return '<OVERWRITE:' . ($arguments[5] ? 'yes' : 'no') . '>'; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([
    'html' => $html, 'messages' => $GLOBALS['messages'], 'imported' => $GLOBALS['imported'],
], JSON_THROW_ON_ERROR);
PHP;

        try {
            [$status, $output] = \F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    $script,
                    dirname(__DIR__) . '/admin/code/tce_import_omr_bulk.php',
                    $cache,
                ],
                dirname(__DIR__) . '/admin/code',
            );
        } finally {
            $files = glob($cache . '/OMR/*');
            if ($files === false) {
                $files = [];
            }
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($cache . '/OMR');
            rmdir($cache);
        }

        self::assertSame(0, $status, $output);
        /** @var array{html:string,messages:array{array{string,string,bool},array{string,string,bool}},imported:array{int,string,array<int,mixed>,array<int,int>,bool}} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([31, '2026-08-10 12:34:56', [7, ['question' => 11]], [1 => 2], true], $result['imported']);
        self::assertStringContainsString('<DATE:2026-08-10 12:34:56>', $result['html']);
        self::assertStringContainsString('<DIRECTORY:' . $cache . '/OMR/>', $result['html']);
        self::assertStringContainsString('<OVERWRITE:yes>', $result['html']);
        self::assertStringContainsString('<SUBMIT:upload:Upload:Submit>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertCount(2, $result['messages']);
        self::assertSame('MESSAGE', $result['messages'][0][0]);
        self::assertStringContainsString('[REG42] Imported', $result['messages'][0][1]);
        self::assertStringContainsString('testuser_id=32&test_id=7&user_id=31', $result['messages'][0][1]);
        self::assertSame('MESSAGE', $result['messages'][1][0]);
        self::assertStringContainsString('LOGFILE:', $result['messages'][1][1]);
    }
}
