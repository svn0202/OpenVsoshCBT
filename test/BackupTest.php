<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_backup.php';

final class BackupTest extends TestCase
{
    private string $temporaryDirectory;

    /** @throws \Random\RandomException */
    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/openvsosh-backup-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory, 0o700));
    }

    protected function tearDown(): void
    {
        $paths = glob($this->temporaryDirectory . '/*');
        foreach ($paths === false ? [] : $paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }
        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }
    }

    /** @throws \TmfBackupException */
    public function testBackupFilenameValidationRejectsTraversalAndSymlinks(): void
    {
        self::assertTrue(\F_tmf_backup_file_is_valid('20260727120000_tcexam_backup.sql.gz'));
        self::assertTrue(\F_tmf_backup_file_is_valid('20260727120000_tcexam_backup.tar.gz'));
        self::assertFalse(\F_tmf_backup_file_is_valid('../20260727120000_tcexam_backup.sql.gz'));
        self::assertFalse(\F_tmf_backup_file_is_valid('20260727120000_other.sql.gz'));

        $outside = tempnam(sys_get_temp_dir(), 'openvsosh-backup-outside-');
        self::assertNotFalse($outside);
        $link = $this->temporaryDirectory . '/20260727120000_tcexam_backup.sql.gz';
        self::assertTrue(symlink($outside, $link));
        try {
            $this->expectException(\TmfBackupException::class);
            \F_tmf_backup_resolve_file(
                $this->temporaryDirectory,
                basename($link),
            );
        } finally {
            unlink($link);
            unlink($outside);
        }
    }

    /** @throws \TmfBackupException */
    public function testMissingBackupArchiveIsRejected(): void
    {
        $this->expectException(\TmfBackupException::class);
        $this->expectExceptionMessage('Резервная копия недоступна для чтения.');

        \F_tmf_backup_restore(
            [
                'type' => 'MYSQL',
                'host' => 'db',
                'port' => '3306',
                'name' => 'exam',
                'user' => 'backup',
                'password' => 'secret',
            ],
            $this->temporaryDirectory . '/missing.sql.gz',
        );
    }

    /**
     * @throws \TmfBackupException
     * @throws \ValueError
     */
    public function testEmptyBackupInputPathPreservesValueError(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Path must not be empty');

        $pipes = [];
        \F_tmf_backup_start_process(['/bin/echo'], [], $pipes, '');
    }

    public function testCommandArgumentsNeverContainDatabasePassword(): void
    {
        $config = [
            'type' => 'POSTGRESQL',
            'host' => 'db.example',
            'port' => '5432',
            'name' => 'exam',
            'user' => 'backup',
            'password' => 'secret with shell characters $()`',
        ];
        $command = \F_tmf_backup_dump_command($config);
        $environment = \F_tmf_backup_environment($config);

        self::assertNotContains($config['password'], $command);
        self::assertArrayHasKey('PGPASSWORD', $environment);
        self::assertSame($config['password'], $environment['PGPASSWORD'] ?? null);
    }

    public function testBackupConfigPreservesDatabaseConstants(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_db_config.php"; '
                    . 'require_once "tce_functions_backup.php"; '
                    . '$config = F_tmf_backup_config_from_constants(); '
                    . 'echo gettype($config["port"]) . ":" '
                    . '. ($config["port"] === K_DATABASE_PORT ? "same" : "different");',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('string:same', $output);
    }

    public function testOnlyHarmlessPostgresqlVersionMismatchDiagnosticIsIgnored(): void
    {
        $diagnostic = 'pg_restore: error: could not execute query: ERROR:  '
            . "unrecognized configuration parameter \"transaction_timeout\"\n"
            . "Command was: SET transaction_timeout = 0;\n"
            . 'pg_restore: warning: errors ignored on restore: 1';

        self::assertTrue(\F_tmf_backup_ignorable_postgresql_restore_diagnostic($diagnostic));
        self::assertFalse(\F_tmf_backup_ignorable_postgresql_restore_diagnostic(
            $diagnostic . "\npg_restore: error: relation \"backup_probe\" does not exist",
        ));
        self::assertFalse(\F_tmf_backup_ignorable_postgresql_restore_diagnostic(
            'pg_restore: error: connection to server failed',
        ));
    }

    /** @throws \Throwable */
    public function testCheckedBackupStreamsOutputAndRemovesFailedArchive(): void
    {
        $config = [
            'type' => 'MYSQL',
            'host' => 'db',
            'port' => '3306',
            'name' => 'exam',
            'user' => 'backup',
            'password' => 'secret',
            'mysqldump_binary' => '/bin/echo',
        ];
        $path = \F_tmf_backup_create($config, $this->temporaryDirectory, '20260727120000');
        self::assertSame(
            '20260727120000_tcexam_backup.sql.gz',
            basename($path),
        );
        self::assertStringContainsString('--opt', (string) file_get_contents('compress.zlib://' . $path));

        $config['mysqldump_binary'] = '/definitely/missing/openvsosh-dump';
        try {
            \F_tmf_backup_create($config, $this->temporaryDirectory, '20260727120001');
            self::fail('Missing database utility must fail.');
        } catch (\TmfBackupException) {
            self::assertFileDoesNotExist(
                $this->temporaryDirectory . '/20260727120001_tcexam_backup.sql.gz',
            );
        }
    }

    /** @throws \Throwable */
    public function testBackupNeverOverwritesArchiveWithTheSameTimestamp(): void
    {
        $config = [
            'type' => 'MYSQL',
            'host' => 'db',
            'port' => '3306',
            'name' => 'exam',
            'user' => 'backup',
            'password' => 'secret',
            'mysqldump_binary' => '/bin/echo',
        ];
        $path = \F_tmf_backup_create($config, $this->temporaryDirectory, '20260727120000');
        $original = file_get_contents($path);

        try {
            \F_tmf_backup_create($config, $this->temporaryDirectory, '20260727120000');
            self::fail('A backup must not overwrite an archive from the same second.');
        } catch (\TmfBackupException) {
            self::assertSame($original, file_get_contents($path));
            $backupFiles = glob($this->temporaryDirectory . '/*');
            self::assertCount(1, $backupFiles === false ? [] : $backupFiles);
        }
    }
}
