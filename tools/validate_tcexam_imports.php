<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/code/tmf_word_import_lib.php';

if ($argc !== 3) {
    fwrite(STDERR, "usage: php validate_tcexam_imports.php IMPORT_ROOT OUTPUT_TSV\n");
    exit(2);
}

$root = realpath($argv[1]);
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "invalid import root\n");
    exit(2);
}

$rows = [[
    'file', 'status', 'module', 'topic', 'questions', 'answers', 'images',
    'single', 'multiple', 'text', 'order', 'matching', 'warnings',
]];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $item) {
    if (!$item->isFile() || strtolower($item->getExtension()) !== 'docx') {
        continue;
    }
    $path = $item->getPathname();
    $relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
    try {
        $parsed = (new TmfWordImporter($path))->parse();
        $types = array_fill_keys([1, 2, 3, 4, 5], 0);
        $answers = 0;
        foreach ($parsed['questions'] ?? [] as $question) {
            $type = (int) ($question['type'] ?? 0);
            if (array_key_exists($type, $types)) {
                ++$types[$type];
            }
            $answers += count($question['answers'] ?? []);
        }
        $warnings = $parsed['warnings'] ?? [];
        $rows[] = [
            $relative,
            'OK',
            (string) ($parsed['module'] ?? ''),
            (string) ($parsed['topic'] ?? ''),
            (string) count($parsed['questions'] ?? []),
            (string) $answers,
            (string) count($parsed['images'] ?? []),
            (string) $types[1],
            (string) $types[2],
            (string) $types[3],
            (string) $types[4],
            (string) $types[5],
            implode(' | ', array_map('strval', $warnings)),
        ];
    } catch (Throwable $exception) {
        $rows[] = [$relative, 'ERROR', '', '', '0', '0', '0', '0', '0', '0', '0', '0', $exception->getMessage()];
    }
}

$handle = fopen($argv[2], 'wb');
if ($handle === false) {
    fwrite(STDERR, "cannot open output\n");
    exit(2);
}
foreach ($rows as $row) {
    fputcsv($handle, $row, "\t", '"', "\\");
}
fclose($handle);

$ok = count(array_filter(array_slice($rows, 1), static fn(array $row): bool => $row[1] === 'OK'));
$errors = count($rows) - 1 - $ok;
fwrite(STDOUT, 'files=' . (count($rows) - 1) . " ok={$ok} errors={$errors}\n");
exit($errors === 0 ? 0 : 1);
