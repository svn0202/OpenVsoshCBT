<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class SelectUsersControllerTest extends TestCase
{
    public function testSelectionFiltersAndGroupRenderingRemainUnchanged(): void
    {
        $result = self::runController(false);

        self::assertStringContainsString('<option value="4" selected="selected">Beta &amp; Co</option>', $result['html']);
        self::assertStringContainsString('value="alpha beta"', $result['html']);
        self::assertStringContainsString('<SUBMIT:search:Search:Search>', $result['html']);
        self::assertStringContainsString('<NOSCRIPT>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertStringNotContainsString('[[DB-ERROR]]', $result['html']);
        self::assertSame('user_lastname,user_firstname', $result['selected'][0]);
        self::assertSame(0, $result['selected'][1]);
        self::assertSame(0, $result['selected'][2]);
        self::assertSame(25, $result['selected'][3]);
        self::assertSame(4, $result['selected'][4]);
        self::assertSame(
            "(((user_name LIKE '%alpha%') OR (user_email LIKE '%alpha%')"
                . " OR (user_firstname LIKE '%alpha%') OR (user_lastname LIKE '%alpha%')"
                . " OR (user_regnumber LIKE '%alpha%') OR (user_ssn LIKE '%alpha%'))"
                . " AND ((user_name LIKE '%beta%') OR (user_email LIKE '%beta%')"
                . " OR (user_firstname LIKE '%beta%') OR (user_lastname LIKE '%beta%')"
                . " OR (user_regnumber LIKE '%beta%') OR (user_ssn LIKE '%beta%')))",
            preg_replace('/\s+/', ' ', $result['selected'][5]),
        );
        self::assertSame('alpha beta', $result['selected'][6]);
    }

    public function testAddGroupMutationUsesSelectedRow(): void
    {
        $result = self::runController(true);

        self::assertCount(2, $result['queries']);
        self::assertSame('SELECT groups', $result['queries'][0] ?? null);
        self::assertSame(
            'INSERT INTO usergroup ( usrgrp_user_id, usrgrp_group_id ) VALUES ( \'31\', \'9\' )',
            preg_replace('/\s+/', ' ', $result['queries'][1] ?? ''),
        );
        self::assertStringContainsString('[[MESSAGE:Updated]]', $result['html']);
    }

    /**
     * @return array{html: string, queries: list<string>, selected: array{string, int, int, int, int, string, string}}
     */
    private static function runController(bool $addGroup): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_USERS', 10);
define('K_AUTH_ADMIN_GROUPS', 8);
define('K_AUTH_DELETE_GROUPS', 8);
define('K_AUTH_DELETE_USERS', 8);
define('K_AUTH_MOVE_GROUPS', 8);
define('K_MAX_ROWS_PER_PAGE', 50);
define('K_NEWLINE', "\n");
define('K_TABLE_USERS', 'users');
define('K_TABLE_USERGROUP', 'usergroup');
$l = [
    't_user_select' => 'Select users', 'm_authorization_denied' => 'Denied',
    'w_group' => 'Group', 'a_meta_charset' => 'UTF-8', 'w_search' => 'Search',
    'm_updated' => 'Updated',
];
$db = 'db';
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_select_users.php';
$_SESSION = ['session_user_level' => 10, 'session_user_id' => 1];
$_REQUEST = [
    'searchterms' => 'alpha beta', 'rowsperpage' => '25', 'group_id' => '4',
];
$_POST = [];
if ($argv[2] === '1') {
    $_REQUEST['new_group_id'] = '9';
    $_POST = ['addgroup' => '1', 'userid1' => '31'];
}
$GLOBALS['group_index'] = 0;
$GLOBALS['queries'] = [];
$GLOBALS['selected'] = null;
function f_is_authorized_editor_for_group($group_id) { return true; }
function f_is_authorized_editor_for_user($user_id) { return true; }
function f_form_option_is_selected($value, $expected) { return (int) $value === (int) $expected; }
function F_print_error($type, $message, $exit = false) { echo "[[$type:$message]]"; }
function F_user_group_select_sql() { return 'SELECT groups'; }
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    if ($sql !== 'SELECT groups') { return true; }
    return fopen('php://memory', 'r');
}
function F_db_fetch_array($result) {
    $rows = [['group_id' => '3', 'group_name' => 'Alpha'], ['group_id' => '4', 'group_name' => 'Beta & Co']];
    return $rows[$GLOBALS['group_index']++] ?? false;
}
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function get_form_noscript_select() { return '<NOSCRIPT>'; }
function F_get_user_groups($user_id) { return []; }
function F_select_user(...$arguments) { $GLOBALS['selected'] = $arguments; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([
    'html' => $html, 'queries' => $GLOBALS['queries'], 'selected' => $GLOBALS['selected'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/admin/code/tce_select_users.php',
                $addGroup ? '1' : '0',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html: string, queries: list<string>, selected: array{string, int, int, int, int, string, string}} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
