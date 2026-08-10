<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class ImportOmrAnswersControllerTest extends TestCase
{
    public function testFormRenderingAndUserSelectionRemainUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_OMR_IMPORT', 9);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_TABLE_USERS', 'users');
define('K_TABLE_USERGROUP', 'usergroup');
define('K_NEWLINE', "\n");
$l = [
    't_omr_answers_importer' => 'OMR answers importer', 'm_authorization_denied' => 'Denied',
    'w_user' => 'User', 'a_meta_charset' => 'UTF-8', 'w_select' => 'Select', 'w_date' => 'Date',
    'w_datetime_format' => 'YYYY-MM-DD HH:MM:SS', 'w_omr_data_page' => 'Data page',
    'h_omr_data_page' => 'Upload data page', 'w_omr_answer_sheet' => 'Answer sheet',
    'w_overwrite' => 'Overwrite', 'h_omr_overwrite' => 'Replace answers', 'w_upload' => 'Upload',
    'h_submit_file' => 'Submit files', 'hp_omr_answers_importer' => 'OMR import help',
];
$db = 'db';
$_SERVER = ['SCRIPT_NAME' => '/admin/code/tce_import_omr_answers.php'];
$_SESSION = ['session_user_level' => 10, 'session_user_id' => 4];
$_REQUEST = ['date' => '2026-08-10 12:34:56', 'overwrite' => '1'];
$_FILES = [];
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [[
    'user_id' => 31, 'user_lastname' => 'Иванов', 'user_firstname' => 'Иван', 'user_name' => 'ivan',
], false];
function f_get_boolean($value) { return $value === '1'; }
function f_is_authorized_editor_for_user($userId) { return true; }
function F_db_query($sql, $db) { $GLOBALS['queries'][] = $sql; return fopen('php://memory', 'r'); }
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows']); }
function F_display_db_error() { echo '<DB_ERROR>'; }
function F_print_error($type, $message) { echo "<ERROR:$type:$message>"; }
function get_form_row_text_input(...$arguments) { return '<DATE:' . $arguments[4] . '>'; }
function get_form_upload_file(...$arguments) { return '<FILE:' . $arguments[1] . ':' . $arguments[2] . '>'; }
function get_form_row_checkbox(...$arguments) { return '<OVERWRITE:' . ($arguments[5] ? 'yes' : 'no') . '>'; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['queries'], $pagelevel, $thispage_title], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_import_omr_answers.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string, list<string>, int, string} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $queries, $pageLevel, $title] = $result;
        self::assertSame(9, $pageLevel);
        self::assertSame('OMR answers importer', $title);
        self::assertCount(1, $queries);
        self::assertStringContainsString('FROM users WHERE (user_id>1)', $queries[0] ?? '');
        self::assertStringContainsString('<option value="31">1. Иванов Иван - ivan</option>', $html);
        self::assertStringContainsString('<DATE:2026-08-10 12:34:56>', $html);
        self::assertStringContainsString('<FILE:omrdata:Data page>', $html);
        self::assertStringContainsString('<FILE:omrsheet10:Answer sheet 10>', $html);
        self::assertStringContainsString('<OVERWRITE:yes>', $html);
        self::assertStringContainsString('<SUBMIT:upload:Upload:Submit files>', $html);
        self::assertStringContainsString('<CSRF>', $html);
        self::assertStringContainsString('<div class="pagehelp">OMR import help</div>', $html);
        self::assertStringNotContainsString('<DB_ERROR>', $html);
    }
}
