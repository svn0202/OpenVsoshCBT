<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../install/tce_functions_migrate.php';

final class MigrationTest extends TestCase
{
    public function testSqlSplitterPreservesQuotedSemicolon(): void
    {
        self::assertSame(
            ["INSERT INTO x VALUES ('a;b')", 'UPDATE x SET y=1'],
            \F_tmf_migration_statements("INSERT INTO x VALUES ('a;b'); UPDATE x SET y=1;", 'MYSQL'),
        );
    }

    public function testOracleTriggerIsOneStatement(): void
    {
        $sql = 'CREATE OR REPLACE TRIGGER trg BEFORE INSERT ON x FOR EACH ROW '
            . 'BEGIN SELECT seq.nextval INTO :new.id FROM DUAL; END;; CREATE INDEX idx ON x(id);';
        $statements = \F_tmf_migration_statements($sql, 'ORACLE');
        self::assertCount(2, $statements);
        self::assertStringContainsString('BEGIN SELECT', $statements[0]);
        self::assertSame('CREATE INDEX idx ON x(id)', $statements[1]);
    }

    public function testManifestOrderIsStableAndComplete(): void
    {
        $files = \F_tmf_migration_files(__DIR__ . '/../install/upgrade/mysql');
        self::assertSame('openvsosh_access_settings.sql', basename($files[0]));
        self::assertSame('openvsosh_result_publication.sql', basename($files[array_key_last($files)]));
        self::assertSame('openvsosh_roles.sql', basename($files[11]));
        self::assertSame('openvsosh_focus_monitoring.sql', basename($files[4]));
        self::assertCount(15, $files);
    }
}
