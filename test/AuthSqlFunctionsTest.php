<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class AuthSqlFunctionsTest extends TestCase
{
    public function testAdministratorQueriesPreserveSqlStructure(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "../config/tce_db_config.php"; '
                    . 'require_once "../../admin/config/tce_auth.php"; '
                    . 'require_once "tce_functions_auth_sql.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; '
                    . '$queries = ['
                    . 'F_select_modules_sql("module_enabled=1"), '
                    . 'F_select_subjects_sql("subject_enabled=1"), '
                    . 'F_select_module_subjects_sql("subject_enabled=1"), '
                    . 'F_select_tests_sql(), F_select_executed_tests_sql()]; '
                    . 'echo implode("\n---\n", array_map('
                    . 'static fn($sql) => preg_replace("/\\s+/", " ", trim($sql)), $queries));',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame(
            "SELECT * FROM tce_modules WHERE module_enabled=1 ORDER BY module_name\n"
                . "---\n"
                . 'SELECT * FROM tce_modules,tce_subjects WHERE module_id=subject_module_id '
                . "AND subject_enabled=1 ORDER BY module_name,subject_name\n"
                . "---\n"
                . 'SELECT * FROM tce_modules,tce_subjects WHERE module_id=subject_module_id '
                . "AND subject_enabled=1 ORDER BY module_name,subject_name\n"
                . "---\n"
                . "SELECT * FROM tce_tests ORDER BY test_begin_time DESC, test_name\n"
                . "---\n"
                . 'SELECT * FROM tce_tests WHERE test_id IN ( SELECT testuser_test_id FROM tce_tests_users '
                . 'WHERE testuser_status>0 ) ORDER BY test_begin_time DESC, test_name',
            $output,
        );
    }

    public function testRegularUserQueriesPreserveAuthorizationFilters(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "../config/tce_db_config.php"; '
                    . 'require_once "../../admin/config/tce_auth.php"; '
                    . 'function f_get_authorized_users($user_id) { $GLOBALS["user_ids"][] = $user_id; return "7,42"; } '
                    . 'require_once "tce_functions_auth_sql.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR - 1; '
                    . '$_SESSION["session_user_id"] = 42; '
                    . '$queries = ['
                    . 'F_select_modules_sql("module_enabled=1"), '
                    . 'F_select_module_subjects_sql("subject_enabled=1"), '
                    . 'F_select_tests_sql(), F_select_executed_tests_sql()]; '
                    . 'echo json_encode([$GLOBALS["user_ids"], array_map('
                    . 'static fn($sql) => preg_replace("/\\s+/", " ", trim($sql)), $queries)]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                [42, 42, 42, 42],
                [
                    'SELECT * FROM tce_modules WHERE module_user_id IN (7,42) '
                        . 'AND module_enabled=1 ORDER BY module_name',
                    'SELECT * FROM tce_modules,tce_subjects WHERE module_id=subject_module_id '
                        . 'AND (module_user_id IN (7,42) OR subject_user_id IN (7,42)) '
                        . 'AND subject_enabled=1 ORDER BY module_name,subject_name',
                    'SELECT * FROM tce_tests WHERE test_user_id IN (7,42) '
                        . 'ORDER BY test_begin_time DESC, test_name',
                    'SELECT * FROM tce_tests WHERE test_id IN ( SELECT testuser_test_id FROM tce_tests_users '
                        . 'WHERE testuser_status>0 ) AND test_user_id IN (7,42) '
                        . 'ORDER BY test_begin_time DESC, test_name',
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
