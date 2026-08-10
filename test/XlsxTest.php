<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use ZipArchive;

require_once __DIR__ . '/../shared/code/tce_functions_users_xlsx.php';

final class XlsxTest extends TestCase
{
    public function testAdminXlsxUserGroupsAreReturnedInQueryOrder(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'class FakeResult { public int $index = 0; public function __construct(public array $rows) {} } '
                    . 'define("K_TABLE_GROUPS", "groups"); define("K_TABLE_USERGROUP", "user_groups"); '
                    . '$GLOBALS["db"] = new stdClass(); $GLOBALS["query"] = ""; '
                    . 'function F_db_query($sql, $db) { $GLOBALS["query"] = $sql; '
                    . 'return new FakeResult([["group_name" => "alpha"], ["group_name" => "beta"]]); } '
                    . 'function F_db_fetch_array($result) { return $result->rows[$result->index++] ?? false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_tmf_users_xlsx_groups_for_user/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . '$start = $match[0][1]; $end = strpos($source, "\\nif (isset(\\$_GET[\'download\']", $start); '
                    . 'eval(substr($source, $start, $end - $start)); '
                    . 'echo json_encode([F_tmf_users_xlsx_groups_for_user(42), $GLOBALS["query"]]);',
                dirname(__DIR__) . '/admin/code/tce_users_xlsx.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            ['alpha, beta', 'SELECT g.group_name FROM groups g INNER JOIN user_groups ug ON '
                . 'ug.usrgrp_group_id=g.group_id WHERE ug.usrgrp_user_id=42 ORDER BY g.group_name'],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAdminXlsxSenderWritesBytesUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_tmf_users_xlsx_send.*?\\n\\}\\n/s", $source, $match); '
                    . 'eval($match[0]); F_tmf_users_xlsx_send(base64_decode("YmluYXJ5AGRhdGE="), "test.xlsx");',
                dirname(__DIR__) . '/admin/code/tce_users_xlsx.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame("binary\0data", $output);
    }

    public function testAdminHtmlHelperEscapesUsingConfiguredCharset(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_tmf_users_xlsx_html.*?\\n\\}\\n/s", $source, $match); '
                    . 'eval($match[0]); $GLOBALS["l"] = ["a_meta_charset" => "UTF-8"]; '
                    . 'echo F_tmf_users_xlsx_html(base64_decode("PGEgaHJlZj0ieCI+J3ZhbHVlJyAmIHRleHQ8L2E+"));',
                dirname(__DIR__) . '/admin/code/tce_users_xlsx.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('&lt;a href=&quot;x&quot;&gt;&#039;value&#039; &amp; text&lt;/a&gt;', $output);
    }

    /** @throws \RuntimeException */
    public function testNativeWorkbookRoundTripsTypesAndTreatsFormulaTextAsText(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is not installed.');
        }
        $bytes = \F_tmf_xlsx_build([[
            'name' => 'Проверка / Excel',
            'rows' => [
                ['login', 'score', 'note'],
                ['student-1', ['value' => 12.5, 'type' => 'number'], '=HYPERLINK("https://bad.test")'],
                ['  пробелы  ', 0, 'обычный текст'],
            ],
        ]]);
        $file = tempnam(sys_get_temp_dir(), 'openvsosh-xlsx-test-');
        self::assertNotFalse($file);
        file_put_contents($file, $bytes);
        try {
            $rows = \F_tmf_xlsx_read($file);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
        self::assertSame('student-1', $rows[1][0] ?? null);
        self::assertSame('12.5', $rows[1][1] ?? null);
        self::assertSame('=HYPERLINK("https://bad.test")', $rows[1][2] ?? null);
        self::assertSame('  пробелы  ', $rows[2][0] ?? null);
    }

    /** @throws \RuntimeException */
    public function testReaderRejectsWorkbookFormulaCells(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is not installed.');
        }
        $bytes = \F_tmf_xlsx_build([['rows' => [['header'], ['safe']]]]);
        $file = tempnam(sys_get_temp_dir(), 'openvsosh-xlsx-formula-');
        self::assertNotFalse($file);
        file_put_contents($file, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($file) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet = str_replace(
            '<c r="A2" t="inlineStr" s="0"><is><t>safe</t></is></c>',
            '<c r="A2"><f>1+1</f><v>2</v></c>',
            $sheet,
        );
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Formulas are not accepted');
            \F_tmf_xlsx_read($file);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testWriterPreservesCanonicalDecimalTextInNumericCell(): void
    {
        $xml = \F_tmf_xlsx_sheet_xml([
            [['value' => '0.100', 'type' => 'number']],
        ], []);
        self::assertStringContainsString('<v>0.100</v>', $xml);
        self::assertStringNotContainsString('<v>0.1</v>', $xml);
    }

    public function testUserPreviewReportsDuplicatesUnknownGroupsAndInvalidRows(): void
    {
        $rows = [
            \TMF_USERS_XLSX_HEADERS,
            ['new-user', 'long-password', 'valid@example.test', 'Имя', 'Фамилия', '40212',
                '', '', '', '1', 'default'],
            ['new-user', 'short', 'invalid', '', '', '03.02.2010', '', '', '', '99', 'missing'],
            ['existing', 'long-password', '', '', '', '', '', '', '', '1', 'default'],
        ];
        $result = \F_tmf_users_xlsx_validate(
            $rows,
            ['existing' => true],
            ['default' => 1],
            10,
        );
        self::assertArrayHasKey(2, $result['records']);
        self::assertArrayHasKey(3, $result['errors']);
        self::assertArrayHasKey(4, $result['errors']);
        self::assertStringContainsString('повторяется', implode(' ', $result['errors'][3] ?? []));
        self::assertStringContainsString('Неизвестная группа', implode(' ', $result['errors'][3] ?? []));
        self::assertStringContainsString('уже существует', implode(' ', $result['errors'][4] ?? []));
        self::assertStringStartsWith('$', (string) ($result['records'][2]['password_hash'] ?? ''));
        self::assertSame('2010-02-03', $result['records'][2]['birth_date'] ?? null);
    }

    public function testUserImportKeepsItsTransactionAndSqlContract(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_TABLE_USERS', 'users');
define('K_TABLE_USERGROUP', 'user_groups');
$db = 'db';
$_SERVER['REMOTE_ADDR'] = '192.0.2.9';
$GLOBALS['queries'] = [];
function date($format) { return '2026-08-10 13:45:00'; }
function get_normalized_ip($ip) { return $ip; }
function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql));
    return true;
}
function F_db_insert_id($db, $table, $field) { return 55; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
$source = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $source);
eval('namespace Harness; ' . $source);
$count = F_tmf_users_xlsx_import([2 => [
    'login' => "o'reilly", 'email' => 'student@example.test', 'password_hash' => '$hash',
    'registration_number' => '', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
    'birth_date' => '2010-02-03', 'birth_place' => '', 'ssn' => '', 'level' => 2,
    'group_ids' => [3, 7], 'group_names' => ['alpha', 'beta'],
]]);
echo json_encode([$count, $GLOBALS['queries']], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/shared/code/tce_functions_users_xlsx.php'],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{int,array{string,string,string,string,string}} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        [$count, $queries] = $decoded;
        self::assertSame(1, $count);
        self::assertSame('START TRANSACTION', $queries[0]);
        self::assertSame(
            "INSERT INTO users (user_regdate,user_ip,user_name,user_email,user_password,user_regnumber,"
                . "user_firstname,user_lastname,user_birthdate,user_birthplace,user_ssn,user_level) VALUES "
                . "('2026-08-10 13:45:00','192.0.2.9','o''reilly','student@example.test','"
                . '$hash'
                . "',NULL,"
                . "'Ada','Lovelace','2010-02-03',NULL,NULL,2)",
            $queries[1],
        );
        self::assertSame(
            'INSERT INTO user_groups (usrgrp_user_id,usrgrp_group_id) VALUES (55,3)',
            $queries[2],
        );
        self::assertSame(
            'INSERT INTO user_groups (usrgrp_user_id,usrgrp_group_id) VALUES (55,7)',
            $queries[3],
        );
        self::assertSame('COMMIT', $queries[4]);
    }
}
