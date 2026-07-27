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
}
