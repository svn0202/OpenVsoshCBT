<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EditSslCertificatesControllerTest extends TestCase
{
    public function testUpdatePreservesOwnerAndRendersSelectedCertificate(): void
    {
        $result = self::runController('update');

        self::assertCount(3, $result['queries']);
        $update = $result['queries'][0] ?? null;
        $selectCurrent = $result['queries'][1] ?? null;
        $selectAll = $result['queries'][2] ?? null;
        self::assertIsString($update);
        self::assertIsString($selectCurrent);
        self::assertIsString($selectAll);
        self::assertStringStartsWith('UPDATE sslcerts SET', $update);
        self::assertStringContainsString("ssl_name='Client Cert'", $update);
        self::assertStringContainsString("ssl_enabled='1'", $update);
        self::assertStringContainsString("ssl_user_id='12'", $update);
        self::assertStringContainsString('WHERE ssl_id=5', $update);
        self::assertSame('SELECT * FROM sslcerts WHERE ssl_id=5 LIMIT 1', $selectCurrent);
        self::assertSame('SELECT * FROM sslcerts ORDER BY ssl_name', $selectAll);
        self::assertStringContainsString('<option value="5" selected="selected">1. [5] Client Cert (2030-01-01)', $result['html']);
        self::assertStringContainsString('<SUBMIT:update:Update:Update>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
    }

    public function testClearRendersUploadAndAddControls(): void
    {
        $result = self::runController('clear');

        self::assertSame(['SELECT * FROM sslcerts ORDER BY ssl_name'], $result['queries']);
        self::assertStringContainsString('name="userfile"', $result['html']);
        self::assertStringContainsString('<SUBMIT:add:Add:Add>', $result['html']);
        self::assertStringNotContainsString('<SUBMIT:update:', $result['html']);
        self::assertStringContainsString('<CHECKBOX:ssl_enabled:1>', $result['html']);
    }

    /** @return array{html:string,queries:list<string>} */
    private static function runController(string $mode): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_SSLCERT', 10);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_NEWLINE', "\n");
define('K_TABLE_SSLCERTS', 'sslcerts');
define('K_TABLE_TEST_SSLCERTS', 'test_sslcerts');
define('K_TABLE_QUESTIONS', 'questions');
define('K_MAX_UPLOAD_SIZE', 1048576);
define('K_PATH_CACHE', sys_get_temp_dir() . '/');
$l = [
    't_sslcerts' => 'SSL certificates', 'm_authorization_denied' => 'Denied',
    'm_disabled_vs_deleted' => 'Disabled', 'm_delete_confirm' => 'Confirm delete',
    'a_meta_charset' => 'UTF-8', 'w_delete' => 'Delete', 'h_delete' => 'Delete',
    'w_cancel' => 'Cancel', 'h_cancel' => 'Cancel', 'm_deleted' => 'Deleted',
    'm_form_missing_fields' => 'Missing', 'w_confirm' => 'Confirm', 'w_update' => 'Update',
    'm_duplicate_name' => 'Duplicate', 'm_updated' => 'Updated', 'w_sslcert' => 'Certificate',
    'w_upload_file' => 'Upload', 'h_upload_file' => 'Upload', 'w_name' => 'Name',
    'w_enabled' => 'Enabled', 'h_enabled' => 'Enabled', 'h_update' => 'Update',
    'w_add' => 'Add', 'h_add' => 'Add', 'w_clear' => 'Clear', 'h_clear' => 'Clear',
    'hp_import_ssl_certificates' => 'Help',
];
$db = 'db';
$menu_mode = $argv[2];
$formstatus = true;
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_edit_sslcerts.php';
$_SESSION = ['session_user_id' => 7, 'session_user_level' => 10];
$_FILES = [];
$_POST = [];
$_REQUEST = $argv[2] === 'update'
    ? [
        'ssl_id' => '5', 'ssl_name' => 'Client Cert', 'ssl_enabled' => '1',
        'ssl_user_id' => '12', 'confirmupdate' => '1',
    ]
    : [];
$GLOBALS['queries'] = [];
$certificate = [
    'ssl_id' => '5', 'ssl_name' => 'Client Cert', 'ssl_hash' => str_repeat('a', 32),
    'ssl_end_date' => '2030-01-01', 'ssl_enabled' => '1', 'ssl_user_id' => '12',
];
$GLOBALS['rows'] = $argv[2] === 'update' ? [$certificate, $certificate, false] : [false];
function utrim($value) { return trim((string) $value); }
function f_get_boolean($value) { return (bool) $value; }
function f_is_authorized_user(...$arguments) { return true; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function F_check_form_fields() { return true; }
function F_check_unique(...$arguments) { return true; }
function F_escape_sql($db, $value) { return str_replace("'", "''", (string) $value); }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    return str_starts_with($sql, 'SELECT') ? fopen('php://memory', 'r') : true;
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows']); }
function F_db_insert_id(...$arguments) { return 13; }
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error($type, $message, ...$arguments) { echo "<$type:$message>"; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_text_input($name, $label, $title, $required, $value, ...$arguments) {
    return '<TEXT:' . $name . ':' . $value . '>';
}
function get_form_row_checkbox($name, $label, $title, $required, $value, $checked, ...$arguments) {
    return '<CHECKBOX:' . $name . ':' . (int) $checked . '>';
}
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode(['html' => $html, 'queries' => $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_sslcerts.php', $mode],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html:string,queries:list<string>} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
