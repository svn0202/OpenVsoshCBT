<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TsvUsersExportTest extends TestCase
{
    public function testPopulatedExportAndRestrictedQueriesRemainUnchanged(): void
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
    'groups' => [
        ['group_name' => 'Alpha'],
        ['group_name' => 'Beta'],
    ],
];
function F_db_query($query, $db) {
    $GLOBALS['queries'][] = $query;
    return str_contains($query, 'usrgrp_group_id=group_id') ? 'groups' : 'users';
}
function F_db_fetch_array($result) { return array_shift($GLOBALS['rows'][$result]); }
$source = file_get_contents($argv[1]);
preg_match('/function [Ff]_tsv_export_users\(/', $source, $match, PREG_OFFSET_CAPTURE);
eval('namespace Harness; ' . substr($source, $match[0][1]));
require_once '../config/tce_config.php';
$_SESSION['session_user_level'] = 5;
$_SESSION['session_user_id'] = 7;
$tsv = F_tsv_export_users();
echo json_encode($GLOBALS['queries'], JSON_THROW_ON_ERROR), "\n---\n", $tsv;
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_tsv_users.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        $sections = explode("\n---\n", $output, 2);
        self::assertCount(2, $sections);
        self::assertSame(
            [
                "SELECT * FROM tce_users WHERE (user_id>1) AND ((user_level<5) OR (user_id=7))"
                    . " AND user_id IN (SELECT tb.usrgrp_user_id\n\t\t\tFROM tce_usrgroups AS ta, tce_usrgroups AS tb"
                    . "\n\t\t\tWHERE ta.usrgrp_group_id=tb.usrgrp_group_id\n\t\t\t\tAND ta.usrgrp_user_id=7"
                    . "\n\t\t\t\tAND tb.usrgrp_user_id=user_id) ORDER BY user_lastname,user_firstname,user_name",
                "SELECT *\n\t\t\t\tFROM tce_user_groups, tce_usrgroups\n\t\t\t\tWHERE usrgrp_group_id=group_id"
                    . "\n\t\t\t\t\tAND usrgrp_user_id=7\n\t\t\t\tORDER BY group_name",
            ],
            json_decode($sections[0], true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            implode("\t", [
                'user_id', 'user_name', 'user_password', 'user_email', 'user_regdate', 'user_ip',
                'user_firstname', 'user_lastname', 'user_birthdate', 'user_birthplace', 'user_regnumber',
                'user_ssn', 'user_level', 'user_verifycode', 'user_otpkey', 'user_groups',
            ])
                . "\n"
                . implode("\t", [
                    '7', 'alice', '', 'alice@example.test', '2026-08-10 12:34:56', '127.0.0.1',
                    'Alice', 'Example', '2001-02-03', 'Test City', 'REG-7', 'SSN-7', '5', 'verify-7',
                    'otp-7', 'Alpha,Beta',
                ]),
            $sections[1] ?? '',
        );
    }
}
