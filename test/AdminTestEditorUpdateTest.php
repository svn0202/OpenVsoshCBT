<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminTestEditorUpdateTest extends TestCase
{
    /** @return iterable<string,array{bool,list<int>,bool}> */
    public static function updates(): iterable
    {
        yield 'existing attempts, multiple groups' => [true, [3, 7], true];
        yield 'existing attempts, remove all groups' => [true, [], true];
        yield 'unused test' => [false, [3, 7], true];
        yield 'missing confirmation' => [true, [3, 7], false];
    }

    /** @param list<int> $groups */
    #[DataProvider('updates')]
    public function testUpdatePreservesAttempts(bool $hasAttempts, array $groups, bool $confirmed): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                <<<'HARNESS'
                    namespace Harness;
                    const K_TABLE_TESTS = 'tests';
                    const K_TABLE_TEST_USER = 'attempts';
                    const K_TABLE_TEST_GROUPS = 'test_groups';
                    const K_TABLE_TEST_SSLCERTS = 'test_certs';
                    function F_check_form_fields() { return true; }
                    function F_check_unique($table, ...$args) { return $table !== 'attempts' || !$GLOBALS['hasAttempts']; }
                    function F_escape_sql($db, $value) { return str_replace("'", "''", $value); }
                    function f_empty_to_null($value) { return "'" . $value . "'"; }
                    function f_legacy_int_equals($value, $expected) { return (int) $value === $expected; }
                    function f_legacy_db_query_result($result) { return $result; }
                    function F_db_query($sql, $db) { $GLOBALS['queries'][] = $sql; return true; }
                    function F_display_db_error(...$args) { throw new \RuntimeException('Database error'); }
                    function F_print_error($type, $message) {}
                    [$hasAttempts, $user_groups, $confirmed] = json_decode($argv[2], true);
                    $_REQUEST = $confirmed ? ['confirmupdate' => 1] : [];
                    $source = file_get_contents($argv[1]);
                    $start = strpos($source, "    case 'update':");
                    $end = strpos($source, "    case 'updateattempts':", $start);
                    $block = substr($source, $start, $end - $start);
                    // Populate all submitted scalar test fields; non-default scoring must be ignored for used tests.
                    preg_match_all('/\$(test_[a-z_]+)/', $block, $matches);
                    foreach (array_unique($matches[1]) as $field) { $$field = 9; }
                    $test_id = 42;
                    $test_name = 'Exam';
                    $test_description = 'Description';
                    $test_ip_range = '*';
                    $test_begin_time = '2026-09-10 00:00:00';
                    $test_end_time = '2026-09-30 00:00:00';
                    $test_results_to_users = false;
                    $test_report_to_users = true;
                    $new_test_password = '';
                    $sslcerts = [];
                    $db = null;
                    $l = ['m_updated' => 'updated', 'm_form_missing_fields' => 'missing', 'w_confirm' => 'confirm', 'w_update' => 'update'];
                    $GLOBALS['queries'] = [];
                    eval('namespace Harness; switch ("update") {' . $block . '}');
                    echo json_encode($GLOBALS['queries']);
                    HARNESS,
                dirname(__DIR__) . '/admin/code/tce_edit_test.php',
                json_encode([$hasAttempts, $groups, $confirmed], JSON_THROW_ON_ERROR),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /** @var list<string> $queries */
        $queries = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        if (!$confirmed) {
            self::assertSame([], $queries);
            return;
        }

        self::assertCount(3 + count($groups), $queries);
        $update = $queries[0] ?? '';
        self::assertStringContainsString('UPDATE tests', $update);
        self::assertStringContainsString('test_name=', $update);
        self::assertStringContainsString('test_end_time=', $update);
        self::assertStringContainsString('test_repeatable=', $update);
        self::assertStringContainsString("test_results_to_users='0'", $update);
        self::assertStringContainsString("test_report_to_users='1'", $update);
        if ($hasAttempts) {
            foreach ([
                'test_score_',
                'test_max_score',
                'test_duration_time',
                'test_random_',
                'test_password',
            ] as $protected) {
                self::assertStringNotContainsString($protected, $update);
            }
        } else {
            self::assertStringContainsString('test_score_right=', $update);
            self::assertStringContainsString('test_duration_time=', $update);
        }

        self::assertMatchesRegularExpression('/DELETE FROM test_groups\s+WHERE tstgrp_test_id=42/', $queries[1] ?? '');
        foreach ($groups as $index => $group) {
            self::assertMatchesRegularExpression(
                "/INSERT INTO test_groups.*VALUES\s*\(\s*'42',\s*'" . $group . "'\s*\)/s",
                $queries[$index + 2] ?? '',
            );
        }
        foreach ($queries as $query) {
            self::assertStringNotContainsString('attempts', $query);
        }
    }
}
