<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UsersXlsxControllerTest extends TestCase
{
    public function testSessionLevelIsReadOnlyAfterAuthorization(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/admin/code/tce_users_xlsx.php');

        self::assertIsString($source);
        $authorization = strpos($source, "require_once '../../shared/code/tce_authorization.php';");
        $sessionLevel = strpos($source, "\$_SESSION['session_user_level']");

        self::assertIsInt($authorization);
        self::assertIsInt($sessionLevel);
        self::assertLessThan($sessionLevel, $authorization);
    }

    public function testExportKeepsRoleFilterAndWorkbookContract(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_IMPORT_USERS', 5);
define('K_AUTH_ADMINISTRATOR', 10);
define('K_TABLE_USERS', 'users');
define('K_TABLE_GROUPS', 'groups');
define('K_TABLE_USERGROUP', 'user_groups');
$db = 'db';
$_GET = ['download' => 'export'];
$_POST = [];
$_SESSION = ['session_user_level' => 5];
$GLOBALS['queries'] = [];
$GLOBALS['kinds'] = [];
$GLOBALS['indexes'] = ['users' => 0, 'groups' => 0];
function date($format) { return '20260810-123456'; }
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    $kind = str_contains($sql, 'SELECT * FROM users') ? 'users' : 'groups';
    $result = fopen('php://memory', 'r');
    $GLOBALS['kinds'][get_resource_id($result)] = $kind;
    return $result;
}
function F_db_fetch_array($result) {
    $kind = $GLOBALS['kinds'][get_resource_id($result)];
    $rows = [
        'users' => [[
            'user_id' => '42', 'user_name' => 'student', 'user_email' => 'student@example.test',
            'user_firstname' => 'Ada', 'user_lastname' => 'Lovelace', 'user_birthdate' => '2010-02-03 00:00:00',
            'user_birthplace' => 'London', 'user_regnumber' => 'A-42', 'user_ssn' => '',
            'user_level' => '2', 'user_regdate' => '2026-01-02 03:04:05',
        ]],
        'groups' => [['group_name' => 'Alpha']],
    ];
    return $rows[$kind][$GLOBALS['indexes'][$kind]++] ?? false;
}
function F_tmf_xlsx_build($sheets) {
    return 'JSON:' . json_encode([$sheets, $GLOBALS['queries']], JSON_THROW_ON_ERROR);
}
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_users_xlsx.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringStartsWith('JSON:', $output);
        /** @var array{list<array<string,mixed>>,list<string>} $decoded */
        $decoded = json_decode(substr($output, 5), true, 512, JSON_THROW_ON_ERROR);
        [$sheets, $queries] = $decoded;
        self::assertSame(
            'SELECT * FROM users WHERE user_id>1 AND user_level<5 ORDER BY user_lastname,user_firstname,user_name',
            $queries[0] ?? null,
        );
        self::assertStringContainsString('WHERE ug.usrgrp_user_id=42', $queries[1] ?? '');
        self::assertSame('Пользователи', $sheets[0]['name'] ?? null);
        /** @var list<list<mixed>> $rows */
        $rows = $sheets[0]['rows'] ?? [];
        self::assertSame('student', $rows[1][1] ?? null);
        self::assertSame('2010-02-03', $rows[1][5] ?? null);
        self::assertSame('Alpha', $rows[1][11] ?? null);
        self::assertSame(['value' => 42, 'type' => 'number'], $rows[1][0] ?? null);
    }

    public function testPreviewRejectsNonStringUploadPath(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_IMPORT_USERS', 5);
define('K_AUTH_ADMINISTRATOR', 10);
$l = ['a_meta_charset' => 'UTF-8'];
$db = 'db';
$_GET = [];
$_POST = ['xlsx_action' => 'preview', 'csrf_token' => 'valid'];
$_FILES = ['xlsx_file' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => ['nested']]];
$_SESSION = ['session_user_level' => 5];
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_users_xlsx.php';
function check_csrf_token($token) { return $token === 'valid'; }
function is_uploaded_file($path) { return false; }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
$source = str_replace('new RuntimeException(', 'new \\RuntimeException(', $source);
$source = str_replace('catch (Throwable ', 'catch (\\Throwable ', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode($html, JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_users_xlsx.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var string $html */
        $html = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Выберите XLSX-файл без ошибок загрузки.', $html);
    }

    public function testImportConsumesApprovedPreviewAndRendersResult(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_AUTH_IMPORT_USERS', 5);
define('K_AUTH_ADMINISTRATOR', 10);
$l = ['a_meta_charset' => 'UTF-8'];
$db = 'db';
$_GET = [];
$_POST = ['xlsx_action' => 'import', 'csrf_token' => 'valid', 'preview_token' => 'token-1'];
$_SESSION = [
    'session_user_level' => 5,
    'tmf_users_xlsx_preview' => [
        'token' => 'token-1', 'created_at' => 900,
        'records' => [2 => ['login' => 'student']],
    ],
];
$_SERVER['SCRIPT_NAME'] = '/admin/code/tce_users_xlsx.php';
$GLOBALS['imported'] = null;
function time() { return 1000; }
function check_csrf_token($token) { return $token === 'valid'; }
function F_tmf_users_xlsx_import($records) { $GLOBALS['imported'] = $records; return count($records); }
function f_get_csrf_token_field() { return '<CSRF>'; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
ob_start();
eval('namespace Harness; ' . $source);
$html = ob_get_clean();
echo json_encode([$html, $_SESSION, $GLOBALS['imported']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_users_xlsx.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{string,array<string,mixed>,array<int,array<string,mixed>>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$html, $session, $imported] = $decoded;
        self::assertStringContainsString('Импорт завершён. Создано пользователей: 1.', $html);
        self::assertArrayNotHasKey('tmf_users_xlsx_preview', $session);
        self::assertSame([2 => ['login' => 'student']], $imported);
    }
}
