<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class SelectUsersPopupControllerTest extends TestCase
{
    public function testSelectionFiltersAndGroupRenderingRemainUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_ADMIN_USERS', 10);
define('K_MAX_ROWS_PER_PAGE', 50);
define('K_NEWLINE', "\n");
$l = [
    't_user_select' => 'Select users', 'm_authorization_denied' => 'Denied',
    'w_group' => 'Group', 'a_meta_charset' => 'UTF-8', 'w_search' => 'Search',
];
$db = 'db';
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_select_users_popup.php';
$_REQUEST = [
    'searchterms' => 'alpha beta', 'rowsperpage' => '25', 'group_id' => '4',
    'cid' => 'Field-42_bad!', 'uids' => 'x1x02badx3',
];
$GLOBALS['group_index'] = 0;
$GLOBALS['shown'] = null;
function f_is_authorized_editor_for_group($group_id) { return true; }
function F_print_error(...$arguments) { echo '[[ERROR]]'; }
function F_user_group_select_sql() { return 'SELECT groups'; }
function F_db_query($sql, $db) { echo "[[QUERY:$sql]]"; return fopen('php://memory', 'r'); }
function F_db_fetch_array($result) {
    $rows = [['group_id' => '3', 'group_name' => 'Alpha'], ['group_id' => '4', 'group_name' => 'Beta & Co']];
    return $rows[$GLOBALS['group_index']++] ?? false;
}
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
function F_submit_button($name, $value, $title) { echo "<SUBMIT:$name:$value:$title>"; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function get_form_noscript_select() { return '<NOSCRIPT>'; }
function F_show_select_user_popup(...$arguments) { $GLOBALS['shown'] = $arguments; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $GLOBALS['shown']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_select_users_popup.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string,array{string,int,int,int,int,string,string,string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $shown] = $decoded;
        self::assertStringContainsString('name="cid" id="cid" value="ield42_bad"', $html);
        self::assertStringContainsString('name="uids" id="uids" value="x1x02x3"', $html);
        self::assertStringContainsString('<option value="4" selected="selected">Beta &amp; Co</option>', $html);
        self::assertStringContainsString('value="alpha beta"', $html);
        self::assertStringContainsString('<SUBMIT:search:Search:Search>', $html);
        self::assertStringContainsString('<NOSCRIPT>', $html);
        self::assertStringNotContainsString('[[DB-ERROR]]', $html);
        self::assertSame('user_lastname,user_firstname', $shown[0]);
        self::assertSame(0, $shown[1]);
        self::assertSame(0, $shown[2]);
        self::assertSame(25, $shown[3]);
        self::assertSame(4, $shown[4]);
        self::assertSame(
            "(((user_name LIKE '%alpha%') OR (user_email LIKE '%alpha%')"
                . " OR (user_firstname LIKE '%alpha%') OR (user_lastname LIKE '%alpha%')"
                . " OR (user_regnumber LIKE '%alpha%') OR (user_ssn LIKE '%alpha%'))"
                . " AND ((user_name LIKE '%beta%') OR (user_email LIKE '%beta%')"
                . " OR (user_firstname LIKE '%beta%') OR (user_lastname LIKE '%beta%')"
                . " OR (user_regnumber LIKE '%beta%') OR (user_ssn LIKE '%beta%')))"
                . ' AND (user_id IN (0,1,2,3))',
            preg_replace('/\s+/', ' ', $shown[5]),
        );
        self::assertSame('alpha beta', $shown[6]);
        self::assertSame('ield42_bad', $shown[7]);
    }
}
