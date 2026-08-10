<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_pregeneration.php';

final class PregenerationTest extends TestCase
{
    public function testDatabaseRowsRemainStableForHashingAndEligibleUsers(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_TABLE_USERGROUP', 'usergroups');
define('K_TABLE_TEST_GROUPS', 'testgroups');
$db = 'db-link';
final class LoadValue
{
    public function load(): string { return 'loaded'; }
}
final class StringValue
{
    public function __toString(): string { return 'stringable'; }
}
$stream = fopen('php://memory', 'r+');
fwrite($stream, 'resource');
rewind($stream);
$GLOBALS['assoc_rows'] = [['z' => new StringValue(), 'a' => $stream, 'm' => new LoadValue()], false];
$GLOBALS['array_rows'] = [['usrgrp_user_id' => '9'], ['usrgrp_user_id' => '10'], false];
function F_db_query($sql, $db)
{
    $GLOBALS['queries'][] = [preg_replace('/\s+/', ' ', trim($sql)), $db];
    return $sql === 'SELECT source' ? 'hash-result' : 'eligible-result';
}
function F_db_fetch_assoc($result) { return array_shift($GLOBALS['assoc_rows']); }
function F_db_fetch_array($result) { return array_shift($GLOBALS['array_rows']); }
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-pregeneration-functions-" . uniqid(); '
                    . 'mkdir($root . "/shared/code", 0700, true); mkdir($root . "/shared/config", 0700); '
                    . 'copy($argv[1], $root . "/shared/code/tce_functions_pregeneration.php"); '
                    . 'file_put_contents($root . "/shared/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'chdir($root . "/shared/code"); require "../config/tce_config.php"; '
                    . 'require "tce_functions_pregeneration.php"; '
                    . '$result = [F_tmf_pregeneration_hash_rows("SELECT source"), '
                    . 'F_tmf_pregeneration_eligible_users(17), $GLOBALS["queries"]]; '
                    . 'unlink($root . "/shared/code/tce_functions_pregeneration.php"); '
                    . 'unlink($root . "/shared/config/tce_config.php"); rmdir($root . "/shared/code"); '
                    . 'rmdir($root . "/shared/config"); rmdir($root . "/shared"); rmdir($root); '
                    . 'echo json_encode($result);',
                __DIR__ . '/../shared/code/tce_functions_pregeneration.php',
                base64_encode($configSource),
            ],
            __DIR__ . '/../shared/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: array{array{a: string, m: string, z: string}},
         *     1: array{int, int},
         *     2: array{array{string, string}, array{string, string}}
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([['a' => 'resource', 'm' => 'loaded', 'z' => 'stringable']], $decoded[0]);
        self::assertSame([9, 10], $decoded[1]);
        self::assertSame(['SELECT source', 'db-link'], $decoded[2][0]);
        self::assertSame(
            ['SELECT DISTINCT ug.usrgrp_user_id FROM usergroups ug '
                . 'INNER JOIN testgroups tg ON tg.tstgrp_group_id=ug.usrgrp_group_id '
                . 'WHERE tg.tstgrp_test_id=17 ORDER BY ug.usrgrp_user_id', 'db-link'],
            $decoded[2][1],
        );
    }

    public function testPreparedAttemptStillLooksAvailableInTheCatalogue(): void
    {
        self::assertSame(0, F_tmf_catalog_test_status(1, true));
    }

    public function testStartedAttemptKeepsItsProgressStatus(): void
    {
        self::assertSame(1, F_tmf_catalog_test_status(1, false));
        self::assertSame(2, F_tmf_catalog_test_status(2, false));
        self::assertSame(3, F_tmf_catalog_test_status(3, false));
    }
}
