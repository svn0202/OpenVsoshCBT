<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestFamilyContinuationTest extends TestCase
{
    public function testContinuationSkipsCatalogueWhileNewStartsPreserveFamilyRules(): void
    {
        $script = <<<'PHP'
$root = sys_get_temp_dir() . '/test-families-' . bin2hex(random_bytes(8));
mkdir($root . '/code', 0700, true);
mkdir($root . '/config', 0700);
copy($argv[1], $root . '/code/families.php');
// No ID configuration file: variants are discovered from the database.
require $root . '/code/families.php';
define('K_TABLE_TESTS', 'tests');
define('K_TABLE_TEST_USER', 'attempts');
define('K_TABLE_TEST_GROUPS', 'testgroups');
define('K_TABLE_USERGROUP', 'usergroups');
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE tests(test_id INT,test_name TEXT,test_begin_time TEXT,test_family_key TEXT)');
$db->exec("INSERT INTO tests VALUES(10,'Новый предмет. 9 класс. Вариант 1','2032-01-01',NULL),(11,'Новый предмет, 9 класс. Вариант 2','2032-01-01',NULL),(12,'Новый предмет 9 класс. Вариант 3','2032-01-01',NULL),(20,'Экзамен А','2032-01-02','same-exam'),(21,'Другой заголовок','2032-01-03','same-exam'),(99,'Самостоятельный тест','2032-01-01',NULL)");
$db->exec('CREATE TABLE attempts(testuser_id INTEGER PRIMARY KEY, testuser_test_id INT, testuser_user_id INT, testuser_status INT, testuser_pregenerated INT, testuser_creation_time TEXT)');
$db->exec('CREATE TABLE testgroups(tstgrp_test_id INT,tstgrp_group_id INT)');
$db->exec('CREATE TABLE usergroups(usrgrp_user_id INT,usrgrp_group_id INT)');
$db->exec('INSERT INTO testgroups VALUES(10,1),(11,2),(12,3),(20,1),(21,2)');
$db->exec('INSERT INTO usergroups VALUES(1,1),(1,2),(2,2),(3,1),(3,2),(4,1),(4,2),(5,1),(5,2),(6,1),(6,2),(8,2),(8,3),(9,3)');
$db->exec("INSERT INTO attempts VALUES(1,11,3,1,0,'2026-01-01'),(2,11,4,4,0,'2026-01-01'),(3,10,5,4,0,'2026-01-01'),(4,11,5,1,0,'2026-01-02'),(5,11,6,1,1,'2026-01-01'),(6,11,1,0,0,'2026-01-01')");
$GLOBALS['queries'] = [];
function F_db_query($sql,$db) { $GLOBALS['queries'][] = $sql; return $db->query($sql); }
function F_db_fetch_array($result) { return $result->fetch(PDO::FETCH_ASSOC); }
// Existing ordinary work must not load the complete test catalogue.
if (!f_tmf_test_family_allows(11,3)) throw new RuntimeException('Existing work denied');
if (count($GLOBALS['queries']) !== 1 || !str_starts_with($GLOBALS['queries'][0], 'SELECT testuser_id FROM attempts')) {
    throw new RuntimeException('Existing work loaded the family catalogue');
}
$cases = [
    [10,1,true], [11,1,false], // two groups: one deterministic variant; failed generation ignored
    [10,2,false], [11,2,true], // a single assignment to variant 2 remains intact
    [10,3,false], [11,3,true], // already started variant 2 beats lowest-ID selection
    [10,4,false], [11,4,true], // completion does not unlock variant 1
    [10,5,true], [11,5,true], // historical double start: preserve both existing works
    [10,6,true], [11,6,false], // pregeneration is not a real start
    [10,7,false], [11,7,false], // no assignment
    [20,4,true], [21,4,false], // separate subject unaffected by completed subject
    [99,4,true], // independent tests unchanged
    [12,8,false], [11,8,true], [12,9,true], // three variants, future year, no configuration
];
$results = [];
foreach ($cases as [$test,$user,$expected]) {
    $actual = f_tmf_test_family_allows($test,$user);
    if ($actual !== $expected) throw new RuntimeException("Unexpected decision for test $test/user $user");
    $results[] = $actual;
}
if (f_tmf_test_family_allows(11,5,true)) throw new RuntimeException('Historical second work allowed a new attempt');
if (!f_tmf_test_family_allows(10,5,true)) throw new RuntimeException('First variant rejected');
if (f_tmf_test_family_allows(123456,1)) throw new RuntimeException('Unknown test allowed');
$db->exec('DROP TABLE attempts');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
if (f_tmf_test_family_allows(10,1)) throw new RuntimeException('Database error allowed access');
unlink($root . '/code/families.php');
rmdir($root . '/code'); rmdir($root . '/config'); rmdir($root);
echo json_encode($results);
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, __DIR__ . '/../shared/code/tce_functions_test_families.php'],
            __DIR__,
        );
        self::assertSame(0, $status, $output);
        $results = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($results);
        self::assertCount(20, $results);
    }

    public function testActivationSkipsFamiliesOnlyWhenNoPreparedAttemptExists(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TABLE_TEST_USER', 'attempts');
$GLOBALS['db'] = 'db';
$GLOBALS['row'] = false;
$GLOBALS['failed'] = false;
$GLOBALS['families'] = 0;
function F_db_query($sql,$db) { return $GLOBALS['failed'] ? false : 'result'; }
function F_db_fetch_array($result) { return $GLOBALS['row']; }
function f_tmf_test_family($id) { ++$GLOBALS['families']; return []; }
function f_tmf_pregeneration_activate_locked($test,$user) { return 'activated'; }
$source = file_get_contents($argv[1]);
$start = strpos($source, 'function f_tmf_pregeneration_activate(');
$end = strpos($source, "\n}\n", $start) + 2;
$function = substr($source, $start, $end-$start);
$function = preg_replace('/^\s*require_once [^;]+;\n/m', '', $function);
eval('namespace Harness; '.$function);
$none = f_tmf_pregeneration_activate(10,7);
$GLOBALS['failed'] = true;
$failure = f_tmf_pregeneration_activate(10,7);
$GLOBALS['failed'] = false;
$GLOBALS['row'] = ['testuser_id'=>42];
$prepared = f_tmf_pregeneration_activate(10,7);
echo json_encode([$none,$failure,$prepared,$GLOBALS['families']]);
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/shared/code/tce_functions_pregeneration.php'],
            __DIR__,
        );
        self::assertSame(0, $status, $output);
        self::assertSame(['none', 'denied', 'activated', 1], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }
}
