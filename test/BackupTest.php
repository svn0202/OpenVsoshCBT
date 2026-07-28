<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_backup.php';

final class BackupTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/openvsosh-backup-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }
    }

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

        self::assertNotContains($config['password'], $command);
        self::assertSame($config['password'], \F_tmf_backup_environment($config)['PGPASSWORD']);
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
            self::assertCount(1, glob($this->temporaryDirectory . '/*') ?: []);
        }
    }
}
