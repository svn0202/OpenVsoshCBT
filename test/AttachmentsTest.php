<?php

namespace Test;

use PHPUnit\Framework\TestCase;

if (!defined('K_TABLE_PREFIX')) {
    define('K_TABLE_PREFIX', 'tce_');
}
if (!defined('K_PATH_CACHE')) {
    define('K_PATH_CACHE', __DIR__ . '/../cache/');
}
require_once __DIR__ . '/../shared/code/tce_functions_attachments.php';

final class AttachmentsTest extends TestCase
{
    public function testRealPngIsAcceptedFromItsContents(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'openvsosh-attachment-');
        self::assertNotFalse($file);
        file_put_contents(
            $file,
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            ),
        );
        try {
            $metadata = \F_tmf_attachment_inspect($file, "../\0photo.png");
        } finally {
            unlink($file);
        }
        self::assertSame('image/png', $metadata['mime']);
        self::assertSame('photo.png', $metadata['original_name']);
        self::assertSame(64, strlen($metadata['sha256']));
    }

    public function testExecutableContentIsRejectedEvenWithImageName(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'openvsosh-attachment-');
        self::assertNotFalse($file);
        file_put_contents($file, "<?php echo 'unsafe';");
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('JPEG, PNG и PDF');
            \F_tmf_attachment_inspect($file, 'camera.jpg');
        } finally {
            unlink($file);
        }
    }

    public function testUploadArraysAreNormalizedAndEmptySlotsIgnored(): void
    {
        $files = [
            'name' => ['one.png', ''],
            'tmp_name' => ['/tmp/one', ''],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
            'size' => [10, 0],
        ];
        self::assertSame([[
            'name' => 'one.png',
            'tmp_name' => '/tmp/one',
            'error' => UPLOAD_ERR_OK,
            'size' => 10,
        ]], \F_tmf_attachment_normalize_uploads($files));
    }

    public function testAttachmentHtmlKeepsEscapingLinksAndImagePreview(): void
    {
        $result = self::runDatabaseOperation('html');

        self::assertSame(
            'SELECT * FROM tce_testlog_attachments WHERE attachment_testlog_id=77 ORDER BY attachment_id',
            $result['queries'][0] ?? null,
        );
        self::assertStringContainsString('&lt;photo&gt;.png', $result['html']);
        self::assertStringContainsString('tce_attachment.php?id=5', $result['html']);
        self::assertStringContainsString('tce_attachment.php?id=5&amp;inline=1', $result['html']);
        self::assertStringContainsString('(2.0 КБ)', $result['html']);
    }

    public function testAttemptDeletionKeepsMetadataAndFileRemovalContract(): void
    {
        $result = self::runDatabaseOperation('delete');

        self::assertStringContainsString('WHERE tl.testlog_testuser_id=31', $result['queries'][0] ?? '');
        self::assertSame(
            'DELETE FROM tce_testlog_attachments WHERE attachment_testlog_id IN '
                . '(SELECT testlog_id FROM test_logs WHERE testlog_testuser_id=31)',
            $result['queries'][1] ?? null,
        );
        self::assertSame(['/cache/attachments/' . str_repeat('a', 64)], $result['deleted']);
    }

    /** @return array{html: string, queries: list<string>, deleted: list<string>} */
    private static function runDatabaseOperation(string $operation): array
    {
        $script = <<<'PHP'
namespace Harness;
define('K_TABLE_PREFIX', 'tce_');
define('K_PATH_CACHE', '/cache/');
define('K_TABLE_TESTS_LOGS', 'test_logs');
define('K_TABLE_TEST_USER', 'test_users');
define('K_TABLE_QUESTIONS', 'questions');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
$db = 'db';
$l = ['a_meta_charset' => 'UTF-8'];
$GLOBALS['queries'] = [];
$GLOBALS['row_index'] = 0;
$GLOBALS['deleted'] = [];
function F_db_query($sql, $db) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = $sql;
    if (str_starts_with($sql, 'DELETE')) { return true; }
    return fopen('php://memory', 'r');
}
function F_db_fetch_array($result) {
    $rows = [[
        'attachment_id' => 5, 'attachment_user_id' => 9,
        'attachment_stored_name' => str_repeat('a', 64),
        'attachment_original_name' => '<photo>.png', 'attachment_mime' => 'image/png',
        'attachment_size' => 2048, 'attachment_sha256' => str_repeat('b', 64),
        'testuser_test_id' => 7,
    ]];
    return $rows[$GLOBALS['row_index']++] ?? false;
}
function is_file($path) { return true; }
function unlink($path) { $GLOBALS['deleted'][] = $path; return true; }
$source = file_get_contents($argv[1]);
$source = preg_replace('/^<\?php\s*/', '', $source);
eval('namespace Harness; ' . $source);
$html = '';
if ($argv[2] === 'html') { $html = f_tmf_attachment_html(77); }
else { f_tmf_attachment_delete_attempt(31); }
echo json_encode([
    'html' => $html, 'queries' => $GLOBALS['queries'], 'deleted' => $GLOBALS['deleted'],
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                $script,
                dirname(__DIR__) . '/shared/code/tce_functions_attachments.php',
                $operation,
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        /** @var array{html: string, queries: list<string>, deleted: list<string>} */
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
