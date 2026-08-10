<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UploadFunctionsTest extends TestCase
{
    public function testUploadRejectsHiddenFilenameBeforeMovingFile(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_upload.php"; '
                    . '$_FILES["userfile"] = ["name" => ".hidden", "tmp_name" => "/tmp/not-uploaded"]; '
                    . 'echo json_encode(f_upload_file("userfile", K_PATH_CACHE));',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }

    public function testReadsLocalFileSizeIncludingLegacyEmptyFileResult(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_upload.php"; '
                    . '$full = tempnam(sys_get_temp_dir(), "tce-upload-full-"); '
                    . '$empty = tempnam(sys_get_temp_dir(), "tce-upload-empty-"); '
                    . 'file_put_contents($full, "abc"); '
                    . 'try { echo json_encode([f_read_file_size($full), f_read_file_size($empty)]); } '
                    . 'finally { unlink($full); unlink($empty); }',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('[3,1]', $output);
    }

    public function testAllowedUploadExtensionMatchingIsCaseInsensitive(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'error_reporting(0); define("K_ALLOWED_UPLOAD_EXTENSIONS", serialize(["jpg", "png"])); '
                    . 'require "tce_functions_upload.php"; '
                    . 'echo json_encode([f_is_allowed_upload("photo.JPG"), '
                    . 'f_is_allowed_upload("shell.php"), f_is_allowed_upload("README")]);',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('[true,false,false]', $output);
    }
}
