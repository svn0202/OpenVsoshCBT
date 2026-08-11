<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EditGroupControllerTest extends TestCase
{
    public function testAddCreatesGroupMembershipAndSelectedOption(): void
    {
        $result = self::runController('add');

        self::assertCount(3, $result['queries']);
        $insertGroup = $result['queries'][0] ?? null;
        $insertMembership = $result['queries'][1] ?? null;
        $selectGroup = $result['queries'][2] ?? null;
        self::assertIsString($insertGroup);
        self::assertIsString($insertMembership);
        self::assertIsString($selectGroup);
        self::assertStringStartsWith('INSERT INTO groups', $insertGroup);
        self::assertStringContainsString("'7', '9'", $insertMembership);
        self::assertSame('SELECT groups WHERE group_id=9 LIMIT 1', $selectGroup);
        self::assertStringContainsString('<option value="9" selected="selected">New Group</option>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
    }

    public function testSearchUsesBoundedQueryAndEscapesGroupNames(): void
    {
        $result = self::runController('search');

        self::assertSame(["SELECT groups WHERE group_name LIKE '%Alpha%' LIMIT 25"], $result['queries']);
        self::assertStringContainsString('<option value="3">Alpha &amp; Beta</option>', $result['html']);
        self::assertStringContainsString('value="Alpha"', $result['html']);
        self::assertStringContainsString('<SUBMIT:search:Search:Search>', $result['html']);
    }

    public function testDeleteDoesNotSelectAnEmptyGroupIdAfterSuccess(): void
    {
        $result = self::runController('forcedelete');

        self::assertSame(['DELETE FROM groups WHERE group_id=3'], $result['queries']);
        self::assertStringNotContainsString('group_id=', implode("\n", array_slice($result['queries'], 1)));
        self::assertStringContainsString('<MESSAGE:[Old Group] Deleted>', $result['html']);
    }

    /** @return array{html: string, queries: list<string>} */
    private static function runController(string $mode): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_GROUPS', 10);
define('K_AUTH_DELETE_GROUPS', 8);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_DATABASE_TYPE', 'POSTGRESQL');
define('K_MAX_ROWS_PER_PAGE', 25);
define('K_NEWLINE', "\n");
define('K_TABLE_GROUPS', 'groups');
define('K_TABLE_USERGROUP', 'usergroup');
$l = [
    't_group_editor' => 'Group editor', 'm_authorization_denied' => 'Denied',
    'w_name' => 'Name', 'a_meta_charset' => 'UTF-8', 'm_delete_confirm' => 'Confirm delete',
    'w_delete' => 'Delete', 'h_delete' => 'Delete group', 'w_cancel' => 'Cancel',
    'h_cancel' => 'Cancel', 'm_group_deleted' => 'Deleted', 'm_form_missing_fields' => 'Missing',
    'w_confirm' => 'Confirm', 'w_update' => 'Update', 'm_duplicate_name' => 'Duplicate',
    'm_group_updated' => 'Updated', 'w_group' => 'Group', 'w_search' => 'Search',
    'h_group_name' => 'Group name', 'h_update' => 'Update group', 'w_add' => 'Add',
    'h_add' => 'Add group', 'w_clear' => 'Clear', 'h_clear' => 'Clear form',
    'hp_edit_group' => 'Help',
];
$db = 'db';
$menu_mode = in_array($argv[2], ['add', 'forcedelete'], true) ? $argv[2] : '';
$formstatus = true;
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_edit_group.php';
$_SESSION = ['session_user_id' => 7, 'session_user_ip' => '192.0.2.7', 'session_user_level' => 10];
$_POST = $argv[2] === 'forcedelete' ? ['forcedelete' => 'Delete'] : [];
$_REQUEST = match ($argv[2]) {
    'add' => ['group_name' => 'New Group'],
    'forcedelete' => ['group_id' => '3', 'group_name' => 'Old Group'],
    default => ['group_searchterms' => 'Alpha'],
};
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = $argv[2] === 'add'
    ? [['group_id' => '9', 'group_name' => 'New Group'], false]
    : [['group_id' => '3', 'group_name' => 'Alpha & Beta'], false];
function f_is_authorized_editor_for_group($groupId) { return true; }
function openvsosh_is_default_group($groupId) { return false; }
function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
function f_legacy_literal_equals($value, $expected) { return (string) $value === $expected; }
function F_check_form_fields() { return true; }
function F_check_unique(...$arguments) { return true; }
function F_escape_sql($db, $value) { return str_replace("'", "''", (string) $value); }
function F_user_group_select_sql($where = '') { return 'SELECT groups' . ($where === '' ? '' : ' WHERE ' . $where); }
function F_db_query($sql, $db) {
    $sql = trim(preg_replace('/\s+/', ' ', $sql));
    $GLOBALS['queries'][] = $sql;
    return str_starts_with($sql, 'SELECT') ? fopen('php://memory', 'r') : true;
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows']); }
function F_db_insert_id($db, $table, $field) { return 9; }
function F_display_db_error(...$arguments) { echo '<DB-ERROR>'; }
function F_print_error($type, $message) { echo "<$type:$message>"; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function f_get_csrf_token_field() { return '<CSRF>'; }
function get_form_noscript_select($name) { return '<NOSCRIPT:' . $name . '>'; }
function get_form_row_text_input($name, $label, $title, $required, $value) {
    return '<TEXT:' . $name . ':' . $value . '>';
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
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_edit_group.php', $mode],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html: string, queries: list<string>} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
