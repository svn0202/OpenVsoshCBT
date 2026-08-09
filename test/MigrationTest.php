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
        self::assertStringContainsString('BEGIN SELECT', $statements[0] ?? '');
        self::assertSame('CREATE INDEX idx ON x(id)', $statements[1] ?? null);
    }

    public function testManifestOrderIsStableAndComplete(): void
    {
        $files = \F_tmf_migration_files(__DIR__ . '/../install/upgrade/mysql');
        self::assertSame([
            'openvsosh_access_settings.sql',
            'openvsosh_word_import.sql',
            'openvsosh_answer_save.sql',
            'openvsosh_monitoring.sql',
            'openvsosh_focus_monitoring.sql',
            'openvsosh_pregeneration.sql',
            'openvsosh_offline.sql',
            'openvsosh_test_access.sql',
            'openvsosh_essay_attachments.sql',
            'openvsosh_question_shuffle.sql',
            'openvsosh_review_flag.sql',
            'openvsosh_roles.sql',
            'openvsosh_exam_display.sql',
            'openvsosh_user_card.sql',
            'openvsosh_result_publication.sql',
        ], array_map(static fn (string $path): string => basename($path), $files));
    }
}
