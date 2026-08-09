<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../admin/code/tce_functions_filemanager.php';

final class FileManagerFunctionsTest extends TestCase
{
    public function testRejectsTraversalWhenDeletingMediaFile(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_DELETE_MEDIAFILE; '
                    . 'echo json_encode(f_delete_media_file("/tmp/../outside.txt"));',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }

    public function testAdministratorCanAccessEveryImmediateMediaDirectory(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMINISTRATOR; echo f_get_authorized_dirs();',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('[^/]*', $output);
    }

    public function testChecksMediaDirectoryAuthorizationAgainstConfiguredPattern(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . 'echo json_encode(['
                    . 'f_is_authorized_dir("/cache/alice/", "/cache/", "alice"), '
                    . 'f_is_authorized_dir("/cache/bob/", "/cache/", "alice"), '
                    . 'f_is_authorized_dir("/cache/bob/docs/", "/cache/", "alice|bob")]);',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('[true,false,true]', $output);
    }

    /** @throws Random\RandomException */
    public function testListsDirectoriesAndFilesInNaturalCaseInsensitiveOrder(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/tcexam-filemanager-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($temporaryDirectory));
        self::assertTrue(mkdir($temporaryDirectory . '/zeta'));
        self::assertTrue(mkdir($temporaryDirectory . '/Alpha'));
        self::assertNotFalse(file_put_contents($temporaryDirectory . '/zeta.txt', 'z'));
        self::assertNotFalse(file_put_contents($temporaryDirectory . '/alpha.txt', 'a'));

        try {
            $directory = $temporaryDirectory . '/';
            [$status, $output] = F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                        . '$data = f_get_dir_files($argv[1], $argv[1], "[^/]*"); '
                        . 'echo implode("\n", $data["dirs"]) . "\n--\n" . implode("\n", $data["files"]);',
                    $directory,
                ],
                __DIR__ . '/../admin/code',
            );

            self::assertSame(0, $status);
            self::assertSame(
                $temporaryDirectory
                    . "/Alpha\n"
                    . $temporaryDirectory
                    . "/zeta\n--\n"
                    . $temporaryDirectory
                    . "/alpha.txt\n"
                    . $temporaryDirectory
                    . '/zeta.txt',
                $output,
            );
        } finally {
            unlink($temporaryDirectory . '/zeta.txt');
            unlink($temporaryDirectory . '/alpha.txt');
            rmdir($temporaryDirectory . '/zeta');
            rmdir($temporaryDirectory . '/Alpha');
            rmdir($temporaryDirectory);
        }
    }

    public function testBuildsLinkedMediaDirectoryPath(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$_SERVER["SCRIPT_NAME"] = "/admin/code/tce_filemanager.php"; '
                    . 'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . '$l["w_change_dir"] = "Change directory"; '
                    . 'echo f_get_media_dir_path_link(K_PATH_CACHE . "alpha/beta/", false);',
            ],
            __DIR__ . '/../admin/code',
        );
        self::assertSame(0, $status);

        $cachePath = dirname(__DIR__) . '/cache/';
        self::assertSame(
            '<a href="/admin/code/tce_filemanager.php?d='
                . urlencode($cachePath)
                . '&amp;v=0" title="CACHE ROOT">[CACHE]</a> /'
                . ' <a href="/admin/code/tce_filemanager.php?d='
                . urlencode($cachePath . 'alpha/')
                . '&amp;v=0" title="Change directory">alpha</a> /'
                . ' <a href="/admin/code/tce_filemanager.php?d='
                . urlencode($cachePath . 'alpha/beta/')
                . '&amp;v=0" title="Change directory">beta</a> /',
            $output,
        );
    }

    public function testReturnsFileInformationWithStableScalarFields(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "tce_functions_filemanager.php"; $info = f_get_file_info($argv[1]); echo implode("\n", ['
                    . '$info["basename"], $info["extension"], $info["filename"], $info["dir"] ? "1" : "0", '
                    . '(string) $info["size"], $info["link"] ? "1" : "0", $info["aperms"]]);',
                __FILE__,
            ],
            __DIR__ . '/../admin/code',
        );
        self::assertSame(0, $status);

        $fields = explode("\n", $output);
        self::assertCount(7, $fields);
        self::assertSame(
            [
                'FileManagerFunctionsTest.php',
                'php',
                'FileManagerFunctionsTest',
                '0',
                (string) filesize(__FILE__),
                '0',
            ],
            array_slice($fields, 0, 6),
        );
        self::assertStringStartsWith('-', $fields[6] ?? '');
    }

    public function testFormatsFileSizesUsingLegacyUnitsAndRounding(): void
    {
        self::assertSame('0', f_format_file_size(0));
        self::assertSame('1 B ', f_format_file_size(1));
        self::assertSame('2 KB', f_format_file_size(1536));
        self::assertSame('1 MB', f_format_file_size(1024 * 1024));
        self::assertSame('1 KB', f_format_file_size('1024'));
    }
}
