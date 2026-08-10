<?php

//============================================================+
// File name   : DatabaseDalIntegrationTest.php
// Begin       : 2026-06-22
//
// Description : Integration tests for the Database Abstraction Layer (DAL)
//               run against a real database server.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

namespace Test\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @file
 * Integration tests for the Database Abstraction Layer (shared/code/tce_db_dal_*.php) against a
 * live, freshly-seeded database. They are driver-agnostic: the DAL implementation and connection
 * settings are selected from the TCEXAM_DB_* environment variables, so the same suite runs against
 * both MySQL/MariaDB and PostgreSQL. The Dockerised environment (`make dockertest`) sets those
 * variables; outside it (e.g. the host `make test`) every test self-skips.
 * @package com.tecnick.tcexam.test
 */
final class DatabaseDalIntegrationTest extends TestCase
{
    /** Prefix for rows created by this suite, so they can be isolated and cleaned up. */
    private const ROW_PREFIX = 'itest_';

    /** Live database link returned by F_db_connect(). */
    private mixed $db = null;

    /** Guards the one-time DAL include (the DAL declares global F_db_* functions). */
    private static bool $dalLoaded = false;

    protected function setUp(): void
    {
        $type = (string) getenv('TCEXAM_DB_TYPE');
        if ($type === '') {
            $this->markTestSkipped(
                'Integration database not configured: set TCEXAM_DB_* (run via `make dockertest`).'
            );
        }

        if ($type !== 'MYSQL' && $type !== 'POSTGRESQL') {
            $this->markTestSkipped('Unsupported TCEXAM_DB_TYPE: ' . $type);
        }

        self::loadDal($type);
        self::defineTableConstants();

        $this->db = \F_db_connect(
            (string) getenv('TCEXAM_DB_HOST'),
            (string) getenv('TCEXAM_DB_PORT'),
            (string) getenv('TCEXAM_DB_USER'),
            (string) getenv('TCEXAM_DB_PASSWORD'),
            (string) getenv('TCEXAM_DB_NAME')
        );

        $this->assertNotFalse($this->db, 'F_db_connect() should open a live connection');
    }

    protected function tearDown(): void
    {
        if (empty($this->db)) {
            return;
        }

        // Drop anything this suite inserted, then release the connection (best-effort).
        try {
            \F_db_query(
                'DELETE FROM ' . \K_TABLE_GROUPS . " WHERE group_name LIKE '" . self::ROW_PREFIX . "%'",
                $this->db
            );
            \F_db_close($this->db);
        } catch (\Throwable) {
            // cleanup failures are not test failures
        }

        $this->db = null;
    }

    private function dbScalar(string $sql): mixed
    {
        $result = \F_db_query($sql, $this->db);
        $this->assertNotFalse($result, $sql);
        $row = \F_db_fetch_array($result);
        return $row === false ? null : $row[0];
    }

    private function dbExec(string $sql): void
    {
        $this->assertNotFalse(\F_db_query($sql, $this->db), $sql);
    }

    private static function executablePath(string $binary): ?string
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binary;
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    /** Load the DAL implementation matching the configured database type. */
    private static function loadDal(string $type): void
    {
        if (self::$dalLoaded) {
            return;
        }

        $dal = match ($type) {
            'MYSQL' => __DIR__ . '/../../shared/code/tce_db_dal_mysqli.php',
            'POSTGRESQL' => __DIR__ . '/../../shared/code/tce_db_dal_postgresql.php',
            default => null,
        };

        if ($dal === null) {
            self::fail('Unsupported TCEXAM_DB_TYPE: ' . $type);
        }

        require_once $dal;
        self::$dalLoaded = true;
    }

    /** Define the subset of table-name constants these tests reference. */
    private static function defineTableConstants(): void
    {
        if (! \defined('K_TABLE_PREFIX')) {
            \define('K_TABLE_PREFIX', 'tce_');
        }

        if (! \defined('K_TABLE_USERS')) {
            \define('K_TABLE_USERS', \K_TABLE_PREFIX . 'users');
        }

        if (! \defined('K_TABLE_GROUPS')) {
            \define('K_TABLE_GROUPS', \K_TABLE_PREFIX . 'user_groups');
        }
        foreach ([
            'K_TABLE_MODULES' => 'modules',
            'K_TABLE_SUBJECTS' => 'subjects',
            'K_TABLE_QUESTIONS' => 'questions',
            'K_TABLE_ANSWERS' => 'answers',
        ] as $constant => $suffix) {
            if (\defined($constant)) {
                continue;
            }

            \define($constant, \K_TABLE_PREFIX . $suffix);
        }
    }

    public function testSeededUsersArePresent(): void
    {
        $res = \F_db_query(
            'SELECT user_name, user_level FROM ' . \K_TABLE_USERS . ' ORDER BY user_level',
            $this->db
        );
        $this->assertNotFalse($res, 'querying the seeded schema/data should succeed');

        $levels = [];
        $row = \F_db_fetch_assoc($res);
        while (is_array($row)) {
            $levels[$row['user_name']] = (int) $row['user_level'];
            $row = \F_db_fetch_assoc($res);
        }

        $this->assertGreaterThanOrEqual(2, \F_db_num_rows($res));
        $this->assertArrayHasKey('anonymous', $levels);
        $this->assertArrayHasKey('admin', $levels);
        $this->assertSame(0, $levels['anonymous']);
        $this->assertSame(10, $levels['admin']);
    }

    public function testEscapeSqlRoundTripsThroughTheServer(): void
    {
        // A value with the characters SQL injection relies on; after escaping it must survive a
        // round-trip through the server unchanged (proving the escaping matches the dialect).
        $raw = "O'Brien \"quote\" \\ end";
        $escaped = \F_escape_sql($this->db, $raw, false);

        $res = \F_db_query("SELECT '" . $escaped . "' AS v", $this->db);
        $this->assertNotFalse($res, 'a query embedding the escaped value should be valid SQL');

        $row = \F_db_fetch_assoc($res);
        $this->assertSame($raw, $row['v'], 'the escaped value must round-trip unchanged');
    }

    public function testInsertFetchAndDeleteRoundTrip(): void
    {
        $name = self::ROW_PREFIX . 'group_1';

        $ins = \F_db_query(
            'INSERT INTO ' . \K_TABLE_GROUPS . " (group_name) VALUES ('" . $name . "')",
            $this->db
        );
        $this->assertNotFalse($ins, 'INSERT should succeed');
        $this->assertSame(1, \F_db_affected_rows($this->db, $ins));

        $id = (int) \F_db_insert_id($this->db, \K_TABLE_GROUPS, 'group_id');
        $this->assertGreaterThan(0, $id, 'a generated id should be returned for the new row');

        $sel = \F_db_query(
            'SELECT group_id, group_name FROM ' . \K_TABLE_GROUPS . " WHERE group_name = '" . $name . "'",
            $this->db
        );
        $this->assertSame(1, \F_db_num_rows($sel));

        $found = \F_db_fetch_assoc($sel);
        $this->assertSame($name, $found['group_name']);
        $this->assertSame($id, (int) $found['group_id']);

        $del = \F_db_query(
            'DELETE FROM ' . \K_TABLE_GROUPS . " WHERE group_name = '" . $name . "'",
            $this->db
        );
        $this->assertNotFalse($del);
        $this->assertSame(1, \F_db_affected_rows($this->db, $del));
    }

    public function testDatetimeDiffSecondsExpressionEvaluates(): void
    {
        // The DAL emits a dialect-specific seconds-difference expression; verify it is valid SQL
        // and computes the expected value (60s) on the live server.
        if ((string) getenv('TCEXAM_DB_TYPE') === 'POSTGRESQL') {
            $expr = \F_db_datetime_diff_seconds(
                "TIMESTAMP '2024-01-01 00:00:00'",
                "TIMESTAMP '2024-01-01 00:01:00'"
            );
        } else {
            $expr = \F_db_datetime_diff_seconds("'2024-01-01 00:00:00'", "'2024-01-01 00:01:00'");
        }

        $res = \F_db_query('SELECT ' . $expr . ' AS d', $this->db);
        $this->assertNotFalse($res);

        $row = \F_db_fetch_assoc($res);
        $this->assertSame(60, (int) $row['d']);
    }

    public function testAnswerVersionColumnsAreAvailable(): void
    {
        $res = \F_db_query(
            'SELECT testlog_answer_version, testlog_answer_operation FROM tce_tests_logs WHERE 1=0',
            $this->db
        );

        $this->assertNotFalse($res, 'fresh schemas must include versioned answer-save columns');
    }

    public function testPerQuestionShuffleColumnIsAvailable(): void
    {
        $res = \F_db_query(
            'SELECT question_shuffle_answers FROM tce_questions WHERE 1=0',
            $this->db
        );

        $this->assertNotFalse($res, 'fresh schemas must include the per-question shuffle flag');
    }

    public function testServerReviewFlagIsAvailable(): void
    {
        $res = \F_db_query(
            'SELECT testlog_reviewed FROM tce_tests_logs WHERE 1=0',
            $this->db
        );

        $this->assertNotFalse($res, 'fresh schemas must include the server-side review flag');
    }

    public function testExamDisplayControlsAreAvailable(): void
    {
        $res = \F_db_query(
            'SELECT test_live_score,test_auto_fullscreen,test_hide_exam_info FROM tce_tests WHERE 1=0',
            $this->db
        );

        $this->assertNotFalse($res, 'fresh schemas must include the optional exam display controls');
    }

    public function testParticipantCardFieldsAreAvailable(): void
    {
        $res = \F_db_query(
            'SELECT user_note,user_schedule FROM tce_users WHERE 1=0',
            $this->db
        );

        $this->assertNotFalse($res, 'fresh schemas must include participant card text fields');
    }

    public function testMigrationCliBaselinesAndVerifiesFreshSchema(): void
    {
        $command = [PHP_BINARY, __DIR__ . '/../../install/migrate.php', '--baseline'];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $stderr);
        $this->assertStringContainsString('complete; pending handled:', $stdout);

        $result = \F_db_query('SELECT COUNT(*) AS n FROM tce_schema_migrations', $this->db);
        $row = \F_db_fetch_assoc($result);
        $this->assertSame(15, (int) $row['n']);

        $verify = proc_open(
            [PHP_BINARY, __DIR__ . '/../../install/migrate.php', '--dry-run'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $verifyPipes,
            dirname(__DIR__, 2)
        );
        $this->assertIsResource($verify);
        $verifyOut = stream_get_contents($verifyPipes[1]);
        $verifyErr = stream_get_contents($verifyPipes[2]);
        fclose($verifyPipes[1]);
        fclose($verifyPipes[2]);
        $this->assertSame(0, proc_close($verify), $verifyErr);
        $this->assertStringContainsString('already applied openvsosh_result_publication.sql', $verifyOut);
        $this->assertStringContainsString('pending handled: 0', $verifyOut);
    }

    public function testMonitoringSchemaIsAvailable(): void
    {
        $attempt = \F_db_query(
            'SELECT testuser_last_activity, testuser_close_reason, testuser_generation_hash, '
            . 'testuser_pregenerated, testuser_focus_loss_count, testuser_last_focus_event '
            . 'FROM tce_tests_users WHERE 1=0',
            $this->db
        );
        $answer = \F_db_query(
            'SELECT testlog_answer_saved_at FROM tce_tests_logs WHERE 1=0',
            $this->db
        );
        $audit = \F_db_query(
            'SELECT monitor_audit_time, monitor_action, monitor_details FROM tce_monitor_audit WHERE 1=0',
            $this->db
        );
        $offline = \F_db_query(
            'SELECT offline_payload_hash, offline_status, offline_result_hash '
            . 'FROM tce_offline_packages WHERE 1=0',
            $this->db
        );

        $this->assertNotFalse($attempt, 'fresh schemas must include attempt monitoring columns');
        $this->assertNotFalse($answer, 'fresh schemas must include answer activity timestamps');
        $this->assertNotFalse($audit, 'fresh schemas must include the immutable monitoring audit');
        $this->assertNotFalse($offline, 'fresh schemas must include signed offline package records');
    }

    public function testResultPublicationSchemaIsAvailable(): void
    {
        $result = \F_db_query(
            'SELECT test_results_publish_at, test_results_unpublish_at, test_results_anonymized '
            . 'FROM tce_tests WHERE 1=0',
            $this->db,
        );
        $this->assertNotFalse($result);
    }

    /**
     * @throws \Random\RandomException
     */
    public function testWordImportCanCommitAndRollBackRealDatabaseWrites(): void
    {
        require_once __DIR__ . '/../../admin/code/tmf_word_import_lib.php';
        require_once __DIR__ . '/../../admin/code/tmf_word_import_db.php';

        $suffix = bin2hex(random_bytes(6));
        $moduleName = self::ROW_PREFIX . 'word_module_' . $suffix;
        $rollbackTopic = self::ROW_PREFIX . 'word_rollback_' . $suffix;
        $commitTopic = self::ROW_PREFIX . 'word_commit_' . $suffix;
        $adminId = (int) $this->dbScalar("SELECT user_id FROM tce_users WHERE user_name='admin'");
        // @mago-expect lint:no-global -- integration test wires the connection expected by the legacy DB helpers
        global $db;
        $db = $this->db;
        $_SESSION['session_user_id'] = $adminId;
        $docx = tempnam(sys_get_temp_dir(), 'openvsosh-word-db-');
        $this->assertNotFalse($docx);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($docx, \ZipArchive::OVERWRITE) === true);
        $paragraphs = [
            'MODULE:=' . $moduleName,
            'TOPIC:=' . $rollbackTopic,
            'Q:1) [[DIFFICULTY=2]] Integration question',
            'A:) Right [[WEIGHT=100]]',
            'B:) Wrong',
            'RIGHT:A',
        ];
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= '<w:p><w:r><w:t>'
                . htmlspecialchars($paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '</w:t></w:r></w:p>';
        }
        $this->assertTrue($zip->addFromString(
            'word/document.xml',
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body . '</w:body></w:document>',
        ));
        $zip->close();
        try {
            $parsed = (new \TmfWordImporter($docx))->parse();
        } finally {
            unlink($docx);
        }
        $this->assertSame($moduleName, $parsed['module']);
        $this->assertSame($rollbackTopic, $parsed['topic']);
        $this->assertSame(['A'], $parsed['questions'][0]['right_keys'] ?? null);

        $rolledBack = \F_tmf_import_word_questions($parsed, false);
        $this->assertFalse($rolledBack['committed']);
        $this->assertSame(
            '0',
            $this->dbScalar(
                "SELECT COUNT(*) FROM tce_modules WHERE module_name='" . $moduleName . "'",
            ),
        );

        try {
            $parsed['topic'] = $commitTopic;
            $committed = \F_tmf_import_word_questions($parsed);
            $this->assertTrue($committed['committed']);
            $this->assertSame(1, $committed['questions']);
            $this->assertSame(2, $committed['answers']);
            $this->assertSame(
                '2',
                $this->dbScalar(
                    "SELECT COUNT(*) FROM tce_answers a JOIN tce_questions q "
                    . 'ON q.question_id=a.answer_question_id JOIN tce_subjects s '
                    . 'ON s.subject_id=q.question_subject_id WHERE s.subject_name=\'' . $commitTopic . "'",
                ),
            );
        } finally {
            $moduleId = (int) ($this->dbScalar(
                "SELECT module_id FROM tce_modules WHERE module_name='" . $moduleName . "'",
            ) ?? '0');
            if ($moduleId > 0) {
                $this->dbExec(
                    'DELETE FROM tce_answers WHERE answer_question_id IN (SELECT question_id FROM tce_questions '
                    . 'WHERE question_subject_id IN (SELECT subject_id FROM tce_subjects '
                    . 'WHERE subject_module_id=' . $moduleId . '))',
                );
                $this->dbExec(
                    'DELETE FROM tce_questions WHERE question_subject_id IN (SELECT subject_id FROM tce_subjects '
                    . 'WHERE subject_module_id=' . $moduleId . ')',
                );
                $this->dbExec('DELETE FROM tce_subjects WHERE subject_module_id=' . $moduleId);
                $this->dbExec('DELETE FROM tce_modules WHERE module_id=' . $moduleId);
            }
        }
    }

    /**
     * @throws \Random\RandomException
     */
    public function testDatabaseBackupCanActuallyRestoreDisposableDatabase(): void
    {
        require_once __DIR__ . '/../../shared/code/tce_functions_backup.php';

        $type = (string) getenv('TCEXAM_DB_TYPE');
        $dumpBinary = self::executablePath($type === 'POSTGRESQL' ? 'pg_dump' : 'mysqldump');
        $restoreBinary = self::executablePath($type === 'POSTGRESQL' ? 'pg_restore' : 'mysql');
        if ($dumpBinary === null || $restoreBinary === null) {
            self::markTestSkipped('Database backup clients are not installed.');
        }

        $databaseName = 'itest_backup_' . bin2hex(random_bytes(5));
        $quotedDatabase = $type === 'POSTGRESQL'
            ? '"' . $databaseName . '"'
            : '`' . $databaseName . '`';
        $backupDirectory = sys_get_temp_dir() . '/openvsosh-backup-integration-' . bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($backupDirectory, 0o700));
        $archive = null;
        $targetDb = null;
        $controlDb = $this->db;
        $separateControlDb = false;
        $config = [
            'type' => $type,
            'host' => (string) getenv('TCEXAM_DB_HOST'),
            'port' => (string) getenv('TCEXAM_DB_PORT'),
            'name' => $databaseName,
            'user' => (string) getenv('TCEXAM_DB_USER'),
            'password' => (string) getenv('TCEXAM_DB_PASSWORD'),
        ];
        if ($type === 'POSTGRESQL') {
            $config['pg_dump_binary'] = $dumpBinary;
            $config['pg_restore_binary'] = $restoreBinary;
        } else {
            $config['mysqldump_binary'] = $dumpBinary;
            $config['mysql_binary'] = $restoreBinary;
            $adminUser = (string) getenv('TCEXAM_DB_ADMIN_USER');
            if ($adminUser !== '') {
                $controlDb = \F_db_connect(
                    $config['host'],
                    $config['port'],
                    $adminUser,
                    (string) getenv('TCEXAM_DB_ADMIN_PASSWORD'),
                    (string) getenv('TCEXAM_DB_NAME'),
                );
                $this->assertNotFalse($controlDb);
                $separateControlDb = true;
            }
        }

        try {
            $this->assertNotFalse(\F_db_query('CREATE DATABASE ' . $quotedDatabase, $controlDb));
            if ($type !== 'POSTGRESQL' && $separateControlDb) {
                $applicationUser = \F_escape_sql(
                    $controlDb,
                    (string) getenv('TCEXAM_DB_USER'),
                );
                $this->assertNotFalse(\F_db_query(
                    'GRANT ALL PRIVILEGES ON ' . $quotedDatabase . ".* TO '" . $applicationUser . "'@'%'",
                    $controlDb,
                ));
            }
            $targetDb = \F_db_connect(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['name'],
            );
            $this->assertNotFalse($targetDb);
            $this->assertNotFalse(\F_db_query(
                'CREATE TABLE backup_probe (probe_value VARCHAR(32) NOT NULL)',
                $targetDb,
            ));
            $this->assertNotFalse(\F_db_query(
                "INSERT INTO backup_probe (probe_value) VALUES ('before')",
                $targetDb,
            ));
            \F_db_close($targetDb);
            $targetDb = null;

            $archive = \F_tmf_backup_create($config, $backupDirectory, '20260727120000');
            $this->assertFileExists($archive);
            $this->assertGreaterThan(0, filesize($archive));

            $targetDb = \F_db_connect(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['name'],
            );
            $this->assertNotFalse($targetDb);
            $this->assertNotFalse(\F_db_query(
                "UPDATE backup_probe SET probe_value='after'",
                $targetDb,
            ));
            \F_db_close($targetDb);
            $targetDb = null;

            \F_tmf_backup_restore($config, $archive);
            $targetDb = \F_db_connect(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['name'],
            );
            $this->assertNotFalse($targetDb);
            $result = \F_db_query('SELECT probe_value FROM backup_probe', $targetDb);
            $this->assertNotFalse($result);
            $row = \F_db_fetch_array($result);
            $this->assertSame('before', $row[0] ?? null);
        } finally {
            if ($targetDb !== null) {
                \F_db_close($targetDb);
            }
            if ($type === 'POSTGRESQL') {
                $this->dbExec(
                    "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='"
                    . $databaseName . "' AND pid <> pg_backend_pid()"
                );
            }
            $this->assertNotFalse(\F_db_query(
                'DROP DATABASE IF EXISTS ' . $quotedDatabase,
                $controlDb,
            ));
            if ($separateControlDb) {
                \F_db_close($controlDb);
            }
            if (is_string($archive) && is_file($archive)) {
                unlink($archive);
            }
            if (is_dir($backupDirectory)) {
                rmdir($backupDirectory);
            }
        }
    }
}
