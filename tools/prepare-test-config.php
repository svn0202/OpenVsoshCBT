<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$created = [];

foreach (['shared', 'admin', 'public'] as $area) {
    $source = $root . '/' . $area . '/config.default';
    $destination = $root . '/' . $area . '/config';
    if (is_dir($destination)) {
        continue;
    }

    $created[] = $area;
    mkdir($destination, 0777, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $item) {
        $target = $destination . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            mkdir($target, 0777, true);
            continue;
        }
        copy($item->getPathname(), $target);
    }
}

$replace = static function (string $relativePath, array $replacements) use ($root): void {
    $path = $root . '/' . $relativePath;
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read test configuration: ' . $path);
    }
    $updated = str_replace(array_keys($replacements), array_values($replacements), $contents);
    if ($updated !== $contents && file_put_contents($path, $updated) === false) {
        throw new RuntimeException('Unable to write test configuration: ' . $path);
    }
};

if (in_array('shared', $created, true)) {
    $mainPath = str_replace('\\', '/', $root) . '/';
    $replace('shared/config/tce_paths.php', [
        "define('K_PATH_HOST', '');" => "define('K_PATH_HOST', 'http://localhost');",
        "define('K_PATH_TCEXAM', '');" => "define('K_PATH_TCEXAM', '/');",
        "define('K_PATH_MAIN', '');" => "define('K_PATH_MAIN', " . var_export($mainPath, true) . ');',
    ]);
    $replace('shared/config/tce_general_constants.php', [
        "define('K_RANDOM_SECURITY', 'CHANGE_THIS_K_RANDOM_SECURITY');"
            => "define('K_RANDOM_SECURITY', 'test-" . str_repeat('a1b2', 8) . "');",
    ]);
    $replace('shared/config/tce_config.php', [
        "define('K_BRUTE_FORCE_DELAY_RATIO', 2);" => "define('K_BRUTE_FORCE_DELAY_RATIO', 0);",
    ]);
}

foreach (['admin', 'public'] as $area) {
    if (!in_array($area, $created, true)) {
        continue;
    }
    $replace($area . '/config/tce_config.php', [
        "require_once '../../shared/code/tce_db_connect.php';"
            => "if (getenv('TCEXAM_TEST_MODE') !== '1') {\n"
                . "    require_once '../../shared/code/tce_db_connect.php';\n}",
    ]);
}
