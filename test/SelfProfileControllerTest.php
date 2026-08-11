<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class SelfProfileControllerTest extends TestCase
{
    public function testSessionUserIsReadOnlyAfterAuthorization(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/admin/code/tce_self_profile.php');

        self::assertIsString($source);
        $authorization = strpos($source, "require_once '../../shared/code/tce_authorization.php';");
        $userId = strpos($source, "\$_SESSION['session_user_id']");

        self::assertIsInt($authorization);
        self::assertIsInt($userId);
        self::assertLessThan($userId, $authorization);
    }

    public function testProfilePageKeepsDisplayedDataAndFormContract(): void
    {
        $result = self::runController(false);

        self::assertStringContainsString('<strong>Логин:</strong> operator', $result['html']);
        self::assertStringContainsString('<strong>Уровень:</strong> 5', $result['html']);
        self::assertStringContainsString('<strong>Группы:</strong> Alpha, Beta &amp; Co', $result['html']);
        self::assertStringContainsString('tce_user_change_email.php">Change email</a>', $result['html']);
        self::assertStringContainsString('tce_user_change_password.php">Change password</a>', $result['html']);
        self::assertStringContainsString('<CSRF>', $result['html']);
        self::assertSame('user_firstname', $result['fields'][0][0] ?? null);
        self::assertSame('Ada', $result['fields'][0][4] ?? null);
        self::assertSame('user_lastname', $result['fields'][1][0] ?? null);
        self::assertSame('Lovelace', $result['fields'][1][4] ?? null);
        self::assertSame('currentpassword', $result['fields'][2][0] ?? null);
    }

    public function testProfileUpdateKeepsPasswordSqlAndSessionContract(): void
    {
        $result = self::runController(true);

        self::assertSame(
            "UPDATE users SET user_firstname='Grace', user_lastname='Hopper' WHERE user_id=42",
            $result['queries'][1] ?? null,
        );
        self::assertContains(['MESSAGE', 'Профиль обновлён.'], $result['messages']);
        self::assertSame('Grace', $result['session']['session_user_firstname'] ?? null);
        self::assertSame('Hopper', $result['session']['session_user_lastname'] ?? null);
        self::assertStringNotContainsString('[[DB-ERROR]]', $result['html']);
    }

    /**
     * @return array{
     *     html:string,
     *     queries:list<string>,
     *     messages:list<array{string,string}>,
     *     fields:list<list<mixed>>,
     *     session:array<string,mixed>
     * }
     */
    private static function runController(bool $post): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_OPERATOR', 5);
define('K_NEWLINE', "\n");
define('K_TABLE_USERS', 'users');
define('K_TABLE_GROUPS', 'groups');
define('K_TABLE_USERGROUP', 'user_groups');
$l = [
    'm_login_wrong' => 'Wrong password', 'a_meta_charset' => 'UTF-8',
    'w_firstname' => 'First name', 'w_lastname' => 'Last name',
    'w_current_password' => 'Current password', 'h_password' => 'Password help',
    'w_change_email' => 'Change email', 'w_change_password' => 'Change password',
];
$db = 'db';
$_SERVER = ['REQUEST_METHOD' => $argv[2] === 'post' ? 'POST' : 'GET', 'SCRIPT_NAME' => '/admin/code/tce_self_profile.php'];
$_POST = $argv[2] === 'post' ? [
    'save_profile' => '1', 'csrf_token' => 'valid', 'user_firstname' => ' Grace ',
    'user_lastname' => ' Hopper ', 'currentpassword' => 'current-secret',
] : [];
$_SESSION = ['session_user_id' => 42];
$GLOBALS['queries'] = [];
$GLOBALS['messages'] = [];
$GLOBALS['fields'] = [];
$GLOBALS['group_index'] = 0;
function check_csrf_token($token) { return $token === 'valid'; }
function check_password($plain, $hash) { return $plain === 'current-secret' && $hash === 'stored-hash'; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    return str_starts_with($sql, 'SELECT') ? fopen('php://memory', 'r') : true;
}
function F_db_fetch_array($result) {
    $sql = end($GLOBALS['queries']);
    if (str_contains($sql, 'SELECT user_password')) { return ['user_password' => 'stored-hash']; }
    if (str_contains($sql, 'SELECT user_name')) {
        return ['user_name' => 'operator', 'user_email' => 'operator@example.test',
            'user_firstname' => 'Ada', 'user_lastname' => 'Lovelace', 'user_level' => '5'];
    }
    $groups = [['group_name' => 'Alpha'], ['group_name' => 'Beta & Co']];
    return $groups[$GLOBALS['group_index']++] ?? false;
}
function F_print_error($type, $message) { $GLOBALS['messages'][] = [$type, $message]; }
function F_display_db_error(...$arguments) { echo '[[DB-ERROR]]'; }
function get_form_row_text_input(...$arguments) { $GLOBALS['fields'][] = $arguments; return '<FIELD>'; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([
    'html' => $html, 'queries' => $GLOBALS['queries'], 'messages' => $GLOBALS['messages'],
    'fields' => $GLOBALS['fields'], 'session' => $_SESSION,
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_self_profile.php',
                $post ? 'post' : 'get',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{
         *     html:string,
         *     queries:list<string>,
         *     messages:list<array{string,string}>,
         *     fields:list<list<mixed>>,
         *     session:array<string,mixed>
         * }
         */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
