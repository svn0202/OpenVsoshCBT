<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class XmlUsersExportTest extends TestCase
{
    public function testUserAndGroupExportRemainUnchangedForNonAdministrator(): void
    {
        $script = <<<'PHP'
namespace Harness;
$GLOBALS['queries'] = [];
$GLOBALS['rows'] = [
    'users' => [[
        'user_id' => 7,
        'user_name' => 'alice',
        'user_email' => 'alice@example.test',
        'user_regdate' => '2026-08-10 12:34:56',
        'user_ip' => '127.0.0.1',
        'user_firstname' => 'Alice',
        'user_lastname' => 'Example',
        'user_birthdate' => '2001-02-03 00:00:00',
        'user_birthplace' => 'Test City',
        'user_regnumber' => 'REG-7',
        'user_ssn' => 'SSN-7',
        'user_level' => 5,
        'user_verifycode' => 'verify-7',
        'user_otpkey' => 'otp-7',
    ]],
    'groups' => [['group_id' => 3, 'group_name' => 'Students']],
];
function F_db_query($query, $db) {
    $GLOBALS['queries'][] = $query;
    return str_contains($query, 'usrgrp_group_id=group_id') ? 'groups' : 'users';
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][$result]); }
function f_text_to_xml($value) { return '{' . (string) $value . '}'; }
$source = file_get_contents($argv[1]);
preg_match('/function [Ff]_xml_export_users\(/', $source, $match, PREG_OFFSET_CAPTURE);
eval('namespace Harness; ' . substr($source, $match[0][1]));
require_once '../config/tce_config.php';
$_SESSION['session_user_level'] = 5;
$_SESSION['session_user_id'] = 7;
$xml = F_xml_export_users();
echo json_encode($GLOBALS['queries'], JSON_THROW_ON_ERROR), "\n---\n", $xml;
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_xml_users.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        $sections = explode("\n---\n", $output, 2);
        self::assertCount(2, $sections);
        $queriesJson = $sections[0];
        $xml = $sections[1] ?? '';
        self::assertSame(
            [
                "SELECT * FROM tce_users WHERE (user_id>1) AND ((user_level<5) OR (user_id=7))"
                    . " AND user_id IN (SELECT tb.usrgrp_user_id\n\t\t\tFROM tce_usrgroups AS ta, tce_usrgroups AS tb"
                    . "\n\t\t\tWHERE ta.usrgrp_group_id=tb.usrgrp_group_id\n\t\t\t\tAND ta.usrgrp_user_id=7"
                    . "\n\t\t\t\tAND tb.usrgrp_user_id=user_id) ORDER BY user_lastname,user_firstname,user_name",
                "SELECT *\n\t\t\t\tFROM tce_user_groups, tce_usrgroups\n\t\t\t\tWHERE usrgrp_group_id=group_id"
                    . "\n\t\t\t\t\tAND usrgrp_user_id=7\n\t\t\t\tORDER BY group_name",
            ],
            json_decode($queriesJson, true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertMatchesRegularExpression(
            '~^<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamusers version="[^"]+">\n\t<header lang="ru" date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t</header>\n\t<body>\n\t\t\t<user id="7">\n'
                . '\t\t\t\t<name>\{alice\}</name>\n\t\t\t\t<password></password>\n'
                . '\t\t\t\t<email>\{alice@example\.test\}</email>\n'
                . '\t\t\t\t<regdate>\{2026-08-10 12:34:56\}</regdate>\n'
                . '\t\t\t\t<ip>\{127\.0\.0\.1\}</ip>\n'
                . '\t\t\t\t<firstname>\{Alice\}</firstname>\n'
                . '\t\t\t\t<lastname>\{Example\}</lastname>\n'
                . '\t\t\t\t<birthdate>\{2001-02-03\}</birthdate>\n'
                . '\t\t\t\t<birthplace>\{Test City\}</birthplace>\n'
                . '\t\t\t\t<regnumber>\{REG-7\}</regnumber>\n'
                . '\t\t\t\t<ssn>\{SSN-7\}</ssn>\n'
                . '\t\t\t\t<level>\{5\}</level>\n'
                . '\t\t\t\t<verifycode>\{verify-7\}</verifycode>\n'
                . '\t\t\t\t<otpkey>\{otp-7\}</otpkey>\n'
                . '\t\t\t\t<group id="3">\{Students\}</group>\n'
                . '\t\t\t</user>\n\t</body>\n</tcexamusers>\n$~',
            $xml,
        );
    }

    public function testEmptyUserExportStructureAndQueryRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["query"] = ""; '
                    . 'function F_db_query($query, $db) { $GLOBALS["query"] = $query; return "result"; } '
                    . 'function F_db_fetch_array($result) { return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function [Ff]_xml_export_users\\(/", $source, $match, PREG_OFFSET_CAPTURE); '
                    . 'eval("namespace Harness; " . substr($source, $match[0][1])); '
                    . 'require_once "../config/tce_config.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; '
                    . '$xml = F_xml_export_users(); echo $GLOBALS["query"], "\\n---\\n", $xml;',
                dirname(__DIR__) . '/admin/code/tce_xml_users.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertMatchesRegularExpression(
            '~^SELECT \* FROM tce_users WHERE \(user_id>1\) '
                . 'ORDER BY user_lastname,user_firstname,user_name\n---\n'
                . '<\?xml version="1\.0" encoding="UTF-8" \?>\n'
                . '<tcexamusers version="[^"]+">\n\t<header lang="ru" '
                . 'date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}">\n'
                . '\t</header>\n\t<body>\n\t</body>\n</tcexamusers>\n$~',
            $output,
        );
    }
}
