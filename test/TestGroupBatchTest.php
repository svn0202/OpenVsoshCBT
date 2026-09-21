<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TestGroupBatchTest extends TestCase
{
    public function testBatchPreservesAccessChecksAndAvoidsPerTestQueries(): void
    {
        $script = <<<'CODE'
namespace Harness;
define('K_TABLE_TEST_GROUPS', 'testgroups');
define('K_TABLE_USERGROUP', 'usergroups');
$GLOBALS['db'] = new \PDO('sqlite::memory:');
$db = $GLOBALS['db'];
$db->exec('CREATE TABLE testgroups(tstgrp_test_id INT,tstgrp_group_id INT)');
$db->exec('CREATE TABLE usergroups(usrgrp_user_id INT,usrgrp_group_id INT)');
$db->exec('INSERT INTO testgroups VALUES(10,1),(10,2),(11,2),(12,3)');
$db->exec('INSERT INTO usergroups VALUES(7,1),(7,2),(8,3)');
$GLOBALS['queries'] = 0;
$GLOBALS['ip'] = true;
$GLOBALS['cert'] = true;
$GLOBALS['family'] = true;
$_SESSION = ['session_user_id' => 7];
function F_db_query($sql, $db) { ++$GLOBALS['queries']; return $db->query($sql); }
function F_db_fetch_array($result) { return $result->fetch(\PDO::FETCH_ASSOC); }
function F_count_rows($table, $where) { return F_db_query('SELECT COUNT(*) FROM '.$table.' '.$where, $GLOBALS['db'])->fetchColumn(); }
function f_is_valid_ip($a, $b) { return $GLOBALS['ip']; }
function f_is_valid_ssl_cert($a) { return $GLOBALS['cert']; }
function f_tmf_test_family_allows($a, $b) { return $GLOBALS['family']; }
$helper = file_get_contents($argv[1]);
eval('namespace Harness; ' . substr($helper, 5));
$source = file_get_contents($argv[2]);
$start = strpos($source, 'function f_is_valid_test_user(');
$end = strpos($source, "\n/**", $start);
$function = substr($source, $start, $end - $start);
$function = preg_replace('/^\s*require_once [^;]+;\n/m', '', $function);
eval('namespace Harness; ' . $function);
function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
$ids = f_tmf_group_test_ids(7);
check(count($ids) === 2 && isset($ids[10],$ids[11]), 'Duplicate groups or missing assignments');
for ($i=0; $i<400; ++$i) {
    check(f_is_valid_test_user(10,null,null,$ids), 'Assigned test denied');
    check(!f_is_valid_test_user(12,null,null,$ids), 'Other user assignment leaked');
}
check($GLOBALS['queries'] === 1, 'N+1 queries in batch path');
$guards = ['ip','cert'];
if (str_contains($function, 'f_tmf_test_family_allows(')) $guards[] = 'family';
foreach ($guards as $guard) {
    $GLOBALS[$guard] = false;
    check(!f_is_valid_test_user(10,null,null,$ids), 'Bypassed '.$guard);
    $GLOBALS[$guard] = true;
}
check(f_is_valid_test_user(10,null,null), 'Live assigned test denied');
$db->exec('DELETE FROM usergroups WHERE usrgrp_user_id=7');
check(!f_is_valid_test_user(10,null,null), 'Direct access ignored revoked membership');
check(f_tmf_group_test_ids(7) === [], 'Next rendering reused old permissions');
check(f_tmf_group_test_ids(8) === [12=>true], 'Users mixed');
$db->exec('DROP TABLE usergroups');
$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
check(f_tmf_group_test_ids(7) === [], 'Database failure did not deny');
check(!f_is_valid_test_user(10,null,null,[]), 'Empty snapshot granted access');
echo 'ok';
CODE;
        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script,
                dirname(__DIR__) . '/shared/code/tce_functions_test_groups.php',
                dirname(__DIR__) . '/shared/code/tce_functions_test.php'],
            __DIR__,
        );
        self::assertSame(0, $status, $output);
        self::assertSame('ok', $output);
    }
}
