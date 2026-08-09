<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UploadFunctionsTest extends TestCase
{
    public function testAllowedUploadExtensionMatchingIsCaseInsensitive(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_ALLOWED_UPLOAD_EXTENSIONS", serialize(["jpg", "png"])); '
                    . 'require "tce_functions_upload.php"; '
                    . 'echo json_encode([f_is_allowed_upload("photo.JPG"), f_is_allowed_upload("shell.php")]);',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('[true,false]', $output);
    }
}
