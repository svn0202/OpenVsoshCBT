<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../admin/code/tce_functions_filemanager.php';

final class FileManagerFunctionsTest extends TestCase
{
    public function testUsedMediaFileDetectionQueryRemainsUnchanged(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'namespace Harness; require_once "../config/tce_config.php"; $GLOBALS["calls"] = []; '
                    . 'function F_escape_sql($db, $value) { $GLOBALS["calls"]["escaped"] = $value; return $value; } '
                    . 'function F_db_query($query, $db) { '
                    . '$GLOBALS["calls"]["queries"][] = preg_replace("/\\s+/", " ", trim($query)); return "result"; } '
                    . 'function F_db_fetch_array($result) { return ["question_id" => 1]; } '
                    . '$source = file_get_contents($argv[1]); '
                    . 'preg_match("/function (F_isUsedMediaFile|f_is_used_media_file)\\(/", '
                    . '$source, $match, PREG_OFFSET_CAPTURE); '
                    . '$name = $match[1][0]; $start = $match[0][1]; '
                    . '$end = strpos($source, "\\n/**", $start); '
                    . 'eval("namespace Harness; " . substr($source, $start, $end - $start)); '
                    . '$qualifiedName = __NAMESPACE__ . "\\\\" . $name; '
                    . '$result = $qualifiedName(K_PATH_CACHE . "media/a.png"); '
                    . 'echo json_encode(["result" => $result, "calls" => $GLOBALS["calls"]]);',
                __DIR__ . '/../admin/code/tce_functions_filemanager.php',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            [
                'result' => true,
                'calls' => [
                    'escaped' => 'media/a.png',
                    'queries' => [
                        "SELECT question_id FROM tce_questions WHERE question_description LIKE '%media/a.png"
                            . "[/object%' OR question_explanation LIKE '%media/a.png[/object%' LIMIT 1",
                    ],
                ],
            ],
            json_decode($output, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /** @throws Random\RandomException */
    public function testRendersEmptyMediaDirectoryInBothDisplayModes(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/tcexam-render-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($temporaryDirectory));

        try {
            [$status, $output] = F_tcecode_run_process(
                [
                    PHP_BINARY,
                    '-r',
                    '$_SERVER["SCRIPT_NAME"] = "/files.php"; require "../config/tce_config.php"; '
                        . 'require "tce_functions_filemanager.php"; $_SESSION["session_user_id"] = 7; '
                        . '$l["w_directory"] = "Directory"; $l["w_name"] = "Name"; $l["w_size"] = "Size"; '
                        . '$l["w_datetime_format"] = "Date format"; $l["w_date"] = "Date"; '
                        . '$l["w_permissions"] = "Permissions"; '
                        . 'echo base64_encode(f_get_dir_table($argv[1], "", "", $argv[1], "[^/]*")), "\n", '
                        . 'base64_encode(f_get_dir_visual_table($argv[1], "", "", $argv[1], "[^/]*"));',
                    $temporaryDirectory . '/',
                ],
                __DIR__ . '/../admin/code',
            );
            self::assertSame(0, $status);

            $table = '<table class="filemanager">' . "\n"
                . '<caption class="sr-only">Directory</caption><thead><tr><th scope="col">Name</th>'
                . '<th scope="col">Size</th><th scope="col" title="Date format">Date</th>'
                . '<th scope="col">Permissions</th></tr>' . "\n"
                . '</thead></table>' . "\n";
            self::assertSame(
                base64_encode($table) . "\n" . base64_encode('<br style="clear:both;" />'),
                $output,
            );
        } finally {
            rmdir($temporaryDirectory);
        }
    }

    public function testRejectsDirectoryDeletionOutsideMediaCache(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMIN_DIRS; '
                    . 'echo json_encode(f_delete_media_dir("/tmp/outside/"));',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }

    public function testMissingMediaDirectoryCannotBeDeleted(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMIN_DIRS; '
                    . 'echo json_encode(f_delete_media_dir(K_PATH_CACHE . "missing-directory/"));',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('false', $output);
    }

    public function testRejectsTraversalWhenCreatingMediaDirectory(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_ADMIN_DIRS; '
                    . 'echo json_encode(f_create_media_dir("/tmp/../outside"));',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }

    public function testRejectsTraversalWhenRenamingMediaFile(): void
    {
        [$status, $output] = F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "../config/tce_config.php"; require "tce_functions_filemanager.php"; '
                    . '$_SESSION["session_user_level"] = K_AUTH_RENAME_MEDIAFILE; '
                    . 'echo json_encode(f_rename_media_file("/tmp/../source.txt", "/tmp/../target.txt"));',
            ],
            __DIR__ . '/../admin/code',
        );

        self::assertSame(0, $status);
        self::assertSame('false', $output);
    }

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
        self::assertSame('1024 YB', f_format_file_size(1024 ** 9));
    }

    public function testFormatsLegacyZeroRepresentationsAsZero(): void
    {
        foreach ([0.0, '0', '00', '0.0', '0e2', false, null] as $zero) {
            self::assertSame('0', f_format_file_size($zero));
        }
    }
}
