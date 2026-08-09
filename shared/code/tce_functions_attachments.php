<?php

const TMF_ATTACHMENT_MAX_FILES = 3;
const TMF_ATTACHMENT_MAX_BYTES = 5_242_880;
const TMF_ATTACHMENT_ALLOWED_MIME = [
    'image/jpeg' => 'JPEG',
    'image/png' => 'PNG',
    'application/pdf' => 'PDF',
];

function F_tmf_attachment_table(): string
{
    return K_TABLE_PREFIX . 'testlog_attachments';
}

function f_tmf_attachment_directory(): string
{
    return rtrim(K_PATH_CACHE, '/\\') . DIRECTORY_SEPARATOR . 'attachments' . DIRECTORY_SEPARATOR;
}

/**
 * @return array{original_name:string,mime:string,size:int,sha256:string}
 */
function f_tmf_attachment_inspect(string $path, string $original_name): array
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('Загруженный файл недоступен.');
    }
    $size = filesize($path);
    if ($size === false || $size < 1 || $size > TMF_ATTACHMENT_MAX_BYTES) {
        throw new RuntimeException('Размер вложения должен быть от 1 байта до 5 МБ.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($path);
    if (!isset(TMF_ATTACHMENT_ALLOWED_MIME[$mime])) {
        throw new RuntimeException('Разрешены только JPEG, PNG и PDF.');
    }
    if (str_starts_with($mime, 'image/')) {
        set_error_handler(static fn (): bool => true);
        try {
            $image = getimagesize($path);
        } finally {
            restore_error_handler();
        }
        if (!is_array($image) || ($image['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Содержимое изображения повреждено или не соответствует типу.');
        }
    } elseif ($mime === 'application/pdf') {
        $header = file_get_contents($path, false, null, 0, 5);
        if ($header !== '%PDF-') {
            throw new RuntimeException('Содержимое PDF повреждено.');
        }
    }
    $original_name = basename(str_replace("\0", '', trim($original_name)));
    $original_name = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $original_name);
    $original_name = mb_substr((string) $original_name, 0, 255);
    if ($original_name === '') {
        $original_name = 'attachment.' . strtolower(TMF_ATTACHMENT_ALLOWED_MIME[$mime]);
    }
    return [
        'original_name' => $original_name,
        'mime' => $mime,
        'size' => (int) $size,
        'sha256' => hash_file('sha256', $path),
    ];
}

/**
 * @return array<int,array{name:string,tmp_name:string,error:int,size:int}>
 */
function f_tmf_attachment_normalize_uploads(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $temporary = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [$files['tmp_name'] ?? ''];
    $errors = is_array($files['error'] ?? null) ? $files['error'] : [$files['error'] ?? UPLOAD_ERR_NO_FILE];
    $sizes = is_array($files['size'] ?? null) ? $files['size'] : [$files['size'] ?? 0];
    $normalized = [];
    foreach ($names as $index => $name) {
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $normalized[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($temporary[$index] ?? ''),
            'error' => $error,
            'size' => (int) ($sizes[$index] ?? 0),
        ];
    }
    return $normalized;
}

/**
 * Store all submitted files atomically at the metadata level.
 *
 * @return array{status:string,count:int,message:string}
 */
function F_tmf_attachment_store_uploads(int $test_id, int $testlog_id, array $files): array
{
    global $db;
    $user_id = (int) ($_SESSION['session_user_id'] ?? 0);
    $owner_result = F_db_query(
        'SELECT q.question_type FROM ' . K_TABLE_TESTS_LOGS . ' tl'
        . ' INNER JOIN ' . K_TABLE_TEST_USER . ' tu ON tu.testuser_id=tl.testlog_testuser_id'
        . ' INNER JOIN ' . K_TABLE_QUESTIONS . ' q ON q.question_id=tl.testlog_question_id'
        . ' WHERE tl.testlog_id=' . $testlog_id . ' AND tu.testuser_test_id=' . $test_id
        . ' AND tu.testuser_user_id=' . $user_id . ' AND tu.testuser_status<4 LIMIT 1',
        $db,
    );
    if (!$owner_result || !($owner = F_db_fetch_array($owner_result)) || (int) $owner['question_type'] !== 3) {
        return ['status' => 'forbidden', 'count' => 0, 'message' => 'Вложения разрешены только к своему эссе.'];
    }
    $uploads = F_tmf_attachment_normalize_uploads($files);
    if ($uploads === []) {
        return ['status' => 'empty', 'count' => 0, 'message' => ''];
    }
    $existing = F_count_rows(
        F_tmf_attachment_table(),
        'WHERE attachment_testlog_id=' . $testlog_id,
    );
    if ($existing + count($uploads) > TMF_ATTACHMENT_MAX_FILES) {
        return [
            'status' => 'limit',
            'count' => 0,
            'message' => 'К одному ответу можно приложить не более трёх файлов.',
        ];
    }
    $candidates = [];
    try {
        foreach ($uploads as $upload) {
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                throw new RuntimeException('Один из файлов не был загружен полностью.');
            }
            $metadata = F_tmf_attachment_inspect($upload['tmp_name'], $upload['name']);
            if ($metadata['size'] !== $upload['size']) {
                throw new RuntimeException('Размер загруженного файла изменился.');
            }
            $candidates[] = ['upload' => $upload, 'metadata' => $metadata];
        }
    } catch (Throwable $exception) {
        return ['status' => 'invalid', 'count' => 0, 'message' => $exception->getMessage()];
    }
    $directory = F_tmf_attachment_directory();
    if (!is_dir($directory) && !mkdir($directory, 0o750, true) && !is_dir($directory)) {
        return ['status' => 'storage_error', 'count' => 0, 'message' => 'Хранилище вложений недоступно.'];
    }
    $stored_paths = [];
    if (!F_db_query('START TRANSACTION', $db)) {
        return ['status' => 'database_error', 'count' => 0, 'message' => 'Не удалось начать сохранение вложений.'];
    }
    try {
        foreach ($candidates as $candidate) {
            $stored_name = bin2hex(random_bytes(32));
            $stored_path = $directory . $stored_name;
            if (!move_uploaded_file($candidate['upload']['tmp_name'], $stored_path)) {
                throw new RuntimeException('Не удалось переместить вложение в защищённое хранилище.');
            }
            chmod($stored_path, 0o640);
            $stored_paths[] = $stored_path;
            $metadata = $candidate['metadata'];
            $sql = 'INSERT INTO ' . F_tmf_attachment_table() . ' ('
                . 'attachment_testlog_id,attachment_user_id,attachment_stored_name,'
                . 'attachment_original_name,attachment_mime,attachment_size,attachment_sha256,'
                . 'attachment_created_at) VALUES ('
                . $testlog_id . ',' . $user_id . ",'" . $stored_name . "','"
                . F_escape_sql($db, $metadata['original_name']) . "','"
                . F_escape_sql($db, $metadata['mime']) . "'," . $metadata['size'] . ",'"
                . $metadata['sha256'] . "','" . date(K_TIMESTAMP_FORMAT) . "')";
            if (!F_db_query($sql, $db)) {
                throw new RuntimeException('Не удалось записать сведения о вложении.');
            }
        }
        if (!F_db_query('COMMIT', $db)) {
            throw new RuntimeException('Не удалось завершить сохранение вложений.');
        }
    } catch (Throwable $exception) {
        F_db_query('ROLLBACK', $db);
        foreach ($stored_paths as $stored_path) {
            if (is_file($stored_path)) {
                unlink($stored_path);
            }
        }
        return ['status' => 'storage_error', 'count' => 0, 'message' => $exception->getMessage()];
    }
    return ['status' => 'stored', 'count' => count($candidates), 'message' => 'Вложения сохранены.'];
}

/**
 * @return array<int,array<string,mixed>>
 */
function F_tmf_attachment_list(int $testlog_id): array
{
    global $db;
    $attachments = [];
    $result = F_db_query(
        'SELECT * FROM ' . F_tmf_attachment_table()
        . ' WHERE attachment_testlog_id=' . $testlog_id . ' ORDER BY attachment_id',
        $db,
    );
    while ($result && ($row = F_db_fetch_array($result))) {
        $attachments[] = $row;
    }
    return $attachments;
}

/**
 * @return array{
 *     attachment_id: mixed,
 *     attachment_user_id: mixed,
 *     attachment_stored_name: mixed,
 *     attachment_original_name: mixed,
 *     attachment_mime: mixed,
 *     attachment_sha256: mixed,
 *     testuser_test_id: mixed
 * }|false
 */
function F_tmf_attachment_find(int $attachment_id): array|false
{
    global $db;
    $result = F_db_query(
        'SELECT a.*,tu.testuser_test_id FROM ' . F_tmf_attachment_table() . ' a'
        . ' INNER JOIN ' . K_TABLE_TESTS_LOGS . ' tl ON tl.testlog_id=a.attachment_testlog_id'
        . ' INNER JOIN ' . K_TABLE_TEST_USER . ' tu ON tu.testuser_id=tl.testlog_testuser_id'
        . ' WHERE a.attachment_id=' . $attachment_id . ' LIMIT 1',
        $db,
    );
    if (!$result) {
        return false;
    }
    $row = F_db_fetch_array($result);
    return is_array($row) ? $row : false;
}

function F_tmf_attachment_path(array $attachment): string
{
    $stored_name = (string) ($attachment['attachment_stored_name'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $stored_name) !== 1) {
        return '';
    }
    return F_tmf_attachment_directory() . $stored_name;
}

function F_tmf_attachment_html(int $testlog_id): string
{
    global $l;
    $attachments = F_tmf_attachment_list($testlog_id);
    if ($attachments === []) {
        return '';
    }
    $html = '<div class="essay-attachments"><strong>Вложения:</strong><ul>';
    foreach ($attachments as $attachment) {
        $name = htmlspecialchars(
            (string) $attachment['attachment_original_name'],
            ENT_QUOTES,
            $l['a_meta_charset'],
        );
        $html .= '<li><a href="tce_attachment.php?id=' . (int) $attachment['attachment_id']
            . '" target="_blank" rel="noopener">' . $name . '</a> ('
            . number_format((int) $attachment['attachment_size'] / 1024, 1, '.', ' ') . ' КБ)';
        if (str_starts_with((string) $attachment['attachment_mime'], 'image/')) {
            $html .= '<br /><a href="tce_attachment.php?id=' . (int) $attachment['attachment_id']
                . '" target="_blank" rel="noopener"><img src="tce_attachment.php?id='
                . (int) $attachment['attachment_id'] . '&amp;inline=1" alt="' . $name
                . '" style="max-width:240px;max-height:180px" /></a>';
        }
        $html .= '</li>';
    }
    return $html . '</ul></div>';
}

function F_tmf_attachment_send(array $attachment, bool $inline): never
{
    $path = F_tmf_attachment_path($attachment);
    if (
        $path === ''
        || !is_file($path)
        || !hash_equals((string) $attachment['attachment_sha256'], hash_file('sha256', $path))
    ) {
        http_response_code(404);
        exit();
    }
    $disposition = $inline && str_starts_with((string) $attachment['attachment_mime'], 'image/')
        ? 'inline'
        : 'attachment';
    $fallback = 'attachment-' . (int) $attachment['attachment_id'];
    header('Content-Type: ' . $attachment['attachment_mime']);
    header('Content-Length: ' . filesize($path));
    header(
        'Content-Disposition: ' . $disposition . '; filename="' . $fallback
        . '"; filename*=UTF-8\'\'' . rawurlencode((string) $attachment['attachment_original_name']),
    );
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit();
}

function F_tmf_attachment_delete_attempt(int $testuser_id): void
{
    global $db;
    $result = F_db_query(
        'SELECT a.* FROM ' . F_tmf_attachment_table() . ' a'
        . ' INNER JOIN ' . K_TABLE_TESTS_LOGS . ' tl ON tl.testlog_id=a.attachment_testlog_id'
        . ' WHERE tl.testlog_testuser_id=' . $testuser_id,
        $db,
    );
    $attachments = [];
    while ($result && ($attachment = F_db_fetch_array($result))) {
        $attachments[] = $attachment;
    }
    if (!F_db_query(
        'DELETE FROM ' . F_tmf_attachment_table()
        . ' WHERE attachment_testlog_id IN (SELECT testlog_id FROM ' . K_TABLE_TESTS_LOGS
        . ' WHERE testlog_testuser_id=' . $testuser_id . ')',
        $db,
    )) {
        throw new RuntimeException('Не удалось удалить сведения о вложениях.');
    }
    foreach ($attachments as $attachment) {
        $path = F_tmf_attachment_path($attachment);
        if ($path !== '' && is_file($path)) {
            unlink($path);
        }
    }
}

function F_tmf_attempt_archive(int $testuser_id): string
{
    global $db;
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('ZipArchive недоступен.');
    }
    $result = F_db_query(
        'SELECT tl.testlog_id,tl.testlog_question_id,tl.testlog_answer_text,'
        . 'tl.testlog_score,q.question_description FROM ' . K_TABLE_TESTS_LOGS . ' tl'
        . ' INNER JOIN ' . K_TABLE_QUESTIONS . ' q ON q.question_id=tl.testlog_question_id'
        . ' WHERE tl.testlog_testuser_id=' . $testuser_id . ' ORDER BY tl.testlog_id',
        $db,
    );
    $manifest = ['format' => 'openvsosh-attempt-archive-v1', 'testuser_id' => $testuser_id, 'answers' => []];
    $files = [];
    while ($result && ($answer = F_db_fetch_array($result))) {
        $entry = [
            'testlog_id' => (int) $answer['testlog_id'],
            'question_id' => (int) $answer['testlog_question_id'],
            'question' => trim(strip_tags((string) $answer['question_description'])),
            'answer_text' => (string) $answer['testlog_answer_text'],
            'score' => $answer['testlog_score'] === null ? null : (float) $answer['testlog_score'],
            'attachments' => [],
        ];
        foreach (F_tmf_attachment_list((int) $answer['testlog_id']) as $attachment) {
            $path = F_tmf_attachment_path($attachment);
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $safe_name = preg_replace(
                '/[^a-zA-Z0-9._-]+/u',
                '_',
                (string) $attachment['attachment_original_name'],
            );
            $archive_name = 'attachments/' . (int) $attachment['attachment_id'] . '-'
                . mb_substr((string) $safe_name, 0, 120);
            $files[$archive_name] = $path;
            $entry['attachments'][] = [
                'name' => $attachment['attachment_original_name'],
                'path' => $archive_name,
                'mime' => $attachment['attachment_mime'],
                'size' => (int) $attachment['attachment_size'],
                'sha256' => $attachment['attachment_sha256'],
            ];
        }
        $manifest['answers'][] = $entry;
    }
    $temporary = tempnam(sys_get_temp_dir(), 'openvsosh-attempt-');
    if ($temporary === false) {
        throw new RuntimeException('Не удалось создать архив.');
    }
    $zip = new ZipArchive();
    if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        unlink($temporary);
        throw new RuntimeException('Не удалось открыть архив.');
    }
    try {
        $zip->addFromString(
            'manifest.json',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
        foreach ($files as $archive_name => $path) {
            $zip->addFile($path, $archive_name);
        }
    } finally {
        $zip->close();
    }
    $bytes = file_get_contents($temporary);
    unlink($temporary);
    if (!is_string($bytes)) {
        throw new RuntimeException('Не удалось прочитать архив.');
    }
    return $bytes;
}
