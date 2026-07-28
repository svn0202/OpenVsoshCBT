<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../shared/code/tce_functions_language.php';

$default_file = $argv[1] ?? '';
$runtime_file = $argv[2] ?? '';
$cache_directory = $argv[3] ?? '';
if ($default_file === '' || $runtime_file === '') {
    fwrite(STDERR, "Usage: php sync_language.php DEFAULT_TMX RUNTIME_TMX [CACHE_DIRECTORY]\n");
    exit(2);
}

try {
    if (F_sync_tmx_translations($default_file, $runtime_file)) {
        $cache_files = glob(rtrim($cache_directory, '/') . '/*.php');
        foreach ($cache_files === false ? [] : $cache_files as $cache_file) {
            if (is_file($cache_file)) {
                unlink($cache_file);
            }
        }
        fwrite(STDOUT, "[tcexam] runtime translations updated; language caches invalidated.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[tcexam] WARNING: translation update failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
