<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TsvQuestionImporterTest extends TestCase
{
    public function testImporterReusesAuthorizedExistingModule(): void
    {
        $script = <<<'PHP'
namespace Harness;
error_reporting(E_ERROR);
define('K_TABLE_MODULES', 'modules');
$db = 'db';
$_SESSION = ['session_user_id' => 17];
$GLOBALS['queries'] = [];
function f_tsv_to_text($value) { return $value; }
function F_escape_sql($db, $value, $quote) { return $value; }
function F_db_query($sql, $db) {
    $GLOBALS['queries'][] = preg_replace('/\s+/', ' ', trim($sql));
    return fopen('php://memory', 'r');
}
function F_db_fetch_array($result) { return ['module_id' => 9]; }
function f_is_authorized_user($table, $field, $id, $userField) {
    $GLOBALS['authorization'] = [$table, $field, $id, $userField];
    return true;
}
function F_display_db_error($exit = true) { echo '[[DB_ERROR]]'; }
$source = file_get_contents($argv[1]);
preg_match('/function (f_tsv_question_importer)\(/', $source, $match, PREG_OFFSET_CAPTURE);
$function = substr($source, $match[0][1]);
$function = preg_replace('/^\s*require_once [^;]+;\n/m', '', $function);
eval('namespace Harness; ' . $function);
$path = tempnam(sys_get_temp_dir(), 'questions-');
file_put_contents($path, "M\t1\tExisting module\n");
$result = f_tsv_question_importer($path);
unlink($path);
echo json_encode([
    'result' => $result,
    'queries' => $GLOBALS['queries'],
    'authorization' => $GLOBALS['authorization'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_import_questions.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{result:bool,queries:array{0:string},authorization:array{0:string,1:string,2:int,3:string}} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($result['result']);
        self::assertStringContainsString("WHERE module_name='Existing module'", $result['queries'][0]);
        self::assertSame(['modules', 'module_id', 9, 'module_user_id'], $result['authorization']);
        self::assertStringNotContainsString('[[DB_ERROR]]', $output);
    }

    public function testImporterRejectsUnreadableFileBeforeDatabaseAccess(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; $GLOBALS["open_calls"] = []; '
                    . 'function fopen($path, $mode) { $GLOBALS["open_calls"][] = [$path, $mode]; return false; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (f_tsv_question_importer)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$function = substr($source, $start); '
                    . '$function = preg_replace("/^\\s*require_once [^;]+;\\n/m", "", $function); '
                    . 'eval("namespace Harness; " . $function); '
                    . '$qualified = __NAMESPACE__ . "\\\\" . $name; '
                    . '$result = $qualified("questions.tsv"); '
                    . 'echo json_encode([$result, $GLOBALS["open_calls"]]);',
                dirname(__DIR__) . '/admin/code/tce_import_questions.php',
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [false, [['questions.tsv', 'r']]],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
